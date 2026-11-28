/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the scheduled-exports store (scheduled-exports
 * tasks 17-18): every call goes to the export API, "Run now" polls the run
 * until it settles, preview asks for the file, and the lists follow saves,
 * deletes and retries.
 */

import axios from '@nextcloud/axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useExportsStore } from '../scheduledExports.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const BASE = '/index.php/apps/launchpad/api/exports'

describe('scheduledExports store', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('runs now and polls the run until it is no longer running', async () => {
		axios.post.mockResolvedValueOnce({ data: { id: 'r1', status: 'running' } })
		axios.get
			.mockResolvedValueOnce({ data: { id: 'r1', status: 'running' } })
			.mockResolvedValueOnce({ data: { id: 'r1', status: 'success' } })
		const store = useExportsStore()
		const seen = []

		const run = await store.runExportNow('e1', { onProgress: (r) => seen.push(r.status), interval: 0 })

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/run`)
		expect(axios.get).toHaveBeenCalledWith(`${BASE}/e1/runs/r1`)
		expect(axios.get).toHaveBeenCalledTimes(2)
		expect(seen).toEqual(['running', 'running', 'success'])
		expect(run.status).toBe('success')
		expect(store.runsByExport.e1).toEqual([{ id: 'r1', status: 'success' }])
		expect(store.running).toEqual({})
	})

	it('stops polling after the poll budget and clears the running flag on failure', async () => {
		axios.post.mockResolvedValueOnce({ data: { id: 'r1', status: 'running' } })
		axios.get.mockResolvedValue({ data: { id: 'r1', status: 'running' } })
		const store = useExportsStore()

		const run = await store.runExportNow('e1', { interval: 0, maxPolls: 3 })
		expect(axios.get).toHaveBeenCalledTimes(3)
		expect(run.status).toBe('running')

		axios.post.mockRejectedValueOnce(new Error('404'))
		await expect(store.runExportNow('e2', { interval: 0 })).rejects.toThrow('404')
		expect(store.running).toEqual({})
	})

	it('previews one format for one recipient as a file', async () => {
		const blob = new Blob(['%PDF'])
		axios.post.mockResolvedValueOnce({ data: blob })

		const result = await useExportsStore().previewExport('e1', 'gm', 'pdf')

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/preview`, null, { params: { recipientId: 'gm', format: 'pdf' }, responseType: 'blob' })
		expect(result).toBe(blob)
	})

	it('creates with POST, updates with PATCH and deletes, keeping the list in step', async () => {
		axios.get.mockResolvedValueOnce({ data: { results: [{ id: 'e1', name: 'A' }] } })
		axios.post.mockResolvedValueOnce({ data: { id: 'e2', name: 'B' } })
		axios.patch.mockResolvedValueOnce({ data: { id: 'e1', name: 'A2' } })
		axios.delete.mockResolvedValueOnce({})
		const store = useExportsStore()

		await store.fetchExports()
		await store.saveExport({ name: 'B' })
		await store.saveExport({ id: 'e1', name: 'A2' })
		await store.deleteExport('e2')

		expect(axios.get).toHaveBeenCalledWith(BASE, { params: { limit: 200 } })
		expect(axios.post).toHaveBeenCalledWith(BASE, { name: 'B' })
		expect(axios.patch).toHaveBeenCalledWith(`${BASE}/e1`, { id: 'e1', name: 'A2' })
		expect(axios.delete).toHaveBeenCalledWith(`${BASE}/e2`)
		expect(store.exports).toEqual([{ id: 'e1', name: 'A2' }])
	})

	it('loads runs and replaces a retried run', async () => {
		axios.get.mockResolvedValueOnce({ data: { results: [{ id: 'r1', status: 'partial' }, { id: 'r0', status: 'success' }] } })
		axios.post.mockResolvedValueOnce({ data: { id: 'r1', status: 'running' } })
		const store = useExportsStore()

		await store.fetchRuns('e1')
		const retried = await store.retryRun('e1', 'r1')

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/runs/r1/retry`)
		expect(retried.status).toBe('running')
		expect(store.runsByExport.e1.map((r) => r.status)).toEqual(['running', 'success'])
	})

	it('reads one run and replaces the listed row with it', async () => {
		axios.get
			.mockResolvedValueOnce({ data: { results: [{ id: 'r1', status: 'running' }, { id: 'r0', status: 'success' }] } })
			.mockResolvedValueOnce({ data: { id: 'r1', status: 'partial', deliveries: [{ status: 'failed-permanent' }] } })
		const store = useExportsStore()

		await store.fetchRuns('e1')
		const run = await store.fetchRun('e1', 'r1')

		expect(axios.get).toHaveBeenLastCalledWith(`${BASE}/e1/runs/r1`)
		expect(run.deliveries).toHaveLength(1)
		expect(store.runsByExport.e1.map((r) => r.status)).toEqual(['partial', 'success'])
	})
})
