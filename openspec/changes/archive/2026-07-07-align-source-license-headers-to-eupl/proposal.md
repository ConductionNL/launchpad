# Align source licence headers and README badge to EUPL-1.2

## Why

LaunchPad's canonical licence is **EUPL-1.2**. Every authoritative declaration
says so:

- `LICENSE` — the full EUROPEAN UNION PUBLIC LICENCE v. 1.2 text
- `composer.json` — `"license": "EUPL-1.2"`
- `publiccode.yml` — `license: EUPL-1.2`
- `REUSE.toml` — `SPDX-License-Identifier = "EUPL-1.2"` for `**/*.php`, `**/*.vue`, `**/*.js`, `**/*.ts`, `**/*.css`
- `appinfo/info.xml` description — "Free and open source under the EUPL-1.2 license."
- `README.md` §License — "This project is licensed under the [EUPL-1.2]"

But the **source files and one badge contradict every one of those**, and
LaunchPad is the only app in the Conduction fleet that does so:

1. **Source-file SPDX headers say the wrong licence.** Every PHP file under
   `lib/` carries `SPDX-License-Identifier: AGPL-3.0-or-later`. This contradicts
   `REUSE.toml` (which declares those same files EUPL-1.2), `composer.json`, and
   the `LICENSE` file. Sibling apps openregister, opencatalogi, docudesk and
   pipelinq all emit `SPDX-License-Identifier: EUPL-1.2` — launchpad is the
   outlier.

2. **The SPDX line contradicts the PHPDoc in the same file.** e.g.
   `lib/Service/PermissionService.php` carries
   `@license EUPL-1.2 https://joinup.ec.europa.eu/...` **and**
   `SPDX-License-Identifier: AGPL-3.0-or-later` — two conflicting licence
   statements in one docblock.

3. **The copyright holder is inconsistent.** SPDX headers say
   `SPDX-FileCopyrightText: 2024 LaunchPad Contributors`, while the PHPDoc
   `@copyright` and `REUSE.toml`/`publiccode.yml` say **Conduction B.V.**

4. **The README badge contradicts the README text.** The header badge reads
   `license-AGPL--3.0` while README §License says EUPL-1.2.

This is exactly the "README metadata drift" flagged in the 2026-06-11 readiness
verdict, plus the deeper source-header drift underneath it. A reader auditing
the repo for reuse/compliance gets a different answer depending on which file
they open — an honesty defect for a project that markets itself as EUPL-1.2 and
sells into public-sector procurement where licence provenance is scrutinised.

**Out of scope on purpose:** `appinfo/info.xml`'s `<licence>agpl</licence>`
element. That token is a fleet-wide convention driven by the Nextcloud
app-store `info.xsd` / appstore acceptance list (openregister, opencatalogi,
docudesk and procest all set `<licence>agpl</licence>` while shipping EUPL-1.2
source), so changing it in isolation would fight a deliberate fleet decision.
This change reconciles the artefacts LaunchPad fully controls and that are
unambiguously wrong; the `info.xml` token is left to a fleet-level decision.

## What Changes

- Every source-file `SPDX-License-Identifier` under LaunchPad's own code
  (`lib/**` PHP, plus any `src/**` Vue/JS/TS/CSS that carry a header) MUST read
  `EUPL-1.2`, matching `REUSE.toml` and `composer.json`. No file's SPDX line
  and PHPDoc `@license` may state different licences.
- Every source-file `SPDX-FileCopyrightText` MUST name the same copyright holder
  as `REUSE.toml` and the PHPDoc `@copyright` — **Conduction B.V.** — rather
  than "LaunchPad Contributors".
- The README licence badge MUST state EUPL-1.2, matching the README §License
  text and the rest of the fleet.
- A lightweight repo check MUST assert no `AGPL-3.0` SPDX identifier remains in
  LaunchPad's own source tree, so the drift cannot silently return.

## Capabilities

### New Capabilities

- `license-header-consistency` — a single canonical licence (EUPL-1.2) and a
  single canonical copyright holder (Conduction B.V.) across every source-file
  header, the README badge, and `REUSE.toml`, with a guard preventing
  regression.

### Modified Capabilities

(none)

## Impact

**Affected files (reconciliation only — no behavioural code change):**

- `lib/**/*.php` — SPDX header licence `AGPL-3.0-or-later` → `EUPL-1.2`; copyright text → `Conduction B.V.`
- `src/**` source files that carry an SPDX header — same reconciliation
- `appinfo/info.xml` — the file's own docblock SPDX header (not the `<licence>` element)
- `README.md` — licence badge `AGPL-3.0` → `EUPL-1.2`
- CI / a small script — assert no `AGPL-3.0` SPDX identifier remains and `reuse lint` passes

**No runtime behaviour changes.** This is a metadata/honesty reconciliation.
