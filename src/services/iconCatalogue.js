/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Shared icon catalogue for CnIconBrowser across LaunchPad's icon-input
 * surfaces (tiles, org-nav, dashboards). LaunchPad already depends on `@mdi/js`
 * and stores icons as SVG path strings, so we use the path-based `mdiCatalogue`
 * adapter — the emitted/stored value is a self-contained path that
 * CnDashboardIcon / CnTileWidget render anywhere. Built once and frozen so it
 * isn't made reactive when referenced from components.
 */

import * as mdi from '@mdi/js'
import { mdiCatalogue } from '@conduction/nextcloud-vue'

export const ICON_CATALOGUE = Object.freeze(mdiCatalogue(mdi))
