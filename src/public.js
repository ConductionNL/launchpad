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

import Vue from 'vue'
import { PiniaVuePlugin, createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import DashboardPublicShareView from './views/DashboardPublicShareView.vue'
import 'gridstack/dist/gridstack.min.css'

Vue.use(PiniaVuePlugin)
const pinia = createPinia()

Vue.prototype.t = t
Vue.prototype.n = n

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

const app = new Vue({
	el: '#public-share-vue',
	pinia,
	render: h => h(DashboardPublicShareView, { props: { token } }),
})

export default app
