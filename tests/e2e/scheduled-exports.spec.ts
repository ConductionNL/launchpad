/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for scheduled exports (openspec change
 * `scheduled-exports`, tasks 27 and 28). The retry and retention tasks (29,
 * 30) are in scheduled-exports-delivery.spec.ts.
 *
 * Task 27, the full workflow, is driven through the UI the way an author
 * works: More → Settings → Exports, a new weekly export, "Run now", the run
 * in the history, a PDF preview, an edit, and switching the schedule off. The
 * last step then lets the edited fire time pass and executes the background
 * runner: the switched-off export must not fire, while a control export due
 * at the same moment does, so a runner that fired nothing cannot pass it.
 *
 * Task 28, the filter context, sets up through the API: one export, two
 * recipients (gemeente A and gemeente B) with distinct filter contexts, each
 * delivering into its own folder of the owner's Files. One manual run must
 * render twice and each folder must hold only its gemeente's rows.
 *
 * Time: the runner is executed with `occ background-job:execute
 * --force-execute` in the container named by LAUNCHPAD_E2E_CONTAINER (default
 * `nextcloud`); the stack runs no cron. Task 27 waits for a fire time a few
 * minutes ahead instead of moving it, because `nextRunAt` belongs to the
 * runner and cannot be written from outside.
 *
 * PDF: a PDF render needs the headless browser an operator configures as the
 * app value `export_chromium_binary` (design.md, "Hosts and the headless
 * browser are operator settings"). Without it the preview must refuse with
 * that reason; with it, it must show the PDF. Which branch ran is recorded as
 * an annotation on the test.
 *
 * Data: each test's throwaway account owns a LaunchPad dashboard with a
 * text widget in table mode holding the rows the CSV is made of, placed
 * through LaunchPad's own dashboard API.
 *
 * Accounts are throwaway accounts, removed in afterAll with their dashboards,
 * exports and run-rows.
 *
 * Gate-19 @e2e traceability:
 *   @e2e scheduled-exports::manual-run-with-user-tracking
 *   @e2e scheduled-exports::background-job-fires-and-updates-timestamps
 *   @e2e scheduled-exports::two-recipients-with-distinct-filter-contexts-produce-two-renders
 *   @e2e scheduled-exports::preview-without-dispatch
 *   @e2e scheduled-exports::the-exports-tab-lists-this-dashboards-exports
 *   @e2e scheduled-exports::the-meer-menu-no-longer-offers-scheduled-exports
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

import type { Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { MORE_MENU, openDashboardSettings } from './support/dashboardSwitcher.ts'
import { setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { API } from './support/principals.ts'
import {
	addTableWidget,
	createExport,
	davPath,
	executeJob,
	EXPORTS,
	filesIn,
	readAppValue,
	readExport,
	readRun,
	removeExport,
	RUNNER_JOB,
	runNow,
	runsOf,
	WEEKLY,
} from './support/scheduledExports.ts'

const STAMP = Date.now()

const DAY_CODES = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']

const DAY_LABELS: Record<string, string> = {
	MO: 'Monday',
	TU: 'Tuesday',
	WE: 'Wednesday',
	TH: 'Thursday',
	FR: 'Friday',
	SA: 'Saturday',
	SU: 'Sunday',
}

const WEEK_MS = 7 * 24 * 3600 * 1000

/** A weekly fire slot: its moment, its day code and its time, in UTC. */
interface Slot {
	at: number
	code: string
	time: string
}

/**
 * The first whole minute at least `aheadMs` from now, as a weekly slot in UTC.
 *
 * @param aheadMs How far ahead at least.
 * @return The slot.
 */
function slotAhead(aheadMs: number): Slot {
	const at = new Date(Date.now() + aheadMs)
	at.setUTCSeconds(0, 0)
	at.setUTCMinutes(at.getUTCMinutes() + 1)
	const pad = (n: number): string => String(n).padStart(2, '0')
	return {
		at: at.getTime(),
		code: DAY_CODES[at.getUTCDay()],
		time: `${pad(at.getUTCHours())}:${pad(at.getUTCMinutes())}`,
	}
}

/**
 * Open the scheduled exports list from the start page's More menu.
 *
 * @param page The page, signed in as someone who may edit the active dashboard.
 * @return The list.
 */
async function openExports(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await openDashboardSettings(page)
	await page.locator('[data-test="config-tab-exports"]').click()
	const list = page.getByTestId('scheduled-exports')
	await expect(list).toBeVisible({ timeout: 15_000 })
	return list
}

/**
 * Choose a row action of an export in the list.
 *
 * @param page The page.
 * @param name The export's name.
 * @param action The action's label.
 * @return Resolves once the action is clicked.
 */
async function rowAction(page: Page, name: string, action: string): Promise<void> {
	await page.getByRole('button', { name: `Actions for ${name}` }).click()
	await page.getByRole('menuitem', { name: action }).click()
}

/**
 * Choose an option of a select.
 *
 * @param page The page (the options render in the body).
 * @param scope Where the select is.
 * @param label The select's label.
 * @param option The option's label.
 * @return Resolves once it is chosen.
 */
async function choose(page: Page, scope: Locator, label: string, option: string): Promise<void> {
	const combobox = scope.getByRole('combobox', { name: label })
	// The dialog's focus trap can take focus back to its first field just
	// after it opens, and the typing below would then land in Name. Type only
	// once the select itself holds focus.
	await expect(async () => {
		await combobox.click()
		await expect(combobox).toBeFocused({ timeout: 1_000 })
	}).toPass({ timeout: 10_000 })
	// Typing narrows the list to the one option, which Enter then picks; clicking
	// the option by role did not resolve for every select of this editor.
	await page.keyboard.type(option)
	await page.keyboard.press('Enter')
	await expect(combobox.locator('xpath=ancestor::*[contains(@class, "v-select")][1]')).toContainText(option)
}

/**
 * Close the dialog that holds an element.
 *
 * @param page The page.
 * @param inner An element inside the dialog.
 * @return Resolves once it is gone.
 */
async function closeDialogOf(page: Page, inner: Locator): Promise<void> {
	// The innermost dialog that holds the element: the export list is a dialog too.
	await page.getByRole('dialog').filter({ has: inner }).last().getByRole('button', { name: 'Close' }).first().click()
	await expect(inner).toBeHidden()
}

/**
 * Save the export editor and return the server's answer.
 *
 * @param page The page.
 * @param editor The editor.
 * @param method POST for a new export, PATCH for an edit.
 * @return The saved export.
 */
async function saveEditor(page: Page, editor: Locator, method: 'POST' | 'PATCH'): Promise<Record<string, any>> {
	const saved = page.waitForResponse((res) =>
		res.url().includes('/api/exports') && res.request().method() === method,
	)
	await editor.getByRole('button', { name: 'Save' }).click()
	const res = await saved
	expect(res.status(), await res.text()).toBe(method === 'POST' ? 201 : 200)
	await expect(editor).toBeHidden()
	return res.json()
}

test.describe('scheduled exports: full workflow (task 27)', () => {
	let own: OwnDashboard | undefined
	let exportId: string | null = null
	let controlId: string | null = null

	test.beforeAll(async () => {
		own = await setUpOwnDashboard('e2e-sched', `Wekelijks overzicht ${STAMP}`)
		await addTableWidget(own.owner.api, own.id, [
			{ gemeenteCode: 'GM0355', gemeente: 'Utrecht', zaak: 'Bezwaar WOZ', aantal: 30 },
			{ gemeenteCode: 'GM0344', gemeente: 'Amersfoort', zaak: 'Bezwaar omgevingsvergunning', aantal: 4 },
		])
	})

	test.afterAll(async () => {
		if (own) {
			await removeExport(own.owner.api, exportId)
			await removeExport(own.owner.api, controlId)
		}
		await tearDownOwnDashboard(own)
	})

	test('@e2e scheduled-exports::manual-run-with-user-tracking @e2e scheduled-exports::background-job-fires-and-updates-timestamps create, run now, history, preview, edit, switch off, no next fire', async ({ browser }) => {
		test.setTimeout(10 * 60_000)
		const owner = own!.owner
		const name = `Weekrapport ${STAMP}`
		const edited = `${name} bewerkt`
		const chromium = await readAppValue('export_chromium_binary')
		const { context, page } = await loginAs(browser, owner.username, owner.password)
		try {
			// Create: a weekly export of the active dashboard, a CSV into Files.
			const list = await openExports(page)
			await list.getByTestId('scheduled-exports-new').click()
			const editor = page.getByTestId('export-editor')
			await expect(editor).toBeVisible()
			await editor.getByLabel('Name', { exact: true }).fill(name)
			await expect(editor.getByTestId('export-recurrence-summary')).toHaveText('Runs every Monday at 09:00.')
			await editor.getByLabel('Time zone', { exact: true }).fill('UTC')
			await choose(page, editor, 'Format', 'CSV table')
			await choose(page, editor, 'Send by', 'Nextcloud Files')
			await editor.getByLabel('Folder', { exact: true }).fill('/Exports e2e')
			const created = await saveEditor(page, editor, 'POST')
			exportId = String(created.id)
			expect(created.subjectType).toBe('dashboard')
			expect(created.subjectId).toBe(own!.uuid)
			expect(created.recurrence).toEqual({ type: 'weekly', daysOfWeek: ['MO'], timeOfDay: '09:00' })
			expect(created.enabled).toBe(true)
			expect(created.nextRunAt, 'a new export is scheduled').toBeTruthy()
			await expect(list.getByTestId('scheduled-export-name').filter({ hasText: name })).toBeVisible()

			// Run now: a manual run-row by this user; the schedule does not move.
			const scheduled = (await readExport(owner.api, exportId)).nextRunAt
			const ran = page.waitForResponse((res) =>
				res.url().endsWith(`/api/exports/${exportId}/run`) && res.request().method() === 'POST',
			)
			await rowAction(page, name, 'Run now')
			const runRes = await ran
			expect(runRes.status(), await runRes.text()).toBe(201)
			const run = await runRes.json()
			expect(run.triggeredBy).toBe('manual')
			expect(run.triggeredByUser).toBe(owner.username)
			await expect
				.poll(async () => (await readRun(owner.api, exportId!, run.id)).status, { timeout: 60_000 })
				.not.toBe('running')
			const settled = await readRun(owner.api, exportId, run.id)
			expect(settled.status, JSON.stringify(settled.deliveries)).toBe('success')
			expect(settled.renderArtefacts).toHaveLength(1)
			expect(settled.renderArtefacts[0].format).toBe('csv')
			const delivered = await filesIn(owner.api, owner.username, '/Exports e2e')
			expect(delivered, 'the CSV is delivered into the chosen folder').toHaveLength(1)
			const csv = await (await owner.api.get(davPath(owner.username, `/Exports e2e/${delivered[0]}`))).text()
			expect(csv).toContain('gemeenteCode')
			expect(csv).toContain('Utrecht')
			const afterRun = await readExport(owner.api, exportId)
			expect(afterRun.nextRunAt, 'a manual run leaves the schedule alone').toBe(scheduled)
			expect(afterRun.lastRunAt).toBeTruthy()
			expect(afterRun.lastRunStatus).toBe('success')

			// History: the run is listed and opens its detail.
			await rowAction(page, name, 'View runs')
			const history = page.getByTestId('export-run-history')
			await expect(history.getByTestId('export-run-open')).toHaveCount(1, { timeout: 15_000 })
			await expect(history).toContainText('By hand')
			await history.getByTestId('export-run-open').click()
			const detail = page.getByTestId('run-detail')
			await expect(detail).toBeVisible()
			await expect(detail.getByTestId('run-detail-export')).toHaveText(name)
			await expect(detail).toContainText(`By hand by ${owner.username}`)
			await expect(detail.getByTestId('run-detail-delivery')).toContainText('Succeeded')
			await expect(detail.getByTestId('run-detail-download')).toHaveCount(1)
			await closeDialogOf(page, detail)
			await history.getByTestId('export-runs-back').click()

			// Preview as PDF: rendered without a delivery and without a run-row.
			await rowAction(page, name, 'Preview')
			const preview = page.getByTestId('export-preview')
			await expect(preview).toBeVisible()
			await choose(page, preview, 'Format', 'PDF document')
			const rendered = page.waitForResponse((res) => res.url().includes(`/api/exports/${exportId}/preview`))
			await preview.getByTestId('preview-render').click()
			const previewRes = await rendered
			if (chromium !== null) {
				test.info().annotations.push({ type: 'pdf', description: `rendered with ${chromium}` })
				expect(previewRes.status()).toBe(200)
				expect(previewRes.headers()['content-type']).toContain('application/pdf')
				expect((await previewRes.body()).subarray(0, 4).toString()).toBe('%PDF')
				await expect(preview.getByTestId('preview-pdf')).toBeVisible()
			} else {
				test.info().annotations.push({
					type: 'pdf',
					description: 'export_chromium_binary is not set, so the refusal was checked instead of a PDF',
				})
				expect(previewRes.status(), await previewRes.text()).toBe(422)
				await expect(preview.getByTestId('preview-error')).toContainText(
					'No headless browser is configured for PDF and PNG exports',
				)
			}
			expect(await runsOf(owner.api, exportId), 'a preview writes no run-row').toHaveLength(1)
			await closeDialogOf(page, preview)

			// Edit: a new name and a fire time a few minutes ahead.
			const slot = slotAhead(150_000)
			await rowAction(page, name, 'Edit')
			await expect(editor).toBeVisible()
			await editor.getByLabel('Name', { exact: true }).fill(edited)
			const days = editor.getByTestId('export-days')
			if (slot.code !== 'MO') {
				await days.getByText(DAY_LABELS[slot.code], { exact: true }).click()
				await days.getByText('Monday', { exact: true }).click()
			}
			await editor.getByLabel('Time', { exact: true }).fill(slot.time)
			await expect(editor.getByTestId('export-recurrence-summary')).toHaveText(
				`Runs every ${DAY_LABELS[slot.code]} at ${slot.time}.`,
			)
			const saved = await saveEditor(page, editor, 'PATCH')
			expect(saved.name).toBe(edited)
			expect(saved.recurrence).toEqual({ type: 'weekly', daysOfWeek: [slot.code], timeOfDay: slot.time })
			expect(Date.parse(saved.nextRunAt), 'the changed recurrence is rescheduled').toBe(slot.at)
			await expect(list.getByTestId('scheduled-export-name').filter({ hasText: edited })).toBeVisible()

			// A control export, enabled, due at the same moment.
			const control = await createExport(owner.api, {
				name: `Controle ${STAMP}`,
				subjectType: 'dashboard',
				subjectId: own!.uuid,
				runTimezone: 'UTC',
				recurrence: { type: 'weekly', daysOfWeek: [slot.code], timeOfDay: slot.time },
				renderTargets: [{ format: 'csv' }],
				recipients: [{ id: 'files', channel: 'nextcloudFiles', channelConfig: { targetPath: '/Exports e2e controle' } }],
			})
			controlId = String(control.id)
			expect(Date.parse(control.nextRunAt)).toBe(slot.at)

			// Switch it off.
			await rowAction(page, edited, 'Edit')
			await expect(editor).toBeVisible()
			const onSchedule = editor
				.getByRole('switch', { name: 'Run on schedule' })
				.or(editor.getByRole('checkbox', { name: 'Run on schedule' }))
			await expect(onSchedule).toBeChecked()
			await editor.getByText('Run on schedule', { exact: true }).click()
			await expect(onSchedule).not.toBeChecked()
			const off = await saveEditor(page, editor, 'PATCH')
			expect(off.enabled).toBe(false)
			const offRow = list.locator('tr').filter({ hasText: edited })
			await expect(offRow.getByTestId('scheduled-export-next')).toHaveText('Off')
			expect(
				Date.now(),
				'the edit and the switch took longer than the slot allows; the export may have been due while still on',
			).toBeLessThan(slot.at - 10_000)

			// Let the fire time pass, then run the runner until the control fired.
			await new Promise((resolve) => setTimeout(resolve, Math.max(0, slot.at - Date.now() + 3_000)))
			await expect
				.poll(async () => {
					await executeJob(RUNNER_JOB)
					return (await runsOf(owner.api, controlId!)).filter((r) => r.triggeredBy === 'schedule').length
				}, { timeout: 90_000, intervals: [5_000] })
				.toBe(1)

			const controlAfter = await readExport(owner.api, controlId)
			expect(controlAfter.lastRunAt, 'the runner records when it fired').toBeTruthy()
			expect(controlAfter.lastRunStatus, 'the runner records how it ended').toBe('success')
			expect(Date.parse(controlAfter.nextRunAt), 'the runner moves the schedule a week on').toBe(slot.at + WEEK_MS)

			const runs = await runsOf(owner.api, exportId)
			expect(runs, 'the switched-off export did not fire').toHaveLength(1)
			expect(runs[0].triggeredBy).toBe('manual')
			const exportAfter = await readExport(owner.api, exportId)
			expect(exportAfter.enabled).toBe(false)
			expect(exportAfter.lastRunAt).toBe(afterRun.lastRunAt)
			expect(Date.parse(exportAfter.nextRunAt)).toBe(slot.at)
		} finally {
			await context.close()
		}
	})
})

test.describe('scheduled exports: filter context per recipient (task 28)', () => {
	const rows = [
		{ gemeenteCode: 'GM0355', gemeente: 'Utrecht', zaak: 'Bezwaar parkeervergunning', aantal: 12 },
		{ gemeenteCode: 'GM0355', gemeente: 'Utrecht', zaak: 'Bezwaar WOZ', aantal: 30 },
		{ gemeenteCode: 'GM0344', gemeente: 'Amersfoort', zaak: 'Bezwaar omgevingsvergunning', aantal: 4 },
	]
	let own: OwnDashboard | undefined
	let exportId: string | null = null

	test.beforeAll(async () => {
		own = await setUpOwnDashboard('e2e-schedfilter', `Bezwaren per gemeente ${STAMP}`)
		await addTableWidget(own.owner.api, own.id, rows)
	})

	test.afterAll(async () => {
		if (own) {
			await removeExport(own.owner.api, exportId)
		}
		await tearDownOwnDashboard(own)
	})

	test('@e2e scheduled-exports::two-recipients-with-distinct-filter-contexts-produce-two-renders @e2e scheduled-exports::preview-without-dispatch two gemeenten get their own rows from one run', async () => {
		test.setTimeout(3 * 60_000)
		const owner = own!.owner
		const api = owner.api
		const created = await createExport(api, {
			name: `Bezwaren per gemeente ${STAMP}`,
			subjectType: 'dashboard',
			subjectId: own!.uuid,
			runTimezone: 'UTC',
			recurrence: WEEKLY,
			renderTargets: [{ format: 'csv', filenamePattern: 'bezwaren.{ext}' }],
			recipients: [
				{ id: 'gemeente-a', channel: 'nextcloudFiles', filterContext: { gemeenteCode: 'GM0355' }, channelConfig: { targetPath: '/Exports/GM0355' } },
				{ id: 'gemeente-b', channel: 'nextcloudFiles', filterContext: { gemeenteCode: 'GM0344' }, channelConfig: { targetPath: '/Exports/GM0344' } },
			],
		})
		exportId = String(created.id)

		// Preview for gemeente A: its rows only, and nothing written.
		const preview = await api.post(`${EXPORTS}/${exportId}/preview`, {
			data: { recipientId: 'gemeente-a', format: 'csv' },
		})
		expect(preview.status(), await preview.text()).toBe(200)
		const previewed = await preview.text()
		expect(previewed).toContain('Utrecht')
		expect(previewed).not.toContain('Amersfoort')
		expect(await runsOf(api, exportId), 'a preview writes no run-row').toHaveLength(0)
		expect(await filesIn(api, owner.username, '/Exports/GM0355'), 'a preview delivers nothing').toHaveLength(0)

		// One manual run: two renders, each delivered to its own gemeente.
		const fired = await runNow(api, exportId)
		await expect.poll(async () => (await readRun(api, exportId!, fired.id)).status, { timeout: 60_000 }).not.toBe('running')
		const run = await readRun(api, exportId, fired.id)
		expect(run.status, JSON.stringify(run.deliveries)).toBe('success')
		expect(run.renderArtefacts, 'one render per distinct filter context').toHaveLength(2)
		const hashes = run.renderArtefacts.map((a) => a.sha256)
		expect(new Set(hashes).size, 'the two renders differ').toBe(2)
		for (const hash of hashes) {
			expect(hash).toMatch(/^[a-f0-9]{64}$/)
		}
		expect(run.deliveries).toHaveLength(2)
		const byRecipient = new Map(run.deliveries.map((d) => [d.recipientId, d]))
		for (const id of ['gemeente-a', 'gemeente-b']) {
			expect(byRecipient.get(id)?.status, `delivery to ${id}`).toBe('success')
			expect(byRecipient.get(id)?.artefactIndexes).toHaveLength(1)
		}
		expect(byRecipient.get('gemeente-a')!.artefactIndexes[0]).not.toBe(byRecipient.get('gemeente-b')!.artefactIndexes[0])

		// Each gemeente's folder holds its own rows and not the other's.
		const expected: Array<[string, string, string]> = [
			['/Exports/GM0355', 'Utrecht', 'Amersfoort'],
			['/Exports/GM0344', 'Amersfoort', 'Utrecht'],
		]
		for (const [folder, mine, theirs] of expected) {
			const files = await filesIn(api, owner.username, folder)
			expect(files, `files in ${folder}`).toEqual(['bezwaren.csv'])
			const file = await api.get(davPath(owner.username, `${folder}/bezwaren.csv`))
			expect(file.status()).toBe(200)
			const csv = await file.text()
			expect(csv).toContain('gemeenteCode')
			expect(csv).toContain(mine)
			expect(csv).not.toContain(theirs)
		}
	})
})

test.describe('scheduled exports: the Exports tab of the dashboard settings (REQ-SCH-011)', () => {
	let own: OwnDashboard | undefined
	const exportIds: string[] = []

	test.beforeAll(async () => {
		own = await setUpOwnDashboard('e2e-schedtab', `Exports tab ${STAMP}`)
	})

	test.afterAll(async () => {
		if (own) {
			for (const id of exportIds) {
				await removeExport(own.owner.api, id)
			}
		}
		await tearDownOwnDashboard(own)
	})

	test('@e2e scheduled-exports::the-exports-tab-lists-this-dashboards-exports @e2e scheduled-exports::the-meer-menu-no-longer-offers-scheduled-exports the settings list this dashboard\'s exports only, and the Meer menu has no entry', async ({ browser }) => {
		test.setTimeout(3 * 60_000)
		const owner = own!.owner
		const api = owner.api

		// A second dashboard of the same owner, with an export of its own.
		const other = await api.post(`${API}/dashboard`, { data: { name: `Other ${STAMP}` } })
		expect(other.status(), await other.text()).toBeLessThan(300)
		const otherJson = await other.json()
		const otherUuid = String((otherJson.dashboard ?? otherJson.data?.dashboard ?? otherJson).uuid)
		expect((await api.post(`${API}/dashboard/${own!.id}/activate`)).status()).toBeLessThan(300)
		expect((await api.post(`${API}/dashboards/active`, { data: { uuid: own!.uuid } })).status()).toBeLessThan(300)

		const recipients = [{ channel: 'nextcloudFiles', channelConfig: { targetPath: '/Exports e2e tab' } }]
		const renderTargets = [{ format: 'csv' }]
		const mine = await createExport(api, {
			name: `This dashboard ${STAMP}`,
			subjectType: 'dashboard',
			subjectId: own!.uuid,
			runTimezone: 'UTC',
			recurrence: WEEKLY,
			renderTargets,
			recipients,
		})
		exportIds.push(String(mine.id))
		const theirs = await createExport(api, {
			name: `Other dashboard ${STAMP}`,
			subjectType: 'dashboard',
			subjectId: otherUuid,
			runTimezone: 'UTC',
			recurrence: WEEKLY,
			renderTargets,
			recipients,
		})
		exportIds.push(String(theirs.id))

		const { context, page } = await loginAs(browser, owner.username, owner.password)
		try {
			await page.goto('/index.php/apps/launchpad')
			const more = page.locator(MORE_MENU)
			await expect(more).toBeVisible({ timeout: 30_000 })
			await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
			// Control: the menu is open on this dashboard's actions.
			await expect(page.locator('[data-testid="launchpad-more-menu-item-edit"]')).toBeVisible()
			for (const id of ['config', 'scheduled-exports', 'schedule', 'publish', 'unpublish']) {
				await expect(page.locator(`[data-testid="launchpad-more-menu-item-${id}"]`)).toHaveCount(0)
			}
			await page.keyboard.press('Escape')
			await openDashboardSettings(page)
			await page.locator('[data-test="config-tab-exports"]').click()
			const list = page.getByTestId('scheduled-exports')
			await expect(list.getByTestId('scheduled-export-name').filter({ hasText: `This dashboard ${STAMP}` })).toBeVisible({ timeout: 15_000 })
			await expect(list.getByTestId('scheduled-export-name').filter({ hasText: `Other dashboard ${STAMP}` })).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})
