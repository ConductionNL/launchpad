/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * V2 kind-tagged component registry (ADR-036). `CnPageRenderer` resolves every
 * `type: "custom"` page's `component` string against the `kind: "page"` entries
 * here, so a page declaring a component name that is absent renders nothing.
 *
 * 🔴 `WorkspaceApp` IS A PAGE HERE, WHICH IT WAS NOT BEFORE. Until
 * `launchpad-manifest-tier-3` it was the app's ONLY child — `App.vue` rendered
 * it directly and there was no router. It is the dashboard route's view now,
 * and the manifest names it, so it has to be resolvable by name.
 */

import AdminSettingsRedirect from './views/AdminSettingsRedirect.vue'
import WorkspaceApp from './views/WorkspaceApp.vue'

export default {
	WorkspaceApp: { kind: 'page', component: WorkspaceApp },
	AdminSettingsRedirect: { kind: 'page', component: AdminSettingsRedirect },
}
