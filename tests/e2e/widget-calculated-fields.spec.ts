/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for widget calculated fields
 * (widget-calculated-fields tasks 44, 45 and 46).
 *
 * Each run creates its own OpenRegister register and schema (as the
 * administrator, the only role OpenRegister lets create schemas) and removes
 * them afterwards, so no shared schema of the instance is touched.
 *
 * - Render (task 44): a throwaway account's dashboard holds a stats block
 *   over that schema with two calculated fields, one of which divides by
 *   zero, beside a text widget without fields. The working field shows its
 *   value, the failing one an error icon whose hover text names the formula,
 *   and both other widgets render.
 * - Cross-widget (task 45): a text widget's field reads the stats block's
 *   count (`widget:<id>.count + 1`). The stats block consumes the
 *   `gemeenteCode` filter; removing that filter in the active-filters panel
 *   makes the block refetch, and the dependent field follows without a reload.
 * - Server mode (task 46): the administrator places an object-list on the
 *   schema with a `server` field and a `client` field. The server field is a
 *   read-only computed property of the schema and its value is on every
 *   object saved afterwards, read through the OpenRegister objects API; the
 *   client field is on neither. Removing the widget removes the property.
 *
 * Gate-19 @e2e traceability:
 *   @e2e calculated-fields::division-by-zero-in-client-side-evaluation
 *   @e2e calculated-fields::error-does-not-block-widget-render
 *   @e2e calculated-fields::recompute-on-filter-change
 *   @e2e calculated-fields::cross-widget-dependency-tracking
 *   @e2e calculated-fields::create-server-mode-computed-field-entry
 *   @e2e calculated-fields::delete-computed-field-entry-when-widget-is-deleted
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Account, SeededUser } from './support/filterBus.ts'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import {
	API,
	apiAs,
	chip,
	openFiltered,
	provisionUserWithDashboard,
	removeUser,
} from './support/filterBus.ts'
import { NL_ADMINISTRATIVE, storeHierarchies } from './support/hierarchies.ts'
import { gridItem } from './support/widgetPlacements.ts'

const OR_API = '/index.php/apps/openregister/api'
const SLUG = `e2e-calc-${Date.now()}`
const OBJECTS = `${OR_API}/objects/${SLUG}/${SLUG}`

type Ast = Record<string, unknown>

/** A reference to a field of the widget's own data, or of another widget's. */
const ref = (path: string, source = 'self'): Ast => ({ type: 'fieldRef', source, path })

/** A number literal. */
const num = (value: number): Ast => ({ type: 'literal', valueType: 'number', value })

/** A binary operation. */
const bin = (op: string, left: Ast, right: Ast): Ast => ({ type: 'binary', op, left, right })

/** A calculated field as the placement update takes it. */
function field(name: string, label: string, expression: string, compiledExpression: Ast, evaluationMode = 'client') {
	return { name, label, expression, compiledExpression, evaluationMode }
}

let schemaId = 0
let registerId = 0
let user: SeededUser | null = null
let statsId = 0
let dependentId = 0

/**
 * Ask an API and fail unless it answers below 300.
 *
 * @param res the pending response.
 * @return the parsed body.
 */
async function ok(res: ReturnType<APIRequestContext['get']>): Promise<any> {
	const answer = await res
	expect(answer.status(), await answer.text()).toBeLessThan(300)
	const text = await answer.text()
	return text === '' ? null : JSON.parse(text)
}

/**
 * Place a widget on a dashboard and give it calculated fields.
 *
 * @param account the dashboard owner, or the administrator when null.
 * @param dashboardId the dashboard.
 * @param placement the widget type, grid position and content.
 * @param fields the calculated fields.
 * @return the placement id and the update's answer.
 */
async function placeWithFields(
	account: Account | null,
	dashboardId: number,
	placement: Record<string, unknown>,
	fields: object[],
): Promise<{ id: number; updated: any }> {
	const api = await apiAs(account ?? undefined)
	try {
		const created = await ok(api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { gridWidth: 4, gridHeight: 3, ...placement },
		}))
		const id = Number(created.id ?? created.data?.id ?? created.placement?.id)
		const updated = fields.length > 0
			? await ok(api.put(`${API}/widgets/${id}`, { data: { calculatedFields: fields } }))
			: null
		return { id, updated }
	} finally {
		await api.dispose()
	}
}

/**
 * The calculated-values row of one field under one placed widget.
 *
 * @param page the page.
 * @param placementId the placement.
 * @param name the field name.
 * @return the row locator.
 */
function calculatedRow(page: Page, placementId: number, name: string) {
	return gridItem(page, placementId)
		.locator(`[data-testid="calculated-values"] [data-field="${name}"]`)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const schema = await ok(admin.post(`${OR_API}/schemas`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: {
				title: SLUG,
				slug: SLUG,
				properties: { amount: { type: 'number' }, gemeente: { type: 'string' } },
			},
		}))
		schemaId = Number(schema.id)
		const register = await ok(admin.post(`${OR_API}/registers`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { title: SLUG, slug: SLUG, schemas: [schemaId] },
		}))
		registerId = Number(register.id)
	} finally {
		await admin.dispose()
	}

	user = await provisionUserWithDashboard('e2e-calc')
	const api = await apiAs(user)
	try {
		await clearSeededWidgets(api, user.dashboardId)
		await storeHierarchies(api, user.dashboardId, [NL_ADMINISTRATIVE])
		for (const gemeente of ['GM0355', 'GM0355', 'GM0344']) {
			await ok(api.post(OBJECTS, { headers: { 'OCS-APIRequest': 'true' }, data: { amount: 1, gemeente } }))
		}
	} finally {
		await api.dispose()
	}

	statsId = (await placeWithFields(user, user.dashboardId, {
		widgetId: 'stats-block',
		gridX: 0,
		gridY: 0,
		content: {
			title: 'Calculated source',
			dataSource: { register: SLUG, schema: SLUG, aggregate: 'count' },
			props: { countLabel: 'objects', showZeroCount: true },
			consumes: [{ dimension: 'gemeenteCode', field: 'gemeente' }],
		},
	}, [
		field('tenfold', 'Tenfold', 'count * 10', bin('*', ref('count'), num(10))),
		field('ratio', 'Ratio', 'count / 0', bin('/', ref('count'), num(0))),
	])).id

	dependentId = (await placeWithFields(user, user.dashboardId, {
		widgetId: 'text',
		gridX: 4,
		gridY: 0,
		content: { text: 'Dependent widget', contentMode: 'markdown' },
	}, [
		field('next', 'Next', `widget:${statsId}.count + 1`, bin('+', ref('count', `widget:${statsId}`), num(1))),
	])).id

	await placeWithFields(user, user.dashboardId, {
		widgetId: 'text',
		gridX: 8,
		gridY: 0,
		content: { text: 'Plain neighbour', contentMode: 'markdown' },
	}, [])
})

test.afterAll(async () => {
	await removeUser(user)
	const admin = await apiAs()
	try {
		const listed = await admin.get(`${OBJECTS}?_limit=100`, { headers: { 'OCS-APIRequest': 'true' } })
		const results = listed.ok() ? ((await listed.json()).results ?? []) : []
		for (const object of results) {
			await admin.delete(`${OBJECTS}/${object.id}`, { headers: { 'OCS-APIRequest': 'true' } })
		}
		if (registerId > 0) {
			await admin.delete(`${OR_API}/registers/${registerId}`, { headers: { 'OCS-APIRequest': 'true' } })
		}
		if (schemaId > 0) {
			await admin.delete(`${OR_API}/schemas/${schemaId}`, { headers: { 'OCS-APIRequest': 'true' } })
		}
	} finally {
		await admin.dispose()
	}
})

test.describe('widget calculated fields (REQ-CALC-003, REQ-CALC-004, REQ-CALC-008)', () => {
	// @e2e calculated-fields::division-by-zero-in-client-side-evaluation
	// @e2e calculated-fields::error-does-not-block-widget-render
	test('a widget shows its calculated values, an inline error for a failing field, and the other widgets still render', async ({ browser }) => {
		const { context, page } = await openFiltered(browser, user!, [])
		try {
			await expect(page.getByText('Calculated source')).toBeVisible({ timeout: 30_000 })
			await expect(calculatedRow(page, statsId, 'tenfold').locator('[data-testid="calculated-value"]')).toHaveText('30', { timeout: 30_000 })

			const error = calculatedRow(page, statsId, 'ratio').locator('[data-testid="calculated-value-error"]')
			await expect(error).toBeVisible()
			await expect(error).toHaveAttribute('title', /count \/ 0/)
			await expect(calculatedRow(page, statsId, 'ratio').locator('[data-testid="calculated-value"]')).toHaveCount(0)

			await expect(page.getByText('Plain neighbour')).toBeVisible()
			await expect(page.getByText('Dependent widget')).toBeVisible()
			await expect(calculatedRow(page, dependentId, 'next').locator('[data-testid="calculated-value"]')).toHaveText('4')
		} finally {
			await context.close()
		}
	})

	// @e2e calculated-fields::recompute-on-filter-change
	// @e2e calculated-fields::cross-widget-dependency-tracking
	test('removing the source widget\'s filter re-evaluates the dependent widget\'s field in the open page', async ({ browser }) => {
		const { context, page } = await openFiltered(browser, user!, [
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
		])
		try {
			const next = calculatedRow(page, dependentId, 'next').locator('[data-testid="calculated-value"]')
			const tenfold = calculatedRow(page, statsId, 'tenfold').locator('[data-testid="calculated-value"]')
			await expect(tenfold).toHaveText('20', { timeout: 30_000 })
			await expect(next).toHaveText('3')

			let reloaded = false
			page.on('framenavigated', (frame) => {
				if (frame === page.mainFrame()) {
					reloaded = true
				}
			})
			await chip(page, 'gemeenteCode').locator('[data-testid="active-filter-remove"]').click()

			await expect(tenfold).toHaveText('30', { timeout: 15_000 })
			await expect(next).toHaveText('4')
			expect(reloaded, 'the page reloaded instead of re-evaluating in place').toBe(false)
		} finally {
			await context.close()
		}
	})

	// @e2e calculated-fields::create-server-mode-computed-field-entry
	// @e2e calculated-fields::delete-computed-field-entry-when-widget-is-deleted
	test('a server-mode field is materialised and readable through the OpenRegister API; a client-mode field is not', async () => {
		const admin = await apiAs()
		const headers = { 'OCS-APIRequest': 'true' }
		let dashboardId = 0
		try {
			const created = await ok(admin.post(`${API}/dashboard`, { data: { name: `${SLUG} server mode` } }))
			dashboardId = Number((created.dashboard ?? created.data?.dashboard ?? created).id)

			const { id, updated } = await placeWithFields(null, dashboardId, {
				widgetId: 'object-list',
				gridX: 0,
				gridY: 0,
				content: { register: SLUG, schema: SLUG, columns: [{ key: 'amount', label: 'Amount' }] },
			}, [
				field('doubled', 'Doubled', 'amount * 2', bin('*', ref('amount'), num(2)), 'server'),
				field('tripled', 'Tripled', 'amount * 3', bin('*', ref('amount'), num(3)), 'client'),
			])
			const serverKey = `launchpad_${id}__doubled`
			const clientKey = `launchpad_${id}__tripled`
			const fields = (updated.calculatedFields ?? updated.data?.calculatedFields) as Array<Record<string, unknown>>
			expect(fields.find((f) => f.name === 'doubled')?.serverComputedFieldId).toBe(serverKey)
			expect(fields.find((f) => f.name === 'tripled')?.serverComputedFieldId ?? null).toBeNull()
			expect(updated.warnings ?? []).toEqual([])

			const schema = await ok(admin.get(`${OR_API}/schemas/${schemaId}`, { headers }))
			expect(schema.properties[serverKey]).toMatchObject({ readOnly: true, calculation: { type: 'number' } })
			expect(schema.properties[clientKey]).toBeUndefined()

			const saved = await ok(admin.post(OBJECTS, { headers, data: { amount: 21, gemeente: 'GM0363' } }))
			const read = await ok(admin.get(`${OBJECTS}/${saved.id}`, { headers }))
			expect(read[serverKey]).toBe(42)
			expect(read).not.toHaveProperty(clientKey)

			const list = await ok(admin.get(`${OBJECTS}?_limit=100`, { headers }))
			const listed = (list.results as Array<Record<string, unknown>>).find((object) => object.id === saved.id)
			expect(listed?.[serverKey]).toBe(42)
			expect(listed).not.toHaveProperty(clientKey)

			await ok(admin.delete(`${API}/widgets/${id}`))
			const after = await ok(admin.get(`${OR_API}/schemas/${schemaId}`, { headers }))
			expect(after.properties[serverKey]).toBeUndefined()
		} finally {
			if (dashboardId > 0) {
				await admin.delete(`${API}/dashboard/${dashboardId}`)
			}
			await admin.dispose()
		}
	})
})
