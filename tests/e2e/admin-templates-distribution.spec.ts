/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Who receives which admin template, and what their copy is afterwards
 * (admin-templates REQ-TMPL-003, -004, -005, -006, -007, -010, -019, -021).
 *
 * Every account and group here is a throwaway this file makes and removes.
 * A first access is the member's own `GET /api/dashboard` (the call the page
 * makes, and the one that hands out a copy) unless the scenario is about the
 * page, in which case the member logs in and opens LaunchPad.
 *
 * Templates with widgets are seeded through the dashboard importer: the
 * widget API has no path to a template (see support/adminTemplates.ts), which
 * is also why the scenarios whose ACTION is "the admin adds a widget to the
 * template" are written to the scenario and marked fixme below.
 *
 * Settings this file flips (allowUserDashboards, the default template, the
 * recorded shipped install of mijn-werkdag) are read first and restored in
 * afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::update-template-target-groups
 *   @e2e admin-templates::update-template-permission-level
 *   @e2e admin-templates::update-template-widget-layout
 *   @e2e admin-templates::delete-a-template-with-existing-user-copies
 *   @e2e admin-templates::first-time-user-receives-default-template
 *   @e2e admin-templates::first-time-user-receives-group-targeted-template
 *   @e2e admin-templates::template-already-distributed-to-user
 *   @e2e admin-templates::multiple-templates-match-the-user
 *   @e2e admin-templates::user-modifies-their-template-copy
 *   @e2e admin-templates::admin-updates-template-after-distribution
 *   @e2e admin-templates::admin-deletes-template-after-distribution
 *   @e2e admin-templates::add-widget-to-template
 *   @e2e admin-templates::remove-widget-from-template
 *   @e2e admin-templates::configure-template-grid-layout
 *   @e2e admin-templates::template-placements-include-tile-data
 *   @e2e admin-templates::user-added-to-a-target-group-after-template-creation
 *   @e2e admin-templates::user-removed-from-a-target-group-after-receiving-template
 *   @e2e admin-templates::template-targets-non-existent-group
 *   @e2e admin-templates::personal-dashboards-off
 *   @e2e admin-templates::the-default-template-replaces-the-dashboard-for-everyone
 *   @e2e admin-templates::people-already-using-launchpad-are-left-alone
 *   @e2e admin-templates::the-oldest-hand-made-template-wins
 *   @e2e admin-templates::after-a-forced-install-the-recorded-copy-wins
 *   @e2e admin-templates::a-shipped-template-goes-before-a-hand-made-one
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext, Browser } from '@playwright/test'
import type { SeedWidget, TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	activeDashboard,
	copiesOf,
	createTemplate,
	deleteTemplates,
	getTemplate,
	importTemplate,
	placementsOf,
	readAllowUserDashboards,
	readAppConfig,
	rememberDefaultTemplate,
	restoreDefaultTemplate,
	setAllowUserDashboards,
	sortedLayout,
	textWidget,
	visibleDashboards,
	writeAppConfig,
} from './support/adminTemplates.ts'
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
const STAMP = `E2E Dist ${RUN}`
const SHIPPED_KEYS = [
	'shipped_template_mijn-werkdag',
	'shipped_template_version_mijn-werkdag',
]

let admin: APIRequestContext
let allowBefore: boolean | null = null
let defaultBefore: number | null = null
const shippedBefore: Record<string, string> = {}
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	defaultBefore = await rememberDefaultTemplate(admin)
	for (const key of SHIPPED_KEYS) {
		shippedBefore[key] = await readAppConfig(admin, key)
	}
	// A member receives a COPY only with personal dashboards on; the one
	// scenario about them off switches it itself.
	await setAllowUserDashboards(admin, true)
})

test.afterAll(async () => {
	await deleteTemplates(admin, templates)
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	for (const g of groups) {
		await removeGroup(admin, g)
	}
	await restoreDefaultTemplate(admin, defaultBefore)
	for (const key of SHIPPED_KEYS) {
		await writeAppConfig(admin, key, shippedBefore[key] ?? '').catch(
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
	const id = await createGroup(admin, `e2e-tdist-${tag}-${RUN}`)
	groups.push(id)
	return id
}

/** A throwaway account, optionally in groups. */
async function member(tag: string, inGroups: string[] = []): Promise<Principal> {
	const p = await createPrincipal(admin, `e2e-tdist-${tag}`)
	principals.push(p)
	for (const g of inGroups) {
		await addToGroup(admin, p.username, g)
	}
	return p
}

/** Import a template this file owns. */
async function seedTemplate(
	name: string,
	options: Parameters<typeof importTemplate>[2] = {},
): Promise<TemplateRow> {
	const row = await importTemplate(admin, `${STAMP} ${name}`, options)
	templates.push(row.id)
	return row
}

/** Create a widget-less template this file owns. */
async function createOwned(data: Record<string, unknown>): Promise<TemplateRow> {
	const row = await createTemplate(admin, data)
	templates.push(row.id)
	return row
}

/** A force install of mijn-werkdag this file owns. */
async function forceInstallShipped(targetGroups: string[]): Promise<number> {
	const res = await admin.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{
			data: { force: true },
		},
	)
	expect(res.status(), `install answered ${res.status()}`).toBe(201)
	const id = (await res.json()).id as number
	templates.push(id)
	const put = await admin.put(`${API}/admin/templates/${id}`, {
		data: { targetGroups },
	})
	expect(put.status()).toBe(200)
	return id
}

/**
 * The admin adds a widget to a template through the widget API, as
 * REQ-TMPL-007 describes. Asserted, because the scenarios below depend on it.
 */
async function adminAddsWidgetToTemplate(
	templateId: number,
	widget: SeedWidget,
): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${templateId}/widgets`, {
		data: widget,
	})
	expect(
		res.status(),
		`adding a widget to the template answered ${res.status()}`,
	).toBe(201)
	const body = await res.json()
	return Number(body.id ?? body.placement?.id)
}

/** Log in, open LaunchPad, and wait for the text to show. */
async function openAndSee(
	browser: Browser,
	who: Principal,
	text: string,
): Promise<void> {
	const { context, page } = await loginAs(browser, who.username, who.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.getByText(text, { exact: true }).first()).toBeVisible({
			timeout: 45_000,
		})
		await expect(page.getByText('No dashboards available')).toHaveCount(0)
	} finally {
		await context.close()
	}
}

function FIVE_WIDGETS(tag: string): SeedWidget[] {
	return [
		textWidget(`${tag} c1`, { x: 0, y: 0 }, { isCompulsory: 1 }),
		textWidget(`${tag} c2`, { x: 4, y: 0 }, { isCompulsory: 1, gridWidth: 8 }),
		textWidget(`${tag} c3`, { x: 0, y: 2 }, { isCompulsory: 1, gridHeight: 3 }),
		textWidget(`${tag} f1`, { x: 4, y: 2 }),
		textWidget(`${tag} f2`, { x: 8, y: 2 }),
	]
}

test.describe('first access (REQ-TMPL-005)', () => {
	// @e2e admin-templates::first-time-user-receives-default-template
	test('a new account receives a copy of the default template with every placement', async () => {
		const company = await seedTemplate('Company', {
			widgets: FIVE_WIDGETS(`${STAMP} company`),
			permissionLevel: 'add_only',
		})
		expect(
			(
				await admin.put(`${API}/admin/templates/${company.id}`, {
					data: { isDefault: true },
				})
			).status(),
		).toBe(200)
		try {
			const alice = await member('default')
			const first = await activeDashboard(alice.api)
			expect(first.status).toBe(200)
			expect(first.dashboard.type).toBe('user')
			expect(first.dashboard.userId).toBe(alice.username)
			expect(first.dashboard.basedOnTemplate).toBe(company.id)
			expect(first.dashboard.permissionLevel).toBe('add_only')
			expect(first.permissionLevel).toBe('add_only')

			const template = await getTemplate(admin, company.id)
			expect(first.placements).toHaveLength(5)
			expect(sortedLayout(first.placements)).toEqual(
				sortedLayout(template.placements),
			)
			expect(
				first.placements.filter((p) => p.isCompulsory === 1),
			).toHaveLength(3)

			// Set as the active dashboard: the next resolution lands on it.
			const again = await activeDashboard(alice.api)
			expect(again.dashboard.id).toBe(first.dashboard.id)
		} finally {
			await admin.put(`${API}/admin/templates/${company.id}`, {
				data: { isDefault: false },
			})
		}
	})

	// @e2e admin-templates::first-time-user-receives-group-targeted-template
	test('a member of the targeted group receives a copy of that template', async () => {
		const marketing = await group('marketing')
		const tpl = await seedTemplate('Marketing', {
			widgets: [textWidget(`${STAMP} mkt`, { x: 0, y: 0 })],
			targetGroups: [marketing],
		})
		const bob = await member('bob', [marketing])
		const first = await activeDashboard(bob.api)
		expect(first.status).toBe(200)
		expect(first.dashboard.basedOnTemplate).toBe(tpl.id)
		expect(first.dashboard.userId).toBe(bob.username)
		expect(first.placements.map((p) => p.customTitle)).toEqual([`${STAMP} mkt`])
	})

	// @e2e admin-templates::template-already-distributed-to-user
	test('a second access finds the existing copy and makes no duplicate', async () => {
		const g = await group('again')
		const tpl = await seedTemplate('Again', {
			widgets: [textWidget(`${STAMP} again`, { x: 0, y: 0 })],
			targetGroups: [g],
		})
		const alice = await member('again', [g])
		const first = await activeDashboard(alice.api)
		expect(first.dashboard.basedOnTemplate).toBe(tpl.id)
		const second = await activeDashboard(alice.api)
		expect(second.status).toBe(200)
		expect(second.dashboard.id).toBe(first.dashboard.id)
		expect(await copiesOf(alice.api, tpl.id)).toHaveLength(1)
	})

	// @e2e admin-templates::multiple-templates-match-the-user
	test('a group-targeted template goes before the default template', async () => {
		const marketing = await group('multi')
		const company = await seedTemplate('Multi Company', {
			widgets: [textWidget(`${STAMP} multi company`, { x: 0, y: 0 })],
		})
		await admin.put(`${API}/admin/templates/${company.id}`, {
			data: { isDefault: true },
		})
		try {
			const tpl = await seedTemplate('Multi Marketing', {
				widgets: [textWidget(`${STAMP} multi mkt`, { x: 0, y: 0 })],
				targetGroups: [marketing],
			})
			const alice = await member('multi', [marketing])
			const first = await activeDashboard(alice.api)
			expect(first.dashboard.basedOnTemplate).toBe(tpl.id)
			expect(await copiesOf(alice.api, company.id)).toHaveLength(0)
			expect(await copiesOf(alice.api, tpl.id)).toHaveLength(1)
		} finally {
			await admin.put(`${API}/admin/templates/${company.id}`, {
				data: { isDefault: false },
			})
		}
	})
})

test.describe('updating and deleting after distribution (REQ-TMPL-003, -004, -006)', () => {
	// @e2e admin-templates::update-template-target-groups
	test('a newly targeted group receives the template and existing copies are untouched', async () => {
		const marketing = await group('upd-mkt')
		const sales = await group('upd-sales')
		const tpl = await seedTemplate('Retarget', {
			widgets: [textWidget(`${STAMP} retarget`, { x: 0, y: 0 })],
			targetGroups: [marketing],
		})
		const m = await member('upd-m', [marketing])
		const mCopy = await activeDashboard(m.api)
		expect(mCopy.dashboard.basedOnTemplate).toBe(tpl.id)
		const mBefore = await placementsOf(m.api, mCopy.dashboard.id)

		const res = await admin.put(`${API}/admin/templates/${tpl.id}`, {
			data: { targetGroups: [marketing, sales] },
		})
		expect(res.status()).toBe(200)
		expect((await getTemplate(admin, tpl.id)).template.targetGroups).toEqual([
			marketing,
			sales,
		])

		const s = await member('upd-s', [sales])
		const sCopy = await activeDashboard(s.api)
		expect(sCopy.dashboard.basedOnTemplate).toBe(tpl.id)

		const mAfter = await activeDashboard(m.api)
		expect(mAfter.dashboard.id).toBe(mCopy.dashboard.id)
		expect(await placementsOf(m.api, mCopy.dashboard.id)).toEqual(mBefore)
		expect(await copiesOf(m.api, tpl.id)).toHaveLength(1)
	})

	// @e2e admin-templates::update-template-permission-level
	test("a copy follows the template's new permission level at once", async () => {
		const g = await group('level')
		const tpl = await seedTemplate('Level', {
			widgets: [textWidget(`${STAMP} level`, { x: 0, y: 0 })],
			targetGroups: [g],
			permissionLevel: 'add_only',
		})
		const alice = await member('level', [g])
		expect((await activeDashboard(alice.api)).permissionLevel).toBe('add_only')

		const res = await admin.put(`${API}/admin/templates/${tpl.id}`, {
			data: { permissionLevel: 'full' },
		})
		expect(res.status()).toBe(200)
		expect((await getTemplate(admin, tpl.id)).template.permissionLevel).toBe(
			'full',
		)

		const after = await activeDashboard(alice.api)
		expect(after.dashboard.basedOnTemplate).toBe(tpl.id)
		expect(after.permissionLevel).toBe('full')
	})

	// @e2e admin-templates::delete-a-template-with-existing-user-copies
	// @e2e admin-templates::admin-deletes-template-after-distribution
	test('deleting a template leaves the copies working with their own level', async () => {
		const g = await group('del')
		const tpl = await seedTemplate('Deleted later', {
			widgets: FIVE_WIDGETS(`${STAMP} del`),
			targetGroups: [g],
			permissionLevel: 'add_only',
		})
		const alice = await member('del-a', [g])
		const bob = await member('del-b', [g])
		const aCopy = await activeDashboard(alice.api)
		const bCopy = await activeDashboard(bob.api)
		expect(aCopy.dashboard.basedOnTemplate).toBe(tpl.id)
		expect(bCopy.dashboard.basedOnTemplate).toBe(tpl.id)
		const aBefore = sortedLayout(aCopy.placements)

		// The template's level now differs from the copies' own (add_only),
		// so the fallback after the delete is observable.
		await admin.put(`${API}/admin/templates/${tpl.id}`, {
			data: { permissionLevel: 'full' },
		})
		expect((await activeDashboard(alice.api)).permissionLevel).toBe('full')

		const res = await admin.delete(`${API}/admin/templates/${tpl.id}`)
		expect(res.status()).toBe(200)

		for (const [who, copy] of [
			[alice, aCopy],
			[bob, bCopy],
		] as const) {
			const after = await activeDashboard(who.api)
			expect(after.status).toBe(200)
			expect(after.dashboard.id).toBe(copy.dashboard.id)
			expect(after.placements).toHaveLength(5)
			expect(after.permissionLevel).toBe('add_only')
		}
		expect(sortedLayout((await activeDashboard(alice.api)).placements)).toEqual(
			aBefore,
		)
	})

	// @e2e admin-templates::user-modifies-their-template-copy
	test("a member's change to their copy touches neither the template nor another copy", async () => {
		const g = await group('modify')
		const tpl = await seedTemplate('Modify', {
			widgets: [textWidget(`${STAMP} modify`, { x: 0, y: 0 })],
			targetGroups: [g],
			permissionLevel: 'add_only',
		})
		const alice = await member('mod-a', [g])
		const bob = await member('mod-b', [g])
		const aCopy = await activeDashboard(alice.api)
		const bCopy = await activeDashboard(bob.api)
		const templateBefore = sortedLayout(
			(await getTemplate(admin, tpl.id)).placements,
		)
		const bobBefore = await placementsOf(bob.api, bCopy.dashboard.id)

		const added = await alice.api.post(
			`${API}/dashboard/${aCopy.dashboard.id}/widgets`,
			{
				data: textWidget(`${STAMP} alice own`, { x: 0, y: 4 }),
			},
		)
		expect(added.status()).toBe(201)
		expect(
			(await placementsOf(alice.api, aCopy.dashboard.id)).map(
				(p) => p.customTitle,
			),
		).toContain(`${STAMP} alice own`)

		expect(sortedLayout((await getTemplate(admin, tpl.id)).placements)).toEqual(
			templateBefore,
		)
		expect(await placementsOf(bob.api, bCopy.dashboard.id)).toEqual(bobBefore)
	})

	// @e2e admin-templates::admin-updates-template-after-distribution
	// Blocked by the product: an admin template has no owner (userId null),
	// and PermissionService::canAddWidget resolves access by ownership or
	// share only (lib/Service/PermissionService.php:214-225, 521-530), so
	// `POST /api/dashboard/{templateId}/widgets` answers 403 for the admin.
	test.fixme('a widget the admin adds later reaches new copies only', async () => {
		const g = await group('later')
		const tpl = await seedTemplate('Later', {
			widgets: [textWidget(`${STAMP} later`, { x: 0, y: 0 })],
			targetGroups: [g],
		})
		const early = await member('later-early', [g])
		const earlyCopy = await activeDashboard(early.api)

		await adminAddsWidgetToTemplate(
			tpl.id,
			textWidget(`${STAMP} later new`, { x: 4, y: 0 }),
		)

		const titles = (await placementsOf(early.api, earlyCopy.dashboard.id)).map(
			(p) => p.customTitle,
		)
		expect(titles).not.toContain(`${STAMP} later new`)
		const late = await member('later-late', [g])
		expect(
			(await activeDashboard(late.api)).placements.map((p) => p.customTitle),
		).toContain(`${STAMP} later new`)
	})

	// @e2e admin-templates::update-template-widget-layout
	// Blocked by the same 403 (lib/Service/PermissionService.php:214-251).
	test.fixme('adding and repositioning template widgets leaves existing copies as they were', async () => {
		const g = await group('layout')
		const tpl = await seedTemplate('Layout', {
			widgets: [0, 1, 2, 3].map((i) =>
				textWidget(`${STAMP} layout ${i}`, { x: i * 3, y: 0 }),
			),
			targetGroups: [g],
		})
		const alice = await member('layout', [g])
		const copy = await activeDashboard(alice.api)
		const copyBefore = sortedLayout(copy.placements)

		await adminAddsWidgetToTemplate(
			tpl.id,
			textWidget(`${STAMP} layout new`, { x: 0, y: 4 }),
		)
		const first = (await getTemplate(admin, tpl.id)).placements[0]
		const moved = await admin.put(`${API}/widgets/${first.id}`, {
			data: { gridY: 6 },
		})
		expect(moved.status()).toBe(200)

		const template = await getTemplate(admin, tpl.id)
		expect(template.placements).toHaveLength(5)
		expect(template.placements.find((p) => p.id === first.id)?.gridY).toBe(6)
		expect(
			sortedLayout(await placementsOf(alice.api, copy.dashboard.id)),
		).toEqual(copyBefore)
	})
})

test.describe('template widgets (REQ-TMPL-007)', () => {
	// @e2e admin-templates::add-widget-to-template
	// Blocked by the product: lib/Service/PermissionService.php:214-225 gives
	// the admin no access level on an owner-less template, so the widget API
	// answers 403; WidgetService::addWidget also never reads `isCompulsory`.
	test.fixme('the admin adds a compulsory widget to a template', async () => {
		const tpl = await createOwned({ name: `${STAMP} Add widget` })
		const res = await admin.post(`${API}/dashboard/${tpl.id}/widgets`, {
			data: {
				...textWidget(`${STAMP} added`, { x: 0, y: 0 }),
				isCompulsory: 1,
			},
		})
		expect(res.status()).toBe(201)
		const placements = (await getTemplate(admin, tpl.id)).placements
		expect(placements).toHaveLength(1)
		expect(placements[0].dashboardId).toBe(tpl.id)
		expect(placements[0].isCompulsory).toBe(1)
	})

	// @e2e admin-templates::remove-widget-from-template
	// Blocked by the product: canRemoveWidget resolves no level for the admin
	// on an owner-less template (lib/Service/PermissionService.php:237-251).
	test.fixme('the admin removes a widget from a template and copies keep theirs', async () => {
		const g = await group('remove')
		const tpl = await seedTemplate('Remove widget', {
			widgets: [
				textWidget(`${STAMP} rm keep`, { x: 0, y: 0 }),
				textWidget(`${STAMP} rm gone`, { x: 4, y: 0 }),
			],
			targetGroups: [g],
		})
		const alice = await member('remove', [g])
		const copy = await activeDashboard(alice.api)
		const gone = (await getTemplate(admin, tpl.id)).placements.find(
			(p) => p.customTitle === `${STAMP} rm gone`,
		)!

		const res = await admin.delete(`${API}/widgets/${gone.id}`)
		expect(res.status()).toBe(200)
		expect(
			(await getTemplate(admin, tpl.id)).placements.map((p) => p.id),
		).not.toContain(gone.id)
		expect(
			(await placementsOf(alice.api, copy.dashboard.id)).map(
				(p) => p.customTitle,
			),
		).toContain(`${STAMP} rm gone`)
	})

	// @e2e admin-templates::configure-template-grid-layout
	// Blocked by the product: an admin template cannot be opened on the grid
	// (no page lists or opens an `admin_template`; grep src/ for it), and the
	// placement API refuses the admin (lib/Service/PermissionService.php:214-251).
	test.fixme('positions the admin arranges are what new copies receive', async () => {
		const g = await group('grid')
		const tpl = await createOwned({ name: `${STAMP} Grid`, targetGroups: [g] })
		const id = await adminAddsWidgetToTemplate(
			tpl.id,
			textWidget(`${STAMP} grid`, { x: 0, y: 0 }),
		)
		const moved = await admin.put(`${API}/widgets/${id}`, {
			data: { gridX: 6, gridY: 3, gridWidth: 6, gridHeight: 3 },
		})
		expect(moved.status()).toBe(200)

		const alice = await member('grid', [g])
		const copy = await activeDashboard(alice.api)
		expect(
			copy.placements.map((p) => [
				p.gridX,
				p.gridY,
				p.gridWidth,
				p.gridHeight,
			]),
		).toEqual([[6, 3, 6, 3]])
	})

	// @e2e admin-templates::template-placements-include-tile-data
	test('a tile placement is cloned with its inline tile data and renders the same', async ({
		browser,
	}) => {
		const g = await group('tile')
		const tileTitle = `${STAMP} Tegel`
		const tile: SeedWidget = {
			widgetId: 'tile',
			gridX: 0,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
			tileType: 'custom',
			tileTitle,
			tileIcon: 'Star',
			tileIconType: 'mdi',
			tileBackgroundColor: '#123456',
			tileTextColor: '#ffffff',
			tileLinkType: 'url',
			tileLinkValue: 'https://example.org/e2e-tile',
		}
		const tpl = await seedTemplate('Tile', {
			widgets: [tile],
			targetGroups: [g],
		})
		const source = (await getTemplate(admin, tpl.id)).placements[0]
		expect(source.tileTitle).toBe(tileTitle)

		const alice = await member('tile', [g])
		const copy = await activeDashboard(alice.api)
		expect(copy.dashboard.basedOnTemplate).toBe(tpl.id)
		expect(copy.placements).toHaveLength(1)
		const cloned = copy.placements[0]
		for (const field of [
			'tileType',
			'tileTitle',
			'tileIcon',
			'tileIconType',
			'tileBackgroundColor',
			'tileTextColor',
			'tileLinkType',
			'tileLinkValue',
		] as const) {
			expect(cloned[field], field).toBe(source[field])
		}

		const { context, page } = await loginAs(
			browser,
			alice.username,
			alice.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			const rendered = page.locator(`[data-placement-id="${cloned.id}"]`)
			await expect(rendered).toBeVisible({ timeout: 45_000 })
			await expect(rendered.getByText(tileTitle)).toBeVisible()
			await expect(
				rendered.locator('a[href="https://example.org/e2e-tile"]').first(),
			).toHaveCount(1)
		} finally {
			await context.close()
		}
	})
})

test.describe('group membership at access time (REQ-TMPL-010)', () => {
	// @e2e admin-templates::user-added-to-a-target-group-after-template-creation
	test('a user who joins the group after the template was made receives it', async () => {
		const g = await group('joined')
		const tpl = await seedTemplate('Joined', {
			widgets: [textWidget(`${STAMP} joined`, { x: 0, y: 0 })],
			targetGroups: [g],
		})
		const alice = await member('joined')
		await addToGroup(admin, alice.username, g)

		const first = await activeDashboard(alice.api)
		expect(first.dashboard.basedOnTemplate).toBe(tpl.id)
	})

	// @e2e admin-templates::user-removed-from-a-target-group-after-receiving-template
	test('leaving the group does not revoke the copy', async () => {
		const g = await group('left')
		const tpl = await seedTemplate('Left', {
			widgets: [textWidget(`${STAMP} left`, { x: 0, y: 0 })],
			targetGroups: [g],
		})
		const alice = await member('left', [g])
		const copy = await activeDashboard(alice.api)
		expect(copy.dashboard.basedOnTemplate).toBe(tpl.id)

		const removed = await admin.delete(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(alice.username)}/groups`,
			{ form: { groupid: g } },
		)
		expect(removed.ok()).toBe(true)

		const after = await activeDashboard(alice.api)
		expect(after.status).toBe(200)
		expect(after.dashboard.id).toBe(copy.dashboard.id)
		expect(after.placements.map((p) => p.customTitle)).toEqual([`${STAMP} left`])
		expect(await copiesOf(alice.api, tpl.id)).toHaveLength(1)
	})

	// @e2e admin-templates::template-targets-non-existent-group
	test('a template for a group that does not exist reaches nobody and breaks nothing', async () => {
		const tpl = await seedTemplate('Nowhere', {
			widgets: [textWidget(`${STAMP} nowhere`, { x: 0, y: 0 })],
			targetGroups: [`e2e-no-such-group-${RUN}`],
		})
		const someone = await member('nowhere', [await group('nowhere-own')])
		const first = await activeDashboard(someone.api)
		expect(first.status).toBe(200)
		expect(first.dashboard.basedOnTemplate ?? null).not.toBe(tpl.id)
		expect(await copiesOf(someone.api, tpl.id)).toHaveLength(0)
	})
})

test.describe('what the page shows on a first visit (REQ-TMPL-019)', () => {
	// @e2e admin-templates::personal-dashboards-off
	test('with personal dashboards off the member sees the template itself, view only', async ({
		browser,
	}) => {
		const g = await group('off')
		const text = `${STAMP} view only`
		const tpl = await seedTemplate('Off', {
			widgets: [textWidget(text, { x: 0, y: 0 })],
			targetGroups: [g],
		})
		const sanne = await member('off', [g])
		await setAllowUserDashboards(admin, false)
		try {
			await openAndSee(browser, sanne, text)

			const shown = await activeDashboard(sanne.api)
			expect(shown.status).toBe(200)
			expect(shown.dashboard.id).toBe(tpl.id)
			expect(shown.dashboard.type).toBe('admin_template')
			expect(shown.permissionLevel).toBe('view_only')

			const own = await (await sanne.api.get(`${API}/dashboards`)).json()
			expect((own.items ?? own) as unknown[]).toEqual([])
			expect(await copiesOf(sanne.api, tpl.id)).toHaveLength(0)
		} finally {
			await setAllowUserDashboards(admin, true)
		}
	})

	// @e2e admin-templates::the-default-template-replaces-the-dashboard-for-everyone
	test('the default template wins over the seeded dashboard for everyone', async ({
		browser,
	}) => {
		const everyone = (await visibleDashboards(admin)).find(
			(d) => d.type === 'group_shared' && d.groupId === 'default',
		)
		expect(
			everyone,
			'the instance must have the dashboard for everyone',
		).toBeDefined()

		const text = `${STAMP} default tpl`
		const tpl = await seedTemplate('Default', {
			widgets: [textWidget(text, { x: 0, y: 0 })],
		})
		await admin.put(`${API}/admin/templates/${tpl.id}`, {
			data: { isDefault: true },
		})
		try {
			const mark = await member('mark')
			await openAndSee(browser, mark, text)
			const shown = await activeDashboard(mark.api)
			expect(shown.dashboard.basedOnTemplate).toBe(tpl.id)
			expect(shown.dashboard.id).not.toBe(everyone!.id)
		} finally {
			await admin.put(`${API}/admin/templates/${tpl.id}`, {
				data: { isDefault: false },
			})
		}
	})

	// @e2e admin-templates::people-already-using-launchpad-are-left-alone
	test('an owner of a dashboard and a user with a saved choice are not moved', async ({
		browser,
	}) => {
		const g = await group('alone')
		const everyone = (await visibleDashboards(admin)).find(
			(d) => d.type === 'group_shared' && d.groupId === 'default',
		)
		expect(
			everyone,
			'the instance must have the dashboard for everyone',
		).toBeDefined()

		const pieter = await member('pieter', [g])
		const own = await pieter.api.post(`${API}/dashboard`, {
			data: { name: `${STAMP} Pieter own` },
		})
		expect(own.status()).toBeLessThan(300)
		const pieterOwn = Number((await own.json()).dashboard.id)

		const lotte = await member('lotte', [g])
		const pinned = await lotte.api.post(`${API}/dashboards/active`, {
			data: { uuid: everyone!.uuid },
		})
		expect(pinned.status()).toBe(200)

		const text = `${STAMP} not for them`
		const tpl = await seedTemplate('Alone', {
			widgets: [textWidget(text, { x: 0, y: 0 })],
			targetGroups: [g],
		})

		for (const [who, expected] of [
			[pieter, pieterOwn],
			[lotte, everyone!.id],
		] as const) {
			const { context, page } = await loginAs(
				browser,
				who.username,
				who.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad/')
				await page.waitForSelector(
					'.workspace-shell, .launchpad-floating-controls',
					{ timeout: 45_000 },
				)
				await expect(page.getByText(text, { exact: true })).toHaveCount(0)
			} finally {
				await context.close()
			}
			const shown = await activeDashboard(who.api)
			expect(shown.dashboard.id).toBe(expected)
			expect(await copiesOf(who.api, tpl.id)).toHaveLength(0)
		}
	})
})

test.describe('one rule when several templates target a group (REQ-TMPL-021)', () => {
	// @e2e admin-templates::the-oldest-hand-made-template-wins
	test('of two hand-made templates the lowest id wins', async () => {
		const g = await group('oldest')
		const zaken = await createOwned({
			name: `${STAMP} Zaken`,
			targetGroups: [g],
		})
		const aanvragen = await createOwned({
			name: `${STAMP} Aanvragen`,
			targetGroups: [g],
		})
		expect(zaken.id).toBeLessThan(aanvragen.id)

		const pieter = await member('oldest', [g])
		expect((await activeDashboard(pieter.api)).dashboard.basedOnTemplate).toBe(
			zaken.id,
		)
	})

	// @e2e admin-templates::after-a-forced-install-the-recorded-copy-wins
	test('after a forced install the recorded copy is handed out, not the earlier one', async () => {
		const g = await group('forced')
		const earlier = await forceInstallShipped([g])
		const recorded = await forceInstallShipped([g])
		expect(earlier).toBeLessThan(recorded)
		const recordedUuid = (await getTemplate(admin, recorded)).template.uuid
		expect(await readAppConfig(admin, 'shipped_template_mijn-werkdag')).toBe(
			recordedUuid,
		)

		const newcomer = await member('forced', [g])
		expect((await activeDashboard(newcomer.api)).dashboard.basedOnTemplate).toBe(
			recorded,
		)
	})

	// @e2e admin-templates::a-shipped-template-goes-before-a-hand-made-one
	test('the installed shipped template goes before an older hand-made one', async () => {
		const g = await group('shipped-first')
		const afdeling = await createOwned({
			name: `${STAMP} Afdeling`,
			targetGroups: [g],
		})
		const shipped = await forceInstallShipped([g])
		expect(afdeling.id).toBeLessThan(shipped)

		const pieter = await member('shipped-first', [g])
		const first = await activeDashboard(pieter.api)
		expect(first.dashboard.basedOnTemplate).toBe(shipped)
		expect(first.dashboard.name).toBe('Mijn werkdag')
	})
})
