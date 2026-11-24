/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for mandatory-read acknowledgements
 * (openspec/specs/dashboard-acknowledgements), written for
 * e2e-coverage-programme task 2.11.
 *
 * THE WORLD THIS FILE BUILDS: AN ANNOUNCEMENT WITHOUT A TEMPLATE
 * ==============================================================
 * Tom, an ordinary account, owns a dashboard with a text widget and asks for
 * a read confirmation on it. He shares the dashboard view-only with six
 * colleagues; alice, bob, carol and dave fork it, which copies the widget with
 * its `announcementKey` (WidgetPlacementMapper::cloneToDashboard). Without a
 * template the audience is the owners of the dashboards holding a copy
 * (tom, alice, bob, carol, dave) plus everyone tom's dashboard is shared
 * with (erin and pieter too), and the author is tom, the owner of the first
 * placement that carried the key. Erin forks only after tom bumps the
 * content version; pieter can see tom's dashboard but holds no copy, and
 * must confirm all the same (REQ-ACK-003). Olaf has no share and no copy, so
 * he is outside the audience.
 *
 * Everything is seeded over the API as the person it belongs to; what a
 * person does on screen (reading the prompt, signing off, opening the
 * read-receipt report and downloading its CSV) is driven through the UI.
 *
 * The template-routed scenarios live in dashboard-acknowledgements-template.spec.ts
 * and REQ-ACK-007 (asking from the widget menu) in
 * dashboard-acknowledgements-ask.spec.ts.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-acknowledgements::a-recipient-cannot-change-the-requirement-on-their-copy
 *   @e2e dashboard-acknowledgements::unacknowledged-item-blocks-with-a-sign-off-prompt
 *   @e2e dashboard-acknowledgements::already-acknowledged-item-renders-normally
 *   @e2e dashboard-acknowledgements::deadline-is-presented-but-does-not-auto-acknowledge
 *   @e2e dashboard-acknowledgements::first-acknowledgement-writes-exactly-one-receipt
 *   @e2e dashboard-acknowledgements::repeated-acknowledgement-is-idempotent
 *   @e2e dashboard-acknowledgements::a-user-cannot-acknowledge-on-behalf-of-another-user
 *   @e2e dashboard-acknowledgements::only-the-announcements-audience-can-acknowledge-it
 *   @e2e dashboard-acknowledgements::a-share-recipient-of-a-personal-dashboard-must-confirm
 *   @e2e dashboard-acknowledgements::non-author-cannot-read-the-report
 *   @e2e dashboard-acknowledgements::a-version-that-is-not-published-yet-cannot-be-acknowledged
 *   @e2e dashboard-acknowledgements::without-a-template-the-users-own-copy-sets-the-current-version
 *   @e2e dashboard-acknowledgements::report-exports-as-csv-compliance-evidence
 *   @e2e dashboard-acknowledgements::read-the-report
 *   @e2e dashboard-acknowledgements::the-key-reaches-the-dashboard
 *   @e2e dashboard-acknowledgements::who-has-not-confirmed
 *
 * The last two are the delta scenarios of the open change
 * `read-receipts-dialog` (REQ-ACK-009, REQ-ACK-010).
 *
 * @spec openspec/specs/dashboard-acknowledgements/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { AckPlacement, DashboardRef } from './fixtures/acknowledgements.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { randomUUID } from 'crypto'
import * as fs from 'fs'
import {
	ACKS,
	activate,
	addTextWidget,
	createPersonalDashboard,
	forkAndActivate,
	isoDate,
	pendingFor,
	placementWithText,
	postAcknowledgement,
	promptSelector,
	readUserDashboardsFlag,
	reportOf,
	setUserDashboardsFlag,
	share,
	updatePlacement,
} from './fixtures/acknowledgements.ts'
import { loginAs } from './fixtures/secondary-user.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const NOTICE = `Integriteitscode ${RUN}`
const OTHER = `Kantine ${RUN}`
const PROMPT = 'Ik heb de 2026 integriteitscode gelezen en begrepen'
const PAST_DEADLINE = isoDate(-2)
const PROFILE_DEFINITIONS =
	'/index.php/apps/launchpad/api/profile-fields/definitions'
const PROFILE_OWN = '/index.php/apps/launchpad/api/profile-fields/me'
const GUARD_MESSAGE =
	'Only an admin or the template owner may set an acknowledgement requirement'

let admin: APIRequestContext
let userDashboardsBefore: boolean | null = null
let tom: Principal | null = null
let alice: Principal | null = null
let bob: Principal | null = null
let carol: Principal | null = null
let dave: Principal | null = null
let erin: Principal | null = null
let pieter: Principal | null = null
let olaf: Principal | null = null

let source: DashboardRef | null = null
let original: AckPlacement
let KEY = ''
const forks: Record<string, DashboardRef> = {}
const copies: Record<string, AckPlacement> = {}
let firstReceipt: { id: number; acknowledgedAt: string } | null = null

test.describe.configure({ mode: 'serial' })
// Fresh accounts build their home folder on first use.
test.setTimeout(180_000)

/**
 * Fork tom's dashboard as `who` and record the fork and its copy of the notice.
 *
 * @param who the principal.
 * @return the copy of the notice.
 */
async function forkFor(who: Principal): Promise<AckPlacement> {
	forks[who.username] = await forkAndActivate(who, source!.uuid, `Fork ${RUN}`)
	const copy = await placementWithText(who.api, forks[who.username].id, NOTICE)
	copies[who.username] = copy
	return copy
}

test.beforeAll(async () => {
	test.setTimeout(600_000)
	admin = await adminContext()
	userDashboardsBefore = await readUserDashboardsFlag(admin)
	await setUserDashboardsFlag(admin, true)

	tom = await createPrincipal(admin, 'e2e-ack-tom')
	alice = await createPrincipal(admin, 'e2e-ack-alice')
	bob = await createPrincipal(admin, 'e2e-ack-bob')
	carol = await createPrincipal(admin, 'e2e-ack-carol')
	dave = await createPrincipal(admin, 'e2e-ack-dave')
	erin = await createPrincipal(admin, 'e2e-ack-erin')
	pieter = await createPrincipal(admin, 'e2e-ack-pieter')
	olaf = await createPrincipal(admin, 'e2e-ack-olaf')

	source = await createPersonalDashboard(tom, `Intranet ${RUN}`)
	await activate(tom.api, source.uuid)
	const noticeId = await addTextWidget(tom.api, source.id, NOTICE, 0)
	await addTextWidget(tom.api, source.id, OTHER, 4)

	// Tom owns the dashboard and the placement carries no key yet, so he may
	// ask for the confirmation (PermissionService::canManageAcknowledgement).
	const asked = await updatePlacement(tom.api, noticeId, {
		requiresAcknowledgement: 1,
		acknowledgementPrompt: PROMPT,
		acknowledgementDeadline: PAST_DEADLINE,
	})
	expect(asked.status(), await asked.text()).toBe(200)
	original = await placementWithText(tom.api, source.id, NOTICE)
	KEY = String(original.announcementKey ?? '')
	expect(KEY, 'asking for a confirmation mints the announcement key').not.toBe('')

	for (const who of [alice, bob, carol, dave, erin, pieter]) {
		await publishForRecipients(tom.api, source.id)
		await share(tom.api, source.id, who.username, 'view_only')
	}
	for (const who of [alice, bob, carol, dave]) {
		const copy = await forkFor(who)
		expect(copy.announcementKey).toBe(KEY)
		expect(copy.requiresAcknowledgement).toBe(1)
		expect(copy.acknowledgementContentVersion).toBe(1)
	}
})

test.afterAll(async () => {
	for (const who of [alice, bob, carol, dave, erin]) {
		if (who !== null && forks[who.username]) {
			await who.api.delete(
				`/index.php/apps/launchpad/api/dashboard/${forks[who.username].id}`,
			)
		}
	}
	if (tom !== null && source !== null) {
		await tom.api.delete(`/index.php/apps/launchpad/api/dashboard/${source.id}`)
	}
	for (const who of [tom, alice, bob, carol, dave, erin, pieter, olaf]) {
		await removePrincipal(admin, who)
	}
	if (userDashboardsBefore !== null) {
		await setUserDashboardsFlag(admin, userDashboardsBefore)
	}
	await admin.dispose()
})

// @e2e dashboard-acknowledgements::unacknowledged-item-blocks-with-a-sign-off-prompt
test('an unacknowledged notice blocks the widget with its sign-off sentence and nothing to dismiss it', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		alice!.username,
		alice!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const prompt = page.locator(promptSelector(copies[alice!.username].id))
		await expect(prompt).toBeVisible({ timeout: 30_000 })

		// THEN: the prompt text and a sign-off affordance …
		await expect(
			prompt.locator('[data-testid="acknowledgement-prompt-text"]'),
		).toHaveText(PROMPT)
		await expect(
			prompt.locator('[data-testid="acknowledgement-signoff"]'),
		).toHaveCount(1)

		// AND: … and no other control at all — no dismiss, close or snooze.
		await expect(prompt.locator('button')).toHaveCount(1)
		await expect(
			prompt.locator(
				'[data-testid*="dismiss"], [data-testid*="close"], [data-testid*="snooze"]',
			),
		).toHaveCount(0)

		// AND: the dashboard reports at least one outstanding item.
		const pending = await pendingFor(alice!.api)
		expect(pending.count).toBeGreaterThanOrEqual(1)
		expect(pending.items.map((i) => i.announcementKey)).toContain(KEY)
		await expect(
			page.locator('[data-testid="acknowledgement-outstanding-count"]'),
		).toHaveText(new RegExp(`\\b${pending.count} items? to acknowledge`))
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::deadline-is-presented-but-does-not-auto-acknowledge
test('a passed deadline is shown as overdue but the notice still needs an explicit sign-off', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		alice!.username,
		alice!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const prompt = page.locator(promptSelector(copies[alice!.username].id))
		await expect(prompt).toBeVisible({ timeout: 30_000 })

		// The deadline (two days ago) is presented, marked overdue …
		const deadline = prompt.locator(
			'[data-testid="acknowledgement-prompt-deadline"]',
		)
		await expect(deadline).toContainText(PAST_DEADLINE)
		await expect(deadline).toHaveClass(/launchpad-ack-prompt__deadline--overdue/)

		// THEN: … and the prompt still requires the explicit sign-off: nothing
		// was acknowledged on her behalf.
		await expect(
			prompt.locator('[data-testid="acknowledgement-signoff"]'),
		).toBeVisible()
		const pending = await pendingFor(alice!.api)
		expect(pending.items.map((i) => i.announcementKey)).toContain(KEY)

		// AND: the item is reportable as overdue in the read-receipt report.
		const report = await reportOf(tom!.api, KEY)
		expect(report.deadline).toBe(PAST_DEADLINE)
		expect(report.overdue).toBe(true)
		expect(report.pending).toContain(alice!.username)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::first-acknowledgement-writes-exactly-one-receipt
test('signing off writes one receipt and answers with its timestamp', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		alice!.username,
		alice!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const prompt = page.locator(promptSelector(copies[alice!.username].id))
		await expect(prompt).toBeVisible({ timeout: 30_000 })

		const posted = page.waitForResponse(
			(r) =>
				// The page posts to /apps/..., without the /index.php in ACKS.
				new URL(r.url()).pathname.endsWith('/apps/launchpad/api/acknowledgements')
				&& r.request().method() === 'POST',
		)
		await prompt.locator('[data-testid="acknowledgement-signoff"]').click()
		const response = await posted

		// AND: success with the stored acknowledgedAt.
		expect(response.status(), await response.text()).toBe(200)
		const receipt = await response.json()
		expect(receipt).toMatchObject({
			announcementKey: KEY,
			userId: alice!.username,
			contentVersion: 1,
		})
		expect(String(receipt.acknowledgedAt ?? '')).not.toBe('')
		firstReceipt = {
			id: Number(receipt.id),
			acknowledgedAt: receipt.acknowledgedAt,
		}
		await expect(prompt).toHaveCount(0)

		// THEN: exactly one receipt (ak-1, alice, 1, <now>) — the report holds
		// one acknowledged entry for her, with that timestamp.
		const report = await reportOf(tom!.api, KEY)
		expect(
			report.acknowledged.filter((a) => a.userId === alice!.username),
		).toEqual([
			{ userId: alice!.username, acknowledgedAt: receipt.acknowledgedAt },
		])
		expect(report.rows.filter((r) => r.userId === alice!.username)).toHaveLength(
			1,
		)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::already-acknowledged-item-renders-normally
test('once acknowledged, the widget renders its own content and is not counted', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		alice!.username,
		alice!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const cell = page.locator(
			`[data-placement-id="${copies[alice!.username].id}"]`,
		)
		await expect(cell).toContainText(NOTICE, { timeout: 30_000 })

		// THEN: normal content, no forced-delivery prompt …
		await expect(
			page.locator(promptSelector(copies[alice!.username].id)),
		).toHaveCount(0)

		// AND: … and the outstanding count does not include it.
		const pending = await pendingFor(alice!.api)
		expect(pending.items.map((i) => i.announcementKey)).not.toContain(KEY)
		expect(pending.count).toBe(pending.items.length)
		await expect(
			page.locator('[data-testid="acknowledgement-outstanding-count"]'),
		).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-acknowledgements::repeated-acknowledgement-is-idempotent
test('acknowledging again returns the original receipt', async () => {
	expect(firstReceipt, 'the sign-off test recorded a receipt').not.toBeNull()
	const again = await postAcknowledgement(alice!.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(again.status(), await again.text()).toBe(200)
	const body = await again.json()

	// THEN: no second row — the answer IS the first row, same id …
	expect(Number(body.id)).toBe(firstReceipt!.id)
	// AND: … with the original acknowledgedAt unchanged.
	expect(body.acknowledgedAt).toBe(firstReceipt!.acknowledgedAt)
	const report = await reportOf(tom!.api, KEY)
	expect(report.acknowledged.filter((a) => a.userId === alice!.username)).toEqual([
		{ userId: alice!.username, acknowledgedAt: firstReceipt!.acknowledgedAt },
	])
})

// @e2e dashboard-acknowledgements::a-user-cannot-acknowledge-on-behalf-of-another-user
test('alice cannot sign off for bob', async () => {
	const refused = await postAcknowledgement(alice!.api, {
		announcementKey: KEY,
		contentVersion: 1,
		userId: bob!.username,
	})
	expect(refused.status(), await refused.text()).toBe(403)
	expect((await refused.json()).error).toBe(
		'Cannot acknowledge on behalf of another user',
	)

	// AND: no receipt for bob.
	const report = await reportOf(tom!.api, KEY)
	expect(report.pending).toContain(bob!.username)
	expect(report.acknowledged.map((a) => a.userId)).not.toContain(bob!.username)

	// Control: naming herself is accepted.
	const own = await postAcknowledgement(alice!.api, {
		announcementKey: KEY,
		contentVersion: 1,
		userId: alice!.username,
	})
	expect(own.status(), await own.text()).toBe(200)
	expect((await own.json()).userId).toBe(alice!.username)
})

// @e2e dashboard-acknowledgements::a-share-recipient-of-a-personal-dashboard-must-confirm
test('pieter, who sees tom\'s dashboard through a share but holds no copy, must confirm', async () => {
	// THEN: the notice is pending for pieter …
	const pending = await pendingFor(pieter!.api)
	expect(pending.items.map((i) => i.announcementKey)).toContain(KEY)
	expect(pending.items.filter((i) => i.announcementKey === KEY)).toHaveLength(1)

	// AND: … and he is pending in tom's read-receipt report.
	const report = await reportOf(tom!.api, KEY)
	expect(report.pending).toContain(pieter!.username)
	expect(report.rows.map((r) => r.userId)).not.toContain(olaf!.username)
})

// @e2e dashboard-acknowledgements::only-the-announcements-audience-can-acknowledge-it
test('someone outside the audience cannot acknowledge, not even by naming a member', async () => {
	// Olaf has no share of tom's dashboard and holds no copy of the notice, so
	// he is not in its audience.
	const outsider = await postAcknowledgement(olaf!.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(outsider.status(), await outsider.text()).toBe(403)
	expect((await outsider.json()).error).toBe(
		'This announcement is not addressed to you',
	)

	// … nor for a key no placement carries …
	const unknown = await postAcknowledgement(olaf!.api, {
		announcementKey: randomUUID(),
		contentVersion: 1,
	})
	expect(unknown.status(), await unknown.text()).toBe(403)

	// … and naming a member of the audience does not borrow her membership:
	// the audience is resolved for the session user.
	const borrowed = await postAcknowledgement(olaf!.api, {
		announcementKey: KEY,
		contentVersion: 1,
		userId: alice!.username,
	})
	expect(borrowed.status(), await borrowed.text()).toBe(403)

	// AND: no receipt was written — none for olaf, alice's unchanged.
	const report = await reportOf(tom!.api, KEY)
	expect(report.rows.map((r) => r.userId)).not.toContain(olaf!.username)
	expect(
		report.acknowledged.find((a) => a.userId === alice!.username)
			?.acknowledgedAt,
	).toBe(firstReceipt!.acknowledgedAt)

	// Control: a member of the audience may.
	const member = await postAcknowledgement(carol!.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(member.status(), await member.text()).toBe(200)
	expect((await member.json()).userId).toBe(carol!.username)
})

// @e2e dashboard-acknowledgements::non-author-cannot-read-the-report
test('a recipient who is not the author cannot read the report or its CSV', async () => {
	const report = await bob!.api.get(`${ACKS}/report/${KEY}`)
	expect(report.status(), await report.text()).toBe(403)
	const csv = await bob!.api.get(`${ACKS}/report/${KEY}/csv`)
	expect(csv.status(), await csv.text()).toBe(403)

	// Control: the author can.
	expect((await tom!.api.get(`${ACKS}/report/${KEY}`)).status()).toBe(200)
	expect((await tom!.api.get(`${ACKS}/report/${KEY}/csv`)).status()).toBe(200)
})

// @e2e dashboard-acknowledgements::a-recipient-cannot-change-the-requirement-on-their-copy
test("a recipient cannot change the requirement on their own copy; the first placement's owner can", async () => {
	const copy = copies[bob!.username]
	for (const change of [
		{ requiresAcknowledgement: 0 },
		{ reacknowledgeOnChange: 0 },
		{ acknowledgementContentVersion: 2 },
	]) {
		const refused = await updatePlacement(bob!.api, copy.id, change)
		expect(
			refused.status(),
			`${JSON.stringify(change)}: ${await refused.text()}`,
		).toBe(403)
		expect((await refused.json()).error).toBe(GUARD_MESSAGE)
	}

	// AND: the copy is unchanged.
	const after = await placementWithText(bob!.api, forks[bob!.username].id, NOTICE)
	expect(after).toMatchObject({
		requiresAcknowledgement: 1,
		reacknowledgeOnChange: 0,
		acknowledgementContentVersion: 1,
		announcementKey: KEY,
	})

	// AND: without a template the author is the owner of the first placement
	// that carried the key — tom, not bob, whose fork is a later copy.
	const author = await updatePlacement(tom!.api, original.id, {
		acknowledgementPrompt: PROMPT,
	})
	expect(author.status(), await author.text()).toBe(200)
})

// @e2e dashboard-acknowledgements::report-exports-as-csv-compliance-evidence
test('the report exports as CSV with one row per audience member', async () => {
	const ack = await postAcknowledgement(dave!.api, {
		announcementKey: KEY,
		contentVersion: 1,
	})
	expect(ack.status(), await ack.text()).toBe(200)

	const report = await reportOf(tom!.api, KEY)
	const res = await tom!.api.get(`${ACKS}/report/${KEY}/csv`)
	expect(res.status(), await res.text()).toBe(200)
	expect(res.headers()['content-type']).toContain('text/csv')
	const lines = (await res.text()).split('\r\n').filter((l) => l !== '')
	expect(lines[0]).toBe('user_id,status,acknowledged_at')

	// THEN: one row per audience member.
	const rows = lines.slice(1)
	const audience = [tom, alice, bob, carol, dave, erin, pieter]
		.map((p) => p!.username)
		.sort()
	expect(rows.map((l) => JSON.parse(`[${l}]`)[0]).sort()).toEqual(audience)

	const row = (user: string) => rows.find((l) => l.startsWith(`"${user}",`))
	const at = (user: string) =>
		report.acknowledged.find((a) => a.userId === user)!.acknowledgedAt
	// AND: alice's and carol's rows are acknowledged with their timestamps.
	expect(row(alice!.username)).toBe(
		`"${alice!.username}","acknowledged","${at(alice!.username)}"`,
	)
	expect(row(carol!.username)).toBe(
		`"${carol!.username}","acknowledged","${at(carol!.username)}"`,
	)
	// AND: bob's row is pending with an empty timestamp.
	expect(row(bob!.username)).toBe(`"${bob!.username}","pending",""`)
})

// @e2e dashboard-acknowledgements::read-the-report
// @e2e dashboard-acknowledgements::the-key-reaches-the-dashboard
// @e2e dashboard-acknowledgements::who-has-not-confirmed
test('the author opens Read confirmations on the dashboard: 3 of 7 confirmed, the 4 pending names with their department, a CSV', async ({
	browser,
}) => {
	// The administrator marks one profile field as the department
	// (REQ-ACK-010); bob and erin, two of the pending, fill it in. The
	// existing definitions are kept and restored, and no other field may be
	// the department at the same time.
	const definitions = await admin.get(PROFILE_DEFINITIONS)
	expect(definitions.status(), await definitions.text()).toBe(200)
	const definitionsBefore = ((await definitions.json()).fields ?? []) as Array<
		Record<string, unknown>
	>
	const department = {
		key: `e2e_ack_afdeling_${RUN}`,
		label: 'Afdeling',
		type: 'text',
		source: 'self',
		searchable: false,
		shownInWidget: false,
		visibility: 'everyone',
		department: true,
	}
	const marked = await admin.put(PROFILE_DEFINITIONS, {
		data: {
			fields: [
				...definitionsBefore.map((f) => ({ ...f, department: false })),
				department,
			],
		},
	})
	expect(marked.status(), await marked.text()).toBe(200)
	const departments: Record<string, string> = {
		[bob!.username]: `Burgerzaken ${RUN}`,
		[erin!.username]: `Vergunningen ${RUN}`,
	}
	for (const who of [bob, erin]) {
		const saved = await who!.api.put(PROFILE_OWN, {
			data: { values: { [department.key]: departments[who!.username] } },
		})
		expect(saved.status(), await saved.text()).toBe(200)
	}

	const { context, page } = await loginAs(browser, tom!.username, tom!.password)
	try {
		// THEN (REQ-ACK-009): the placement tom's dashboard loads carries its
		// announcementKey …
		const loaded = await placementWithText(tom!.api, source!.id, NOTICE)
		expect(loaded.announcementKey).toBe(KEY)
		await page.goto('/index.php/apps/launchpad/')
		const more = page.locator(MORE_MENU)
		await expect(more).toBeVisible({ timeout: 30_000 })
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		// … and Read receipts appears in the More menu, which it only does
		// when a loaded placement asks for confirmation and has its key.
		const item = page.locator(
			'[data-testid="launchpad-more-menu-item-read-receipts"]',
		)
		await expect(item).toBeVisible()
		await item.click()

		const report = page.locator('[data-testid="acknowledgement-report"]')
		await expect(report).toBeVisible({ timeout: 15_000 })
		await expect(report.locator('h2')).toHaveText(/^Read confirmations/)
		await expect(
			report.locator('[data-testid="ack-report-eyebrow"]'),
		).toHaveText(`Announcement · Intranet ${RUN}`)
		// THEN: 3 confirmed (alice, carol, dave) of the live audience of 7 …
		const summary = report.locator('[data-testid="ack-summary"]')
		await expect(summary).toContainText('3 of 7 confirmed')
		// … with 4 still to go (bob, tom, and the share recipients erin and
		// pieter).
		await expect(summary).toContainText('4 to go')
		await expect(report.locator('[data-testid="ack-bar"]')).toHaveAttribute(
			'aria-valuenow',
			String(Math.round((3 / 7) * 100)),
		)
		const rows = report.locator('tbody tr')
		await expect(rows).toHaveCount(7)
		for (const who of [bob, tom, erin, pieter]) {
			const row = report.locator(`[data-testid="ack-row-${who!.username}"]`)
			await expect(row).toContainText(who!.username)
			await expect(row).toContainText('Not yet')
			await expect(row).not.toContainText('Confirmed')
		}
		for (const who of [alice, carol, dave]) {
			const row = report.locator(`[data-testid="ack-row-${who!.username}"]`)
			await expect(row).toContainText(who!.username)
			await expect(row).toContainText('Confirmed')
			await expect(row).not.toContainText('Not yet')
		}

		// AND: "Not confirmed yet" narrows the list to exactly the 4 pending
		// names, and the note says so.
		await report.locator('[data-testid="ack-filter-pending"]').click()
		await expect(
			report.locator('[data-testid="ack-filter-pending"]'),
		).toHaveAttribute('aria-pressed', 'true')
		await expect(rows).toHaveCount(4)
		for (const who of [bob, tom, erin, pieter]) {
			await expect(
				report.locator(`[data-testid="ack-row-${who!.username}"]`),
			).toContainText('Not yet')
		}
		// … each with their department: the field's label heads the column,
		// bob and erin show their own value, tom and pieter filled in none.
		await expect(report.locator('thead th').nth(1)).toHaveText('Afdeling')
		for (const who of [bob, erin]) {
			await expect(
				report
					.locator(`[data-testid="ack-row-${who!.username}"] td`)
					.nth(1),
			).toHaveText(departments[who!.username])
		}
		for (const who of [tom, pieter]) {
			await expect(
				report
					.locator(`[data-testid="ack-row-${who!.username}"] td`)
					.nth(1),
			).toHaveText('—')
		}
		for (const who of [alice, carol, dave]) {
			await expect(
				report.locator(`[data-testid="ack-row-${who!.username}"]`),
			).toHaveCount(0)
		}
		await expect(report.locator('[data-testid="ack-shown"]')).toContainText(
			'4 of 7 shown',
		)

		// AND: the CSV downloads, one row per audience member.
		const downloading = page.waitForEvent('download')
		await report.locator('[data-testid="acknowledgement-report-csv"]').click()
		const download = await downloading
		const csv = fs.readFileSync((await download.path())!, 'utf-8')
		const lines = csv.split('\r\n').filter((l) => l !== '')
		expect(lines).toHaveLength(8)
		expect(lines).toContain(`"${bob!.username}","pending",""`)
	} finally {
		await context.close()
		await admin.put(PROFILE_DEFINITIONS, {
			data: { fields: definitionsBefore },
		})
	}
})

// @e2e dashboard-acknowledgements::without-a-template-the-users-own-copy-sets-the-current-version
test('without a template, a copy at version 2 can be signed off at 2 while a copy at 1 cannot', async () => {
	// Tom (the author) moves his notice to version 2; erin forks only now, so
	// her copy is at version 2 while bob's stays at 1. No template exists.
	const bumped = await updatePlacement(tom!.api, original.id, {
		acknowledgementContentVersion: 2,
	})
	expect(bumped.status(), await bumped.text()).toBe(200)
	const erinCopy = await forkFor(erin!)
	expect(erinCopy.acknowledgementContentVersion).toBe(2)
	const bobCopy = await placementWithText(
		bob!.api,
		forks[bob!.username].id,
		NOTICE,
	)
	expect(bobCopy.acknowledgementContentVersion).toBe(1)

	// THEN: erin's own copy sets the current version, so version 2 is accepted.
	const accepted = await postAcknowledgement(erin!.api, {
		announcementKey: KEY,
		contentVersion: 2,
	})
	expect(accepted.status(), await accepted.text()).toBe(200)
	expect(await accepted.json()).toMatchObject({
		userId: erin!.username,
		contentVersion: 2,
	})

	// Contrast: for bob, whose copy is at 1, version 2 is not published.
	const ahead = await postAcknowledgement(bob!.api, {
		announcementKey: KEY,
		contentVersion: 2,
	})
	expect(ahead.status(), await ahead.text()).toBe(400)
})

// @e2e dashboard-acknowledgements::a-version-that-is-not-published-yet-cannot-be-acknowledged
test('a version above the published one is refused and leaves no receipt; lower ones are accepted', async () => {
	// GIVEN: the announcement is at version 2 (erin's copy and tom's notice).
	const future = await postAcknowledgement(erin!.api, {
		announcementKey: KEY,
		contentVersion: 3,
	})
	expect(future.status(), await future.text()).toBe(400)
	expect((await future.json()).message).toBe(
		'contentVersion is not a published version of this announcement',
	)

	// AND: a version at or below 2 is still accepted.
	for (const version of [1, 2]) {
		const ok = await postAcknowledgement(erin!.api, {
			announcementKey: KEY,
			contentVersion: version,
		})
		expect(ok.status(), await ok.text()).toBe(200)
		expect((await ok.json()).contentVersion).toBe(version)
	}

	// THEN (no receipt written): once tom publishes version 3, erin is still
	// pending at it; a version-3 receipt from the refused call would show.
	const published = await updatePlacement(tom!.api, original.id, {
		acknowledgementContentVersion: 3,
	})
	expect(published.status(), await published.text()).toBe(200)
	const report = await reportOf(tom!.api, KEY)
	expect(report.contentVersion).toBe(3)
	expect(report.rows.find((r) => r.userId === erin!.username)).toEqual({
		userId: erin!.username,
		status: 'pending',
		acknowledgedAt: null,
	})
})
