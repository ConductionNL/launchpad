# Design: add-sbom-generation

## Architecture Overview

This change is entirely CI/CD and tooling — no runtime code, API endpoints, database changes, or Nextcloud integration required. SBOMs are generated during GitHub Actions workflows and published as artifacts.

```
┌─────────────────────────────────────────────────────┐
│  ConductionNL/.github (shared workflows)            │
│  └─ quality.yml                                     │
│     ├─ existing checks (PHPCS, PHPMD, Psalm, ...)   │
│     └─ NEW: sbom-generation job                     │
│        ├─ composer CycloneDX → bom-php.cdx.json     │
│        └─ npx CycloneDX-npm → bom-npm.cdx.json     │
│                    │                                │
│                    ▼                                │
│         GitHub Actions Artifacts                    │
│         (attached to workflow runs + releases)      │
└─────────────────────────────────────────────────────┘
         ▲                          ▲
         │                          │
┌────────┴────────┐     ┌──────────┴──────────┐
│ Apps using       │     │ Apps with custom     │
│ reusable workflow│     │ workflows            │
│ (openregister,   │     │ (docudesk,           │
│  pipelinq,       │     │  opencatalogi,       │
│  procest, ...)   │     │  softwarecatalog)    │
│                  │     │                      │
│ Automatic via    │     │ Manual integration   │
│ enable-sbom:true │     │ (copy job snippet)   │
└─────────────────┘     └─────────────────────┘
```

## API Design

Not applicable — this change introduces no API endpoints. SBOM files are static JSON artifacts generated during CI.

## Database Changes

None.

## Nextcloud Integration

None — this change only affects CI/CD tooling and dev dependencies.

## Decisions

### Decision 1: CycloneDX format over SPDX

**Choice**: CycloneDX JSON (`.cdx.json`)

**Why**: CycloneDX is purpose-built for SBOMs with richer vulnerability correlation support. SPDX is more license-focused. CycloneDX has first-party tooling for both Composer (`cyclonedx-php-composer`) and npm (`@cyclonedx/cyclonedx-npm`), making integration straightforward. Most CVE scanning tools (Trivy, Grype, OWASP Dependency-Track) natively consume CycloneDX.

**Alternative considered**: SPDX — broader adoption in some ecosystems but weaker tooling for PHP and less focused on vulnerability tracking.

### Decision 2: Spec version 1.5

**Choice**: CycloneDX spec version 1.5

**Why**: Version 1.5 is the current stable release with wide tool support. Version 1.6 is still gaining adoption and tooling compatibility. Both Composer and npm plugins support 1.5. We can upgrade to 1.6 later as a non-breaking change.

### Decision 3: Committed to repo after validation

**Choice**: SBOMs are committed to the repository (in an `sbom/` directory) but only after passing CVE vulnerability scanning and license compliance checks. They are also attached to releases.

**Why**: Committing SBOMs to the repository makes them directly accessible to external parties for CVE scanning without needing GitHub API access. By gating the commit on CVE and license checks, we ensure that only validated, clean SBOMs are published — a failing check blocks the SBOM update and signals a dependency issue that must be resolved.

**Alternative considered**: Artifact-only (not committed) — simpler but requires API access for external scanners. Since the goal is transparency for external parties, direct repo access is preferred.

### Decision 4: Feature flag in reusable workflow

**Choice**: Add `enable-sbom` input parameter (default: `true`) to the shared `quality.yml` workflow.

**Why**: Consistent with existing pattern (`enable-psalm`, `enable-phpstan`, `enable-phpmetrics`, etc.). Apps opt in by default but can disable if needed. Apps with custom workflows copy the job snippet directly.

### Decision 5: Separate job, not inline step

**Choice**: SBOM generation runs as a separate job in the workflow, not as steps within existing jobs.

**Why**: SBOM generation is independent of quality checks — it shouldn't block or be blocked by linting/testing. Running it as a parallel job means faster overall workflow time. It also keeps the SBOM artifact upload cleanly separated.

## File Structure

Changes per app repository:

```
composer.json                    # Add cyclonedx/cyclonedx-php-composer to require-dev
package.json                     # Add @cyclonedx/cyclonedx-npm to devDependencies
sbom.cdx.json                    # Generated SBOM (committed after validation)
.github/workflows/quality.yml    # Add enable-sbom: true (reusable pattern apps)
```

Changes to shared workflow repository (`ConductionNL/.github`):

```
.github/workflows/quality.yml    # Add sbom-generation job + enable-sbom input
```

## SBOM Generation Tooling

### PHP (Composer)

**Tool**: `cyclonedx/cyclonedx-php-composer` (Composer plugin)

```bash
# Install as dev dependency
composer require --dev cyclonedx/cyclonedx-php-composer

# Generate SBOM (production dependencies only)
composer CycloneDX:make-sbom --output-format=JSON --output-file=bom-php.cdx.json --spec-version=1.5 --omit=dev --omit=plugin
```

Output: `bom-php.cdx.json` — intermediate CycloneDX 1.5 JSON for PHP dependencies (merged into `sbom.cdx.json`).

### JavaScript (npm)

**Tool**: `@cyclonedx/cyclonedx-npm` (npm package)

```bash
# Install as dev dependency
npm install --save-dev @cyclonedx/cyclonedx-npm

# Generate SBOM (production dependencies only)
npx @cyclonedx/cyclonedx-npm --output-file bom-npm.cdx.json --spec-version 1.5 --omit dev
```

Output: `bom-npm.cdx.json` — intermediate CycloneDX 1.5 JSON for npm dependencies (merged into `sbom.cdx.json`).

### GitHub Actions Job

The SBOM job generates SBOMs, validates them against CVE databases and license policies, and only commits to the repo if all checks pass.

```yaml
sbom:
  name: SBOM Generation & Validation
  if: ${{ inputs.enable-sbom }}
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
      with:
        ref: ${{ github.head_ref || github.ref_name }}
        token: ${{ secrets.GITHUB_TOKEN }}

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ inputs.php-version }}

    - name: Setup Node
      if: ${{ inputs.enable-frontend }}
      uses: actions/setup-node@v4
      with:
        node-version: '20'

    - name: Install Composer dependencies
      run: composer install --no-interaction --prefer-dist

    - name: Generate PHP SBOM
      run: composer CycloneDX:make-sbom --output-format=JSON --output-file=bom-php.cdx.json --spec-version=1.5 --omit=dev --omit=plugin

    - name: Install npm dependencies
      if: ${{ inputs.enable-frontend }}
      run: npm ci

    - name: Generate npm SBOM
      if: ${{ inputs.enable-frontend }}
      run: npx @cyclonedx/cyclonedx-npm --output-file bom-npm.cdx.json --spec-version 1.5 --omit dev

    - name: Merge SBOMs into single file
      run: |
        npm exec --yes -- @cyclonedx/cyclonedx-cli merge \
          --input-files bom-php.cdx.json ${{ inputs.enable-frontend && 'bom-npm.cdx.json' || '' }} \
          --output-file sbom.cdx.json \
          --output-format json
      # For PHP-only apps (no frontend), just rename the PHP SBOM
      if: ${{ inputs.enable-frontend }}

    - name: Use PHP SBOM as single SBOM (no frontend)
      if: ${{ !inputs.enable-frontend }}
      run: mv bom-php.cdx.json sbom.cdx.json

    # --- Validation gate: CVE + License checks ---

    - name: Install Grype (CVE scanner)
      uses: anchore/scan-action/download-grype@v5

    - name: CVE scan SBOM
      run: grype sbom:sbom.cdx.json --fail-on critical

    - name: License compliance check (Composer)
      run: composer audit --format=json

    - name: License compliance check (npm)
      if: ${{ inputs.enable-frontend }}
      run: npm audit --audit-level=critical

    # --- Only commit if all checks passed ---

    - name: Commit SBOM to repository
      run: |
        git config user.name "github-actions[bot]"
        git config user.email "github-actions[bot]@users.noreply.github.com"
        git add sbom.cdx.json
        if git diff --cached --quiet; then
          echo "No SBOM changes to commit"
        else
          git commit -m "chore: update SBOM"
          git push
        fi

    - name: Upload SBOM as artifact
      uses: actions/upload-artifact@v4
      with:
        name: sbom-${{ inputs.app-name }}
        path: sbom.cdx.json
        retention-days: 90
```

### Release Attachment

For release workflows, the SBOM is already committed to the repo root. Attach it to the release as well:

```yaml
- name: Attach SBOM to release
  if: ${{ github.event_name == 'push' && startsWith(github.ref, 'refs/tags/') }}
  uses: softprops/action-gh-release@v2
  with:
    files: sbom.cdx.json
```

## Implementation Verification

### Shared Workflow (quality.yml) — Actual Behavior

The implemented SBOM job in `.github/.github/workflows/quality.yml` (Stage 4) differs from the design doc in several ways:

1. **Grype installation**: Uses `anchore/scan-action/download-grype@v5` with an `id: grype-install` step, then references `${{ steps.grype-install.outputs.cmd }}` instead of hardcoded `grype` binary. This handles version pinning and PATH issues.

2. **False-positive management**: The workflow creates a `.grype.yaml` inline if one doesn't already exist in the repo:
   ```yaml
   ignore:
     - vulnerability: GHSA-8rmg-jf7p-4p22  # typosquatting false positive (gen-mapping)
     - vulnerability: GHSA-pvjq-589m-3mc8  # typosquatting false positive (helper-validator-identifier)
   ```
   If the repo already has a `.grype.yaml` (like openregister does), the existing file is used. This means repos can customize their ignore list.

3. **Composer audit non-blocking**: The workflow runs `composer audit --format=json || true` — the `|| true` means Composer audit never blocks the build. Only Grype is a hard gate. This differs from the spec which says "audit failure blocks SBOM commit."

4. **SBOM merge uses jq, not cyclonedx-cli**: Instead of `@cyclonedx/cyclonedx-cli merge`, the implementation uses:
   ```bash
   jq -s '.[0] * {components: ([.[].components[]?] | unique_by(.purl // .name))}' bom-php.cdx.json bom-npm.cdx.json > sbom.cdx.json
   ```

5. **Job dependency**: The SBOM job has `needs: [security]` — it runs after the security job, not in parallel with quality checks. The `if` condition includes `!cancelled()` so it runs even if earlier jobs failed.

6. **Release attachment**: The SBOM is attached to releases via `softprops/action-gh-release@v2` directly in the same job, gated on `startsWith(github.ref, 'refs/tags/')`.

### SBOM Merge Edge Cases

The `jq` merge approach has known limitations compared to `@cyclonedx/cyclonedx-cli merge`:

1. **Metadata loss**: `.[0] *` takes metadata from the first file (PHP SBOM). npm SBOM metadata (tool info for `@cyclonedx/cyclonedx-npm`, npm-specific `metadata.component`) is discarded. The merged SBOM will only show `cyclonedx-php-composer` as the generating tool.

2. **Component deduplication**: `unique_by(.purl // .name)` deduplicates by purl first, falling back to name. This is mostly correct but could merge distinct components that share a name but differ in ecosystem (unlikely in practice since PHP uses `pkg:composer/` and npm uses `pkg:npm/`).

3. **Dependencies section**: The jq merge does NOT merge the `dependencies[]` arrays. Only the PHP dependency tree is preserved; npm dependency relationships are lost. This affects NTIA compliance for the npm portion.

4. **Schema/version fields**: The jq merge preserves the PHP SBOM's `$schema`, `specVersion`, `serialNumber`, and `version` — the npm SBOM's values are discarded. This is acceptable since both target CycloneDX 1.5.

**Recommendation**: Consider switching to `@cyclonedx/cyclonedx-cli merge` (as originally designed) for production use. The jq approach was likely a pragmatic shortcut to avoid an npm global install step.

### False Positive Management

The `.grype.yaml` mechanism works as follows:

- **Inline creation**: The shared workflow creates a default `.grype.yaml` if the repo doesn't have one. This catches two known false positives where Grype matches unscoped CycloneDX component names (e.g., `gen-mapping`) against malware advisories for typosquatting npm packages (e.g., the malicious `gen-mapping` vs the legitimate `@jridgewell/gen-mapping`).

- **Per-repo override**: Repos can commit their own `.grype.yaml` to customize. Currently only `openregister/.grype.yaml` exists with the same two ignore rules.

- **Standalone workflows**: The standalone `sbom.yml` files (opencatalogi, openconnector, docudesk, softwarecatalog, mydash) do NOT create a `.grype.yaml` inline — they call `grype sbom:sbom.cdx.json --fail-on critical` directly. This means false positives will block these workflows unless a `.grype.yaml` is committed to the repo.

- **Maintenance burden**: As new false positives are discovered, they must be added to both the shared workflow's inline template AND any per-repo `.grype.yaml` files. There is no centralized false-positive list.

## Security Considerations

- SBOMs expose the full dependency tree including exact versions — this is intentional for transparency but means attackers can identify vulnerable versions. This is acceptable because the same information is already in `composer.lock` and `package-lock.json`.
- SBOM generation uses `--no-dev` / `--omit dev` to exclude development dependencies from the published SBOM, reducing unnecessary exposure.
- No secrets or credentials are involved in SBOM generation.

## NL Design System

Not applicable — no UI components involved.

## Trade-offs

| Trade-off | Decision | Rationale |
|-----------|----------|-----------|
| Full dependency tree vs production-only | Production-only | Reduces noise; dev dependencies aren't deployed |
| JSON vs XML format | JSON | Easier to parse programmatically, smaller files |
| Always-on vs opt-in | Default on, opt-out available | Maximizes coverage while allowing exceptions |
| Per-workflow vs dedicated workflow | Integrated into quality workflow | Fewer workflow files to maintain, consistent trigger conditions |
| Committed vs artifact-only | Committed after validation | External parties can scan directly without API access |
| Grype vs Trivy for CVE scanning | Grype | Native CycloneDX SBOM input, simple CI integration, same Anchore ecosystem |
| Fail on all CVEs vs critical-only | Critical-only (`--fail-on critical`) | Avoids blocking on low/medium issues while catching real risks |
