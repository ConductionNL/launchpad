/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import WorkspaceApp from './WorkspaceApp.vue'

// Import gridstack CSS
import 'gridstack/dist/gridstack.min.css'

// Dashboard widget registry - captures registrations from other Nextcloud apps
// so we can render their widgets in our workspace grid.
//
// We use Object.defineProperty with getter/setter traps to make the intercept
// survive overwrites by @nextcloud/vue-dashboard or other packages that may
// replace OCA.Dashboard.register after our script runs.
const dashboardWidgetRegistry = {}

if (typeof window.OCA === 'undefined') { window.OCA = {} }
if (!window.OCA.Dashboard) { window.OCA.Dashboard = {} }

// Wrap a register function to capture callbacks into our registry
function wrapRegister(origFn) {
	return function(widgetId, callback) {
		if (!dashboardWidgetRegistry[widgetId]) {
			dashboardWidgetRegistry[widgetId] = {}
		}
		dashboardWidgetRegistry[widgetId].callback = callback
		if (typeof origFn === 'function') {
			origFn.call(window.OCA.Dashboard, widgetId, callback)
		}
	}
}

function wrapRegisterStatus(origFn) {
	return function(widgetId, callback) {
		if (!dashboardWidgetRegistry[widgetId]) {
			dashboardWidgetRegistry[widgetId] = {}
		}
		dashboardWidgetRegistry[widgetId].statusCallback = callback
		if (typeof origFn === 'function') {
			origFn.call(window.OCA.Dashboard, widgetId, callback)
		}
	}
}

// Use defineProperty so that if another script overwrites OCA.Dashboard.register,
// our setter captures the new function and re-wraps it.
let _currentRegister = window.OCA.Dashboard.register || null
let _currentRegisterStatus = window.OCA.Dashboard.registerStatus || null

Object.defineProperty(window.OCA.Dashboard, 'register', {
	get() {
		return wrapRegister(_currentRegister)
	},
	set(newFn) {
		_currentRegister = newFn
	},
	configurable: true,
	enumerable: true,
})

Object.defineProperty(window.OCA.Dashboard, 'registerStatus', {
	get() {
		return wrapRegisterStatus(_currentRegisterStatus)
	},
	set(newFn) {
		_currentRegisterStatus = newFn
	},
	configurable: true,
	enumerable: true,
})

// Also protect against OCA.Dashboard itself being replaced entirely
const _interceptedDashboard = window.OCA.Dashboard
Object.defineProperty(window.OCA, 'Dashboard', {
	get() {
		return _interceptedDashboard
	},
	set(newDash) {
		// Another script is replacing OCA.Dashboard — absorb its properties
		// but keep our intercepted register/registerStatus
		if (newDash && typeof newDash === 'object') {
			Object.keys(newDash).forEach(key => {
				if (key !== 'register' && key !== 'registerStatus') {
					_interceptedDashboard[key] = newDash[key]
				} else {
					// Capture the new underlying function via our setter
					_interceptedDashboard[key] = newDash[key]
				}
			})
		}
	},
	configurable: true,
	enumerable: true,
})

// Expose registry for ApiWidget to use
window._sendentDashboardRegistry = dashboardWidgetRegistry

const View = createApp(WorkspaceApp)
View.config.globalProperties.t = t

// Load initial state
const widgets = loadState('sendentworkspace', 'widgets')
const layout = loadState('sendentworkspace', 'layout')
const primaryGroup = loadState('sendentworkspace', 'primaryGroup')
const isAdmin = loadState('sendentworkspace', 'isAdmin')
const activeDashboardId = loadState('sendentworkspace', 'activeDashboardId', '')
const dashboardSource = loadState('sendentworkspace', 'dashboardSource', 'group')
const groupDashboards = loadState('sendentworkspace', 'groupDashboards', [])
const userDashboards = loadState('sendentworkspace', 'userDashboards', [])
const allowUserDashboards = loadState('sendentworkspace', 'allowUserDashboards', false)
const primaryGroupName = loadState('sendentworkspace', 'primaryGroupName', '')

View.provide('widgets', widgets)
View.provide('layout', layout)
View.provide('primaryGroup', primaryGroup)
View.provide('primaryGroupName', primaryGroupName)
View.provide('isAdmin', isAdmin)
View.provide('activeDashboardId', activeDashboardId)
View.provide('dashboardSource', dashboardSource)
View.provide('groupDashboards', groupDashboards)
View.provide('userDashboards', userDashboards)
View.provide('allowUserDashboards', allowUserDashboards)

View.mount('#workspace-vue')
