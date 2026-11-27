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

import { defineAsyncComponent } from 'vue'
// The dashboard view is the page nearly every visit lands on, so it ships in
// launchpad-main and renders in the same task as the app mount. As a lazy chunk
// it evaluated only after Nextcloud's own load handlers, ~100 ms later on
// screen (first useful content, ADR-124).
// @spec openspec/specs/runtime-shell/spec.md
import WorkspaceApp from './views/WorkspaceApp.vue'

const AdminPage = defineAsyncComponent(() => import(/* webpackChunkName: "admin-page" */ './views/AdminPage.vue').then((m) => m.default))

export default {
	WorkspaceApp: { kind: 'page', component: WorkspaceApp },
	// The administration (admin-settings REQ-ADM-030), loaded with the admin routes.
	AdminPage: { kind: 'page', component: AdminPage },
}
