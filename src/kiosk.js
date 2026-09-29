/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Kiosk player entry (/apps/launchpad/kiosk/{token}). Boots the full-screen
 * KioskPlayerView with the token from the URL. No login is required; the
 * player reads the playlist as JSON from the same route.
 *
 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createApp, h } from 'vue'
import KioskPlayerView from './views/KioskPlayerView.vue'

import './publicPath.js'

const pathSegments = window.location.pathname.replace(/\/+$/, '').split('/')
const token = pathSegments[pathSegments.length - 1] || ''

const app = createApp({
	name: 'LaunchpadKioskRoot',
	render: () => h(KioskPlayerView, { token }),
})

app.mixin({ methods: { t, n } })
app.mount('#kiosk-vue')

export default app
