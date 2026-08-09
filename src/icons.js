/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import CogOutline from 'vue-material-design-icons/CogOutline.vue'
import FileReplaceOutline from 'vue-material-design-icons/FileReplaceOutline.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'

/**
 * MDI icons LaunchPad's manifests name, keyed by the PascalCase name used
 * in `src/manifest.json` and `lib/Settings/launchpad_register.json`.
 *
 * This map is not decoration. `CnIcon` resolves a manifest icon name ONLY
 * through the registry `registerIcons()` populates, and it has no fallback
 * for a name it cannot find — an unregistered name renders NOTHING: no
 * glyph, no placeholder, no console error. So every name that appears as an
 * `icon` in a manifest has to appear here too, and the two lists have to be
 * kept in step by hand.
 *
 * Importing the icons individually (rather than pulling in the whole
 * vue-material-design-icons package) is what keeps the bundle small — the
 * package ships several thousand components.
 *
 * @see openspec/architecture — ADR-077 (semantic icon vocabulary)
 * @type {Record<string, import('vue').Component>}
 */
export const LAUNCHPAD_ICONS = {
	// Menu: "Dashboards" + the register's Dashboard schema (ADR-077 Tier A
	// concept `dashboard`).
	ViewDashboardOutline,
	// Menu: "Templates" (Tier B concept `template`).
	FileReplaceOutline,
	// Menu: "Settings" (Tier A concept `settings`).
	CogOutline,
}
