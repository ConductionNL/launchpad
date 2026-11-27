/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for template-routed mandatory-read
 * acknowledgements (openspec/specs/dashboard-acknowledgements), written for
 * e2e-coverage-programme task 2.11.
 *
 * THE WORLD THIS FILE BUILDS: AN ANNOUNCEMENT ON AN ADMIN TEMPLATE
 * ================================================================
 * The administrator installs the shipped "Mijn werkdag" template (a fresh
 * copy this file owns), routes it to a throwaway group and asks for a read
 * confirmation on its header widget. Members of the group get their personal
 * copy on their first request (TemplateService::createDashboardFromTemplate),
 * carrying the template placement's `announcementKey`. The audience is the
 * group's CURRENT members, resolved through IGroupManager at report time, so
 * group membership is changed over OCS to show the audience following it.
 * A change on the template reaches the copies through the documented admin
 * re-sync (`POST /api/admin/templates/{id}/resync`, strategy `merge`).
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-acknowledgements::admin-requires-acknowledgement-on-a-template-announcement
 *   @e2e dashboard-acknowledgements::non-author-cannot-require-acknowledgement
 *   @e2e dashboard-acknowledgements::a-copy-kept-after-leaving-the-audience-is-not-pending
 *   @e2e dashboard-acknowledgements::report-separates-acknowledged-from-pending-against-the-live-audience
 *   @e2e dashboard-acknowledgements::a-newly-added-group-member-becomes-pending-automatically
 *   @e2e dashboard-acknowledgements::version-bump-re-forces-delivery-when-re-acknowledge-is-on
 *   @e2e dashboard-acknowledgements::version-bump-does-not-re-force-when-re-acknowledge-is-off
 *   @e2e dashboard-acknowledgements::clearing-the-requirement-stops-forcing-delivery
 *
 * @spec openspec/specs/dashboard-acknowledgements/spec.md
 */

import type { APIRequestContext, Browser } from '@playwright/test'
import type { AckPlacement } from './fixtures/acknowledgements.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	API,
	pendingFor,
	postAcknowledgement,
	promptSelector,
	readUserDashboardsFlag,
	reportOf,
	setUserDashboardsFlag,
	updatePlacement,
} from './fixtures/acknowledgements.ts'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	addToGroup,
	adminContext,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const PROMPT = 'Ik heb de 2026 integriteitscode gelezen en begrepen'
const DEADLINE = '2026-08-01'
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

let admin: APIRequestContext
let userDashboardsBefore: boolean | null = null
let group: string | null = null
let templateId: number | null = null
let alice: Principal | null = null
let bob: Principal | null = null
let carol: Principal | null = null
let frank: Principal | null = null
let dave: Principal | null = null

/** The template placements this file uses, by role. */
let notice: AckPlacement
let untouched: AckPlacement
let calendar: AckPlacement
let KEY = ''
/** Each member's copy dashboard id and their copies, keyed by template placement id. */
const copyDashboards: Record<string, number> = {}
const receipts: Record<string, { id: number; acknowledgedAt: string }> = {}

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * The admin template's placements.
 *
 * @return its placements.
 */
async function templatePlacements(): Promise<AckPlacement[]> {
	const res = await admin.get(`${API}/admin/templates/${templateId}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).placements as AckPlacement[]
}

/**
 * One template placement, re-read.
 *
 * @param id the placement id.
 * @return the placement.
 */
async function templatePlacement(id: number): Promise<AckPlacement> {
	const found = (await templatePlacements()).find((p) => p.id === id)
	expect(found, `template placement ${id}`).toBeTruthy()
	return found!
}

/**
 * A member's copy of a template placement.
 *
 * @param who the member.
 * @param templatePlacementId the template placement it was copied from.
 * @return the copy.
 */
async function copyOf(
	who: Principal,
	templatePlacementId: number,
): Promise<AckPlacement & { templatePlacementId: number }> {
	const res = await who.api.get(`${API}/dashboard/${copyDashboards[who.username]}`)
	expect(res.status(), await res.text()).toBe(200)
	const placements = (await res.json()).placements as Array<
		AckPlacement & { templatePlacementId: number }
	>
	const copy = placements.find(
		(p) => p.templatePlacementId === templatePlacementId,
	)
	expect(copy, JSON.stringify(placements)).toBeTruthy()
	return copy!
}

/**
 * Push the template to every copy, as the admin's re-sync does.
 *
 * @return nothing.
 */
async function resync(): Promise<void> {
	const res = await admin.post(`${API}/admin/templates/${templateId}/resync`, {
		data: { strategy: 'merge', dryRun: false },
	})
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * A member's first request, which hands them their copy of the template.
 *
 * @param who the member.
 * @return nothing.
 */
async function receiveCopy(who: Principal): Promise<void> {
	const res = await who.api.get(`${API}/dashboard`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.dashboard.basedOnTemplate).toBe(templateId)
	expect(body.dashboard.userId).toBe(who.username)
	copyDashboards[who.username] = Number(body.dashboard.id)
}

/**
 * Open the workspace as a member.
 *
 * @param browser the browser fixture.
 * @param who the member.
 * @return the context and page.
 */
async function open(browser: Browser, who: Principal) {
	const session = await loginAs(browser, who.username, who.password)
	await session.page.goto('/index.php/apps/launchpad/')
	return session
}

test.beforeAll(async () => {
	test.setTimeout(600_000)
	admin = await adminContext()
	userDashboardsBefore = await readUserDashboardsFlag(admin)
	// A member is only handed a copy while personal dashboards are on.
	await setUserDashboardsFlag(admin, true)

	group = await createGroup(admin, `e2e-ack-hr-${RUN}`)
	alice = await createPrincipal(admin, 'e2e-acktpl-alice')
	bob = await createPrincipal(admin, 'e2e-acktpl-bob')
	carol = await createPrincipal(admin, 'e2e-acktpl-carol')
	frank = await createPrincipal(admin, 'e2e-acktpl-frank')
	for (const who of [alice, bob, carol, frank]) {
		await addToGroup(admin, who.username, group)
	}

	const installed = await admin.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{
			data: { force: true },
		},
	)
	expect(installed.status(), await installed.text()).toBeLessThan(300)
	templateId = Number((await installed.json()).id)
	const targeted = await admin.put(`${API}/admin/templates/${templateId}`, {
		data: { targetGroups: [group] },
	})
	expect(targeted.status(), await targeted.text()).toBeLessThan(300)

	const placements = await templatePlacements()
	const byWidget = (id: string) => {
		const hit = placements.filter((p) => p.widgetId === id)
		expect(hit, `${id} in ${JSON.stringify(placements)}`).toHaveLength(1)
		return hit[0]
	}
	notice = byWidget('header')
	// The template's apps block is a well of the viewer's apps since the
	// multi-tile was retired (layout-widgets-replace-multi-tile D3).
	untouched = byWidget('container')
	calendar = byWidget('calendar')
})

test.afterAll(async () => {
	if (templateId !== null) {
		await admin.delete(`${API}/admin/templates/${templateId}`)
	}
	for (const who of [alice, bob, carol, frank, dave]) {
		if (who !== null && copyDashboards[who.username]) {
			await who.api.delete(`${API}/dashboard/${copyDashboards[who.username]}`)
		}
		await removePrincipal(admin, who)
	}
	await removeGroup(admin, group)
	if (userDashboardsBefore !== null) {
		await setUserDashboardsFlag(admin, userDashboardsBefore)
	}
	await admin.dispose()
})

// @e2e dashboard-acknowledgements::admin-requires-acknowledgement-on-a-template-announcement
test('the admin asks for confirmation on a template widget; every copy made later carries it', async () => {
	const asked = await updatePlacement(admin, notice.id, {
		requiresAcknowledgement: 1,
		acknowledgementPrompt: PROMPT,
		acknowledgementDeadline: DEADLINE,
		reacknowledgeOnChange: 1,
	})
	expect(asked.status(), await asked.text()).toBe(200)

	// THEN: the fields persist at content version 1 …
	const stored = await templatePlacement(notice.id)
	expect(stored).toMatchObject({
		requiresAcknowledgement: 1,
		acknowledgementPrompt: PROMPT,
		acknowledgementDeadline: DEADLINE,
		acknowledgementContentVersion: 1,
	})
	// AND: … with a minted announcementKey.
	KEY = String(stored.announcementKey ?? '')
	expect(KEY).toMatch(UUID)

	// AND: every user dashboard cloned from the template afterwards carries a
	// placement with the same key and the same acknowledgement fields.
	for (const who of [alice!, bob!, carol!, frank!]) {
		await receiveCopy(who)
		const copy = await copyOf(who, notice.id)
		expect(copy).toMatchObject({
			announcementKey: KEY,
			requiresAcknowledgement: 1,
			acknowledgementPrompt: PROMPT,
			acknowledgementDeadline: DEADLINE,
			reacknowledgeOnChange: 1,
			acknowledgementContentVersion: 1,
		})
	}
})

// @e2e dashboard-acknowledgements::non-author-cannot-require-acknowledgement
test('a recipient of the template cannot ask for confirmation on a template widget', async () => {
	const refused = await updatePlacement(bob!.api, untouched.id, {
		requiresAcknowledgement: 1,
	})
	expect(refused.status(), await refused.text()).toBe(403)

	// AND: the placement's requiresAcknowledgement is still 0.
	expect((await templatePlacement(untouched.id)).requiresAcknowledgement).toBe(0)

	// Control: the administrator may touch the same field.
	const allowed = await updatePlacement(admin, untouched.id, {
		requiresAcknowledgement: 0,
	})
	expect(allowed.status(), await allowed.text()).toBe(200)
})

// @e2e dashboard-acknowledgements::a-copy-kept-after-leaving-the-audience-is-not-pending
test('a member who leaves the group keeps the copy but is no longer asked', async ({
	browser,
}) => {
	// While a member, the notice is outstanding for frank.
	expect(
		(await pendingFor(frank!.api)).items.map((i) => i.announcementKey),
	).toContain(KEY)

	const left = await admin.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(frank!.username)}/groups`,
		{ params: { groupid: group! } },
	)
	expect(left.ok(), await left.text()).toBe(true)

	// THEN: not listed, not counted …
	const pending = await pendingFor(frank!.api)
	expect(pending.items.map((i) => i.announcementKey)).not.toContain(KEY)
	expect(pending.count).toBe(pending.items.length)

	// AND: … and no blocking prompt over his copy, which he still has.
	const copy = await copyOf(frank!, notice.id)
	expect(copy.announcementKey).toBe(KEY)
	const { context, page } = await open(browser, frank!)
	try {
		await expect(page.locator(`[data-placement-id="${copy.id}"]`)).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator(promptSelector(copy.id))).toHaveCount(0)
		await expect(
			page.locator('[data-testid="acknowledgement-outstanding-count"]'),
		).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::report-separates-acknowledged-from-pending-against-the-live-audience
test('the report counts alice and carol as acknowledged and bob as pending', async () => {
	for (const who of [alice!, carol!]) {
		const res = await postAcknowledgement(who.api, {
			announcementKey: KEY,
			contentVersion: 1,
		})
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		receipts[who.username] = {
			id: Number(body.id),
			acknowledgedAt: body.acknowledgedAt,
		}
	}

	const report = await reportOf(admin, KEY)
	// THEN: 2 acknowledged, with alice's and carol's timestamps …
	expect(report.acknowledgedCount).toBe(2)
	expect(
		[...report.acknowledged].sort((a, b) => a.userId.localeCompare(b.userId)),
	).toEqual(
		[alice!, carol!]
			.map((p) => ({
				userId: p.username,
				acknowledgedAt: receipts[p.username].acknowledgedAt,
			}))
			.sort((a, b) => a.userId.localeCompare(b.userId)),
	)
	// AND: … 1 pending, bob.
	expect(report.pendingCount).toBe(1)
	expect(report.pending).toEqual([bob!.username])
})

// @e2e dashboard-acknowledgements::a-newly-added-group-member-becomes-pending-automatically
test('a member added to the group later is pending until he signs off', async () => {
	dave = await createPrincipal(admin, 'e2e-acktpl-dave')
	await addToGroup(admin, dave.username, group!)

	// THEN: the audience includes dave, and he is pending.
	const before = await reportOf(admin, KEY)
	expect(before.rows.map((r) => r.userId)).toContain(dave.username)
	expect(before.pending).toContain(dave.username)

	// AND: until he acknowledges.
	const ack = await postAcknowledgement(dave.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(ack.status(), await ack.text()).toBe(200)
	const after = await reportOf(admin, KEY)
	expect(after.pending).not.toContain(dave.username)
	expect(after.acknowledged.map((a) => a.userId)).toContain(dave.username)
})

// @e2e dashboard-acknowledgements::version-bump-re-forces-delivery-when-re-acknowledge-is-on
test('bumping the content version asks alice again; her version-1 receipt stays but does not count', async ({
	browser,
}) => {
	const bumped = await updatePlacement(admin, notice.id, {
		acknowledgementContentVersion: 2,
	})
	expect(bumped.status(), await bumped.text()).toBe(200)
	await resync()

	// THEN: alice's item is unacknowledged again — listed, and blocking.
	const pending = await pendingFor(alice!.api)
	expect(
		pending.items.find((i) => i.announcementKey === KEY)?.contentVersion,
	).toBe(2)
	const copy = await copyOf(alice!, notice.id)
	const { context, page } = await open(browser, alice!)
	try {
		await expect(page.locator(promptSelector(copy.id))).toBeVisible({
			timeout: 30_000,
		})
	} finally {
		await context.close()
	}

	// AND: her version-1 receipt is retained (acknowledging version 1 again
	// answers with that same row) …
	const v1 = await postAcknowledgement(alice!.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(v1.status(), await v1.text()).toBe(200)
	expect(await v1.json()).toMatchObject({
		id: receipts[alice!.username].id,
		acknowledgedAt: receipts[alice!.username].acknowledgedAt,
	})
	// … but does not count toward version 2.
	const report = await reportOf(admin, KEY)
	expect(report.contentVersion).toBe(2)
	expect(report.pending).toContain(alice!.username)
})

// @e2e dashboard-acknowledgements::version-bump-does-not-re-force-when-re-acknowledge-is-off
test('with re-acknowledge off, bumping the version does not ask alice again', async ({
	browser,
}) => {
	const asked = await updatePlacement(admin, calendar.id, {
		requiresAcknowledgement: 1,
		acknowledgementPrompt: PROMPT,
		reacknowledgeOnChange: 0,
	})
	expect(asked.status(), await asked.text()).toBe(200)
	await resync()
	const second = String(
		(await templatePlacement(calendar.id)).announcementKey ?? '',
	)
	expect(second).toMatch(UUID)
	const signed = await postAcknowledgement(alice!.api, {
		announcementKey: second,
		contentVersion: 1,
	})
	expect(signed.status(), await signed.text()).toBe(200)

	const bumped = await updatePlacement(admin, calendar.id, {
		acknowledgementContentVersion: 2,
	})
	expect(bumped.status(), await bumped.text()).toBe(200)
	await resync()
	const copy = await copyOf(alice!, calendar.id)
	expect(copy.acknowledgementContentVersion).toBe(2)

	// THEN: no re-forced delivery for her.
	expect(
		(await pendingFor(alice!.api)).items.map((i) => i.announcementKey),
	).not.toContain(second)
	const { context, page } = await open(browser, alice!)
	try {
		await expect(page.locator(`[data-placement-id="${copy.id}"]`)).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator(promptSelector(copy.id))).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::clearing-the-requirement-stops-forcing-delivery
test('clearing the requirement stops asking and keeps the receipts', async ({
	browser,
}) => {
	expect(
		(await pendingFor(bob!.api)).items.map((i) => i.announcementKey),
	).toContain(KEY)

	const cleared = await updatePlacement(admin, notice.id, {
		requiresAcknowledgement: 0,
	})
	expect(cleared.status(), await cleared.text()).toBe(200)
	await resync()

	// THEN: the placement no longer forces delivery.
	expect(
		(await pendingFor(bob!.api)).items.map((i) => i.announcementKey),
	).not.toContain(KEY)
	const copy = await copyOf(bob!, notice.id)
	const { context, page } = await open(browser, bob!)
	try {
		await expect(page.locator(`[data-placement-id="${copy.id}"]`)).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator(promptSelector(copy.id))).toHaveCount(0)
	} finally {
		await context.close()
	}

	// AND: the existing receipts are kept as history — acknowledging again
	// answers with the very rows written before.
	for (const who of [alice!, carol!]) {
		const again = await postAcknowledgement(who.api, {
			announcementKey: KEY,
			contentVersion: 1,
		})
		expect(again.status(), await again.text()).toBe(200)
		expect(Number((await again.json()).id)).toBe(receipts[who.username].id)
	}
})
