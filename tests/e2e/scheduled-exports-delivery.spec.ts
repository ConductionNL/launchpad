/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for scheduled exports (openspec change
 * `scheduled-exports`, tasks 29 and 30): a failing webhook that is retried
 * until it is delivered, and the retention janitor. Tasks 27 and 28 are in
 * scheduled-exports.spec.ts.
 *
 * Task 29, failure and retry. A webhook recipient points at a wrong address
 * on an https fixture served by this runner (it answers 404 there and 200 on
 * the right path), with a policy of two attempts an hour apart and no jitter.
 * A manual run fails the first attempt and schedules the next one an hour on;
 * the runner then spends the second attempt, and the delivery fails for good,
 * with a notice to the administrators. In the run detail the owner clicks
 * "Retry failed deliveries": that attempt fails too and schedules another.
 * The address is then corrected, the hour passes, the runner retries, and the
 * delivery succeeds with a payload the fixture can verify against the
 * signing secret.
 *
 * Task 30, retention. An export that keeps its files for 14 days fires twice.
 * One run is moved 15 days back, the other 13, and the janitor runs: the old
 * run loses its file and its `storagePath`, but keeps its hashes, sizes and
 * delivery outcomes; the younger run keeps everything.
 *
 * How time is advanced: the retry sweep and the janitor compare stored
 * timestamps with now, so the test writes an earlier `nextAttemptAt` or
 * `startedAt` into the run-row through the OpenRegister objects API (as the
 * administrator) and then executes the job with `occ background-job:execute
 * --force-execute` in the container named by LAUNCHPAD_E2E_CONTAINER (default
 * `nextcloud`). The stack runs no cron.
 *
 * What task 29 needs from its environment, and skips without:
 * - Nextcloud must reach this runner: LIVE_TILE_FIXTURE_HOST (default
 *   127.0.0.1; `host.docker.internal` from a container) and
 *   `allow_local_remote_servers`, as the live-tile specs need.
 * - A webhook is only sent over https, so the fixture serves TLS with a
 *   certificate made by `openssl` on this runner for the fixture host. It is
 *   imported into the instance's trusted certificates for the test and
 *   removed afterwards. The fixture host is added to the app value
 *   `export_allowed_hosts` and the previous value restored afterwards.
 *
 * Data: each test's throwaway account owns a LaunchPad dashboard with a
 * text widget in table mode holding the rows the CSV is made of, placed
 * through LaunchPad's own dashboard API.
 *
 * Accounts are throwaway accounts, removed in afterAll with their dashboards,
 * exports, run-rows and notices.
 *
 * Gate-19 @e2e traceability:
 *   @e2e scheduled-exports::webhook-retry-with-exponential-backoff
 *   @e2e scheduled-exports::dead-letter-after-max-retries-exhausted
 *   @e2e scheduled-exports::webhook-payload-is-signed
 *   @e2e scheduled-exports::artefacts-expire-audit-metadata-retained
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

import type { IncomingHttpHeaders, Server } from 'node:http'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { createServer } from 'node:https'
import { isIP } from 'node:net'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { loginAs } from './fixtures/secondary-user.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { adminContext } from './support/principals.ts'
import {
	addTableWidget,
	createExport,
	davFromStoragePath,
	executeJob,
	EXPORTS,
	inContainer,
	JANITOR_JOB,
	makeRetriesDue,
	occ,
	patchRun,
	readAppValue,
	readExport,
	readRun,
	removeExport,
	RUNNER_JOB,
	runNow,
	WEEKLY,
	writeAppValue,
} from './support/scheduledExports.ts'

const STAMP = Date.now()

const FIXTURE_HOST = process.env.LIVE_TILE_FIXTURE_HOST || '127.0.0.1'

const ROWS = [
	{ gemeenteCode: 'GM0355', gemeente: 'Utrecht', zaak: 'Bezwaar WOZ', aantal: 30 },
	{ gemeenteCode: 'GM0344', gemeente: 'Amersfoort', zaak: 'Bezwaar omgevingsvergunning', aantal: 4 },
]

const HOUR = 3600

const DAY_MS = 24 * 3600 * 1000

/** One request the webhook fixture received. */
interface HookRequest {
	path: string
	headers: IncomingHttpHeaders
	body: Buffer
}

/**
 * An ATOM timestamp, as the run-rows store them.
 *
 * @param ms Milliseconds since the epoch.
 * @return The timestamp.
 */
function atom(ms: number): string {
	return new Date(ms).toISOString().replace(/\.\d{3}Z$/, '+00:00')
}

/**
 * Seconds between two stored timestamps.
 *
 * @param from The earlier one.
 * @param to The later one.
 * @return The difference in seconds.
 */
function secondsBetween(from: string, to: string): number {
	return (Date.parse(to) - Date.parse(from)) / 1000
}

test.describe('scheduled exports: failed webhook and retry (task 29)', () => {
	const secret = `e2e-signing-${STAMP}`
	const certName = `launchpad-e2e-hook-${STAMP}.crt`
	const received: HookRequest[] = []
	let skipReason: string | null = null
	let own: OwnDashboard | undefined
	let exportId: string | null = null
	let server: Server | null = null
	let certDir: string | null = null
	let certImported = false
	let hostsBefore: string | null = null
	let hostsChanged = false
	let base = ''

	test.beforeAll(async () => {
		const local = (await occ('config:system:get', 'allow_local_remote_servers').catch(() => '')).trim()
		if (local !== 'true') {
			skipReason = 'Nextcloud refuses local addresses (allow_local_remote_servers is off), so it cannot reach the webhook fixture on this runner'
			return
		}

		certDir = mkdtempSync(join(tmpdir(), 'launchpad-e2e-hook-'))
		const key = join(certDir, 'hook.key')
		const crt = join(certDir, 'hook.crt')
		try {
			execFileSync('openssl', [
				'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2',
				'-keyout', key, '-out', crt,
				'-subj', `/CN=${FIXTURE_HOST}`,
				'-addext', `subjectAltName=${isIP(FIXTURE_HOST) ? 'IP' : 'DNS'}:${FIXTURE_HOST}`,
			], { stdio: 'ignore' })
		} catch {
			skipReason = 'openssl is not available on the test runner, so no certificate can be made for the https webhook fixture'
			return
		}

		server = createServer({ key: readFileSync(key), cert: readFileSync(crt) }, (req, res) => {
			const chunks: Buffer[] = []
			req.on('data', (chunk: Buffer) => chunks.push(chunk))
			req.on('end', () => {
				received.push({ path: req.url ?? '', headers: req.headers, body: Buffer.concat(chunks) })
				if (req.method === 'POST' && req.url === '/hook') {
					res.writeHead(200, { 'Content-Type': 'application/json', 'X-Request-Id': `lp-e2e-${received.length}` })
					res.end('{"ok":true}')
					return
				}
				res.writeHead(404, { 'Content-Type': 'text/plain' })
				res.end('not here')
			})
		})
		await new Promise<void>((resolve) => server!.listen(0, '0.0.0.0', () => resolve()))
		const address = server.address()
		const port = typeof address === 'object' && address !== null ? address.port : 0
		base = `https://${FIXTURE_HOST}:${port}`

		await inContainer(['sh', '-c', `cat > /tmp/${certName}`], readFileSync(crt, 'utf8'))
		await occ('security:certificates:import', `/tmp/${certName}`)
		certImported = true

		hostsBefore = await readAppValue('export_allowed_hosts')
		const hosts: string[] = hostsBefore === null ? [] : JSON.parse(hostsBefore)
		await writeAppValue('export_allowed_hosts', JSON.stringify([...hosts, FIXTURE_HOST]))
		hostsChanged = true

		own = await setUpOwnDashboard('e2e-schedhook', `Webhook-export ${STAMP}`)
		await addTableWidget(own.owner.api, own.id, ROWS)
	})

	test.afterAll(async () => {
		if (own) {
			await removeExport(own.owner.api, exportId)
		}
		await tearDownOwnDashboard(own)
		if (exportId !== null) {
			const admin = await adminContext()
			try {
				const res = await admin.get('/ocs/v2.php/apps/notifications/api/v2/notifications?format=json')
				const notices = res.ok() ? (await res.json()).ocs?.data ?? [] : []
				for (const notice of notices.filter((n: any) => n.object_id === exportId)) {
					await admin.delete(`/ocs/v2.php/apps/notifications/api/v2/notifications/${notice.notification_id}`)
				}
			} finally {
				await admin.dispose()
			}
		}
		if (hostsChanged) {
			await writeAppValue('export_allowed_hosts', hostsBefore)
		}
		if (certImported) {
			await occ('security:certificates:remove', certName).catch(() => '')
			await inContainer(['rm', '-f', `/tmp/${certName}`]).catch(() => '')
		}
		if (server) {
			await new Promise<void>((resolve) => server!.close(() => resolve()))
		}
		if (certDir) {
			rmSync(certDir, { recursive: true, force: true })
		}
	})

	test('@e2e scheduled-exports::webhook-retry-with-exponential-backoff @e2e scheduled-exports::dead-letter-after-max-retries-exhausted @e2e scheduled-exports::webhook-payload-is-signed a bad webhook fails, is retried from the run detail and delivered once its address is right', async ({ browser }) => {
		test.skip(skipReason !== null, skipReason ?? '')
		test.setTimeout(5 * 60_000)
		const owner = own!.owner
		const api = owner.api
		const name = `Webhook-export ${STAMP}`

		const created = await createExport(api, {
			name,
			subjectType: 'dashboard',
			subjectId: own!.uuid,
			runTimezone: 'UTC',
			recurrence: WEEKLY,
			renderTargets: [{ format: 'csv', filenamePattern: 'bezwaren.{ext}' }],
			recipients: [{
				id: 'hook',
				channel: 'webhook',
				channelConfig: { url: `${base}/wrong-hook`, signingSecret: secret },
				retryPolicy: { maxAttempts: 2, initialBackoffSeconds: HOUR, maxBackoffSeconds: HOUR, jitter: false },
			}],
		})
		exportId = String(created.id)

		// First attempt fails; the next one waits initialBackoffSeconds.
		const fired = await runNow(api, exportId)
		let run = await readRun(api, exportId, fired.id)
		let delivery = run.deliveries[0]
		expect(delivery.status, JSON.stringify(delivery)).toBe('retrying')
		expect(delivery.attemptCount).toBe(1)
		expect(delivery.error).toBe('HTTP 404')
		const wait = secondsBetween(run.startedAt, delivery.nextAttemptAt)
		// Both stamps have whole-second resolution and are taken a moment apart, so one second of slack.
		expect(wait, 'the first retry waits initialBackoffSeconds').toBeGreaterThanOrEqual(HOUR - 1)
		expect(wait).toBeLessThan(HOUR + 120)
		expect(run.status, 'a run with a retry pending is still running').toBe('running')
		expect(received.map((r) => r.path)).toEqual(['/wrong-hook'])

		// The hour passes: the runner spends the last attempt, the delivery fails for good.
		await makeRetriesDue(api, exportId, run.id)
		await executeJob(RUNNER_JOB)
		run = await readRun(api, exportId, run.id)
		delivery = run.deliveries[0]
		expect(delivery.status, JSON.stringify(delivery)).toBe('failed-permanent')
		expect(delivery.attemptCount).toBe(2)
		expect(delivery.error).toBe('HTTP 404')
		expect(run.status).toBe('failed')
		expect(received.map((r) => r.path)).toEqual(['/wrong-hook', '/wrong-hook'])

		const admin = await adminContext()
		try {
			const res = await admin.get('/ocs/v2.php/apps/notifications/api/v2/notifications?format=json')
			expect(res.status(), await res.text()).toBe(200)
			const notices = ((await res.json()).ocs?.data ?? []).filter((n: any) => n.object_id === exportId)
			expect(notices.length, 'the administrators are told the delivery failed for good').toBeGreaterThan(0)
		} finally {
			await admin.dispose()
		}

		// The owner retries from the run detail: attempted at once, failed, scheduled again.
		const { context, page } = await loginAs(browser, owner.username, owner.password)
		try {
			await page.goto('/index.php/apps/launchpad')
			const more = page.locator(MORE_MENU)
			await expect(more).toBeVisible({ timeout: 30_000 })
			await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
			await page.locator('[data-testid="cn-grouped-menu-item-scheduled-exports"]').click()
			await page.getByRole('button', { name: `Actions for ${name}` }).click()
			await page.getByRole('menuitem', { name: 'View runs' }).click()
			const history = page.getByTestId('export-run-history')
			await expect(history.getByTestId('export-run-open')).toHaveCount(1, { timeout: 15_000 })
			await history.getByTestId('export-run-open').click()
			const detail = page.getByTestId('run-detail')
			const row = detail.getByTestId('run-detail-delivery')
			await expect(row).toHaveCount(1)
			await expect(row).toContainText('Failed for good')
			await expect(row).toContainText('HTTP 404')

			const retried = page.waitForResponse((res) =>
				res.url().endsWith(`/runs/${run.id}/retry`) && res.request().method() === 'POST',
			)
			const clickedAt = Date.now()
			await detail.getByTestId('run-detail-retry').click()
			const retryRes = await retried
			expect(retryRes.status(), await retryRes.text()).toBe(200)
			const afterRetry = await retryRes.json()
			delivery = afterRetry.deliveries[0]
			expect(delivery.status, JSON.stringify(delivery)).toBe('retrying')
			expect(delivery.attemptCount, 'a manual retry starts a fresh round of attempts').toBe(1)
			expect(Date.parse(delivery.nextAttemptAt), 'the next attempt is scheduled an hour on').toBeGreaterThan(clickedAt + (HOUR - 120) * 1000)
			await expect(row).toContainText('Waiting for a retry')
			await expect(detail.getByTestId('run-detail-retry')).toBeHidden()
		} finally {
			await context.close()
		}
		expect(received.map((r) => r.path)).toEqual(['/wrong-hook', '/wrong-hook', '/wrong-hook'])

		// The address is corrected; the stored secret is kept by sending back its placeholder.
		const stored = await readExport(api, exportId)
		expect(stored.recipients[0].channelConfig.signingSecret, 'the secret is redacted on read').toBe('********')
		const patched = await api.patch(`${EXPORTS}/${exportId}`, {
			data: {
				recipients: [{
					...stored.recipients[0],
					channelConfig: { ...stored.recipients[0].channelConfig, url: `${base}/hook` },
				}],
			},
		})
		expect(patched.status(), await patched.text()).toBe(200)

		// The hour passes: the runner retries and the delivery succeeds.
		await makeRetriesDue(api, exportId, run.id)
		await executeJob(RUNNER_JOB)
		run = await readRun(api, exportId, run.id)
		delivery = run.deliveries[0]
		expect(delivery.status, JSON.stringify(delivery)).toBe('success')
		expect(delivery.attemptCount).toBe(2)
		expect(delivery.deliveredAt).toBeTruthy()
		expect(run.status).toBe('success')

		const hits = received.filter((r) => r.path === '/hook')
		expect(hits, 'the corrected address got exactly one delivery').toHaveLength(1)
		expect(delivery.providerMessageId, 'the receiver request id is recorded').toBe(`lp-e2e-${received.length}`)
		const signature = String(hits[0].headers['x-launchpad-signature'] ?? '')
		const expected = `sha256=${createHmac('sha256', secret).update(hits[0].body).digest('hex')}`
		expect(signature, 'the payload is signed with the recipient secret').toBe(expected)
		expect(hits[0].body.toString()).toContain('Utrecht')
	})
})

test.describe('scheduled exports: retention (task 30)', () => {
	let own: OwnDashboard | undefined
	let exportId: string | null = null

	test.beforeAll(async () => {
		own = await setUpOwnDashboard('e2e-schedkeep', `Archief-bron ${STAMP}`)
		await addTableWidget(own.owner.api, own.id, ROWS)
	})

	test.afterAll(async () => {
		if (own) {
			await removeExport(own.owner.api, exportId)
		}
		await tearDownOwnDashboard(own)
	})

	test('@e2e scheduled-exports::artefacts-expire-audit-metadata-retained the janitor removes files older than 14 days and keeps the run record', async () => {
		test.setTimeout(3 * 60_000)
		const api = own!.owner.api
		const created = await createExport(api, {
			name: `Archiefexport ${STAMP}`,
			subjectType: 'dashboard',
			subjectId: own!.uuid,
			runTimezone: 'UTC',
			recurrence: WEEKLY,
			renderTargets: [{ format: 'csv' }],
			recipients: [{ id: 'archief', channel: 'nextcloudFiles', channelConfig: { targetPath: '/Archief' } }],
			retention: { keepArtefactsForDays: 14, keepAuditForDays: 365 },
		})
		exportId = String(created.id)
		expect(created.retention).toEqual({ keepArtefactsForDays: 14, keepAuditForDays: 365 })

		// Fire twice now; both runs store their file.
		const runs = []
		for (let i = 0; i < 2; i++) {
			const fired = await runNow(api, exportId)
			const run = await readRun(api, exportId, fired.id)
			expect(run.status, JSON.stringify(run.deliveries)).toBe('success')
			expect(run.renderArtefacts).toHaveLength(1)
			expect(run.renderArtefacts[0].storagePath, 'the run records where its file is').toBeTruthy()
			const file = await api.get(davFromStoragePath(run.renderArtefacts[0].storagePath))
			expect(file.status(), 'the file is in the owner\'s Files').toBe(200)
			runs.push(run)
		}
		const [old, young] = runs

		// Advance time: one run is 15 days old, the other 13.
		const oldStart = atom(Date.now() - 15 * DAY_MS)
		const youngStart = atom(Date.now() - 13 * DAY_MS)
		await patchRun(old.id, { startedAt: oldStart })
		await patchRun(young.id, { startedAt: youngStart })
		await executeJob(JANITOR_JOB)

		// The 15-day-old run lost its file and its path, nothing else.
		const purged = await readRun(api, exportId, old.id)
		expect(purged.startedAt).toBe(oldStart)
		expect(purged.status).toBe(old.status)
		expect(purged.renderArtefacts).toHaveLength(1)
		expect(purged.renderArtefacts[0].storagePath ?? null, 'the path of a purged file is nulled').toBeNull()
		expect(purged.renderArtefacts[0].sha256).toBe(old.renderArtefacts[0].sha256)
		expect(purged.renderArtefacts[0].sizeBytes).toBe(old.renderArtefacts[0].sizeBytes)
		expect(purged.renderArtefacts[0].format).toBe('csv')
		expect(purged.deliveries).toEqual(old.deliveries)
		const gone = await api.get(davFromStoragePath(old.renderArtefacts[0].storagePath))
		expect(gone.status(), 'the expired file is deleted from Files').toBe(404)

		// The 13-day-old run is within the period and keeps everything.
		const kept = await readRun(api, exportId, young.id)
		expect(kept.renderArtefacts[0].storagePath).toBe(young.renderArtefacts[0].storagePath)
		const still = await api.get(davFromStoragePath(young.renderArtefacts[0].storagePath))
		expect(still.status(), 'a file inside the period stays').toBe(200)
	})
})
