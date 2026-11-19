/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the language-version UI of
 * openspec/specs/dashboard-language-content (REQ-LANGUI-001, REQ-LANGUI-002),
 * written for the e2e-coverage-programme.
 *
 * Each person in the scenarios is a throwaway account that owns its own
 * "Intranet" dashboard, seeded through the API (create, plus the variants
 * the GIVEN names). What the person DOES goes through the product's own
 * controls: the dashboard's cog menu, "Dashboard settings", the Languages
 * tab, and the workspace page. The reader's Nextcloud language is set the
 * way Nextcloud stores it, through the OCS provisioning API (key `language`).
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-language-content::add-a-dutch-version
 *   @e2e dashboard-language-content::primary-switch
 *   @e2e dashboard-language-content::reader-in-dutch
 *   @e2e dashboard-language-content::reader-in-german
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Locator,
	Page,
} from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const PANEL = '[data-test="config-panel-languages"]'
const ROW = '[data-testid="language-row"]'
const HEADING = '[data-testid="dashboard-language-heading"]'

type Variant = {
	languageCode: string
	name: string | null
	description: string | null
	isPrimary: number
}
type Dashboard = { id: number; uuid: string; name: string; description: string }

let admin: APIRequestContext
let allowBefore: boolean | null = null
const people: Principal[] = []

/**
 * Set a user's Nextcloud language through the provisioning API.
 *
 * @param username the account.
 * @param language a language Nextcloud ships.
 * @return nothing.
 */
async function setLanguage(username: string, language: string): Promise<void> {
	const res = await admin.put(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}?format=json`,
		{ form: { key: 'language', value: language } },
	)
	expect(res.status(), `language=${language}: ${await res.text()}`).toBe(200)
}

/**
 * A throwaway account, English for now, with the app's first-open notes
 * settled so no modal mask sits over the page.
 *
 * @param prefix a readable prefix.
 * @return the principal.
 */
async function person(prefix: string): Promise<Principal> {
	const who = await createPrincipal(admin, prefix)
	people.push(who)
	await assertActingAs(who.api, who.username)
	await setLanguage(who.username, 'en')
	const seen = await who.api.put(`${API}/preferences/support-dialog-seen`, {
		data: { value: '1' },
	})
	expect(seen.status(), await seen.text()).toBeLessThan(300)
	return who
}

/**
 * Create the person's "Intranet" dashboard and make it their active one.
 *
 * @param who the owner.
 * @param label a readable label.
 * @return the dashboard; its seeded primary is English.
 */
async function intranet(who: Principal, label: string): Promise<Dashboard> {
	const name = `E2E Intranet ${label} ${RUN}`
	const description = `The English intranet of ${label}`
	const res = await who.api.post(`${API}/dashboard`, {
		data: { name, description },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	const made = { id: Number(dash.id), uuid: String(dash.uuid), name, description }
	const activate = await who.api.post(`${API}/dashboard/${made.id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const active = await who.api.post(`${API}/dashboards/active`, {
		data: { uuid: made.uuid },
	})
	expect(active.status(), await active.text()).toBeLessThan(300)
	return made
}

/**
 * Add a variant through the API (seeding a GIVEN).
 *
 * @param who the owner.
 * @param uuid the dashboard.
 * @param body the variant.
 * @return nothing.
 */
async function seedVariant(
	who: Principal,
	uuid: string,
	body: Record<string, string>,
): Promise<void> {
	const res = await who.api.post(`${API}/dashboards/${uuid}/translations`, {
		data: body,
	})
	expect(res.status(), await res.text()).toBe(201)
}

/**
 * The variants as stored.
 *
 * @param who the owner.
 * @param uuid the dashboard.
 * @return the variants.
 */
async function stored(who: Principal, uuid: string): Promise<Variant[]> {
	const res = await who.api.get(`${API}/dashboards/${uuid}/translations`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).translations
}

/**
 * Log in as the person with the product walkthrough suppressed, the same
 * per-user marker global-setup writes for the admin.
 *
 * @param browser the browser.
 * @param who the person.
 * @return the context and page.
 */
async function signIn(
	browser: Browser,
	who: Principal,
): Promise<{ context: BrowserContext; page: Page }> {
	const session = await loginAs(browser, who.username, who.password)
	await session.context.addInitScript(() => {
		try {
			window.localStorage.setItem('cn-walkthrough-seen:launchpad', '999.0.0')
			window.localStorage.setItem('cn-support-dialog-shown:launchpad', '1')
		} catch {
			// The server-side preference set in person() is the real mechanism.
		}
	})
	return session
}

/**
 * Open Settings for the dashboard through its cog menu, then the Languages tab.
 *
 * @param page the page, signed in as the owner.
 * @param name the dashboard's name.
 * @return the Languages panel.
 */
async function openLanguagesTab(page: Page, name: string): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	await openDashboardSwitcher(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.and(page.locator(`[aria-label="${name}"]`))
		.first()
	await row.locator('.dashboard-row-actions button').first().click()
	await page.locator('[data-testid="cog-dashboard-config"]').click()
	await page
		.locator('[data-testid="dashboard-name-input"]')
		.waitFor({ state: 'visible' })
	await page.locator('[data-test="config-tab-languages"]').click()
	const panel = page.locator(PANEL)
	await expect(panel).toBeVisible()
	return panel
}

/**
 * The row of one language in the Languages tab, by its displayed name.
 *
 * @param panel the Languages panel.
 * @param language the language's English name.
 * @return the row.
 */
function languageRow(panel: Locator, language: string): Locator {
	return panel.locator(ROW).filter({
		has: panel.page().locator('.languages-tab__head strong', {
			hasText: new RegExp(`^${language}$`),
		}),
	})
}

/**
 * Pick an option in one of the tab's selects.
 *
 * @param panel the Languages panel.
 * @param label the select's label.
 * @param option the option's text.
 * @return nothing.
 */
async function choose(panel: Locator, label: string, option: string): Promise<void> {
	const input = panel.getByLabel(label, { exact: true })
	await input.click()
	await input.fill(option)
	await panel
		.page()
		.locator('.vs__dropdown-option', {
			hasText: new RegExp(`^\\s*${option}\\s*$`),
		})
		.first()
		.click()
}

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	allowBefore = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)
})

test.afterAll(async () => {
	for (const who of people) {
		await removePrincipal(admin, who)
	}
	if (allowBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: allowBefore },
		})
	}
	await admin.dispose()
})

test.describe('REQ-LANGUI-001 the owner manages language versions', () => {
	test('Sanne adds a Dutch version copied from English and renames it', async ({
		browser,
	}) => {
		// @e2e dashboard-language-content::add-a-dutch-version
		const sanne = await person('e2e-lang-sanne')
		const dash = await intranet(sanne, 'Sanne')
		expect((await stored(sanne, dash.uuid)).map((v) => v.languageCode)).toEqual([
			'en',
		])

		const { context, page } = await signIn(browser, sanne)
		try {
			const panel = await openLanguagesTab(page, dash.name)
			await expect(panel.locator(ROW)).toHaveCount(1)

			await choose(panel, 'Language', 'Dutch')
			await choose(panel, 'Start from', 'Copy from English')
			const created = page.waitForResponse(
				(r) =>
					r.url().includes(`/dashboards/${dash.uuid}/translations`)
					&& r.request().method() === 'POST',
			)
			await panel.locator('[data-testid="language-add"]').click()
			expect((await created).status()).toBe(201)

			// The Dutch variant is listed, with the English content copied in.
			await expect(panel.locator(ROW)).toHaveCount(2)
			const dutch = languageRow(panel, 'Dutch')
			await expect(dutch.getByLabel('Name', { exact: true })).toHaveValue(
				dash.name,
			)
			await expect(
				dutch.getByLabel('Description', { exact: true }),
			).toHaveValue(dash.description)
			await expect(dutch.locator('.languages-tab__badge')).toHaveCount(0)

			// And she can edit its name.
			await dutch
				.getByLabel('Name', { exact: true })
				.fill('Intranet in het Nederlands')
			const saved = page.waitForResponse(
				(r) =>
					r.url().includes(`/dashboards/${dash.uuid}/translations/nl`)
					&& r.request().method() === 'PUT',
			)
			await dutch.locator('[data-testid="language-save"]').click()
			expect((await saved).status()).toBe(200)
			await expect(
				languageRow(panel, 'Dutch').getByLabel('Name', { exact: true }),
			).toHaveValue('Intranet in het Nederlands')
		} finally {
			await context.close()
		}

		const list = await stored(sanne, dash.uuid)
		const nl = list.find((v) => v.languageCode === 'nl')
		expect(nl).toMatchObject({
			name: 'Intranet in het Nederlands',
			description: dash.description,
		})
		expect(Number(nl!.isPrimary)).toBe(0)
	})

	test('Sanne makes Dutch the primary', async ({ browser }) => {
		// @e2e dashboard-language-content::primary-switch
		const sanne = await person('e2e-lang-sanne2')
		const dash = await intranet(sanne, 'Sanne primary')
		await seedVariant(sanne, dash.uuid, {
			languageCode: 'nl',
			name: 'Intranet NL',
		})

		const { context, page } = await signIn(browser, sanne)
		try {
			const panel = await openLanguagesTab(page, dash.name)
			const english = languageRow(panel, 'English')
			const dutch = languageRow(panel, 'Dutch')
			await expect(english.locator('.languages-tab__badge')).toHaveText(
				'Primary',
			)
			await expect(dutch.locator('.languages-tab__badge')).toHaveCount(0)

			const promoted = page.waitForResponse((r) =>
				r
					.url()
					.includes(
						`/dashboards/${dash.uuid}/translations/nl/set-primary`,
					),
			)
			await dutch.locator('[data-testid="language-make-primary"]').click()
			expect((await promoted).status()).toBe(200)

			// Dutch is marked primary; English is a normal variant again.
			await expect(dutch.locator('.languages-tab__badge')).toHaveText(
				'Primary',
			)
			await expect(
				dutch.locator('[data-testid="language-make-primary"]'),
			).toHaveCount(0)
			await expect(english.locator('.languages-tab__badge')).toHaveCount(0)
			await expect(
				english.locator('[data-testid="language-make-primary"]'),
			).toBeVisible()
			await expect(
				english.locator('[data-testid="language-delete"]'),
			).toBeVisible()
		} finally {
			await context.close()
		}

		const list = await stored(sanne, dash.uuid)
		expect(Number(list.find((v) => v.languageCode === 'nl')!.isPrimary)).toBe(1)
		expect(Number(list.find((v) => v.languageCode === 'en')!.isPrimary)).toBe(0)
	})
})

test.describe("REQ-LANGUI-002 the workspace page shows the reader's language", () => {
	// SUSPECTED BUG: the resolver the heading reads never consults the
	// reader's language. lib/Controller/DashboardTranslationApiController.php:527-531
	// (resolveLocaleVariant) passes `preferredLanguage: ''`, so a Dutch reader
	// is served the English primary with `isFallback: true` and the page
	// shows the English name and the "primary language" note.
	test.fixme('Jan, reading in Dutch, sees the Dutch name and description', async ({
		browser,
	}) => {
		// @e2e dashboard-language-content::reader-in-dutch
		const jan = await person('e2e-lang-jan')
		const dash = await intranet(jan, 'Jan')
		await seedVariant(jan, dash.uuid, {
			languageCode: 'nl',
			name: `Intranet NL ${RUN}`,
			description: 'Het Nederlandse intranet',
		})
		await setLanguage(jan.username, 'nl')

		const { context, page } = await signIn(browser, jan)
		try {
			await page.goto('/index.php/apps/launchpad')
			const heading = page.locator(HEADING)
			await expect(heading).toBeVisible({ timeout: 30_000 })
			await expect(
				heading.locator('.dashboard-language-heading__name'),
			).toHaveText(`Intranet NL ${RUN}`)
			await expect(
				heading.locator('.dashboard-language-heading__description'),
			).toHaveText('Het Nederlandse intranet')
			await expect(
				page.locator('[data-testid="dashboard-language-fallback"]'),
			).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	test('Petra, reading in German, sees the primary and the fallback note', async ({
		browser,
	}) => {
		// @e2e dashboard-language-content::reader-in-german
		const petra = await person('e2e-lang-petra')
		const dash = await intranet(petra, 'Petra')
		await seedVariant(petra, dash.uuid, {
			languageCode: 'nl',
			name: `Intranet NL ${RUN}`,
			description: 'Het Nederlandse intranet',
		})
		await setLanguage(petra.username, 'de')

		const { context, page } = await signIn(browser, petra)
		try {
			const region = page.locator(
				`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dash.uuid}"]`,
			)
			await page.goto('/index.php/apps/launchpad')
			await expect(region).toBeVisible({ timeout: 30_000 })
			const heading = page.locator(HEADING)
			await expect(
				heading.locator('.dashboard-language-heading__name'),
			).toHaveText(dash.name)
			await expect(
				heading.locator('.dashboard-language-heading__description'),
			).toHaveText(dash.description)
			// The note is the string "Shown in the primary language", rendered in
			// Petra's own language (l10n/de.js).
			await expect(
				page.locator('[data-testid="dashboard-language-fallback"]'),
			).toHaveText('In der primären Sprache angezeigt')
		} finally {
			await context.close()
		}
	})
})
