# license-header-consistency Specification

## Purpose
TBD - created by archiving change align-source-license-headers-to-eupl. Update Purpose after archive.

## Requirements
### Requirement: REQ-LIC-001 Source-file SPDX identifier is EUPL-1.2

@e2e exclude both scenarios read SPDX lines in checked-in source files, which no running instance serves or renders, so a browser has nothing to observe. The AGPL half is pinned by `scripts/check-license-headers.sh` (composer `lint:licenses`, part of `composer check:strict`), which greps `lib/`, `src/` and `appinfo/` for `SPDX-License-Identifier: AGPL-3.0` and exits 1. The positive half (every identifier reads `EUPL-1.2`) has no dedicated check; it holds today (every SPDX identifier under `lib/`, `src/` and `appinfo/` reads `EUPL-1.2`, measured 2026-11-19), and the shared workflow's REUSE lint job would report another licence, but it runs non-blocking for this app.

LaunchPad-authored source files MUST declare `EUPL-1.2` as their
`SPDX-License-Identifier`, matching `REUSE.toml`, `composer.json`,
`publiccode.yml`, and the `LICENSE` file. No LaunchPad-authored source file MUST
carry an `AGPL-3.0-or-later` (or any non-`EUPL-1.2`) `SPDX-License-Identifier`.

#### Scenario: Every PHP file under lib/ declares EUPL-1.2

- **GIVEN** the LaunchPad source tree
- **WHEN** the `SPDX-License-Identifier` lines under `lib/**/*.php` are collected
- **THEN** every one MUST read `EUPL-1.2`
- **AND** none MUST read `AGPL-3.0-or-later`

#### Scenario: Frontend source headers declare EUPL-1.2

- **GIVEN** a `src/**` Vue/JS/TS/CSS file that carries an SPDX header
- **WHEN** its `SPDX-License-Identifier` is read
- **THEN** it MUST read `EUPL-1.2`

### Requirement: REQ-LIC-002 No in-file licence contradiction

@e2e exclude a property of one checked-in PHP file's header comment, invisible at runtime. Pinned statically: `scripts/check-license-headers.sh` refuses an AGPL SPDX line beside the PHPDoc `@license EUPL-1.2`, and the PHPCS `@license` sniff in the shared quality workflow reads the PHPDoc tag. No check asserts "no other licence identifier" in the file.

A source file MUST NOT declare two different licences. When a file carries both
a PHPDoc `@license` tag and an `SPDX-License-Identifier`, the two MUST name the
same licence (`EUPL-1.2`).

#### Scenario: PHPDoc @license and SPDX line agree

- **GIVEN** `lib/Service/PermissionService.php`, whose PHPDoc reads `@license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12`
- **WHEN** its `SPDX-License-Identifier` is read
- **THEN** it MUST read `EUPL-1.2` (not `AGPL-3.0-or-later`)
- **AND** the file MUST contain no other licence identifier

### Requirement: REQ-LIC-003 Consistent copyright holder

@e2e exclude a property of checked-in header comments, invisible at runtime. No automated check pins it: `scripts/check-license-headers.sh` looks only for AGPL identifiers. It does not hold for the whole tree: on 2026-11-19, 12 files under `src/` still carry `SPDX-FileCopyrightText: 2026 LaunchPad Contributors`, for example `src/composables/useTileSearch.js` and `src/components/Widgets/Renderers/NewsWidget.vue`. The scenario as written names a PHP file, and no file under `lib/` carries that holder.

Every LaunchPad-authored source file's `SPDX-FileCopyrightText` MUST name the
same copyright holder as `REUSE.toml`, `publiccode.yml`, and the PHPDoc
`@copyright` tag — `Conduction B.V.` — rather than "LaunchPad Contributors".

#### Scenario: SPDX copyright matches REUSE and PHPDoc

- **GIVEN** a PHP file whose PHPDoc reads `@copyright 2024 Conduction b.v.`
- **WHEN** its `SPDX-FileCopyrightText` line is read
- **THEN** it MUST name `Conduction B.V.`
- **AND** it MUST NOT name "LaunchPad Contributors"

### Requirement: REQ-LIC-004 README licence badge matches the declared licence

@e2e exclude the subject is `README.md` in the repository, which the app does not serve. No automated check pins it. It holds today: the badge reads `license-EUPL--1.2` and the §License prose reads "licensed under the [EUPL-1.2]".

The README licence badge MUST state the same licence as the README §License
prose and the rest of the repository (`EUPL-1.2`).

#### Scenario: Badge and prose agree

- **GIVEN** `README.md` whose §License prose reads "licensed under the [EUPL-1.2]"
- **WHEN** the header licence badge is read
- **THEN** it MUST display `EUPL-1.2`
- **AND** it MUST NOT display `AGPL-3.0`

### Requirement: REQ-LIC-005 Regression guard for licence drift

@e2e exclude both scenarios are about CI behaviour on a commit (a build that fails, a linter verdict), not about a running instance. The first is `scripts/check-license-headers.sh`, which exits 1 and names each offending file and the `EUPL-1.2` replacement; it runs as composer `lint:licenses` inside `composer check:strict`. The second is the shared quality workflow's `REUSE compliance` job (`fsfe/reuse-action`), which runs for this app with `reuse-blocking` false, so a REUSE finding does not fail the build yet.

The repository MUST carry an automated check that fails if a LaunchPad-authored
source file reintroduces an `AGPL-3.0` `SPDX-License-Identifier`, and `reuse
lint` MUST pass against the reconciled tree.

#### Scenario: CI fails on a reintroduced AGPL header

- **GIVEN** the regression check is wired into CI
- **WHEN** a commit adds a file with `SPDX-License-Identifier: AGPL-3.0-or-later` under `lib/`
- **THEN** the check MUST fail the build
- **AND** the failure message MUST name the offending file and the expected `EUPL-1.2` identifier

#### Scenario: reuse lint passes on the reconciled tree

- **GIVEN** all source headers reconciled to EUPL-1.2 and Conduction B.V.
- **WHEN** `reuse lint` runs
- **THEN** it MUST report the tree as compliant

