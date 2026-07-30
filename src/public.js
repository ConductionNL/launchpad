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

// The token is the last path segment of /apps/launchpad/s/{token}.
//
// This previously read a `public-share-token` initial-state key first,
// described as the primary source. That read was dead: no PHP ever provides
// that key (PageController::publicShare renders the template without any
// provideInitialState call), so it always returned the fallback and the path
// parse below did the real work — as templates/public.php already documents.
// It also broke REQ-INIT-003, which routes every initial-state read through
// src/utils/loadInitialState.js. The page is served anonymously, so adding a
// key to that contract would be a server change with no consumer; reading the
// URL is the correct source here.
const pathSegments = window.location.pathname.replace(/\/+$/, '').split('/')
const token = pathSegments[pathSegments.length - 1] || ''

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
