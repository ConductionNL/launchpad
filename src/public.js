/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Anonymous public-share entry point. Boots the read-only
 * `DashboardPublicShareView`, which fetches the shared dashboard from
 * `/s/{token}/data` and renders it without a Nextcloud login. Mounted by the
 * `templates/public.php` page served at `/apps/launchpad/s/{token}`.
 */

import './publicPath.js'

import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import DashboardPublicShareView from './views/DashboardPublicShareView.vue'
import 'gridstack/dist/gridstack.min.css'

// Populate the shared dashboard widget catalog so shared placements render.
// Widget types self-register via import-time side effects that `sideEffects`
// tree-shaking (ADR-061) would otherwise drop; calling this no-op forces the
// aggregator — and every widget registration — into the bundle. See main.js.
import { registerBuiltinDashboardWidgets } from '@conduction/nextcloud-vue'
registerBuiltinDashboardWidgets()

// Vue 3 has no global Vue constructor — pinia and t/n are installed on the
// app instance created below (`Vue.prototype.x` becomes an app-level mixin).
const pinia = createPinia()

// The token is provided as initial state by PageController::publicShare; fall
// back to the last path segment of /apps/launchpad/s/{token} if absent.
let token = ''
try {
	token = loadState('launchpad', 'public-share-token', '')
} catch (e) {
	token = ''
}
if (!token) {
	const parts = window.location.pathname.replace(/\/+$/, '').split('/')
	token = parts[parts.length - 1] || ''
}

// Props are flat in Vue 3 — the Vue-2 `{ props: { … } }` createElement data
// object is gone.
const app = createApp({
	name: 'LaunchpadPublicShareRoot',
	render: () => h(DashboardPublicShareView, { token }),
})

app.mixin({ methods: { t, n } })
app.use(pinia)
app.mount('#public-share-vue')

export default app
