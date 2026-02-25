# Release Workflows Specification

## Purpose

Defines the three-tier release workflow for Nextcloud apps: automated version management, branch synchronization, and release publishing across development (nightly), beta, and stable channels. Each app MUST have independent version tracks that do not interfere when code flows between branches.

## ADDED Requirements

### Requirement: Branch Structure

Each app repository MUST maintain five long-lived branches with defined roles:
- `development` — active development, PRs land here
- `development-release` — auto-synced mirror of development, triggers nightly builds
- `beta` — release candidates, receives PRs from development
- `beta-release` — auto-synced mirror of beta, triggers beta builds
- `main` — stable releases, receives PRs from beta

#### Scenario: Initial branch creation
- GIVEN an app repository with only a `main` branch
- WHEN the three-tier workflow is set up
- THEN the branches `development`, `beta`, `development-release`, and `beta-release` SHALL be created from `main`
- AND each branch SHALL contain the same code as `main` at creation time

#### Scenario: Branch exists already
- GIVEN an app repository that already has a `development` or `beta` branch
- WHEN the workflow setup runs
- THEN existing branches SHALL NOT be overwritten
- AND only missing branches SHALL be created

### Requirement: PR Flow Enforcement

The system MUST enforce that pull requests follow the tier progression.

#### Scenario: Valid PR to main
- GIVEN a pull request targeting `main`
- WHEN the source branch is `beta` or starts with `hotfix/`
- THEN the PR check workflow SHALL pass

#### Scenario: Invalid PR to main
- GIVEN a pull request targeting `main`
- WHEN the source branch is `development` or any other non-allowed branch
- THEN the PR check workflow SHALL fail with a clear error message

#### Scenario: Valid PR to beta
- GIVEN a pull request targeting `beta`
- WHEN the source branch is `development` or starts with `hotfix/`
- THEN the PR check workflow SHALL pass

#### Scenario: Invalid PR to beta
- GIVEN a pull request targeting `beta`
- WHEN the source branch is `main` or any other non-allowed branch
- THEN the PR check workflow SHALL fail with a clear error message

### Requirement: Version Preservation During Sync

When code is synced from a working branch to its release branch, the release branch's version in `appinfo/info.xml` MUST be preserved.

#### Scenario: Sync development to development-release
- GIVEN `development-release` has version `0.2.9-unstable.3` in `appinfo/info.xml`
- WHEN a push to `development` triggers the sync workflow
- THEN `development-release` SHALL be hard-reset to match `development`'s code
- AND the version in `appinfo/info.xml` SHALL be restored to `0.2.9-unstable.3`
- AND the restore commit message SHALL include `[skip ci]`

#### Scenario: Sync beta to beta-release
- GIVEN `beta-release` has version `0.2.9-beta.2` in `appinfo/info.xml`
- WHEN a push to `beta` triggers the sync workflow
- THEN `beta-release` SHALL be hard-reset to match `beta`'s code
- AND the version in `appinfo/info.xml` SHALL be restored to `0.2.9-beta.2`
- AND the restore commit message SHALL include `[skip ci]`

#### Scenario: First sync with no existing release branch
- GIVEN `development-release` does not exist remotely
- WHEN a push to `development` triggers the sync workflow
- THEN `development-release` SHALL be created from `development`
- AND no version restoration SHALL occur (no previous version to preserve)

### Requirement: Stable Version Bumping

On push to `main`, the system MUST auto-increment the patch version and publish a stable release.

#### Scenario: Patch version increment
- GIVEN `main` has version `0.2.8` in `appinfo/info.xml`
- WHEN a push to `main` triggers the release workflow
- THEN the version SHALL be updated to `0.2.9`
- AND the commit message SHALL include `[skip ci]`

#### Scenario: Release artifact creation
- GIVEN the version has been bumped on `main`
- WHEN the build step completes
- THEN a signed tarball SHALL be created
- AND a GitHub release SHALL be created with tag `v{version}` and `prerelease: false`
- AND the tarball SHALL be uploaded to the Nextcloud App Store with `nightly: false`

### Requirement: Beta Version Bumping

On push to `beta-release`, the system MUST calculate the beta version based on main's current version.

#### Scenario: First beta after a stable release
- GIVEN `main` has version `0.2.8`
- AND `beta-release` has version `0.2.8` (freshly synced)
- WHEN the beta release workflow runs
- THEN the version SHALL be set to `0.2.9-beta.1`

#### Scenario: Subsequent beta release
- GIVEN `main` has version `0.2.8`
- AND `beta-release` has version `0.2.9-beta.2`
- WHEN the beta release workflow runs
- THEN the version SHALL be set to `0.2.9-beta.3`

#### Scenario: Beta counter reset after stable release
- GIVEN `main` was just bumped to `0.2.9`
- AND `beta-release` has version `0.2.9-beta.5`
- WHEN the beta release workflow runs
- THEN the version SHALL be set to `0.2.10-beta.1`

#### Scenario: Beta release artifact
- GIVEN a beta version has been calculated
- WHEN the build completes
- THEN a GitHub release SHALL be created with `prerelease: true`
- AND the tarball SHALL be uploaded to the Nextcloud App Store with `nightly: false`

### Requirement: Unstable Version Bumping

On push to `development-release`, the system MUST calculate the unstable version based on main's current version.

#### Scenario: Unstable version calculation
- GIVEN `main` has version `0.2.8`
- AND `development-release` has version `0.2.9-unstable.4`
- WHEN the unstable release workflow runs
- THEN the version SHALL be set to `0.2.9-unstable.5`

#### Scenario: Unstable release artifact
- GIVEN an unstable version has been calculated
- WHEN the build completes
- THEN a GitHub release SHALL be created with `prerelease: true`
- AND the tarball SHALL be uploaded to the Nextcloud App Store with `nightly: true`

### Requirement: Build and Package Process

All release workflows (stable, beta, unstable) MUST follow the same build and packaging process.

#### Scenario: App with frontend (has_frontend=true)
- GIVEN an app with a `package.json` containing a `build` script
- WHEN the release workflow runs
- THEN `npm ci` SHALL be executed
- AND `npm run build` SHALL be executed
- AND `composer install --no-dev --optimize-autoloader --classmap-authoritative` SHALL be executed

#### Scenario: App without frontend (has_frontend=false)
- GIVEN an app without a frontend build step (e.g., nldesign)
- WHEN the release workflow runs
- THEN npm steps SHALL be skipped
- AND `composer install --no-dev --optimize-autoloader --classmap-authoritative` SHALL be executed

#### Scenario: Package signing
- GIVEN a built app
- WHEN packaging completes
- THEN the tarball SHALL be signed using `openssl dgst -sha512 -sign` with the app's private key
- AND the signature SHALL be base64-encoded

### Requirement: Workflow Files Per App

Each app MUST have six workflow files in `.github/workflows/`.

#### Scenario: Complete workflow set
- GIVEN an app repository
- WHEN the three-tier workflow is fully set up
- THEN the following files SHALL exist:
  - `sync-dev.yaml` — triggers on push to `development`
  - `sync-beta.yaml` — triggers on push to `beta`
  - `release-stable.yaml` — triggers on push to `main`
  - `release-beta.yaml` — triggers on push to `beta-release`
  - `release-unstable.yaml` — triggers on push to `development-release`
  - `pr-check.yaml` — triggers on PRs to `main` or `beta`

### Requirement: Skip CI Loop Prevention

Version bump commits MUST NOT trigger subsequent workflow runs to prevent infinite loops.

#### Scenario: Version bump commit
- GIVEN a release workflow bumps the version in `appinfo/info.xml`
- WHEN it commits and pushes the change
- THEN the commit message SHALL contain `[skip ci]`
- AND no other workflow SHALL be triggered by this commit
