---
capability: license-header-consistency
delta: true
status: draft
---

# Licence Header Consistency — Delta from change `align-source-license-headers-to-eupl`

## ADDED Requirements

### Requirement: REQ-LIC-001 Source-file SPDX identifier is EUPL-1.2

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

A source file MUST NOT declare two different licences. When a file carries both
a PHPDoc `@license` tag and an `SPDX-License-Identifier`, the two MUST name the
same licence (`EUPL-1.2`).

#### Scenario: PHPDoc @license and SPDX line agree

- **GIVEN** `lib/Service/PermissionService.php`, whose PHPDoc reads `@license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12`
- **WHEN** its `SPDX-License-Identifier` is read
- **THEN** it MUST read `EUPL-1.2` (not `AGPL-3.0-or-later`)
- **AND** the file MUST contain no other licence identifier

### Requirement: REQ-LIC-003 Consistent copyright holder

Every LaunchPad-authored source file's `SPDX-FileCopyrightText` MUST name the
same copyright holder as `REUSE.toml`, `publiccode.yml`, and the PHPDoc
`@copyright` tag — `Conduction B.V.` — rather than "LaunchPad Contributors".

#### Scenario: SPDX copyright matches REUSE and PHPDoc

- **GIVEN** a PHP file whose PHPDoc reads `@copyright 2024 Conduction b.v.`
- **WHEN** its `SPDX-FileCopyrightText` line is read
- **THEN** it MUST name `Conduction B.V.`
- **AND** it MUST NOT name "LaunchPad Contributors"

### Requirement: REQ-LIC-004 README licence badge matches the declared licence

The README licence badge MUST state the same licence as the README §License
prose and the rest of the repository (`EUPL-1.2`).

#### Scenario: Badge and prose agree

- **GIVEN** `README.md` whose §License prose reads "licensed under the [EUPL-1.2]"
- **WHEN** the header licence badge is read
- **THEN** it MUST display `EUPL-1.2`
- **AND** it MUST NOT display `AGPL-3.0`

### Requirement: REQ-LIC-005 Regression guard for licence drift

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
