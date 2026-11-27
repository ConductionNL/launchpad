/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Bookmark import limits and app discovery (tiles REQ-BMI-001, -003, -004,
 * REQ-DISC-001, -002).
 *
 * Bookmark import: a view-only share offers no import; an import larger than
 * the widget quota is refused with 409 and creates nothing; a file with too
 * many bookmarks is refused in the browser before anything is sent. The quota
 * is an admin setting, read first and put back.
 *
 * Discovery: the Nextcloud apps in "Choose the app" of the App or link tile form
 * (app-link-tile-widget REQ-APPTILE-004) are compared with the
 * account's own Nextcloud app menu (`/ocs/v2.php/core/navigation/apps`), one
 * app is placed by ticking it, and an app restricted to a group the account is
 * not in stays out while a member of that group sees it. Deck is used when the
 * instance has it; the CI instance may not, so the same steps then run on
 * another app from the account's own menu. The group restriction is put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::view-only-dashboard-offers-no-import
 *   @e2e tiles::import-too-large-for-the-quota
 *   @e2e tiles::oversized-file
 *   @e2e tiles::deck-is-discovered
 *   @e2e tiles::place-deck
 *   @e2e tiles::restricted-app-stays-hidden
 *
 * @spec openspec/specs/tiles/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode, MORE_MENU, switchToDashboard } from './support/dashboardSwitcher.ts'
import {
	readPlacements,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import {
	addToGroup,
	adminContext,
	API,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const STAMP = `${Date.now()}`

type NavApp = { id: string; name: string; href: string; icon: string }

let admin: APIRequestContext

/**
 * Enable an app for some groups only, or for everyone with `[]`. Nextcloud
 * 34 moved the app management into the `appstore` app's OCS API; older
 * servers keep the settings route.
 *
 * @param api an admin context.
 * @param appId the app.
 * @param groups the groups, or none for everyone.
 * @return the response.
 */
async function enableAppFor(api: APIRequestContext, appId: string, groups: string[]) {
	const ocs = await api.post('/ocs/v2.php/apps/appstore/api/v1/apps/enable', {
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
		data: { appId, groups },
	})
	if (ocs.status() !== 404) {
		return ocs
	}
	return api.post('/index.php/settings/apps/enable', {
		data: { appIds: [appId], groups },
	})
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
})

test.afterAll(async () => {
	await admin?.dispose()
})

/**
 * A Netscape bookmarks file.
 *
 * @param folders Folder name to bookmark count.
 * @return The file's text.
 */
function bookmarksFile(folders: Record<string, number>): string {
	const parts = Object.entries(folders).map(([name, count]) => {
		const links = Array.from(
			{ length: count },
			(_, i) =>
				`        <DT><A HREF="https://site${i}.example.invalid/">Site ${i}</A>`,
		)
		return `    <DT><H3>${name}</H3>\n    <DL><p>\n${links.join('\n')}\n    </DL><p>`
	})
	return [
		'<!DOCTYPE NETSCAPE-Bookmark-file-1>',
		'<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">',
		'<TITLE>Bookmarks</TITLE>',
		'<H1>Bookmarks</H1>',
		'<DL><p>',
		...parts,
		'</DL>',
	].join('\n')
}

/** A bookmarks file with one bookmark, only to open the import dialog. */
const OPENING_FILE = '<!DOCTYPE NETSCAPE-Bookmark-file-1>\n<DL><p><DT><A HREF="https://example.invalid/">Open</A></DL>'

/**
 * Open the import dialog the way a person does now that the dashboards
 * sidebar's row menu is gone (more-menu-favourites): open the dashboard
 * through More, edit mode, Add tiles, then a bookmarks file under "Import
 * bookmarks" (REQ-BMI-001).
 *
 * @param page The page.
 * @param name The dashboard name.
 * @return The dialog.
 */
async function openImport(page: Page, name: string): Promise<Locator> {
	await switchToDashboard(page, name)
	await enterEditMode(page)
	await page.getByRole('button', { name: 'Add tiles' }).click()
	await page.locator('[data-testid="add-tiles-bookmark-file"]').setInputFiles({
		name: 'bookmarks.html',
		mimeType: 'text/html',
		buffer: Buffer.from(OPENING_FILE, 'utf-8'),
	})
	const dialog = page.locator('[data-testid="bookmark-import"]')
	await expect(dialog).toBeVisible()
	return dialog
}

// @e2e tiles::view-only-dashboard-offers-no-import
test('on a view-only dashboard there is no way to "Import bookmarks"; with full permission Add tiles offers it', async ({
	browser,
}) => {
	const viewer = await createPrincipal(admin, 'e2e-bmi-viewer')
	const viewOnly = await seedDashboardWithWidget(admin, `E2E Organisatie ${STAMP}`)
	const full = await seedDashboardWithWidget(admin, `E2E Mijn werkplek ${STAMP}`)
	try {
		await publishForRecipients(admin, viewOnly.id)
		await shareWith(admin, viewOnly.id, viewer.username, 'view_only')
		await publishForRecipients(admin, full.id)
		await shareWith(admin, full.id, viewer.username, 'full')

		const active = await viewer.api.post(`${API}/dashboards/default`, {
			data: { uuid: viewOnly.uuid },
		})
		expect(active.status(), await active.text()).toBeLessThan(300)
		const { context, page } = await loginAs(
			browser,
			viewer.username,
			viewer.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			// View only: More offers no Edit, so there is no way to Add tiles
			// and its Import bookmarks.
			await switchToDashboard(page, `E2E Organisatie ${STAMP}`)
			await page.locator(`${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`).click()
			await expect(page.locator('[data-testid="launchpad-more-menu-item-set-default"]')).toBeVisible()
			await expect(page.locator('[data-testid="launchpad-more-menu-item-edit"]')).toHaveCount(0)
			await page.keyboard.press('Escape')

			const switched = await viewer.api.post(`${API}/dashboards/default`, {
				data: { uuid: full.uuid },
			})
			expect(switched.status(), await switched.text()).toBeLessThan(300)
			await page.goto('/index.php/apps/launchpad/')
			await switchToDashboard(page, `E2E Mijn werkplek ${STAMP}`)
			await enterEditMode(page)
			await page.getByRole('button', { name: 'Add tiles' }).click()
			await expect(page.locator('[data-testid="add-tiles-bookmarks"]')).toBeVisible()
		} finally {
			await context.close()
		}
	} finally {
		await admin.delete(`${API}/dashboard/${viewOnly.id}`)
		await admin.delete(`${API}/dashboard/${full.id}`)
		await removePrincipal(admin, viewer)
	}
})

// @e2e tiles::import-too-large-for-the-quota
test('an import that exceeds the widget quota answers 409, says how much room is left and creates nothing', async ({
	browser,
}) => {
	const settings = await admin.get(`${API}/admin/settings`)
	const current = await settings.json()
	const limitBefore = Number((current.data ?? current).maxWidgetsPerDashboard ?? 0)
	let own: OwnDashboard | undefined
	try {
		own = await setUpOwnDashboard('e2e-bmi-quota', `E2E quota werkplek ${STAMP}`)
		for (let i = 0; i < 18; i++) {
			const res = await own.owner.api.post(`${API}/dashboard/${own.id}/tile`, {
				data: {
					title: `Tegel ${i}`,
					linkType: 'url',
					linkValue: `https://t${i}.example.invalid/`,
					gridX: (i % 6) * 2,
					gridY: Math.floor(i / 6) * 2,
				},
			})
			expect(res.status(), await res.text()).toBe(201)
		}
		const limit = await admin.put(`${API}/admin/settings`, {
			data: { maxWidgetsPerDashboard: 20 },
		})
		expect(limit.status(), await limit.text()).toBe(200)

		const { context, page } = await loginAs(
			browser,
			own.owner.username,
			own.owner.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			await expect(page.locator('.tile-widget')).toHaveCount(18, {
				timeout: 30_000,
			})
			const dialog = await openImport(page, `E2E quota werkplek ${STAMP}`)
			await dialog
				.locator('[data-testid="bookmark-import-file"]')
				.setInputFiles({
					name: 'bookmarks.html',
					mimeType: 'text/html',
					buffer: Buffer.from(
						bookmarksFile({ Werk: 2, Privé: 2, Hobby: 2 }),
						'utf-8',
					),
				})
			await expect(
				dialog.locator('[data-testid="bookmark-import-folder"]'),
			).toHaveCount(3)
			for (const folder of ['Werk', 'Privé', 'Hobby']) {
				await expect(dialog.getByLabel(new RegExp(folder))).toBeChecked()
			}
			const refused = page.waitForResponse(
				(res) =>
					res.request().method() === 'POST'
					&& /\/api\/dashboard\/\d+\/tiles\/import/.test(res.url()),
			)
			await dialog.locator('[data-testid="bookmark-import-confirm"]').click()
			expect((await refused).status()).toBe(409)
			await expect(
				dialog.locator('[data-testid="bookmark-import-error"]'),
			).toHaveText(
				'This dashboard can take 2 more. Choose fewer folders or bookmarks.',
			)
		} finally {
			await context.close()
		}

		const placements = await readPlacements(own.owner.api, own.id)
		expect(placements).toHaveLength(18)
		expect(placements.some((p) => p.widgetId === 'container')).toBe(false)
	} finally {
		await admin.put(`${API}/admin/settings`, {
			data: { maxWidgetsPerDashboard: limitBefore },
		})
		await tearDownOwnDashboard(own)
	}
})

// @e2e tiles::oversized-file
test('a file with 3,500 bookmarks is refused in the dialog and nothing is sent', async ({
	browser,
}) => {
	let own: OwnDashboard | undefined
	try {
		own = await setUpOwnDashboard('e2e-bmi-large', `E2E large import ${STAMP}`)
		const { context, page } = await loginAs(
			browser,
			own.owner.username,
			own.owner.password,
		)
		try {
			let sent = 0
			page.on('request', (req) => {
				if (/\/tiles\/import/.test(req.url())) {
					sent++
				}
			})
			await page.goto('/index.php/apps/launchpad/')
			const dialog = await openImport(page, `E2E large import ${STAMP}`)
			await dialog
				.locator('[data-testid="bookmark-import-file"]')
				.setInputFiles({
					name: 'bookmarks.html',
					mimeType: 'text/html',
					buffer: Buffer.from(bookmarksFile({ Alles: 3_500 }), 'utf-8'),
				})
			await expect(
				dialog.locator('[data-testid="bookmark-import-error"]'),
			).toHaveText(
				'This file has more than 2,000 bookmarks. Export one folder at a time.',
			)
			await expect(
				dialog.locator('[data-testid="bookmark-import-folder"]'),
			).toHaveCount(0)
			await page.waitForTimeout(1_000)
			expect(sent).toBe(0)
		} finally {
			await context.close()
		}
		expect(await readPlacements(own.owner.api, own.id)).toHaveLength(0)
	} finally {
		await tearDownOwnDashboard(own)
	}
})

/**
 * The account's own Nextcloud app menu.
 *
 * @param api The account.
 * @return The menu entries.
 */
async function appMenu(api: APIRequestContext): Promise<NavApp[]> {
	const res = await api.get('/ocs/v2.php/core/navigation/apps?format=json')
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).ocs?.data ?? []) as NavApp[]
}

/**
 * Open the add flow (More menu, "Add widget"), choose the App or link tile and
 * then "An app": the "Choose the app" list that replaced the picker's App
 * tiles section (app-link-tile-widget REQ-APPTILE-004, board LpTegelToevoegen).
 *
 * @param page The page.
 * @return The "Choose the app" list.
 */
async function openAddFlow(page: Page): Promise<Locator> {
	await page
		.locator(MORE_MENU)
		.locator('[data-testid="launchpad-more-menu-trigger"]')
		.click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
	const picker = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(picker).toBeVisible({ timeout: 10_000 })
	await picker.getByTestId('widget-picker-type-tile').click()
	await picker.getByTestId('widget-picker-add').click()
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()
	const chooser = page.getByTestId('tile-app-chooser')
	await expect(chooser).toBeVisible({ timeout: 10_000 })
	await expect(chooser).toContainText('Choose the app')
	return chooser
}

/**
 * The row of a Nextcloud app in "Choose the app".
 *
 * @param chooser The "Choose the app" list.
 * @param name The app name.
 * @return The row.
 */
function discovered(chooser: Locator, name: string): Locator {
	return chooser
		.getByTestId('tile-app-row-nextcloud')
		.filter({ has: chooser.page().locator('.add-tiles__row-name').getByText(name, { exact: true }) })
}

test.describe('discovery', () => {
	let own: OwnDashboard
	let deckWasEnabled = true
	let app: NavApp

	test.beforeAll(async () => {
		const enabled = await admin.get(
			'/ocs/v1.php/cloud/apps?filter=enabled&format=json',
		)
		const list = ((await enabled.json()).ocs?.data?.apps ?? []) as string[]
		deckWasEnabled = list.includes('deck')
		if (!deckWasEnabled) {
			await admin.post('/ocs/v1.php/cloud/apps/deck')
		}
		own = await setUpOwnDashboard('e2e-disc', `E2E Mijn werkplek ${STAMP}`)
		const menu = (await appMenu(own.owner.api)).filter(
			(entry) => entry.id !== 'launchpad',
		)
		const chosen =
			menu.find((entry) => entry.id === 'deck')
			?? menu.find((entry) => entry.id !== 'files')
			?? menu[0]
		expect(chosen, 'the account has an app in its menu').toBeTruthy()
		app = chosen!
	})

	test.afterAll(async () => {
		await tearDownOwnDashboard(own)
		if (!deckWasEnabled) {
			await admin.delete('/ocs/v1.php/cloud/apps/deck')
		}
	})

	// @e2e tiles::deck-is-discovered
	test('every app in the account\'s app menu but LaunchPad is in "Choose the app" with its icon', async ({
		browser,
	}) => {
		const menu = (await appMenu(own.owner.api)).filter(
			(entry) => entry.id !== 'launchpad',
		)
		const served = await own.owner.api.get(`${API}/tiles/discover`)
		expect(served.status(), await served.text()).toBe(200)
		const apps = ((await served.json()).apps ?? []) as Array<{
			appId: string
			icon: string
		}>
		expect(apps.map((a) => a.appId).sort()).toEqual(menu.map((m) => m.id).sort())

		const { context, page } = await loginAs(
			browser,
			own.owner.username,
			own.owner.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			const picker = await openAddFlow(page)
			for (const entry of menu) {
				await expect(discovered(picker, entry.name)).toBeVisible()
			}
			await expect(discovered(picker, 'LaunchPad')).toHaveCount(0)
			const chosen = discovered(picker, app.name)
			const icon = apps.find((a) => a.appId === app.id)?.icon ?? ''
			expect(icon).not.toBe('')
			await expect(chosen.locator('.tile-app-chooser__icon')).toHaveAttribute('style', new RegExp(icon.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
		} finally {
			await context.close()
		}
	})

	// @e2e tiles::place-deck
	test('one click places the app as a tile at the bottom, and the add flow then marks it', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(
			browser,
			own.owner.username,
			own.owner.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			const picker = await openAddFlow(page)
			const entry = discovered(picker, app.name)
			await expect(entry).not.toContainText('On this dashboard')
			const placed = page.waitForResponse(
				(res) =>
					res.request().method() === 'POST'
					&& /\/api\/dashboard\/\d+\/widgets$/.test(res.url()),
			)
			await entry.getByRole('checkbox').check()
			await page.getByTestId('tile-editor-save').click()
			const response = await placed
			expect(response.status(), await response.text()).toBeLessThan(300)
			const id = Number((await response.json()).id)
			await expect(page.getByTestId('tile-app-chooser')).toBeHidden({ timeout: 10_000 })

			const tileLink = page.locator(
				`.grid-stack-item[gs-id="${id}"] a[data-testid="cn-app-tile"]`,
			)
			await expect(tileLink).toBeVisible({ timeout: 10_000 })
			await expect(tileLink).toHaveAttribute('title', app.name)
			await expect(tileLink).toHaveAttribute(
				'href',
				new RegExp(`/apps/${app.id}/?$`),
			)

			const stored = await readPlacements(own.owner.api, own.id)
			const tile = stored.find((p) => Number(p.id) === id)!
			expect(tile).toMatchObject({ widgetId: 'tile', content: { mode: 'app', appId: app.id } })
			const bottom = Math.max(
				0,
				...stored
					.filter((p) => Number(p.id) !== id)
					.map((p) => Number(p.gridY) + Number(p.gridHeight)),
			)
			expect(Number(tile.gridY)).toBe(bottom)
			expect(Number(tile.gridX)).toBe(0)

			const again = await openAddFlow(page)
			await expect(discovered(again, app.name)).toContainText(
				'On this dashboard',
			)
		} finally {
			await context.close()
		}
	})

	// @e2e tiles::restricted-app-stays-hidden
	test('an app enabled only for a group the account is not in is not discovered; a member sees it', async ({
		browser,
	}) => {
		const adminMenu = await appMenu(admin)
		const target = ['dashboard', 'photos', 'activity', 'deck']
			.map((id) => adminMenu.find((entry) => entry.id === id))
			.find(Boolean)
		expect(target, 'an app that can be limited to a group').toBeTruthy()
		const group = await createGroup(admin, `e2e-communicatie-${STAMP}`)
		let member: Principal | null = null
		try {
			member = await createPrincipal(admin, 'e2e-disc-member')
			await addToGroup(admin, member.username, group)
			const restrict = await enableAppFor(admin, target!.id, [group])
			expect(restrict.status(), await restrict.text()).toBe(200)

			// The member still sees it, so the hiding is about the group.
			const memberApps = await member.api.get(`${API}/tiles/discover`)
			expect(
				((await memberApps.json()).apps ?? []).map(
					(a: { appId: string }) => a.appId,
				),
			).toContain(target!.id)

			const ownApps = await own.owner.api.get(`${API}/tiles/discover`)
			expect(ownApps.status()).toBe(200)
			expect(
				((await ownApps.json()).apps ?? []).map(
					(a: { appId: string }) => a.appId,
				),
			).not.toContain(target!.id)

			const { context, page } = await loginAs(
				browser,
				own.owner.username,
				own.owner.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad/')
				const picker = await openAddFlow(page)
				await expect(picker.getByTestId('tile-app-row-nextcloud').first()).toBeVisible()
				await expect(discovered(picker, target!.name)).toHaveCount(0)
			} finally {
				await context.close()
			}
		} finally {
			await enableAppFor(admin, target!.id, [])
			await removePrincipal(admin, member)
			await removeGroup(admin, group)
		}
	})
})
