#!/usr/bin/env bash
set -euo pipefail

die() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

base_url="${NV_AGENT_BASE_URL:-https://backend.nvnhan0810.com/agent}"
base_url="${base_url%/}"
version="${NV_AGENT_VERSION:-}"
bin_dir="${NV_AGENT_BIN_DIR:-${HOME:?HOME is required}/.local/bin}"

case "$base_url" in
    https://*) ;;
    *) die "Download URL must use HTTPS" ;;
esac

for tool in curl uname mktemp chmod mv mkdir rm; do
    command -v "$tool" >/dev/null 2>&1 ||
        die "Required command not found: $tool"
done

if command -v sha256sum >/dev/null 2>&1; then
    hash_tool="sha256sum"
elif command -v shasum >/dev/null 2>&1; then
    hash_tool="shasum"
else
    die "Install sha256sum or shasum before continuing"
fi

download() {
    local max_time=300 retries=3
    if [[ "${NV_AGENT_AUTO_CHECK:-}" == 1 ]]; then
        max_time=15
        retries=0
    fi
    curl --fail --silent --show-error --location \
        --proto '=https' --proto-redir '=https' \
        --connect-timeout 5 --max-time "$max_time" --retry "$retries" \
        --output "$2" "$1"
}

case "$(uname -s)" in
    Linux) os="unknown-linux-gnu"; platform="linux" ;;
    Darwin) os="apple-darwin"; platform="macos" ;;
    *) die "Only Linux and macOS are supported" ;;
esac
case "$platform:$(uname -m)" in
    linux:x86_64|linux:amd64) arch="x86_64" ;;
    macos:arm64|macos:aarch64) arch="aarch64" ;;
    *) die "Unsupported platform: $platform $(uname -m). Releases support Linux x86_64 (including WSL) and macOS Apple Silicon (M series) only. On Apple Silicon, run from a native ARM64 terminal." ;;
esac

mkdir -p -- "$bin_dir"
bin_dir="$(cd -- "$bin_dir" && pwd -P)"
destination="$bin_dir/nvn-agent"

check_destination() {
    [[ ! -L "$destination" ]] ||
        die "$destination is a symbolic link; remove it or choose another directory"
    if [[ -e "$destination" && ! -f "$destination" ]]; then
        die "$destination exists and is not a regular file"
    fi
}
check_destination

# Serialize installers so simultaneous sessions cannot overwrite a newer release.
lock_dir="$bin_dir/.nvn-agent-update.lock"
if ! mkdir -- "$lock_dir" 2>/dev/null; then
    die "Another installer is running ($lock_dir)"
fi
trap 'rmdir -- "$lock_dir"' EXIT

# Stage on the same filesystem for atomic replacement.
work_dir="$(mktemp -d "$bin_dir/.nvn-agent-install.XXXXXX")"
trap 'rm -rf -- "$work_dir"; rmdir -- "$lock_dir"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ -z "$version" ]]; then
    download "$base_url/stable-$platform.txt" "$work_dir/stable.txt"
    version="$(<"$work_dir/stable.txt")"
fi
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] ||
    die "Invalid release version: $version"

# Read the actual installation under the lock; never trust a running older process.
if [[ -x "$destination" ]]; then
    installed_report="$("$destination" --version)" || die "Cannot read installed version"
    [[ "$installed_report" =~ ^nvn-agent\ ([0-9]+\.[0-9]+\.[0-9]+)$ ]] || die "Invalid installed version: $installed_report"
    NV_AGENT_CURRENT_VERSION="${BASH_REMATCH[1]}"
fi
if [[ -n "${NV_AGENT_CURRENT_VERSION:-}" ]]; then
    [[ "$NV_AGENT_CURRENT_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "Invalid current version"
    IFS=. read -r current_major current_minor current_patch <<< "$NV_AGENT_CURRENT_VERSION"
    IFS=. read -r next_major next_minor next_patch <<< "$version"
    if (( 10#$next_major < 10#$current_major ||
          (10#$next_major == 10#$current_major && 10#$next_minor < 10#$current_minor) ||
          (10#$next_major == 10#$current_major && 10#$next_minor == 10#$current_minor && 10#$next_patch <= 10#$current_patch) )); then
        printf 'nvn-agent %s is up to date.\n' "$NV_AGENT_CURRENT_VERSION"
        exit 0
    fi
fi

artifact="nvn-agent-${arch}-${os}"
release_url="$base_url/releases/$version/$artifact"
printf 'Downloading nvn-agent %s for %s-%s...\n' "$version" "$arch" "$os"
download "$release_url" "$work_dir/nvn-agent"
download "$release_url.sha256" "$work_dir/checksum"

expected="$(<"$work_dir/checksum")"
[[ "$expected" =~ ^[0-9a-f]{64}$ ]] || die "Invalid SHA-256 checksum file"
if [[ "$hash_tool" == "sha256sum" ]]; then
    actual="$(sha256sum "$work_dir/nvn-agent")"
else
    actual="$(shasum -a 256 "$work_dir/nvn-agent")"
fi
actual="${actual%% *}"
[[ "$actual" == "$expected" ]] ||
    die "Checksum verification failed; existing installation was preserved"

chmod 755 "$work_dir/nvn-agent"
reported="$("$work_dir/nvn-agent" --version)" ||
    die "Downloaded binary cannot run on this machine"
[[ "$reported" == "nvn-agent $version" ]] ||
    die "Unexpected binary version: $reported"

check_destination
mv -f -- "$work_dir/nvn-agent" "$destination"
# Persist only after a verified binary has been installed. The next process
# acknowledges this receipt once it is running the recorded version.
if [[ -n "${NV_AGENT_UPDATE_RECEIPT:-}" && -n "${NV_AGENT_CURRENT_VERSION:-}" ]]; then
    receipt_tmp="$NV_AGENT_UPDATE_RECEIPT.tmp.$$"
    if printf '%s\n%s\n' "$NV_AGENT_CURRENT_VERSION" "$version" > "$receipt_tmp"; then
        mv -f -- "$receipt_tmp" "$NV_AGENT_UPDATE_RECEIPT"
    fi
fi
printf '\nInstalled: %s\n%s\n' "$destination" "$reported"
case ":${PATH:-}:" in
    *":$bin_dir:"*) ;;
    *) printf '\nAdd this directory to your shell PATH:\n  %s\n' "$bin_dir" ;;
esac
printf '\nRestart nvn-agent to use this release.\n'
