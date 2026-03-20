# sbom-generation Specification

## Purpose

Automated generation, validation, and publication of a CycloneDX Software Bill of Materials (SBOM) for each ConductionNL repository. The SBOM captures all production PHP and JavaScript dependencies in a single machine-readable file (`sbom.cdx.json`) at the repository root, enabling external CVE scanning and supply chain transparency.

## Requirements

### Requirement: SBOM file format

The system MUST generate a single `sbom.cdx.json` file in CycloneDX 1.5 JSON format at the repository root. The file MUST contain all production dependencies from both Composer (PHP) and npm (JavaScript) ecosystems, merged into one document.

#### Scenario: PHP-only repository generates SBOM

- GIVEN a repository with a `composer.json` but no frontend (`enable-frontend: false`)
- WHEN the SBOM generation workflow runs
- THEN a `sbom.cdx.json` file MUST be produced containing all production Composer dependencies
- AND the file MUST conform to CycloneDX 1.5 JSON schema

#### Scenario: Full-stack repository generates merged SBOM

- GIVEN a repository with both `composer.json` and `package.json`
- WHEN the SBOM generation workflow runs
- THEN individual PHP and npm SBOMs MUST be generated
- AND they MUST be merged into a single `sbom.cdx.json` file
- AND the merged file MUST contain components from both ecosystems

#### Scenario: Development dependencies excluded

- GIVEN a repository with development dependencies in `require-dev` (Composer) and `devDependencies` (npm)
- WHEN the SBOM is generated
- THEN only production dependencies SHALL be included
- AND development dependencies MUST NOT appear in `sbom.cdx.json`

### Requirement: CVE vulnerability scanning

The system MUST scan the generated SBOM against known CVE databases before committing. The workflow MUST fail if critical vulnerabilities are detected.

#### Scenario: SBOM passes CVE scan

- GIVEN a generated `sbom.cdx.json` with no critical CVEs
- WHEN Grype scans the SBOM
- THEN the scan MUST pass
- AND the workflow MUST proceed to the commit step

#### Scenario: SBOM fails CVE scan on critical vulnerability

- GIVEN a generated `sbom.cdx.json` containing a dependency with a critical CVE
- WHEN Grype scans the SBOM with `--fail-on critical`
- THEN the scan MUST fail
- AND the workflow MUST NOT commit the SBOM to the repository
- AND the workflow MUST report the failing CVE details in the job output

#### Scenario: Non-critical CVEs do not block

- GIVEN a generated `sbom.cdx.json` containing dependencies with low, medium, or high (but not critical) CVEs
- WHEN Grype scans the SBOM with `--fail-on critical`
- THEN the scan MUST pass
- AND the workflow MUST proceed to the commit step

### Requirement: License compliance check

The system MUST verify license compliance of all dependencies before committing the SBOM. The workflow MUST fail if critical license issues are detected.

#### Scenario: Composer audit passes

- GIVEN a repository with no critical Composer dependency issues
- WHEN `composer audit` runs
- THEN it MUST pass
- AND the workflow MUST proceed

#### Scenario: npm audit passes

- GIVEN a repository with frontend dependencies and no critical npm issues
- WHEN `npm audit --audit-level=critical` runs
- THEN it MUST pass
- AND the workflow MUST proceed

#### Scenario: Audit failure blocks SBOM commit

- GIVEN a repository where `composer audit` or `npm audit` reports a critical issue
- WHEN the validation gate runs
- THEN the workflow MUST fail
- AND the SBOM MUST NOT be committed to the repository

### Requirement: SBOM committed to repository

The system MUST commit the validated `sbom.cdx.json` to the repository root. The commit MUST only happen after all CVE and license checks pass.

#### Scenario: SBOM updated on successful validation

- GIVEN all CVE scans and license checks have passed
- WHEN the commit step runs
- THEN `sbom.cdx.json` MUST be staged and committed with the message `chore: update SBOM`
- AND the commit MUST be pushed to the current branch

#### Scenario: No changes detected

- GIVEN the generated `sbom.cdx.json` is identical to the existing committed version
- WHEN the commit step runs
- THEN no commit SHALL be created
- AND the workflow MUST succeed without error

### Requirement: Reusable workflow integration

The shared `ConductionNL/.github` quality workflow MUST support SBOM generation via an `enable-sbom` input parameter.

#### Scenario: App opts into SBOM generation

- GIVEN an app workflow that calls the shared `quality.yml` with `enable-sbom: true`
- WHEN the quality workflow runs
- THEN the SBOM generation, validation, and commit job MUST execute

#### Scenario: App opts out of SBOM generation

- GIVEN an app workflow that calls the shared `quality.yml` with `enable-sbom: false`
- WHEN the quality workflow runs
- THEN the SBOM job MUST be skipped entirely

#### Scenario: Default behavior

- GIVEN an app workflow that calls the shared `quality.yml` without specifying `enable-sbom`
- WHEN the quality workflow runs
- THEN `enable-sbom` MUST default to `true`
- AND the SBOM job MUST execute

### Requirement: Release attachment

The system MUST attach `sbom.cdx.json` to GitHub Releases when a tag-based release is created.

#### Scenario: SBOM attached to release

- GIVEN a push event with a tag reference (e.g., `refs/tags/v1.0.0`)
- WHEN the release workflow creates a GitHub Release
- THEN `sbom.cdx.json` from the repository root MUST be attached as a release asset

### Requirement: Local generation

Developers MUST be able to generate the SBOM locally using standard tooling without CI.

#### Scenario: Developer generates SBOM locally

- GIVEN a developer with the repository checked out and dependencies installed
- WHEN they run `composer make-bom` and `npx @cyclonedx/cyclonedx-npm`
- THEN the SBOM files MUST be generated locally
- AND they can be merged using `@cyclonedx/cyclonedx-cli merge`

## NTIA Compliance Check

The [NTIA Minimum Elements for SBOMs](https://www.ntia.gov/report/2021/minimum-elements-software-bill-materials-sbom) requires seven data fields. Analysis of the openregister `sbom.cdx.json` (the only committed SBOM as of 2026-03-16):

| NTIA Element | Required | CycloneDX Field | Status |
|---|---|---|---|
| Supplier name | MUST | `components[].group` + `components[].author` | PASS — e.g., `"adbario"`, `"Riku Särkinen"` |
| Component name | MUST | `components[].name` | PASS — e.g., `"php-dot-notation"` |
| Version string | MUST | `components[].version` | PASS — e.g., `"3.3.0"` |
| Unique identifier | MUST | `components[].purl` | PASS — e.g., `"pkg:composer/adbario/php-dot-notation@3.3.0"` |
| Dependency relationship | MUST | `dependencies[].dependsOn` | PARTIAL — present for PHP deps with transitive deps; leaf nodes have empty `dependsOn`; npm deps lose relationships in jq merge |
| Author of SBOM data | MUST | `metadata.tools` | PASS — `cyclonedx-php-composer v6.2.0` |
| Timestamp | MUST | `metadata.timestamp` | PASS — ISO 8601 format |

**Gaps:**
1. The `metadata.component` field (identifying the software being described) uses a dev branch purl (`pkg:composer/conductionnl/openregister@dev-feature/php-linting`) rather than a stable version identifier. This should reflect the release version or at minimum the branch name of the build.
2. For full-stack apps using the jq merge, npm dependency relationships are lost (the jq merge only preserves components, not the `dependencies[]` array from the npm SBOM). This makes the merged SBOM non-compliant for NTIA "dependency relationship" for the JavaScript portion.

## Schema Validation

Currently, NO explicit CycloneDX schema validation step exists in the workflow. The generated SBOMs self-declare conformance via:
```json
"$schema": "http://cyclonedx.org/schema/bom-1.5.schema.json"
```

However, the workflow does not validate the output against this schema. Grype implicitly validates that the SBOM is parseable (it would fail on invalid JSON/structure), but this is not a formal schema validation.

**Recommendation**: Add a schema validation step using the CycloneDX CLI:
```bash
npx @cyclonedx/cyclonedx-cli validate --input-file sbom.cdx.json --input-format json --input-version 1.5
```

This would catch structural issues (missing required fields, invalid enum values) before the SBOM is committed.

## MODIFIED Requirements

None.

## REMOVED Requirements

None.
