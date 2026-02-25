# Proposal: three-tier-release-workflows

## Summary

Adopt OpenConnector's three-tier release workflow (development → beta → main) with automated version management across all six Nextcloud apps. This solves the version conflict problem where merging dev→beta overwrites the beta version number in `info.xml`, and establishes independent release channels (nightly, beta, stable) for the Nextcloud App Store.

## Motivation

When we merge `development` into `beta`, the semver version from `info.xml` comes along and overwrites beta's version. This breaks our release strategy where:
- **minor** bumps happen on `main` (stable releases)
- **patch** bumps happen on `beta` (pre-release)
- **unstable counters** happen on `development` (nightly)

OpenConnector already solved this with separate "release branches" (`development-release`, `beta-release`) that auto-sync code but **preserve their own version numbers**. We need to replicate this pattern across all our apps.

## Affected Projects

- [ ] Project: `openregister` — Add GitHub Actions workflows, create release branches (v0.2.10)
- [ ] Project: `opencatalogi` — Add GitHub Actions workflows, create release branches (v0.7.9)
- [ ] Project: `docudesk` — Align existing release workflows with three-tier model (v0.0.32)
- [ ] Project: `softwarecatalog` — Add GitHub Actions workflows, create release branches (v0.1.137)
- [ ] Project: `nldesign` — Add GitHub Actions workflows, create release branches (v0.1.1)
- [ ] Project: `mydash` — Add GitHub Actions workflows, create release branches (v1.0.1)

## Scope

### In Scope

- GitHub Actions workflows for all six apps:
  - `release-workflow.yaml` — Stable release on push to `main`
  - `beta-release.yaml` — Beta release on push to `beta-release`
  - `unstable-release.yaml` — Nightly release on push to `development-release`
  - `push-development-to-development-release.yaml` — Auto-sync with version preservation
  - `push-beta-to-beta-release.yaml` — Auto-sync with version preservation
  - `pull-request-from-branch-check.yaml` — Enforce PR flow (dev→beta→main)
- Branch creation: `development`, `beta`, `development-release`, `beta-release` for each repo
- Disabling branch protections temporarily to allow initial setup PRs
- Version number initialization on each release branch
- Tonight's focus: dev→beta PRs only (main stays untouched)

### Out of Scope

- Merging to `main` tonight (avoid accidental stable releases)
- Changelog automation (can be added later)
- Quality gate workflows (PHPCS, tests) — these already exist on most apps
- Nextcloud App Store signing setup (assumes secrets already configured)
- Modifying the OpenConnector workflows (reference only)

## Approach

1. Use OpenConnector's `.github/workflows/` as the canonical template
2. For each app: copy workflows, adapt app-specific paths (e.g., `appinfo/info.xml` vs `appinfo/info.xml`)
3. Create the four branches per repo (development, beta, development-release, beta-release)
4. Disable branch protection rules temporarily
5. Initialize version numbers on each release branch
6. Test with dev→beta PRs first, leave main alone tonight

## Cross-Project Dependencies

- All six apps share the same workflow pattern — changes to the template should be propagated to all
- OpenConnector is the **reference implementation** (read-only, not modified)
- All apps require the same GitHub secrets: `NEXTCLOUD_SIGNING_CERT`, `NEXTCLOUD_SIGNING_KEY`, `NEXTCLOUD_APPSTORE_TOKEN`, `DEPLOY_KEY`
- DocuDesk already has partial release workflows that need alignment rather than fresh creation

## Rollback Strategy

1. **Workflows**: Delete the workflow files from `.github/workflows/` and push — GitHub Actions stop immediately
2. **Branches**: Delete the release branches (`development-release`, `beta-release`) — no code is lost since they're auto-synced copies
3. **Version numbers**: Revert `info.xml` to previous version on any affected branch
4. **Branch protections**: Re-enable via GitHub repo settings or API
5. **No data loss risk**: This change only adds CI/CD automation, doesn't modify application code

## Open Questions

- Are the GitHub secrets (`NEXTCLOUD_SIGNING_CERT`, `NEXTCLOUD_SIGNING_KEY`, `NEXTCLOUD_APPSTORE_TOKEN`) already configured on all six repos?
- Should we use a shared reusable workflow (GitHub Actions `workflow_call`) to reduce duplication across apps, or keep independent copies per app for simplicity?
- DocuDesk already has some release workflows — should we overwrite them with the OpenConnector model or carefully merge the differences?
