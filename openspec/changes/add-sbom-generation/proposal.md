# Proposal: add-sbom-generation

## Summary

Add automated SBOM (Software Bill of Materials) generation for PHP (Composer) and JavaScript (npm) dependencies to all ConductionNL Nextcloud apps, integrated into the existing company-wide quality workflow. This makes repositories scannable for CVE vulnerabilities by external parties and improves supply chain transparency.

## Motivation

ConductionNL already has a mature quality workflow with PHPCS, PHPMD, Psalm, PHPStan, and basic vulnerability checks (`composer audit`, `npm audit`, `roave/security-advisories`). However, these tools only flag known vulnerabilities at build time — there is no machine-readable dependency manifest (SBOM) that external parties (auditors, government clients, security scanners) can consume to continuously monitor for new CVEs.

Dutch government procurement increasingly requires supply chain transparency. Generating and publishing SBOMs in a standard format (CycloneDX) addresses this requirement and enables automated CVE scanning by tools like Trivy, Grype, or GitHub's Dependency Graph.

## Affected Projects

All core PHP apps that use the shared quality workflow:

- [ ] Project: `openregister` — Add SBOM generation to quality workflow (reference implementation)
- [ ] Project: `opencatalogi` — Add SBOM generation
- [ ] Project: `openconnector` — Add SBOM generation
- [ ] Project: `docudesk` — Add SBOM generation
- [ ] Project: `nldesign` — Add SBOM generation
- [ ] Project: `pipelinq` — Add SBOM generation
- [ ] Project: `procest` — Add SBOM generation
- [ ] Project: `softwarecatalog` — Add SBOM generation
- [ ] Project: `mydash` — Add SBOM generation
- [ ] Project: `.github` (ConductionNL shared workflows) — Add reusable SBOM workflow template

## Scope

### In Scope

- CycloneDX SBOM generation for Composer (PHP) dependencies
- CycloneDX SBOM generation for npm (JavaScript) dependencies
- CVE vulnerability scanning (Grype) and license compliance checks on generated SBOMs
- Single merged SBOM (`sbom.cdx.json`) committed to repository root, only after passing all validation checks
- Integration into existing GitHub Actions quality workflows
- SBOM artifact upload to GitHub Releases
- Reusable workflow template in `ConductionNL/.github`
- Documentation for local SBOM generation

### Out of Scope

- Docker image scanning / container SBOMs
- SLSA provenance or artifact signing
- Centralized SBOM aggregation dashboard
- DAST or runtime security scanning
- Dependabot / Renovate automated dependency updates
- License compliance beyond what npm/Composer already report

## Approach

1. Add `cyclonedx/cyclonedx-php-composer` as a Composer dev dependency for PHP SBOM generation
2. Use `@cyclonedx/cyclonedx-npm` for JavaScript SBOM generation
3. Create a reusable GitHub Actions workflow in `ConductionNL/.github` that generates both SBOMs and uploads them as workflow artifacts
4. Integrate the SBOM step into the existing `quality.yml` reusable workflow (for apps using the shared pattern) and document manual integration for apps with custom workflows
5. Attach SBOMs to GitHub Releases so external scanners can consume them

## Capabilities

### New Capabilities

- `sbom-generation` — Automated generation of CycloneDX SBOMs for PHP and npm dependencies, integrated into CI/CD

### Modified Capabilities

None — this is additive to the existing quality workflow.

## Cross-Project Dependencies

- **ConductionNL/.github** — The shared reusable workflow repository needs the SBOM generation step added to the quality workflow template
- All apps consuming the reusable workflow will automatically get SBOM generation once the shared workflow is updated
- Apps with custom workflows (docudesk, opencatalogi, softwarecatalog) need manual integration

## Rollback Strategy

- Remove the SBOM generation step from GitHub Actions workflows
- Remove `cyclonedx/cyclonedx-php-composer` from `composer.json` dev dependencies
- Remove `@cyclonedx/cyclonedx-npm` from `package.json` dev dependencies
- SBOM generation is fully additive — removing it has zero impact on existing quality checks or app functionality

## Current Implementation Status

Inventory of per-app SBOM readiness (verified 2026-03-16):

| App | Composer `cyclonedx` | npm `@cyclonedx` | Workflow | Committed `sbom.cdx.json` | `.grype.yaml` |
|-----|---------------------|-------------------|----------|--------------------------|---------------|
| openregister | ^6.2 | ^4.2.1 | `quality.yml` (`enable-sbom: true`) | Yes | Yes |
| opencatalogi | ^6.2 | ^4.2.1 | standalone `sbom.yml` | No | No |
| openconnector | ^6.2 | ^4.2.1 | standalone `sbom.yml` | No | No |
| docudesk | ^6.2 | ^4.2.1 | standalone `sbom.yml` | No | No |
| nldesign | ^6.2 | ^4.2.1 | `code-quality.yml` (`enable-sbom: true`) | No | No |
| pipelinq | ^6.2 | ^4.2.1 | `code-quality.yml` (`enable-sbom: true`) | No | No |
| procest | ^6.2 | ^4.2.1 | `code-quality.yml` (`enable-sbom: true`) | No | No |
| softwarecatalog | ^6.2 | ^4.2.1 | standalone `sbom.yml` | No | No |
| mydash | ^6.2 | ^4.2.1 | standalone `sbom.yml` | No | No |
| .github (shared) | N/A | N/A | `quality.yml` (SBOM job, Stage 4) | N/A | N/A |

**Key findings:**
- All 9 apps have both Composer and npm CycloneDX tooling installed.
- All 9 apps have SBOM workflow integration (4 via shared workflow, 5 via standalone `sbom.yml`).
- Only openregister has a committed `sbom.cdx.json` (55,615 lines, PHP-only, CycloneDX 1.5).
- Only openregister has a `.grype.yaml` false-positive ignore file.
- The shared workflow uses `jq` merge instead of `@cyclonedx/cyclonedx-cli merge` (divergence from design doc).

## Standards & Compliance

### CycloneDX 1.5

The implementation targets CycloneDX 1.5 (schema: `http://cyclonedx.org/schema/bom-1.5.schema.json`). Key spec features used:
- `metadata.tools` — records generator tool identity and version
- `metadata.timestamp` — ISO 8601 generation timestamp
- `components[]` — flat list with `bom-ref`, `type`, `name`, `version`, `group`, `purl`, `description`, `author`, `licenses`
- `dependencies[]` — dependency graph with `ref` and `dependsOn` relationships
- `serialNumber` — UUID URN for SBOM instance identification

### NTIA Minimum Elements for SBOMs

The [NTIA "Minimum Elements for a Software Bill of Materials"](https://www.ntia.gov/report/2021/minimum-elements-software-bill-materials-sbom) defines seven required data fields. Current compliance:

| NTIA Element | CycloneDX Field | Present in openregister SBOM |
|---|---|---|
| Supplier name | `components[].group` + `components[].author` | Yes (`"adbario"`, `"Riku Särkinen"`) |
| Component name | `components[].name` | Yes (`"php-dot-notation"`) |
| Version | `components[].version` | Yes (`"3.3.0"`) |
| Unique identifier | `components[].purl` | Yes (`"pkg:composer/adbario/php-dot-notation@3.3.0"`) |
| Dependency relationship | `dependencies[].dependsOn` | Partial — some entries have empty `dependsOn` (leaf nodes), others have full trees |
| Author of SBOM data | `metadata.tools` | Yes (cyclonedx-php-composer v6.2.0) |
| Timestamp | `metadata.timestamp` | Yes (`"2026-03-16T09:39:30Z"`) |

**Gap:** The `metadata.component` field (identifying the root application itself) is absent in the current openregister SBOM — this is the "subject" of the SBOM. CycloneDX tooling should populate this from `composer.json` `name` field, but the current output uses a dev branch ref (`pkg:composer/conductionnl/openregister@dev-feature/php-linting`) which is a CI artifact, not a stable identifier.

### Dutch Government SBOM Requirements

Dutch government procurement (specifically Rijksoverheid ICO-standaarden and BIO/BIR frameworks) increasingly references:
- **CISA SBOM guidance** (US, but adopted by Dutch NCSC): recommends machine-readable SBOMs for all software in government use
- **NEN-EN-ISO/IEC 27001:2023** Annex A.8.9 (Configuration management) — SBOMs support configuration transparency
- **Logius Standard for Interoperability**: while not yet mandating SBOMs, the trend toward "open supply chain" aligns with CycloneDX publication
- **Common Ground / Haven**: Dutch municipal platform standards recommend dependency transparency; CycloneDX SBOMs satisfy this

### SPDX vs CycloneDX Rationale (expanded)

CycloneDX was chosen over SPDX for these implementation-specific reasons:
1. **First-party PHP tooling**: `cyclonedx-php-composer` is maintained by the CycloneDX project itself; no equivalent quality SPDX Composer plugin exists
2. **Vulnerability correlation**: CycloneDX's `vulnerabilities[]` section and purl-based component identification map directly to Grype/Trivy scanners
3. **JSON-native**: CycloneDX JSON is the primary format; SPDX's primary format is tag-value with JSON as secondary
4. **Size**: CycloneDX JSON SBOMs are typically 30-50% smaller than equivalent SPDX JSON documents

## Remaining Verification Items

1. **Workflow dry-run**: The shared quality workflow SBOM job has not been verified on a feature branch push (Task 1.1 incomplete)
2. **Non-critical CVE passthrough**: Not yet verified that medium/high CVEs pass through without blocking (Task 1.2 incomplete)
3. **SBOM commit on push**: Only openregister has a committed sbom.cdx.json — remaining 8 apps need their first successful workflow run to produce one
4. **Release attachment**: Not yet verified that SBOM appears as a download on GitHub Releases (Task 1.5 incomplete)
5. **npm SBOM merge quality**: The jq-based merge may lose npm-specific metadata (see design.md); needs validation on a full-stack app
6. **`.grype.yaml` propagation**: Only openregister has the false-positive ignore file; standalone `sbom.yml` workflows create one inline only if absent, but the shared workflow always creates one inline — need to verify consistency

## Open Questions

1. ~~Should SBOMs be committed to the repository?~~ **Resolved**: Yes, committed to repo root after passing CVE + license checks.
2. ~~Which CycloneDX spec version?~~ **Resolved**: 1.5 (current stable, wide tool support).
3. ~~Should we run CVE scanning in CI?~~ **Resolved**: Yes, using Grype against generated SBOMs. Fails on critical CVEs.
