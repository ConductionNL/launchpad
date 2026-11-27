/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace page asks while it loads: the detail fields wait for
 * the switcher, the boot view-event waits for the page to settle (or for the
 * page to be left), and the acknowledgements and the default-dashboard
 * preference go out together with the first loads. Run against the real
 * Views options on a stand-in host; only HTTP, stores and the settle timer
 * are doubled.
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { LazyMountSupport, SETTLE_DELAY_MS } from '../../components/LazyMount.vue'
import { api } from '../../services/api.js'
import { useDashboardStore } from '../../stores/dashboard.js'
import { useTileStore } from '../../stores/tiles.js'
import { useWidgetStore } from '../../stores/widgets.js'
import { takeBootRead } from '../../utils/loadInitialState.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getMetadataFieldDefinitions: vi.fn(),
		getDefaultDashboardPreference: vi.fn(),
		recordDashboardViewEvent: vi.fn(),
	},
}))
vi.mock('../../utils/loadInitialState.js', async (importOriginal) => ({
	...(await importOriginal()),
	takeBootRead: vi.fn(),
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
	const host = {
		detailFields: [],
		detailFieldsRequested: false,
		defaultDashboardUuid: '',
		viewEventLastSent: Object.create(null),
		historyBooted: false,
		canEdit: false,
		grid: {},
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.recordViewEvent = vi.fn()
	return Object.assign(host, state)
}

beforeEach(async () => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
	// No Web Locks in this host: recordViewEventDebounced sends directly.
	vi.stubGlobal('navigator', {})
})

afterEach(() => {
	vi.unstubAllGlobals()
	vi.useRealTimers()
	vi.restoreAllMocks()
})

describe('Views boot view-event', () => {
	it('is recorded once the page has settled, not while it loads', () => {
		vi.useFakeTimers()
		vi.stubGlobal('requestIdleCallback', undefined)
		const host = makeHost()

		Views.watch['activeDashboard.uuid'].handler.call(Object.assign(host, {
			openFilterSession: vi.fn(),
			pushUrlForActiveDashboard: vi.fn(),
		}), 'd1')
		expect(host.recordViewEvent).not.toHaveBeenCalled()

		vi.advanceTimersByTime(SETTLE_DELAY_MS)
		expect(host.recordViewEvent).toHaveBeenCalledTimes(1)
		expect(host.recordViewEvent).toHaveBeenCalledWith('d1')
	})

	it('is sent at once when the page is left before it settled', () => {
		const cancel = vi.fn()
		vi.spyOn(LazyMountSupport, 'whenSettled').mockReturnValue(cancel)
		const host = makeHost({
			presence: { stop: vi.fn() },
			grid: { detach: vi.fn() },
			filterSession: null,
			headerActionsObserver: null,
		})
		host.recordViewEventAfterLoad('d1')
		expect(host.recordViewEvent).not.toHaveBeenCalled()

		Views.beforeUnmount.call(host)

		expect(cancel).toHaveBeenCalled()
		expect(host.recordViewEvent).toHaveBeenCalledWith('d1')
	})

	it('a switch after the boot is recorded at once', () => {
		const settle = vi.spyOn(LazyMountSupport, 'whenSettled')
		const host = makeHost({ historyBooted: true, openFilterSession: vi.fn(), pushUrlForActiveDashboard: vi.fn() })

		Views.watch['activeDashboard.uuid'].handler.call(host, 'd2')

		expect(settle).not.toHaveBeenCalled()
		expect(host.recordViewEvent).toHaveBeenCalledWith('d2')
	})
})

describe('Views first loads', () => {
	it('asks for the acknowledgements and the default dashboard before the dashboards have loaded', async () => {
		let finishTiles
		const dashboardStore = useDashboardStore()
		vi.spyOn(dashboardStore, 'loadDashboards').mockResolvedValue()
		vi.spyOn(dashboardStore, 'fetchPendingAcknowledgements').mockResolvedValue()
		vi.spyOn(useWidgetStore(), 'loadAvailableWidgets').mockResolvedValue()
		vi.spyOn(useTileStore(), 'loadTiles').mockReturnValue(new Promise((resolve) => { finishTiles = resolve }))
		api.getDefaultDashboardPreference.mockResolvedValue({ data: { uuid: 'd9' } })
		const host = makeHost({
			openDeepLinkedDashboard: vi.fn(),
			openHashLinkedDashboard: vi.fn(),
			replaceUrlAfterBoot: vi.fn(),
		})

		const booting = Views.created.call(host)
		expect(dashboardStore.fetchPendingAcknowledgements).toHaveBeenCalledTimes(1)
		expect(api.getDefaultDashboardPreference).toHaveBeenCalledTimes(1)

		finishTiles()
		await booting
		expect(host.defaultDashboardUuid).toBe('d9')
		expect(host.historyBooted).toBe(true)
	})
})

describe('Views boot view-event, two dashboards during the boot', () => {
	it('records only the dashboard the boot ends on', () => {
		const cancelFirst = vi.fn()
		const sends = []
		vi.spyOn(LazyMountSupport, 'whenSettled').mockImplementation((send) => {
			sends.push(send)
			return sends.length === 1 ? cancelFirst : vi.fn()
		})
		const host = makeHost()

		host.recordViewEventAfterLoad('stored')
		host.recordViewEventAfterLoad('deep-linked')
		sends[1]()

		expect(cancelFirst).toHaveBeenCalled()
		expect(host.recordViewEvent.mock.calls).toEqual([['deep-linked']])
	})
})

describe('Views default dashboard', () => {
	it('takes the pin the page carries, without asking the API', async () => {
		takeBootRead.mockReturnValue({ uuid: 'd7' })
		const host = makeHost()

		await host.loadDefaultDashboardPreference()

		expect(takeBootRead).toHaveBeenCalledWith('defaultDashboard')
		expect(host.defaultDashboardUuid).toBe('d7')
		expect(api.getDefaultDashboardPreference).not.toHaveBeenCalled()
	})

	it('asks the API when the page carries no pin', async () => {
		takeBootRead.mockReturnValue(undefined)
		api.getDefaultDashboardPreference.mockResolvedValue({ data: { uuid: 'd8' } })
		const host = makeHost()

		await host.loadDefaultDashboardPreference()

		expect(host.defaultDashboardUuid).toBe('d8')
	})
})
