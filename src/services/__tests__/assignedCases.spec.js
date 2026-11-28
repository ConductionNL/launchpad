/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The assigned cases client: what OpenRegister answers for the viewer's
 * cases, the counts per status and the status pills
 * (assigned-cases-widget REQ-CASES-001..002).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { caseStatus, loadAssignedCases, READ_LIMIT } from '../assignedCases.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => `/index.php${path.replace(/\{(\w+)\}/g, (_m, key) => params[key])}`,
}))

const IN_BEHANDELING = { id: 's1', name: 'In behandeling', colour: 'purple' }
const WACHT = { id: 's2', name: 'Wacht op aanvrager', colour: 'orange' }

beforeEach(() => {
	axios.get.mockReset()
})

describe('loadAssignedCases', () => {
	it('asks OpenRegister for the viewer\'s cases, deadline first, with their status', async () => {
		axios.get.mockResolvedValue({ data: { results: [] } })
		await loadAssignedCases({ register: 'dossiq', schema: 'case', userId: 'pieter' })
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/openregister/api/objects/dossiq/case', {
			params: { assignee: 'pieter', '_order[deadline]': 'asc', '_extend[]': 'status', _limit: READ_LIMIT },
		})
	})

	it('leaves closed cases out and counts the open ones per status', async () => {
		axios.get.mockResolvedValue({ data: { results: [
			{ id: 'c1', title: 'Kapvergunning Molenweg 4', identifier: 'Z-2026-0412', deadline: '2026-10-05', status: IN_BEHANDELING },
			{ id: 'c2', title: 'Dakkapel Kerkstraat 12', identifier: 'Z-2026-0440', deadline: '2026-10-21', status: WACHT },
			{ id: 'c3', title: 'Evenement Stadshaven', identifier: 'Z-2026-0431', deadline: '2026-10-08', status: IN_BEHANDELING },
			{ id: 'c4', title: 'Afgerond', status: { id: 's9', name: 'Afgerond' }, isFinalStatus: true },
			{ id: '', title: 'no id' },
		] } })
		const result = await loadAssignedCases({ register: 'dossiq', schema: 'case', userId: 'pieter' })
		expect(result.state).toBe('ok')
		expect(result.total).toBe(3)
		expect(result.cases.map((record) => record.identifier)).toEqual(['Z-2026-0412', 'Z-2026-0440', 'Z-2026-0431'])
		expect(result.counts).toEqual([
			{ key: 's1', label: 'In behandeling', variant: 'purple', count: 2 },
			{ key: 's2', label: 'Wacht op aanvrager', variant: 'warning', count: 1 },
		])
	})

	it('reads a failure as an error', async () => {
		axios.get.mockRejectedValue(new Error('403'))
		expect(await loadAssignedCases({ register: 'dossiq', schema: 'case', userId: 'pieter' })).toEqual({ state: 'error', cases: [], counts: [], total: 0 })
	})
})

describe('caseStatus', () => {
	it('falls back to the public status label when the status is not extended', () => {
		expect(caseStatus({ status: 'uuid-1', statusPublicLabel: 'In behandeling' })).toEqual({ key: 'uuid-1', label: 'In behandeling', variant: 'default' })
		expect(caseStatus({ status: { id: 'x', name: 'Nieuw', colour: 'unknown' } }).variant).toBe('default')
	})
})
