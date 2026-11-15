/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A list that asked to be hidden when its register is not on this instance
 * is left out of the grid outside edit mode, after OpenRegister answered
 * 404 for its source; every other failure keeps the list on the page
 * (admin-templates REQ-TMPL-022). Asked the way the page asks: through the
 * page's own watcher, computed and probe, with the probe's HTTP call the
 * only thing replaced.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path, vars) => path.replace(/\{(\w+)\}/g, (_m, k) => vars[k]),
}))
const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: { get } }))

let Views
let resetSourceAvailability

/**
 * @param {object[]} placements The dashboard's placements.
 * @param {boolean} isEditMode Whether the page is in edit mode.
 * @return {object} host with the real Views methods and computed.
 */
function makeHost(placements, isEditMode = false) {
	const host = { widgetPlacements: placements, isEditMode, unavailableSources: {} }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	Object.defineProperty(host, 'shownPlacements', {
		get: () => Views.computed.shownPlacements.call(host),
	})
	return host
}

const header = { id: 1, widgetId: 'header', content: { title: 'Mijn werkdag' } }
const cases = {
	id: 2,
	widgetId: 'object-list',
	content: { register: 'dossiq', schema: 'case', hideWhenUnavailable: true },
}
const tickets = {
	id: 3,
	widgetId: 'object-list',
	content: { register: 'pipelinq', schema: 'ticket', hideWhenUnavailable: true },
}
const plainList = {
	id: 4,
	widgetId: 'object-list',
	content: { register: 'pipelinq', schema: 'lead' },
}

function answer(register) {
	if (register === 'dossiq') {
		return Promise.resolve({ data: { results: [], total: 0 } })
	}
	if (register === 'pipelinq') {
		return Promise.reject({ response: { status: 404 } })
	}
	return Promise.reject({ response: { status: 500 } })
}

beforeEach(async () => {
	vi.clearAllMocks()
	get.mockImplementation((url) => answer(url.split('/')[5]))
	;({ resetSourceAvailability } =
		await import('../../services/sourceAvailability.js'))
	resetSourceAvailability()
	Views = (await import('../Views.vue')).default
})

describe('Views hides a list whose register is not here', () => {
	it('leaves out the list OpenRegister answered 404 for, and keeps the others', async () => {
		const host = makeHost([header, cases, tickets, plainList])
		expect(host.shownPlacements.map((p) => p.id)).toEqual([1, 2, 3, 4])

		await host.probeHideableSources()

		expect(host.shownPlacements.map((p) => p.id)).toEqual([1, 2, 4])
		// The list without the flag was not asked about: it renders as before.
		expect(get).toHaveBeenCalledTimes(2)
		expect(get).toHaveBeenCalledWith(
			'/apps/openregister/api/objects/pipelinq/ticket',
			{ params: { _limit: 1 } },
		)
	})

	it('keeps every list in edit mode', async () => {
		const host = makeHost([header, cases, tickets], true)
		await host.probeHideableSources()
		expect(host.shownPlacements.map((p) => p.id)).toEqual([1, 2, 3])
	})

	it('keeps a list whose source could not be asked', async () => {
		const broken = {
			id: 5,
			widgetId: 'object-list',
			content: {
				register: 'learniq',
				schema: 'pupil',
				hideWhenUnavailable: true,
			},
		}
		const host = makeHost([header, broken])
		await host.probeHideableSources()
		expect(host.shownPlacements.map((p) => p.id)).toEqual([1, 5])
	})

	it('asks once per source, not once per list', async () => {
		const second = { ...tickets, id: 6 }
		const host = makeHost([tickets, second])
		await host.probeHideableSources()
		await host.probeHideableSources()
		expect(get).toHaveBeenCalledTimes(1)
		expect(host.shownPlacements).toEqual([])
	})
})

describe('Views hides an app tile whose register is not here', () => {
	it('leaves out a stat that names its source under `source`, and keeps one whose source is here', async () => {
		// Mijn werkdag 4: one number per app, each reading its own register.
		const caseTile = {
			id: 5,
			widgetId: 'stat',
			content: {
				source: { register: 'dossiq', schema: 'case', metric: 'count' },
				hideWhenUnavailable: true,
			},
		}
		const ticketTile = {
			id: 6,
			widgetId: 'stat',
			content: {
				source: { register: 'pipelinq', schema: 'ticket', metric: 'count' },
				hideWhenUnavailable: true,
			},
		}
		const plainTile = {
			id: 7,
			widgetId: 'stat',
			content: {
				source: { register: 'pipelinq', schema: 'lead', metric: 'count' },
			},
		}
		const host = makeHost([header, caseTile, ticketTile, plainTile])

		await host.probeHideableSources()

		expect(host.shownPlacements.map((p) => p.id)).toEqual([1, 5, 7])
		expect(get).toHaveBeenCalledTimes(2)
	})
})
