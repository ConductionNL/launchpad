/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The per-dashboard footer override scenarios that write a footer mode over
 * `PUT /api/dashboard/{id}` (openspec/specs/footer-customization,
 * REQ-FTR-006). The read side and the rest of the API contract live in
 * footer-customization-api.spec.ts.
 *
 * DashboardApiController::update() passes `dashboardFooterMode` and
 * `dashboardFooterHtml` on to DashboardService::applyFooterUpdates(), which
 * holds the 400 and the clear-on-inherit rule; the fields count as the
 * owner's metadata, so a view-only recipient is refused. The scenarios say
 * `{uuid}`; the real route is `PUT /api/dashboard/{id}` with the numeric id.
 *
 * The tests are not serial, so a red one cannot skip the next. `beforeAll`
 * snapshots the five footer keys and the personal-dashboard flags;
 * `afterAll` writes them back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e footer-customization::custom-mode-requires-html
 *   @e2e footer-customization::mode-change-clears-stale-html
 *   @e2e footer-customization::only-dashboard-owner-can-set-custom-footer
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { FooterSettings } from './support/footerSettings.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	readFooter,
	resetFooter,
	restoreFooter,
} from './support/footerSettings.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = `${API}/admin/settings`

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let footerSnapshot: FooterSettings | undefined
let flagSnapshot: { allowUserDash: boolean; allowMultiDash: boolean } | undefined
const dashboards: { api: APIRequestContext; id: number }[] = []

/**
 * Create a personal dashboard as `api`'s account and remember it for cleanup.
 *
 * @param api the owner's context; afterAll deletes the dashboard through it.
 * @param name the dashboard name.
 * @return its id.
 */
async function createDashboard(
	api: APIRequestContext,
	name: string,
): Promise<number> {
	const res = await api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const id = Number((await res.json()).dashboard.id)
	dashboards.push({ api, id })
	return id
}

/**
 * Read a dashboard's wire format as `api`'s account.
 *
 * @param api the reader's context.
 * @param id the dashboard id.
 * @return the `dashboard` object of `GET /api/dashboard/{id}`.
 */
async function readDashboard(
	api: APIRequestContext,
	id: number,
): Promise<Record<string, unknown>> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).dashboard
}

test.beforeAll(async () => {
	admin = await adminContext()
	footerSnapshot = await readFooter(admin)

	const settings = await admin.get(SETTINGS)
	expect(settings.status(), await settings.text()).toBe(200)
	const body = await settings.json()
	flagSnapshot = {
		allowUserDash: Boolean(body.allowUserDashboards),
		allowMultiDash: body.allowMultipleDashboards !== false,
	}
	const enable = await admin.put(SETTINGS, {
		data: { allowUserDash: true, allowMultiDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	alice = await createPrincipal(admin, 'e2e-footer-alice')
	bob = await createPrincipal(admin, 'e2e-footer-bob')
	await assertActingAs(alice.api, alice.username)
	await assertActingAs(bob.api, bob.username)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	for (const { api, id } of dashboards) {
		await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	await restoreFooter(admin, footerSnapshot)
	if (flagSnapshot !== undefined) {
		await admin.put(SETTINGS, { data: flagSnapshot })
	}
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	await admin.dispose()
})

test.describe('per-dashboard footer override writes (REQ-FTR-006)', () => {
	// @e2e footer-customization::custom-mode-requires-html
	test('custom mode without footer HTML is refused with 400', async () => {
		const id = await createDashboard(alice!.api, `E2E Footer NoHtml ${RUN}`)

		const refused = await alice!.api.put(`${API}/dashboard/${id}`, {
			data: { dashboardFooterMode: 'custom' },
		})
		expect(refused.status()).toBe(400)
		const body = await refused.json()
		expect(String(body.message ?? body.error)).toMatch(/custom/i)
		expect(String(body.message ?? body.error)).toMatch(/html/i)
		const dashboard = await readDashboard(alice!.api, id)
		expect(dashboard.dashboardFooterMode).toBe('inherit')

		// With HTML the same request is accepted, so the 400 is about the HTML.
		const allowed = await alice!.api.put(`${API}/dashboard/${id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml: '<p>Mine</p>',
			},
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect((await readDashboard(alice!.api, id)).dashboardFooterMode).toBe(
			'custom',
		)
	})

	// @e2e footer-customization::mode-change-clears-stale-html
	test('switching custom to inherit clears the stored footer HTML', async () => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Global footer</p>',
		})
		const id = await createDashboard(alice!.api, `E2E Footer Clear ${RUN}`)

		const custom = await alice!.api.put(`${API}/dashboard/${id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml: '<p>Old footer</p>',
			},
		})
		expect(custom.status(), await custom.text()).toBe(200)
		const seeded = await readDashboard(alice!.api, id)
		expect(seeded.dashboardFooterMode).toBe('custom')
		expect(seeded.dashboardFooterHtml).toBe('<p>Old footer</p>')

		const inherit = await alice!.api.put(`${API}/dashboard/${id}`, {
			data: { dashboardFooterMode: 'inherit' },
		})
		expect(inherit.status(), await inherit.text()).toBe(200)
		const after = await readDashboard(alice!.api, id)
		expect(after.dashboardFooterMode).toBe('inherit')
		expect(after.dashboardFooterHtml).toBeNull()
		// "Subsequent renders use the global footer": when the payload carries
		// the resolved footer it is the global one.
		if (Object.hasOwn(after, 'effectiveFooter')) {
			expect((after.effectiveFooter as { html?: unknown }).html).toBe(
				'<p>Global footer</p>',
			)
		}
	})

	// @e2e footer-customization::only-dashboard-owner-can-set-custom-footer
	test('a view-only recipient is refused with 403; the owner may set custom', async () => {
		const id = await createDashboard(alice!.api, `E2E Footer Owner ${RUN}`)
		await publishForRecipients(alice!.api, id)
		await shareWith(alice!.api, id, bob!.username, 'view_only')
		await expect
			.poll(async () =>
				(await bob!.api.get(`${API}/dashboard/${id}`)).status(),
			)
			.toBe(200)

		const refused = await bob!.api.put(`${API}/dashboard/${id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml: '<p>Bob was here</p>',
			},
		})
		expect(refused.status()).toBe(403)
		const untouched = await readDashboard(alice!.api, id)
		expect(untouched.dashboardFooterMode).toBe('inherit')
		expect(untouched.dashboardFooterHtml).toBeNull()

		const allowed = await alice!.api.put(`${API}/dashboard/${id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml: '<p>Alice footer</p>',
			},
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
		const owned = await readDashboard(alice!.api, id)
		expect(owned.dashboardFooterMode).toBe('custom')
		expect(owned.dashboardFooterHtml).toBe('<p>Alice footer</p>')
	})
})
