# Agent installation and update plan

Status: proposed implementation. This document records the agreed design; it does not implement the feature.

## Objective and scope

Allow users to install or update `nvn-agent` by downloading an installer from `https://backend.nvnhan0810.com`. Users should not need Rust or `sudo`.

The existing API gateway remains the only HTTP entry point. The backend `modules/agent` module owns distribution configuration, route definitions, handlers, and the installer source. The separate `agent/cli` project owns the CLI source, binary builds, and release packaging.

Version one supports Linux and macOS on x86_64 and ARM64 where release artifacts have been built and tested. Rerunning the installer updates to the current stable release. Scheduled background updates and a CLI `update` command are later enhancements.

## Current workspace findings

- `backend/modules/agent/Cargo.toml` already declares the `agent-module` package.
- Its `src/lib.rs`, `src/config.rs`, and `src/routes.rs` currently contain no implementation.
- The backend workspace does not yet list `modules/agent` as a member.
- The gateway already contains `agent::AgentConfig` and `/agent` mounting calls. Its dependency declaration needs correction (see below); keep the existing mount rather than adding a duplicate.
- The workspace already defines `tower-http`; the module inherits it and enables `fs`.
- `agent/cli` builds the executable `nvn-agent`; its Cargo package is named `nvnhan0810-agent`.

## Ownership and directory layout

```text
backend/
├── apps/api-gateway/
│   ├── Cargo.toml                 # Depend on agent-module
│   └── src/main.rs                # Mount module router
├── modules/agent/
│   ├── Cargo.toml
│   ├── assets/install.sh          # Installer source of truth
│   └── src/
│       ├── lib.rs                 # Public module API
│       ├── config.rs              # Distribution settings
│       └── routes.rs              # Relative distribution routes
└── docs/agent-install-update-plan.md

agent/cli/
├── Cargo.toml                     # Release version
├── src/presentation/cli.rs        # Enable --version
└── scripts/
    ├── install.sh                # Existing local source installer
    └── package-release.sh        # Build and package release artifacts
```

## Shared backend storage configuration

Set one backend-wide environment variable:

```env
STORAGE_DIR=/srv/nvn-storage
```

Load it once through the backend's shared configuration layer at startup and derive public and private roots in `StorageConfig`, and pass the appropriate root to each module. Require a non-empty absolute path and fail startup if it is missing or invalid. Do not silently fall back to the working directory. All modules derive their storage paths from this root using `Path::join` and fixed module-relative paths.

Storage layout:

```text
/srv/nvn-storage/                         # STORAGE_DIR: private server filesystem root
├── public/
│   └── agent/
│       └── distributions/                 # Explicitly served at /agent/*
│           ├── install.sh
│           ├── stable-<platform>.txt
│           └── releases/
└── private/
    └── uploads/                          # Future uploads; no static HTTP mount
```

The directory name `public` does not expose files automatically. Each module explicitly mounts only its intended public subdirectory. Never mount the entire storage root, the entire public root, or the private root as the agent file service. Future private downloads must use authenticated handlers that check access to the requested file.

`STORAGE_DIR` is server configuration. Users installing the CLI do not set it. The public URLs remain `/agent/*`; the filesystem prefix does not appear in download URLs. Remove the former module-specific `NV_AGENT_DISTRIBUTION_DIR` setting.

Deployment provides this dedicated public distribution directory:

```text
/srv/nvn-storage/public/agent/distributions/
├── install.sh                     # Copied from module assets at deployment
├── stable-<platform>.txt                     # For example: 0.1.0 followed by a newline
└── releases/
    └── 0.1.0/
        ├── nvn-agent-x86_64-unknown-linux-gnu
        ├── nvn-agent-x86_64-unknown-linux-gnu.sha256
        ├── nvn-agent-aarch64-apple-darwin
        └── nvn-agent-aarch64-apple-darwin.sha256
```

The example lists two artifacts; publish additional targets only after validation. Keep release binaries out of source control. The API process needs read-only access to this directory.

## Public routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/agent/install.sh` | Download installer |
| GET | `/agent/stable-<platform>.txt` | Resolve the current stable version |
| GET | `/agent/releases/{version}/{artifact}` | Download immutable binary |
| GET | `/agent/releases/{version}/{artifact}.sha256` | Download checksum |

All routes use `https://backend.nvnhan0810.com`. They must be accessible before the user installs or authenticates the CLI. The reverse proxy forwards `/agent/*` to the existing gateway, preserving the path.

## Backend integration details

Add `modules/agent` to the workspace members. Define the shared dependency using the version already declared by the gateway:

```toml
# backend/Cargo.toml, under [workspace.dependencies]
tower-http = { version = "0.7.1", features = ["trace", "cors", "compression-full"] }
```

The gateway can then use `tower-http = { workspace = true }`, while the module adds the `fs` feature:

```toml
# modules/agent/Cargo.toml
[dependencies]
axum = { workspace = true }
tower-http = { workspace = true, features = ["fs"] }
```

Proposed `modules/agent/src/config.rs`:

```rust
use std::path::{Path, PathBuf};

#[derive(Debug, Clone)]
pub struct AgentConfig {
    pub distribution_dir: PathBuf,
}

impl AgentConfig {
    pub fn new(public_storage_dir: &Path) -> Self {
        Self {
            distribution_dir: public_storage_dir.join("agent").join("distributions"),
        }
    }
}
```

The shared app-config layer owns `STORAGE_DIR`; the agent module owns the relative suffix `agent/distributions` beneath the public root.

### Load STORAGE_DIR through ConfigSource

Set this in the backend `.env` used when starting the gateway, or in the deployment environment:

```env
STORAGE_DIR=/srv/nvn-storage
```

The existing `ConfigSource::load()` reads `.env` relative to the process working directory and overlays process environment variables, which take precedence. It does not copy `.env` values into the process environment. Use `ConfigSource` rather than `std::env::var_os()` so values defined only in `.env` are available.

Create `backend/crates/app-config/src/storage_config.rs`:

```rust
use std::{io, path::PathBuf};

use crate::ConfigSource;

#[derive(Debug, Clone)]
pub struct StorageConfig {
    pub public_dir: PathBuf,
    pub private_dir: PathBuf,
}

impl StorageConfig {
    pub fn from_source(source: &ConfigSource) -> io::Result<Self> {
        let value = source.required("STORAGE_DIR").map_err(|message| {
            io::Error::new(io::ErrorKind::InvalidInput, message)
        })?;

        let root = PathBuf::from(value);
        if !root.is_absolute() {
            return Err(io::Error::new(
                io::ErrorKind::InvalidInput,
                "STORAGE_DIR must be a non-empty absolute path",
            ));
        }

        Ok(Self {
            public_dir: root.join("public"),
            private_dir: root.join("private"),
        })
    }
}
```

`required()` rejects missing, empty, and whitespace-only values. This loader rejects relative paths. It does not create directories, check permissions, or expose files publicly; deployment prepares the directories.

Update `backend/crates/app-config/src/lib.rs`, preserving existing exports:

```rust
mod config_source;
mod database_config;
mod storage_config;

pub use config_source::ConfigSource;
pub use database_config::DatabaseConfig;
pub use storage_config::StorageConfig;
```

### Add storage to gateway configuration

In `backend/apps/api-gateway/src/config/app_config.rs`, keep existing imports needed by other fields. Replace the previous `storage_dir` field with this field alongside the existing fields in `AppConfig`:

```rust
pub storage: app_config::StorageConfig,
```

Add this initializer inside the existing `Ok(Self { ... })` in `AppConfig::from_source()`:

```rust
storage: app_config::StorageConfig::from_source(source)
    .map_err(|error| error.to_string())?,
```

The gateway loads its existing `AppConfig` once at startup. Missing or invalid storage configuration now fails startup through the existing configuration error handling. Modules receive the appropriate public or private root instead of reading environment variables independently. This replaces the earlier `load_storage_dir()` API.

In the same file, add this pair to the values array in the existing `source_with_cors()` test fixture:

```rust
("STORAGE_DIR", "/tmp/nvn-storage"),
```

When implementing, verify missing, empty, whitespace-only, and relative values fail; an absolute root succeeds. Verify `.env`-only configuration is available through `ConfigSource`. Database-only migrations do not need this setting.

### Pass storage to the agent module

Use the `AgentConfig::new()` constructor above. In `backend/apps/api-gateway/src/main.rs`, replace the existing `agent::AgentConfig::from_env()` call with:

```rust
let agent_config = agent::AgentConfig::new(&config.storage.public_dir);
```

Here `config` is the existing gateway `AppConfig`. Keep its existing router mount:

```rust
.nest("/agent", agent::routes(agent_config))
```

Other modules accept the appropriate public or private root and append fixed module-relative paths. For example, a future upload module receives `&config.storage.private_dir` and derives `private_dir.join("uploads")`. Private upload handlers must authenticate users and authorize file access; do not expose uploads with `ServeDir`. Only `$STORAGE_DIR/public/agent/distributions` is served at `/agent/`; the storage root remains private. All files in distribution must be intended for public download. Do not place symlinks to private files inside the public directory.

Proposed `modules/agent/src/routes.rs`:

```rust
use axum::Router;
use tower_http::services::ServeDir;

use crate::config::AgentConfig;

pub fn routes(config: AgentConfig) -> Router {
    Router::new().fallback_service(
        ServeDir::new(config.distribution_dir)
            .append_index_html_on_directories(false),
    )
}
```

The fallback serves paths relative to the module mount point. Verify the nesting behavior with gateway integration tests. All files placed in the configured directory are public; keep it dedicated to distribution assets.

Proposed `modules/agent/src/lib.rs`:

```rust
pub mod config;
pub mod routes;

pub use config::AgentConfig;
pub use routes::routes;
```

Add this gateway dependency:

```toml
agent-module = { path = "../../modules/agent" }
```

In the existing gateway router construction, before the existing middleware layers:

```rust
let agent_config = agent_module::AgentConfig::new(&config.storage.public_dir);

// Add this call to the existing Router builder:
.nest("/agent", agent_module::routes(agent_config))
```

Do not create another executable or listener. Keep distribution route behavior inside the module. Review existing authentication and rate limiting middleware so public downloads work and normal installer requests are not rejected.

## Installer implementation requirements

These are developer implementation steps. Users do not need an existing agent installation or an installer configuration file. The full proposed script appears below.

Implement `modules/agent/assets/install.sh` in Bash with `set -euo pipefail`.

Configuration:

```bash
base_url="${NV_AGENT_BASE_URL:-https://backend.nvnhan0810.com/agent}"
base_url="${base_url%/}"
version="${NV_AGENT_VERSION:-}"
bin_dir="${NV_AGENT_BIN_DIR:-${HOME:?HOME is required}/.local/bin}"
```

Installation sequence:

1. Require HTTPS, `curl`, platform utilities, and either `sha256sum` or `shasum`.
2. Detect OS and CPU using `uname`; reject unsupported combinations clearly.
3. Create the destination directory and a temporary staging directory inside it, with cleanup on exit. Reject a destination that is a directory.
4. When no version is pinned, download `stable-<platform>.txt` once. Accept stable numeric versions matching `^[0-9]+\.[0-9]+\.[0-9]+$`.
5. Construct `nvn-agent-${arch}-${os}` and download the binary and checksum from the resolved version directory.
6. Require a checksum file containing exactly a 64-character hexadecimal SHA-256 digest plus an optional trailing newline. Compare the digest with the downloaded binary, using the lowercase digest format produced by the packaging script.
7. Set executable permissions and run the staged binary with `--version`. Require `nvn-agent <resolved-version>` before installation.
8. Replace `$bin_dir/nvn-agent` using a same-filesystem atomic rename. Preserve the installed executable if any preceding step fails. Handle or reject existing destination symlinks explicitly.
9. Print the installed path and version. If the directory is absent from `PATH`, explain how to add it without changing shell startup files automatically.

Use bounded download timeouts and retries, and restrict both initial URLs and redirects to HTTPS:

```bash
curl --fail --silent --show-error --location \
  --proto '=https' --proto-redir '=https' \
  --connect-timeout 10 --max-time 300 --retry 3 \
  "$url" --output "$output"
```

The installer must preserve configuration and keyring credentials. SHA-256 verifies integrity against the checksum served by the trusted HTTPS backend; it is not an independent release signature.

An existing process continues running its loaded version. The replacement takes effect on the next launch. For version one, pinned installation may intentionally downgrade; document this behavior.

## CLI version and release packaging

Enable Clap version reporting in `agent/cli/src/presentation/cli.rs`:

```rust
#[derive(Parser)]
#[command(
    name = "nvn-agent",
    version,
    about = "AI Agent CLI with TUI & Vim-like features"
)]
pub struct Cli {
    #[command(subcommand)]
    pub command: Option<Commands>,
}
```

Keep the package version in `agent/cli/Cargo.toml` synchronized with the published release version. Verify that `nvn-agent --version` works before connection migration or credential initialization.

Create `agent/cli/scripts/package-release.sh TARGET VERSION` to:

1. Validate the version and restrict the target to supported release targets.
2. Build with `cargo build --locked --release --target "$target" --bin nvn-agent`, using explicit manifest and target directory paths.
3. Copy the binary to `dist/$version/nvn-agent-$target`.
4. Write its bare SHA-256 digest to the corresponding `.sha256` file.
5. Check that the package version matches the requested release version, and smoke-test version output on a compatible runner.

| Platform | Rust target |
| --- | --- |
| Linux Intel/AMD | `x86_64-unknown-linux-gnu` |
| Linux ARM64 | `aarch64-unknown-linux-gnu` |
| macOS Intel | `x86_64-apple-darwin` |
| macOS Apple Silicon | `aarch64-apple-darwin` |

Build on compatible runners with required Rust targets and native dependencies. Validate PTY and keyring behavior. Choose and document a minimum Linux/glibc version, and build against that baseline. Do not assume installing a Rust target supplies a working cross-compilation toolchain.

## Deployment and publishing

Set `STORAGE_DIR=/srv/nvn-storage` for the backend. The agent module serves only `$STORAGE_DIR/public/agent/distributions`, never the shared storage root or all of the agent storage directory. If containerized, mount storage at the configured container path. Give the API process read-only access to agent distribution files; grant other modules write access to their own subdirectories as needed. The release publisher writes through a separate deployment path. Do not make the entire shared root read-only if other modules need to write to it.

Publishing order:

1. Set the CLI version and build/test the supported targets.
2. Package all release binaries and checksums.
3. Publish the complete version directory without changing existing releases.
4. Deploy the installer from the module assets.
5. Smoke-test public downloads and a pinned installation.
6. Atomically replace `stable-<platform>.txt` to promote the release.

Configure the module or reverse proxy to revalidate or avoid caching `install.sh` and `stable-<platform>.txt`. Immutable versioned artifacts may use long-lived caching. Roll back the default release by atomically pointing `stable-<platform>.txt` to a previously verified version; installed clients change only when installation runs again.

## User commands

Download the installer into a private temporary directory and run it:

```bash
(
  set -eu
  installer_dir="$(mktemp -d)"
  trap 'rm -rf -- "$installer_dir"' EXIT
  curl --fail --silent --show-error --location \
    --proto '=https' --proto-redir '=https' \
    https://backend.nvnhan0810.com/agent/install.sh \
    --output "$installer_dir/install.sh"
  bash "$installer_dir/install.sh"
)
```

Use the same command to update. To pin a release, replace its final Bash invocation with:

```bash
NV_AGENT_VERSION=0.1.0 bash "$installer_dir/install.sh"
```

## Implementation order and acceptance checks

1. Register the backend module and resolve dependency inheritance.
2. Implement module configuration, routes, installer assets, and gateway mounting.
3. Enable CLI version output and add packaging.
4. Configure deployment storage, publishing, and cache behavior.
5. Validate the complete flow before announcing the public command.

Required checks:

- Missing, empty, or relative `STORAGE_DIR` fails startup; a custom absolute root resolves public agent storage under `public/agent/distributions` and private uploads under `private/uploads`. Verify private files cannot be downloaded through `/agent/*`, including traversal requests. Files belonging to other modules are not exposed by `/agent/*`.
- Gateway routes serve the expected installer, version pointer, binaries, and checksums without login; missing files return 404.
- Fresh installation, repeated installation, update, and pinned installation succeed on supported targets.
- Missing artifacts, bad checksums, malformed versions, network failures, and incompatible binaries leave the installed executable intact.
- Paths with spaces, unsupported platforms, destination symlinks, and absent PATH entries have predictable behavior.
- Configuration and credentials remain intact; updating while the CLI runs does not interrupt that process.
- A release promotion during installation cannot mix artifacts from different versions.
- Cache behavior allows new stable versions to become visible as intended.

Run appropriate Rust checks and integration tests when implementing. The code snippets in this plan have not been compiled or executed.

## Later enhancements

- Add `nvn-agent update`, dispatching before connection migration and credential initialization.
- Add release signatures if independent publisher verification is needed.
- Consider background update checks, explicit downgrade policy, and Windows support as separate work.


## Complete proposed installer code

File: `backend/modules/agent/assets/install.sh`. This is source code to add when implementing the feature; deployment copies it into the public distribution directory.

```bash
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
    Linux) os="unknown-linux-gnu"; platform="linux" ;;
    Darwin) os="apple-darwin"; platform="macos" ;;
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
    download "$base_url/stable-$platform.txt" "$work_dir/stable-<platform>.txt"
    version="$(<"$work_dir/stable-<platform>.txt")"
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
```

The checksum file contains a lowercase SHA-256 digest with a trailing newline. The installer strips trailing newlines when reading it. Use a destination directory owned by the installing user.

## Exact gateway dependency correction

The current gateway manifest contains an incomplete declaration using `version:` and the dependency name `agent`, whereas the package is named `agent-module`. Replace that declaration with:

```toml
# backend/apps/api-gateway/Cargo.toml, under [dependencies]
agent = { package = "agent-module", path = "../../modules/agent" }
```

This alias matches the gateway code already present:

```rust
let agent_config = agent::AgentConfig::new(&config.storage.public_dir);

// Keep this existing call in the router builder before middleware:
.nest("/agent", agent::routes(agent_config))
```

The earlier `agent-module` dependency and `agent_module::...` examples are an alternative naming convention. Use the `agent` alias consistently for this workspace. Do not add both dependencies or both mount calls.

Add `"modules/agent",` to the existing workspace `members` array. Keep the existing shared `tower-http` declaration; the module manifest already requests its `fs` feature. The configuration, router, and library code in the backend section above are the complete proposed contents of those three module source files.

## Complete proposed release packaging code

File: `agent/cli/scripts/package-release.sh`.

This script uses Python 3 to read Cargo metadata. Build and run it on a runner compatible with the selected target; the version smoke check executes the resulting binary locally.

```bash
#!/usr/bin/env bash
set -euo pipefail

die() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

target="${1:?Usage: package-release.sh TARGET VERSION}"
version="${2:?Usage: package-release.sh TARGET VERSION}"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "Invalid version"
case "$target" in
    x86_64-unknown-linux-gnu|aarch64-unknown-linux-gnu|\
    x86_64-apple-darwin|aarch64-apple-darwin) ;;
    *) die "Unsupported release target: $target" ;;
esac
for tool in cargo python3 mkdir mktemp cp mv rm; do
    command -v "$tool" >/dev/null 2>&1 || die "Required command not found: $tool"
done
if command -v sha256sum >/dev/null 2>&1; then
    hash_tool="sha256sum"
elif command -v shasum >/dev/null 2>&1; then
    hash_tool="shasum"
else
    die "Install sha256sum or shasum"
fi

cli_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
target_dir="$cli_root/target"
output_dir="$cli_root/dist/$version"
artifact="nvn-agent-$target"

package_version="$(cargo metadata --no-deps --format-version 1 \
    --manifest-path "$cli_root/Cargo.toml" | python3 -c '
import json, sys
packages = json.load(sys.stdin)["packages"]
print(next(p["version"] for p in packages if p["name"] == "nvnhan0810-agent"))
')"
[[ "$package_version" == "$version" ]] ||
    die "Package version $package_version does not match $version"

cargo build --locked --release \
    --manifest-path "$cli_root/Cargo.toml" \
    --target-dir "$target_dir" --target "$target" --bin nvn-agent
binary="$target_dir/$target/release/nvn-agent"
reported="$("$binary" --version)" || die "Binary cannot run on this runner"
[[ "$reported" == "nvn-agent $version" ]] || die "Unexpected version: $reported"

mkdir -p -- "$output_dir"
[[ ! -e "$output_dir/$artifact" && ! -e "$output_dir/$artifact.sha256" ]] ||
    die "Artifacts already exist; do not overwrite a published release"
stage="$(mktemp -d "$output_dir/.package.XXXXXX")"
trap 'rm -rf -- "$stage"' EXIT
cp -- "$binary" "$stage/$artifact"
if [[ "$hash_tool" == "sha256sum" ]]; then
    checksum="$(sha256sum "$stage/$artifact")"
else
    checksum="$(shasum -a 256 "$stage/$artifact")"
fi
printf '%s\n' "${checksum%% *}" > "$stage/$artifact.sha256"
mv -- "$stage/$artifact" "$output_dir/$artifact"
mv -- "$stage/$artifact.sha256" "$output_dir/$artifact.sha256"
printf 'Packaged: %s\n' "$output_dir/$artifact"
```

Example, from the CLI directory on a compatible Linux x86_64 runner:

```bash
bash scripts/package-release.sh x86_64-unknown-linux-gnu 0.1.0
```

Update the CLI package version and enable Clap `version` as described earlier before packaging. Publish only after all artifacts and checksums for the release are complete. Local packaging is not the release promotion operation.

## Deployment example

These commands run on the release publisher/server, with write access to the distribution directory. They are examples to adapt to the deployment environment; end users do not run them.

```bash
# Run from backend/ after the release artifacts have been transferred.
# The version directory must already contain the complete verified release.
# Use the same STORAGE_DIR value as the backend deployment.
: "${STORAGE_DIR:?Set STORAGE_DIR to the backend storage root}"
case "$STORAGE_DIR" in
    /*) ;;
    *) printf '%s\n' 'STORAGE_DIR must be an absolute path' >&2; exit 1 ;;
esac
distribution_dir="${STORAGE_DIR%/}/public/agent/distributions"
mkdir -p "$distribution_dir"
installer_stage="$(mktemp "$distribution_dir/.install.XXXXXX")"
cp modules/agent/assets/install.sh "$installer_stage"
chmod 644 "$installer_stage"
mv -f "$installer_stage" "$distribution_dir/install.sh"

# After public downloads and pinned installation have passed smoke checks:
stable_stage="$(mktemp "$distribution_dir/.stable.XXXXXX")"
printf '%s\n' '0.1.0' > "$stable_stage"
chmod 644 "$stable_stage"
mv -f "$stable_stage" "$distribution_dir/stable-<platform>.txt"
```

The backend process and release publisher receive `STORAGE_DIR=/srv/nvn-storage`. The shared configuration derives `public` and `private`; the agent module derives `agent/distributions` from the public root; no module-specific storage environment variable is needed. Users normally set no environment variables: the installer defaults to the public backend domain, the stable release, and `~/.local/bin`.

All code in this document is proposed implementation. Updating this document does not create the scripts, change the gateway, build releases, or publish files.

## Platform release channels

Use `stable-linux.txt` for Linux and `stable-macos.txt` for macOS. Linux points to an x86_64-unknown-linux-gnu binary (native Linux and Ubuntu WSL on Intel Windows); macOS points to an aarch64-apple-darwin binary for Apple Silicon M series. No Linux ARM64, Intel macOS or native Windows releases are built. Linux clients need compatible glibc and system libraries; use the same Ubuntu release as the builder where possible. The shared releases/VERSION directory and installer URL are unchanged. Publish Linux locally to storage; publish macOS through SSH to ssh.nvnhan0810.com. Promote only the OS being built after the platform binary and checksum are available. CLI release tooling is in cli/scripts/release.sh.example in the agent repository.
