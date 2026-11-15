/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'

const baseUrl = generateUrl('/apps/launchpad/api/exports')

/** How long runExportNow waits between run-status polls, in milliseconds. */
export const POLL_INTERVAL_MS = 2000

/** How many polls runExportNow makes before it gives up waiting. */
export const MAX_POLLS = 90

/**
 * Wait a number of milliseconds.
 *
 * @param {number} ms The wait.
 * @return {Promise<void>}
 */
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * Scheduled exports through the LaunchPad export API (never straight to
 * OpenRegister: the API validates, schedules and keeps channel secrets
 * encrypted and redacted).
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-17
 */
export const useExportsStore = defineStore('scheduledExports', {
	state: () => ({
		/** @type {object[]} the exports the user may see */
		exports: [],
		/** @type {Record<string, object[]>} run-rows per export id, newest first */
		runsByExport: {},
		/** @type {Record<string, boolean>} export ids with a "Run now" in progress */
		running: {},
		loading: false,
	}),

	actions: {
		/**
		 * Load the exports the user may see.
		 *
		 * @return {Promise<object[]>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		async fetchExports() {
			this.loading = true
			try {
				const { data } = await axios.get(baseUrl, { params: { limit: 200 } })
				this.exports = data.results ?? []
				return this.exports
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create or update an export; the saved export replaces the listed one.
		 *
		 * @param {object} exportData The export; with an `id` it is updated.
		 * @return {Promise<object>} The saved export.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		async saveExport(exportData) {
			const { data } = exportData.id
				? await axios.patch(`${baseUrl}/${exportData.id}`, exportData)
				: await axios.post(baseUrl, exportData)
			const index = this.exports.findIndex((e) => e.id === data.id)
			if (index === -1) {
				this.exports.push(data)
			} else {
				this.exports.splice(index, 1, data)
			}
			return data
		},

		/**
		 * Soft-delete an export.
		 *
		 * @param {string} exportId The export.
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		async deleteExport(exportId) {
			await axios.delete(`${baseUrl}/${exportId}`)
			this.exports = this.exports.filter((e) => e.id !== exportId)
		},

		/**
		 * "Run now": start a manual run and poll its run-row until it is no
		 * longer running (or the poll budget is spent). `onProgress` gets the
		 * row after every poll, for a progress toast.
		 *
		 * @param {string} exportId The export.
		 * @param {{onProgress?: function(object): void, interval?: number, maxPolls?: number}} [options] Polling options.
		 * @return {Promise<object>} The last run-row seen.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-17
		 */
		async runExportNow(exportId, { onProgress = () => {}, interval = POLL_INTERVAL_MS, maxPolls = MAX_POLLS } = {}) {
			this.running = { ...this.running, [exportId]: true }
			try {
				let { data: run } = await axios.post(`${baseUrl}/${exportId}/run`)
				onProgress(run)
				for (let poll = 0; run.status === 'running' && poll < maxPolls; poll++) {
					await wait(interval)
					run = (await axios.get(`${baseUrl}/${exportId}/runs/${run.id}`)).data
					onProgress(run)
				}
				this.runsByExport = { ...this.runsByExport, [exportId]: [run, ...(this.runsByExport[exportId] ?? []).filter((r) => r.id !== run.id)] }
				return run
			} finally {
				const rest = { ...this.running }
				delete rest[exportId]
				this.running = rest
			}
		},

		/**
		 * "Preview": render for one recipient in one format, without delivering.
		 *
		 * @param {string} exportId The export.
		 * @param {string} recipientId The recipient whose filter context applies ('' for none).
		 * @param {string} format pdf, png, csv or xlsx.
		 * @return {Promise<Blob>} The rendered file.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-18
		 */
		async previewExport(exportId, recipientId, format) {
			const { data } = await axios.post(`${baseUrl}/${exportId}/preview`, null, {
				params: { recipientId: recipientId ?? '', format },
				responseType: 'blob',
			})
			return data
		},

		/**
		 * Load an export's run history.
		 *
		 * @param {string} exportId The export.
		 * @return {Promise<object[]>} The run-rows, newest first.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
		 */
		async fetchRuns(exportId) {
			const { data } = await axios.get(`${baseUrl}/${exportId}/runs`, { params: { limit: 200 } })
			this.runsByExport = { ...this.runsByExport, [exportId]: data.results ?? [] }
			return this.runsByExport[exportId]
		},

		/**
		 * Load one run-row with every delivery; it replaces the listed row.
		 *
		 * @param {string} exportId The export.
		 * @param {string} runId The run.
		 * @return {Promise<object>} The run-row.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		async fetchRun(exportId, runId) {
			const { data } = await axios.get(`${baseUrl}/${exportId}/runs/${runId}`)
			const runs = this.runsByExport[exportId] ?? []
			this.runsByExport = { ...this.runsByExport, [exportId]: runs.map((r) => (r.id === runId ? data : r)) }
			return data
		},

		/**
		 * Retry every delivery of a run that failed for good.
		 *
		 * @param {string} exportId The export.
		 * @param {string} runId The run.
		 * @return {Promise<object>} The updated run-row.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		async retryRun(exportId, runId) {
			const { data } = await axios.post(`${baseUrl}/${exportId}/runs/${runId}/retry`)
			const runs = this.runsByExport[exportId] ?? []
			this.runsByExport = { ...this.runsByExport, [exportId]: runs.map((r) => (r.id === runId ? data : r)) }
			return data
		},
	},
})
