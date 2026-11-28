/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Picking a template's target groups on the Templates page
 * (admin-templates REQ-TMPL-011, REQ-TMPL-018).
 *
 * EXPECTED RED until the template editor offers the Nextcloud groups. The
 * "Target groups" field is an NcSelectTags (src/modals/TemplateEditorModal.vue,
 * the `form.targetGroups` field) fed `availableGroups: []`, and NcSelectTags
 * fetches the instance's SYSTEM TAGS, not its groups, so no group can be
 * picked and an administrator cannot give a template to a group from the
 * page. These tests used to be `test.fixme`, which gate-19 does not count as a
 * citation; they now run so the defect shows.
 *
 * They sit in their own file, without serial mode, so a red here cannot skip
 * the serial suites in admin-templates-ui.spec.ts and
 * admin-templates-shipped.spec.ts.
 *
 * The recorded shipped install of mijn-werkdag and the personal-dashboards
 * flag are read first and written back in afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::group-selection-in-template-editor
 *   @e2e admin-templates::an-administrator-adds-the-shipped-template-and-gives-it-to-a-group
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	activeDashboard,
	deleteTemplates,
	listTemplates,
	readAllowUserDashboards,
	readAppConfig,
	setAllowUserDashboards,
	writeAppConfig,
} from './support/adminTemplates.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { expectOpenDashboard } from './support/dashboardSwitcher.ts'
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
const UUID_KEY = 'shipped_template_mijn-werkdag'
const VERSION_KEY = 'shipped_template_version_mijn-werkdag'
const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad#templates`
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

interface ShippedRow {
	id: string
	isInstalled: boolean
	installedUuid: string
}

let admin: APIRequestContext
let allowBefore: boolean | null = null
const recordedBefore: Record<string, string> = {}
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

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

/** A throwaway group this file owns. */
async function group(tag: string): Promise<string> {
	const id = await createGroup(admin, `e2e-tpick-${tag}-${RUN}`)
	groups.push(id)
	return id
}

/**
 * A group in the target groups select, by its exact text. The select splits
 * a long name over two spans, so the option's accessible name is not the
 * group id and a by-role name match misses it.
 */
function groupOption(page: Page, id: string): Locator {
	const exact = new RegExp(`^${id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
	return page.getByRole('option').filter({ hasText: exact })
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

/** The shipped listing's mijn-werkdag row. */
async function shippedRow(): Promise<ShippedRow> {
	const res = await admin.get(`${API}/admin/templates/shipped`)
	expect(res.status()).toBe(200)
	return ((await res.json()) as ShippedRow[]).find((r) => r.id === 'mijn-werkdag')!
}

// @e2e admin-templates::group-selection-in-template-editor
test('the target groups field offers the Nextcloud groups', async ({ page }) => {
	const g = await group('pickable')
	await openTemplatesPage(page)
	await page.locator('[data-testid="admin-create-template"]').click()
	const editor = page.locator('[data-testid="admin-template-editor"]')
	await expect(editor).toBeVisible()
	await editor.getByRole('combobox', { name: 'Target groups' }).click()
	await expect(groupOption(page, g)).toBeVisible({
		timeout: 15_000,
	})
	// The built-in admin group is a Nextcloud group too.
	await expect(
		groupOption(page, 'admin'),
	).toBeVisible()
})

// @e2e admin-templates::an-administrator-adds-the-shipped-template-and-gives-it-to-a-group
test('the admin adds mijn-werkdag on the Templates page and gives it to a group', async ({
	page,
	browser,
}) => {
	const medewerkers = await group('medewerkers')
	const sanne = await createPrincipal(admin, 'e2e-tpick-sanne')
	principals.push(sanne)
	await addToGroup(admin, sanne.username, medewerkers)
	// Not installed: forget the recorded install, so the page offers "Add".
	await writeAppConfig(admin, UUID_KEY, '')
	await writeAppConfig(admin, VERSION_KEY, '')
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
	)
	expect(installed, 'the installed template is in the listing').toBeDefined()
	templates.push(installed!.id)

	// The row of the copy this test added: an earlier run can leave another
	// "Mijn werkdag" in the list.
	const row = page.locator(`[data-testid="admin-template-row-${installed!.id}"]`)
	await expect(row).toContainText('Mijn werkdag')
	await row.getByRole('button', { name: 'Edit' }).click()
	const editor = page.locator('[data-testid="admin-template-editor"]')
	await expect(editor).toBeVisible()
	await editor.getByRole('combobox', { name: 'Target groups' }).click()
	await groupOption(page, medewerkers).click()
	await editor.getByRole('button', { name: 'Save' }).click()
	await expect(editor).toBeHidden()

	// THEN the list shows "Mijn werkdag" with the group.
	await expect(row).toContainText(medewerkers)
	expect(
		(await listTemplates(admin)).find((t) => t.id === installed!.id)
			?.targetGroups,
	).toEqual([medewerkers])

	// AND a member opening LaunchPad for the first time gets a copy of it.
	const { context, page: memberPage } = await loginAs(
		browser,
		sanne.username,
		sanne.password,
	)
	try {
		await memberPage.goto('/index.php/apps/launchpad/')
		await expectOpenDashboard(memberPage, 'Mijn werkdag', 45_000)
	} finally {
		await context.close()
	}
	const copy = await activeDashboard(sanne.api)
	expect(copy.dashboard.basedOnTemplate).toBe(installed!.id)
	const compulsory = copy.placements
		.filter((p) => p.isCompulsory === 1)
		.map((p) => p.widgetId)
	// The header and the "First today" (attention) list are compulsory.
	expect(compulsory).toEqual(expect.arrayContaining(['header', 'attention']))
})
