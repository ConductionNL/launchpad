/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for LaunchPad's Nextcloud unified-search
 * provider (openspec/specs/nc-unified-search-integration), written for
 * e2e-coverage-programme task 5.5.
 *
 * The provider is observed where Nextcloud exposes it: over OCS
 * (`GET /ocs/v2.php/search/providers` and
 * `GET /ocs/v2.php/search/providers/launchpad/search?term=…`) and in the
 * header's unified-search dialog. Every test provisions its own throwaway
 * accounts over basic auth, so each searcher sees only its own dashboards
 * plus whatever instance-wide default dashboards exist; assertions are made
 * against the uuids the test seeded, never against an empty instance.
 *
 * Dashboards and metadata are seeded as their owner. Widget placements are
 * seeded by the administrator, the way a text widget is stored by the
 * add-widget flow (`widgetId: 'text'`, `content: {text, contentMode}`):
 * the admin is exempt from the role-feature-permission widget allow-list that
 * other specs install as `default`, so seeding does not depend on suite order.
 *
 * Gate-19 @e2e traceability:
 *   @e2e nc-unified-search-integration::provider-is-registered-and-discoverable
 *   @e2e nc-unified-search-integration::localized-provider-name
 *   @e2e nc-unified-search-integration::provider-name-appears-in-search-ui
 *   @e2e nc-unified-search-integration::dashboard-name-match
 *   @e2e nc-unified-search-integration::dashboard-description-match
 *   @e2e nc-unified-search-integration::partial-substring-match
 *   @e2e nc-unified-search-integration::exact-match-and-partial-matches-coexist
 *   @e2e nc-unified-search-integration::no-match-returns-empty-result
 *   @e2e nc-unified-search-integration::text-widget-content-match
 *   @e2e nc-unified-search-integration::multiple-text-widgets-on-same-dashboard
 *   @e2e nc-unified-search-integration::text-widget-with-html-rendering
 *   @e2e nc-unified-search-integration::case-insensitive-widget-content-search
 *   @e2e nc-unified-search-integration::widget-placement-without-matching-content
 *   @e2e nc-unified-search-integration::non-text-widgets-are-skipped
 *   @e2e nc-unified-search-integration::metadata-field-value-match-when-capability-enabled
 *   @e2e nc-unified-search-integration::metadata-field-names-are-not-searchable-only-values
 *   @e2e nc-unified-search-integration::case-insensitive-metadata-value-search
 *   @e2e nc-unified-search-integration::user-sees-only-dashboards-with-view-permission
 *   @e2e nc-unified-search-integration::permission-check-happens-before-result-inclusion
 *   @e2e nc-unified-search-integration::group-shared-dashboard-visible-only-to-group-members
 *   @e2e nc-unified-search-integration::dashboard-search-result-entry
 *   @e2e nc-unified-search-integration::widget-content-search-result-entry
 *   @e2e nc-unified-search-integration::metadata-field-search-result-entry
 *   @e2e nc-unified-search-integration::icon-url-is-valid-and-reachable
 *   @e2e nc-unified-search-integration::dashboard-results-capped-to-10
 *   @e2e nc-unified-search-integration::widget-results-capped-to-10
 *   @e2e nc-unified-search-integration::metadata-results-capped-to-10
 *   @e2e nc-unified-search-integration::combined-result-types-and-capping
 *   @e2e nc-unified-search-integration::provider-name-is-translated
 *   @e2e nc-unified-search-integration::subline-strings-are-translated
 *   @e2e nc-unified-search-integration::metadata-subline-is-translated-when-capability-enabled
 *   @e2e nc-unified-search-integration::missing-translation-falls-back-to-english
 *   @e2e nc-unified-search-integration::no-matches-returns-empty-array
 *   @e2e nc-unified-search-integration::empty-search-term
 *   @e2e nc-unified-search-integration::very-short-query-term-single-character
 *   @e2e nc-unified-search-integration::search-result-is-clickable
 *   @e2e nc-unified-search-integration::search-result-resourceurl-is-deep-linked
 *   @e2e nc-unified-search-integration::search-result-thumbnail-is-displayed
 *   @e2e nc-unified-search-integration::search-result-appears-in-the-correct-section
 *   @e2e nc-unified-search-integration::provider-order-is-respected
 *   @e2e nc-unified-search-integration::order-does-not-affect-search-completeness
 *   @e2e nc-unified-search-integration::boost-in-app-order
 *
 * @spec openspec/specs/nc-unified-search-integration/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { loginAs } from './fixtures/secondary-user.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const API = '/index.php/apps/launchpad/api'
const SEARCH = '/ocs/v2.php/search/providers'
const PROVIDER = 'launchpad'
/** A route outside LaunchPad, with the header's unified search on it. */
const OFF_APP = '/index.php/settings/user'
/**
 * LaunchPad's own page route (`launchpad.page.index`), as the router path:
 * Nextcloud's route matcher does not resolve a `/index.php`-prefixed `from`.
 */
const IN_APP = '/apps/launchpad/'
const L10N_DIR = path.resolve(__dirname, '../../l10n')

type Entry = {
	title: string
	subline: string
	resourceUrl: string
	thumbnailUrl: string
	icon: string
}
type Answer = {
	status: number
	name: string
	isPaginated: boolean
	entries: Entry[]
}
type Provider = { id: string; name: string; order: number }
type Dashboard = { id: number; uuid: string; name: string; owner: Principal }

let admin: APIRequestContext
let savedSettings: Record<string, unknown> | null = null
const principals: Principal[] = []
const groups: string[] = []
const fieldIds: number[] = []
const groupDashboards: Array<{ groupId: string; uuid: string }> = []

/**
 * The backend catalogue of one locale (what PHP `IL10N` reads).
 *
 * @param locale the locale code.
 * @return the source → translation map.
 */
function catalogue(locale: string): Record<string, string> {
	const file = path.join(L10N_DIR, `${locale}.json`)
	return JSON.parse(fs.readFileSync(file, 'utf8')).translations
}

/**
 * A throwaway account with its Nextcloud language set.
 *
 * @param prefix a readable part of the username.
 * @param language the Nextcloud language code.
 * @return the principal; removed in afterAll.
 */
async function account(prefix: string, language = 'en'): Promise<Principal> {
	const principal = await createPrincipal(admin, `e2e-srch-${prefix}`)
	principals.push(principal)
	const res = await admin.put(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(principal.username)}?format=json`,
		{ form: { key: 'language', value: language } },
	)
	expect(res.status(), await res.text()).toBe(200)
	expect((await res.json()).ocs.meta.statuscode).toBe(100)
	return principal
}

/**
 * Create a personal dashboard as its owner.
 *
 * @param owner the owning account.
 * @param name the dashboard name.
 * @param description an optional description.
 * @return its id, uuid and name.
 */
async function dashboard(
	owner: Principal,
	name: string,
	description?: string,
): Promise<Dashboard> {
	const data: Record<string, string> = { name }
	if (description !== undefined) {
		data.description = description
	}
	const res = await owner.api.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	return { id: Number(dash.id), uuid: String(dash.uuid), name, owner }
}

/**
 * Place a widget on a dashboard, as its owner: only the owner may add
 * widgets to a personal dashboard.
 *
 * @param dash the dashboard.
 * @param widgetId the widget type (`text`, `weather`, `calendar` …).
 * @param content the widget's content blob.
 * @return the placement id.
 */
async function placement(
	dash: Dashboard,
	widgetId: string,
	content: Record<string, unknown>,
): Promise<number> {
	const res = await dash.owner.api.post(`${API}/dashboard/${dash.id}/widgets`, {
		data: { widgetId, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, content },
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

/**
 * Place a text-display widget, stored the way the add-widget flow stores it.
 *
 * @param dash the dashboard.
 * @param text the widget body (Markdown or HTML).
 * @return the placement id.
 */
async function textWidget(dash: Dashboard, text: string): Promise<number> {
	return placement(dash, 'text', { text, contentMode: 'markdown' })
}

/**
 * A text metadata field with this key: the existing one, or a new one that
 * afterAll deletes again.
 *
 * @param key the field key.
 * @return nothing; throws when the field cannot be had.
 */
async function metadataField(key: string): Promise<void> {
	const list = await admin.get(`${API}/admin/metadata-fields`)
	expect(list.status(), await list.text()).toBe(200)
	const existing = ((await list.json()).fields ?? []) as Array<{ key: string }>
	if (existing.some((field) => field.key === key)) {
		return
	}
	const res = await admin.post(`${API}/admin/metadata-fields`, {
		data: { key, label: key, type: 'text' },
	})
	expect(res.status(), await res.text()).toBe(201)
	fieldIds.push(Number((await res.json()).id))
}

/**
 * Write metadata values on a dashboard, as its owner.
 *
 * @param owner the owning account.
 * @param dash the dashboard.
 * @param values field key → value.
 * @return nothing; throws when the write is refused.
 */
async function metadata(
	owner: Principal,
	dash: Dashboard,
	values: Record<string, string>,
): Promise<void> {
	for (const key of Object.keys(values)) {
		await metadataField(key)
	}
	const res = await owner.api.put(`${API}/dashboards/${dash.uuid}/metadata`, {
		data: { metadata: values },
	})
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The search providers Nextcloud lists for a page.
 *
 * @param who the searching account.
 * @param from the page the search is opened on.
 * @return the providers, in the order Nextcloud returns them.
 */
async function providers(who: Principal, from = OFF_APP): Promise<Provider[]> {
	const res = await who.api.get(`${SEARCH}?format=json&from=${encodeURIComponent(from)}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).ocs.data as Provider[]
}

/**
 * The LaunchPad provider as listed for a page.
 *
 * @param who the searching account.
 * @param from the page the search is opened on.
 * @return the provider; fails when it is not listed.
 */
async function launchpadProvider(who: Principal, from = OFF_APP): Promise<Provider> {
	const listed = await providers(who, from)
	const found = listed.find((p) => p.id === PROVIDER)
	expect(found, `providers: ${listed.map((p) => p.id).join(', ')}`).toBeDefined()
	return found!
}

/**
 * Run the LaunchPad provider through Nextcloud's unified-search endpoint.
 *
 * @param who the searching account.
 * @param term the search term.
 * @param from the page the search is opened on.
 * @return the HTTP status and the provider's result.
 */
async function search(who: Principal, term: string, from = OFF_APP): Promise<Answer> {
	const res = await who.api.get(
		`${SEARCH}/${PROVIDER}/search?format=json&term=${encodeURIComponent(term)}`
			+ `&from=${encodeURIComponent(from)}`,
	)
	const text = await res.text()
	let data: Partial<Answer>
	try {
		data = JSON.parse(text).ocs?.data ?? {}
	} catch {
		data = {}
	}
	return {
		status: res.status(),
		name: String(data.name ?? ''),
		isPaginated: Boolean(data.isPaginated),
		entries: (data.entries ?? []) as Entry[],
	}
}

/**
 * The entries that point at a dashboard.
 *
 * @param entries the provider's entries.
 * @param dash the dashboard.
 * @return those whose deep-link names its uuid.
 */
function about(entries: Entry[], dash: Dashboard): Entry[] {
	return entries.filter((e) => e.resourceUrl.includes(dash.uuid))
}

/**
 * Whether an entry is a widget-content hit (its link carries a widget hint).
 *
 * @param entry the entry.
 * @return true for a widget hit.
 */
function isWidget(entry: Entry): boolean {
	return /[;?&]widget=\d+/.test(entry.resourceUrl)
}

/**
 * Whether an entry is a metadata hit (English subline).
 *
 * @param entry the entry.
 * @return true for a metadata hit.
 */
function isMetadata(entry: Entry): boolean {
	return !isWidget(entry) && entry.subline.startsWith('Metadata: ')
}

/**
 * Whether an entry is a dashboard name/description hit.
 *
 * @param entry the entry.
 * @return true for a dashboard hit.
 */
function isDashboard(entry: Entry): boolean {
	return !isWidget(entry) && !isMetadata(entry)
}

/**
 * The placement id in a widget deep-link.
 *
 * @param entry a widget entry.
 * @return the id, or NaN.
 */
function placementIdOf(entry: Entry): number {
	const match = entry.resourceUrl.match(/[;?&]widget=(\d+)/)
	return match ? Number(match[1]) : Number.NaN
}

/**
 * The uuids of the dashboards an account may see.
 *
 * @param who the account.
 * @return the visible uuids.
 */
async function visibleUuids(who: Principal): Promise<string[]> {
	const res = await who.api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows = (Array.isArray(body) ? body : body.items ?? body.data ?? []) as Array<{ uuid: string }>
	return rows.map((row) => String(row.uuid))
}

/**
 * The uuid inside a LaunchPad deep-link.
 *
 * @param entry the entry.
 * @return the uuid.
 */
function uuidOf(entry: Entry): string {
	const match = entry.resourceUrl.match(/#dashboard\/([^;?&]+)/)
	return match ? decodeURIComponent(match[1]) : ''
}

/**
 * Escape a string for a RegExp.
 *
 * @param text the text.
 * @return the escaped text.
 */
function escapeRe(text: string): string {
	return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Open the header's unified search on a non-LaunchPad page and type a term.
 *
 * @param page a logged-in page.
 * @param term the term to type.
 * @return the search dialog.
 */
async function openUnifiedSearch(page: Page, term: string): Promise<Locator> {
	await page.goto(OFF_APP)
	// Nextcloud 34 draws the trigger as a search-bar button in the header.
	const trigger = page
		.locator('.unified-search-input__button')
		.or(page.locator('#unified-search button'))
		.or(page.getByRole('button', { name: /unified search/i }))
		.first()
	await trigger.click()
	const dialog = page
		.locator('.unified-search-modal')
		.or(page.getByRole('dialog', { name: /unified search/i }))
		.first()
	await expect(dialog).toBeVisible({ timeout: 15_000 })
	await dialog.getByRole('textbox').first().fill(term)
	return dialog
}

/**
 * The result group of one provider in the unified-search dialog.
 *
 * @param dialog the dialog.
 * @param providerName the provider's display name.
 * @return the group.
 */
function resultSection(dialog: Locator, providerName: string): Locator {
	return dialog.locator('.result').filter({
		has: dialog.page().locator('.result-title', {
			hasText: new RegExp(`^\\s*${escapeRe(providerName)}\\s*$`),
		}),
	})
}

/**
 * The provider group titles in the dialog, top to bottom.
 *
 * @param dialog the dialog.
 * @return the titles.
 */
async function sectionTitles(dialog: Locator): Promise<string[]> {
	const titles = await dialog.locator('.result .result-title').allInnerTexts()
	return titles.map((title) => title.trim())
}

/**
 * Upload a small file into an account's home over WebDAV.
 *
 * @param who the account.
 * @param name the file name.
 * @return nothing; throws when the upload fails.
 */
async function uploadFile(who: Principal, name: string): Promise<void> {
	const res = await who.api.put(
		`/remote.php/dav/files/${encodeURIComponent(who.username)}/${encodeURIComponent(name)}`,
		{ data: 'unified search probe', headers: { 'Content-Type': 'text/plain' } },
	)
	expect([201, 204]).toContain(res.status())
}

test.beforeAll(async () => {
	admin = await adminContext()
	const current = await admin.get(`${API}/admin/settings`)
	expect(current.status(), await current.text()).toBe(200)
	const body = await current.json()
	const settings = body.data ?? body
	savedSettings = {
		allowUserDash: settings.allowUserDashboards,
		allowMultiDash: settings.allowMultipleDashboards,
		maxDashboardsPerUser: settings.maxDashboardsPerUser,
		maxWidgetsPerDashboard: settings.maxWidgetsPerDashboard,
	}
	const put = await admin.put(`${API}/admin/settings`, {
		data: {
			allowUserDash: true,
			allowMultiDash: true,
			maxDashboardsPerUser: 0,
			maxWidgetsPerDashboard: 0,
		},
	})
	expect(put.status(), await put.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	for (const { groupId, uuid } of groupDashboards) {
		await admin.delete(`${API}/dashboards/group/${encodeURIComponent(groupId)}/${uuid}`)
	}
	for (const id of fieldIds) {
		await admin.delete(`${API}/admin/metadata-fields/${id}?cascade=true`)
	}
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const groupId of groups) {
		await removeGroup(admin, groupId)
	}
	if (savedSettings !== null) {
		await admin.put(`${API}/admin/settings`, { data: savedSettings })
	}
	await admin.dispose()
})

test.describe.serial('unified search: provider registration (REQ-SRCH-001)', () => {
	// @e2e nc-unified-search-integration::provider-is-registered-and-discoverable
	test('the launchpad provider is listed with id, name and order 50', async () => {
		const alice = await account('reg')
		const provider = await launchpadProvider(alice)
		expect(provider.id).toBe('launchpad')
		expect(provider.name).toBe('Dashboards')
		expect(provider.order).toBe(50)

		const answer = await search(alice, `probe ${RUN}`)
		expect(answer.status).toBe(200)
		expect(answer.name).toBe('Dashboards')
	})

	// @e2e nc-unified-search-integration::localized-provider-name
	test('a Dutch user gets the Dutch provider name, in the API and in the search bar', async ({ browser }) => {
		const nl = catalogue('nl')
		expect(Object.keys(nl)).toContain('Dashboards')
		const carol = await account('nlname', 'nl')
		const dash = await dashboard(carol, `Zoekbord ${RUN}`)

		const provider = await launchpadProvider(carol)
		expect(provider.name).toBe(nl.Dashboards)

		const { context, page } = await loginAs(browser, carol.username, carol.password)
		try {
			const dialog = await openUnifiedSearch(page, `Zoekbord ${RUN}`)
			const section = resultSection(dialog, nl.Dashboards)
			await expect(section.locator(`a[href*="${dash.uuid}"]`)).toBeVisible({ timeout: 20_000 })
			await expect(section.locator('.result-title')).toHaveText(nl.Dashboards)
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::provider-name-appears-in-search-ui
	test('LaunchPad results sit under the provider name as section header', async ({ browser }) => {
		const alice = await account('uiname')
		const dash = await dashboard(alice, `Header probe ${RUN}`)
		const provider = await launchpadProvider(alice)

		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, `Header probe ${RUN}`)
			const section = resultSection(dialog, provider.name)
			await expect(section.locator('.result-title')).toHaveText(provider.name)
			await expect(section.locator(`a[href*="${dash.uuid}"]`)).toContainText(dash.name, { timeout: 20_000 })
		} finally {
			await context.close()
		}
	})
})

test.describe.serial('unified search: name and description matching (REQ-SRCH-002)', () => {
	// @e2e nc-unified-search-integration::dashboard-name-match
	test('a name substring finds the dashboard, in any letter case', async () => {
		const alice = await account('name')
		const dash = await dashboard(alice, 'Marketing Campaign 2026')
		for (const term of ['market', 'MARKET', 'Market']) {
			const answer = await search(alice, term)
			expect(answer.status).toBe(200)
			const hits = about(answer.entries, dash).filter(isDashboard)
			expect(hits.map((e) => e.title), term).toEqual(['Marketing Campaign 2026'])
		}
	})

	// @e2e nc-unified-search-integration::dashboard-description-match
	test('a description substring finds the dashboard under its name', async () => {
		const bob = await account('desc')
		const description = 'Company quarterly performance overview'
		const dash = await dashboard(bob, 'Q1 Metrics', description)
		const answer = await search(bob, 'quarterly')
		expect(answer.status).toBe(200)
		const hits = about(answer.entries, dash).filter(isDashboard)
		expect(hits.map((e) => e.title)).toEqual(['Q1 Metrics'])
		// REQ-SRCH-002 asks for "metadata like creation info or widget count";
		// REQ-SRCH-006 pins the dashboard subline to the description, which is
		// the stricter of the two and what the product answers.
		expect(hits[0].subline).toBe(description)
	})

	// @e2e nc-unified-search-integration::partial-substring-match
	test('a fragment inside a word matches (substring, not whole word)', async () => {
		const alice = await account('partial')
		const dash = await dashboard(alice, 'Sales Pipeline Analysis')
		const answer = await search(alice, 'pipe')
		expect(answer.status).toBe(200)
		expect(about(answer.entries, dash).filter(isDashboard).map((e) => e.title))
			.toEqual(['Sales Pipeline Analysis'])
	})

	// @e2e nc-unified-search-integration::exact-match-and-partial-matches-coexist
	test('an exact name and two longer names all match the same term', async () => {
		const alice = await account('coexist')
		const dashes = [
			await dashboard(alice, 'Marketing'),
			await dashboard(alice, 'Market Research'),
			await dashboard(alice, 'Marketing Campaigns'),
		]
		const answer = await search(alice, 'market')
		expect(answer.status).toBe(200)
		const found = answer.entries.filter(isDashboard).map(uuidOf)
		for (const dash of dashes) {
			expect(found, dash.name).toContain(dash.uuid)
		}
	})

	// @e2e nc-unified-search-integration::no-match-returns-empty-result
	test('a term nothing matches answers a complete, empty result', async () => {
		const alice = await account('nomatch')
		await dashboard(alice, 'Sales')
		await dashboard(alice, 'Marketing')
		await dashboard(alice, 'HR')
		// The run stamp keeps instance-wide default dashboards out of the match.
		const answer = await search(alice, `engineering ${RUN}`)
		expect(answer.status).toBe(200)
		expect(answer.name).toBe('Dashboards')
		expect(answer.isPaginated).toBe(false)
		expect(answer.entries).toEqual([])
	})
})

test.describe.serial('unified search: widget content (REQ-SRCH-003)', () => {
	// @e2e nc-unified-search-integration::text-widget-content-match
	test('a text widget body match deep-links to its placement', async () => {
		const alice = await account('widget')
		const dash = await dashboard(alice, 'Analytics')
		const pid = await textWidget(dash, '## Budget Proposal for Q2')
		const answer = await search(alice, 'budget')
		expect(answer.status).toBe(200)
		const hits = about(answer.entries, dash).filter(isWidget)
		expect(hits.map((e) => [e.title, e.subline, placementIdOf(e)]))
			.toEqual([['Analytics', 'Widget content on Analytics', pid]])
		expect(hits[0].resourceUrl).toContain(`widget=${pid}`)
	})

	// @e2e nc-unified-search-integration::multiple-text-widgets-on-same-dashboard
	test('each of several text widgets is found by its own words', async () => {
		const bob = await account('multi')
		const dash = await dashboard(bob, 'Notes')
		const pidA = await textWidget(dash, 'Meeting notes from Monday')
		const pidB = await textWidget(dash, 'Project timeline: starts in January')
		const pidC = await textWidget(dash, 'Sales targets for 2026')

		const january = about((await search(bob, 'january')).entries, dash).filter(isWidget)
		expect(january.map(placementIdOf)).toEqual([pidB])

		const sales = about((await search(bob, 'sales')).entries, dash).filter(isWidget)
		expect(sales.map(placementIdOf)).toEqual([pidC])

		expect(uuidOf(january[0])).toBe(dash.uuid)
		expect(uuidOf(sales[0])).toBe(dash.uuid)
		expect(new Set([pidA, pidB, pidC]).size).toBe(3)
		expect(placementIdOf(january[0])).not.toBe(placementIdOf(sales[0]))
	})

	// @e2e nc-unified-search-integration::text-widget-with-html-rendering
	test('HTML widget bodies match on their text, not their tags', async () => {
		const alice = await account('html')
		const dash = await dashboard(alice, `Html board ${RUN}`)
		const pid = await textWidget(dash, '<h1>Quarterly Report</h1><p>Revenue up 15%</p>')
		for (const term of ['revenue', 'quarterly', 'Quarterly Report']) {
			const answer = await search(alice, term)
			expect(answer.status, term).toBe(200)
			expect(about(answer.entries, dash).filter(isWidget).map(placementIdOf), term).toEqual([pid])
		}
	})

	// @e2e nc-unified-search-integration::case-insensitive-widget-content-search
	test('widget content matches regardless of letter case', async () => {
		const bob = await account('wcase')
		const dash = await dashboard(bob, `Case board ${RUN}`)
		const pid = await textWidget(dash, 'IMPORTANT: Project Deadline Tomorrow')
		for (const term of ['important', 'DEADLINE']) {
			const answer = await search(bob, term)
			expect(about(answer.entries, dash).filter(isWidget).map(placementIdOf), term).toEqual([pid])
		}
	})

	// @e2e nc-unified-search-integration::widget-placement-without-matching-content
	test('text widgets whose body does not hold the term stay out', async () => {
		const alice = await account('wnone')
		const dash = await dashboard(alice, `Lorem board ${RUN}`)
		const lorem = await textWidget(dash, 'Lorem Ipsum')
		await textWidget(dash, 'Dolor sit amet')

		const budget = await search(alice, 'budget')
		expect(budget.status).toBe(200)
		expect(about(budget.entries, dash)).toEqual([])

		// Control: the same widget IS searchable by its own words.
		const control = await search(alice, 'lorem ipsum')
		expect(about(control.entries, dash).filter(isWidget).map(placementIdOf)).toEqual([lorem])
	})

	// @e2e nc-unified-search-integration::non-text-widgets-are-skipped
	test('only the text widget is content-searched; weather and calendar are skipped', async () => {
		const bob = await account('nontext')
		const dash = await dashboard(bob, `Mixed board ${RUN}`)
		await placement(dash, 'weather', { location: 'forecast', unitsOverride: '' })
		await placement(dash, 'calendar', { title: 'forecast' })
		const text = await textWidget(dash, 'Weekly forecast')

		const answer = await search(bob, 'forecast')
		expect(answer.status).toBe(200)
		expect(about(answer.entries, dash).filter(isWidget).map(placementIdOf)).toEqual([text])
	})
})

test.describe.serial('unified search: metadata values (REQ-SRCH-004)', () => {
	// @e2e nc-unified-search-integration::metadata-field-value-match-when-capability-enabled
	test('a metadata value match names the field and links to the dashboard', async () => {
		const alice = await account('meta')
		const dash = await dashboard(alice, `Project Tracker ${RUN}`)
		await metadata(alice, dash, { year: '2026', department: 'Sales' })

		const answer = await search(alice, '2026')
		expect(answer.status).toBe(200)
		const hits = about(answer.entries, dash).filter(isMetadata)
		expect(hits.map((e) => [e.title, e.subline])).toEqual([[dash.name, 'Metadata: year = 2026']])
		expect(hits[0].resourceUrl).toMatch(new RegExp(`#dashboard/${dash.uuid}$`))
	})

	// @e2e nc-unified-search-integration::metadata-field-names-are-not-searchable-only-values
	test('the field key is not searched, the value is', async () => {
		const alice = await account('metakey')
		const key = `projectstatus_${RUN}`
		const dash = await dashboard(alice, `Tracker ${RUN}`)
		await metadata(alice, dash, { [key]: 'In Progress' })

		const byKey = await search(alice, 'ProjectStatus')
		expect(byKey.status).toBe(200)
		expect(about(byKey.entries, dash)).toEqual([])

		const byValue = await search(alice, 'In Progress')
		expect(about(byValue.entries, dash).filter(isMetadata).map((e) => e.subline))
			.toEqual([`Metadata: ${key} = In Progress`])
	})

	// @e2e nc-unified-search-integration::case-insensitive-metadata-value-search
	test('metadata values match regardless of letter case', async () => {
		const bob = await account('metacase')
		const key = `priority_${RUN}`
		const dash = await dashboard(bob, `Escalations ${RUN}`)
		await metadata(bob, dash, { [key]: 'URGENT' })

		const answer = await search(bob, 'urgent')
		expect(answer.status).toBe(200)
		expect(about(answer.entries, dash).filter(isMetadata).map((e) => e.subline))
			.toEqual([`Metadata: ${key} = URGENT`])
	})
})

test.describe.serial('unified search: permission filtering (REQ-SRCH-005)', () => {
	// @e2e nc-unified-search-integration::user-sees-only-dashboards-with-view-permission
	test('alice finds her own dashboard and never bob\'s private one', async () => {
		const alice = await account('pa')
		const bob = await account('pb')
		const mine = await dashboard(alice, 'My Dashboard')
		const his = await dashboard(bob, 'Bob\'s Private Dashboard')

		const answer = await search(alice, 'dashboard')
		expect(answer.status).toBe(200)
		const uuids = answer.entries.map(uuidOf)
		expect(uuids).toContain(mine.uuid)
		expect(uuids).not.toContain(his.uuid)

		// The candidate set is alice's visible set: nothing outside it appears.
		const visible = await visibleUuids(alice)
		for (const uuid of uuids) {
			expect(visible).toContain(uuid)
		}

		// The allowed principal does find it.
		const bobs = await search(bob, 'private dashboard')
		expect(bobs.entries.map(uuidOf)).toContain(his.uuid)
	})

	// @e2e nc-unified-search-integration::permission-check-happens-before-result-inclusion
	test('a matching dashboard alice cannot view leaks nothing', async () => {
		const alice = await account('leak')
		const bob = await account('leakowner')
		const term = `Zebra ledger ${RUN}`
		const his = await dashboard(bob, term, `Zebra ledger notes ${RUN}`)
		await metadata(bob, his, { [`ledger_${RUN}`]: term })

		expect(await visibleUuids(alice)).not.toContain(his.uuid)
		const answer = await search(alice, term)
		expect(answer.status).toBe(200)
		expect(answer.entries).toEqual([])

		const owner = await search(bob, term)
		expect(owner.entries.map(uuidOf)).toContain(his.uuid)
	})

	// @e2e nc-unified-search-integration::group-shared-dashboard-visible-only-to-group-members
	test('a group-shared dashboard is found by members only', async () => {
		const groupId = await createGroup(admin, `e2e-srch-sales-${RUN}`)
		groups.push(groupId)
		const alice = await account('gmember')
		const bob = await account('goutsider')
		await addToGroup(admin, alice.username, groupId)

		const created = await admin.post(`${API}/dashboards/group/${encodeURIComponent(groupId)}`, {
			data: { name: 'Team Analytics' },
		})
		expect(created.status(), await created.text()).toBe(201)
		const body = await created.json()
		const uuid = String((body.dashboard ?? body.data?.dashboard ?? body).uuid)
		groupDashboards.push({ groupId, uuid })
		const published = await admin.post(`${API}/dashboards/${uuid}/publish`)
		expect(published.status(), await published.text()).toBe(200)

		const member = await search(alice, 'analytics')
		expect(member.status).toBe(200)
		expect(member.entries.filter(isDashboard).map(uuidOf)).toContain(uuid)

		const outsider = await search(bob, 'analytics')
		expect(outsider.status).toBe(200)
		expect(outsider.entries.map(uuidOf)).not.toContain(uuid)
	})
})

test.describe.serial('unified search: result entries (REQ-SRCH-006)', () => {
	// @e2e nc-unified-search-integration::dashboard-search-result-entry
	test('a dashboard entry carries name, description, icon and uuid link', async () => {
		const alice = await account('entry')
		const described = await dashboard(alice, `Sales Dashboard ${RUN}`, 'Regional sales figures')
		const bare = await dashboard(alice, `Sales Dashboard bare ${RUN}`)

		const answer = await search(alice, `Sales Dashboard`)
		const withText = about(answer.entries, described).filter(isDashboard)
		expect(withText.map((e) => [e.title, e.subline])).toEqual([[described.name, 'Regional sales figures']])
		expect(withText[0].thumbnailUrl).toMatch(/^https?:\/\/.+\/launchpad\/img\/app\.svg/)
		expect(withText[0].resourceUrl).toContain(described.uuid)

		const fallback = about(answer.entries, bare).filter(isDashboard)
		expect(fallback.map((e) => [e.title, e.subline])).toEqual([[bare.name, 'LaunchPad dashboard']])
	})

	// @e2e nc-unified-search-integration::widget-content-search-result-entry
	test('a widget entry carries the dashboard name, widget subline, icon and widget link', async () => {
		const bob = await account('wentry')
		const dash = await dashboard(bob, 'Analytics')
		const pid = await textWidget(dash, `Pipeline review ${RUN}`)

		const answer = await search(bob, `Pipeline review ${RUN}`)
		const hits = about(answer.entries, dash).filter(isWidget)
		expect(hits.map((e) => [e.title, e.subline])).toEqual([['Analytics', 'Widget content on Analytics']])
		expect(hits[0].thumbnailUrl).toMatch(/^https?:\/\/.+\/launchpad\/img\/app\.svg/)
		expect(hits[0].resourceUrl).toContain(dash.uuid)
		expect(hits[0].resourceUrl).toContain(`widget=${pid}`)
	})

	// @e2e nc-unified-search-integration::metadata-field-search-result-entry
	test('a metadata entry carries the field subline and a plain dashboard link', async () => {
		const alice = await account('mentry')
		const key = `region_${RUN}`
		const dash = await dashboard(alice, `Region board ${RUN}`)
		await metadata(alice, dash, { [key]: `North ${RUN}` })

		const answer = await search(alice, `North ${RUN}`)
		const hits = about(answer.entries, dash).filter(isMetadata)
		expect(hits.map((e) => [e.title, e.subline])).toEqual([[dash.name, `Metadata: ${key} = North ${RUN}`]])
		expect(hits[0].thumbnailUrl).toMatch(/^https?:\/\/.+\/launchpad\/img\/app\.svg/)
		expect(hits[0].resourceUrl).toMatch(new RegExp(`#dashboard/${dash.uuid}$`))
		expect(hits[0].resourceUrl).not.toContain('widget=')
	})

	// @e2e nc-unified-search-integration::icon-url-is-valid-and-reachable
	test('the thumbnail URL answers 200 with a square SVG icon', async () => {
		const alice = await account('icon')
		const dash = await dashboard(alice, `Icon board ${RUN}`)
		const answer = await search(alice, `Icon board ${RUN}`)
		const hit = about(answer.entries, dash).filter(isDashboard)[0]
		expect(hit).toBeDefined()

		const icon = await alice.api.get(hit.thumbnailUrl)
		expect(icon.status()).toBe(200)
		expect(icon.headers()['content-type']).toMatch(/^image\/svg\+xml/)
		const svg = await icon.text()
		expect(svg).toContain('<svg')
		const box = svg.match(/viewBox="\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*"/)
		expect(box, 'the icon declares a viewBox').not.toBeNull()
		expect(Number(box![1])).toBe(Number(box![2]))
	})
})

test.describe.serial('unified search: result capping (REQ-SRCH-007)', () => {
	// @e2e nc-unified-search-integration::dashboard-results-capped-to-10
	test('25 matching dashboards yield at most 10 dashboard entries', async () => {
		const alice = await account('capd')
		const ours: string[] = []
		for (let i = 1; i <= 25; i++) {
			ours.push((await dashboard(alice, `Cap dashboard ${RUN} ${i}`)).uuid)
		}
		const answer = await search(alice, `dashboard ${RUN}`)
		expect(answer.status).toBe(200)
		const dashboards = answer.entries.filter(isDashboard)
		expect(dashboards.length).toBeGreaterThan(0)
		expect(dashboards.length).toBeLessThanOrEqual(10)
		for (const entry of dashboards) {
			expect(ours).toContain(uuidOf(entry))
		}
	})

	// @e2e nc-unified-search-integration::widget-results-capped-to-10
	test('50 matching text widgets yield at most 10 widget entries, each linked to its own placement', async () => {
		const bob = await account('capw')
		const placements = new Map<string, number[]>()
		for (let d = 1; d <= 5; d++) {
			const dash = await dashboard(bob, `Widget host ${d}`)
			const ids: number[] = []
			for (let w = 1; w <= 10; w++) {
				ids.push(await textWidget(dash, `capwidget ${RUN} item ${d}-${w}`))
			}
			placements.set(dash.uuid, ids)
		}
		const answer = await search(bob, `capwidget ${RUN}`)
		expect(answer.status).toBe(200)
		const widgets = answer.entries.filter(isWidget)
		expect(widgets.length).toBeGreaterThan(0)
		expect(widgets.length).toBeLessThanOrEqual(10)
		for (const entry of widgets) {
			expect(placements.get(uuidOf(entry)) ?? []).toContain(placementIdOf(entry))
		}
	})

	// @e2e nc-unified-search-integration::metadata-results-capped-to-10
	test('15 matching metadata values yield at most 10 metadata entries', async () => {
		const alice = await account('capm')
		const keys = [`capa_${RUN}`, `capb_${RUN}`, `capc_${RUN}`]
		const ours: string[] = []
		for (let d = 1; d <= 5; d++) {
			const dash = await dashboard(alice, `Meta host ${d}`)
			ours.push(dash.uuid)
			await metadata(alice, dash, Object.fromEntries(keys.map((k) => [k, `capmeta ${RUN} ${d}`])))
		}
		const answer = await search(alice, `capmeta ${RUN}`)
		expect(answer.status).toBe(200)
		const metas = answer.entries.filter(isMetadata)
		expect(metas.length).toBeGreaterThan(0)
		expect(metas.length).toBeLessThanOrEqual(10)
		for (const entry of metas) {
			expect(ours).toContain(uuidOf(entry))
			expect(entry.subline).toMatch(new RegExp(`^Metadata: cap[abc]_${RUN} = capmeta ${RUN} \\d$`))
		}
	})

	// @e2e nc-unified-search-integration::combined-result-types-and-capping
	test('5 dashboards, 8 widgets and 6 metadata hits all come back, within 30', async () => {
		const alice = await account('combo')
		const term = `combo ${RUN}`
		const dashes: Dashboard[] = []
		for (let d = 1; d <= 5; d++) {
			dashes.push(await dashboard(alice, `${term} board ${d}`))
		}
		for (let w = 0; w < 8; w++) {
			await textWidget(dashes[w % 5], `${term} note ${w}`)
		}
		const keys = [`comboa_${RUN}`, `combob_${RUN}`]
		for (let d = 0; d < 3; d++) {
			await metadata(alice, dashes[d], Object.fromEntries(keys.map((k) => [k, `${term} tag ${d}`])))
		}

		const answer = await search(alice, term)
		expect(answer.status).toBe(200)
		expect(answer.entries.length).toBeLessThanOrEqual(30)
		const counts = {
			dashboards: answer.entries.filter(isDashboard).length,
			widgets: answer.entries.filter(isWidget).length,
			metadata: answer.entries.filter(isMetadata).length,
		}
		expect(counts).toEqual({ dashboards: 5, widgets: 8, metadata: 6 })
	})
})

test.describe.serial('unified search: localisation (REQ-SRCH-008)', () => {
	// @e2e nc-unified-search-integration::provider-name-is-translated
	test('the provider label is the Dutch catalogue value for a Dutch user', async ({ browser }) => {
		const nl = catalogue('nl')
		expect(Object.keys(nl)).toContain('Dashboards')
		const dave = await account('nllabel', 'nl')
		const dash = await dashboard(dave, `Labelbord ${RUN}`)
		expect((await launchpadProvider(dave)).name).toBe(nl.Dashboards)

		const { context, page } = await loginAs(browser, dave.username, dave.password)
		try {
			const dialog = await openUnifiedSearch(page, `Labelbord ${RUN}`)
			const section = resultSection(dialog, nl.Dashboards)
			await expect(section.locator(`a[href*="${dash.uuid}"]`)).toBeVisible({ timeout: 20_000 })
			await expect(section.locator('.result-title')).toHaveText(nl.Dashboards)
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::subline-strings-are-translated
	test('a Dutch user reads the widget subline in Dutch', async () => {
		const nl = catalogue('nl')
		expect(Object.keys(nl)).toContain('Widget content on %s')
		const bob = await account('nlsub', 'nl')
		const dash = await dashboard(bob, 'Analytics')
		await textWidget(dash, `Kwartaalcijfers ${RUN}`)

		const answer = await search(bob, `Kwartaalcijfers ${RUN}`)
		const hits = about(answer.entries, dash).filter(isWidget)
		expect(hits.map((e) => e.subline)).toEqual([nl['Widget content on %s'].replace('%s', 'Analytics')])
	})

	// @e2e nc-unified-search-integration::metadata-subline-is-translated-when-capability-enabled
	test('the metadata subline follows the user\'s language', async () => {
		const de = catalogue('de')
		expect(Object.keys(catalogue('en'))).toContain('Metadata: %1$s = %2$s')
		expect(Object.keys(de)).toContain('Metadata: %1$s = %2$s')
		const erin = await account('desub', 'de')
		const dash = await dashboard(erin, `Jahresplan ${RUN}`)
		await metadata(erin, dash, { year: '2026' })

		const answer = await search(erin, '2026')
		expect(answer.status).toBe(200)
		const expected = de['Metadata: %1$s = %2$s'].replace('%1$s', 'year').replace('%2$s', '2026')
		expect(expected).not.toBe('Metadata: year = 2026')
		expect(about(answer.entries, dash).map((e) => e.subline)).toContain(expected)
	})

	// @e2e nc-unified-search-integration::missing-translation-falls-back-to-english
	test('a language LaunchPad has no catalogue for falls back to English', async () => {
		expect(fs.existsSync(path.join(L10N_DIR, 'ja.json'))).toBe(false)
		const frank = await account('ja', 'ja')
		const dash = await dashboard(frank, `Fallback board ${RUN}`)

		expect((await launchpadProvider(frank)).name).toBe('Dashboards')
		const answer = await search(frank, `Fallback board ${RUN}`)
		expect(answer.status).toBe(200)
		expect(answer.name).toBe('Dashboards')
		expect(about(answer.entries, dash).map((e) => e.subline)).toEqual(['LaunchPad dashboard'])
	})
})

test.describe.serial('unified search: empty results (REQ-SRCH-009)', () => {
	// @e2e nc-unified-search-integration::no-matches-returns-empty-array
	test('no match answers 200 with no entries, and the dialog says so', async ({ browser }) => {
		const alice = await account('empty')
		await dashboard(alice, 'Sales')
		await dashboard(alice, 'Marketing')
		await dashboard(alice, 'HR')
		const term = 'nonexistent-term-xyz'

		const answer = await search(alice, term)
		expect(answer.status).toBe(200)
		expect(answer.name).toBe('Dashboards')
		expect(answer.isPaginated).toBe(false)
		expect(answer.entries).toEqual([])

		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, term)
			// The scenario allows "or equivalent": Nextcloud 34's dialog says "No matching results".
			await expect(dialog).toContainText(
				new RegExp(`No results for.*${escapeRe(term)}|No matching results`),
				{ timeout: 20_000 },
			)
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::empty-search-term
	test('an empty or whitespace-only term returns no LaunchPad entries', async () => {
		// Nextcloud drops a blank term from the filter list and refuses the
		// request itself ("No valid filters provided") before any provider
		// runs, so over HTTP the answer is core's 400, never a LaunchPad
		// error. The provider's own empty answer for a blank term is pinned by
		// LaunchPadSearchProviderTest::testEmptyTermReturnsEmptyResult.
		const alice = await account('blank')
		await dashboard(alice, `Blank probe ${RUN}`)
		for (const term of ['', '   ']) {
			const res = await alice.api.get(
				`${SEARCH}/${PROVIDER}/search?format=json&term=${encodeURIComponent(term)}`,
			)
			const body = await res.json()
			expect(res.status(), JSON.stringify(term)).toBe(400)
			expect(body.ocs?.data, JSON.stringify(term)).toBe('No valid filters provided')
		}
	})

	// @e2e nc-unified-search-integration::very-short-query-term-single-character
	test('a single character is searched; no match is an empty result', async () => {
		const bob = await account('short')
		const dash = await dashboard(bob, 'Admin Dashboard')
		const answer = await search(bob, 'a')
		expect(answer.status).toBe(200)
		expect(answer.entries.filter(isDashboard).map(uuidOf)).toContain(dash.uuid)

		const carol = await account('shortnone')
		const none = await search(carol, 'ж')
		expect(none.status).toBe(200)
		expect(none.entries).toEqual([])
	})
})

test.describe.serial('unified search: search UI (REQ-SRCH-011)', () => {
	// @e2e nc-unified-search-integration::search-result-is-clickable
	test('clicking a result opens that dashboard in LaunchPad', async ({ browser }) => {
		const alice = await account('click')
		const other = await dashboard(alice, `Other board ${RUN}`)
		const target = await dashboard(alice, `Marketing Dashboard ${RUN}`)
		const active = await alice.api.post(`${API}/dashboards/default`, { data: { uuid: other.uuid } })
		expect(active.status(), await active.text()).toBe(200)

		const provider = await launchpadProvider(alice)
		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, `marketing dashboard ${RUN}`)
			const link = resultSection(dialog, provider.name).locator(`a[href*="${target.uuid}"]`)
			await expect(link).toContainText(target.name, { timeout: 20_000 })
			await link.click()
			await expect(page).toHaveURL(new RegExp(`#dashboard/${target.uuid}`), { timeout: 30_000 })
			await expect(page.locator('.workspace-shell')).toBeVisible({ timeout: 30_000 })
			await openDashboardSwitcher(page)
			await expect(
				page.locator('[data-testid="launchpad-more-menu-dashboard-list"] [role="menuitemradio"]').filter({ hasText: target.name }),
			).toHaveAttribute('aria-checked', 'true')
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::search-result-resourceurl-is-deep-linked
	test('clicking a widget result navigates to the uuid + widget deep-link', async ({ browser }) => {
		const bob = await account('wclick')
		const dash = await dashboard(bob, 'Analytics')
		const pid = await textWidget(dash, `Widgetclick ${RUN}`)
		const provider = await launchpadProvider(bob)

		const { context, page } = await loginAs(browser, bob.username, bob.password)
		try {
			const dialog = await openUnifiedSearch(page, `Widgetclick ${RUN}`)
			const link = resultSection(dialog, provider.name).locator(`a[href*="widget=${pid}"]`)
			await expect(link).toBeVisible({ timeout: 20_000 })
			await link.click()
			await expect(page).toHaveURL(new RegExp(`${dash.uuid}.*widget=${pid}`), { timeout: 30_000 })
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::search-result-thumbnail-is-displayed
	test('the result shows the thumbnail, loaded and at icon size', async ({ browser }) => {
		const alice = await account('thumb')
		const dash = await dashboard(alice, `Thumb board ${RUN}`)
		const provider = await launchpadProvider(alice)
		const hit = about((await search(alice, `Thumb board ${RUN}`)).entries, dash)[0]
		expect(hit).toBeDefined()

		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, `Thumb board ${RUN}`)
			const link = resultSection(dialog, provider.name).locator(`a[href*="${dash.uuid}"]`)
			await expect(link).toBeVisible({ timeout: 20_000 })
			const shown = await link.evaluate((root, url) => {
				const nodes = [root, ...Array.from(root.querySelectorAll('*'))] as HTMLElement[]
				for (const node of nodes) {
					const bg = getComputedStyle(node).backgroundImage
					const src = node instanceof HTMLImageElement ? node.currentSrc || node.src : ''
					if (bg.includes(url) || src.includes(url) || bg.includes('app.svg') || src.includes('app.svg')) {
						const rect = node.getBoundingClientRect()
						return { width: Math.round(rect.width), height: Math.round(rect.height) }
					}
				}
				return null
			}, new URL(hit.thumbnailUrl).pathname)
			expect(shown, 'an element in the result renders the thumbnail').not.toBeNull()
			expect(shown!.width).toBe(shown!.height)
			expect(shown!.width).toBeGreaterThanOrEqual(16)
			expect(shown!.width).toBeLessThanOrEqual(64)
			const fetched = await page.request.get(hit.thumbnailUrl)
			expect(fetched.status()).toBe(200)
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::search-result-appears-in-the-correct-section
	test('LaunchPad results stay in their own section, apart from file results', async ({ browser }) => {
		const alice = await account('section')
		const token = `sect${RUN}`
		const dash = await dashboard(alice, `Section board ${token}`)
		const fileName = `${token}-notes.txt`
		await uploadFile(alice, fileName)
		const listed = await providers(alice)
		const launchpadName = listed.find((p) => p.id === PROVIDER)!.name
		const filesName = listed.find((p) => p.id === 'files')!.name

		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, token)
			const ours = resultSection(dialog, launchpadName)
			const files = resultSection(dialog, filesName)
			await expect(ours.locator(`a[href*="${dash.uuid}"]`)).toBeVisible({ timeout: 20_000 })
			await expect(files).toContainText(fileName, { timeout: 20_000 })
			await expect(ours).not.toContainText(fileName)
			await expect(files.locator(`a[href*="${dash.uuid}"]`)).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})

test.describe.serial('unified search: provider order (REQ-SRCH-012)', () => {
	// @e2e nc-unified-search-integration::provider-order-is-respected
	test('outside LaunchPad the provider sits at 50, and groups follow provider order', async ({ browser }) => {
		const alice = await account('order')
		const token = `ord${RUN}`
		const dash = await dashboard(alice, `Order board ${token}`)
		await uploadFile(alice, `${token}-file.txt`)

		const listed = await providers(alice)
		const index = listed.findIndex((p) => p.id === PROVIDER)
		expect(listed[index].order).toBe(50)
		listed.forEach((p, i) => {
			if (p.order < 50) {
				expect(i, `${p.id} (${p.order}) precedes launchpad`).toBeLessThan(index)
			}
			if (p.order > 50) {
				expect(i, `${p.id} (${p.order}) follows launchpad`).toBeGreaterThan(index)
			}
		})

		const orderOf = new Map(listed.map((p) => [p.name, p.order]))
		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			const dialog = await openUnifiedSearch(page, token)
			await expect(resultSection(dialog, listed[index].name).locator(`a[href*="${dash.uuid}"]`))
				.toBeVisible({ timeout: 20_000 })
			await expect(dialog).toContainText(`${token}-file.txt`, { timeout: 20_000 })
			const titles = await sectionTitles(dialog)
			const orders = titles.map((title) => orderOf.get(title) ?? Number.NaN)
			expect(orders).toEqual([...orders].sort((a, b) => a - b))
			const filesName = listed.find((p) => p.id === 'files')!.name
			expect(titles.indexOf(filesName)).toBeLessThan(titles.indexOf(listed[index].name))
		} finally {
			await context.close()
		}
	})

	// @e2e nc-unified-search-integration::order-does-not-affect-search-completeness
	test('the same results come back whatever order the provider has', async () => {
		const alice = await account('complete')
		const dashes = [
			await dashboard(alice, `Order probe ${RUN} one`),
			await dashboard(alice, `Order probe ${RUN} two`),
			await dashboard(alice, `Order probe ${RUN} three`),
		]
		expect((await launchpadProvider(alice, IN_APP)).order)
			.not.toBe((await launchpadProvider(alice, OFF_APP)).order)

		const inApp = await search(alice, `Order probe ${RUN}`, IN_APP)
		const offApp = await search(alice, `Order probe ${RUN}`, OFF_APP)
		const links = (answer: Answer) => answer.entries.map((e) => e.resourceUrl).sort()
		expect(links(inApp)).toEqual(links(offApp))
		for (const dash of dashes) {
			expect(inApp.entries.map(uuidOf)).toContain(dash.uuid)
		}
	})

	// @e2e nc-unified-search-integration::boost-in-app-order
	test('on a LaunchPad route the provider is ordered ahead of 50', async () => {
		const alice = await account('boost')
		expect((await launchpadProvider(alice, OFF_APP)).order).toBe(50)
		const boosted = await launchpadProvider(alice, IN_APP)
		expect(boosted.order).toBeLessThan(50)
		expect(boosted.order).toBe(5)
	})
})
