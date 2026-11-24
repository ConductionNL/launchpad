/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the footer settings API and the
 * per-dashboard footer override API (openspec/specs/footer-customization),
 * written for the e2e-coverage-programme.
 *
 * These scenarios ARE an HTTP contract — status codes, the stored
 * (sanitised) value, validation messages, the dashboard wire format — so they
 * are driven through `GET|PUT /api/admin/footer-settings` and
 * `GET|PUT /api/dashboard/{id}` (appinfo/routes.php). Every write is re-read.
 * Refusals are probed with a logged-in throwaway account with no admin
 * rights, re-read as the administrator, and the administrator is shown to
 * succeed in the same test. Rendering lives in
 * footer-customization-render.spec.ts.
 *
 * The three per-dashboard override scenarios that write a footer mode
 * (custom-mode-requires-html, mode-change-clears-stale-html,
 * only-dashboard-owner-can-set-custom-footer) run, expected red, in
 * footer-customization-override-defects.spec.ts.
 *
 * The footer is instance-wide. `beforeAll` snapshots all five footer keys and
 * the personal-dashboard flags; `afterAll` writes them back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e footer-customization::non-admin-cannot-toggle-footer
 *   @e2e footer-customization::admin-provides-valid-html
 *   @e2e footer-customization::html-injection-attempt-blocked
 *   @e2e footer-customization::script-tag-stripped
 *   @e2e footer-customization::external-links-get-rel-attribute
 *   @e2e footer-customization::data-uris-stripped-from-img
 *   @e2e footer-customization::oversized-html-rejected
 *   @e2e footer-customization::allowed-tags-list-enforcement
 *   @e2e footer-customization::admin-sets-structured-footer-config
 *   @e2e footer-customization::structured-config-schema-validation
 *   @e2e footer-customization::partial-structured-config-optional-fields
 *   @e2e footer-customization::links-array-in-structured-config
 *   @e2e footer-customization::sanitiser-strips-forbidden-attributes
 *   @e2e footer-customization::style-attributes-not-allowed
 *   @e2e footer-customization::images-with-src-only
 *   @e2e footer-customization::nested-tags
 *   @e2e footer-customization::dashboard-with-inherit-mode-default
 *   @e2e footer-customization::invalid-color-values-rejected
 *   @e2e footer-customization::get-footer-settings
 *   @e2e footer-customization::update-footer-settings
 *   @e2e footer-customization::non-admin-rejects-get
 *   @e2e footer-customization::non-admin-rejects-put
 *   @e2e footer-customization::dashboard-api-returns-effective-footer
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { FooterSettings } from './support/footerSettings.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	FOOTER_KEYS,
	FOOTER_SETTINGS,
	readFooter,
	resetFooter,
	restoreFooter,
	writeFooter,
} from './support/footerSettings.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
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
 * Store `html` as the global footer HTML and return what GET then answers.
 *
 * @param html the raw HTML the administrator sends.
 * @return the stored footerHtml.
 */
async function storeHtml(html: string): Promise<unknown> {
	await resetFooter(admin, { footerEnabled: true })
	await writeFooter(admin, { footerHtml: html })
	return (await readFooter(admin)).footerHtml
}

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

test.describe.serial('footer settings API (REQ-FTR-001, REQ-FTR-010)', () => {
	// @e2e footer-customization::get-footer-settings
	test('GET answers 200 with all five footer keys', async () => {
		const seeded: FooterSettings = {
			footerEnabled: true,
			footerHtml: `<p>E2E get ${RUN}</p>`,
			footerConfig: { organisation: `E2E Org ${RUN}`, layoutMode: 'inline' },
			footerBackgroundColor: '#123456',
			footerTextColor: '#abcdef',
		}
		await writeFooter(admin, seeded)

		const res = await admin.get(FOOTER_SETTINGS)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body).sort()).toEqual([...FOOTER_KEYS].sort())
		expect(body).toEqual(seeded)
	})

	// @e2e footer-customization::update-footer-settings
	test('PUT updates the two keys sent, answers {"status":"ok"} and leaves the rest alone', async () => {
		await resetFooter(admin, {
			footerEnabled: false,
			footerHtml: '<p>Old content</p>',
			footerConfig: { organisation: `E2E Keep ${RUN}`, layoutMode: 'columns' },
			footerBackgroundColor: '#1a1a1a',
			footerTextColor: '#ffffff',
		})

		const res = await admin.put(FOOTER_SETTINGS, {
			data: { footerEnabled: true, footerHtml: '<p>New content</p>' },
		})
		expect(res.status()).toBe(200)
		expect(await res.json()).toEqual({ status: 'ok' })

		expect(await readFooter(admin)).toEqual({
			footerEnabled: true,
			footerHtml: '<p>New content</p>',
			footerConfig: { organisation: `E2E Keep ${RUN}`, layoutMode: 'columns' },
			footerBackgroundColor: '#1a1a1a',
			footerTextColor: '#ffffff',
		})
	})

	// @e2e footer-customization::non-admin-rejects-get
	test('a non-admin GET is refused with 403 and discloses no setting', async () => {
		const secret = `E2E draft copy ${RUN}`
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: `<p>${secret}</p>`,
			footerBackgroundColor: '#222222',
		})

		const refused = await alice!.api.get(FOOTER_SETTINGS)
		expect(refused.status()).toBe(403)
		const text = await refused.text()
		expect(text).not.toContain(secret)
		expect(text).not.toContain('#222222')
		for (const key of FOOTER_KEYS) {
			expect(text).not.toContain(key)
		}

		// The administrator reads the same endpoint, so the 403 is a refusal.
		expect((await readFooter(admin)).footerHtml).toBe(`<p>${secret}</p>`)
	})

	// @e2e footer-customization::non-admin-rejects-put
	test('a non-admin PUT is refused with 403 and writes nothing', async () => {
		const baseline: FooterSettings = {
			footerEnabled: true,
			footerHtml: `<p>E2E baseline ${RUN}</p>`,
			footerConfig: { organisation: 'Baseline', layoutMode: 'columns' },
			footerBackgroundColor: '#101010',
			footerTextColor: '#efefef',
		}
		await writeFooter(admin, baseline)

		const refused = await alice!.api.put(FOOTER_SETTINGS, {
			data: {
				footerEnabled: false,
				footerHtml: '<p>hijacked</p>',
				footerConfig: { organisation: 'Hijack', layoutMode: 'inline' },
				footerBackgroundColor: '#000000',
				footerTextColor: '#000000',
			},
		})
		expect(refused.status()).toBe(403)
		expect(await readFooter(admin)).toEqual(baseline)

		// The same body from the administrator lands.
		await writeFooter(admin, { footerHtml: '<p>admin may</p>' })
		expect((await readFooter(admin)).footerHtml).toBe('<p>admin may</p>')
	})

	// @e2e footer-customization::non-admin-cannot-toggle-footer
	test('a non-admin cannot switch the footer off; the administrator can', async () => {
		await resetFooter(admin, { footerEnabled: true, footerHtml: '<p>on</p>' })
		const before = await readFooter(admin)
		expect(before.footerEnabled).toBe(true)

		const refused = await alice!.api.put(FOOTER_SETTINGS, {
			data: { footerEnabled: false },
		})
		expect(refused.status()).toBe(403)
		expect(await readFooter(admin)).toEqual(before)

		const allowed = await admin.put(FOOTER_SETTINGS, {
			data: { footerEnabled: false },
		})
		expect(allowed.status()).toBe(200)
		expect((await readFooter(admin)).footerEnabled).toBe(false)
	})
})

test.describe.serial('footer HTML sanitisation (REQ-FTR-002, REQ-FTR-005)', () => {
	// @e2e footer-customization::admin-provides-valid-html
	test('the allowed tags pass through and every other tag is stripped', async () => {
		expect(await storeHtml('<p>Copyright 2026</p>')).toBe(
			'<p>Copyright 2026</p>',
		)

		const stored = await storeHtml(
			'<p>A <strong>b</strong> <em>c</em><br><span>d</span></p>'
				+ '<ul><li>e</li></ul><ol><li>f</li></ol>'
				+ '<a href="/apps/files">g</a><img src="https://example.com/i.png">'
				+ '<section><h1>h</h1></section>',
		)
		expect(stored).toBe(
			'<p>A <strong>b</strong> <em>c</em><br />d</p>'
				+ '<ul><li>e</li></ul><ol><li>f</li></ol>'
				+ '<a href="/apps/files">g</a><img src="https://example.com/i.png" />'
				+ 'h',
		)
		// The render clause is in footer-customization-render.spec.ts.
	})

	// @e2e footer-customization::html-injection-attempt-blocked
	test('an onclick attribute is stripped', async () => {
		expect(await storeHtml("<p onclick='alert()'>Click me</p>")).toBe(
			'<p>Click me</p>',
		)
	})

	// @e2e footer-customization::script-tag-stripped
	test('a script element is removed with its content', async () => {
		expect(
			await storeHtml("<p>Text <script>alert('xss')</script> here</p>"),
		).toBe('<p>Text  here</p>')
	})

	// @e2e footer-customization::external-links-get-rel-attribute
	test('an external link gains rel="noopener noreferrer" and target="_blank"', async () => {
		expect(await storeHtml("<a href='https://example.com'>Link</a>")).toBe(
			'<a href="https://example.com" rel="noopener noreferrer" target="_blank">Link</a>',
		)
		// The rendered-HTML clause is in footer-customization-render.spec.ts.
	})

	// @e2e footer-customization::data-uris-stripped-from-img
	test('a data: URI is stripped from img src', async () => {
		const stored = String(
			await storeHtml("<img src='data:image/png;base64,iVBORw0KGgo=' />"),
		)
		expect(stored).not.toContain('data:')
		expect(stored).not.toContain('src')
		expect(['', '<img />']).toContain(stored)
	})

	// @e2e footer-customization::oversized-html-rejected
	test('a 9000-byte footer is refused with 413 and the stored value is kept', async () => {
		await resetFooter(admin, { footerHtml: '<p>keep me</p>' })

		const oversized = `<p>${'a'.repeat(9000 - 7)}</p>`
		expect(Buffer.byteLength(oversized)).toBe(9000)
		const refused = await admin.put(FOOTER_SETTINGS, {
			data: { footerHtml: oversized },
		})
		expect(refused.status()).toBe(413)
		expect((await readFooter(admin)).footerHtml).toBe('<p>keep me</p>')

		// Just under the cap is accepted, so the 413 is about size.
		const fits = `<p>${'b'.repeat(8000)}</p>`
		await writeFooter(admin, { footerHtml: fits })
		expect((await readFooter(admin)).footerHtml).toBe(fits)
	})

	// @e2e footer-customization::allowed-tags-list-enforcement
	test('a div is stripped and only the allowed paragraph markup remains', async () => {
		const stored = String(
			await storeHtml('<div>Not allowed</div><p>Allowed</p>'),
		)
		expect(stored).not.toMatch(/<\/?div/i)
		expect(stored).toContain('<p>Allowed</p>')
		expect(stored.match(/<[^>]+>/g)).toEqual(['<p>', '</p>'])
	})

	// @e2e footer-customization::sanitiser-strips-forbidden-attributes
	test('class and data-* attributes are stripped', async () => {
		expect(await storeHtml("<p class='danger' data-value='test'>Text</p>")).toBe(
			'<p>Text</p>',
		)
	})

	// @e2e footer-customization::style-attributes-not-allowed
	test('a style attribute is stripped', async () => {
		expect(await storeHtml("<p style='color: red;'>Text</p>")).toBe(
			'<p>Text</p>',
		)
	})

	// @e2e footer-customization::images-with-src-only
	test('img keeps src and loses alt', async () => {
		const stored = String(
			await storeHtml("<img src='https://example.com/logo.png' alt='logo' />"),
		)
		expect(stored).toContain('https://example.com/logo.png')
		expect(stored).not.toContain('alt')
		// The scenario writes the result with single quotes; the quote style
		// is not part of the contract, the attribute set is.
		expect(stored.replace(/"/g, "'")).toBe(
			"<img src='https://example.com/logo.png' />",
		)
	})

	// @e2e footer-customization::nested-tags
	test('nested allowed tags are preserved with their nesting', async () => {
		expect(await storeHtml('<strong><em>Bold and italic</em></strong>')).toBe(
			'<strong><em>Bold and italic</em></strong>',
		)
	})
})

test.describe.serial('structured footer config (REQ-FTR-003)', () => {
	const FULL = {
		logoUrl: 'https://example.com/logo.png',
		organisation: 'ACME Corp',
		address: 'Main Street 123\n1234 AB Amsterdam',
		links: [{ label: 'Privacy', url: 'https://example.com/privacy' }],
		legal: 'All rights reserved',
		copyrightYear: 2026,
		layoutMode: 'columns',
	}

	// @e2e footer-customization::admin-sets-structured-footer-config
	test('a full config is validated and stored in footer_config', async () => {
		await resetFooter(admin)
		const res = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: FULL },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await readFooter(admin)).footerConfig).toEqual(FULL)
	})

	// @e2e footer-customization::structured-config-schema-validation
	test('an unknown key is refused with 400 and nothing is stored', async () => {
		await resetFooter(admin, { footerConfig: FULL })

		const refused = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: { ...FULL, unknownField: 'value' } },
		})
		expect(refused.status()).toBe(400)
		const body = await refused.json()
		expect(String(body.error)).toContain('unknownField')
		expect((await readFooter(admin)).footerConfig).toEqual(FULL)
	})

	// @e2e footer-customization::structured-config-schema-validation
	test.fixme('the 400 names the keys that are allowed', async () => {
		/*
		 * SUSPECTED BUG. lib/Service/FooterService.php assertKnownConfigKeys()
		 * throws 'footerConfig contains unknown key: <key>' — it names the
		 * offending key but not the allowed ones (STRUCTURED_CONFIG_KEYS),
		 * which the scenario requires.
		 */
		const refused = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: { organisation: 'ACME', unknownField: 'value' } },
		})
		expect(refused.status()).toBe(400)
		const message = String((await refused.json()).error)
		for (const key of Object.keys(FULL)) {
			expect(message).toContain(key)
		}
	})

	// @e2e footer-customization::partial-structured-config-optional-fields
	test('a partial config with only organisation and layoutMode is accepted', async () => {
		await resetFooter(admin)
		const partial = { organisation: 'ACME', layoutMode: 'inline' }
		const res = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: partial },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await readFooter(admin)).footerConfig).toEqual(partial)
		// The render clause is in footer-customization-render.spec.ts.
	})

	// @e2e footer-customization::links-array-in-structured-config
	test('a links array is stored as-is', async () => {
		await resetFooter(admin)
		const links = [
			{ label: 'Privacy', url: 'https://example.com/privacy' },
			{ label: 'Contact', url: 'https://example.com/contact' },
		]
		const res = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: { links } },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await readFooter(admin)).footerConfig).toEqual({ links })
		// The render clause is in footer-customization-render.spec.ts.
	})
})

test.describe.serial('footer colours (REQ-FTR-009)', () => {
	// @e2e footer-customization::invalid-color-values-rejected
	test('a colour that is not a hex string is refused with 400', async () => {
		await resetFooter(admin, { footerBackgroundColor: '#1a1a1a' })

		const refused = await admin.put(FOOTER_SETTINGS, {
			data: { footerBackgroundColor: 'not-a-color' },
		})
		expect(refused.status()).toBe(400)
		expect(String((await refused.json()).error)).toMatch(/hex/i)
		expect((await readFooter(admin)).footerBackgroundColor).toBe('#1a1a1a')

		await writeFooter(admin, { footerBackgroundColor: '#abc' })
		expect((await readFooter(admin)).footerBackgroundColor).toBe('#abc')
	})
})

test.describe
	.serial('per-dashboard footer override API (REQ-FTR-006, REQ-FTR-010)', () => {
	// @e2e footer-customization::dashboard-api-returns-effective-footer
	test('GET /api/dashboard/{id} carries dashboardFooterMode and dashboardFooterHtml', async () => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Global</p>',
		})
		const id = await createDashboard(alice!.api, `E2E Footer Api ${RUN}`)

		const dashboard = await readDashboard(alice!.api, id)
		expect(['inherit', 'hidden', 'custom']).toContain(
			dashboard.dashboardFooterMode,
		)
		expect(Object.hasOwn(dashboard, 'dashboardFooterHtml')).toBe(true)
		expect(
			dashboard.dashboardFooterHtml === null
				|| typeof dashboard.dashboardFooterHtml === 'string',
		).toBe(true)
		// `effectiveFooter` is optional (MAY); when present it is an object or null.
		if (Object.hasOwn(dashboard, 'effectiveFooter')) {
			expect(typeof dashboard.effectiveFooter).toBe('object')
		}
	})

	// @e2e footer-customization::dashboard-with-inherit-mode-default
	test('a new dashboard is in inherit mode with no footer HTML of its own', async () => {
		const id = await createDashboard(alice!.api, `E2E Footer Inherit ${RUN}`)
		const dashboard = await readDashboard(alice!.api, id)
		expect(dashboard.dashboardFooterMode).toBe('inherit')
		expect(dashboard.dashboardFooterHtml).toBeNull()
		// The render clause is in footer-customization-render.spec.ts.
	})
})
