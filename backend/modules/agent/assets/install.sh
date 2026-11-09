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
    curl --fail --silent --show-error --location \
        --proto '=https' --proto-redir '=https' \
        --connect-timeout 10 --max-time 300 --retry 3 \
        --output "$2" "$1"
}

case "$(uname -s)" in
    Linux) os="unknown-linux-gnu" ;;
    Darwin) os="apple-darwin" ;;
    *) die "Only Linux and macOS are supported" ;;
esac
case "$(uname -m)" in
    x86_64|amd64) arch="x86_64" ;;
    arm64|aarch64) arch="aarch64" ;;
    *) die "Unsupported CPU architecture: $(uname -m)" ;;
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

# Stage on the same filesystem for atomic replacement.
work_dir="$(mktemp -d "$bin_dir/.nvn-agent-install.XXXXXX")"
trap 'rm -rf -- "$work_dir"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ -z "$version" ]]; then
    download "$base_url/stable.txt" "$work_dir/stable.txt"
    version="$(<"$work_dir/stable.txt")"
fi
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] ||
    die "Invalid release version: $version"

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
printf '\nInstalled: %s\n%s\n' "$destination" "$reported"
case ":${PATH:-}:" in
    *":$bin_dir:"*) ;;
    *) printf '\nAdd this directory to your shell PATH:\n  %s\n' "$bin_dir" ;;
esac
printf '\nRun nvn-agent to get started.\n'