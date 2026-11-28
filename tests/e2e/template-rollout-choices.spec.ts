/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rollout window on the Templates page (board LpBeheerSjablonen) and the
 * two strategies it adds (admin-templates REQ-RESYNC-006..008).
 *
 * HOW A DIFFERENCE IS STAGED. As in admin-templates-resync.spec.ts: the widget
 * API cannot change an admin template, so "the template gained a widget" is
 * staged by the member removing that widget from their copy, and "the person
 * moved a widget" by the member moving it. The re-sync sees the same diff.
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::the-admin-opens-the-rollout-window
 *   @e2e admin-templates::two-new-widgets-reach-every-copy
 *   @e2e admin-templates::alice-moved-the-news-widget
 *
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { PlacementRow, SeedWidget, TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	activeDashboard,
	deleteTemplates,
	importTemplate,
	placementsOf,
	readAllowUserDashboards,
	setAllowUserDashboards,
	textWidget,
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
const STAMP = `E2E Rollout ${RUN}`
const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad#templates`
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

let admin: APIRequestContext
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

interface Copy {
	who: Principal
	dashboardId: number
}

/** A template for a fresh group and one member who holds a copy of it. */
async function distributed(
	tag: string,
	widgets: SeedWidget[],
): Promise<{ template: TemplateRow; copy: Copy }> {
	const g = await createGroup(admin, `e2e-rollout-${tag}-${RUN}`)
	groups.push(g)
	const template = await importTemplate(admin, `${STAMP} ${tag}`, {
		widgets,
		targetGroups: [g],
		permissionLevel: 'add_only',
	})
	templates.push(template.id)

	const who = await createPrincipal(admin, `e2e-rollout-${tag}`)
	principals.push(who)
	await addToGroup(admin, who.username, g)
	const first = await activeDashboard(who.api)
	expect(first.dashboard.basedOnTemplate, `${who.username} must hold a copy`).toBe(
		template.id,
	)
	return { template, copy: { who, dashboardId: first.dashboard.id } }
}

/** The placement titled `title`. */
function byTitle(ps: PlacementRow[], title: string): PlacementRow {
	const found = ps.find((p) => p.customTitle === title)
	if (found === undefined) {
		throw new Error(`no placement titled ${title}`)
	}
	return found
}

/** The member moves one of their copy's widgets. */
async function memberMoves(copy: Copy, title: string, gridY: number): Promise<void> {
	const p = byTitle(await placementsOf(copy.who.api, copy.dashboardId), title)
	const res = await copy.who.api.put(`${API}/widgets/${p.id}`, {
		data: { gridY, version: p.version },
	})
	expect(res.status(), `moving ${title} answered ${res.status()}`).toBe(200)
}

/** The member removes one of their copy's widgets. */
async function memberRemoves(copy: Copy, title: string): Promise<void> {
	const p = byTitle(await placementsOf(copy.who.api, copy.dashboardId), title)
	const res = await copy.who.api.delete(`${API}/widgets/${p.id}`)
	expect(res.status(), `removing ${title} answered ${res.status()}`).toBe(200)
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

test.describe('the rollout window (REQ-RESYNC-008, REQ-RESYNC-006)', () => {
	// @e2e admin-templates::the-admin-opens-the-rollout-window
	// @e2e admin-templates::two-new-widgets-reach-every-copy
	test('Only add new widgets brings back the missing widget and leaves the moved one where the member put it', async ({
		page,
	}) => {
		const one = `${STAMP} one`
		const birthdays = `${STAMP} Birthdays`
		const { template, copy } = await distributed('addonly', [
			textWidget(one, { x: 0, y: 0 }),
			textWidget(birthdays, { x: 4, y: 0 }),
		])
		await memberRemoves(copy, birthdays)
		await memberMoves(copy, one, 7)

		await openTemplatesPage(page)
		const row = page.locator('.launchpad-admin__templates .launchpad-admin__template', {
			has: page.getByText(template.name, { exact: true }),
		})
		await row.getByTestId('admin-resync-template').click()

		const dialog = page.getByTestId('template-resync-dialog')
		await expect(dialog.getByTestId('template-resync-title')).toHaveText(
			'Push changes to 1 dashboards',
		)
		await expect(dialog.getByTestId('template-resync-choice-merge')).toBeChecked()
		await expect(dialog.getByTestId('template-resync-choice-add_only')).not.toBeChecked()
		await expect(dialog.getByTestId('template-resync-choice-overwrite')).not.toBeChecked()
		await expect(dialog.getByTestId('template-resync-changed-count')).toHaveText(
			'1 people changed their dashboard.',
		)

		await dialog.getByTestId('template-resync-choice-add_only').check()
		const push = dialog.getByTestId('template-resync-apply')
		await expect(push).toHaveText('Push to 1 dashboards')
		await expect(push).toBeEnabled()
		const pushed = page.waitForResponse(
			(r) =>
				r.request().method() === 'POST'
				&& /\/api\/admin\/templates\/\d+\/resync/.test(r.url())
				&& r.request().postDataJSON()?.dryRun === false,
		)
		await push.click()
		expect((await pushed).status()).toBeLessThan(300)
		// The templates page closes the window once the push is done
		// (TemplatesPage closes it on `resynced`).
		await expect(dialog).toBeHidden({ timeout: 10_000 })

		const after = await placementsOf(copy.who.api, copy.dashboardId)
		expect(after.filter((p) => p.customTitle === birthdays)).toHaveLength(1)
		expect(byTitle(after, one).gridY).toBe(7)
	})
})

test.describe('merge keeps the person\'s move (REQ-RESYNC-007)', () => {
	// @e2e admin-templates::alice-moved-the-news-widget
	test('a template widget the member moved stays where they put it under merge', async () => {
		const news = `${STAMP} News`
		const { template, copy } = await distributed('merge', [
			textWidget(`${STAMP} merge one`, { x: 0, y: 0 }),
			textWidget(news, { x: 4, y: 3 }),
		])
		await memberMoves(copy, news, 0)

		const res = await admin.post(`${API}/admin/templates/${template.id}/resync`, {
			data: { strategy: 'merge', dryRun: false },
		})
		expect(res.status()).toBe(200)

		const after = await placementsOf(copy.who.api, copy.dashboardId)
		expect(byTitle(after, news).gridY).toBe(0)
	})
})
