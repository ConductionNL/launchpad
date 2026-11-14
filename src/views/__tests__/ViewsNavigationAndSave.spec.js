/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page's deep-link history (building the address, replacing
 * it at boot, pushing it on a switch, following the back button), the
 * default-dashboard preference from a row and from the config modal, the
 * active dashboard's source, the per-dashboard view-event debounce, the
 * tile search dimming and saving a tile or custom widget. Run against the
 * real Views methods and computeds on a stand-in host.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { useTileSearchStore } from '../../stores/tileSearch.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getDashboardPath: vi.fn(),
		getDashboardByPath: vi.fn(),
		setDefaultDashboardPreference: vi.fn(),
		clearDefaultDashboardPreference: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
	generateOcsUrl: (path) => `/ocs/v2.php${path}`,
	imagePath: (app, file) => `/${app}/img/${file}`,
	generateFilePath: (app, type, file) => `/${app}/${type}/${file}`,
	linkTo: (app, file) => `/${app}/${file}`,
}))
vi.mock('../../stores/tileSearch.js', () => ({
	useTileSearchStore: vi.fn(),
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

/**
 * A host carrying the real Views methods.
 *
 * @param {object} state Data and overrides.
 * @return {object} the host.
 */
function makeHost(state = {}) {
	const host = { activeDashboard: null, defaultDashboardUuid: '', viewEventLastSent: {} }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	return Object.assign(host, state)
}

/**
 * Point the jsdom location at a path without navigating.
 *
 * @param {string} path The pathname.
 */
function at(path) {
	window.history.replaceState(null, '', path)
}

beforeEach(async () => {
	vi.clearAllMocks()
	at('/index.php/apps/launchpad')
	Views = (await import('../Views.vue')).default
})

afterEach(() => {
	vi.useRealTimers()
	vi.restoreAllMocks()
})

describe('Views deep-link history', () => {
	it('builds an address under the app prefix, adding the leading slash', () => {
		const host = makeHost()
		expect(host.buildDeepLinkUrl('team/hr')).toBe('/index.php/apps/launchpad/team/hr')
		expect(host.buildDeepLinkUrl('/team/hr')).toBe('/index.php/apps/launchpad/team/hr')
		expect(host.buildDeepLinkUrl('')).toBe('')
		expect(host.buildDeepLinkUrl(null)).toBe('')
	})

	it('replaces the boot address with the injected deep link, unless it is already there', () => {
		const replace = vi.spyOn(window.history, 'replaceState')
		const host = makeHost({ injectedDeepLinkPath: 'team/hr', activeDashboard: { uuid: 'u-hr' } })

		host.replaceUrlFromInitialState()
		expect(replace).toHaveBeenCalledWith({ uuid: 'u-hr', source: 'launchpad-deeplink' }, '', '/index.php/apps/launchpad/team/hr')

		replace.mockClear()
		at('/index.php/apps/launchpad/team/hr/')
		replace.mockClear()
		host.replaceUrlFromInitialState()
		expect(replace).not.toHaveBeenCalled()

		makeHost({ injectedDeepLinkPath: '' }).replaceUrlFromInitialState()
		expect(replace).not.toHaveBeenCalled()
	})

	it('logs instead of failing when the history refuses the replacement', () => {
		const error = new Error('refused')
		vi.spyOn(window.history, 'replaceState').mockImplementation(() => {
			throw error
		})

		makeHost({ injectedDeepLinkPath: 'x', activeDashboard: null }).replaceUrlFromInitialState()

		expect(logger.warn).toHaveBeenCalledWith('[Views] history.replaceState failed:', error)
	})

	it('pushes the active dashboard\'s path, once, and nothing without a uuid or path', async () => {
		const push = vi.spyOn(window.history, 'pushState')
		api.getDashboardPath.mockResolvedValue({ data: { path: 'team/hr' } })
		const host = makeHost({ activeDashboard: { uuid: 'u-hr' } })

		await host.pushUrlForActiveDashboard()
		expect(api.getDashboardPath).toHaveBeenCalledWith('u-hr')
		expect(push).toHaveBeenCalledWith({ uuid: 'u-hr', source: 'launchpad-deeplink' }, '', '/index.php/apps/launchpad/team/hr')

		push.mockClear()
		at('/index.php/apps/launchpad/team/hr')
		await host.pushUrlForActiveDashboard()
		expect(push).not.toHaveBeenCalled()

		api.getDashboardPath.mockResolvedValue({ data: {} })
		at('/index.php/apps/launchpad')
		await host.pushUrlForActiveDashboard()
		expect(push).not.toHaveBeenCalled()

		api.getDashboardPath.mockClear()
		await makeHost({ activeDashboard: null }).pushUrlForActiveDashboard()
		expect(api.getDashboardPath).not.toHaveBeenCalled()
	})

	it('logs a failed path lookup', async () => {
		const error = new Error('down')
		api.getDashboardPath.mockRejectedValue(error)

		await makeHost({ activeDashboard: { uuid: 'u' } }).pushUrlForActiveDashboard()

		expect(logger.warn).toHaveBeenCalledWith('[Views] failed to push URL for active dashboard:', error)
	})

	it('follows the back button to the dashboard at the address, giving back the edit lock first', async () => {
		const order = []
		api.getDashboardByPath.mockResolvedValue({ data: { dashboard: { id: 0 } } })
		const host = makeHost({ activeDashboard: { uuid: 'u-now' } })
		host.dropEditLock = vi.fn(async () => order.push('drop'))
		host.switchDashboard = vi.fn(async (id) => order.push(`switch ${id}`))
		at('/index.php/apps/launchpad/team/hr/')

		await host.handleHistoryPopState({ state: { uuid: 'u-hr' } })

		expect(api.getDashboardByPath).toHaveBeenCalledWith('team/hr')
		expect(order).toEqual(['drop', 'switch 0'])
	})

	it('ignores a pop to the active dashboard, to the bare app address, or to an unknown path', async () => {
		const host = makeHost({ activeDashboard: { uuid: 'u-hr' } })
		host.dropEditLock = vi.fn()
		host.switchDashboard = vi.fn()

		at('/index.php/apps/launchpad/team/hr')
		await host.handleHistoryPopState({ state: { uuid: 'u-hr' } })
		at('/index.php/apps/launchpad/')
		await host.handleHistoryPopState({ state: null })
		at('/elsewhere/team')
		await host.handleHistoryPopState(undefined)
		expect(api.getDashboardByPath).not.toHaveBeenCalled()

		api.getDashboardByPath.mockResolvedValue({ data: { dashboard: null } })
		at('/index.php/apps/launchpad/gone')
		await host.handleHistoryPopState({ state: {} })
		expect(api.getDashboardByPath).toHaveBeenCalledWith('gone')
		expect(host.switchDashboard).not.toHaveBeenCalled()

		const error = new Error('down')
		api.getDashboardByPath.mockRejectedValue(error)
		await host.handleHistoryPopState({ state: {} })
		expect(logger.warn).toHaveBeenCalledWith('[Views] popstate path resolution failed:', error)
	})
})

describe('Views default-dashboard preference', () => {
	it('a row sets the default, and clears it when it already is the default', async () => {
		const host = makeHost()

		await host.onRowSetDefault({ uuid: 'u1' }, 'user')
		expect(api.setDefaultDashboardPreference).toHaveBeenCalledWith('u1')
		expect(host.defaultDashboardUuid).toBe('u1')

		await host.onRowSetDefault({ uuid: 'u1' }, 'user')
		expect(api.clearDefaultDashboardPreference).toHaveBeenCalledTimes(1)
		expect(host.defaultDashboardUuid).toBe('')

		await host.onRowSetDefault({}, 'user')
		expect(api.setDefaultDashboardPreference).toHaveBeenCalledTimes(1)
	})

	it('a failing row update keeps the old default and logs', async () => {
		const error = new Error('down')
		api.setDefaultDashboardPreference.mockRejectedValueOnce(error)
		const host = makeHost({ defaultDashboardUuid: 'old' })

		await host.onRowSetDefault({ uuid: 'u1' }, 'user')

		expect(host.defaultDashboardUuid).toBe('old')
		expect(logger.error).toHaveBeenCalledWith('[Views] Failed to update default-dashboard preference:', error)
	})

	it('the modal sets the default, clears only its own default, and ignores a missing uuid', async () => {
		const host = makeHost({ defaultDashboardUuid: 'other' })

		await host.onModalSetDefault({ uuid: 'u1', isDefault: false })
		expect(api.clearDefaultDashboardPreference).not.toHaveBeenCalled()
		expect(host.defaultDashboardUuid).toBe('other')

		await host.onModalSetDefault({ uuid: 'u1', isDefault: true })
		expect(api.setDefaultDashboardPreference).toHaveBeenCalledWith('u1')
		expect(host.defaultDashboardUuid).toBe('u1')

		await host.onModalSetDefault({ uuid: 'u1', isDefault: false })
		expect(api.clearDefaultDashboardPreference).toHaveBeenCalledTimes(1)
		expect(host.defaultDashboardUuid).toBe('')

		await host.onModalSetDefault({ uuid: '', isDefault: true })
		expect(api.setDefaultDashboardPreference).toHaveBeenCalledTimes(1)

		const error = new Error('down')
		api.setDefaultDashboardPreference.mockRejectedValueOnce(error)
		await host.onModalSetDefault({ uuid: 'u2', isDefault: true })
		expect(host.defaultDashboardUuid).toBe('')
		expect(logger.error).toHaveBeenCalledWith('[Views] Failed to update default-dashboard preference from modal:', error)
	})
})

describe('Views computeds', () => {
	it('resolves the active dashboard\'s source from the sidebar buckets, else from ownership', () => {
		const source = (state) => Views.computed.activeDashboardSource.call({
			userDashboards: [{ id: 1 }],
			defaultGroupDashboards: [{ id: 2 }],
			groupSharedDashboards: [{ id: 3 }],
			sharedWithMeDashboards: [{ id: 4 }],
			...state,
		})

		expect(source({ activeDashboard: { id: 1 } })).toBe('user')
		expect(source({ activeDashboard: { id: 2 } })).toBe('default')
		expect(source({ activeDashboard: { id: 3 } })).toBe('group')
		expect(source({ activeDashboard: { id: 4 } })).toBe('shared')
		expect(source({ activeDashboard: { id: 5, isOwner: false } })).toBe('group')
		expect(source({ activeDashboard: { id: 5 } })).toBe('user')
		expect(source({ activeDashboard: null, userDashboards: undefined })).toBe('user')
	})

	it('dims tiles the search does not match, but never the search widget itself', () => {
		const isDimmed = vi.fn((id) => id === 7)
		useTileSearchStore.mockReturnValue({ isDimmed })
		const dimmed = Views.computed.isTileDimmed.call({})

		expect(dimmed({ id: 7, widgetId: 'tile-1' })).toBe(true)
		expect(dimmed({ id: 8, widgetId: 'tile-2' })).toBe(false)
		expect(dimmed({ id: 7, widgetId: 'search' })).toBe(false)
		expect(isDimmed).toHaveBeenCalledTimes(2)
	})
})

describe('Views view events', () => {
	it('records a dashboard view at most once a second per dashboard', () => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date('2026-01-01T10:00:00Z'))
		const host = makeHost({ recordViewEvent: vi.fn() })

		host.recordViewEventDebounced('a')
		host.recordViewEventDebounced('a')
		host.recordViewEventDebounced('b')
		expect(host.recordViewEvent.mock.calls).toEqual([['a'], ['b']])

		vi.advanceTimersByTime(999)
		host.recordViewEventDebounced('a')
		expect(host.recordViewEvent).toHaveBeenCalledTimes(2)

		vi.advanceTimersByTime(1)
		host.recordViewEventDebounced('a')
		expect(host.recordViewEvent.mock.calls).toEqual([['a'], ['b'], ['a']])

		host.recordViewEventDebounced('')
		expect(host.recordViewEvent).toHaveBeenCalledTimes(3)
	})
})

describe('Views saving tiles and custom widgets', () => {
	const tile = {
		title: 'Mail',
		icon: 'mail',
		iconType: 'mdi',
		backgroundColor: '#000',
		textColor: '#fff',
		linkType: 'url',
		linkValue: 'https://example.org',
	}

	it('updates the edited tile with its fields and the health-ping defaults', async () => {
		const host = makeHost({ editingTile: { id: 5 }, updateWidgetPlacement: vi.fn(), closeTileEditor: vi.fn() })

		await host.saveTile(tile)

		expect(host.updateWidgetPlacement).toHaveBeenCalledWith(5, {
			tileTitle: 'Mail',
			tileIcon: 'mail',
			tileIconType: 'mdi',
			tileBackgroundColor: '#000',
			tileTextColor: '#fff',
			tileLinkType: 'url',
			tileLinkValue: 'https://example.org',
			content: { healthPingEnabled: false, healthUrl: '', expectedStatus: 200, pingInterval: 60, internalUrl: '' },
		})
		expect(host.closeTileEditor).toHaveBeenCalledTimes(1)
	})

	it('adds a new tile, patching the health ping only when it is enabled', async () => {
		const host = makeHost({
			editingTile: null,
			addTileToDashboard: vi.fn(async () => ({ id: 9 })),
			updateWidgetPlacement: vi.fn(),
			closeTileEditor: vi.fn(),
		})

		await host.saveTile(tile)
		expect(host.addTileToDashboard).toHaveBeenCalledWith(tile)
		expect(host.updateWidgetPlacement).not.toHaveBeenCalled()

		await host.saveTile({ ...tile, healthPingEnabled: true, healthUrl: '/status', expectedStatus: 204, pingInterval: 30 })
		expect(host.updateWidgetPlacement).toHaveBeenCalledWith(9, {
			content: { healthPingEnabled: true, healthUrl: '/status', expectedStatus: 204, pingInterval: 30, internalUrl: '' },
		})
		expect(host.closeTileEditor).toHaveBeenCalledTimes(2)
	})

	it('adds a new tile with an office-network address, patching it in (launcher-tile-internal-address)', async () => {
		const host = makeHost({
			editingTile: null,
			addTileToDashboard: vi.fn(async () => ({ id: 9 })),
			updateWidgetPlacement: vi.fn(),
			closeTileEditor: vi.fn(),
		})

		await host.saveTile({ ...tile, internalUrl: 'http://zaken.intern' })
		expect(host.updateWidgetPlacement).toHaveBeenCalledWith(9, {
			content: { healthPingEnabled: false, healthUrl: '', expectedStatus: 200, pingInterval: 60, internalUrl: 'http://zaken.intern' },
		})
	})

	it('keeps the tile editor open and logs when saving fails', async () => {
		const error = new Error('down')
		const host = makeHost({ editingTile: { id: 5 }, updateWidgetPlacement: vi.fn().mockRejectedValue(error), closeTileEditor: vi.fn() })

		await host.saveTile(tile)

		expect(host.closeTileEditor).not.toHaveBeenCalled()
		expect(logger.error).toHaveBeenCalledWith('[Views] Failed to save tile:', error)
	})

	it('updates an edited custom widget\'s content, or creates one and then patches its chrome', async () => {
		const editing = makeHost({
			customWidgetEditing: { id: 3, styleConfig: {} },
			updateWidgetPlacement: vi.fn(),
			addWidgetToDashboard: vi.fn(),
			closeCustomWidgetModal: vi.fn(),
		})
		await editing.saveCustomWidget({ type: 'text', content: { text: 'Hi' } })
		expect(editing.updateWidgetPlacement).toHaveBeenCalledWith(3, expect.objectContaining({ content: { text: 'Hi' } }))
		expect(editing.addWidgetToDashboard).not.toHaveBeenCalled()
		expect(editing.closeCustomWidgetModal).toHaveBeenCalledTimes(1)

		const creating = makeHost({
			customWidgetEditing: null,
			updateWidgetPlacement: vi.fn(),
			addWidgetToDashboard: vi.fn(async () => ({ id: 11, styleConfig: {} })),
			closeCustomWidgetModal: vi.fn(),
		})
		await creating.saveCustomWidget({ type: 'text', content: { text: 'Hi' } })
		expect(creating.addWidgetToDashboard).toHaveBeenCalledWith({ type: 'text', content: { text: 'Hi' } })
		expect(creating.updateWidgetPlacement).toHaveBeenCalledWith(11, expect.any(Object))
		expect(creating.updateWidgetPlacement.mock.calls[0][1]).not.toHaveProperty('content')
		expect(creating.closeCustomWidgetModal).toHaveBeenCalledTimes(1)
	})

	it('skips the chrome patch when nothing was created, and logs a failed save', async () => {
		const host = makeHost({
			customWidgetEditing: null,
			updateWidgetPlacement: vi.fn(),
			addWidgetToDashboard: vi.fn(async () => null),
			closeCustomWidgetModal: vi.fn(),
		})
		await host.saveCustomWidget({ type: 'text', content: {} })
		expect(host.updateWidgetPlacement).not.toHaveBeenCalled()
		expect(host.closeCustomWidgetModal).toHaveBeenCalledTimes(1)

		const error = new Error('down')
		host.addWidgetToDashboard = vi.fn().mockRejectedValue(error)
		await host.saveCustomWidget({ type: 'text', content: {} })
		expect(host.closeCustomWidgetModal).toHaveBeenCalledTimes(1)
		expect(logger.error).toHaveBeenCalledWith('[Views] Failed to save custom widget:', error)
	})
})
