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

import Vue from 'vue'
import { PiniaVuePlugin, createPinia } from 'pinia'
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

// Global functions
Vue.mixin({
	methods: {
		t,
		n,
	},
})

Vue.use(PiniaVuePlugin)
const pinia = createPinia()

// Load the typed initial-state snapshot for the admin page (REQ-INIT-002).
// Plain (non-reactive) values; descendants `inject(key, default)` to read.
const initialState = loadInitialState('admin')

const app = new Vue({
	el: '#launchpad-admin-settings',
	pinia,
	provide: { ...initialState },
	render: h => h(AdminSettings),
})

export default app
