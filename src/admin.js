/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Admin entry point. Loads the typed initial-state contract via
 * {@link loadInitialState} and exposes every key down the component tree
 * via Vue 2's root `provide` option (REQ-INIT-003, REQ-INIT-004) — Vue 3
 * `app.provide(key, value)` semantics, achieved here through the root
 * options bag because LaunchPad runs on Vue 2.7.
 *
 * Provided values are plain (non-reactive) snapshots (REQ-INIT-005).
 */

import './publicPath.js'

import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

// Install the OCA.Dashboard shim before anything else runs. Rendering this
// admin page calls WidgetService::getAvailableWidgets() server-side, which goes
// through NC's IManager::getWidgets() — and that loads every dashboard widget's
// bundle onto the page as a side effect. Those legacy bundles register at module
// top-level via OCA.Dashboard.register(), which only exists on /apps/dashboard
// (or wherever this bridge runs). Importing the bridge here mirrors the main
// workspace entry (where it loads via the widgets store) so the injected widget
// bundles find a register() to call instead of throwing on undefined OCA.Dashboard.
import { widgetBridge } from './services/widgetBridge.js'

import AdminSettings from './components/admin/AdminSettings.vue'
import { loadInitialState } from './utils/loadInitialState.js'

// Reference the imported singleton so its side-effecting construction is not
// tree-shaken / flagged as an unused import.
// eslint-disable-next-line no-void
void widgetBridge

// Vue 3 has no global Vue constructor — t/n are installed as an app-level
// mixin on the instance created below.
const pinia = createPinia()

// Load the typed initial-state snapshot for the admin page (REQ-INIT-002).
// Plain (non-reactive) values; descendants `inject(key, default)` to read.
const initialState = loadInitialState('admin')

const app = createApp({
	name: 'LaunchpadAdminRoot',
	render: () => h(AdminSettings),
})

// Global t/n — an app-level mixin replaces Vue 2's global Vue.mixin.
app.mixin({ methods: { t, n } })
app.use(pinia)

// Vue 3 moves provide() off the component options onto the app instance, so
// each initial-state key is registered individually rather than as a
// `provide:` object.
for (const [key, value] of Object.entries(initialState)) {
	app.provide(key, value)
}

app.mount('#launchpad-admin-settings')

export default app
