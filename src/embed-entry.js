/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Embed entry point (embedded-analytics task 23). Boots `EmbedView` in the
 * page `templates/embed.php` serves at `/apps/launchpad/embed/{subjectType}/{subjectId}`.
 * The view reads its subject from the path and its token from `?token=` or
 * the SDK's handshake, verifies the parent's origin against the token's host
 * origins and relays events to it.
 */

import {
	registerBuiltinDashboardWidgets,
	registerTranslations,
} from '@conduction/nextcloud-vue'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createPinia } from 'pinia'
import { createApp, h } from 'vue'
import EmbedView from './views/EmbedView.vue'

import './publicPath.js'
import 'gridstack/dist/gridstack.min.css'
import '@conduction/nextcloud-vue/css/index.css'

// Widget types self-register on import; see public.js.
registerBuiltinDashboardWidgets()
registerTranslations()

const app = createApp({
	name: 'LaunchpadEmbedRoot',
	render: () => h(EmbedView),
})

app.mixin({ methods: { t, n } })
app.use(createPinia())
app.mount('#embed-vue')

export default app
