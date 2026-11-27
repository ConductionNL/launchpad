/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for dashboard metadata ("detail") fields
 * (openspec/specs/dashboard-metadata-fields), written for
 * e2e-coverage-programme task 2.7.
 *
 * Two halves:
 *
 *  - The HTTP contract (REQ-MDFL-001..008): the admin field registry under
 *    `/api/admin/metadata-fields`, the per-dashboard values under
 *    `/api/dashboards/{uuid}/metadata` and the list filter. These have no
 *    browser control of their own beyond the pages below, so they are driven
 *    over the API and every status, message and body the scenario names is
 *    asserted, then re-read to show what the instance actually holds.
 *  - The pages (REQ-MDUI-001..003): Beheer ▸ Detail fields, the Details
 *    section of the dashboard settings dialog, and "Filter by detail" in the
 *    dashboard switcher, all driven through their own controls.
 *
 * Field keys are instance-wide and unique, so every key carries this run's
 * stamp; the labels the scenarios name ("Department", "Go Live", ...) are used
 * as written because the validation messages embed them. Every field, value,
 * dashboard and account this file creates is removed in afterAll, and the
 * personal-dashboard settings it switches on are put back as they were.
 *
 * A REQUIRED FIELD IS INSTANCE-WIDE STATE. The Details tab saves every defined
 * field, so a required field left behind makes every other spec's Details save
 * answer 400. The two tests that create one delete it in their own `finally`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-metadata-fields::create-a-text-field-definition
 *   @e2e dashboard-metadata-fields::validate-key-format
 *   @e2e dashboard-metadata-fields::create-a-select-field-with-options
 *   @e2e dashboard-metadata-fields::select-field-without-options-is-rejected
 *   @e2e dashboard-metadata-fields::non-admin-cannot-create-field-definitions
 *   @e2e dashboard-metadata-fields::list-all-field-definitions
 *   @e2e dashboard-metadata-fields::update-field-label-and-sortorder
 *   @e2e dashboard-metadata-fields::forbid-key-rename
 *   @e2e dashboard-metadata-fields::delete-field-with-no-values
 *   @e2e dashboard-metadata-fields::delete-field-without-cascade-rejected-when-values-exist
 *   @e2e dashboard-metadata-fields::get-metadata-for-a-dashboard
 *   @e2e dashboard-metadata-fields::empty-metadata-for-a-new-dashboard
 *   @e2e dashboard-metadata-fields::set-metadata-on-a-dashboard
 *   @e2e dashboard-metadata-fields::partial-update-upsert-specific-keys
 *   @e2e dashboard-metadata-fields::setting-a-required-field-to-null
 *   @e2e dashboard-metadata-fields::number-field-validation
 *   @e2e dashboard-metadata-fields::number-field-accepts-decimal
 *   @e2e dashboard-metadata-fields::date-field-validation
 *   @e2e dashboard-metadata-fields::select-field-validation
 *   @e2e dashboard-metadata-fields::filter-dashboards-by-text-field-exact-match
 *   @e2e dashboard-metadata-fields::filter-dashboards-by-numeric-range
 *   @e2e dashboard-metadata-fields::multiple-metadata-filters-and-logic
 *   @e2e dashboard-metadata-fields::filter-by-select-field
 *   @e2e dashboard-metadata-fields::user-can-read-their-own-dashboards-metadata
 *   @e2e dashboard-metadata-fields::user-cannot-read-another-users-dashboard-metadata
 *   @e2e dashboard-metadata-fields::user-can-write-metadata-for-their-own-dashboard
 *   @e2e dashboard-metadata-fields::department-field
 *   @e2e dashboard-metadata-fields::select-without-options
 *   @e2e dashboard-metadata-fields::set-the-owning-department
 *   @e2e dashboard-metadata-fields::filter-to-finance
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { openDashboardSettings, openDashboardSwitcher, switchToDashboard } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const FIELDS = `${API}/admin/metadata-fields`
const SETTINGS = `${API}/admin/settings`
const SETTINGS_PAGE = `${BASE}/index.php/apps/launchpad/admin`
const ROW = '[data-testid="launchpad-more-menu-dashboard-list"] [role="menuitemradio"]'

/** Five minutes either way: "current time" on a container clock. */
const CLOCK_SLACK_MS = 5 * 60_000

interface Field {
	id: number
	key: string
	label: string
	type: string
	options: string[] | null
	required: number
	sortOrder: number
	createdAt: string | null
	updatedAt: string | null
}

interface Dash {
	id: number
	uuid: string
	name: string
	owner: APIRequestContext
}

let admin: APIRequestContext
/** The dashboard owner ("alice", "Sanne", "Pieter" in the scenarios). */
let owner: Principal | null = null
/** Another ordinary account with no access to the owner's dashboards ("bob"/"alice"). */
let stranger: Principal | null = null
/** A fresh account whose personal list holds only the filter dashboards. */
let lister: Principal | null = null

let previousSettings: { allowUserDash: boolean; allowMultiDash: boolean } | null =
	null
const createdFieldIds = new Set<number>()
const createdDashboards: Dash[] = []

/**
 * Create a field as the admin and remember it for cleanup.
 *
 * @param body the create payload.
 * @return the created field.
 */
async function createField(body: Record<string, unknown>): Promise<Field> {
	const res = await admin.post(FIELDS, { data: body })
	expect(res.status(), await res.text()).toBe(201)
	const field = (await res.json()) as Field
	createdFieldIds.add(Number(field.id))
	return field
}

/**
 * Delete a field (and any values it carries) and forget it.
 *
 * @param id the field id.
 * @return nothing.
 */
async function dropField(id: number): Promise<void> {
	await admin.delete(`${FIELDS}/${id}?cascade=true`)
	createdFieldIds.delete(id)
}

/**
 * Every defined field, as the admin reads them.
 *
 * @return the listing envelope.
 */
async function listFields(): Promise<{ fields: Field[]; count: number }> {
	const res = await admin.get(FIELDS)
	expect(res.status()).toBe(200)
	return res.json()
}

/**
 * Create a personal dashboard as `ctx` and remember it for cleanup.
 *
 * @param ctx the owner's context.
 * @param name the dashboard name.
 * @return the dashboard.
 */
async function createDashboard(ctx: APIRequestContext, name: string): Promise<Dash> {
	const res = await ctx.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const d = body.dashboard ?? body
	const dash = { id: Number(d.id), uuid: String(d.uuid), name, owner: ctx }
	expect(dash.uuid, JSON.stringify(body)).toMatch(/^[0-9a-f-]{36}$/)
	createdDashboards.push(dash)
	return dash
}

/**
 * Write metadata on a dashboard and insist it was accepted.
 *
 * @param ctx who writes.
 * @param uuid the dashboard.
 * @param metadata the key/value map.
 * @return the stored metadata the server answered with.
 */
async function writeMetadata(
	ctx: APIRequestContext,
	uuid: string,
	metadata: Record<string, unknown>,
): Promise<Record<string, string>> {
	const res = await ctx.put(`${API}/dashboards/${uuid}/metadata`, {
		data: { metadata },
	})
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Read a dashboard's metadata, insisting on 200.
 *
 * @param ctx who reads.
 * @param uuid the dashboard.
 * @return the metadata map.
 */
async function readMetadata(
	ctx: APIRequestContext,
	uuid: string,
): Promise<Record<string, string>> {
	const res = await ctx.get(`${API}/dashboards/${uuid}/metadata`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * The uuids `GET /api/dashboards?<query>` answers, in either list shape.
 *
 * @param ctx who lists.
 * @param query the raw query string, exactly as the scenario spells it.
 * @return the uuids.
 */
async function listedUuids(
	ctx: APIRequestContext,
	query: string,
): Promise<string[]> {
	const res = await ctx.get(`${API}/dashboards?${query}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows: Array<{ uuid: string }> = Array.isArray(body)
		? body
		: (body.items ?? [])
	return rows.map((r) => String(r.uuid)).sort()
}

/**
 * Assert a timestamp the server set is "now" on its clock.
 *
 * @param stamp the ISO-8601 value.
 * @param before a wall-clock time just before the request.
 * @return the parsed time.
 */
function expectNow(stamp: string | null, before: number): number {
	expect(stamp, 'the timestamp must be set').toBeTruthy()
	const at = Date.parse(String(stamp))
	expect(Number.isNaN(at), `unparseable timestamp ${stamp}`).toBe(false)
	expect(Math.abs(at - before)).toBeLessThan(CLOCK_SLACK_MS)
	return at
}

/**
 * Choose an option in an NcSelect by typing it, then taking the one match.
 *
 * @param page the page (the option list is appended to body).
 * @param select the `.v-select` root.
 * @param text the option to choose.
 * @return nothing.
 */
async function pick(page: Page, select: Locator, text: string): Promise<void> {
	const input = select.locator('input').first()
	await input.click()
	await input.fill(text)
	const option = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(option.first()).toBeVisible({ timeout: 5_000 })
	await option.first().click()
}

/**
 * Open the dashboard settings dialog for one named personal dashboard
 * through its switcher row menu, and wait for its Details section.
 *
 * @param page a logged-in page.
 * @param name the dashboard's name.
 * @return the Details section.
 */
async function openDetails(page: Page, name: string): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	await switchToDashboard(page, name)
	await openDashboardSettings(page)
	const details = page.locator('[data-testid="details-tab"]')
	await expect(details).toBeVisible({ timeout: 15_000 })
	return details
}

/**
 * Make `uuid` the account's active dashboard so the shell renders on load.
 *
 * @param ctx the account.
 * @param uuid the dashboard.
 * @return nothing.
 */
async function activate(ctx: APIRequestContext, uuid: string): Promise<void> {
	const res = await ctx.post(`${API}/dashboards/active`, { data: { uuid } })
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Open Beheer ▸ Detail fields as the admin.
 *
 * @param page the admin page.
 * @return the section.
 */
async function openDetailFieldsTab(page: Page): Promise<Locator> {
	await page.goto(SETTINGS_PAGE, {
		waitUntil: 'domcontentloaded',
		timeout: 60_000,
	})
	await adminTab(page, 'detail-fields').click({ timeout: 30_000 })
	const section = page.locator('[data-testid="metadata-fields-settings"]')
	await expect(section).toBeVisible({ timeout: 20_000 })
	return section
}

/**
 * Log in as a principal in a fresh browser context.
 *
 * @param browser the browser fixture.
 * @param who the account.
 * @return the page; close its context when done.
 */
async function pageAs(browser: Browser, who: Principal): Promise<Page> {
	const { page } = await loginAs(browser, who.username, who.password)
	return page
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')

	const settings = await (await admin.get(SETTINGS)).json()
	previousSettings = {
		allowUserDash: Boolean(settings.allowUserDashboards),
		allowMultiDash: settings.allowMultipleDashboards !== false,
	}
	const enable = await admin.put(SETTINGS, {
		data: { allowUserDash: true, allowMultiDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	owner = await createPrincipal(admin, 'mdfl-owner')
	stranger = await createPrincipal(admin, 'mdfl-stranger')
	lister = await createPrincipal(admin, 'mdfl-lister')
	await assertActingAs(owner.api, owner.username)
	await assertActingAs(stranger.api, stranger.username)
	await assertActingAs(lister.api, lister.username)
})

test.afterAll(async () => {
	for (const id of [...createdFieldIds]) {
		await dropField(id)
	}
	for (const dash of createdDashboards.reverse()) {
		await dash.owner.delete(`${API}/dashboard/${dash.id}`).catch(() => null)
	}
	await removePrincipal(admin, owner)
	await removePrincipal(admin, stranger)
	await removePrincipal(admin, lister)
	if (previousSettings !== null) {
		await admin.put(SETTINGS, { data: previousSettings })
	}
	await admin.dispose()
})

test.describe.serial('field definitions (REQ-MDFL-001..003)', () => {
	// @e2e dashboard-metadata-fields::create-a-text-field-definition
	test('creating a text field answers 201 with every default the spec names', async () => {
		const key = `department_${RUN}`
		const before = Date.now()
		const res = await admin.post(FIELDS, {
			data: { key, label: 'Department', type: 'text', required: 1 },
		})
		expect(res.status(), await res.text()).toBe(201)
		const field = (await res.json()) as Field
		createdFieldIds.add(Number(field.id))
		try {
			expect(field.key).toBe(key)
			expect(field.label).toBe('Department')
			expect(field.type).toBe('text')
			expect(field.required).toBe(1)
			expect(field.options).toBeNull()
			expect(field.sortOrder).toBe(0)
			expectNow(field.createdAt, before)
			expectNow(field.updatedAt, before)

			// The record exists, under the key it was given.
			const read = await admin.get(`${FIELDS}/${field.id}`)
			expect(read.status()).toBe(200)
			const stored = (await read.json()) as Field
			expect(stored).toMatchObject({
				key,
				label: 'Department',
				type: 'text',
				required: 1,
				options: null,
				sortOrder: 0,
			})
		} finally {
			// Required: must not outlive this test (see the header).
			await dropField(Number(field.id))
		}
	})

	// @e2e dashboard-metadata-fields::validate-key-format
	test('a key with capitals and a space is refused with 400 and nothing is created', async () => {
		const before = await listFields()
		const res = await admin.post(FIELDS, {
			data: { key: 'Invalid Key', label: 'L', type: 'text' },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe(
			'Field key must be lowercase alphanumeric with underscores only',
		)
		const after = await listFields()
		expect(after.count).toBe(before.count)
		expect(after.fields.map((f) => f.key)).not.toContain('Invalid Key')
	})

	// @e2e dashboard-metadata-fields::create-a-select-field-with-options
	test('a select field keeps its options and answers 201', async () => {
		const field = await createField({
			key: `status_${RUN}`,
			label: 'Status',
			type: 'select',
			options: ['open', 'closed', 'pending'],
		})
		expect(field.type).toBe('select')
		expect(field.options).toEqual(['open', 'closed', 'pending'])

		const stored = (await (
			await admin.get(`${FIELDS}/${field.id}`)
		).json()) as Field
		expect(stored.type).toBe('select')
		expect(stored.options).toEqual(['open', 'closed', 'pending'])
	})

	// @e2e dashboard-metadata-fields::select-field-without-options-is-rejected
	test('a select field without options is refused with 400 and nothing is created', async () => {
		const key = `status_noopts_${RUN}`
		const before = await listFields()
		const res = await admin.post(FIELDS, {
			data: { key, label: 'Status', type: 'select' },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe(
			'Select type requires non-empty options array',
		)
		const after = await listFields()
		expect(after.count).toBe(before.count)
		expect(after.fields.map((f) => f.key)).not.toContain(key)
	})

	// @e2e dashboard-metadata-fields::non-admin-cannot-create-field-definitions
	test('an ordinary account is refused with 403, the admin is not', async () => {
		const key = `nonadmin_${RUN}`
		const body = { key, label: 'Department', type: 'text' }

		const refused = await stranger!.api.post(FIELDS, { data: body })
		expect(refused.status()).toBe(403)
		expect((await listFields()).fields.map((f) => f.key)).not.toContain(key)

		// The same body from the admin is accepted, so the 403 was the rule.
		const allowed = await createField(body)
		expect(allowed.key).toBe(key)
	})

	// @e2e dashboard-metadata-fields::list-all-field-definitions
	test('the listing is an envelope with a count, sorted by sortOrder ascending', async () => {
		const ten = await createField({
			key: `list_ten_${RUN}`,
			label: 'Ten',
			type: 'text',
			sortOrder: 10,
		})
		const zero = await createField({
			key: `list_zero_${RUN}`,
			label: 'Zero',
			type: 'text',
			sortOrder: 0,
		})
		const five = await createField({
			key: `list_five_${RUN}`,
			label: 'Five',
			type: 'text',
			sortOrder: 5,
		})

		const res = await admin.get(FIELDS)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body).sort()).toEqual(['count', 'fields'])
		expect(Array.isArray(body.fields)).toBe(true)
		// Other specs may have fields of their own; the count is of all of them.
		expect(body.count).toBe(body.fields.length)

		const mine = (body.fields as Field[])
			.filter((f) => [ten.id, zero.id, five.id].includes(f.id))
			.map((f) => f.sortOrder)
		expect(mine).toEqual([0, 5, 10])

		const orders = (body.fields as Field[]).map((f) => f.sortOrder)
		expect(orders).toEqual([...orders].sort((a, b) => a - b))
	})

	// @e2e dashboard-metadata-fields::update-field-label-and-sortorder
	test('a PUT changes the label and sortOrder and moves updatedAt', async () => {
		const field = await createField({
			key: `department_upd_${RUN}`,
			label: 'Dept',
			type: 'text',
			sortOrder: 0,
		})
		// The stamp has one-second resolution; let a second pass.
		await new Promise((resolve) => setTimeout(resolve, 1_100))

		const before = Date.now()
		const res = await admin.put(`${FIELDS}/${field.id}`, {
			data: { label: 'Department (Required)', sortOrder: 100 },
		})
		expect(res.status(), await res.text()).toBe(200)
		const updated = (await res.json()) as Field
		expect(updated.id).toBe(field.id)
		expect(updated.label).toBe('Department (Required)')
		expect(updated.sortOrder).toBe(100)
		const at = expectNow(updated.updatedAt, before)
		expect(at).toBeGreaterThan(Date.parse(String(field.updatedAt)))

		const stored = (await (
			await admin.get(`${FIELDS}/${field.id}`)
		).json()) as Field
		expect(stored.label).toBe('Department (Required)')
		expect(stored.sortOrder).toBe(100)
		expect(stored.key).toBe(field.key)
	})

	// @e2e dashboard-metadata-fields::forbid-key-rename
	test('renaming the key is refused with 400 and the key stays', async () => {
		const field = await createField({
			key: `department_ren_${RUN}`,
			label: 'Department',
			type: 'text',
		})
		const res = await admin.put(`${FIELDS}/${field.id}`, {
			data: { key: 'division' },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe('Field key cannot be renamed')

		const stored = (await (
			await admin.get(`${FIELDS}/${field.id}`)
		).json()) as Field
		expect(stored.key).toBe(field.key)
	})

	// @e2e dashboard-metadata-fields::delete-field-with-no-values
	test('a field without values is deleted with 200', async () => {
		const field = await createField({
			key: `delete_empty_${RUN}`,
			label: 'Department',
			type: 'text',
		})
		const res = await admin.delete(`${FIELDS}/${field.id}`)
		expect(res.status(), await res.text()).toBe(200)
		createdFieldIds.delete(field.id)

		expect((await admin.get(`${FIELDS}/${field.id}`)).status()).toBe(404)
		expect((await listFields()).fields.map((f) => f.id)).not.toContain(field.id)
	})

	// @e2e dashboard-metadata-fields::delete-field-without-cascade-rejected-when-values-exist
	test('deleting a field with three values is refused with 409 and nothing changes', async () => {
		const field = await createField({
			key: `delete_used_${RUN}`,
			label: 'Department',
			type: 'text',
		})
		const dashes = [
			await createDashboard(owner!.api, `MDFL used A ${RUN}`),
			await createDashboard(owner!.api, `MDFL used B ${RUN}`),
			await createDashboard(owner!.api, `MDFL used C ${RUN}`),
		]
		for (const [i, d] of dashes.entries()) {
			await writeMetadata(owner!.api, d.uuid, { [field.key]: `value-${i}` })
		}

		const res = await admin.delete(`${FIELDS}/${field.id}`)
		expect(res.status()).toBe(409)
		const body = await res.json()
		expect(body.error).toBe('metadata_field_has_values')
		expect(body.valueCount).toBe(3)

		const stored = await admin.get(`${FIELDS}/${field.id}`)
		expect(stored.status()).toBe(200)
		expect((await stored.json()).key).toBe(field.key)
		for (const [i, d] of dashes.entries()) {
			expect((await readMetadata(owner!.api, d.uuid))[field.key]).toBe(
				`value-${i}`,
			)
		}
	})
})

test.describe.serial('dashboard values (REQ-MDFL-004..006, REQ-MDFL-008)', () => {
	let department: Field
	let priority: Field
	let score: Field
	let goLive: Field
	let status: Field
	let approval: Field

	test.beforeAll(async () => {
		department = await createField({
			key: `v_department_${RUN}`,
			label: 'Department',
			type: 'text',
		})
		priority = await createField({
			key: `v_priority_${RUN}`,
			label: 'Priority',
			type: 'number',
		})
		score = await createField({
			key: `v_score_${RUN}`,
			label: 'Score',
			type: 'number',
		})
		// The scenario's `go-live` is not a legal key (lowercase, digits and
		// underscores only), so the key is go_live; the message names the label.
		goLive = await createField({
			key: `v_go_live_${RUN}`,
			label: 'Go Live',
			type: 'date',
		})
		status = await createField({
			key: `v_status_${RUN}`,
			label: 'Status',
			type: 'select',
			options: ['open', 'closed', 'pending'],
		})
		approval = await createField({
			key: `v_approval_${RUN}`,
			label: 'Status',
			type: 'select',
			options: ['draft', 'approved'],
		})
	})

	// @e2e dashboard-metadata-fields::get-metadata-for-a-dashboard
	test('the owner reads every value as one flat object of strings', async () => {
		const d = await createDashboard(owner!.api, `MDFL read ${RUN}`)
		await writeMetadata(owner!.api, d.uuid, {
			[department.key]: 'marketing',
			[priority.key]: 8,
			[approval.key]: 'approved',
		})

		const res = await owner!.api.get(`${API}/dashboards/${d.uuid}/metadata`)
		expect(res.status()).toBe(200)
		expect(await res.json()).toEqual({
			[department.key]: 'marketing',
			[priority.key]: '8',
			[approval.key]: 'approved',
		})
	})

	// @e2e dashboard-metadata-fields::empty-metadata-for-a-new-dashboard
	test('a new dashboard has an empty object, not an empty list', async () => {
		const d = await createDashboard(owner!.api, `MDFL empty ${RUN}`)
		const res = await owner!.api.get(`${API}/dashboards/${d.uuid}/metadata`)
		expect(res.status()).toBe(200)
		const text = (await res.text()).trim()
		const body = JSON.parse(text)
		// `{}` and `[]` both read as "nothing", but only one is the object
		// the scenario names; a client doing `body[key] = ...` breaks on `[]`.
		expect(Array.isArray(body), `answered ${text}`).toBe(false)
		expect(typeof body).toBe('object')
		expect(body).toEqual({})
	})

	// @e2e dashboard-metadata-fields::set-metadata-on-a-dashboard
	test('a PUT creates both values and answers 200 with them', async () => {
		const d = await createDashboard(owner!.api, `MDFL set ${RUN}`)
		const res = await owner!.api.put(`${API}/dashboards/${d.uuid}/metadata`, {
			data: {
				metadata: { [department.key]: 'marketing', [priority.key]: '5' },
			},
		})
		expect(res.status(), await res.text()).toBe(200)
		const expected = { [department.key]: 'marketing', [priority.key]: '5' }
		expect(await res.json()).toEqual(expected)
		expect(await readMetadata(owner!.api, d.uuid)).toEqual(expected)
	})

	// @e2e dashboard-metadata-fields::partial-update-upsert-specific-keys
	test('a PUT touches only the keys it names and answers the whole object', async () => {
		const d = await createDashboard(owner!.api, `MDFL partial ${RUN}`)
		await writeMetadata(owner!.api, d.uuid, {
			[department.key]: 'marketing',
			[priority.key]: '5',
			[status.key]: 'open',
		})

		const answered = await writeMetadata(owner!.api, d.uuid, {
			[department.key]: 'sales',
			[status.key]: 'pending',
		})
		const expected = {
			[department.key]: 'sales',
			[priority.key]: '5',
			[status.key]: 'pending',
		}
		expect(answered).toEqual(expected)
		expect(await readMetadata(owner!.api, d.uuid)).toEqual(expected)
	})

	// @e2e dashboard-metadata-fields::setting-a-required-field-to-null
	test('null on a required field is refused with 400 naming the label', async () => {
		const required = await createField({
			key: `v_required_${RUN}`,
			label: 'Department',
			type: 'text',
			required: 1,
		})
		try {
			const d = await createDashboard(owner!.api, `MDFL required ${RUN}`)
			await writeMetadata(owner!.api, d.uuid, { [required.key]: 'marketing' })

			const res = await owner!.api.put(
				`${API}/dashboards/${d.uuid}/metadata`,
				{
					data: { metadata: { [required.key]: null } },
				},
			)
			expect(res.status()).toBe(400)
			expect((await res.json()).message).toBe("Field 'Department' is required")
			expect((await readMetadata(owner!.api, d.uuid))[required.key]).toBe(
				'marketing',
			)
		} finally {
			// Required: must not outlive this test (see the header).
			await dropField(required.id)
		}
	})

	// @e2e dashboard-metadata-fields::number-field-validation
	test('a number field refuses text with 400', async () => {
		const d = await createDashboard(owner!.api, `MDFL number ${RUN}`)
		const res = await owner!.api.put(`${API}/dashboards/${d.uuid}/metadata`, {
			data: { metadata: { [priority.key]: 'not-a-number' } },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe(
			"Field 'Priority' must be a valid number",
		)
		expect(await readMetadata(owner!.api, d.uuid)).not.toHaveProperty(
			priority.key,
		)
	})

	// @e2e dashboard-metadata-fields::number-field-accepts-decimal
	test('a number field accepts and stores a decimal', async () => {
		const d = await createDashboard(owner!.api, `MDFL decimal ${RUN}`)
		const answered = await writeMetadata(owner!.api, d.uuid, {
			[score.key]: '42.75',
		})
		expect(answered[score.key]).toBe('42.75')
		expect((await readMetadata(owner!.api, d.uuid))[score.key]).toBe('42.75')
	})

	// @e2e dashboard-metadata-fields::date-field-validation
	test('a date field refuses a non-date with 400', async () => {
		const d = await createDashboard(owner!.api, `MDFL date ${RUN}`)
		const res = await owner!.api.put(`${API}/dashboards/${d.uuid}/metadata`, {
			data: { metadata: { [goLive.key]: 'invalid-date' } },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe(
			"Field 'Go Live' must be a valid date (YYYY-MM-DD)",
		)
		expect(await readMetadata(owner!.api, d.uuid)).not.toHaveProperty(goLive.key)
	})

	// @e2e dashboard-metadata-fields::select-field-validation
	test('a select field refuses a value outside its options with 400', async () => {
		const d = await createDashboard(owner!.api, `MDFL select ${RUN}`)
		const res = await owner!.api.put(`${API}/dashboards/${d.uuid}/metadata`, {
			data: { metadata: { [status.key]: 'rejected' } },
		})
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toBe(
			"Field 'Status' value 'rejected' not in allowed options",
		)
		expect(await readMetadata(owner!.api, d.uuid)).not.toHaveProperty(status.key)
	})

	// @e2e dashboard-metadata-fields::user-can-read-their-own-dashboards-metadata
	test('the owner reads their own dashboard metadata with 200', async () => {
		const d = await createDashboard(owner!.api, `MDFL own read ${RUN}`)
		await writeMetadata(owner!.api, d.uuid, { [department.key]: 'finance' })
		const res = await owner!.api.get(`${API}/dashboards/${d.uuid}/metadata`)
		expect(res.status()).toBe(200)
		expect(await res.json()).toEqual({ [department.key]: 'finance' })
	})

	// @e2e dashboard-metadata-fields::user-cannot-read-another-users-dashboard-metadata
	test('an ordinary account cannot read a stranger dashboard metadata', async () => {
		// "bob" is the owner here, "alice" the stranger with no share.
		const d = await createDashboard(owner!.api, `MDFL private ${RUN}`)
		await writeMetadata(owner!.api, d.uuid, { [department.key]: 'secret-dept' })

		const refused = await stranger!.api.get(
			`${API}/dashboards/${d.uuid}/metadata`,
		)
		expect([403, 404]).toContain(refused.status())
		expect(await refused.text()).not.toContain('secret-dept')

		// The owner still reads it: the refusal was about who asked.
		expect(await readMetadata(owner!.api, d.uuid)).toEqual({
			[department.key]: 'secret-dept',
		})
	})

	// @e2e dashboard-metadata-fields::user-can-write-metadata-for-their-own-dashboard
	test('the owner writes their own dashboard metadata with 200', async () => {
		const d = await createDashboard(owner!.api, `MDFL own write ${RUN}`)
		const res = await owner!.api.put(`${API}/dashboards/${d.uuid}/metadata`, {
			data: { metadata: { [department.key]: 'marketing' } },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json())[department.key]).toBe('marketing')
		expect((await readMetadata(owner!.api, d.uuid))[department.key]).toBe(
			'marketing',
		)
	})
})

/*
 * The scenarios spell the filter `?metadata.<key>=<value>` on
 * `GET /api/dashboards`, and that literal request is what these send. The
 * lister is a fresh account, so its personal list holds exactly the
 * dashboards seeded here and an unfiltered list would fail every assertion.
 */
test.describe.serial('list filter (REQ-MDFL-007)', () => {
	let department: Field
	let priority: Field
	let status: Field
	const byName: Record<string, Dash> = {}

	test.beforeAll(async () => {
		department = await createField({
			key: `f_department_${RUN}`,
			label: 'Department',
			type: 'text',
		})
		priority = await createField({
			key: `f_priority_${RUN}`,
			label: 'Priority',
			type: 'number',
		})
		status = await createField({
			key: `f_status_${RUN}`,
			label: 'Status',
			type: 'select',
			options: ['open', 'closed', 'pending'],
		})
		const seeds: Array<[string, Record<string, unknown>]> = [
			['marketing1', { department: 'marketing', priority: 1, status: 'open' }],
			['sales3', { department: 'sales', priority: 3, status: 'closed' }],
			[
				'engineering5',
				{ department: 'engineering', priority: 5, status: 'pending' },
			],
			[
				'marketing7',
				{ department: 'marketing', priority: 7, status: 'closed' },
			],
			['sales9', { department: 'sales', priority: 9, status: 'open' }],
		]
		for (const [name, v] of seeds) {
			const d = await createDashboard(lister!.api, `MDFL ${name} ${RUN}`)
			byName[name] = d
			await writeMetadata(lister!.api, d.uuid, {
				[department.key]: v.department,
				[priority.key]: v.priority,
				[status.key]: v.status,
			})
		}
	})

	const uuidsOf = (...names: string[]): string[] =>
		names.map((n) => byName[n].uuid).sort()

	// @e2e dashboard-metadata-fields::filter-dashboards-by-text-field-exact-match
	test('?metadata.<text>=marketing keeps only the marketing dashboards', async () => {
		// Three departments; marketing is the exact match, "market" would not be.
		expect(await listedUuids(lister!.api, '')).toEqual(
			uuidsOf('marketing1', 'sales3', 'engineering5', 'marketing7', 'sales9'),
		)
		expect(
			await listedUuids(lister!.api, `metadata.${department.key}=marketing`),
		).toEqual(uuidsOf('marketing1', 'marketing7'))
		expect(
			await listedUuids(lister!.api, `metadata.${department.key}=market`),
		).toEqual([])
	})

	// @e2e dashboard-metadata-fields::filter-dashboards-by-numeric-range
	test('?metadata.<number>.min=5&.max=7 is an inclusive range', async () => {
		expect(
			await listedUuids(
				lister!.api,
				`metadata.${priority.key}.min=5&metadata.${priority.key}.max=7`,
			),
		).toEqual(uuidsOf('engineering5', 'marketing7'))
	})

	// @e2e dashboard-metadata-fields::multiple-metadata-filters-and-logic
	test('two filters must both hold', async () => {
		expect(
			await listedUuids(
				lister!.api,
				`metadata.${department.key}=marketing&metadata.${priority.key}.min=5`,
			),
		).toEqual(uuidsOf('marketing7'))
	})

	// @e2e dashboard-metadata-fields::filter-by-select-field
	test('?metadata.<select>=open keeps only the open dashboards', async () => {
		expect(
			await listedUuids(lister!.api, `metadata.${status.key}=open`),
		).toEqual(uuidsOf('marketing1', 'sales9'))
	})
})

test.describe.serial('detail fields in the pages (REQ-MDUI-001..003)', () => {
	// @e2e dashboard-metadata-fields::department-field
	test('an admin adds a select field and it shows on every dashboard Details', async ({
		page,
		browser,
	}) => {
		const label = `Department (admin ${RUN})`
		const key = `department_admin_${RUN}`
		const adminDash = await createDashboard(admin, `MDUI admin ${RUN}`)
		const userDash = await createDashboard(owner!.api, `MDUI owner ${RUN}`)
		await activate(admin, adminDash.uuid)
		await activate(owner!.api, userDash.uuid)

		const section = await openDetailFieldsTab(page)
		await section
			.locator('input[data-testid="metadata-field-label"]')
			.fill(label)
		await section.locator('input[data-testid="metadata-field-key"]').fill(key)
		await pick(
			page,
			section.locator('.metadata-fields__form .v-select'),
			'One choice',
		)
		await section
			.locator('input[data-testid="metadata-field-options"]')
			.fill('HR, Finance')
		await section.locator('[data-testid="metadata-field-add"]').click()

		const row = section
			.locator('[data-testid="metadata-field-row"]')
			.filter({ hasText: label })
		await expect(row).toBeVisible({ timeout: 10_000 })
		await expect(row).toContainText(key)
		await expect(row).toContainText('HR, Finance')
		await expect(
			section.locator('[data-testid="metadata-field-error"]'),
		).toHaveCount(0)

		const stored = (await listFields()).fields.find((f) => f.key === key)
		expect(stored, 'the field must be saved').toBeTruthy()
		createdFieldIds.add(Number(stored!.id))
		expect(stored!.type).toBe('select')
		expect(stored!.options).toEqual(['HR', 'Finance'])

		// Every dashboard: the admin's own, and an ordinary account's.
		const adminDetails = await openDetails(page, adminDash.name)
		await expect(
			adminDetails.locator(`[data-testid="detail-${key}"]`),
		).toContainText(label)

		const userPage = await pageAs(browser, owner!)
		try {
			const userDetails = await openDetails(userPage, userDash.name)
			await expect(
				userDetails.locator(`[data-testid="detail-${key}"]`),
			).toContainText(label)
		} finally {
			await userPage.context().close()
		}
	})

	// @e2e dashboard-metadata-fields::select-without-options
	test('saving a select field with no options shows the service message, saves nothing', async ({
		page,
	}) => {
		const label = `Department (empty ${RUN})`
		const key = `department_empty_${RUN}`

		const section = await openDetailFieldsTab(page)
		await section
			.locator('input[data-testid="metadata-field-label"]')
			.fill(label)
		await section.locator('input[data-testid="metadata-field-key"]').fill(key)
		await pick(
			page,
			section.locator('.metadata-fields__form .v-select'),
			'One choice',
		)
		await expect(
			section.locator('input[data-testid="metadata-field-options"]'),
		).toHaveValue('')
		await section.locator('[data-testid="metadata-field-add"]').click()

		await expect(
			section.locator('[data-testid="metadata-field-error"]'),
		).toHaveText('Select type requires non-empty options array', {
			timeout: 10_000,
		})
		await expect(
			section
				.locator('[data-testid="metadata-field-row"]')
				.filter({ hasText: label }),
		).toHaveCount(0)
		expect((await listFields()).fields.map((f) => f.key)).not.toContain(key)
	})

	// @e2e dashboard-metadata-fields::set-the-owning-department
	test('the owner picks Finance on Details, saves, and reopening shows Finance', async ({
		browser,
	}) => {
		const field = await createField({
			key: `department_owner_${RUN}`,
			label: `Department (owner ${RUN})`,
			type: 'select',
			options: ['HR', 'Finance'],
		})
		const payroll = await createDashboard(owner!.api, `Payroll ${RUN}`)
		await activate(owner!.api, payroll.uuid)

		const page = await pageAs(browser, owner!)
		try {
			let details = await openDetails(page, payroll.name)
			const control = details.locator(
				`[data-testid="detail-${field.key}"] .v-select`,
			)
			await pick(page, control, 'Finance')
			await details.locator('[data-testid="details-save"]').click()
			await expect(
				details.locator('[data-testid="details-saved"]'),
			).toBeVisible({
				timeout: 10_000,
			})
			await expect(
				details.locator('[data-testid="details-error"]'),
			).toHaveCount(0)
			expect((await readMetadata(owner!.api, payroll.uuid))[field.key]).toBe(
				'Finance',
			)

			// A fresh page, so the dialog reads the value back from the server.
			await page.reload()
			details = await openDetails(page, payroll.name)
			await expect(
				details.locator(`[data-testid="detail-${field.key}"] .vs__selected`),
			).toHaveText('Finance', { timeout: 10_000 })
		} finally {
			await page.context().close()
		}
	})

	// @e2e dashboard-metadata-fields::filter-to-finance
	test('filtering More\'s full list by Department Finance lists only Payroll', async ({
		browser,
	}) => {
		const field = await createField({
			key: `department_filter_${RUN}`,
			label: `Department (filter ${RUN})`,
			type: 'select',
			options: ['HR', 'Finance'],
		})
		const pieter = await createPrincipal(admin, 'mdui-pieter')
		try {
			const payroll = await createDashboard(pieter.api, `Payroll F ${RUN}`)
			const onboarding = await createDashboard(
				pieter.api,
				`Onboarding F ${RUN}`,
			)
			await writeMetadata(pieter.api, payroll.uuid, { [field.key]: 'Finance' })
			await writeMetadata(pieter.api, onboarding.uuid, { [field.key]: 'HR' })
			await activate(pieter.api, payroll.uuid)

			const page = await pageAs(browser, pieter)
			try {
				await page.goto('/index.php/apps/launchpad')
				await openDashboardSwitcher(page)
				// More's full list (REQ-SHELL-019) carries Filter by detail.
				const rowFor = (name: string) =>
					page.locator(ROW).filter({ has: page.locator('.lp-more-menu__label', { hasText: new RegExp(`^${name}$`) }) })
				await expect(rowFor(payroll.name)).toHaveCount(1)
				await expect(rowFor(onboarding.name)).toHaveCount(1)

				const filter = page.locator('[data-testid="detail-filter"]')
				await expect(filter).toBeVisible({ timeout: 10_000 })
				await pick(page, filter.locator('.v-select').first(), field.label)
				await pick(page, filter.locator('.v-select').nth(1), 'Finance')

				await expect(rowFor(onboarding.name)).toHaveCount(0, {
					timeout: 10_000,
				})
				await expect(rowFor(payroll.name)).toHaveCount(1)
				await expect(page.locator(ROW)).toHaveCount(1)
			} finally {
				await page.context().close()
			}
		} finally {
			for (const d of createdDashboards.filter(
				(x) => x.owner === pieter.api,
			)) {
				await pieter.api.delete(`${API}/dashboard/${d.id}`).catch(() => null)
				createdDashboards.splice(createdDashboards.indexOf(d), 1)
			}
			await removePrincipal(admin, pieter)
		}
	})
})
