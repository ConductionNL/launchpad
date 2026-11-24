/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What an automatic version says it changed (REQ-VERSUI-004, the several-
 * changes case) and how a template re-sync signs the version it writes
 * (REQ-VERSUI-005). Written for e2e-coverage-programme task 2.3, for the two
 * scenarios the archived change version-change-notes added to
 * openspec/specs/dashboard-versioning after that task was run green. The
 * single-change case ("A widget moved") is in dashboard-versioning-ui.spec.ts.
 *
 * SEVERAL CHANGES. Femke, a throwaway owner who is not an administrator, has
 * a dashboard on 8 columns with the widget "Oud nieuws". An explicit version
 * is the baseline. In one go she adds the widget "Inbox", removes "Oud
 * nieuws" and saves the dashboard on 12 columns. An automatic version holds
 * the state a save replaced, at most one per 60 seconds per dashboard
 * (REQ-VERS-001), and the baseline starts that window, so the version that
 * records all three changes is the one the next save writes once the window
 * has passed. The test saves again over the API until it appears, the same
 * way the "A widget moved" test does, then reads its note on the Versions tab
 * as Femke sees it.
 *
 * TEMPLATE RE-SYNC. A throwaway member of a fresh group gets a copy of the
 * template "Intranet <run>" on a first visit, then moves one of its widgets
 * so the copy differs from the template. The administrator re-syncs with
 * `overwrite` (admin-templates REQ-RESYNC-001). The copy then holds exactly
 * one new version, by "Template Intranet <run>" and noted "Updated from the
 * template", and no version by the administrator. The member, who owns the
 * copy, reads it on the Versions tab. The run stamp keeps parallel runs apart;
 * the author is cut at 64 characters, which this name stays well under.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-versioning::several-changes-at-once
 *   @e2e dashboard-versioning::intranet-updated-from-its-template
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	activeDashboard,
	addTitledTextWidget,
	deleteTemplates,
	importTemplate,
	placementsOf,
	readAllowUserDashboards,
	setAllowUserDashboards,
	textWidget,
} from './support/adminTemplates.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { openDashboardSettings } from './support/dashboardSwitcher.ts'
import { quietFirstVisit } from './support/firstVisit.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const RUN = Date.now()
const STAMP = `E2E Version notes ${RUN}`
const VERSIONS_TAB = '[data-test="config-tab-versions"]'
const DIALOG = '[data-testid="version-history"]'
const ROW = '[data-testid="version-row"]'

interface VersionRow {
	versionNumber: number
	note: string | null
	createdBy: string
	createdByName?: string
}

let admin: APIRequestContext
let allowBefore: boolean | null = null

/**
 * The versions of a dashboard, newest first, as `api` reads them.
 *
 * @param api a context that may list the versions.
 * @param uuid the dashboard uuid.
 * @return the rows.
 */
async function versionsOf(
	api: APIRequestContext,
	uuid: string,
): Promise<VersionRow[]> {
	const res = await api.get(`${API}/dashboards/${uuid}/versions`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).versions as VersionRow[]
}

/**
 * Open the Versions tab of the dashboard settings from the edit bar.
 *
 * @param page the start page.
 * @return the tab's content, once it is visible.
 */
async function openVersionHistory(page: Page) {
	await openDashboardSettings(page)
	await page.locator(VERSIONS_TAB).click({ timeout: 15_000 })
	const dialog = page.locator(DIALOG)
	await expect(dialog).toBeVisible({ timeout: 15_000 })
	return dialog
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	allowBefore = await readAllowUserDashboards(admin)
	await setAllowUserDashboards(admin, true)
})

test.afterAll(async () => {
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

test.describe('REQ-VERSUI-004 several changes in one save', () => {
	// @e2e dashboard-versioning::several-changes-at-once
	test('Femke adds Inbox, removes Oud nieuws and goes from 8 to 12 columns: the note reads "Widget Inbox added and 2 more changes"', async ({
		browser,
	}) => {
		test.setTimeout(240_000)
		let femke: Principal | null = null
		let dashId: number | null = null
		try {
			femke = await createPrincipal(admin, 'e2e-versnotes-femke')
			await assertActingAs(femke.api, femke.username)
			await quietFirstVisit(femke.api)

			// GIVEN: her dashboard on 8 columns with the widget Oud nieuws.
			const created = await femke.api.post(`${API}/dashboard`, {
				data: { name: `${STAMP} Femke`, gridColumns: 8 },
			})
			expect(created.status(), await created.text()).toBe(201)
			const dash = (await created.json()).dashboard
			dashId = Number(dash.id)
			const uuid = String(dash.uuid)
			const name = String(dash.name)
			const activate = await femke.api.post(`${API}/dashboard/${dashId}/activate`)
			expect(activate.status(), await activate.text()).toBeLessThan(300)
			const pref = await femke.api.post(`${API}/dashboards/active`, {
				data: { uuid },
			})
			expect(pref.status(), await pref.text()).toBeLessThan(300)
			await clearSeededWidgets(femke.api, dashId)
			expect(
				await addTitledTextWidget(femke.api, dashId, 'Oud nieuws', { x: 0, y: 0 }),
			).toEqual({ created: 201, titled: 200 })
			const start = await femke.api.get(`${API}/dashboard/${dashId}`)
			expect(start.status()).toBe(200)
			const startBody = await start.json()
			expect(Number(startBody.dashboard.gridColumns), 'the dashboard starts on 8 columns').toBe(8)

			// The baseline the note is measured against.
			const baseline = await admin.post(`${API}/dashboards/${uuid}/versions`, {
				data: { note: 'baseline' },
			})
			expect(baseline.status(), await baseline.text()).toBe(201)
			const baselineNumber = Number((await baseline.json()).version.versionNumber)

			// WHEN: Inbox added, Oud nieuws removed, columns from 8 to 12.
			expect(
				await addTitledTextWidget(femke.api, dashId, 'Inbox', { x: 4, y: 0 }),
			).toEqual({ created: 201, titled: 200 })
			const oud = (await placementsOf(femke.api, dashId)).find(
				(p) => p.customTitle === 'Oud nieuws',
			)
			expect(oud, 'Oud nieuws is on the dashboard').toBeDefined()
			const removed = await femke.api.delete(`${API}/widgets/${oud!.id}`)
			expect(removed.status(), await removed.text()).toBeLessThan(300)
			const columns = await femke.api.put(`${API}/dashboard/${dashId}`, {
				data: { gridColumns: 12 },
			})
			expect(columns.status(), await columns.text()).toBe(200)
			const after = await femke.api.get(`${API}/dashboard/${dashId}`)
			const afterBody = await after.json()
			expect(Number(afterBody.dashboard.gridColumns), 'the save is stored').toBe(12)
			expect(
				(afterBody.placements as Array<{ customTitle: string | null }>)
					.map((p) => p.customTitle)
					.sort(),
			).toEqual(['Inbox'])

			// THEN: the snapshot that holds all three is written by the next
			// save once the 60-second window has passed.
			await expect
				.poll(
					async () => {
						const put = await femke!.api.put(`${API}/dashboard/${dashId}`, {
							data: { name },
						})
						expect(put.status(), await put.text()).toBe(200)
						return (await versionsOf(femke!.api, uuid))
							.filter((v) => v.versionNumber > baselineNumber)
							.map((v) => v.note)
					},
					{ timeout: 150_000, intervals: [5_000, 10_000, 15_000] },
				)
				.toContain('Widget Inbox added and 2 more changes')

			const { context, page } = await loginAs(
				browser,
				femke.username,
				femke.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad/')
				const dialog = await openVersionHistory(page)
				const row = dialog
					.locator(ROW)
					.filter({ hasText: 'Widget Inbox added and 2 more changes' })
				await expect(row).toHaveCount(1)
				await expect(row.locator('td').nth(2)).toHaveText(
					'Widget Inbox added and 2 more changes',
				)
			} finally {
				await context.close()
			}
		} finally {
			if (femke !== null && dashId !== null) {
				await femke.api.delete(`${API}/dashboard/${dashId}`)
			}
			await removePrincipal(admin, femke)
		}
	})
})

test.describe('REQ-VERSUI-005 a template update is noted as such', () => {
	// @e2e dashboard-versioning::intranet-updated-from-its-template
	test('after a re-sync the copy holds one version by "Template Intranet" noted "Updated from the template"', async ({
		browser,
	}) => {
		test.setTimeout(180_000)
		const templateName = `Intranet ${RUN}`
		const author = `Template ${templateName}`
		let member: Principal | null = null
		let group: string | null = null
		let templateId: number | null = null
		try {
			group = await createGroup(admin, `e2e-versnotes-${RUN}`)
			const template = await importTemplate(admin, templateName, {
				widgets: [
					textWidget(`${STAMP} Nieuws`, { x: 0, y: 0 }),
					textWidget(`${STAMP} Links`, { x: 4, y: 0 }),
				],
				targetGroups: [group],
			})
			templateId = template.id

			// GIVEN: the member's dashboard is a copy of the template.
			member = await createPrincipal(admin, 'e2e-versnotes-member')
			await assertActingAs(member.api, member.username)
			await quietFirstVisit(member.api)
			await addToGroup(admin, member.username, group)
			const first = await activeDashboard(member.api)
			expect(first.status).toBe(200)
			expect(first.dashboard.basedOnTemplate, 'the member holds a copy').toBe(templateId)
			const copyId = Number(first.dashboard.id)
			const copyUuid = String(first.dashboard.uuid)
			expect(
				(first as unknown as { isOwner?: boolean }).isOwner,
				'the member owns the copy, so the Versions tab is offered',
			).toBe(true)

			// The copy differs from the template: the member moved a widget.
			const nieuws = (await placementsOf(member.api, copyId)).find(
				(p) => p.customTitle === `${STAMP} Nieuws`,
			)
			expect(nieuws, 'the template widget is on the copy').toBeDefined()
			const moved = await member.api.put(`${API}/widgets/${nieuws!.id}`, {
				data: { gridY: 6, version: nieuws!.version },
			})
			expect(moved.status(), await moved.text()).toBe(200)
			const before = await versionsOf(member.api, copyUuid)
			const newestBefore = before.reduce((max, v) => Math.max(max, v.versionNumber), 0)

			// WHEN: the administrator re-syncs the template.
			const res = await admin.post(`${API}/admin/templates/${templateId}/resync`, {
				data: { strategy: 'overwrite', dryRun: false },
			})
			expect(res.status(), await res.text()).toBe(200)
			const back = (await placementsOf(member.api, copyId)).find(
				(p) => p.customTitle === `${STAMP} Nieuws`,
			)
			expect(back?.gridY, 'the re-sync changed the copy').toBe(0)

			// THEN: one new version, by the template, with the template note.
			const added = (await versionsOf(member.api, copyUuid)).filter(
				(v) => v.versionNumber > newestBefore,
			)
			expect(added).toHaveLength(1)
			expect(added[0]).toMatchObject({
				createdBy: author,
				note: 'Updated from the template',
			})
			expect(added.map((v) => v.createdBy)).not.toContain(ADMIN_USER)

			const { context, page } = await loginAs(
				browser,
				member.username,
				member.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad/')
				const dialog = await openVersionHistory(page)
				const top = dialog.locator(ROW).first()
				await expect(top.locator('td').nth(1)).toHaveText(author)
				await expect(top.locator('td').nth(2)).toHaveText('Updated from the template')
			} finally {
				await context.close()
			}
		} finally {
			await deleteTemplates(admin, [templateId])
			await removePrincipal(admin, member)
			await removeGroup(admin, group)
		}
	})
})
