/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A re-sync of an admin template with more copies than
 * TemplateResyncService::ASYNC_THRESHOLD (50) is queued, not applied inline
 * (admin-templates REQ-RESYNC-005, "Large groups apply asynchronously").
 * Written for the e2e-coverage-programme, task 3.3.
 *
 * The scenario was excluded with "needs more than 50 provisioned copies and a
 * cron run of TemplateResyncJob, which the e2e instance does not run on
 * demand". Both can be had. The copies are 51 throwaway members of one group,
 * each of whom opens LaunchPad once (`GET /api/dashboard`), which is how
 * admin-templates-resync.spec.ts hands out its copies. The job is run on
 * demand with `occ background-job:execute --force-execute`, as
 * dashboard-view-analytics-history.spec.ts runs its purge job
 * (support/occ.ts). The scenario's 800 users are an example; what the product
 * decides on is "more than 50", so 51 is the smallest GIVEN that takes the
 * async branch.
 *
 * HOW A DIFFERENCE IS STAGED. As in admin-templates-resync.spec.ts: each
 * member removes one template widget from their copy, so an overwrite has
 * something to apply to every copy.
 *
 * What is asserted, in order:
 *   1. the real re-sync answers at once with the accepted envelope
 *      (`async: true`, `accepted: true`, 51 copies, 51 affected) and has
 *      changed no copy yet;
 *   2. a TemplateResyncJob for this template, with the strategy and the
 *      acting admin, is queued;
 *   3. once that job has run, every copy holds the template's layout again
 *      and every member has a LaunchPad notification about their copy.
 *
 * The file takes a while (51 accounts), hence the long timeout. Everything it
 * makes (the group, the template, and the accounts that hold the copies) is
 * removed in afterAll, and the personal-dashboards flag is put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::large-groups-apply-asynchronously
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { PlacementRow, TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	activeDashboard,
	deleteTemplates,
	getTemplate,
	importTemplate,
	placementsOf,
	readAllowUserDashboards,
	setAllowUserDashboards,
	sortedLayout,
	textWidget,
} from './support/adminTemplates.ts'
import { describeRun, runOcc } from './support/occ.ts'
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
const STAMP = `E2E Resync async ${RUN}`
const JOB_CLASS = 'OCA\\LaunchPad\\BackgroundJob\\TemplateResyncJob'
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
/** TemplateResyncService::ASYNC_THRESHOLD is 50: one more takes the async branch. */
const COPIES = 51
const KEPT = `${STAMP} kept`
const REMOVED = `${STAMP} removed`

interface Copy {
	who: Principal
	dashboardId: number
}

interface QueuedJob {
	id: number | string
	argument: Record<string, unknown>
}

let admin: APIRequestContext
let allowBefore: boolean | null = null
let group: string | null = null
let template: TemplateRow | null = null
const copies: Copy[] = []

/**
 * The queued TemplateResyncJob rows, with their arguments decoded.
 *
 * @return the jobs.
 */
async function queuedResyncJobs(): Promise<QueuedJob[]> {
	const listed = await runOcc(
		'background-job:list',
		`--class=${JOB_CLASS}`,
		'--output=json',
	)
	expect(listed.code, describeRun(listed)).toBe(0)
	const rows = JSON.parse(listed.stdout) as Array<{
		id: number | string
		argument: unknown
	}>
	return rows.map((row) => ({
		id: row.id,
		argument:
			typeof row.argument === 'string'
				? (JSON.parse(row.argument) as Record<string, unknown>)
				: ((row.argument ?? {}) as Record<string, unknown>),
	}))
}

/**
 * A member's LaunchPad notifications about their copy.
 *
 * @param who the member.
 * @param copyId their copy's id.
 * @return the notifications.
 */
async function noticesAbout(who: Principal, copyId: number): Promise<unknown[]> {
	const res = await who.api.get(
		'/ocs/v2.php/apps/notifications/api/v2/notifications?format=json',
	)
	if (res.status() === 204) {
		return []
	}
	expect(res.status(), 'the Notifications API must answer').toBe(200)
	const data = ((await res.json()).ocs?.data ?? []) as Array<
		Record<string, unknown>
	>
	return data.filter(
		(n) =>
			n.app === 'launchpad'
			&& n.object_type === 'dashboard'
			&& String(n.object_id) === String(copyId),
	)
}

/**
 * The copy placement titled `title`.
 *
 * @param ps the placements.
 * @param title the custom title.
 * @return the placement.
 */
function byTitle(ps: PlacementRow[], title: string): PlacementRow {
	const found = ps.find((p) => p.customTitle === title)
	if (found === undefined) {
		throw new Error(`no placement titled ${title}`)
	}
	return found
}

test.setTimeout(900_000)

test.beforeAll(async () => {
	test.setTimeout(900_000)
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	await setAllowUserDashboards(admin, true)
	group = await createGroup(admin, `e2e-resync-async-${RUN}`)
	template = await importTemplate(admin, STAMP, {
		widgets: [
			textWidget(KEPT, { x: 0, y: 0 }),
			textWidget(REMOVED, { x: 4, y: 0 }),
		],
		targetGroups: [group],
		permissionLevel: 'add_only',
	})

	for (let i = 0; i < COPIES; i++) {
		const who = await createPrincipal(admin, `e2e-rsa-${i}`)
		copies.push({ who, dashboardId: 0 })
		await addToGroup(admin, who.username, group)
		const first = await activeDashboard(who.api)
		expect(
			first.dashboard?.basedOnTemplate,
			`${who.username} must hold a copy`,
		).toBe(template.id)
		copies[copies.length - 1].dashboardId = first.dashboard.id

		// The difference the re-sync will apply: the member removes a widget.
		const removed = byTitle(first.placements, REMOVED)
		const res = await who.api.delete(`${API}/widgets/${removed.id}`)
		expect(res.status(), `${who.username} removing a widget`).toBe(200)
	}
})

test.afterAll(async () => {
	test.setTimeout(900_000)
	if (admin === undefined) {
		return
	}
	if (template !== null) {
		await deleteTemplates(admin, [template.id])
	}
	for (const copy of copies) {
		await removePrincipal(admin, copy.who)
	}
	await removeGroup(admin, group)
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await admin.dispose()
})

test('a re-sync of more than 50 copies is queued, and the job applies it and notifies every member', async () => {
	// @e2e admin-templates::large-groups-apply-asynchronously
	const templateId = template!.id
	const before = new Set((await queuedResyncJobs()).map((j) => String(j.id)))

	const res = await admin.post(`${API}/admin/templates/${templateId}/resync`, {
		data: { strategy: 'overwrite', dryRun: false },
	})
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body).toMatchObject({
		templateId,
		strategy: 'overwrite',
		dryRun: false,
		async: true,
		accepted: true,
		totalCopies: COPIES,
		affectedCount: COPIES,
	})

	// Accepted, not applied: no copy has its widget back yet.
	for (const copy of [copies[0], copies[COPIES - 1]]) {
		const titles = (await placementsOf(copy.who.api, copy.dashboardId)).map(
			(p) => p.customTitle,
		)
		expect(titles, `${copy.who.username} before the job ran`).not.toContain(
			REMOVED,
		)
	}

	// The job is queued for this template, strategy and admin.
	const queued = (await queuedResyncJobs()).filter(
		(j) =>
			!before.has(String(j.id))
			&& Number(j.argument.templateId) === templateId,
	)
	expect(queued).toHaveLength(1)
	expect(queued[0].argument).toMatchObject({
		strategy: 'overwrite',
		actingAdminId: ADMIN_USER,
	})

	const ran = await runOcc(
		'background-job:execute',
		'--force-execute',
		String(queued[0].id),
	)
	expect(ran.code, describeRun(ran)).toBe(0)

	// The job applied the plan to every copy and notified every member.
	const wanted = sortedLayout((await getTemplate(admin, templateId)).placements)
	expect(wanted).toHaveLength(2)
	for (const copy of copies) {
		expect(
			sortedLayout(await placementsOf(copy.who.api, copy.dashboardId)),
			`${copy.who.username}'s copy after the job`,
		).toEqual(wanted)
		expect(
			(await noticesAbout(copy.who, copy.dashboardId)).length,
			`${copy.who.username} is notified`,
		).toBeGreaterThanOrEqual(1)
	}
})
