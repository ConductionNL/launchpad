/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin changes a template's widgets through the widget API
 * (admin-templates REQ-TMPL-003, -006, -007, REQ-RESYNC-003): adds one,
 * removes one, moves one, and what that does to the copies already handed
 * out and to the ones handed out after.
 *
 * An admin template belongs to nobody (`userId` null), so a Nextcloud
 * admin's right to change its widgets comes from REQ-PERM-011, not from
 * ownership or a share (PermissionService::canAddWidget / canRemoveWidget /
 * canStyleWidget), and only that admin may mark a new template widget
 * compulsory (PermissionService::mayPlaceCompulsory).
 *
 * The admin's widget is added the way the workspace adds one: the create
 * takes no title, so the title is set with a follow-up `PUT` (the
 * addTitledTextWidget helper), and the widget is found again by that title.
 *
 * They sit in their own file, without serial mode, so a red here cannot skip
 * the serial suites in admin-templates-distribution.spec.ts and
 * admin-templates-resync.spec.ts.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::add-widget-to-template
 *   @e2e admin-templates::remove-widget-from-template
 *   @e2e admin-templates::update-template-widget-layout
 *   @e2e admin-templates::admin-updates-template-after-distribution
 *   @e2e admin-templates::template-widget-removed-by-admin-is-removed-under-merge
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type {
	PlacementRow,
	SeedWidget,
	TemplateRow,
} from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	activeDashboard,
	addTitledTextWidget,
	createTemplate,
	deleteTemplates,
	getTemplate,
	importTemplate,
	placementsOf,
	readAllowUserDashboards,
	setAllowUserDashboards,
	sortedLayout,
	textWidget,
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
const STAMP = `E2E TmplWidgets ${RUN}`

let admin: APIRequestContext
let allowBefore: boolean | null = null
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	// A member receives a COPY only with personal dashboards on.
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
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

/** A throwaway group this file owns. */
async function group(tag: string): Promise<string> {
	const id = await createGroup(admin, `e2e-twid-${tag}-${RUN}`)
	groups.push(id)
	return id
}

/** A throwaway account in the given groups. */
async function member(tag: string, inGroups: string[]): Promise<Principal> {
	const p = await createPrincipal(admin, `e2e-twid-${tag}`)
	principals.push(p)
	for (const g of inGroups) {
		await addToGroup(admin, p.username, g)
	}
	return p
}

/** Import a template with widgets this file owns. */
async function seedTemplate(
	name: string,
	widgets: SeedWidget[],
	targetGroups: string[],
): Promise<TemplateRow> {
	const row = await importTemplate(admin, `${STAMP} ${name}`, {
		widgets,
		targetGroups,
	})
	templates.push(row.id)
	return row
}

/** The template placement carrying this title. */
function byTitle(placements: PlacementRow[], title: string): PlacementRow {
	const found = placements.find((p) => p.customTitle === title)
	expect(found, `a template placement titled "${title}"`).toBeDefined()
	return found!
}

/** The admin adds a titled text widget to the template; asserted. */
async function adminAddsWidget(
	templateId: number,
	title: string,
	at: { x: number; y: number },
): Promise<PlacementRow> {
	expect(
		await addTitledTextWidget(admin, templateId, title, at),
		'the admin adds a widget to the template and titles it',
	).toEqual({ created: 201, titled: 200 })
	return byTitle((await getTemplate(admin, templateId)).placements, title)
}

/** The titles on a member's copy. */
async function titlesOn(who: Principal, dashboardId: number): Promise<unknown[]> {
	return (await placementsOf(who.api, dashboardId)).map((p) => p.customTitle)
}

test.describe("the admin edits a template's widgets (REQ-TMPL-007)", () => {
	// @e2e admin-templates::add-widget-to-template
	test('the admin adds a compulsory widget to a template', async () => {
		const tpl = await createTemplate(admin, { name: `${STAMP} Add widget` })
		templates.push(tpl.id)

		const res = await admin.post(`${API}/dashboard/${tpl.id}/widgets`, {
			data: {
				...textWidget(`${STAMP} added`, { x: 0, y: 0 }),
				isCompulsory: 1,
			},
		})
		expect(res.status(), await res.text()).toBe(201)

		const placements = (await getTemplate(admin, tpl.id)).placements
		expect(placements).toHaveLength(1)
		expect(placements[0].dashboardId).toBe(tpl.id)
		expect(placements[0].widgetId).toBe('text')
		expect(placements[0].isCompulsory).toBe(1)
	})

	// @e2e admin-templates::remove-widget-from-template
	test('the admin removes a widget from a template and copies keep theirs', async () => {
		const g = await group('remove')
		const keep = `${STAMP} rm keep`
		const gone = `${STAMP} rm gone`
		const tpl = await seedTemplate(
			'Remove widget',
			[textWidget(keep, { x: 0, y: 0 }), textWidget(gone, { x: 4, y: 0 })],
			[g],
		)
		const alice = await member('remove', [g])
		const copy = await activeDashboard(alice.api)
		expect(copy.dashboard.basedOnTemplate).toBe(tpl.id)
		const copyBefore = sortedLayout(copy.placements)
		const target = byTitle((await getTemplate(admin, tpl.id)).placements, gone)

		const res = await admin.delete(`${API}/widgets/${target.id}`)
		expect(res.status(), await res.text()).toBe(200)

		const after = (await getTemplate(admin, tpl.id)).placements
		expect(after.map((p) => p.id)).not.toContain(target.id)
		expect(after.map((p) => p.customTitle)).toEqual([keep])
		// The copy made before is untouched.
		expect(
			sortedLayout(await placementsOf(alice.api, copy.dashboard.id)),
		).toEqual(copyBefore)
		expect(await titlesOn(alice, copy.dashboard.id)).toContain(gone)
	})
})

test.describe('changing a template after distribution (REQ-TMPL-003, -006)', () => {
	// @e2e admin-templates::update-template-widget-layout
	test('adding and repositioning template widgets leaves existing copies as they were', async () => {
		const g = await group('layout')
		const titles = [0, 1, 2, 3].map((i) => `${STAMP} layout ${i}`)
		const tpl = await seedTemplate(
			'Layout',
			titles.map((t, i) =>
				textWidget(t, { x: (i % 3) * 4, y: Math.floor(i / 3) * 2 }),
			),
			[g],
		)
		expect((await getTemplate(admin, tpl.id)).placements).toHaveLength(4)
		const alice = await member('layout', [g])
		const copy = await activeDashboard(alice.api)
		expect(copy.dashboard.basedOnTemplate).toBe(tpl.id)
		const copyBefore = sortedLayout(copy.placements)

		await adminAddsWidget(tpl.id, `${STAMP} layout new`, { x: 0, y: 6 })
		const first = byTitle(
			(await getTemplate(admin, tpl.id)).placements,
			titles[0],
		)
		const moved = await admin.put(`${API}/widgets/${first.id}`, {
			data: { gridY: 9, version: first.version },
		})
		expect(moved.status(), await moved.text()).toBe(200)

		const template = (await getTemplate(admin, tpl.id)).placements
		expect(template).toHaveLength(5)
		expect(byTitle(template, titles[0]).gridY).toBe(9)
		expect(template.map((p) => p.customTitle)).toContain(`${STAMP} layout new`)
		expect(
			sortedLayout(await placementsOf(alice.api, copy.dashboard.id)),
		).toEqual(copyBefore)
	})

	// @e2e admin-templates::admin-updates-template-after-distribution
	test('a widget the admin adds later reaches new copies only', async () => {
		const g = await group('later')
		const tpl = await seedTemplate(
			'Later',
			[textWidget(`${STAMP} later`, { x: 0, y: 0 })],
			[g],
		)
		const early = await member('later-early', [g])
		const earlyCopy = await activeDashboard(early.api)
		expect(earlyCopy.dashboard.basedOnTemplate).toBe(tpl.id)

		const added = `${STAMP} later new`
		await adminAddsWidget(tpl.id, added, { x: 4, y: 0 })

		expect(await titlesOn(early, earlyCopy.dashboard.id)).not.toContain(added)
		const late = await member('later-late', [g])
		const lateCopy = await activeDashboard(late.api)
		expect(lateCopy.dashboard.basedOnTemplate).toBe(tpl.id)
		expect(lateCopy.placements.map((p) => p.customTitle)).toContain(added)
	})
})

test.describe('re-sync after the admin deleted a template widget (REQ-RESYNC-003)', () => {
	// @e2e admin-templates::template-widget-removed-by-admin-is-removed-under-merge
	test('a widget the admin deleted from the template leaves the copies, own widgets stay', async () => {
		const g = await group('resync')
		const links = `${STAMP} Links`
		const tpl = await seedTemplate(
			'Resync removed',
			[
				textWidget(`${STAMP} resync one`, { x: 0, y: 0 }),
				textWidget(links, { x: 4, y: 0 }),
			],
			[g],
		)
		const copies: Array<{ who: Principal; dashboardId: number; own: string }> =
			[]
		for (const tag of ['resync-a', 'resync-b']) {
			const who = await member(tag, [g])
			const copy = await activeDashboard(who.api)
			expect(copy.dashboard.basedOnTemplate).toBe(tpl.id)
			expect(copy.placements.map((p) => p.customTitle)).toContain(links)
			const own = `${STAMP} own ${who.username}`
			expect(
				await addTitledTextWidget(who.api, copy.dashboard.id, own, {
					x: 0,
					y: 6,
				}),
			).toEqual({ created: 201, titled: 200 })
			copies.push({ who, dashboardId: copy.dashboard.id, own })
		}

		const link = byTitle((await getTemplate(admin, tpl.id)).placements, links)
		const removed = await admin.delete(`${API}/widgets/${link.id}`)
		expect(removed.status(), await removed.text()).toBe(200)

		const res = await admin.post(`${API}/admin/templates/${tpl.id}/resync`, {
			data: { strategy: 'merge', dryRun: false },
		})
		expect(res.status(), await res.text()).toBe(200)
		for (const c of copies) {
			const titles = await titlesOn(c.who, c.dashboardId)
			expect(titles).not.toContain(links)
			expect(titles).toContain(c.own)
			expect(titles).toContain(`${STAMP} resync one`)
		}
	})
})
