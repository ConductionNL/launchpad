/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Admin settings entry point: Launchpad's admin page inside Nextcloud's
 * settings framework, `/settings/admin/launchpad` (admin-settings
 * REQ-ADM-032), mounted by `lib/Settings/AdminSettingsForm.php`. It loads no
 * app shell, manifest pages, router or workspace stores (REQ-ADM-035): only
 * the admin page itself, {@link AdminRoot}, with the admin initial state
 * provided to every tab (REQ-INIT-003).
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 */

import { registerIcons, registerTranslations } from '@conduction/nextcloud-vue'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createPinia } from 'pinia'
import { createApp, h } from 'vue'
import AdminRoot from './views/AdminRoot.vue'
import { LAUNCHPAD_ICONS } from './icons.js'
// Rendering the page calls WidgetService::getAvailableWidgets() server-side,
// which loads every dashboard widget's bundle onto the page; those register
// through OCA.Dashboard.register(), which the bridge provides here too.
import { widgetBridge } from './services/widgetBridge.js'
import { loadInitialState } from './utils/loadInitialState.js'

import './publicPath.js'
// The library's stylesheet is linked by the page (AdminSettingsForm adds
// css/nextcloud-vue.css), so it stays parsed between loads instead of being
// inserted from this bundle as text.
import './styles/modals.css'

// Keep the bridge's side-effecting construction in the bundle.
void widgetBridge

registerTranslations()
registerIcons(LAUNCHPAD_ICONS)

const target = document.getElementById('launchpad-admin-settings')
if (target) {
	const app = createApp({
		name: 'LaunchpadSettingsRoot',
		render: () => h(AdminRoot),
	})
	app.mixin({ methods: { t, n } })
	app.use(createPinia())
	// Each initial-state key, provided individually (Vue 3).
	for (const [key, value] of Object.entries(loadInitialState('admin'))) {
		app.provide(key, value)
	}
	app.mount(target)
}
