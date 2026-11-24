/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Mounted Views.vue: the template's event wiring (top-bar cog, sidebar,
 * dialogs, grid items, right-click menu), the header-actions docking and
 * the created / mounted / beforeUnmount lifecycle. Only HTTP (axios), the
 * Nextcloud globals and the store actions that would hit the server are
 * mocked; child components are stubbed so their events can be emitted.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

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
	DashboardEditBar: passthrough('DashboardEditBar', {
		props: { dashboardName: String, columns: Number, canConfigure: Boolean },
	}),
	AcknowledgementReportModal: passthrough('AcknowledgementReportModal', {
		props: { open: Boolean, announcementKey: String },
	}),
	DashboardReactions: passthrough('DashboardReactions', { template: '<div class="stub-reactions" />' }),
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
		props: { open: Boolean, placementId: [Number, String], widgetTitle: String, dashboardName: String, ruleMatch: String },
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

describe('Views — the Meer menu and sidebar wiring', () => {
	it('opens the sidebar from the Meer menu\'s "All dashboards" and closes it through the sidebar and the backdrop', async () => {
		const { wrapper } = await mountViews()
		const sidebar = () => wrapper.findComponent({ name: 'DashboardSwitcherSidebar' })
		const more = () => wrapper.findComponent({ name: 'DashboardMoreMenu' })

		expect(sidebar().props('isOpen')).toBe(false)
		expect(wrapper.find('.stub-backdrop').exists()).toBe(false)

		await more().vm.$emit('openAll')
		expect(sidebar().props('isOpen')).toBe(true)
		expect(wrapper.find('.stub-backdrop').exists()).toBe(true)

		await wrapper.findComponent({ name: 'SidebarBackdrop' }).vm.$emit('close')
		expect(sidebar().props('isOpen')).toBe(false)

		await more().vm.$emit('openAll')
		await sidebar().vm.$emit('update:open', false)
		expect(sidebar().props('isOpen')).toBe(false)
		expect(wrapper.find('.stub-backdrop').exists()).toBe(false)
	}, 90000)

	it('the Meer menu\'s "Share" opens the config drawer on the sharing tab', async () => {
		const { wrapper } = await mountViews()
		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('share')
		await flushPromises()
		const config = wrapper.findComponent({ name: 'DashboardConfigModal' })
		expect(config.props('open')).toBe(true)
		expect(config.props('initialTab')).toBe('sharing')
	}, 90000)

	it('the Meer menu\'s "Edit" announces the editor and enters edit mode; the edit bar opens the settings', async () => {
		const { wrapper } = await mountViews()
		const cog = wrapper.findComponent({ name: 'DashboardMoreMenu' })

		cog.vm.$emit('toggleEdit')
		await flushPromises()
		expect(axios.post).toHaveBeenCalledWith(`${BASE}/api/dashboards/u-7/presence`)
		expect(wrapper.find('.launchpad-container').classes()).toContain('launchpad-edit-mode')

		wrapper.findComponent({ name: 'DashboardEditBar' }).vm.$emit('openSettings')
		await flushPromises()
		const config = wrapper.findComponent({ name: 'DashboardConfigModal' })
		expect(config.props('open')).toBe(true)
		expect(config.props('initialTab')).toBe('general')
	}, 90000)

	it('the Meer menu\'s "Add widget" opens the widget picker with the server apps and the catalogue', async () => {
		const discovered = [{ appId: 'files', name: 'Files', href: '/apps/files', icon: '' }]
		const catalogue = [{ uuid: 'c-1', name: 'Mail', icon: '', iconType: 'url' }]
		routeAxios({
			get: (url) => {
				if (url === `${BASE}/api/tiles/discover`) {
					return Promise.resolve({ data: { apps: discovered } })
				}
				if (url === `${BASE}/api/catalogue`) {
					return Promise.resolve({ data: { apps: catalogue } })
				}
				return Promise.resolve({ data: {} })
			},
		})
		const { wrapper } = await mountViews()
		const picker = () => wrapper.findComponent({ name: 'WidgetPickerModal' })
		expect(picker().exists()).toBe(false)

		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('addWidget')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/api/dashboards/u-7/presence`)
		expect(picker().vm.$attrs.open).toBe(true)
		expect(picker().vm.$attrs.discoveredApps).toEqual(discovered)
		expect(picker().vm.$attrs.catalogueApps).toEqual(catalogue)
		expect(wrapper.findComponent({ name: 'CnAddWidgetModal' }).props('show')).toBe(false)
	}, 90000)

	it('a Launchpad type picked in the picker opens the custom widget form on it, with no second menu entry', async () => {
		const { wrapper, dashStore } = await mountViews()
		const picker = () => wrapper.findComponent({ name: 'WidgetPickerModal' })
		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('addWidget')
		await flushPromises()

		expect(picker().vm.$attrs.customTypes).toEqual(expect.arrayContaining([expect.objectContaining({ type: 'text' })]))
		picker().vm.$emit('addCustom', 'text')
		await flushPromises()

		expect(dashStore.switchDashboard).not.toHaveBeenCalled()
		expect(picker().vm.$attrs.open).toBe(false)
		const form = wrapper.findComponent({ name: 'CnAddWidgetModal' })
		expect(form.props('show')).toBe(true)
		expect(wrapper.vm.customWidgetPreselectedType).toBeNull()
		expect(wrapper.vm.customWidgetInitialType).toBe('text')
	}, 90000)

	it('the Meer menu\'s "Set as default" toggles the pinned default dashboard over HTTP', async () => {
		const { wrapper } = await mountViews()
		const cog = wrapper.findComponent({ name: 'DashboardMoreMenu' })

		cog.vm.$emit('setDefault')
		await flushPromises()
		expect(axios.post).toHaveBeenCalledWith(
			`${BASE}/api/dashboards/default`,
			{ uuid: 'u-7' },
		)
		expect(wrapper.vm.defaultDashboardUuid).toBe('u-7')
	}, 90000)

	it('REQ-SCH-011 and REQ-SCHEDUI-001: the settings get the Publication and Exports tabs with this dashboard and its widgets', async () => {
		const { wrapper } = await mountViews({
			widgetPlacements: [{ id: 41, widgetId: 'launchpad_text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, customTitle: 'KPI' }],
		})
		const menu = wrapper.findComponent({ name: 'DashboardMoreMenu' })
		expect(menu.vm.$attrs.canScheduleExports).toBeUndefined()

		await wrapper.setData({ isConfigModalOpen: true })
		const config = wrapper.findComponent({ name: 'DashboardConfigModal' })
		expect(config.vm.$attrs.canManagePublication).toBe(true)
		expect(config.vm.$attrs.canScheduleExports).toBe(true)
		expect(config.vm.$attrs.exportSubjects).toEqual([
			{ type: 'dashboard', id: 'u-7', label: 'Home' },
			{ type: 'widget', id: '41', label: expect.any(String) },
		])
	}, 90000)

	it('the Meer menu offers no Versions: the settings carry them as a tab', async () => {
		const { wrapper } = await mountViews()
		const menu = wrapper.findComponent({ name: 'DashboardMoreMenu' })
		expect(menu.vm.$attrs.canViewHistory).toBeUndefined()
		await wrapper.setData({ isConfigModalOpen: true })
		expect(wrapper.findComponent({ name: 'DashboardConfigModal' }).vm.$attrs.canViewVersions).toBe(true)
	}, 90000)

	it('the Meer menu\'s "Copy link" puts the dashboard\'s own address on the clipboard', async () => {
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const { wrapper } = await mountViews()
		axios.get.mockImplementation((url) => Promise.resolve({ data: url.endsWith('/path') ? { path: '/home' } : {} }))
		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('copyLink')
		await flushPromises()
		expect(writeText).toHaveBeenCalledWith(expect.stringMatching(/^https?:\/\/[^/]+.*\/home$/))
	}, 90000)

	it('the Meer menu\'s "Copy link" falls back to the id address, and says so when copying fails', async () => {
		const writeText = vi.fn().mockRejectedValue(new Error('denied'))
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const { wrapper } = await mountViews()
		axios.get.mockImplementation(() => Promise.resolve({ data: {} }))
		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('copyLink')
		await flushPromises()
		expect(writeText).toHaveBeenCalledWith(expect.stringMatching(/\/dashboards\/\d+$/))
	}, 90000)

	it('the Meer menu\'s "Duplicate" forks the open dashboard', async () => {
		const { wrapper } = await mountViews()
		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('duplicate')
		await flushPromises()
		expect(axios.post).toHaveBeenCalledWith(`${BASE}/api/dashboards/u-7/fork`, expect.anything())
	}, 90000)

	it('edit mode shows the edit bar: its settings, columns and Done', async () => {
		const { wrapper } = await mountViews()
		const bar = () => wrapper.findComponent({ name: 'DashboardEditBar' })
		expect(bar().exists()).toBe(false)

		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		expect(bar().exists()).toBe(true)
		expect(bar().props('canConfigure')).toBe(true)
		expect(bar().props('columns')).toBe(12)

		await bar().vm.$emit('openSettings')
		const config = wrapper.findComponent({ name: 'DashboardConfigModal' })
		expect(config.props('open')).toBe(true)
		expect(config.props('initialTab')).toBe('general')

		await bar().vm.$emit('setColumns', 8)
		await flushPromises()
		expect(axios.put).toHaveBeenCalledWith(
			expect.stringMatching(/\/api\/dashboard\/\d+$/),
			{ gridColumns: 8 },
		)
		expect(bar().props('columns')).toBe(8)

		await bar().vm.$emit('done')
		await flushPromises()
		expect(bar().exists()).toBe(false)
	}, 90000)

	it('the Meer menu\'s "Delete" opens the delete dialog for the active dashboard', async () => {
		const { wrapper } = await mountViews()
		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('delete')

		const dialog = wrapper.findComponent({ name: 'DeleteDashboardDialog' })
		expect(dialog.props('open')).toBe(true)
		expect(dialog.props('name')).toBe('Home')
	}, 90000)
})

describe('Views — read receipts and the colleagues editing', () => {
	it('"Read receipts" opens the report for the first announcement; its close shuts it', async () => {
		const { wrapper } = await mountViews({
			widgetPlacements: [
				{ id: 1, widgetId: 'text', requiresAcknowledgement: '1', announcementKey: 'ann-a' },
				{ id: 2, widgetId: 'text', requiresAcknowledgement: 1, announcementKey: 'ann-b' },
				{ id: 3, widgetId: 'text', requiresAcknowledgement: 1, announcementKey: 'ann-a' },
				{ id: 4, widgetId: 'text', requiresAcknowledgement: 0, announcementKey: 'ann-c' },
			],
		})
		const report = () => wrapper.findComponent({ name: 'AcknowledgementReportModal' })
		expect(report().exists()).toBe(false)

		await wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('readReceipts')
		expect(report().props('open')).toBe(true)
		expect(report().props('announcementKey')).toBe('ann-a')

		await report().vm.$emit('close')
		expect(report().props('open')).toBe(false)
	}, 90000)

	it('in edit mode the colleagues editing are shown, in view mode not', async () => {
		const marieke = { displayName: 'Marieke Jansen', since: '2026-11-14T10:42:00+01:00' }
		const { wrapper } = await mountViews()
		expect(wrapper.findComponent({ name: 'CoEditorsBanner' }).exists()).toBe(false)

		axios.post.mockImplementation(() => Promise.resolve({ data: { editors: [marieke] } }))

		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		expect(wrapper.findComponent({ name: 'CoEditorsBanner' }).props('editors')).toEqual([marieke])
	}, 90000)
})

describe('Views — grid items', () => {
	const tile = {
		id: 11,
		tileType: 'custom',
		tileTitle: 'Mail',
		tileIcon: 'mdi-mail',
		tileLinkType: 'url',
		tileLinkValue: 'https://mail',
		content: { healthPingEnabled: true, pingInterval: 30 },
	}
	const widget = { id: 12, widgetId: 'nc-calendar', content: {} }

	it('a tile\'s edit opens the tile editor with its data; its remove deletes the placement', async () => {
		const { wrapper, dashStore } = await mountViews({ widgetPlacements: [tile, widget] })
		const tileStub = wrapper.findComponent({ name: 'TileWidget' })
		expect(tileStub.props('placementId')).toBe(11)

		await tileStub.vm.$emit('edit')
		await flushPromises()
		const editor = wrapper.findComponent({ name: 'TileEditor' })
		expect(editor.props('open')).toBe(true)
		expect(editor.props('tile')).toMatchObject({
			id: 11,
			title: 'Mail',
			linkValue: 'https://mail',
			healthPingEnabled: true,
			pingInterval: 30,
		})

		await tileStub.vm.$emit('remove')
		await flushPromises()
		expect(dashStore.removeWidgetFromDashboard).toHaveBeenCalledWith(11)
	}, 90000)

	it('a widget\'s remove, style and edit reach the store and the style editor', async () => {
		const { wrapper, dashStore } = await mountViews({ widgetPlacements: [tile, widget] })
		const wrapperStub = wrapper.findComponent({ name: 'WidgetWrapper' })
		expect(wrapperStub.props('placement')).toEqual(widget)

		await wrapperStub.vm.$emit('remove')
		await flushPromises()
		expect(dashStore.removeWidgetFromDashboard).toHaveBeenCalledWith(12)

		expect(wrapper.findComponent({ name: 'CnWidgetStyleEditorModal' }).exists()).toBe(false)
		await wrapperStub.vm.$emit('style')
		let editor = wrapper.findComponent({ name: 'CnWidgetStyleEditorModal' })
		expect(editor.props('show')).toBe(true)
		expect(editor.props('widget')).toEqual(widget)

		await editor.vm.$emit('close')
		await flushPromises()
		expect(wrapper.findComponent({ name: 'CnWidgetStyleEditorModal' }).exists()).toBe(false)

		// A stock Nextcloud widget has no registry form, so "edit" also
		// lands on the style editor.
		await wrapperStub.vm.$emit('edit')
		editor = wrapper.findComponent({ name: 'CnWidgetStyleEditorModal' })
		expect(editor.props('show')).toBe(true)
	}, 90000)

	// @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-dialog-is-in-the-widgets-menu-req-cvui-007
	it('"Who sees this widget?" in the widget\'s menu opens the dialog for that widget', async () => {
		const news = { ...widget, customTitle: 'News', ruleMatch: 'all' }
		const { wrapper, dashStore } = await mountViews({ widgetPlacements: [news] })
		dashStore.refreshPlacementVisibility = vi.fn()

		await wrapper.findComponent({ name: 'WidgetWrapper' }).vm.$emit('whoSees', news)
		await flushPromises()

		const modal = wrapper.findComponent({ name: 'VisibilityRulesModal' })
		expect(modal.props('open')).toBe(true)
		expect(modal.props('placementId')).toBe(news.id)
		expect(modal.props('widgetTitle')).toBe('News')
		expect(modal.props('dashboardName')).toBe('Home')
		expect(modal.props('ruleMatch')).toBe('all')

		await modal.vm.$emit('ruleMatchChanged', 'any')
		expect(dashStore.widgetPlacements[0].ruleMatch).toBe('any')
		expect(dashStore.refreshPlacementVisibility).toHaveBeenCalled()
		expect(modal.props('ruleMatch')).toBe('any')
	}, 90000)
})

describe('Views — right-click context menu', () => {
	const placement = { id: 21, widgetId: 'nc-calendar', content: {} }

	/**
	 * Mount, enter edit mode through the cog and right-click the widget.
	 *
	 * @return {Promise<{wrapper: object, dashStore: object}>} the mount.
	 */
	async function openMenu() {
		const mounted = await mountViews({ widgetPlacements: [placement] })
		mounted.wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		await mounted.wrapper
			.find('.launchpad-grid-item[data-placement-id="21"]')
			.trigger('contextmenu', { clientX: 40, clientY: 50 })
		return mounted
	}

	it('opens at the cursor only in edit mode', async () => {
		const { wrapper } = await mountViews({ widgetPlacements: [placement] })
		await wrapper.find('.launchpad-grid-item').trigger('contextmenu', { clientX: 40, clientY: 50 })
		expect(wrapper.find('.stub-ctx').exists()).toBe(false)

		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		await wrapper.find('.launchpad-grid-item').trigger('contextmenu', { clientX: 40, clientY: 50 })
		const menu = wrapper.findComponent({ name: 'WidgetContextMenu' })
		expect(menu.props('left')).toBe(40)
		expect(menu.props('top')).toBe(50)
	}, 90000)

	it('"Edit" closes the menu and opens the style editor for the widget', async () => {
		const { wrapper } = await openMenu()
		await wrapper.findComponent({ name: 'WidgetContextMenu' }).vm.$emit('edit')

		expect(wrapper.find('.stub-ctx').exists()).toBe(false)
		expect(wrapper.findComponent({ name: 'CnWidgetStyleEditorModal' }).props('widget')).toEqual(placement)
	}, 90000)

	it('"Move" opens the keyboard move panel for the widget', async () => {
		const { wrapper } = await openMenu()
		await wrapper.findComponent({ name: 'WidgetContextMenu' }).vm.$emit('move')

		const panel = wrapper.findComponent({ name: 'WidgetMovePanel' })
		expect(panel.props('open')).toBe(true)
		expect(panel.props('placement')).toEqual(placement)
		expect(wrapper.find('.stub-ctx').exists()).toBe(false)
	}, 90000)

	it('"Remove" deletes the placement through the store', async () => {
		const { wrapper, dashStore } = await openMenu()
		await wrapper.findComponent({ name: 'WidgetContextMenu' }).vm.$emit('remove')
		await flushPromises()

		expect(dashStore.removeWidgetFromDashboard).toHaveBeenCalledWith(21)
		expect(wrapper.find('.stub-ctx').exists()).toBe(false)
	}, 90000)

	it('"Visibility rules" opens the rules editor for the placement', async () => {
		const { wrapper } = await openMenu()
		await wrapper.findComponent({ name: 'WidgetContextMenu' }).vm.$emit('visibilityRules')

		const modal = wrapper.findComponent({ name: 'VisibilityRulesModal' })
		expect(modal.props('open')).toBe(true)
		expect(modal.props('placementId')).toBe(21)
	}, 90000)

	it('"Read confirmation" opens its dialog; closing it without saving drops it', async () => {
		const { wrapper, dashStore } = await openMenu()
		const menu = wrapper.findComponent({ name: 'WidgetContextMenu' })
		await menu.vm.$emit('readConfirmation')
		await menu.vm.$emit('close')
		expect(wrapper.find('.stub-ctx').exists()).toBe(false)

		const dialog = wrapper.findComponent({ name: 'ReadConfirmationDialog' })
		expect(dialog.props('placement')).toEqual(placement)

		await dialog.vm.$emit('update:open', true)
		expect(wrapper.find('.stub-read-confirmation').exists()).toBe(true)
		await dialog.vm.$emit('update:open', false)
		expect(wrapper.find('.stub-read-confirmation').exists()).toBe(false)
		expect(dashStore.updateWidgetPlacement).not.toHaveBeenCalled()
	}, 90000)
})

describe('Views — lifecycle', () => {
	it('loads the stores, the default preference and the personal layer of a dashboard it does not own', async () => {
		const { dashStore } = await mountViews({
			activeDashboard: { id: 9, uuid: 'u-9', name: 'Team', isOwner: false },
		})

		expect(dashStore.loadDashboards).toHaveBeenCalledTimes(1)
		expect(dashStore.fetchPendingAcknowledgements).toHaveBeenCalledTimes(1)
		expect(axios.get).toHaveBeenCalledWith(`${BASE}/api/dashboards/default`)
		expect(axios.get).toHaveBeenCalledWith(`${BASE}/api/dashboards/9/personal-layer`)
		expect(dashStore.recordViewEvent).toHaveBeenCalledWith('u-9')
		expect(wrapper.vm.defaultDashboardUuid).toBe('u-default')
	}, 90000)

	it('logs a failed default-preference read and keeps no default', async () => {
		const { logger } = await import('../../utils/logger.js')
		const error = vi.spyOn(logger, 'error').mockImplementation(() => {})
		const failure = new Error('down')
		routeAxios({
			get: (url) => (url === `${BASE}/api/dashboards/default`
				? Promise.reject(failure)
				: Promise.resolve({ data: {} })),
		})

		await mountViews()

		expect(error).toHaveBeenCalledWith(
			'[Views] Failed to load default-dashboard preference:',
			failure,
		)
		expect(wrapper.vm.defaultDashboardUuid).toBe('')
		error.mockRestore()
	}, 90000)

	it('`?edit=1` in the address opens the page in edit mode for an editor', async () => {
		window.history.replaceState(null, '', '/apps/launchpad?edit=1')
		await mountViews()

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/api/dashboards/u-7/presence`)
		expect(wrapper.find('.launchpad-container').classes()).toContain('launchpad-edit-mode')
	}, 90000)

	it('`?edit=1` is ignored for a view-only reader', async () => {
		window.history.replaceState(null, '', '/apps/launchpad?edit=1')
		await mountViews({ permissionLevel: 'view_only' })

		expect(axios.post).not.toHaveBeenCalled()
		expect(wrapper.find('.launchpad-container').classes()).not.toContain('launchpad-edit-mode')
	}, 90000)

	it('switching to another dashboard records a view and pushes its address', async () => {
		const { dashStore } = await mountViews()
		routeAxios({
			get: (url) => (url === `${BASE}/api/dashboards/u-8/path`
				? Promise.resolve({ data: { path: 'team/hr' } })
				: Promise.resolve({ data: {} })),
		})
		const push = vi.spyOn(window.history, 'pushState')

		dashStore.activeDashboard = { id: 8, uuid: 'u-8', name: 'HR', isOwner: true }
		await flushPromises()

		expect(dashStore.recordViewEvent).toHaveBeenCalledWith('u-8')
		expect(push).toHaveBeenCalledWith(
			{ uuid: 'u-8', source: 'launchpad-deeplink' },
			'',
			`${BASE}/team/hr`,
		)
		push.mockRestore()
	}, 90000)

	it('docks the controls into a greeting row, floats them in edit mode and docks again after', async () => {
		await mountViews({
			widgetPlacements: [{ id: 31, widgetId: 'header', ground: true, content: {} }],
			attach: true,
		})
		const controls = () => document.querySelector('[data-testid="launchpad-top-controls"]')

		expect(controls().classList.contains('launchpad-floating-controls--docked')).toBe(true)
		expect(controls().closest('.cn-header-widget--ground')).not.toBeNull()

		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		expect(controls().classList.contains('launchpad-floating-controls--docked')).toBe(false)
		expect(controls().closest('.cn-header-widget--ground')).toBeNull()
	}, 90000)

	it('docks the controls once a greeting row appears after mount', async () => {
		const { dashStore } = await mountViews({
			widgetPlacements: [{ id: 41, widgetId: 'nc-calendar', content: {} }],
			attach: true,
		})
		const controls = () => document.querySelector('[data-testid="launchpad-top-controls"]')
		// Without a greeting row the page title row holds the controls.
		expect(controls().closest('.launchpad-page-header')).not.toBeNull()

		dashStore.widgetPlacements = [
			{ id: 41, widgetId: 'nc-calendar', content: {} },
			{ id: 42, widgetId: 'header', ground: true, content: { ground: true } },
		]
		await flushPromises()
		// The MutationObserver callback runs as a microtask after the DOM change.
		await new Promise((resolve) => setTimeout(resolve, 0))
		await flushPromises()

		expect(controls().closest('.cn-header-widget--ground')).not.toBeNull()
	}, 90000)

	it('unmounting withdraws the presence and stops following the back button', async () => {
		await mountViews()
		wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
		await flushPromises()
		const removed = vi.spyOn(window, 'removeEventListener')
		const popHandler = wrapper.vm.handleHistoryPopState

		wrapper.unmount()
		wrapper = null
		await flushPromises()

		expect(axios.delete).toHaveBeenCalledWith(`${BASE}/api/dashboards/u-7/presence`, undefined)
		expect(removed).toHaveBeenCalledWith('popstate', popHandler)
		removed.mockRestore()
	}, 90000)

	it('a refused renewal (403) drops the page back to view mode', async () => {
		vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
		try {
			await mountViews()
			wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$emit('toggleEdit')
			await flushPromises()
			expect(wrapper.find('.launchpad-container').classes()).toContain('launchpad-edit-mode')

			axios.post.mockImplementation(() => Promise.reject({ response: { status: 403 } }))
			vi.advanceTimersByTime(30 * 1000)
			await flushPromises()

			expect(wrapper.findComponent({ name: 'EditLockBanner' }).props('status')).toBe('forbidden')
			expect(wrapper.find('.launchpad-container').classes()).not.toContain('launchpad-edit-mode')
		} finally {
			vi.useRealTimers()
		}
	}, 90000)
})

describe('Views — states without a full dashboard', () => {
	it('explains that personal dashboards are off when the admin disabled them', async () => {
		await mountViews({ activeDashboard: null, allowUserDashboards: false })

		expect(wrapper.find('.stub-empty').text()).toBe(
			'Personal dashboards are not enabled by your administrator',
		)
	}, 90000)

	it('offers no versions and no duplicate for a dashboard without a uuid', async () => {
		await mountViews({ activeDashboard: { id: 5, name: 'Legacy', isOwner: true } })

		await wrapper.setData({ isConfigModalOpen: true })
		expect(wrapper.findComponent({ name: 'DashboardConfigModal' }).vm.$attrs.canViewVersions).toBe(false)
		expect(wrapper.findComponent({ name: 'DashboardMoreMenu' }).vm.$attrs.canDuplicate).toBe(false)
	}, 90000)

	it('hands the Nextcloud widget catalog from the widget store to the add-widget form', async () => {
		await mountViews({ availableWidgets: [{ id: 'calendar' }, { id: 'mail' }] })

		expect(wrapper.findAll('.stub-add-widget li').map((li) => li.text())).toEqual(['calendar', 'mail'])
	}, 90000)
})

describe('Views — the reactions bar at the foot', () => {
	/**
	 * @spec openspec/changes/archive/2026-11-24-reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
	 */
	it('renders the reactions bar after the grid, under the last widget', async () => {
		const { wrapper } = await mountViews({
			widgetPlacements: [{ id: 1, widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 }],
		})
		const grid = wrapper.find('.stub-grid').element
		const bar = wrapper.find('.stub-reactions').element
		expect(grid).toBeTruthy()
		expect(bar).toBeTruthy()
		expect(grid.compareDocumentPosition(bar) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
	}, 90000)
})
