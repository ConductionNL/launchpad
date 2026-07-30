/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workspace entry point. Implements the ADR-036 Decision 8 runtime-manifest
 * pattern for launchpad: a 5-line stub manifest is bundled with the app; at boot
 * the frontend fetches GET /apps/launchpad/api/manifest and replaces the stub
 * with the per-user manifest assembled from the user's visible dashboards.
 *
 * launchpad is the proving ground for this pattern because it has no "default
 * home page" — every page is user-specific, making a bundled static manifest
 * meaningless. The pattern is what OpenBuild-built apps will also use.
 *
 * Boot sequence:
 *  1. Vue + Pinia initialised.
 *  2. Initial-state snapshot loaded from the NC server's HTML.
 *  3. Stub manifest imported — used as the initial value while the runtime
 *     fetch is in flight (prevents a flash of empty state in most cases).
 *  4. Vue instance mounted with stub manifest.
 *  5. Runtime fetch fires (fire-and-forget) — when it resolves the reactive
 *     `runtimeManifest` ref is updated; App.vue watches it and passes the
 *     live value down to CnAppRoot.
 */

import './publicPath.js'

import { createApp, h, reactive } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import './services/widgetBridge.js'

import App from './App.vue'
import { loadInitialState } from './utils/loadInitialState.js'
import { mergeManifestFragments } from './utils/mergeManifestFragments.js'
import bundledStub from './manifest.json'
import 'gridstack/dist/gridstack.min.css'
import './styles/workspace.css'

// Populate the shared dashboard widget catalog. Each widget type self-registers
// via an import-time side effect aggregated in nc-vue; `sideEffects`
// tree-shaking (ADR-061) drops those bare side-effect imports unless a binding
// is used, which collapses the Add-Widget picker to only the types launchpad
// references directly (link + nc-widget). Calling this exported no-op forces
// the aggregator — and therefore every widget registration — into the bundle.
import { registerBuiltinDashboardWidgets, useAppManifest, registerDashboardWidget, CnNcWidgetWidget, CnNcDashboardWidgetForm } from '@conduction/nextcloud-vue'
registerBuiltinDashboardWidgets()

// nc-vue's own `CnNcWidgetWidget/index.js` self-registers `nc-widget` with
// `form: null` — a "renderer-only" entry its header comment justifies as
// "the matching CnNcWidgetWidgetForm is not yet present in this tree". That
// comment is stale: this nc-vue version DOES ship `CnNcDashboardWidgetForm`
// (wired to `CnNcWidgetGridPicker`), it was just never plugged into the
// registration. `listWidgetTypes()` — what `CnAddWidgetModal`'s type picker
// calls — filters out any entry with a null form, so "Nextcloud widget" was
// silently unreachable from Add Widget with no error anywhere.
// Complete the wiring here using nc-vue's own public, documented
// last-registration-wins registry API (the identical pattern
// registerDashboardWidgets.js itself uses to re-register `table`,
// `object-list`, and `map` after their bare self-registration) — no fork of
// the vendored component required.
registerDashboardWidget('nc-widget', {
	renderer: CnNcWidgetWidget,
	form: CnNcDashboardWidgetForm,
	defaultContent: {
		widgetId: '',
		displayMode: 'vertical',
	},
	displayName: 'Nextcloud widget',
	icon: 'ViewDashboard',
})

// Tier 1 manifest adoption (ADR-024): register the bundled manifest with
// nc-vue so the shared shell can read menu/page declarations. The vue-router
// definition below remains hand-wired (Tier 1 — not yet manifest-driven).
// Tier 3 (launchpad-manifest-tier-3) will replace hand-wired routes.
// `useAppManifest` comes from the package barrel. It was previously required
// from a `@conduction/nextcloud-vue/composables` SUBPATH, which the package
// does not expose (it declares no `exports` map) — so the require always threw
// and the surrounding try/catch silently skipped manifest registration
// entirely. Importing it from the barrel makes the registration actually run.
useAppManifest('launchpad', bundledStub)
// Note: GridStack v12 dropped the separate `gridstack-extra.min.css`
// helper file — the per-column-count CSS rules used by responsive
// breakpoints are now generated dynamically by the engine at init time.

// ADR-037: merge per-OpenSpec-change manifest fragments (src/manifest.d/*.json)
// onto the bundled stub so concurrent same-app builds touch disjoint files and
// never conflict on the shared manifest. No-op until a real fragment is added.
const mergedManifest = mergeManifestFragments(bundledStub)

// Vue 3 has no global Vue constructor — t/n are installed as an app-level
// mixin on the instance created at the bottom of this file.
const pinia = createPinia()

// Load the typed initial-state snapshot for the workspace page. Every key
// declared in REQ-INIT-002 is filled (defaults applied for missing keys
// by the reader); descendants `inject(key, default)` to read.
const initialState = loadInitialState('workspace')

// --- Runtime manifest loader (ADR-036 Decision 8) ---
//
// `useRuntimeManifest` from @conduction/nextcloud-vue is available in
// nc-vue ≥ 1.0.0-beta.57 but may not be present in the local source alias
// used during development builds. We implement the same contract inline so
// the runtime-manifest wiring works regardless of the local lib version.
//
// The runtime manifest ref is passed as a prop to App.vue; App.vue passes
// it to CnAppRoot (once launchpad migrates to CnAppRoot). For now, the ref is
// provided via the root Vue instance's `provide` so any descendant that
// needs it can `inject('runtimeManifest', null)`.
//
// NO deep-merge — the API response fully replaces the stub on success.
const runtimeManifest = reactive({ value: mergedManifest })
const manifestLoading = reactive({ value: true })

;(async () => {
	try {
		const url = generateUrl('/apps/launchpad/api/manifest')
		const response = await axios.get(url)

		if (response && response.status === 200 && response.data
				&& typeof response.data === 'object'
				&& response.data.$schema) {
			// Replace stub with live per-user manifest (no deep-merge per ADR-036)
			runtimeManifest.value = response.data
		}
	} catch (err) {
		// 404, network error, or unauthenticated — fall back to stub silently.
		// The existing launchpad UI works entirely from its Pinia stores and does
		// not depend on the manifest for page routing, so this is non-fatal.
		// eslint-disable-next-line no-console
		console.warn('[launchpad] Runtime manifest fetch failed; using stub', err)
	} finally {
		manifestLoading.value = false
	}
})()
// --- End runtime manifest loader ---

const app = createApp({
	name: 'LaunchpadRoot',
	render: () => h(App),
})

// Global t/n — an app-level mixin replaces Vue 2's global Vue.mixin.
app.mixin({ methods: { t, n } })
app.use(pinia)

// ADR-036 Decision 8: expose runtime manifest refs so descendants can
// reactively access the live per-user manifest. `reactive()` (Vue 2's
// Vue.observable) makes mutations propagate across the tree.
//
// Vue 3 moves provide() off the component options onto the app instance, so
// each key is registered individually rather than as a `provide:` object.
for (const [key, value] of Object.entries(initialState)) {
	app.provide(key, value)
}
app.provide('runtimeManifest', runtimeManifest)
app.provide('manifestLoading', manifestLoading)

app.mount('#workspace-vue')

export default app
