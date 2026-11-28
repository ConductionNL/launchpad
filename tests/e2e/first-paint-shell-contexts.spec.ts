/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first-paint shell in the contexts a dashboard is opened from
 * (widget-declared-placeholders task 4, first-paint-skeleton task 9): several
 * Nextcloud widgets on one dashboard, a dashboard shared view-only, a group
 * template dashboard, and a visibility rule that hides a widget from the
 * viewer. In each the shell draws one frame per widget the viewer gets, in the
 * widget's declared shape, and no frame for a widget the rule hides.
 *
 * The scripts are blocked for the shell checks, so the server's frames stay;
 * one normal load per context shows the page takes over.
 *
 * Gate-19 @e2e traceability:
 *   @e2e first-paint-shell::a-view-only-share-draws-only-the-widgets-the-viewer-gets
 *   @e2e first-paint-shell::a-group-template-dashboard-draws-its-frames
 *
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

import type { APIRequestContext, Browser } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { createGroupDashboard } from './support/dashboardsSpec.ts'
import {
	addToGroup,
	adminContext,
	API,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	shareWith,
} from './support/principals.ts'
import { addTile, seedOwnDashboard, seedRule } from './support/visibilityRules.ts'

const RUN = Date.now()
const RULE_GROUP = `fpc-staff-${RUN}`
const TEMPLATE_GROUP = `fpc-team-${RUN}`
const ANCHOR = `FPC anchor ${RUN}`
const SUBJECT = `FPC hidden ${RUN}`
const TEMPLATE_TILE = `FPC template ${RUN}`

/** Nextcloud widgets shipped with a bare server, plus launchpad's own; none but launchpad_links declares a shape. */
const NC_WIDGETS: Array<{ ncId: string, shape: string, id?: number }> = [
	{ ncId: 'activity', shape: 'rows' },
	{ ncId: 'recommendations', shape: 'rows' },
	{ ncId: 'user_status', shape: 'rows' },
	{ ncId: 'launchpad_links', shape: 'rows' },
]

let admin: APIRequestContext
let owner: Principal
let viewer: Principal
let member: Principal
let shared: { id: number, uuid: string }
let anchorId: number
let subjectId: number
let templateTileId: number
let templateNcId: number
let templateUuid = ''

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * Add an nc-widget placement showing Nextcloud widget `ncId`.
 *
 * @param api the owner's context.
 * @param dashboardId the dashboard.
 * @param ncId the Nextcloud widget id.
 * @param gridX the column.
 * @return the placement id.
 */
async function addNcWidget(api: APIRequestContext, dashboardId: number, ncId: string, gridX: number): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: 'nc-widget', gridX, gridY: 4, gridWidth: 3, gridHeight: 5, content: { widgetId: ncId } },
	})
	expect(res.status(), `adding nc-widget ${ncId}: ${await res.text()}`).toBeLessThan(300)
	const body = await res.json()
	const id = Number((body.placement ?? body.data ?? body).id)
	expect(id, `no placement id for nc-widget ${ncId}`).toBeTruthy()
	return id
}

/**
 * Open launchpad as `who` with its scripts blocked and return the shell's frames.
 *
 * @param browser the browser.
 * @param who the account.
 * @return the placement id of each frame, with its shape.
 */
async function shellFrames(browser: Browser, who: Principal): Promise<Map<number, string>> {
	const { context, page } = await loginAs(browser, who.username, who.password)
	try {
		await page.route(/\/apps\/launchpad\/js\//, (route) => route.abort())
		await page.goto('/index.php/apps/launchpad/')
		const shell = page.locator('#launchpad-first-paint')
		await expect(shell).toBeVisible()
		const frames = await shell.locator('[data-placement-id]').evaluateAll((nodes) => nodes.map((node) => [
			Number(node.getAttribute('data-placement-id')),
			node.querySelector('.cn-widget-placeholder')?.getAttribute('data-shape') ?? '',
		]))
		return new Map(frames as Array<[number, string]>)
	} finally {
		await context.close()
	}
}

/**
 * Open launchpad as `who` normally and wait for the hand-over.
 *
 * @param browser the browser.
 * @param who the account.
 * @param visibleText a tile title that must show on the mounted page.
 */
async function handsOver(browser: Browser, who: Principal, visibleText: string): Promise<void> {
	const { context, page } = await loginAs(browser, who.username, who.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator('.launchpad-grid-item').filter({ hasText: visibleText }).first()).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('#launchpad-first-paint')).toHaveCount(0)
	} finally {
		await context.close()
	}
}

test.beforeAll(async () => {
	admin = await adminContext()
	await createGroup(admin, RULE_GROUP)
	await createGroup(admin, TEMPLATE_GROUP)
	owner = await createPrincipal(admin, 'fpc-owner')
	viewer = await createPrincipal(admin, 'fpc-viewer')
	member = await createPrincipal(admin, 'fpc-member')
	await addToGroup(admin, member.username, TEMPLATE_GROUP)

	// A personal dashboard with four Nextcloud widgets and a widget only RULE_GROUP sees, shared view-only.
	shared = await seedOwnDashboard(owner, `FPC shared ${RUN}`)
	anchorId = await addTile(owner.api, shared.id, ANCHOR, 0)
	subjectId = await addTile(owner.api, shared.id, SUBJECT, 1)
	await seedRule(owner.api, subjectId, { ruleType: 'group', ruleConfig: { groups: [RULE_GROUP] }, isInclude: true })
	for (const [index, entry] of NC_WIDGETS.entries()) {
		entry.id = await addNcWidget(owner.api, shared.id, entry.ncId, index * 3)
	}
	await publishForRecipients(owner.api, shared.id)
	await shareWith(owner.api, shared.id, viewer.username, 'view_only')
	const preference = await viewer.api.post(`${API}/dashboards/default`, { data: { uuid: shared.uuid } })
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	// A group template dashboard, made the group's default and the member's start dashboard.
	const template = await createGroupDashboard(admin, TEMPLATE_GROUP, `FPC template ${RUN}`)
	templateUuid = String(template.uuid)
	templateTileId = await addTile(admin, Number(template.id), TEMPLATE_TILE, 0)
	templateNcId = await addNcWidget(admin, Number(template.id), 'activity', 0)
	const promoted = await admin.post(`${API}/dashboards/group/${encodeURIComponent(TEMPLATE_GROUP)}/default`, { data: { uuid: templateUuid } })
	expect(promoted.status(), await promoted.text()).toBeLessThan(300)
	// The start page is Vandaag unless the member chose another (REVIEW item 1), so the member opens the template.
	const chosen = await member.api.post(`${API}/dashboards/default`, { data: { uuid: templateUuid } })
	expect(chosen.status(), await chosen.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (templateUuid !== '') {
		await admin.delete(`${API}/dashboards/group/${encodeURIComponent(TEMPLATE_GROUP)}/${templateUuid}`).catch(() => null)
	}
	for (const who of [owner, viewer, member]) {
		if (who) {
			await removePrincipal(admin, who.username).catch(() => null)
		}
	}
	await removeGroup(admin, RULE_GROUP).catch(() => null)
	await removeGroup(admin, TEMPLATE_GROUP).catch(() => null)
	await admin.dispose()
})

// @e2e first-paint-shell::a-view-only-share-draws-only-the-widgets-the-viewer-gets
test('a view-only share draws a frame per widget the viewer gets, in its shape, and none for a hidden widget', async ({ browser }) => {
	const frames = await shellFrames(browser, viewer)
	expect(frames.has(anchorId), 'CONTROL: the rule-free tile has a frame').toBe(true)
	expect(frames.get(anchorId)).toBe('tiles')
	expect(frames.has(subjectId), 'the widget the rule hides has no frame').toBe(false)
	for (const entry of NC_WIDGETS) {
		expect(frames.get(entry.id as number), `nc-widget ${entry.ncId}`).toBe(entry.shape)
	}
	await handsOver(browser, viewer, ANCHOR)
})

// @e2e first-paint-shell::a-group-template-dashboard-draws-its-frames
test('a group template dashboard draws its frames for a member', async ({ browser }) => {
	const frames = await shellFrames(browser, member)
	expect(frames.get(templateTileId), 'the template tile').toBe('tiles')
	expect(frames.get(templateNcId), 'the template Nextcloud widget').toBe('rows')
	await handsOver(browser, member, TEMPLATE_TILE)
})
