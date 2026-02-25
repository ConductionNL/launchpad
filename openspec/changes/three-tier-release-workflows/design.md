# Design: three-tier-release-workflows

## Architecture Overview

Each app gets a five-branch structure with two types of automated workflows:

```
                  ┌─────────────────────────────────────┐
                  │        Reusable Workflows Repo       │
                  │  .github/workflows/                  │
                  │    sync-to-release.yaml              │
                  │    create-release.yaml               │
                  │    branch-protection-check.yaml      │
                  └──────────┬──────────────────────────┘
                             │ workflow_call
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
   openregister         opencatalogi          docudesk  ...
   .github/workflows/   .github/workflows/
     sync-dev.yaml        sync-dev.yaml
     sync-beta.yaml       sync-beta.yaml
     release-stable.yaml  release-stable.yaml
     release-beta.yaml    release-beta.yaml
     release-unstable.yaml release-unstable.yaml
     pr-check.yaml        pr-check.yaml
```

Each app has thin caller workflows (~15 lines each) that pass app-specific inputs to shared reusable workflows.

## Decisions

### 1. Where to host the reusable workflows

**Decision**: Dedicated repo `ConductionNL/.github` (or `ConductionNL/shared-workflows`)

**Why over alternatives:**
- **Per-app copies** (OpenConnector model): Works but means updating 6+ repos for any workflow fix. ~400 lines duplicated per app.
- **Monorepo in apps-extra**: Not a GitHub repo per se — each app is its own repo on GitHub.
- **Dedicated shared repo**: Single source of truth. Fix once, all apps pick it up. GitHub natively supports `uses: ConductionNL/.github/.github/workflows/sync-to-release.yaml@main`.

**Trade-off**: Adds a cross-repo dependency. If the shared repo breaks, all releases break. Mitigated by pinning to tags (`@v1`) rather than `@main`.

**Fallback**: If setting up the shared repo is too much tonight, copy workflows per-app (OpenConnector style) and consolidate later.

### 2. Branch structure per app

```
development              ← daily work, PRs land here
development-release      ← auto-synced from development, triggers nightly
beta                     ← PRs from development, release candidates
beta-release             ← auto-synced from beta, triggers beta release
main                     ← PRs from beta, stable releases
```

**PR flow enforced by workflow:**
- `main` ← only from `beta` or `hotfix/*`
- `beta` ← only from `development` or `hotfix/*`

### 3. Version management strategy

**Stable** (main): `MAJOR.MINOR.PATCH` — patch auto-increments on each push to main
**Beta** (beta-release): `MAJOR.MINOR.(PATCH+1)-beta.N` — base version from main, counter increments
**Unstable** (development-release): `MAJOR.MINOR.(PATCH+1)-unstable.N` — base version from main, counter increments

Example progression:
```
main:                0.2.8  →  0.2.9  →  0.2.10
beta-release:        0.2.9-beta.1  →  0.2.9-beta.2  →  0.2.10-beta.1
development-release: 0.2.9-unstable.1  →  0.2.9-unstable.2  →  ...
```

**Version preservation during sync**: When `development` → `development-release`, the workflow hard-resets the release branch to match development's code, then **restores the previous version** from `info.xml`. This prevents version bleed across tiers.

**`[skip ci]` commits**: Version bump commits include `[skip ci]` in the message to prevent triggering the sync workflow again (avoids infinite loops).

### 4. Reusable workflow: sync-to-release

**Inputs:**
```yaml
inputs:
  source_branch:       # 'development' or 'beta'
  target_branch:       # 'development-release' or 'beta-release'
  info_xml_path:       # default: 'appinfo/info.xml'
secrets:
  deploy_key:          # SSH key for push access
```

**Logic:**
1. Checkout source branch with full history
2. Fetch all remote branches
3. If target branch exists: extract its current version from `info.xml`
4. Checkout/create target branch, hard reset to source
5. Restore preserved version (if existed)
6. Commit with `[skip ci]` message
7. Force push target branch

### 5. Reusable workflow: create-release

**Inputs:**
```yaml
inputs:
  trigger_branch:      # 'main', 'beta-release', or 'development-release'
  release_type:        # 'stable', 'beta', or 'unstable'
  base_version_branch: # 'main' (used by beta/unstable to calculate next version)
  nodejs_version:      # default: '18.x'
  php_version:         # default: '8.2'
  has_frontend:        # boolean — skip npm if false (e.g., nldesign has no build step)
  info_xml_path:       # default: 'appinfo/info.xml'
  extra_excludes:      # app-specific rsync excludes beyond the standard set
secrets:
  deploy_key:
  signing_cert:
  signing_key:
  appstore_token:
```

**Logic:**
1. Calculate version based on `release_type`:
   - `stable`: Read current, increment patch
   - `beta`: Read main version, +1 patch, append `-beta.N`
   - `unstable`: Read main version, +1 patch, append `-unstable.N`
2. Update `info.xml`, commit with `[skip ci]`
3. Build: `npm ci && npm run build` (if `has_frontend`), `composer install --no-dev`
4. Package: rsync to `package/{app-name}/`, create tarball
5. Sign: OpenSSL SHA512 + base64
6. Create GitHub release (`prerelease: true` for beta/unstable)
7. Upload to Nextcloud App Store (`nightly: true` only for unstable)

### 6. Reusable workflow: branch-protection-check

**Inputs:**
```yaml
inputs:
  target_branch:    # from github.event.pull_request.base.ref
  source_branch:    # from github.event.pull_request.head.ref
```

**Logic:** Simple conditional — fail if source doesn't match allowed branches for the target.

## Per-App Caller Workflows

Each app gets 6 thin YAML files in `.github/workflows/`:

| File | Triggers on | Calls |
|------|------------|-------|
| `sync-dev.yaml` | push to `development` | `sync-to-release` (dev → dev-release) |
| `sync-beta.yaml` | push to `beta` | `sync-to-release` (beta → beta-release) |
| `release-stable.yaml` | push to `main` | `create-release` (stable) |
| `release-beta.yaml` | push to `beta-release` | `create-release` (beta) |
| `release-unstable.yaml` | push to `development-release` | `create-release` (unstable) |
| `pr-check.yaml` | PR to main/beta | `branch-protection-check` |

Example caller (~15 lines):
```yaml
name: Sync Development to Release
on:
  push:
    branches: [development]
jobs:
  sync:
    uses: ConductionNL/.github/.github/workflows/sync-to-release.yaml@v1
    with:
      source_branch: development
      target_branch: development-release
    secrets:
      deploy_key: ${{ secrets.DEPLOY_KEY }}
```

## App-Specific Configuration

| App | Version | has_frontend | Notes |
|-----|---------|-------------|-------|
| openregister | 0.2.10 | true | Standard NC app |
| opencatalogi | 0.7.9 | true | Standard NC app |
| docudesk | 0.0.32 | true | Has existing workflows to replace |
| softwarecatalog | 0.1.137 | true | Standard NC app |
| nldesign | 0.1.1 | false | CSS-only, no npm build |
| mydash | 1.0.1 | true | Standard NC app |

## Standard Packaging Excludes

```
/package, /.git, /.github, /.cursor, /.vscode, /node_modules, /src, /tests,
/package.json, /package-lock.json, /composer.json, /composer.lock,
/phpcs.xml, /phpmd.xml, /psalm.xml, /phpunit.xml, /.phpunit.cache,
/.phpunit.result.cache, /jest.config.js, /webpack.config.js, /tsconfig.json,
/.babelrc, /.eslintrc.js, /.prettierrc, /stylelint.config.js,
/.gitignore, /.gitattributes, /signing-key.key, /signing-cert.crt
```

## Tonight's Execution Plan

**Phase 1**: Decide on shared repo vs per-app copies (pragmatic choice for tonight)
**Phase 2**: Create branches on each repo (development, beta, development-release, beta-release)
**Phase 3**: Add workflow files to each app
**Phase 4**: Disable branch protections to allow setup PRs
**Phase 5**: Test with dev→beta PRs (main stays untouched)

## Risks / Trade-offs

- **[Risk] Shared workflow repo not ready** → Fallback: copy workflows per-app tonight, consolidate later
- **[Risk] Force push on release branches** → Safe because only workflows write to these branches
- **[Risk] Accidental main release** → Mitigation: skip main workflows tonight, add them when ready
- **[Risk] Missing secrets on some repos** → Check via `gh secret list` before starting
- **[Risk] Infinite workflow loops** → Mitigated by `[skip ci]` in version bump commits
- **[Risk] DocuDesk has existing workflows** → Replace entirely with new pattern for consistency

## Security Considerations

- SSH deploy keys scoped per-repository (not org-wide)
- Signing cert/key stored as GitHub secrets, never logged
- Force push restricted to automated sync workflows only
- Branch protection re-enabled after setup complete

## Open Questions

- Do all six repos already have `DEPLOY_KEY` configured? (needed for SSH push in workflows)
- For tonight: shared repo or per-app copies? (recommend per-app copies for speed, consolidate later)
