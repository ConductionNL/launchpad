/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Re-syncing an admin template to the copies already handed out
 * (admin-templates REQ-RESYNC-001..005): `POST /api/admin/templates/{id}/resync`
 * with `{strategy, dryRun}`. An HTTP contract, so it is driven through the
 * API; the refusal is probed by an ordinary signed-in account and re-read.
 *
 * HOW A DIFFERENCE IS STAGED. A re-sync compares each copy with its template.
 * The widget API cannot change a template (an admin template has no owner,
 * see support/adminTemplates.ts), so where a scenario's GIVEN is "the
 * template and the copy now differ", the difference is made on the COPY by
 * its member: a widget removed, a widget moved, a widget of their own added.
 * The re-sync sees the same diff either way. The one scenario whose GIVEN
 * cannot be reached like that (a template widget the admin DELETED) runs in
 * admin-templates-template-widgets.spec.ts, expected red until the admin may
 * delete a template's widget.
 *
 * Audit records are read from the Activity stream and notifications from the
 * Notifications API; both apps ship with Nextcloud and are on by default.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::admin-re-syncs-a-template-to-existing-copies
 *   @e2e admin-templates::non-admin-cannot-re-sync
 *   @e2e admin-templates::re-sync-rejects-a-non-template-dashboard
 *   @e2e admin-templates::invalid-strategy-is-rejected
 *   @e2e admin-templates::dry-run-reports-affected-copies-without-mutating
 *   @e2e admin-templates::dry-run-on-an-up-to-date-template-reports-no-changes
 *   @e2e admin-templates::merge-keeps-user-additions-while-applying-template-changes
 *   @e2e admin-templates::overwrite-replaces-the-layout
 *   @e2e admin-templates::compulsory-widget-restored-under-merge
 *   @e2e admin-templates::compulsory-widget-position-aligned-under-both-strategies
 *   @e2e admin-templates::re-sync-is-idempotent
 *   @e2e admin-templates::audit-record-is-written-on-a-real-run
 *   @e2e admin-templates::affected-users-are-notified
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
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E Resync ${RUN}`

let admin: APIRequestContext
let alice: Principal | null = null
let allowBefore: boolean | null = null
const templates: number[] = []
const groups: string[] = []
const principals: Principal[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	await setAllowUserDashboards(admin, true)
	alice = await createPrincipal(admin, 'e2e-resync-alice')
	await assertActingAs(alice.api, alice.username)
})

test.afterAll(async () => {
	await deleteTemplates(admin, templates)
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	await removePrincipal(admin, alice)
	for (const g of groups) {
		await removeGroup(admin, g)
	}
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

interface Copy {
	who: Principal
	dashboardId: number
	placements: PlacementRow[]
}

/**
 * A template for a fresh group and `count` members who each opened LaunchPad
 * once, so each holds a copy.
 */
async function distributed(
	tag: string,
	widgets: SeedWidget[],
	count: number,
	permissionLevel = 'add_only',
): Promise<{ template: TemplateRow; copies: Copy[] }> {
	const g = await createGroup(admin, `e2e-resync-${tag}-${RUN}`)
	groups.push(g)
	const template = await importTemplate(admin, `${STAMP} ${tag}`, {
		widgets,
		targetGroups: [g],
		permissionLevel,
	})
	templates.push(template.id)

	const copies: Copy[] = []
	for (let i = 0; i < count; i++) {
		const who = await createPrincipal(admin, `e2e-resync-${tag}-${i}`)
		principals.push(who)
		await addToGroup(admin, who.username, g)
		const first = await activeDashboard(who.api)
		expect(
			first.dashboard.basedOnTemplate,
			`${who.username} must hold a copy`,
		).toBe(template.id)
		copies.push({
			who,
			dashboardId: first.dashboard.id,
			placements: first.placements,
		})
	}
	return { template, copies }
}

/** The copy placement that came from the template placement titled `title`. */
function byTitle(ps: PlacementRow[], title: string): PlacementRow {
	const found = ps.find((p) => p.customTitle === title)
	if (found === undefined) {
		throw new Error(`no placement titled ${title}`)
	}
	return found
}

/** A member moves one of their copy's widgets. */
async function memberMoves(copy: Copy, title: string, gridY: number): Promise<void> {
	const p = byTitle(await placementsOf(copy.who.api, copy.dashboardId), title)
	const res = await copy.who.api.put(`${API}/widgets/${p.id}`, {
		data: { gridY, version: p.version },
	})
	expect(res.status(), `moving ${title} answered ${res.status()}`).toBe(200)
}

/** A member removes one of their copy's widgets. */
async function memberRemoves(copy: Copy, title: string): Promise<void> {
	const p = byTitle(await placementsOf(copy.who.api, copy.dashboardId), title)
	const res = await copy.who.api.delete(`${API}/widgets/${p.id}`)
	expect(res.status(), `removing ${title} answered ${res.status()}`).toBe(200)
}

/** A member adds a widget of their own. */
async function memberAdds(copy: Copy, title: string, gridY: number): Promise<void> {
	const res = await addTitledTextWidget(copy.who.api, copy.dashboardId, title, { x: 0, y: gridY })
	expect(res, `adding ${title}`).toEqual({ created: 201, titled: 200 })
}

/** Ask for a re-sync as the admin. */
async function resync(
	templateId: number,
	data: Record<string, unknown>,
	as = admin,
) {
	return as.post(`${API}/admin/templates/${templateId}/resync`, { data })
}

/** The admin's Activity entries about re-syncs of one template. */
async function auditRows(templateId: number): Promise<any[]> {
	const res = await admin.get(
		'/ocs/v2.php/apps/activity/api/v2/activity/all?format=json&limit=200',
	)
	if (res.status() === 204 || res.status() === 304) {
		return []
	}
	expect(res.status(), 'the Activity API must answer').toBe(200)
	const data = (await res.json()).ocs?.data ?? []
	return data.filter(
		(a: any) =>
			a.app === 'launchpad'
			&& a.object_name === `template-resync-${templateId}`,
	)
}

/** A member's LaunchPad notifications about a re-synced copy. */
async function resyncNotices(who: Principal, copyId: number): Promise<any[]> {
	const res = await who.api.get(
		'/ocs/v2.php/apps/notifications/api/v2/notifications?format=json',
	)
	if (res.status() === 204) {
		return []
	}
	expect(res.status(), 'the Notifications API must answer').toBe(200)
	const data = (await res.json()).ocs?.data ?? []
	return data.filter(
		(n: any) =>
			n.app === 'launchpad'
			&& n.object_type === 'dashboard'
			&& String(n.object_id) === String(copyId),
	)
}

function TWO(tag: string): SeedWidget[] {
	return [
		textWidget(`${STAMP} ${tag} one`, { x: 0, y: 0 }),
		textWidget(`${STAMP} ${tag} two`, { x: 4, y: 0 }),
	]
}

test.describe('the action (REQ-RESYNC-001)', () => {
	// @e2e admin-templates::admin-re-syncs-a-template-to-existing-copies
	test('an overwrite applies the template layout to every copy and reports the count', async () => {
		const { template, copies } = await distributed('all', TWO('all'), 3)
		// Every copy now lacks a template widget and has one moved.
		for (const c of copies) {
			await memberRemoves(c, `${STAMP} all two`)
			await memberMoves(c, `${STAMP} all one`, 5)
		}

		const res = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(body.dryRun).toBe(false)
		expect(body.async).toBe(false)
		expect(body.totalCopies).toBe(3)
		expect(body.affectedCount).toBe(3)

		const wanted = sortedLayout(
			(await getTemplate(admin, template.id)).placements,
		)
		for (const c of copies) {
			expect(
				sortedLayout(await placementsOf(c.who.api, c.dashboardId)),
			).toEqual(wanted)
		}
	})

	// @e2e admin-templates::non-admin-cannot-re-sync
	test('an ordinary account is refused with 403 and no copy changes; the admin may', async () => {
		const { template, copies } = await distributed('guard', TWO('guard'), 1)
		await memberAdds(copies[0], `${STAMP} guard own`, 4)
		const before = await placementsOf(copies[0].who.api, copies[0].dashboardId)

		const refused = await resync(
			template.id,
			{ strategy: 'overwrite', dryRun: false },
			alice!.api,
		)
		expect(refused.status()).toBe(403)
		expect(await placementsOf(copies[0].who.api, copies[0].dashboardId)).toEqual(
			before,
		)

		const allowed = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(allowed.status()).toBe(200)
		expect((await allowed.json()).affectedCount).toBe(1)
		const after = await placementsOf(copies[0].who.api, copies[0].dashboardId)
		expect(after.map((p) => p.customTitle)).not.toContain(`${STAMP} guard own`)
	})

	// @e2e admin-templates::re-sync-rejects-a-non-template-dashboard
	test('a user dashboard is refused as not an admin template and stays as it is', async () => {
		const { copies } = await distributed('notpl', TWO('notpl'), 1)
		await memberAdds(copies[0], `${STAMP} notpl own`, 4)
		const before = await placementsOf(copies[0].who.api, copies[0].dashboardId)

		const res = await resync(copies[0].dashboardId, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).error).toBe('Not an admin template')
		expect(await placementsOf(copies[0].who.api, copies[0].dashboardId)).toEqual(
			before,
		)
	})

	// @e2e admin-templates::invalid-strategy-is-rejected
	test('a strategy other than overwrite or merge is refused with 400', async () => {
		const { template } = await distributed('strategy', TWO('strategy'), 1)
		const bad = await resync(template.id, { strategy: 'replace-all' })
		expect(bad.status()).toBe(400)
		expect((await bad.json()).error).toMatch(/overwrite.*merge/)

		for (const strategy of ['overwrite', 'merge']) {
			const ok = await resync(template.id, { strategy, dryRun: true })
			expect(ok.status(), strategy).toBe(200)
			expect((await ok.json()).strategy).toBe(strategy)
		}
	})
})

test.describe('dry run (REQ-RESYNC-002)', () => {
	// @e2e admin-templates::dry-run-reports-affected-copies-without-mutating
	test('a dry run lists every copy with its counts and writes, audits and notifies nothing', async () => {
		const { template, copies } = await distributed('dry', TWO('dry'), 2)
		await memberMoves(copies[0], `${STAMP} dry one`, 6)
		await memberAdds(copies[0], `${STAMP} dry own`, 8)
		const before = await Promise.all(
			copies.map((c) => placementsOf(c.who.api, c.dashboardId)),
		)

		const res = await resync(template.id, { strategy: 'merge', dryRun: true })
		expect(res.status()).toBe(200)
		const plan = await res.json()
		expect(plan.dryRun).toBe(true)
		expect(plan.totalCopies).toBe(2)
		expect(plan.affectedCount).toBe(1)
		expect(plan.copies).toHaveLength(2)
		const changed = plan.copies.find(
			(c: any) => c.dashboardId === copies[0].dashboardId,
		)
		const same = plan.copies.find(
			(c: any) => c.dashboardId === copies[1].dashboardId,
		)
		expect(changed).toMatchObject({
			toAdd: 0,
			toUpdate: 1,
			toRemove: 0,
			toPreserve: 2,
			hasChanges: true,
			applied: false,
		})
		expect(same).toMatchObject({
			toAdd: 0,
			toUpdate: 0,
			toRemove: 0,
			toPreserve: 2,
			hasChanges: false,
			applied: false,
		})

		const after = await Promise.all(
			copies.map((c) => placementsOf(c.who.api, c.dashboardId)),
		)
		expect(after).toEqual(before)
		expect(await auditRows(template.id)).toEqual([])
		for (const c of copies) {
			expect(await resyncNotices(c.who, c.dashboardId)).toEqual([])
		}
	})

	// @e2e admin-templates::dry-run-on-an-up-to-date-template-reports-no-changes
	test('after a re-sync, a dry run reports nothing to add, update or remove', async () => {
		const { template, copies } = await distributed('fresh', TWO('fresh'), 2)
		await memberMoves(copies[0], `${STAMP} fresh one`, 6)
		expect(
			(
				await resync(template.id, { strategy: 'overwrite', dryRun: false })
			).status(),
		).toBe(200)

		const res = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: true,
		})
		expect(res.status()).toBe(200)
		const plan = await res.json()
		expect(plan.copies).toHaveLength(2)
		for (const copy of plan.copies) {
			expect(copy).toMatchObject({ toAdd: 0, toUpdate: 0, toRemove: 0 })
		}
		expect(plan.affectedCount).toBe(0)
	})
})

test.describe('strategies (REQ-RESYNC-003, -004)', () => {
	// @e2e admin-templates::merge-keeps-user-additions-while-applying-template-changes
	test('merge brings back the template widget and position and keeps the Notes widget', async () => {
		const announcements = `${STAMP} Announcements`
		const { template, copies } = await distributed(
			'merge',
			[
				textWidget(`${STAMP} merge one`, { x: 0, y: 0 }),
				textWidget(announcements, { x: 4, y: 0 }),
			],
			1,
		)
		const aliceCopy = copies[0]
		// The copy lacks the template's Announcements widget and has the
		// other template widget elsewhere: the diff of "the admin added one
		// and moved one". Then she adds her own Notes.
		await memberRemoves(aliceCopy, announcements)
		await memberMoves(aliceCopy, `${STAMP} merge one`, 7)
		await memberAdds(aliceCopy, `${STAMP} Notes`, 9)
		const notesBefore = byTitle(
			await placementsOf(aliceCopy.who.api, aliceCopy.dashboardId),
			`${STAMP} Notes`,
		)

		const res = await resync(template.id, { strategy: 'merge', dryRun: false })
		expect(res.status()).toBe(200)
		expect((await res.json()).affectedCount).toBe(1)

		const tpl = (await getTemplate(admin, template.id)).placements
		const after = await placementsOf(aliceCopy.who.api, aliceCopy.dashboardId)
		const ann = byTitle(after, announcements)
		expect([ann.gridX, ann.gridY]).toEqual([4, 0])
		expect(ann.templatePlacementId).toBe(byTitle(tpl, announcements).id)
		expect(byTitle(after, `${STAMP} merge one`).gridY).toBe(0)
		const notes = byTitle(after, `${STAMP} Notes`)
		expect(notes.id).toBe(notesBefore.id)
		expect([
			notes.gridX,
			notes.gridY,
			notes.gridWidth,
			notes.gridHeight,
		]).toEqual([
			notesBefore.gridX,
			notesBefore.gridY,
			notesBefore.gridWidth,
			notesBefore.gridHeight,
		])
		expect(notes.content).toEqual(notesBefore.content)
	})

	// @e2e admin-templates::overwrite-replaces-the-layout
	test("overwrite makes bob's copy the template layout and drops his own widget", async () => {
		const { template, copies } = await distributed('over', TWO('over'), 1)
		const bob = copies[0]
		await memberAdds(bob, `${STAMP} bob own`, 6)
		await memberMoves(bob, `${STAMP} over two`, 8)

		const res = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(res.status()).toBe(200)

		const after = await placementsOf(bob.who.api, bob.dashboardId)
		expect(sortedLayout(after)).toEqual(
			sortedLayout((await getTemplate(admin, template.id)).placements),
		)
		expect(after.map((p) => p.customTitle)).not.toContain(`${STAMP} bob own`)
	})

	// @e2e admin-templates::compulsory-widget-restored-under-merge
	test('a compulsory widget carol removed comes back at the template position under merge', async () => {
		const news = `${STAMP} Company News`
		const { template, copies } = await distributed(
			'compulsory',
			[
				textWidget(news, { x: 2, y: 1 }, { isCompulsory: 1, gridWidth: 6 }),
				textWidget(`${STAMP} compulsory other`, { x: 0, y: 4 }),
			],
			1,
			// `full` is the level at which a member can remove a compulsory
			// widget at all (PermissionService::allowsRemoval).
			'full',
		)
		const carol = copies[0]
		await memberRemoves(carol, news)
		expect(
			(await placementsOf(carol.who.api, carol.dashboardId)).map(
				(p) => p.customTitle,
			),
		).not.toContain(news)

		const res = await resync(template.id, { strategy: 'merge', dryRun: false })
		expect(res.status()).toBe(200)

		const restored = byTitle(
			await placementsOf(carol.who.api, carol.dashboardId),
			news,
		)
		expect(restored.isCompulsory).toBe(1)
		expect([
			restored.gridX,
			restored.gridY,
			restored.gridWidth,
			restored.gridHeight,
		]).toEqual([2, 1, 6, 2])
	})

	// @e2e admin-templates::compulsory-widget-position-aligned-under-both-strategies
	test("a compulsory widget away from the template's position is put back by merge and by overwrite", async () => {
		const pinned = `${STAMP} Pinned`
		const { template, copies } = await distributed(
			'aligned',
			[
				textWidget(pinned, { x: 0, y: 0 }, { isCompulsory: 1 }),
				textWidget(`${STAMP} aligned other`, { x: 4, y: 0 }),
			],
			1,
			'full',
		)
		const dave = copies[0]
		const wanted = byTitle(
			(await getTemplate(admin, template.id)).placements,
			pinned,
		)

		for (const strategy of ['merge', 'overwrite']) {
			// The copy's compulsory widget now sits where the template's does not.
			await memberMoves(dave, pinned, 9)
			expect(
				byTitle(await placementsOf(dave.who.api, dave.dashboardId), pinned)
					.gridY,
			).toBe(9)

			const res = await resync(template.id, { strategy, dryRun: false })
			expect(res.status(), strategy).toBe(200)
			const p = byTitle(
				await placementsOf(dave.who.api, dave.dashboardId),
				pinned,
			)
			expect(
				[p.gridX, p.gridY, p.gridWidth, p.gridHeight, p.isCompulsory],
				strategy,
			).toEqual([
				wanted.gridX,
				wanted.gridY,
				wanted.gridWidth,
				wanted.gridHeight,
				wanted.isCompulsory,
			])
		}
	})
})

test.describe('idempotent, audited, notified (REQ-RESYNC-005)', () => {
	// @e2e admin-templates::re-sync-is-idempotent
	test('running the same overwrite twice changes nothing the second time', async () => {
		const { template, copies } = await distributed('idem', TWO('idem'), 2)
		await memberMoves(copies[0], `${STAMP} idem one`, 6)
		await memberAdds(copies[1], `${STAMP} idem own`, 6)

		const first = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(first.status()).toBe(200)
		expect((await first.json()).affectedCount).toBe(2)
		const afterFirst = await Promise.all(
			copies.map((c) => placementsOf(c.who.api, c.dashboardId)),
		)

		const second = await resync(template.id, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(second.status()).toBe(200)
		const body = await second.json()
		expect(body.affectedCount).toBe(0)
		expect(
			body.copies.every(
				(c: any) => c.hasChanges === false && c.error === null,
			),
		).toBe(true)
		const afterSecond = await Promise.all(
			copies.map((c) => placementsOf(c.who.api, c.dashboardId)),
		)
		expect(afterSecond).toEqual(afterFirst)
	})

	// @e2e admin-templates::audit-record-is-written-on-a-real-run
	test('a real run writes one audit record with admin, template, strategy, count and time', async () => {
		const { template, copies } = await distributed('audit', TWO('audit'), 2)
		for (const c of copies) {
			await memberMoves(c, `${STAMP} audit two`, 6)
		}
		expect(await auditRows(template.id)).toEqual([])
		const startedAt = Date.now()

		const res = await resync(template.id, { strategy: 'merge', dryRun: false })
		expect(res.status()).toBe(200)
		expect((await res.json()).affectedCount).toBe(2)

		const rows = await auditRows(template.id)
		expect(rows).toHaveLength(1)
		const row = rows[0]
		expect(row.user).toBe(process.env.NC_ADMIN_USER ?? 'admin')
		expect(row.subject).toContain(
			`Template resync: ${template.name} (merge, 2 copies)`,
		)
		expect(row.object_name).toBe(`template-resync-${template.id}`)
		const at = Date.parse(row.datetime)
		expect(Number.isNaN(at)).toBe(false)
		expect(at).toBeGreaterThanOrEqual(startedAt - 60_000)
	})

	// @e2e admin-templates::affected-users-are-notified
	// What can be observed is the Nextcloud notification. The scenario's
	// OpenRegister-dialect branch is not wired in the product
	// (TemplateResyncService::notifyUserResync, lib/Service/TemplateResyncService.php:1002-1035,
	// documents it as deliberately absent), so it is not asserted here.
	test('the member whose copy changed is told an administrator updated it; the other is not', async () => {
		const { template, copies } = await distributed('notify', TWO('notify'), 2)
		const [erin, frank] = copies
		await memberMoves(erin, `${STAMP} notify one`, 6)

		const res = await resync(template.id, { strategy: 'merge', dryRun: false })
		expect(res.status()).toBe(200)
		expect((await res.json()).affectedCount).toBe(1)

		const notices = await resyncNotices(erin.who, erin.dashboardId)
		expect(notices).toHaveLength(1)
		expect(notices[0].subject).toBe(
			`An administrator updated your ${template.name} dashboard`,
		)
		expect(await resyncNotices(frank.who, frank.dashboardId)).toEqual([])
	})
})
