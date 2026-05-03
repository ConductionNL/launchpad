/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import AdminApp from './AdminApp.vue'

// Import gridstack CSS (needed for the workspace editor)
import 'gridstack/dist/gridstack.min.css'

// Dashboard widget registry - captures registrations from other Nextcloud apps
// so we can render their widgets when previewing in the admin editor.
const dashboardWidgetRegistry = {}

if (typeof window.OCA === 'undefined') { window.OCA = {} }
if (!window.OCA.Dashboard) { window.OCA.Dashboard = {} }

const origRegister = window.OCA.Dashboard.register || function() {}
const origRegisterStatus = window.OCA.Dashboard.registerStatus || function() {}

window.OCA.Dashboard.register = function(widgetId, callback) {
	if (!dashboardWidgetRegistry[widgetId]) {
		dashboardWidgetRegistry[widgetId] = {}
	}
	dashboardWidgetRegistry[widgetId].callback = callback
	origRegister.call(window.OCA.Dashboard, widgetId, callback)
}

window.OCA.Dashboard.registerStatus = function(widgetId, callback) {
	if (!dashboardWidgetRegistry[widgetId]) {
		dashboardWidgetRegistry[widgetId] = {}
	}
	dashboardWidgetRegistry[widgetId].statusCallback = callback
	origRegisterStatus.call(window.OCA.Dashboard, widgetId, callback)
}

window._sendentDashboardRegistry = dashboardWidgetRegistry

const View = createApp(AdminApp)
View.config.globalProperties.t = t

// Load initial state
const allGroups = loadState('sendentworkspace', 'allGroups')
const configuredGroups = loadState('sendentworkspace', 'configuredGroups')
const widgets = loadState('sendentworkspace', 'widgets', [])
const allowUserDashboards = loadState('sendentworkspace', 'allowUserDashboards', false)

View.provide('allGroups', allGroups)
View.provide('configuredGroups', configuredGroups)
View.provide('widgets', widgets)
View.provide('allowUserDashboards', allowUserDashboards)

View.mount('#workspace-admin-vue')
