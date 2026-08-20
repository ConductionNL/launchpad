# Tasks — align-source-license-headers-to-eupl

## Tasks

- [x] Task 1: Replace `SPDX-License-Identifier: AGPL-3.0-or-later` with `SPDX-License-Identifier: EUPL-1.2` in every LaunchPad-authored PHP file under `lib/` (REQ-LIC-001, REQ-LIC-002). Do not touch vendored / third-party files.
- [x] Task 2: Apply the same SPDX reconciliation to any `src/**` Vue/JS/TS/CSS file that carries an `AGPL-3.0` header (REQ-LIC-001).
- [x] Task 3: Reconcile `appinfo/info.xml`'s own docblock `SPDX-License-Identifier` to `EUPL-1.2`; leave the `<licence>agpl</licence>` app-store element unchanged (documented fleet convention, out of scope).
- [x] Task 4: Replace `SPDX-FileCopyrightText: … LaunchPad Contributors` with `SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>` so the SPDX copyright holder matches `REUSE.toml`, `publiccode.yml`, and the PHPDoc `@copyright` (REQ-LIC-003).
- [x] Task 5: Change the README licence badge from `license-AGPL--3.0` to `license-EUPL--1.2` so the badge matches the README §License prose (REQ-LIC-004).
- [x] Task 6: Add a lightweight regression check (script wired into CI) that greps LaunchPad-authored source for an `AGPL-3.0` `SPDX-License-Identifier` and fails the build if any is found, naming the offending file (REQ-LIC-005).
- [x] Task 7: Confirm `reuse lint` passes against the reconciled tree (REQ-LIC-005).

## Verification

- `openspec validate align-source-license-headers-to-eupl --strict` exits clean.
- `grep -rl "SPDX-License-Identifier: AGPL-3.0-or-later" lib/ src/ appinfo/` returns nothing.
- No file carries both `@license EUPL-1.2` and `SPDX-License-Identifier: AGPL-3.0-or-later`.
- `grep -c "LaunchPad Contributors" $(git ls-files 'lib/**/*.php')` is `0`.
- The README badge and README §License prose both read EUPL-1.2.
- `reuse lint` reports the tree compliant.

## Tests (company-wide ADR-009)

- The regression guard from Task 6 is itself the test surface: a fixture commit adding an `AGPL-3.0` SPDX header MUST make the check fail; the reconciled tree MUST make it pass.
- No functional/unit tests required — this change alters only licence/copyright metadata and documentation, with no runtime behaviour change.

## Documentation (company-wide ADR-010)

- No user-facing docs beyond the README badge fix (Task 5). `REUSE.toml` already documents the intended EUPL-1.2 / Conduction B.V. state; this change makes the source headers match it.

## i18n (company-wide ADR-005)

- No user-facing strings introduced.
