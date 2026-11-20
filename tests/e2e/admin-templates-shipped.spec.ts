/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The templates LaunchPad ships (admin-templates REQ-TMPL-018) and updating
 * an installed one in place from the Templates page (REQ-TMPL-020).
 *
 * HOW "A NEWER VERSION SHIPS" IS STAGED. The shipped file cannot be changed
 * from a browser test, so the INSTALLED side is made older instead. A forced
 * install gives the shipped widgets as the server reads them; from those an
 * archive is built with three differences (the header dropped, a hand-made
 * text widget added, the calendar's settings changed), imported, and recorded
 * as the installed mijn-werkdag one version below the shipped one, through
 * Nextcloud's app-config API. The update then has a widget to add, one to
 * remove and one to change, and a member's copy to follow.
 *
 * The recorded install (`shipped_template_mijn-werkdag` and its version) is
 * read first and written back in afterAll, so a later suite meets it as it
 * was.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::an-administrator-adds-the-shipped-template-and-gives-it-to-a-group
 *   @e2e admin-templates::installing-twice-adds-one-template
 *   @e2e admin-templates::a-template-deleted-by-the-administrator-can-be-added-again
 *   @e2e admin-templates::the-listing-names-widgets-no-app-registers-here
 *   @e2e admin-templates::an-administrator-updates-the-installed-template-from-the-templates-page
 *   @e2e admin-templates::a-members-copy-follows-and-keeps-what-the-member-added
 *   @e2e admin-templates::a-widget-in-both-versions-keeps-its-row
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type {
	PlacementRow,
	SeedWidget,
	TemplateRow,
} from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	activeDashboard,
	addTitledTextWidget,
	deleteTemplates,
	getTemplate,
	importTemplate,
	listTemplates,
	placementsOf,
	readAllowUserDashboards,
	readAppConfig,
	setAllowUserDashboards,
	textWidget,
	writeAppConfig,
} from './support/adminTemplates.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import {
	addToGroup,
	adminContext,
	API,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E Shipped ${RUN}`
const UUID_KEY = 'shipped_template_mijn-werkdag'
const VERSION_KEY = 'shipped_template_version_mijn-werkdag'
const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad?tab=templates`
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

interface ShippedRow {
	id: string
	name: string
	version: number
	isInstalled: boolean
	installedUuid: string
	installedVersion: number | null
	updateAvailable: boolean
	missingWidgets: string[]
}

let admin: APIRequestContext
let allowBefore: boolean | null = null
const recordedBefore: Record<string, string> = {}
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	await setAllowUserDashboards(admin, true)
	for (const key of [UUID_KEY, VERSION_KEY]) {
		recordedBefore[key] = await readAppConfig(admin, key)
	}
})

test.afterAll(async () => {
	await deleteTemplates(admin, templates)
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	for (const g of groups) {
		await removeGroup(admin, g)
	}
	for (const key of [UUID_KEY, VERSION_KEY]) {
		await writeAppConfig(admin, key, recordedBefore[key] ?? '').catch(
			() => undefined,
		)
	}
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

/** The shipped listing's mijn-werkdag row. */
async function shippedRow(): Promise<ShippedRow> {
	const res = await admin.get(`${API}/admin/templates/shipped`)
	expect(res.status()).toBe(200)
	return ((await res.json()) as ShippedRow[]).find((r) => r.id === 'mijn-werkdag')!
}

/** Install mijn-werkdag. */
async function install(force: boolean) {
	const res = await admin.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{
			data: { force },
		},
	)
	const body = await res.json()
	if (typeof body.id === 'number') {
		templates.push(body.id)
	}
	return { status: res.status(), body }
}

/** A placement as an archive widget, carrying every field it sets. */
function asSeed(p: PlacementRow): SeedWidget {
	const seed: SeedWidget = {
		widgetId: p.widgetId,
		gridX: p.gridX,
		gridY: p.gridY,
		gridWidth: p.gridWidth,
		gridHeight: p.gridHeight,
		isCompulsory: p.isCompulsory,
	}
	for (const key of [
		'customTitle',
		'customIcon',
		'content',
		'styleConfig',
		'showTitle',
		'isVisible',
		'sortOrder',
		'tileType',
		'tileTitle',
		'tileIcon',
		'tileIconType',
		'tileBackgroundColor',
		'tileTextColor',
		'tileLinkType',
		'tileLinkValue',
	]) {
		if (p[key] !== null && p[key] !== undefined) {
			seed[key] = p[key]
		}
	}
	return seed
}

/** Open the Templates page as the admin. */
async function openTemplatesPage(page: Page): Promise<void> {
	await page
		.context()
		.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto(SETTINGS_URL)
	await expect(page.locator('[data-test="templates-page"]')).toBeVisible({
		timeout: 30_000,
	})
}

test.describe('adding a shipped template (REQ-TMPL-018)', () => {
	// @e2e admin-templates::an-administrator-adds-the-shipped-template-and-gives-it-to-a-group
	// Blocked by the product: the editor's "Target groups" field is an
	// NcSelectTags (src/modals/TemplateEditorModal.vue:67-69) with
	// `availableGroups: []` (line 161). NcSelectTags fetches SYSTEM TAGS, not
	// groups, so a Nextcloud group cannot be picked on the Templates page.
	test.fixme('the admin adds mijn-werkdag on the Templates page and gives it to a group', async ({
		page,
		browser,
	}) => {
		const medewerkers = await createGroup(admin, `e2e-medewerkers-${RUN}`)
		groups.push(medewerkers)
		const sanne = await createPrincipal(admin, 'e2e-shipped-sanne')
		principals.push(sanne)
		await addToGroup(admin, sanne.username, medewerkers)
		await writeAppConfig(admin, UUID_KEY, '')
		expect((await shippedRow()).isInstalled).toBe(false)

		await openTemplatesPage(page)
		const shipped = page.locator(
			'[data-testid="admin-shipped-template-mijn-werkdag"]',
		)
		const pending = page.waitForResponse((r) =>
			r.url().includes('/shipped/mijn-werkdag/install'),
		)
		await shipped.locator('[data-testid="admin-shipped-template-add"]').click()
		expect((await pending).status()).toBe(201)
		await expect(
			shipped.locator('[data-testid="admin-shipped-template-added"]'),
		).toBeVisible()
		const installedUuid = (await shippedRow()).installedUuid
		const installed = (await listTemplates(admin)).find(
			(t) => t.uuid === installedUuid,
		)!
		templates.push(installed.id)

		const row = page.locator(
			'.launchpad-admin__templates .launchpad-admin__template',
			{
				hasText: 'Mijn werkdag',
			},
		)
		await row.getByRole('button', { name: 'Edit' }).first().click()
		const editor = page.locator('[data-testid="admin-template-editor"]')
		await expect(editor).toBeVisible()
		await editor.getByRole('combobox', { name: 'Target groups' }).click()
		await page.getByRole('option', { name: medewerkers }).click()
		await editor.getByRole('button', { name: 'Save' }).click()
		await expect(editor).toBeHidden()
		await expect(row.filter({ hasText: medewerkers })).toHaveCount(1)
		expect(
			(await listTemplates(admin)).find((t) => t.id === installed.id)
				?.targetGroups,
		).toEqual([medewerkers])

		const { context, page: memberPage } = await loginAs(
			browser,
			sanne.username,
			sanne.password,
		)
		try {
			await memberPage.goto('/index.php/apps/launchpad/')
			await expect(
				memberPage.getByText('Mijn werkdag', { exact: true }).first(),
			).toBeVisible({ timeout: 45_000 })
		} finally {
			await context.close()
		}
		const copy = await activeDashboard(sanne.api)
		expect(copy.dashboard.basedOnTemplate).toBe(installed.id)
		const compulsory = copy.placements
			.filter((p) => p.isCompulsory === 1)
			.map((p) => p.widgetId)
		expect(compulsory).toEqual(expect.arrayContaining(['header', 'attention']))
	})

	// @e2e admin-templates::installing-twice-adds-one-template
	test('a second install without force adds nothing and names the same template', async () => {
		const first = await install(true)
		expect(first.status).toBe(201)
		const count = (await listTemplates(admin)).length

		const again = await install(false)
		expect(again.status).toBe(200)
		expect(again.body.alreadyInstalled).toBe(true)
		expect(again.body.uuid).toBe(first.body.uuid)
		expect(again.body.id).toBe(first.body.id)
		const after = await listTemplates(admin)
		expect(after).toHaveLength(count)
		expect(after.filter((t) => t.uuid === first.body.uuid)).toHaveLength(1)
	})

	// @e2e admin-templates::a-template-deleted-by-the-administrator-can-be-added-again
	test('after the admin deletes the installed template, an install without force adds a new one', async () => {
		const first = await install(true)
		expect(first.status).toBe(201)
		expect(
			(await admin.delete(`${API}/admin/templates/${first.body.id}`)).status(),
		).toBe(200)
		expect((await shippedRow()).isInstalled).toBe(false)

		const again = await install(false)
		expect(again.status).toBe(201)
		expect(again.body.alreadyInstalled).toBe(false)
		expect(again.body.uuid).not.toBe(first.body.uuid)
		const row = await shippedRow()
		expect(row.isInstalled).toBe(true)
		expect(row.installedUuid).toBe(again.body.uuid)
		expect(
			(await listTemplates(admin)).filter((t) => t.uuid === again.body.uuid),
		).toHaveLength(1)
	})

	// @e2e admin-templates::the-listing-names-widgets-no-app-registers-here
	test('with the Activity app off, the listing names the activity widget as missing', async () => {
		const widgets = await admin.get(
			'/ocs/v2.php/apps/dashboard/api/v1/widgets?format=json',
		)
		const registered = widgets.ok()
			? Object.keys((await widgets.json()).ocs?.data ?? {})
			: []
		const disable = registered.includes('activity')
		if (disable) {
			const off = await admin.delete('/ocs/v1.php/cloud/apps/activity')
			expect(off.ok(), 'the Activity app must switch off').toBe(true)
		}
		try {
			const row = await shippedRow()
			expect(row.missingWidgets).toContain('activity')
		} finally {
			if (disable) {
				const on = await admin.post('/ocs/v1.php/cloud/apps/activity')
				expect(on.ok(), 'the Activity app must switch back on').toBe(true)
			}
		}
	})
})

test.describe('updating the installed template in place (REQ-TMPL-020)', () => {
	let group = ''
	let pieter: Principal | null = null
	let installed: TemplateRow | null = null
	let reference: PlacementRow[] = []
	let calendarId = 0
	let shippedVersion = 0
	const HANDMADE = `${STAMP} handwerk`
	const OWN = `${STAMP} eigen`

	test.beforeAll(async () => {
		group = await createGroup(admin, `e2e-behandelaars-${RUN}`)
		groups.push(group)

		// The shipped widgets as the server reads them.
		const fresh = await install(true)
		expect(fresh.status).toBe(201)
		shippedVersion = fresh.body.version
		reference = (await getTemplate(admin, fresh.body.id)).placements

		// An older installed version: no header, a hand-made widget, other
		// calendar settings.
		const widgets = reference
			.filter((p) => p.widgetId !== 'header')
			.map((p) =>
				p.widgetId === 'calendar'
					? {
							...asSeed(p),
							content: { ...(p.content ?? {}), daysAhead: 3 },
						}
					: asSeed(p),
			)
		widgets.push(textWidget(HANDMADE, { x: 0, y: 16 }))
		installed = await importTemplate(admin, `${STAMP} Werkdag`, {
			widgets,
			targetGroups: [group],
			permissionLevel: 'add_only',
		})
		templates.push(installed.id)
		await writeAppConfig(admin, UUID_KEY, installed.uuid)
		await writeAppConfig(admin, VERSION_KEY, String(shippedVersion - 1))
		calendarId = (await getTemplate(admin, installed.id)).placements.find(
			(p) => p.widgetId === 'calendar',
		)!.id

		pieter = await createPrincipal(admin, 'e2e-shipped-pieter')
		principals.push(pieter)
		await addToGroup(admin, pieter.username, group)
		const copy = await activeDashboard(pieter.api)
		expect(copy.dashboard.basedOnTemplate).toBe(installed.id)
		const own = await addTitledTextWidget(pieter.api, copy.dashboard.id, OWN, { x: 0, y: 20 })
		expect(own).toEqual({ created: 201, titled: 200 })
	})

	// @e2e admin-templates::an-administrator-updates-the-installed-template-from-the-templates-page
	test('the dialog lists the changes, writes nothing until confirmed, then updates in place', async ({
		page,
	}) => {
		const row = await shippedRow()
		expect(row.installedUuid).toBe(installed!.uuid)
		expect(row.updateAvailable).toBe(true)
		const before = (await getTemplate(admin, installed!.id)).placements

		await openTemplatesPage(page)
		const shipped = page.locator(
			'[data-testid="admin-shipped-template-mijn-werkdag"]',
		)
		const button = shipped.locator(
			'[data-testid="admin-shipped-template-update"]',
		)
		await expect(button).toHaveText(`Update to version ${shippedVersion}`)
		await button.click()

		const plan = page.locator('[data-testid="shipped-update-plan"]')
		await expect(plan).toBeVisible({ timeout: 20_000 })
		await expect(
			plan.locator('[data-testid="shipped-update-added"]'),
		).toContainText('Vandaag (header)')
		await expect(
			plan.locator('[data-testid="shipped-update-removed"]'),
		).toContainText(`${HANDMADE} (text)`)
		await expect(
			plan.locator('[data-testid="shipped-update-changed"]'),
		).toContainText('Agenda vandaag (calendar)')
		await expect(
			plan.locator('[data-testid="shipped-update-copies"]'),
		).toContainText('Members with a copy of this template: 1.')

		// Nothing is written while the dialog is open.
		expect((await getTemplate(admin, installed!.id)).placements).toEqual(before)
		expect(await readAppConfig(admin, VERSION_KEY)).toBe(
			String(shippedVersion - 1),
		)

		const applied = page.waitForResponse(
			(r) =>
				r.url().includes('/shipped/mijn-werkdag/update')
				&& r.request().method() === 'POST'
				&& !(r.request().postData() ?? '').includes('"dryRun":true'),
		)
		await page.locator('[data-testid="shipped-update-confirm"]').click()
		expect((await applied).status()).toBe(200)
		await expect(
			page.locator('[data-testid="admin-templates-status"]'),
		).toContainText(`to version ${shippedVersion}`)
		await expect(button).toHaveCount(0)

		const after = await getTemplate(admin, installed!.id)
		expect(after.template.id).toBe(installed!.id)
		expect(after.template.uuid).toBe(installed!.uuid)
		expect(after.template.targetGroups).toEqual([group])
		expect(after.placements.map((p) => p.widgetId).sort()).toEqual(
			reference.map((p) => p.widgetId).sort(),
		)
		expect(after.placements.map((p) => p.customTitle)).not.toContain(HANDMADE)
		expect(
			after.placements.find((p) => p.widgetId === 'header')?.isCompulsory,
		).toBe(1)
		expect(await readAppConfig(admin, VERSION_KEY)).toBe(String(shippedVersion))
		expect((await shippedRow()).updateAvailable).toBe(false)
	})

	// @e2e admin-templates::a-members-copy-follows-and-keeps-what-the-member-added
	test("pieter's copy gains the compulsory header, loses the dropped widget and keeps his own", async () => {
		const copy = await activeDashboard(pieter!.api)
		expect(copy.dashboard.basedOnTemplate).toBe(installed!.id)
		const ps = await placementsOf(pieter!.api, copy.dashboard.id)
		const header = ps.find((p) => p.widgetId === 'header')
		expect(header?.isCompulsory).toBe(1)
		expect(ps.map((p) => p.customTitle)).not.toContain(HANDMADE)
		expect(ps.map((p) => p.customTitle)).toContain(OWN)
	})

	// @e2e admin-templates::a-widget-in-both-versions-keeps-its-row
	test('the calendar row keeps its id and carries the shipped settings', async () => {
		const after = (await getTemplate(admin, installed!.id)).placements
		const calendar = after.find((p) => p.widgetId === 'calendar')!
		expect(calendar.id).toBe(calendarId)
		const shippedCalendar = reference.find((p) => p.widgetId === 'calendar')!
		expect(calendar.content).toEqual(shippedCalendar.content)
		expect((calendar.content as { daysAhead: number }).daysAhead).not.toBe(3)
	})
})
