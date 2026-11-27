/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * app-link-tile-widget REQ-APPTILE-006: an old `app-tile` placement survives
 * the upgrade that turns it into an App or link tile in app mode.
 *
 * HOW AN UPGRADE IS STAGED. The conversion is the post-migration repair step
 * `ConvertTilesToAppOrLinkTiles` (appinfo/info.xml). `occ maintenance:repair`
 * runs every enabled app's post-migration steps, which is the part of an
 * upgrade this scenario is about; role-feature-permissions-upgrade.spec.ts
 * stages its upgrade the same way. The placement an older version stored is
 * written through the widget API, which still accepts the retired type
 * (`app-tile` stays a render alias), and the test checks it is stored as
 * such before the repair runs, so the run proves the conversion and not a
 * conversion on write.
 *
 * Gate-19 @e2e traceability:
 *   @e2e app-tile-widget::an-old-app-tile-survives-the-upgrade
 *
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { describeRun, runOcc } from './support/occ.ts'
import { contextFor } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

/** Where the old tile stands: column 3, row 2. */
const AT = { gridX: 3, gridY: 2, gridWidth: 2, gridHeight: 2 }

type Account = { username: string; password: string }

let account: Account | null = null
let dashboardId = 0
let placementId = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * An API context as the throwaway owner.
 *
 * @return the context; the caller disposes it.
 */
async function ownerApi(): Promise<APIRequestContext> {
	return contextFor(account!.username, account!.password)
}

/**
 * The stored placement of the old tile, read as the owner.
 *
 * @return the placement.
 */
async function readTile(): Promise<Record<string, any>> {
	const api = await ownerApi()
	try {
		const res = await api.get(`${API}/dashboard/${dashboardId}`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const placements = (body.placements ?? body.data?.placements ?? []) as Array<Record<string, any>>
		const tile = placements.find((p) => Number(p.id) === placementId)
		expect(tile, `placement ${placementId} is gone`).toBeTruthy()
		return tile!
	} finally {
		await api.dispose()
	}
}

/**
 * The content of a placement as an object, whether the API sends it as JSON text or parsed.
 *
 * @param placement the placement.
 * @return the content.
 */
function contentOf(placement: Record<string, any>): Record<string, any> {
	const content = placement.content ?? placement.contentArray ?? {}
	return typeof content === 'string' ? JSON.parse(content || '{}') : content
}

test.beforeAll(async () => {
	const admin = await contextFor(ADMIN.user, ADMIN.pass)
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-app-tile-upgrade')
	const api = await ownerApi()
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E old app tile', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		dashboardId = Number(dash.id)

		// GIVEN a dashboard with an app-tile for Files at column 3, row 2, coloured accent.
		const widget = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { widgetId: 'app-tile', ...AT, content: { appId: 'files', colour: 'accent', showCount: true } },
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		const placed = await widget.json()
		placementId = Number((placed.placement ?? placed.data ?? placed).id)
		expect(placementId).toBeGreaterThan(0)

		const activate = await api.post(`${API}/dashboard/${dashboardId}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/default`, { data: { uuid: dash.uuid } })
		expect(preference.status(), await preference.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

// @e2e app-tile-widget::an-old-app-tile-survives-the-upgrade
test('an old app tile for Files is still at column 3, row 2 in the accent colour with the same id after the repair step', async ({ browser }) => {
	const before = await readTile()
	expect(before.widgetId, 'the fixture is not stored as an old app tile').toBe('app-tile')

	const owner = await loginAs(browser, account!.username, account!.password)
	let colourBefore: string
	try {
		await owner.page.goto('/index.php/apps/launchpad')
		const files = owner.page.locator('[data-testid="cn-app-tile"][href*="/apps/files"]')
		await expect(files).toBeVisible({ timeout: 30_000 })
		colourBefore = await files.evaluate((el) => getComputedStyle(el).backgroundColor)
	} finally {
		await owner.context.close()
	}

	// WHEN the repair step runs.
	const repair = await runOcc('maintenance:repair')
	expect(repair.code, describeRun(repair)).toBe(0)
	expect(repair.stdout).toContain('Turn app tiles and link tiles into App or link tiles')

	// THEN the same placement is an App or link tile in app mode, everything else kept.
	const after = await readTile()
	expect(after.widgetId).toBe('tile')
	const content = contentOf(after)
	expect(content.mode).toBe('app')
	expect(content.appId).toBe('files')
	expect(content.colour).toBe('accent')
	expect(content.showCount).toBe(true)
	expect(Number(after.gridX)).toBe(AT.gridX)
	expect(Number(after.gridY)).toBe(AT.gridY)
	expect(Number(after.gridWidth)).toBe(AT.gridWidth)
	expect(Number(after.gridHeight)).toBe(AT.gridHeight)

	// AND the owner sees the tile for Files in the same colour.
	const again = await loginAs(browser, account!.username, account!.password)
	try {
		await again.page.goto('/index.php/apps/launchpad')
		const files = again.page.locator('[data-testid="cn-app-tile"][href*="/apps/files"]')
		await expect(files).toBeVisible({ timeout: 30_000 })
		await expect(files).toHaveCount(1)
		expect(await files.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(colourBefore)
	} finally {
		await again.context.close()
	}
})
