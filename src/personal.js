/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Personal settings entry: the LaunchPad profile fields a person fills in
 * themselves, such as their expertise tags (REQ-PEX-002). The form loads its
 * fields from the API, so this page needs no initial state. Below them, a
 * person reads their own activity (REQ-DWMS-005).
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createApp, h } from 'vue'
import MyActivitySettings from './views/settings/MyActivitySettings.vue'
import ProfileFieldsSettings from './views/settings/ProfileFieldsSettings.vue'

import './publicPath.js'

const app = createApp({
	name: 'LaunchpadPersonalRoot',
	render: () => h('div', [h(ProfileFieldsSettings), h(MyActivitySettings)]),
})

app.mixin({ methods: { t, n } })
app.mount('#launchpad-personal-settings')

export default app
