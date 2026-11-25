/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What people see and do in the browser around admin templates:
 *
 *   - the Templates page lists templates and creates one through its editor
 *     (REQ-TMPL-011);
 *   - the page shell routes a member by their primary group, resolved from
 *     the admin's group order (REQ-TMPL-012), read from the page's own
 *     initial state (`primaryGroup`), which PageController fills from
 *     AdminTemplateService::resolvePrimaryGroup;
 *   - a list on a register that is not here hides, unless it could not be
 *     asked or the dashboard is in edit mode (REQ-TMPL-022);
 *   - deleting a compulsory widget from a template copy says why it cannot
 *     be done (REQ-TMPL-023).
 *
 * Settings this file flips (group order, allowUserDashboards, the default
 * template) are read first and restored in afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::template-list-in-admin-settings
 *   @e2e admin-templates::create-template-via-admin-ui
 *   @e2e admin-templates::first-match-wins-by-admin-configured-priority
 *   @e2e admin-templates::user-in-no-active-group-falls-through-to-default-sentinel
 *   @e2e admin-templates::empty-grouporder-always-returns-default
 *   @e2e admin-templates::configured-group-that-the-user-is-not-in-is-skipped
 *   @e2e admin-templates::configured-group-that-no-longer-exists-in-nextcloud-is-harmless
 *   @e2e admin-templates::a-list-whose-source-could-not-be-asked-still-shows
 *   @e2e admin-templates::in-edit-mode-the-list-is-there
 *   @e2e admin-templates::delete-widget-on-a-compulsory-widget
 *   @e2e admin-templates::a-widget-that-is-not-compulsory-is-deleted-as-before
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Page,
} from '@playwright/test'
import type { SeedWidget, TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	activeDashboard,
	createTemplate,
	deleteTemplates,
	importTemplate,
	listTemplates,
	placementsOf,
	readAllowUserDashboards,
	rememberDefaultTemplate,
	restoreDefaultTemplate,
	setAllowUserDashboards,
	textWidget,
} from './support/adminTemplates.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
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
const STAMP = `E2E TmplUI ${RUN}`
const SETTINGS_URL = `${BASE}/index.php/apps/launchpad/admin/templates`
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

let admin: APIRequestContext
let allowBefore: boolean | null = null
let defaultBefore: number | null = null
let groupOrderBefore: string[] | null = null
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	defaultBefore = await rememberDefaultTemplate(admin)
	const order = await admin.get(`${API}/admin/groups`)
	expect(order.status(), 'the group order must be readable').toBe(200)
	groupOrderBefore = (await order.json()).active ?? []
	await setAllowUserDashboards(admin, true)
})

test.afterAll(async () => {
	await deleteTemplates(admin, templates)
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	if (groupOrderBefore !== null) {
		await admin.post(`${API}/admin/groups`, {
			data: { groups: groupOrderBefore },
		})
	}
	for (const g of groups) {
		await removeGroup(admin, g)
	}
	await restoreDefaultTemplate(admin, defaultBefore)
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

async function group(tag: string): Promise<string> {
	const id = await createGroup(admin, `e2e-tui-${tag}-${RUN}`)
	groups.push(id)
	return id
}

async function member(tag: string, inGroups: string[] = []): Promise<Principal> {
	const p = await createPrincipal(admin, `e2e-tui-${tag}`)
	principals.push(p)
	for (const g of inGroups) {
		await addToGroup(admin, p.username, g)
	}
	return p
}

async function seedTemplate(
	name: string,
	options: Parameters<typeof importTemplate>[2],
): Promise<TemplateRow> {
	const row = await importTemplate(admin, `${STAMP} ${name}`, options)
	templates.push(row.id)
	return row
}

async function openTemplatesPage(page: Page): Promise<void> {
	await page
		.context()
		.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto(SETTINGS_URL)
	await expect(page.locator('[data-test="templates-page"]')).toBeVisible({
		timeout: 30_000,
	})
}

/** The Templates page's row for one template. */
function templateRow(page: Page, name: string) {
	return page.locator('.launchpad-admin__templates .launchpad-admin__template', {
		has: page.getByText(name, { exact: true }),
	})
}

test.describe('the Templates page (REQ-TMPL-011)', () => {
	// @e2e admin-templates::template-list-in-admin-settings
	test('every template is listed with its name, its groups and its default status', async ({
		page,
	}) => {
		const a = await createTemplate(admin, {
			name: `${STAMP} Listed groups`,
			targetGroups: ['e2e-alpha', 'e2e-beta'],
		})
		const b = await createTemplate(admin, {
			name: `${STAMP} Listed default`,
			isDefault: true,
		})
		templates.push(a.id, b.id)
		try {
			await openTemplatesPage(page)
			await expect(
				page
					.locator('[data-test="templates-page"]')
					.getByRole('heading', { name: 'Dashboard templates' }),
			).toBeVisible()

			const rowA = templateRow(page, a.name)
			await expect(rowA).toHaveCount(1)
			await expect(rowA).toContainText('e2e-alpha, e2e-beta')
			await expect(rowA.getByText('Default', { exact: true })).toHaveCount(0)

			const rowB = templateRow(page, b.name)
			await expect(rowB).toHaveCount(1)
			await expect(rowB).toContainText('All users')
			await expect(rowB.getByText('Default', { exact: true })).toBeVisible()

			// Every template the server lists has a row.
			const all = await listTemplates(admin)
			await expect(
				page.locator(
					'.launchpad-admin__templates .launchpad-admin__template',
				),
			).toHaveCount(all.length)
		} finally {
			await admin.put(`${API}/admin/templates/${b.id}`, {
				data: { isDefault: false },
			})
		}
	})

	// @e2e admin-templates::create-template-via-admin-ui
	test('Create template opens a form with every field and saving makes the template', async ({
		page,
	}) => {
		const name = `${STAMP} Made in the UI`
		await openTemplatesPage(page)
		await page.locator('[data-testid="admin-create-template"]').click()
		const editor = page.locator('[data-testid="admin-template-editor"]')
		await expect(editor).toBeVisible()
		await expect(
			editor.getByRole('heading', { name: 'Create template' }),
		).toBeVisible()
		await expect(editor.getByLabel('Template name')).toBeVisible()
		await expect(editor.getByLabel('Description')).toBeVisible()
		await expect(
			editor.getByText('Target groups', { exact: true }),
		).toBeVisible()
		await expect(editor.getByText('Permission level')).toBeVisible()
		await expect(editor.getByText('Set as default template')).toBeVisible()

		await editor.getByLabel('Template name').fill(name)
		await editor.getByLabel('Description').fill('Made by the e2e suite')
		const saved = page.waitForResponse(
			(r) =>
				r.url().endsWith('/api/admin/templates')
				&& r.request().method() === 'POST',
		)
		await editor.getByRole('button', { name: 'Save' }).click()
		const res = await saved
		expect(res.status()).toBe(201)
		const created = (await res.json()) as TemplateRow
		templates.push(created.id)

		await expect(editor).toBeHidden()
		await expect(templateRow(page, name)).toHaveCount(1)
		const stored = (await listTemplates(admin)).find((t) => t.id === created.id)
		expect(stored?.name).toBe(name)
		expect(stored?.description).toBe('Made by the e2e suite')
		expect(stored?.permissionLevel).toBe('add_only')
		expect(stored?.isDefault).toBe(0)
	})
})

/** The `primaryGroup` the page shell was given. */
async function primaryGroupOnPage(page: Page): Promise<string> {
	await page.goto('/index.php/apps/launchpad/')
	const input = page.locator('#initial-state-launchpad-primaryGroup')
	await expect(input).toHaveCount(1, { timeout: 45_000 })
	const raw = (await input.getAttribute('value')) ?? ''
	return JSON.parse(Buffer.from(raw, 'base64').toString('utf8'))
}

async function setGroupOrder(order: string[]): Promise<void> {
	const res = await admin.post(`${API}/admin/groups`, { data: { groups: order } })
	expect(res.status()).toBe(200)
	expect((await res.json()).groupOrder).toEqual(order)
}

async function setMembership(
	who: Principal,
	want: string[],
	all: string[],
): Promise<void> {
	for (const g of all) {
		await admin.delete(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(who.username)}/groups`,
			{
				form: { groupid: g },
			},
		)
	}
	for (const g of want) {
		await addToGroup(admin, who.username, g)
	}
}

test.describe('primary group routing (REQ-TMPL-012)', () => {
	let user: Principal | null = null
	let context: BrowserContext | null = null
	let page: Page | null = null
	let g: Record<string, string> = {}

	test.beforeAll(async ({ browser }: { browser: Browser }) => {
		g = {
			allStaff: await group('all-staff'),
			engineering: await group('engineering'),
			marketing: await group('marketing'),
			executives: await group('executives'),
			support: await group('support'),
		}
		user = await member('routing')
		const session = await loginAs(browser, user.username, user.password)
		context = session.context
		page = session.page
	})

	test.afterAll(async () => {
		await context?.close()
	})

	// @e2e admin-templates::first-match-wins-by-admin-configured-priority
	test('the first group in the admin order that the user is in wins', async () => {
		await setGroupOrder([g.engineering, g.allStaff])
		await setMembership(
			user!,
			[g.allStaff, g.engineering, g.marketing],
			Object.values(g),
		)
		expect(await primaryGroupOnPage(page!)).toBe(g.engineering)
	})

	// @e2e admin-templates::user-in-no-active-group-falls-through-to-default-sentinel
	test("a user in none of the ordered groups gets 'default'", async () => {
		await setGroupOrder([g.engineering, g.executives])
		await setMembership(user!, [g.support], Object.values(g))
		expect(await primaryGroupOnPage(page!)).toBe('default')
	})

	// @e2e admin-templates::empty-grouporder-always-returns-default
	test("with no group order every user gets 'default'", async () => {
		await setGroupOrder([])
		await setMembership(user!, [g.engineering, g.allStaff], Object.values(g))
		expect(await primaryGroupOnPage(page!)).toBe('default')
	})

	// @e2e admin-templates::configured-group-that-the-user-is-not-in-is-skipped
	test('an ordered group the user is not in is skipped', async () => {
		await setGroupOrder([g.executives, g.engineering])
		await setMembership(user!, [g.engineering, g.support], Object.values(g))
		expect(await primaryGroupOnPage(page!)).toBe(g.engineering)
	})

	// @e2e admin-templates::configured-group-that-no-longer-exists-in-nextcloud-is-harmless
	test('an ordered group that was deleted from Nextcloud is passed over without an error', async () => {
		const doomed = await createGroup(admin, `e2e-tui-deleted-${RUN}`)
		await setGroupOrder([doomed, g.engineering])
		await removeGroup(admin, doomed)
		await setMembership(user!, [g.engineering], Object.values(g))

		const res = await page!.goto('/index.php/apps/launchpad/')
		expect(res?.status()).toBe(200)
		expect(await primaryGroupOnPage(page!)).toBe(g.engineering)
	})
})

/** A template for one member, opened by them in the browser. */
async function memberOpens(
	browser: Browser,
	tag: string,
	widgets: SeedWidget[],
	setup: (context: BrowserContext) => Promise<void> = async () => undefined,
): Promise<{
	who: Principal
	copyId: number
	context: BrowserContext
	page: Page
	ids: number[]
}> {
	const g = await group(tag)
	await seedTemplate(tag, {
		widgets,
		targetGroups: [g],
		permissionLevel: 'add_only',
	})
	const who = await member(tag, [g])
	const copy = await activeDashboard(who.api)
	const ids = copy.placements.map((p) => p.id)
	const { context, page } = await loginAs(browser, who.username, who.password)
	await setup(context)
	await page.goto('/index.php/apps/launchpad/')
	return { who, copyId: copy.dashboard.id, context, page, ids }
}

test.describe('a list on a register that is not here (REQ-TMPL-022)', () => {
	const REGISTER = `e2e-absent-${RUN}`
	const listWidget = (title: string): SeedWidget => ({
		widgetId: 'object-list',
		gridX: 0,
		gridY: 2,
		gridWidth: 6,
		gridHeight: 4,
		customTitle: title,
		content: {
			title,
			register: REGISTER,
			schema: 'thing',
			hideWhenUnavailable: true,
		},
	})

	// @e2e admin-templates::a-list-whose-source-could-not-be-asked-still-shows
	test('when OpenRegister answers 500 the list stays, with its own error line', async ({
		browser,
	}) => {
		const title = `${STAMP} Bron 500`
		const marker = `${STAMP} marker 500`
		const session = await memberOpens(
			browser,
			'src500',
			[textWidget(marker, { x: 0, y: 0 }), listWidget(title)],
			async (context) => {
				await context.route(
					(url) =>
						url.pathname.includes(
							`/apps/openregister/api/objects/${REGISTER}/`,
						),
					(route) =>
						route.fulfill({
							status: 500,
							contentType: 'application/json',
							body: '{"error":"boom"}',
						}),
				)
			},
		)
		try {
			const { page } = session
			await expect(
				page.getByText(marker, { exact: true }).first(),
			).toBeVisible({ timeout: 45_000 })
			const list = page.locator('.launchpad-widget', {
				has: page.getByText(title, { exact: true }),
			})
			await expect(list).toBeVisible({ timeout: 20_000 })
			// The list's own error line: for a 5xx the object list says
			// "Temporarily unavailable" (nextcloud-vue loadErrorLabel, ADR-062);
			// "Could not load these records" is its line for other failures.
			await expect(list.getByText('Temporarily unavailable', { exact: true })).toBeVisible(
				{ timeout: 20_000 },
			)
		} finally {
			await session.context.close()
		}
	})

	// @e2e admin-templates::in-edit-mode-the-list-is-there
	test('a list hidden for a missing register is on the page in edit mode', async ({
		browser,
	}) => {
		const probe = await admin.get(
			`/index.php/apps/openregister/api/objects/${REGISTER}/thing?_limit=1`,
		)
		test.skip(
			probe.status() !== 404,
			`OpenRegister answered ${probe.status()}, not 404, for an unknown register`,
		)

		const title = `${STAMP} Bron weg`
		const marker = `${STAMP} marker weg`
		const session = await memberOpens(browser, 'srcgone', [
			textWidget(marker, { x: 0, y: 0 }),
			listWidget(title),
		])
		try {
			const { page } = session
			await expect(
				page.getByText(marker, { exact: true }).first(),
			).toBeVisible({ timeout: 45_000 })
			await page.waitForTimeout(3_000)
			await expect(page.getByText(title, { exact: true })).toHaveCount(0)

			await enterEditMode(page)
			await expect(page.getByText(title, { exact: true }).first()).toBeVisible(
				{ timeout: 20_000 },
			)
		} finally {
			await session.context.close()
		}
	})
})

test.describe('deleting a compulsory widget from a copy (REQ-TMPL-023)', () => {
	const COMPULSORY = `${STAMP} Vandaag eerst`
	const FREE = `${STAMP} Mijn zaken`
	let session: Awaited<ReturnType<typeof memberOpens>> | null = null

	test.beforeAll(async ({ browser }: { browser: Browser }) => {
		session = await memberOpens(browser, 'compulsory', [
			textWidget(COMPULSORY, { x: 0, y: 0 }, { isCompulsory: 1 }),
			textWidget(FREE, { x: 4, y: 0 }),
		])
		await expect(
			session.page.getByText(COMPULSORY, { exact: true }).first(),
		).toBeVisible({ timeout: 45_000 })
		await enterEditMode(session.page)
	})

	test.afterAll(async () => {
		await session?.context.close()
	})

	async function openWidgetMenu(title: string): Promise<void> {
		const { page, who, copyId } = session!
		const placement = (await placementsOf(who.api, copyId)).find(
			(p) => p.customTitle === title,
		)!
		const widget = page.locator(
			`[data-testid="widget-placement-${placement.id}"]`,
		)
		await widget.getByRole('button', { name: 'Widget menu' }).click()
	}

	async function chooseDelete(title: string): Promise<void> {
		await openWidgetMenu(title)
		await session!.page.getByRole('menuitem', { name: 'Delete widget' }).click()
	}

	// @e2e admin-templates::delete-widget-on-a-compulsory-widget
	test('the widget menu offers no delete on a compulsory widget, and nothing is sent or removed', async () => {
		const { page, who, copyId } = session!
		const deletes: string[] = []
		page.on('request', (r) => {
			if (r.method() === 'DELETE' && r.url().includes('/api/widgets/')) {
				deletes.push(r.url())
			}
		})

		await openWidgetMenu(COMPULSORY)
		// CONTROL: the menu is open.
		await expect(page.getByRole('menuitem', { name: 'Edit widget' })).toBeVisible()
		await expect(page.getByRole('menuitem', { name: 'Delete widget' })).toHaveCount(0)
		await page.keyboard.press('Escape')
		await page.waitForTimeout(1_000)
		expect(deletes).toEqual([])
		await expect(
			page.getByText(COMPULSORY, { exact: true }).first(),
		).toBeVisible()
		expect(
			(await placementsOf(who.api, copyId)).map((p) => p.customTitle),
		).toContain(COMPULSORY)
	})

	// @e2e admin-templates::a-widget-that-is-not-compulsory-is-deleted-as-before
	test('a widget that is not compulsory is removed, with no message', async () => {
		const { page, who, copyId } = session!
		const removed = page.waitForResponse(
			(r) =>
				r.request().method() === 'DELETE'
				&& r.url().includes('/api/widgets/'),
		)
		await chooseDelete(FREE)
		expect((await removed).status()).toBe(200)
		await expect(page.getByText(FREE, { exact: true })).toHaveCount(0)
		await expect(
			page.getByText(`"${FREE}" is part of the start page`),
		).toHaveCount(0)
		expect(
			(await placementsOf(who.api, copyId)).map((p) => p.customTitle),
		).not.toContain(FREE)
	})
})
