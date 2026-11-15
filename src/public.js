/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Anonymous public-share entry point. Boots the read-only
 * `DashboardPublicShareView`, which fetches the shared dashboard from
 * `/s/{token}/data` and renders it without a Nextcloud login. Mounted by the
 * `templates/public.php` page served at `/apps/launchpad/s/{token}`.
 */

// Populate the shared dashboard widget catalog so shared placements render.
// Widget types self-register via import-time side effects that `sideEffects`
// tree-shaking (ADR-061) would otherwise drop; calling this no-op forces the
// aggregator — and every widget registration — into the bundle. See main.js.
import {
	registerBuiltinDashboardWidgets,
	registerTranslations,
} from '@conduction/nextcloud-vue'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createPinia } from 'pinia'
import { createApp, h } from 'vue'
import DashboardPublicShareView from './views/DashboardPublicShareView.vue'
import { loadInitialState } from './utils/loadInitialState.js'
import { publicPageToken } from './utils/publicStart.js'

import './publicPath.js'
import 'gridstack/dist/gridstack.min.css'
// The library's own stylesheet (board look, widgets, dialogs). Without it
// the page only had the library CSS another app's bundle happened to load.
import '@conduction/nextcloud-vue/css/index.css'
registerBuiltinDashboardWidgets()
// The library's own strings (the greeting header among them) in the
// viewer's language. See main.js.
registerTranslations()

// Vue 3 has no global Vue constructor — pinia and t/n are installed on the
// app instance created below (`Vue.prototype.x` becomes an app-level mixin).
const pinia = createPinia()

// The token is the last path segment of /apps/launchpad/s/{token}, read from
// the URL: the share page is rendered without initial state. The public start
// page at /apps/launchpad/start is the exception (sharing-public-start-page
// D3): the server hands over the chosen link's token as `publicStartToken`,
// read through the initial-state contract (REQ-INIT-003), and the view adds
// its sign-in bar.
const { token, startPage } = publicPageToken(
	window.location.pathname,
	loadInitialState('public-start').publicStartToken,
)

// Props are flat in Vue 3 — the Vue-2 `{ props: { … } }` createElement data
// object is gone.
const app = createApp({
	name: 'LaunchpadPublicShareRoot',
	render: () => h(DashboardPublicShareView, { token, startPage }),
})

app.mixin({ methods: { t, n } })
app.use(pinia)
app.mount('#public-share-vue')

export default app
