#!/usr/bin/env bash
#
# check-license-headers.sh — licence-drift regression guard (REQ-LIC-005).
#
# Fails the build if any LaunchPad-authored source file under lib/, src/ or
# appinfo/ reintroduces an AGPL-3.0 SPDX-License-Identifier. LaunchPad's
# canonical licence is EUPL-1.2 (LICENSE, composer.json, publiccode.yml,
# REUSE.toml); a stray AGPL-3.0 SPDX line contradicts every one of those.
#
# @spec openspec/changes/align-source-license-headers-to-eupl/specs/license-header-consistency/spec.md
#
# SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
# SPDX-License-Identifier: EUPL-1.2

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PATTERN='SPDX-License-Identifier: AGPL-3.0'
OFFENDERS="$(grep -rIl "$PATTERN" lib src appinfo 2>/dev/null || true)"

if [ -n "$OFFENDERS" ]; then
    echo "ERROR: AGPL-3.0 SPDX-License-Identifier found in LaunchPad-authored source." >&2
    echo "LaunchPad is licensed EUPL-1.2 — replace 'SPDX-License-Identifier: AGPL-3.0-or-later'" >&2
    echo "with 'SPDX-License-Identifier: EUPL-1.2' in the following file(s):" >&2
    echo "$OFFENDERS" | sed 's/^/  - /' >&2
    exit 1
fi

echo "OK: no AGPL-3.0 SPDX-License-Identifier in lib/, src/ or appinfo/ (EUPL-1.2 clean)."
