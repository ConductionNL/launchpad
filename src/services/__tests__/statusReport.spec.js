/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The status report client (dashboards-and-who-may-see-them REQ-DWMS-004).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const post = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { post: (...args) => post(...args) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import { draftStatusReport, keepStatusReport, subjectOf } from '../statusReport.js'

beforeEach(() => {
	post.mockReset()
})

describe('subjectOf', () => {
	it('keeps the filled, trimmed keys and splits closed values written with commas', () => {
		expect(subjectOf({ register: ' dossiq ', schema: 'case', title: '', deadline: '2026-12-31', closedValues: 'done, closed ,', other: 'x' }))
			.toEqual({ register: 'dossiq', schema: 'case', deadline: '2026-12-31', closedValues: ['done', 'closed'] })
	})

	it('takes closed values stored as a list and leaves out an empty one', () => {
		expect(subjectOf({ register: 'r', schema: 's', closedValues: ['done', ' '] })).toEqual({ register: 'r', schema: 's', closedValues: ['done'] })
		expect(subjectOf({ register: 'r', schema: 's', closedValues: [] })).toEqual({ register: 'r', schema: 's' })
		expect(subjectOf(undefined)).toEqual({})
	})
})

describe('draftStatusReport and keepStatusReport', () => {
	it('posts the subject for a draft', async () => {
		post.mockResolvedValue({ data: { health: 'on-track' } })

		await expect(draftStatusReport({ register: 'r', schema: 's' })).resolves.toEqual({ refused: false, report: { health: 'on-track' } })
		expect(post).toHaveBeenCalledWith('/index.php/apps/launchpad/api/status-reports/draft', { register: 'r', schema: 's' })
	})

	it('posts the subject with the rating and summary to keep', async () => {
		post.mockResolvedValue({ data: { id: 'k' } })

		await expect(keepStatusReport({ register: 'r', schema: 's' }, 'at-risk', 'Text')).resolves.toEqual({ refused: false, report: { id: 'k' } })
		expect(post).toHaveBeenCalledWith('/index.php/apps/launchpad/api/status-reports', { register: 'r', schema: 's', health: 'at-risk', summary: 'Text' })
	})

	it('answers an HTTP refusal with its status', async () => {
		post.mockRejectedValue({ response: { status: 503, data: { error: 'x' } } })

		await expect(keepStatusReport({}, 'on-track', 'x')).resolves.toEqual({ refused: true, status: 503 })
	})

	it('throws a failure that is not an HTTP answer', async () => {
		const failure = new Error('network')
		post.mockRejectedValue(failure)

		await expect(draftStatusReport({})).rejects.toBe(failure)
	})
})
