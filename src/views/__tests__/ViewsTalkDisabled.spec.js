/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A collaborative dashboard with Talk disabled renders fully and surfaces
 * no error (openregister-leaf-integrations task 15, REQ-LEAF-004): the
 * mounted page keeps its region and its widgets, the discussion leaves no
 * trace in the DOM, nothing asks for the Talk integration, and neither a
 * console error nor an error toast appears. The same mount with Talk
 * installed shows the discussion, so the test can tell the two apart.
 * DashboardDiscussion is mounted for real; only its leaf is stubbed.
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-15
 */

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

// p-queue (pulled in by @nextcloud/vue) hands itself to Node's
// `events.setMaxListeners`, which refuses anything but a Node EventEmitter
// or a DOM EventTarget and throws under jsdom. A browser has no
// `process.getBuiltinModule`, so the call never runs there; this spec gives
// p-queue the browser's answer.
vi.hoisted(() => {
	const builtin = globalThis.process?.getBuiltinModule
	if (typeof builtin === 'function') {
		globalThis.process.getBuiltinModule = (id) => (id === 'node:events' || id === 'events' ? undefined : builtin(id))
	}
})

vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		t: (_app, key) => key,
		translate: (_app, key) => key,
		translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
	}
})
vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
	showError: vi.fn(),
}))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, _key, fallback) => fallback),
}))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		generateUrl: (path) => path,
	}
})

const BASE = '/apps/launchpad'

/**
 * A stub that renders nothing and keeps every bound prop / listener in `$attrs`.
 *
 * @param {string} name Component name (for findComponent).
 * @param {object} extra Extra options (props, template, inject).
 * @return {object} the stub.
 */
function passthrough(name, extra = {}) {
	return {
		name,
		inheritAttrs: false,
		template: '<div />',
		...extra,
	}
}

const stubs = {
	teleport: { template: '<div class="teleport-stub"><slot /></div>' },
	DashboardSwitcherSidebar: passthrough('DashboardSwitcherSidebar', {
		props: { isOpen: Boolean },
		template: '<div class="stub-sidebar" :data-open="String(isOpen)" />',
	}),
	SidebarBackdrop: passthrough('SidebarBackdrop', {
		template: '<div class="stub-backdrop" />',
	}),
	DashboardRowActions: passthrough('DashboardRowActions', {
		template: '<div class="stub-row-actions" />',
	}),
	DashboardMoreMenu: passthrough('DashboardMoreMenu', {
		template: '<div class="stub-more-menu" />',
	}),
	NcButton: {
		name: 'NcButton',
		template: '<button><slot name="icon" /><slot /></button>',
	},
	MenuIcon: { name: 'MenuIcon', template: '<i class="stub-menu-icon" />' },
	ShareVariant: { name: 'ShareVariant', template: '<i class="stub-share-icon" />' },
	ViewDashboard: { template: '<i />' },
	NcLoadingIcon: { template: '<i />' },
	NcEmptyContent: {
		name: 'NcEmptyContent',
		props: { name: String, description: String },
		template: '<div class="stub-empty">{{ description }}</div>',
	},
	StartPageMenu: passthrough('StartPageMenu'),
	HiddenWidgetsControl: passthrough('HiddenWidgetsControl'),
	DeleteDashboardDialog: passthrough('DeleteDashboardDialog', {
		props: { open: Boolean, name: String },
	}),
	CnConfirmDialog: passthrough('CnConfirmDialog', {
		template: '<div class="stub-confirm" />',
	}),
	VersionHistoryModal: passthrough('VersionHistoryModal', {
		props: { open: Boolean },
	}),
	AcknowledgementReportModal: passthrough('AcknowledgementReportModal', {
		props: { open: Boolean, announcementKey: String },
	}),
	DashboardReactions: passthrough('DashboardReactions'),
	CnIntegrationTab: passthrough('CnIntegrationTab', {
		props: { integrationId: String, objectId: String },
		template: '<div class="stub-talk-leaf" />',
	}),
	DashboardLanguageHeading: passthrough('DashboardLanguageHeading'),
	DashboardBreadcrumb: passthrough('DashboardBreadcrumb'),
	EditLockBanner: passthrough('EditLockBanner', { props: { status: String } }),
	CoEditorsBanner: passthrough('CoEditorsBanner', { props: { editors: Array } }),
	CnDashboardGrid: {
		name: 'CnDashboardGrid',
		props: { layout: { type: Array, default: () => [] } },
		template:
			'<div class="stub-grid"><template v-for="item in layout" :key="item.id"><slot name="widget" :item="item" /></template></div>',
	},
	TileWidget: passthrough('TileWidget', {
		props: { placementId: [Number, String] },
		template: '<div class="stub-tile" />',
	}),
	WidgetWrapper: passthrough('WidgetWrapper', {
		props: { placement: Object },
		template:
			'<div class="stub-wrapper"><div v-if="placement.ground" class="cn-header-widget--ground" /></div>',
	}),
	WidgetPickerModal: passthrough('WidgetPickerModal'),
	CnAddWidgetModal: passthrough('CnAddWidgetModal', {
		props: { show: Boolean, editingWidget: Object },
		// Mirrors CnNcDashboardWidgetForm, which reads the injected catalog.
		inject: { widgets: { default: null } },
		template:
			'<ul class="stub-add-widget"><li v-for="w in widgets || []" :key="w.id">{{ w.id }}</li></ul>',
	}),
	DashboardConfigModal: passthrough('DashboardConfigModal', {
		props: { open: Boolean, mode: String, initialTab: String },
	}),
	CnWidgetStyleEditorModal: passthrough('CnWidgetStyleEditorModal', {
		props: { show: Boolean, widget: Object },
	}),
	TileEditor: passthrough('TileEditor', {
		props: { open: Boolean, tile: Object },
	}),
	WidgetContextMenu: passthrough('WidgetContextMenu', {
		props: { top: Number, left: Number },
		template: '<div class="stub-ctx" :data-top="top" :data-left="left" />',
	}),
	ReadConfirmationDialog: passthrough('ReadConfirmationDialog', {
		props: { placement: Object },
		template: '<div class="stub-read-confirmation" />',
	}),
	WidgetMovePanel: passthrough('WidgetMovePanel', {
		props: { open: Boolean, placement: Object },
	}),
	VisibilityRulesModal: passthrough('VisibilityRulesModal', {
		props: { open: Boolean, placementId: [Number, String] },
	}),
}

let wrapper = null

/**
 * Answer the axios mock per URL.
 *
 * @param {object} overrides `{get: fn(url), post: fn(url)}` replacements.
 */
function routeAxios(overrides = {}) {
	axios.get.mockImplementation(
		overrides.get
			?? ((url) => {
				if (url === `${BASE}/api/dashboards/default`) {
					return Promise.resolve({ data: { uuid: 'u-default' } })
				}
				return Promise.resolve({ data: {} })
			}),
	)
	axios.post.mockImplementation(
		overrides.post ?? (() => Promise.resolve({ data: { acquiredAt: 1 } })),
	)
	axios.put.mockImplementation(() => Promise.resolve({ data: {} }))
	axios.delete.mockImplementation(() => Promise.resolve({ data: {} }))
}

/**
 * Mount Views with real stores whose server-bound actions are mocked.
 *
 * @param {object} opts Store state and injections.
 * @return {Promise<{wrapper: object, dashStore: object}>} the mount.
 */
async function mountViews({
	activeDashboard = { id: 7, uuid: 'u-7', name: 'Home', isOwner: true },
	widgetPlacements = [],
	permissionLevel = 'full',
	isAdmin = false,
	allowUserDashboards = true,
	availableWidgets = [],
	attach = false,
} = {}) {
	const pinia = createPinia()
	setActivePinia(pinia)

	const { default: Views } = await import('../Views.vue')
	const { useDashboardStore } = await import('../../stores/dashboard.js')
	const { useWidgetStore } = await import('../../stores/widgets.js')
	const { useTileStore } = await import('../../stores/tiles.js')

	const dashStore = useDashboardStore()
	dashStore.activeDashboard = activeDashboard
	dashStore.dashboards = activeDashboard ? [activeDashboard] : []
	dashStore.widgetPlacements = widgetPlacements
	dashStore.permissionLevel = permissionLevel
	dashStore.loading = false
	dashStore.loadDashboards = vi.fn(() => Promise.resolve())
	dashStore.fetchPendingAcknowledgements = vi.fn(() => Promise.resolve())
	dashStore.recordViewEvent = vi.fn()
	dashStore.switchDashboard = vi.fn(() => Promise.resolve())
	dashStore.removeWidgetFromDashboard = vi.fn(() => Promise.resolve())
	dashStore.updateWidgetPlacement = vi.fn(() => Promise.resolve())
	dashStore.updatePlacements = vi.fn()

	const widgetStore = useWidgetStore()
	widgetStore.availableWidgets = availableWidgets
	widgetStore.loadAvailableWidgets = vi.fn(() => Promise.resolve())

	const tileStore = useTileStore()
	tileStore.tiles = []
	tileStore.loadTiles = vi.fn(() => Promise.resolve())

	wrapper = mount(Views, {
		global: {
			plugins: [pinia],
			provide: {
				isAdmin,
				allowUserDashboards,
				primaryGroupName: '',
			},
			// Attached mounts keep the real Teleport, so the controls land
			// where the view sends them.
			stubs: attach ? { ...stubs, teleport: false } : stubs,
			mocks: {
				t: (_app, key) => key,
				n: (_app, sing, plur, count) => (count === 1 ? sing : plur),
			},
		},
		attachTo: attach ? document.body : undefined,
	})
	await flushPromises()
	return { wrapper, dashStore }
}

beforeEach(() => {
	vi.clearAllMocks()
	globalThis.t = (_app, key) => key
	window.history.replaceState(null, '', '/apps/launchpad')
	routeAxios()
})

afterEach(() => {
	if (wrapper) {
		wrapper.unmount()
		wrapper = null
	}
	document.body.innerHTML = ''
})

const groupDashboard = { id: 7, uuid: 'g-7', name: 'Team', type: 'group_shared', isOwner: false }
const placement = { id: 11, widgetId: 'tile', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2, content: {} }

/**
 * Mount the page on a group dashboard with these apps installed, recording console errors.
 *
 * @param {object} appswebroots The installed apps' web roots.
 * @return {Promise<{wrapper: object, errors: object}>} the mount and the console.error spy.
 */
async function mountWithApps(appswebroots) {
	window.OC = { ...(window.OC || {}), currentUser: 'alice', appswebroots }
	const errors = vi.spyOn(console, 'error')
	const mounted = await mountViews({ activeDashboard: groupDashboard, widgetPlacements: [placement] })
	await flushPromises()
	return { wrapper: mounted.wrapper, errors }
}

// Load the page before any console spy: the library's own load-time
// notices are not the page's errors.
beforeAll(async () => {
	await import('../Views.vue')
}, 90000)

afterEach(() => {
	vi.restoreAllMocks()
	delete window.OC.appswebroots
})

describe('Views — a collaborative dashboard with Talk disabled', () => {
	it('renders the dashboard and its widgets, with no discussion and no error', async () => {
		const { wrapper, errors } = await mountWithApps({ openregister: '/apps/openregister' })

		expect(wrapper.find('[data-testid="launchpad-dashboard-region"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="launchpad-dashboard-region"]').attributes('data-dashboard-uuid')).toBe('g-7')
		expect(wrapper.findComponent({ name: 'CnDashboardGrid' }).props('layout').map((p) => p.id)).toEqual([11])
		expect(wrapper.find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
		expect(wrapper.find('.stub-talk-leaf').exists()).toBe(false)

		const requested = [...axios.get.mock.calls, ...axios.post.mock.calls].map(([url]) => String(url))
		expect(requested.filter((url) => url.includes('integrations/talk') || url.includes('/shares'))).toEqual([])
		expect(errors).not.toHaveBeenCalled()
		expect(showError).not.toHaveBeenCalled()
	}, 90000)

	it('renders nothing of the discussion without OpenRegister either', async () => {
		const { wrapper, errors } = await mountWithApps({ spreed: '/apps/spreed' })

		expect(wrapper.find('[data-testid="launchpad-dashboard-region"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
		expect(errors).not.toHaveBeenCalled()
		expect(showError).not.toHaveBeenCalled()
	}, 90000)

	it('shows the discussion on the same dashboard once Talk is installed', async () => {
		const { wrapper } = await mountWithApps({ openregister: '/apps/openregister', spreed: '/apps/spreed' })

		expect(wrapper.find('[data-testid="dashboard-discussion"]').exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'CnIntegrationTab' }).props()).toEqual({ integrationId: 'talk', objectId: 'g-7' })
	}, 90000)
})
