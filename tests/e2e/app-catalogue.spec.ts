/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the app catalogue (launcher-app-catalogue,
 * task 10): the add path, group visibility and the decline path.
 *
 * Every account, group and catalogue entry here is created through the real
 * OCS provisioning and LaunchPad admin APIs with a run-unique name, and
 * removed again in `afterAll`. The member and the non-member are throwaway
 * NON-ADMIN accounts logged in through `loginAs()`, which starts from an
 * empty storage state, so neither one inherits the admin session.
 *
 * Gate-19 @e2e traceability (the scenario names of openspec/specs/app-catalogue):
 *   @e2e app-catalogue::administrator-adds-an-app-for-one-group
 *   @e2e app-catalogue::member-adds-the-app
 *   @e2e app-catalogue::non-member-does-not-see-it
 *   @e2e app-catalogue::pieter-asks-for-a-map-viewer
 *   @e2e app-catalogue::too-many-open-requests
 *   @e2e app-catalogue::request-declined-with-a-reason
 *   @e2e app-catalogue::request-approved
 *
 * @spec openspec/specs/app-catalogue/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { contextFor } from './support/principals.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const RUN = `${Date.now()}-${Math.floor(Math.random() * 10_000)}`
const GROUP = `e2e-catalogue-${RUN}`
const APP_NAME = `Zaaksysteem ${RUN}`
const APP_URL = `https://zaken.example.nl/${RUN}`
const REQUESTED_APP = `Kaartviewer ${RUN}`
const DECLINE_NOTE = 'Gebruik de kaartlaag in het zaaksysteem'
const ADMIN_APP_NAME = `Zaaksysteem beheer ${RUN}`
const APPROVED_APP = `Kaartviewer goedgekeurd ${RUN}`
const APPROVED_URL = `https://kaart.example.nl/${RUN}`
const LIMIT_PREFIX = `Limiet ${RUN}`

type Account = { username: string; password: string }

let admin: APIRequestContext
let member: Account | null = null
let outsider: Account | null = null
let entryUuid: string | null = null

/**
 * An API context authenticated as the given account.
 *
 * @param account the account, or the administrator when omitted.
 * @return the request context; the caller disposes it.
 */
async function apiAs(account?: Account): Promise<APIRequestContext> {
	return contextFor(account?.username ?? ADMIN.user, account?.password ?? ADMIN.pass)
}

/**
 * Give an account its own active dashboard, so the workspace shell and its
 * More menu render for it on a cold instance.
 *
 * @param account the account.
 * @return nothing.
 */
async function seedOwnDashboard(account: Account): Promise<void> {
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, {
			data: { name: `E2E Mijn werkplek ${RUN}` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/active`, {
			data: { uuid: dash.uuid },
		})
		expect(preference.status(), await preference.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
}

/**
 * Open LaunchPad and the add flow ("More" → "Add widget").
 *
 * @param page a page logged in as the account under test.
 * @return the picker dialog.
 */
async function openAddFlow(page: Page) {
	await page.goto('/index.php/apps/launchpad')
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * The parsed subjects of an account's Nextcloud notifications, or null
 * when the notifications app is not enabled.
 *
 * @param account the account, or the administrator when omitted.
 * @return the subjects, or null.
 */
async function notificationSubjects(account?: Account): Promise<string[] | null> {
	const api = await apiAs(account)
	try {
		const res = await api.get('/ocs/v2.php/apps/notifications/api/v2/notifications?format=json')
		if (res.status() === 404) {
			return null
		}
		expect(res.status(), await res.text()).toBe(200)
		return ((await res.json()).ocs?.data ?? []).map((entry: { subject: string }) => entry.subject)
	} finally {
		await api.dispose()
	}
}

/**
 * Open the administration page on the App catalogue tab.
 *
 * @param page a page with the administrator's credentials.
 * @return nothing.
 */
async function openCatalogueTab(page: Page): Promise<void> {
	await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	// REQ-ADM-032: the app catalogue is a section of Bediening (operations).
	await page.goto('/index.php/settings/admin/launchpad')
	const tab = adminTab(page, 'operations')
	await expect(tab).toBeVisible({ timeout: 20_000 })
	await tab.click()
	await expect(page.locator('[data-testid="app-catalogue-entries"]')).toBeVisible({ timeout: 10_000 })
}

test.beforeAll(async () => {
	admin = await apiAs()

	// Personal dashboards on, so the throwaway accounts can own one.
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const group = await admin.post('/ocs/v1.php/cloud/groups', {
		form: { groupid: GROUP },
	})
	expect(group.ok(), await group.text()).toBe(true)

	member = await provisionThrowawayUser('e2e-catalogue-member')
	outsider = await provisionThrowawayUser('e2e-catalogue-outsider')
	const join = await admin.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
		{ form: { groupid: GROUP } },
	)
	expect(join.ok(), await join.text()).toBe(true)

	const entry = await admin.post(`${API}/admin/catalogue`, {
		data: {
			name: APP_NAME,
			description: 'Cases for the front office',
			linkType: 'url',
			linkValue: APP_URL,
			targetGroups: [GROUP],
		},
	})
	expect(entry.status(), await entry.text()).toBe(201)
	entryUuid = (await entry.json()).app.uuid

	await seedOwnDashboard(member)
	await seedOwnDashboard(outsider)
})

test.afterAll(async () => {
	if (entryUuid) {
		await admin.delete(`${API}/admin/catalogue/${entryUuid}`)
	}
	// Leave no open request of this run behind for the next one.
	const listed = await admin.get(`${API}/admin/catalogue`)
	if (listed.ok()) {
		const { requests = [] } = await listed.json()
		const { apps = [] } = await (await admin.get(`${API}/admin/catalogue`)).json()
		for (const app of apps) {
			if (app.name === ADMIN_APP_NAME || app.name === APPROVED_APP) {
				await admin.delete(`${API}/admin/catalogue/${app.uuid}`)
			}
		}
		for (const open of requests) {
			if (open.appName === REQUESTED_APP || open.appName === APPROVED_APP || String(open.appName).startsWith(LIMIT_PREFIX)) {
				await admin.post(`${API}/admin/catalogue/requests/${open.uuid}/decline`, {
					data: { note: 'e2e cleanup' },
				})
			}
		}
	}
	for (const account of [member, outsider]) {
		if (account) {
			await deprovisionUser(account.username)
		}
	}
	await admin.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(GROUP)}`)
	await admin.dispose()
})

test.describe('app catalogue (REQ-ACAT-002, REQ-ACAT-003, REQ-ACAT-004)', () => {
	// @e2e app-catalogue::member-adds-the-app
	test('a group member adds the catalogue app as a tile from the add flow', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(browser, member!.username, member!.password)
		try {
			const dialog = await openAddFlow(page)
			await dialog.getByRole('textbox', { name: /search widgets/i }).fill('zaak')

			const section = dialog.locator('[data-testid="widget-picker-section-tiles"]')
			await expect(section).toBeVisible()
			await expect(section).toContainText('App tiles')

			const placed = page.waitForResponse((res) =>
				/\/api\/dashboard\/\d+\/tile$/.test(res.url())
				&& res.request().method() === 'POST',
			)
			await chooseAndAdd(section.getByRole('button', { name: new RegExp(APP_NAME) }))
			const response = await placed
			expect(response.status(), await response.text()).toBeLessThan(300)
			const sent = response.request().postDataJSON()
			expect(sent).toMatchObject({
				title: APP_NAME,
				linkType: 'url',
				linkValue: APP_URL,
			})

			await page.keyboard.press('Escape')
			await expect(
				page.locator('.launchpad-grid-item').filter({ hasText: APP_NAME }),
			).toBeVisible({ timeout: 10_000 })
		} finally {
			await context.close()
		}
	})

	// @e2e app-catalogue::non-member-does-not-see-it
	test('a non-member neither sees the entry nor gets it from the API', async ({
		browser,
	}) => {
		const api = await apiAs(outsider!)
		try {
			const res = await api.get(`${API}/catalogue`)
			expect(res.status(), await res.text()).toBe(200)
			const names = ((await res.json()).apps ?? []).map((app: { name: string }) => app.name)
			expect(names).not.toContain(APP_NAME)
		} finally {
			await api.dispose()
		}

		const { context, page } = await loginAs(browser, outsider!.username, outsider!.password)
		try {
			const dialog = await openAddFlow(page)
			await dialog.getByRole('textbox', { name: /search widgets/i }).fill('zaak')
			await expect(dialog.getByText(APP_NAME)).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e app-catalogue::pieter-asks-for-a-map-viewer
	// @e2e app-catalogue::request-declined-with-a-reason
	test('a request sent from the add flow is declined with a note and the requester hears back', async ({
		browser,
		page,
	}) => {
		// The member asks for a missing app.
		const { context, page: memberPage } = await loginAs(
			browser,
			member!.username,
			member!.password,
		)
		try {
			const dialog = await openAddFlow(memberPage)
			const requestBlock = dialog.locator('[data-testid="widget-picker-request"]')
			await requestBlock.getByRole('button', { name: /can't find your app/i }).click()
			await requestBlock.getByRole('textbox', { name: 'App name' }).fill(REQUESTED_APP)
			await requestBlock
				.getByRole('textbox', { name: /what do you need it for/i })
				.fill('Ik moet percelen bekijken bij vergunningen')
			await requestBlock.getByRole('button', { name: 'Send request' }).click()
			await expect(requestBlock).toContainText('Your request was sent to IT')
		} finally {
			await context.close()
		}

		// Every administrator hears of it.
		const adminSubjects = await notificationSubjects()
		test.skip(adminSubjects === null, 'the notifications app is not enabled on this instance')
		expect(adminSubjects).toContain(`${member!.username} asked for the app ${REQUESTED_APP}`)

		// The administrator declines it on the admin page, with a note.
		await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
		await page.goto('/index.php/settings/admin/launchpad')
		const tab = adminTab(page, 'operations')
		await expect(tab).toBeVisible({ timeout: 20_000 })
		await tab.click()
		const row = page
			.locator('[data-testid="app-catalogue-request"]')
			.filter({ hasText: REQUESTED_APP })
		await expect(row).toBeVisible({ timeout: 10_000 })
		await row.getByRole('button', { name: 'Decline', exact: true }).click()
		const submit = row.getByRole('button', { name: 'Decline request' })
		await expect(submit).toBeDisabled()
		await row.getByRole('textbox', { name: /why is it declined/i }).fill(DECLINE_NOTE)
		await submit.click()
		await expect(page.locator('[data-testid="app-catalogue-notice"]')).toContainText(
			'The request was declined.',
		)
		await expect(row).toHaveCount(0)

		// The request is no longer open.
		const listed = await admin.get(`${API}/admin/catalogue`)
		expect(listed.status()).toBe(200)
		const open = ((await listed.json()).requests ?? []).map(
			(entry: { appName: string }) => entry.appName,
		)
		expect(open).not.toContain(REQUESTED_APP)

		// The requester gets the decision as a Nextcloud notification.
		const api = await apiAs(member!)
		try {
			const res = await api.get(
				'/ocs/v2.php/apps/notifications/api/v2/notifications?format=json',
			)
			test.skip(
				res.status() === 404,
				'the notifications app is not enabled on this instance',
			)
			expect(res.status(), await res.text()).toBe(200)
			const subjects = ((await res.json()).ocs?.data ?? []).map(
				(entry: { subject: string }) => entry.subject,
			)
			expect(subjects).toContain(
				`Your request for ${REQUESTED_APP} was declined: ${DECLINE_NOTE}`,
			)
		} finally {
			await api.dispose()
		}
	})

	// @e2e app-catalogue::administrator-adds-an-app-for-one-group
	test('an administrator adds an app for one group and it is listed with that group', async ({ page }) => {
		await openCatalogueTab(page)
		await page.locator('[data-testid="app-catalogue-add"]').click()
		const form = page.locator('[data-testid="app-catalogue-form"]')
		await expect(form).toBeVisible()
		await form.getByRole('textbox', { name: 'Name' }).fill(ADMIN_APP_NAME)
		await form.getByRole('textbox', { name: 'Web address' }).fill(`${APP_URL}/beheer`)
		const groups = form.getByRole('combobox', { name: 'Visible to groups' })
		await groups.click()
		await groups.fill(GROUP)
		await expect(page.locator('.vs__dropdown-option').filter({ hasText: GROUP })).toBeVisible()
		await groups.press('Enter')
		await page.keyboard.press('Escape')
		await form.getByRole('button', { name: 'Save app' }).click()
		await expect(page.locator('[data-testid="app-catalogue-notice"]')).toContainText('App saved.')

		const entry = page.locator('[data-testid="app-catalogue-entry"]').filter({ hasText: ADMIN_APP_NAME })
		await expect(entry).toBeVisible()
		await expect(entry.locator('[data-testid="app-catalogue-entry-groups"]')).toContainText(GROUP)

		const listed = await admin.get(`${API}/admin/catalogue`)
		const stored = ((await listed.json()).apps ?? []).find((app: { name: string }) => app.name === ADMIN_APP_NAME)
		expect(stored).toMatchObject({ linkType: 'url', linkValue: `${APP_URL}/beheer`, targetGroups: [GROUP] })
	})

	// @e2e app-catalogue::too-many-open-requests
	test('an eleventh open request is refused', async () => {
		const api = await apiAs(outsider!)
		try {
			for (let i = 1; i <= 10; i++) {
				const sent = await api.post(`${API}/catalogue/requests`, {
					data: { appName: `${LIMIT_PREFIX} ${i}`, purpose: 'e2e' },
				})
				expect(sent.status(), await sent.text()).toBeLessThan(300)
			}
			const eleventh = await api.post(`${API}/catalogue/requests`, {
				data: { appName: `${LIMIT_PREFIX} 11`, purpose: 'e2e' },
			})
			expect(eleventh.status()).toBeGreaterThanOrEqual(400)
			expect((await eleventh.json()).error).toBe('You have 10 open requests. Wait for an answer first.')
		} finally {
			await api.dispose()
		}
	})

	// @e2e app-catalogue::request-approved
	test('an approved request lands in the catalogue and the requester hears back', async ({ page }) => {
		const api = await apiAs(member!)
		try {
			const sent = await api.post(`${API}/catalogue/requests`, {
				data: { appName: APPROVED_APP, purpose: 'Ik moet percelen bekijken bij vergunningen' },
			})
			expect(sent.status(), await sent.text()).toBeLessThan(300)
		} finally {
			await api.dispose()
		}

		await openCatalogueTab(page)
		const row = page.locator('[data-testid="app-catalogue-request"]').filter({ hasText: APPROVED_APP })
		await expect(row).toBeVisible({ timeout: 10_000 })
		await row.getByRole('button', { name: 'Approve', exact: true }).click()
		const form = page.locator('[data-testid="app-catalogue-form"]')
		await expect(form.getByRole('textbox', { name: 'Name' })).toHaveValue(APPROVED_APP)
		await form.getByRole('textbox', { name: 'Web address' }).fill(APPROVED_URL)
		await form.getByRole('button', { name: 'Save app' }).click()
		await expect(page.locator('[data-testid="app-catalogue-notice"]')).toContainText(`${APPROVED_APP} is now in the app catalogue`)
		await expect(page.locator('[data-testid="app-catalogue-entry"]').filter({ hasText: APPROVED_APP })).toBeVisible()
		await expect(row).toHaveCount(0)

		const subjects = await notificationSubjects(member!)
		test.skip(subjects === null, 'the notifications app is not enabled on this instance')
		expect(subjects).toContain(`${APPROVED_APP} is now in the app catalogue`)
	})
})
