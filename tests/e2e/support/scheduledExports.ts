/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the scheduled-exports specs: the export and run API, a table
 * widget on a dashboard for the render to read, the two background jobs,
 * stored run-rows moved back in time, and the files an export wrote.
 *
 * WHY TIME IS MOVED BY EDITING STORED TIMESTAMPS. The instance has no clock a
 * test can turn. The runner and the janitor compare stored timestamps with
 * "now": a delivery retries once its `nextAttemptAt` has passed, and the
 * janitor purges by the age of a run's `startedAt`. Writing an earlier value
 * into the run-row through the OpenRegister objects API and then executing the
 * job is the same situation the job meets when the time has really passed.
 *
 * WHY THE JOBS RUN THROUGH `occ`. The test stack runs no cron, and a job that
 * waits for one would never run. `background-job:execute --force-execute` runs
 * the job once, now, on the instance, by the route `support/occ.ts` resolves
 * (its container, named by LAUNCHPAD_E2E_CONTAINER, default `nextcloud`, or a
 * server root on this machine).
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { describeRun, onInstance, onInstanceWithInput } from './occ.ts'
import { adminContext, API } from './principals.ts'

/** The container of the instance under test. */
export const CONTAINER = process.env.LAUNCHPAD_E2E_CONTAINER || 'nextcloud'

/** The export API root. */
export const EXPORTS = `${API}/exports`

/** The OpenRegister objects API of the LaunchPad register. */
const OBJECTS = '/index.php/apps/openregister/api/objects/launchpad'

/** The provisioning API for LaunchPad app values. */
const APP_VALUES = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'

export const RUNNER_JOB = 'OCA\\LaunchPad\\BackgroundJob\\ScheduledExportRunner'

export const JANITOR_JOB = 'OCA\\LaunchPad\\BackgroundJob\\ScheduledExportJanitor'

/** A run-row as the export API returns it. */
export type ExportRun = Record<string, any> & {
	id: string
	status: string
	deliveries: Array<Record<string, any>>
	renderArtefacts: Array<Record<string, any>>
}

/**
 * Run a command on the instance as the web server user: in its container, or
 * in the server root of an instance served on this machine. The route is the
 * one `support/occ.ts` resolves (LAUNCHPAD_E2E_OCC, LAUNCHPAD_E2E_CONTAINER,
 * a local server root, else the container `nextcloud`).
 *
 * @param args The command and its arguments.
 * @param input Text written to the command's standard input.
 * @return Its standard output.
 * @throws Error when the command exits non-zero.
 */
export async function inContainer(args: string[], input?: string): Promise<string> {
	const result = input === undefined
		? await onInstance(...args)
		: await onInstanceWithInput(input, ...args)
	if (result.code !== 0) {
		throw new Error(`${args.join(' ')} failed: ${describeRun(result)}`)
	}
	return result.stdout
}

/**
 * Run an occ command on the instance.
 *
 * @param args The occ arguments.
 * @return Its standard output.
 */
export async function occ(...args: string[]): Promise<string> {
	return inContainer(['php', 'occ', ...args])
}

/**
 * Execute a background job once, now, whatever its interval says.
 *
 * @param jobClass The job class.
 * @return Resolves once the job has run.
 */
export async function executeJob(jobClass: string): Promise<void> {
	const listed = JSON.parse(await occ('background-job:list', `--class=${jobClass}`, '--output=json'))
	expect(listed.length, `${jobClass} is not registered as a background job`).toBeGreaterThan(0)
	await occ('background-job:execute', '--force-execute', String(listed[0].id))
}

/**
 * A LaunchPad app value, or null when it is not set.
 *
 * @param key The key.
 * @return The value.
 */
export async function readAppValue(key: string): Promise<string | null> {
	const admin = await adminContext()
	try {
		const res = await admin.get(`${APP_VALUES}/${key}?format=json`)
		expect(res.status(), await res.text()).toBe(200)
		const data = (await res.json()).ocs?.data?.data
		return typeof data === 'string' && data !== '' ? data : null
	} finally {
		await admin.dispose()
	}
}

/**
 * Set a LaunchPad app value, or delete it for null.
 *
 * @param key The key.
 * @param value The value, or null to remove it.
 * @return Resolves once it is stored.
 */
export async function writeAppValue(key: string, value: string | null): Promise<void> {
	const admin = await adminContext()
	try {
		const res = value === null
			? await admin.delete(`${APP_VALUES}/${key}?format=json`)
			: await admin.post(`${APP_VALUES}/${key}?format=json`, { form: { value } })
		expect(res.ok(), `set ${key} -> ${res.status()}`).toBe(true)
	} finally {
		await admin.dispose()
	}
}

/**
 * A weekly recurrence that never comes due during a run: Mondays at 09:00 UTC.
 */
export const WEEKLY = { type: 'weekly', daysOfWeek: ['MO'], timeOfDay: '09:00' }

/**
 * Place a text widget in table mode on a LaunchPad dashboard: a header row
 * with the keys of the first row, then one row per record. This is the
 * tabular data the export render reads from a dashboard.
 *
 * @param api The owner's context.
 * @param dashboardId The dashboard's numeric id.
 * @param rows The records.
 * @return The placement id.
 */
export async function addTableWidget(
	api: APIRequestContext,
	dashboardId: number,
	rows: Array<Record<string, string | number>>,
): Promise<number> {
	const headers = Object.keys(rows[0])
	const cell = (text: string | number): { text: string, rowSpan: number, colSpan: number } => ({ text: String(text), rowSpan: 1, colSpan: 1 })
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 6,
			gridHeight: 4,
			content: {
				text: '',
				contentMode: 'markdown',
				tableMode: true,
				tableData: {
					headerRow: true,
					columnAlignments: headers.map(() => 'left'),
					rows: [headers.map(cell), ...rows.map((row) => headers.map((h) => cell(row[h])))],
				},
			},
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

/**
 * Create a scheduled export.
 *
 * @param api The owner's context.
 * @param body The export.
 * @return The export as stored.
 */
export async function createExport(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<Record<string, any>> {
	const res = await api.post(EXPORTS, { data: body })
	expect(res.status(), await res.text()).toBe(201)
	return res.json()
}

/**
 * Read one scheduled export.
 *
 * @param api A context that may see it.
 * @param id The export.
 * @return The export.
 */
export async function readExport(api: APIRequestContext, id: string): Promise<Record<string, any>> {
	const res = await api.get(`${EXPORTS}/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Fire an export by hand ("Run now") through the API.
 *
 * @param api The owner's context.
 * @param id The export.
 * @return The run-row the fire wrote.
 */
export async function runNow(api: APIRequestContext, id: string): Promise<ExportRun> {
	const res = await api.post(`${EXPORTS}/${id}/run`, { timeout: 180_000 })
	expect(res.status(), await res.text()).toBe(201)
	return res.json()
}

/**
 * The run history of an export, newest first.
 *
 * @param api A context that may see the export.
 * @param id The export.
 * @return The run-rows.
 */
export async function runsOf(api: APIRequestContext, id: string): Promise<ExportRun[]> {
	const res = await api.get(`${EXPORTS}/${id}/runs?limit=200`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).results
}

/**
 * One run-row with every delivery.
 *
 * @param api A context that may see the export.
 * @param id The export.
 * @param runId The run.
 * @return The run-row.
 */
export async function readRun(api: APIRequestContext, id: string, runId: string): Promise<ExportRun> {
	const res = await api.get(`${EXPORTS}/${id}/runs/${runId}`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Write fields of a stored run-row directly, as the administrator: how a test
 * moves a run or a delivery back in time.
 *
 * @param runId The run.
 * @param fields The fields to write.
 * @return Resolves once they are stored.
 */
export async function patchRun(runId: string, fields: Record<string, unknown>): Promise<void> {
	const admin = await adminContext()
	try {
		const res = await admin.patch(`${OBJECTS}/scheduled_export_run/${runId}`, { data: fields })
		expect(res.status(), await res.text()).toBe(200)
	} finally {
		await admin.dispose()
	}
}

/**
 * Make every delivery of a run that waits for a retry due now, by moving its
 * `nextAttemptAt` a minute into the past.
 *
 * @param api A context that may see the export.
 * @param id The export.
 * @param runId The run.
 * @return Resolves once the run-row is stored.
 */
export async function makeRetriesDue(api: APIRequestContext, id: string, runId: string): Promise<void> {
	const current = await readRun(api, id, runId)
	const past = new Date(Date.now() - 60_000).toISOString().replace(/\.\d{3}Z$/, '+00:00')
	const deliveries = current.deliveries.map((delivery) =>
		delivery.status === 'retrying' ? { ...delivery, nextAttemptAt: past } : delivery,
	)
	expect(deliveries.some((d) => d.status === 'retrying'), 'no delivery of the run waits for a retry').toBe(true)
	await patchRun(runId, { deliveries })
}

/**
 * Remove an export and the run-rows it left, so the shared instance keeps nothing.
 *
 * @param api The owner's context.
 * @param id The export, or null when none was made.
 * @return Resolves once both are gone.
 */
export async function removeExport(api: APIRequestContext, id: string | null): Promise<void> {
	if (id === null) {
		return
	}
	const runs = await api.get(`${EXPORTS}/${id}/runs?limit=200`)
	const rows: ExportRun[] = runs.ok() ? (await runs.json()).results : []
	await api.delete(`${EXPORTS}/${id}`)
	const admin = await adminContext()
	try {
		for (const row of rows) {
			await admin.delete(`${OBJECTS}/scheduled_export_run/${row.id}`)
		}
	} finally {
		await admin.dispose()
	}
}

/**
 * The WebDAV address of a path in a user's Files.
 *
 * @param username The user.
 * @param path The path below their Files root.
 * @return The address.
 */
export function davPath(username: string, path: string): string {
	const parts = path.replace(/^\/+/, '').split('/').filter((p) => p !== '').map(encodeURIComponent)
	return `/remote.php/dav/files/${encodeURIComponent(username)}/${parts.join('/')}`
}

/**
 * The WebDAV address of a file the export wrote, from its node path
 * (`/{user}/files/...`) as a run-row records it.
 *
 * @param storagePath The node path.
 * @return The address.
 */
export function davFromStoragePath(storagePath: string): string {
	const match = /^\/([^/]+)\/files\/(.+)$/.exec(storagePath)
	expect(match, `not a Files node path: ${storagePath}`).not.toBeNull()
	return davPath(decodeURIComponent(match![1]), match![2])
}

/**
 * The names of the files directly in a folder of a user's Files.
 *
 * @param api The user's context.
 * @param username The user.
 * @param folder The folder below their Files root.
 * @return The file names; empty when the folder does not exist.
 */
export async function filesIn(api: APIRequestContext, username: string, folder: string): Promise<string[]> {
	const res = await api.fetch(davPath(username, folder), {
		method: 'PROPFIND',
		headers: { Depth: '1', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
	})
	if (res.status() === 404) {
		return []
	}
	expect(res.status(), await res.text()).toBe(207)
	const hrefs = [...(await res.text()).matchAll(/<d:href>([^<]*)<\/d:href>/g)].map((m) => m[1])
	return hrefs
		.filter((href) => !href.endsWith('/'))
		.map((href) => decodeURIComponent(href.split('/').pop() ?? ''))
}
