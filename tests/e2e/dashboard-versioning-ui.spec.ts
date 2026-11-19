/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The version-history dialog (REQ-VERSUI-001..003), driven through the
 * product's own controls: the start page's More menu, its "Versions" entry,
 * the dialog's "Save this version now" and a row's "Restore" with its
 * confirmation. Written for e2e-coverage-programme task 2.3.
 *
 * The version history is SEEDED over the API (`POST /api/dashboards/{uuid}/
 * versions`), which is exactly what the earlier blanket exclusion said could
 * not be done; everything a person does afterwards goes through the page.
 *
 * WHO. "Owner opens the history" is probed by a throwaway owner who is NOT an
 * administrator, because an administrator is shown the entry on any
 * dashboard and so proves nothing about ownership. "Colleague sees no entry"
 * is a throwaway non-admin the dashboard is shared with at view_only, with the
 * owning administrator's own page as the control that the entry exists at
 * all. The save and restore flows run as the administrator on a dashboard the
 * administrator owns.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-versioning::owner-opens-the-history
 *   @e2e dashboard-versioning::colleague-sees-no-entry
 *   @e2e dashboard-versioning::named-snapshot
 *   @e2e dashboard-versioning::restore-last-weeks-layout
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = `E2E Version UI ${Date.now()}`
const VERSIONS_ITEM = '[data-testid="cn-grouped-menu-item-versions"]'
const DIALOG = '[data-testid="version-history"]'
const ROW = '[data-testid="version-row"]'

let admin: APIRequestContext
let originalAllowUserDash: unknown = null

/**
 * Add a text widget carrying `text`.
 *
 * @param api a context that may edit the dashboard.
 * @param dashboardId the dashboard.
 * @param text the marker text.
 * @return the placement id.
 */
async function addTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

/**
 * Take an explicit snapshot as the admin.
 *
 * @param uuid the dashboard uuid.
 * @param note the note.
 * @return the new version number.
 */
async function snapshot(uuid: string, note: string): Promise<number> {
	const res = await admin.post(`${API}/dashboards/${uuid}/versions`, {
		data: { note },
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()).version.versionNumber
}

/**
 * Create a dashboard as a non-admin and make it that user's active one.
 *
 * `seedActiveDashboard` also flips an admin setting, which a non-admin may
 * not, so this does the same two activation calls without it.
 *
 * @param api the owner's context.
 * @param name the dashboard name.
 * @return the dashboard.
 */
async function createActiveDashboard(
	api: APIRequestContext,
	name: string,
): Promise<SeededDashboard> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBe(201)
	const body = await created.json()
	const dash = {
		id: Number(body.dashboard.id),
		uuid: String(body.dashboard.uuid),
		name,
	}
	const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const pref = await api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(pref.status(), await pref.text()).toBeLessThan(300)
	return dash
}

/**
 * Open the More menu and wait for the "This dashboard" group to render.
 *
 * @param page the start page.
 * @return resolves once the group is visible.
 */
async function openMoreMenu(page: Page): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await expect(
		page.locator('[data-testid="cn-grouped-menu-item-set-default"]'),
	).toBeVisible({
		timeout: 10_000,
	})
}

/**
 * Open the version-history dialog from the More menu.
 *
 * @param page the start page.
 * @return the dialog locator, once its rows have loaded.
 */
async function openVersionHistory(page: Page) {
	await openMoreMenu(page)
	// The entry appears once the menu has asked the server whether this
	// dashboard keeps versions.
	await page.locator(VERSIONS_ITEM).click({ timeout: 15_000 })
	const dialog = page.locator(DIALOG)
	await expect(dialog).toBeVisible({ timeout: 15_000 })
	return dialog
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	originalAllowUserDash = (await settings.json()).allowUserDashboards ?? null
})

test.afterAll(async () => {
	if (originalAllowUserDash !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: originalAllowUserDash },
		})
	}
	await admin.dispose()
})

test.describe('REQ-VERSUI-001 who sees "Version history"', () => {
	test('the owner opens the history and sees four versions newest first, with date and author', async ({
		browser,
	}) => {
		// @e2e dashboard-versioning::owner-opens-the-history
		test.setTimeout(120_000)
		let sanne: Principal | null = null
		let dash: SeededDashboard | null = null
		try {
			sanne = await createPrincipal(admin, 'e2e-versui-sanne')
			await assertActingAs(sanne.api, sanne.username)
			// Personal dashboards on (an admin setting), then "Mijn week" is
			// created by sanne and made her active one.
			const enable = await admin.put(`${API}/admin/settings`, {
				data: { allowUserDash: true },
			})
			expect(enable.status(), await enable.text()).toBe(200)
			dash = await createActiveDashboard(sanne.api, `${STAMP} Mijn week`)
			await addTextWidget(sanne.api, dash.id, 'Mijn week widget')
			for (let k = 1; k <= 4; k++) {
				expect(await snapshot(dash.uuid, `week ${k}`)).toBe(k)
			}

			const { context, page } = await loginAs(
				browser,
				sanne.username,
				sanne.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad/')
				const dialog = await openVersionHistory(page)

				const rows = dialog.locator(ROW)
				await expect(rows).toHaveCount(4)
				for (const [i, n] of [4, 3, 2, 1].entries()) {
					const cells = rows.nth(i).locator('td')
					await expect(cells.nth(0)).toHaveText(String(n))
					// Date: rendered, and not left as the raw `Y-m-d H:i:s` string.
					await expect(cells.nth(1)).toContainText(
						String(new Date().getFullYear()),
					)
					await expect(cells.nth(1)).not.toHaveText(/^\d{4}-\d{2}-\d{2} /)
					await expect(cells.nth(2)).toHaveText(ADMIN_USER)
					await expect(cells.nth(3)).toHaveText(`week ${n}`)
				}
			} finally {
				await context.close()
			}
		} finally {
			if (sanne !== null) {
				await removeSeededDashboard(sanne.api, dash)
			}
			await removePrincipal(admin, sanne)
		}
	})

	test('a colleague who can view but does not own the dashboard gets no entry', async ({
		browser,
		page,
	}) => {
		// @e2e dashboard-versioning::colleague-sees-no-entry
		test.setTimeout(120_000)
		let pieter: Principal | null = null
		let dash: SeededDashboard | null = null
		try {
			dash = await seedActiveDashboard(admin, `${STAMP} shared week`)
			await addTextWidget(admin, dash.id, 'shared widget')
			await snapshot(dash.uuid, 'shared history')

			pieter = await createPrincipal(admin, 'e2e-versui-pieter')
			await assertActingAs(pieter.api, pieter.username)
			await shareWith(admin, dash.id, pieter.username, 'view_only')
			// Pin the shared dashboard as pieter's, so his page opens on it.
			for (const pref of ['default', 'active']) {
				const res = await pieter.api.post(`${API}/dashboards/${pref}`, {
					data: { uuid: dash.uuid },
				})
				expect(res.status(), await res.text()).toBe(200)
			}
			const active = await pieter.api.get(`${API}/dashboard`)
			expect(active.status(), await active.text()).toBe(200)
			const activeBody = await active.json()
			expect(activeBody.dashboard.uuid).toBe(dash.uuid)
			expect(activeBody.isOwner).toBe(false)

			// Control: the owner's page does offer the entry on this dashboard,
			// so its absence below is about pieter, not a wrong locator.
			await page.goto('/index.php/apps/launchpad/')
			await openMoreMenu(page)
			await expect(page.locator(VERSIONS_ITEM)).toBeVisible({
				timeout: 15_000,
			})

			const { context, page: pieterPage } = await loginAs(
				browser,
				pieter.username,
				pieter.password,
			)
			try {
				await pieterPage.goto('/index.php/apps/launchpad/')
				await openMoreMenu(pieterPage)
				// He is on a dashboard he does not own: no Settings entry ...
				await expect(
					pieterPage.locator(
						'[data-testid="cn-grouped-menu-item-config"]',
					),
				).toHaveCount(0)
				// ... and no version history entry.
				await expect(pieterPage.locator(VERSIONS_ITEM)).toHaveCount(0)
			} finally {
				await context.close()
			}
		} finally {
			await removeSeededDashboard(admin, dash)
			await removePrincipal(admin, pieter)
		}
	})
})

test.describe('REQ-VERSUI-002 / REQ-VERSUI-003 saving and restoring from the dialog', () => {
	let dash: SeededDashboard | null = null

	test.afterEach(async () => {
		await removeSeededDashboard(admin, dash)
		dash = null
	})

	test('typing a note and saving puts a new top entry with that note', async ({
		page,
	}) => {
		// @e2e dashboard-versioning::named-snapshot
		dash = await seedActiveDashboard(admin, `${STAMP} named`)
		await addTextWidget(admin, dash.id, 'about to be rearranged')
		await snapshot(dash.uuid, 'an older version')

		await page.goto('/index.php/apps/launchpad/')
		const dialog = await openVersionHistory(page)
		await expect(dialog.locator(ROW)).toHaveCount(1)

		await dialog
			.getByLabel('Note for this version (optional)')
			.fill('before the reorganisation')
		const created = page.waitForResponse(
			(r) =>
				r.request().method() === 'POST'
				&& r.url().includes(`/api/dashboards/${dash!.uuid}/versions`),
		)
		await dialog.locator('[data-testid="version-save"]').click()
		expect((await created).status()).toBe(201)

		const rows = dialog.locator(ROW)
		await expect(rows).toHaveCount(2)
		await expect(rows.first().locator('td').nth(0)).toHaveText('2')
		await expect(rows.first().locator('td').nth(3)).toHaveText(
			'before the reorganisation',
		)

		// The server holds it too.
		const list = await admin.get(`${API}/dashboards/${dash.uuid}/versions`)
		const { versions } = await list.json()
		expect(versions[0]).toMatchObject({
			versionNumber: 2,
			note: 'before the reorganisation',
		})
	})

	test("restoring Friday's version asks with its date, reloads the layout and keeps Monday as pre-restore", async ({
		page,
	}) => {
		// @e2e dashboard-versioning::restore-last-weeks-layout
		dash = await seedActiveDashboard(admin, `${STAMP} restore`)
		const friday = await addTextWidget(admin, dash.id, 'Friday layout')
		expect(await snapshot(dash.uuid, 'Friday')).toBe(1)

		// Monday's change: Friday's widget goes, a Monday widget comes. It is
		// saved as version 2, as an editing session's save would, so version 1
		// is not the newest (restoring the newest version is a no-op).
		const removed = await admin.delete(`${API}/widgets/${friday}`)
		expect(removed.status(), await removed.text()).toBeLessThan(300)
		await addTextWidget(admin, dash.id, 'Monday layout')
		expect(await snapshot(dash.uuid, 'Monday')).toBe(2)

		await page.goto('/index.php/apps/launchpad/')
		const grid = page.locator('.grid-stack-item')
		await expect(grid.filter({ hasText: 'Monday layout' })).toHaveCount(1, {
			timeout: 30_000,
		})
		await expect(grid.filter({ hasText: 'Friday layout' })).toHaveCount(0)

		const dialog = await openVersionHistory(page)
		const fridayRow = dialog.locator(ROW).filter({ hasText: 'Friday' })
		const fridayDate = (await fridayRow.locator('td').nth(1).innerText()).trim()
		expect(fridayDate).not.toBe('')
		await fridayRow.locator('[data-testid="version-restore"]').click()

		// The confirmation names the version's date.
		const confirm = page.locator('[data-testid-modal="cn-confirm-dialog"]')
		await expect(confirm).toBeVisible()
		await expect(confirm).toContainText(fridayDate)
		const restored = page.waitForResponse(
			(r) =>
				r.request().method() === 'POST'
				&& r
					.url()
					.includes(`/api/dashboards/${dash!.uuid}/versions/1/restore`),
		)
		await page.locator('[data-testid="cn-confirm-dialog-confirm"]').click()
		expect((await restored).status()).toBe(200)

		// The list now holds a new top entry: the pre-restore copy.
		const top = dialog.locator(ROW).first()
		await expect(top.locator('td').nth(0)).toHaveText('3')
		await expect(top.locator('td').nth(3)).toHaveText(
			'Saved automatically before a restore',
		)

		// And that copy is Monday's layout.
		const v3 = await admin.get(`${API}/dashboards/${dash.uuid}/versions/3`)
		expect(v3.status()).toBe(200)
		const texts = JSON.parse((await v3.json()).snapshot).placements.map(
			(p: { content: { text: string } }) => p.content.text,
		)
		expect(texts).toEqual(['Monday layout'])

		// The dashboard reloaded with Friday's layout.
		await page.keyboard.press('Escape')
		await expect(grid.filter({ hasText: 'Friday layout' })).toHaveCount(1, {
			timeout: 15_000,
		})
		await expect(grid.filter({ hasText: 'Monday layout' })).toHaveCount(0)
	})
})
