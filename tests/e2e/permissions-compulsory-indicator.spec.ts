/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A compulsory widget is marked as such in edit mode and only in edit mode
 * (permissions REQ-PERM-004, "Compulsory widget visual indicator").
 * Written for e2e-coverage-programme task 1.4.
 *
 * 🔴 EXPECTED RED until the product is fixed. The scenario was excluded as
 * `never built`. Read on development on 2026-11-25: `WidgetWrapper.vue` uses
 * `isCompulsory` only to hide the Delete option (`canRemove`) and renders no
 * lock icon or Required badge, so the edit-mode assertion below fails. The
 * view-mode half and the non-compulsory control pass today. The test is
 * written so that it goes green once the indicator exists.
 *
 * WHAT COUNTS AS THE INDICATOR. The scenario allows "a lock icon" or "a
 * Required badge" and names no selector, so none exists to bind to. The
 * locator accepts, inside the widget's grid cell:
 *   - an element with `data-testid="widget-compulsory-indicator"` (the name
 *     an implementation should use, so this test needs no further change);
 *   - visible text that is exactly Required / Compulsory / Locked (or the
 *     Dutch Verplicht / Vergrendeld);
 *   - an element whose title or accessible name says lock, required or
 *     compulsory.
 * The non-compulsory widget on the same dashboard is the control: the
 * locator must find nothing there. So an indicator drawn on every widget,
 * or a locator that matches ordinary widget chrome, does not pass.
 *
 * WHERE THE COMPULSORY WIDGETS COME FROM: the documented flow that
 * `permissions.spec.ts` uses. The admin installs the shipped `mijn-werkdag`
 * template (compulsory `header` and `attention`, the rest optional) at
 * `add_only` for a new group, and a new member of that group gets their own
 * copy on first asking `GET /api/dashboard`. Every placement is cloned with
 * its `isCompulsory` flag.
 *
 * Every account, group and template this file makes is removed in `afterAll`,
 * and the personal-dashboards setting is put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e permissions::compulsory-widget-visual-indicator
 *
 * @spec openspec/specs/permissions/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { readSettings, restoreSettings, writeSettings } from './support/adminSettings.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
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

const STAMP = Date.now()

interface Placement {
	id: number
	widgetId: string
	isCompulsory: number
	content?: Record<string, unknown>
}

interface Envelope {
	dashboard: { id: number; userId: string | null; basedOnTemplate: number | null }
	placements: Placement[]
	permissionLevel: string
}

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null
let group: string | null = null
let template: number | null = null
let member: Principal | null = null

/**
 * The compulsory marker inside one grid cell, by any of the forms the
 * scenario allows (see the header).
 *
 * @param cell the widget's grid cell.
 * @return a locator for the marker.
 */
function indicatorIn(cell: Locator): Locator {
	return cell
		.locator('[data-testid="widget-compulsory-indicator"]')
		.or(cell.getByText(/^\s*(required|compulsory|locked|verplicht|vergrendeld)\s*$/i))
		.or(cell.getByTitle(/\b(lock(ed)?|required|compulsory|verplicht)\b/i))
		.or(cell.getByRole('img', { name: /\b(lock(ed)?|required|compulsory|verplicht)\b/i }))
}

/**
 * The grid cell of one placement.
 *
 * @param page the dashboard page.
 * @param id the placement id.
 * @return the cell.
 */
function cellOf(page: Page, id: number): Locator {
	return page.locator(`.launchpad-grid-item[data-placement-id="${id}"]`)
}

test.describe.configure({ mode: 'serial', timeout: 180_000 })

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
	// A member is only handed a copy when personal dashboards are on.
	await writeSettings(admin, { allowUserDash: true })

	group = await createGroup(admin, `e2e-perm-ind-${STAMP}`)
	const installed = await admin.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{ data: { force: true } },
	)
	expect(installed.status(), await installed.text()).toBe(201)
	template = Number((await installed.json()).id)
	const targeted = await admin.put(`${API}/admin/templates/${template}`, {
		data: { targetGroups: [group], permissionLevel: 'add_only' },
	})
	expect(targeted.status(), await targeted.text()).toBe(200)

	member = await createPrincipal(admin, 'e2e-perm-ind')
	await assertActingAs(member.api, member.username)
	await addToGroup(admin, member.username, group)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	if (template !== null) {
		await admin.delete(`${API}/admin/templates/${template}`).catch(() => undefined)
	}
	await removePrincipal(admin, member)
	await removeGroup(admin, group)
	await restoreSettings(admin, baseline)
	await admin.dispose()
})

// @e2e permissions::compulsory-widget-visual-indicator
test('in edit mode a compulsory widget carries a lock or Required marker, and in view mode it does not', async ({
	browser,
}) => {
	// GIVEN a dashboard with both compulsory and non-compulsory widgets.
	const res = await member!.api.get(`${API}/dashboard`)
	expect(res.status(), await res.text()).toBe(200)
	const copy = (await res.json()) as Envelope
	expect(copy.dashboard.userId, 'the copy must belong to the member').toBe(
		member!.username,
	)
	expect(copy.dashboard.basedOnTemplate).toBe(template)
	expect(copy.permissionLevel).toBe('add_only')

	// A list or number whose register is not installed hides itself by design
	// (REQ-TMPL-022), so only placements that render are probed.
	const rendered = copy.placements.filter(
		(p) =>
			!(['object-list', 'stat'].includes(p.widgetId)
				&& p.content?.hideWhenUnavailable === true),
	)
	const locked = rendered.filter((p) => Number(p.isCompulsory) === 1)
	const loose = rendered.find(
		(p) => Number(p.isCompulsory) === 0 && !p.widgetId.startsWith('tile'),
	)
	expect(locked.length, 'the copy must carry compulsory widgets').toBeGreaterThan(0)
	expect(loose, 'a non-compulsory widget for the control').toBeTruthy()

	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 60_000 })
		for (const p of [...locked, loose!]) {
			await expect(
				cellOf(page, p.id),
				`${p.widgetId} (placement ${p.id}) must be rendered`,
			).toHaveCount(1, { timeout: 30_000 })
		}

		// View mode: no marker on any widget.
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
		for (const p of locked) {
			await expect(
				indicatorIn(cellOf(page, p.id)),
				`${p.widgetId} shows a compulsory marker outside edit mode`,
			).toHaveCount(0)
		}

		// WHEN the dashboard is rendered in edit mode.
		await enterEditMode(page)

		// THEN each compulsory widget shows the marker.
		for (const p of locked) {
			await expect(
				indicatorIn(cellOf(page, p.id)).first(),
				`compulsory ${p.widgetId} (placement ${p.id}) shows no lock or Required marker in edit mode`,
			).toBeVisible({ timeout: 10_000 })
		}
		// Control: the non-compulsory widget shows none, so the marker is about
		// the flag and not drawn on every widget.
		await expect(
			indicatorIn(cellOf(page, loose!.id)),
			`non-compulsory ${loose!.widgetId} shows a compulsory marker`,
		).toHaveCount(0)
	} finally {
		await context.close()
	}
})
