#!/usr/bin/env bash
# Run on the deployment host, from any working directory.
set -Eeuo pipefail

usage() {
  cat <<'HELP'
Usage: ./deploy.sh [--dry-run] [--all] [--sudo]

Fetch/pull the current branch's upstream, then build only changed applications.
  --dry-run  Fetch and display the plan; do not pull, build, or restart services.
  --all      Build every configured application, even without remote changes.
  --sudo     Run supervisorctl through sudo; builds still run as your user.

Backend: cargo build --release --locked --workspace
         restart nvnhan0810-backend and nvnhan0810-backend-queue
Frontend: npm i --include=dev --no-save, then npm run build
Portfolio SSR: restart nvnhan0810-com after a successful build
HELP
}

log() { printf '[deploy] %s\n' "$*"; }
die() { printf '[deploy] ERROR: %s\n' "$*" >&2; exit 1; }
require_command() { command -v "$1" >/dev/null 2>&1 || die "Missing command: $1"; }
print_command() { printf '[deploy] '; printf '%q ' "$@"; printf '\n'; }

main() {
  local dry_run=false force_all=false use_sudo=false option
  for option in "$@"; do
    case "$option" in
      --dry-run) dry_run=true ;;
      --all) force_all=true ;;
      --sudo) use_sudo=true ;;
      -h|--help) usage; return ;;
      *) die "Unknown argument: $option (see --help)" ;;
    esac
  done

  local repo_root
  repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
  cd -- "$repo_root"
  require_command git
  require_command flock
  [[ "$(git rev-parse --show-toplevel)" == "$repo_root" ]] || die 'Place deploy.sh at the repository root.'

  local state_dir branch branch_key checkpoint remote remote_branch upstream
  state_dir="$(git rev-parse --git-path deploy-state)"
  mkdir -p -- "$state_dir"
  exec 9>"$state_dir/lock"
  flock -n 9 || die 'Another deployment is already running.'
  branch="$(git symbolic-ref --quiet --short HEAD)" || die 'Detached HEAD: check out a deployment branch first.'
  remote="$(git config --get "branch.$branch.remote")" || die 'Current branch has no upstream remote.'
  remote_branch="$(git config --get "branch.$branch.merge")" || die 'Current branch has no upstream branch.'
  [[ "$remote" != '.' ]] || die 'The upstream must be a remote, not a local branch.'
  upstream="$(git rev-parse --abbrev-ref --symbolic-full-name '@{upstream}')" || die 'Cannot resolve upstream.'
  [[ -z "$(git status --porcelain --untracked-files=normal)" ]] || die 'Checkout has local changes. Commit or move them before deploying.'

  log "Fetching $upstream"
  git fetch --prune "$remote"
  local before_head remote_head baseline after_head
  before_head="$(git rev-parse HEAD)"
  remote_head="$(git rev-parse '@{upstream}^{commit}')"
  git merge-base --is-ancestor "$before_head" "$remote_head" || die 'Local branch is ahead of or diverges from upstream. Resolve it before deploying.'

  branch_key="$(printf '%s' "$branch" | git hash-object --stdin)"
  checkpoint="$state_dir/$branch_key.last-success"
  baseline="$before_head"
  if [[ -f "$checkpoint" ]]; then
    local -a saved_state=()
    mapfile -t saved_state < "$checkpoint"
    baseline="${saved_state[0]:-}"
    [[ "${saved_state[1]:-}" != all ]] || force_all=true
    [[ "$baseline" =~ ^[a-f0-9]{40}([a-f0-9]{24})?$ ]] || die "Invalid deployment checkpoint: $checkpoint"
    git cat-file -e "$baseline^{commit}" || die 'Deployment checkpoint commit is unavailable.'
    git merge-base --is-ancestor "$baseline" "$remote_head" || die 'Upstream history no longer contains the last deployment. Resolve the history/checkpoint first.'
  fi
  if [[ "$before_head" == "$remote_head" && "$baseline" == "$remote_head" && "$force_all" == false ]]; then
    log 'Already up to date; nothing to deploy. Use --all for a full rebuild.'
    return
  fi

  # Save the old successful revision BEFORE pulling, so a failed build can be retried.
  if [[ "$dry_run" == false ]]; then
    local pending_mode=changed
    [[ "$force_all" == false ]] || pending_mode=all
    printf '%s\n%s\n' "$baseline" "$pending_mode" > "$checkpoint"
    log "Pulling $upstream (fast-forward only)"
    git pull --ff-only --no-rebase "$remote" "$remote_branch"
    after_head="$(git rev-parse HEAD)"
  else
    after_head="$remote_head"
    print_command git pull --ff-only --no-rebase "$remote" "$remote_branch"
  fi

  local backend_changed=false path app
  local -a frontend_apps=(portfolio identity foreign wallets todos)
  local -A changed_frontends=()
  [[ "$force_all" == false ]] || backend_changed=true
  while IFS= read -r -d '' path; do
    [[ "$path" != backend/* ]] || backend_changed=true
    for app in "${frontend_apps[@]}"; do
      [[ "$path" != "$app/"* ]] || changed_frontends["$app"]=1
    done
  done < <(git diff --no-renames --name-only -z "$baseline" "$after_head")
  if [[ "$force_all" == true ]]; then
    for app in "${frontend_apps[@]}"; do changed_frontends["$app"]=1; done
  fi

  local -a supervisor=(supervisorctl) services=()
  [[ "$use_sudo" == false ]] || supervisor=(sudo supervisorctl)
  if [[ "$backend_changed" == true ]]; then
    services+=(nvnhan0810-backend nvnhan0810-backend-queue)
  fi
  [[ -z "${changed_frontends[portfolio]:-}" ]] || services+=(nvnhan0810-com)

  log "Compare deployed ${baseline:0:12} -> ${after_head:0:12}"
  if [[ "$dry_run" == false ]]; then
    [[ "$backend_changed" == false ]] || require_command cargo
    ((${#changed_frontends[@]} == 0)) || require_command npm
    if ((${#services[@]})); then
      require_command supervisorctl
      [[ "$use_sudo" == false ]] || require_command sudo
    fi
  fi

  # Finish every selected build before restarting any service.
  if [[ "$backend_changed" == true ]]; then
    log 'Build backend'
    if [[ "$dry_run" == true ]]; then
      log 'Working directory: backend'
      print_command cargo build --release --locked --workspace
    else
      (cd backend && cargo build --release --locked --workspace)
    fi
  fi
  for app in "${frontend_apps[@]}"; do
    [[ -n "${changed_frontends[$app]:-}" ]] || continue
    log "Install dependencies and build $app"
    if [[ "$dry_run" == true ]]; then
      log "Working directory: $app"
      print_command npm i --include=dev --no-save
      print_command npm run build
    else
      [[ -f "$app/package.json" ]] || die "Missing $app/package.json after pull. Check the application list in deploy.sh."
      (cd "$app" && npm i --include=dev --no-save && npm run build)
    fi
  done
  if ((${#services[@]})); then
    if [[ "$dry_run" == true ]]; then
      print_command "${supervisor[@]}" restart "${services[@]}"
    else
      "${supervisor[@]}" restart "${services[@]}"
    fi
  else
    log 'No Supervisor services need a restart.'
  fi
  if [[ "$dry_run" == true ]]; then
    log 'Dry run complete; checkout and deployment checkpoint unchanged.'
  else
    printf '%s\n' "$after_head" > "$checkpoint.tmp.$$"
    mv -- "$checkpoint.tmp.$$" "$checkpoint"
    log "Deployment complete at ${after_head:0:12}"
  fi
}

trap 'deploy_exit_code=$?; printf "[deploy] Failed (exit %s). The deployment checkpoint was not advanced; rerun to retry.\n" "$deploy_exit_code" >&2; exit "$deploy_exit_code"' ERR
main "$@"
