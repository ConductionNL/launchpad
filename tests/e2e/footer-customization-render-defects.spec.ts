/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The footer-customization scenarios that need a footer ON the page
 * (openspec/specs/footer-customization): global on, HTML and structured
 * content, the per-dashboard override, language variants, print and colours.
 * The two "must NOT render" scenarios and the shared narrative live in
 * footer-customization-render.spec.ts; the settings API contract in
 * footer-customization-api.spec.ts.
 *
 * The footer reaches the page through the workspace initial state:
 * PageController puts the active dashboard's effective footer
 * (lib/Service/PageFooter.php, with the dashboard's primary language) and
 * the viewer's Nextcloud language under `effectiveFooter` and
 * `viewerLocale`, and src/views/WorkspaceApp.vue hands them to
 * DashboardFooter. The per-dashboard override travels in the dashboard
 * update (DashboardApiController::update, owner only).
 *
 * The tests are not serial: each sets the footer it needs, so a red one
 * cannot skip the next. The footer is instance-wide; `beforeAll` snapshots
 * the five footer keys and the personal-dashboard flags, `afterAll` writes
 * them back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e footer-customization::admin-enables-footer
 *   @e2e footer-customization::disabling-footer-hides-it-everywhere
 *   @e2e footer-customization::footer-renders-with-enabled-global-footer
 *   @e2e footer-customization::admin-provides-valid-html
 *   @e2e footer-customization::external-links-get-rel-attribute
 *   @e2e footer-customization::footer-renders-structured-content-in-columns-layout
 *   @e2e footer-customization::footer-renders-structured-content-in-inline-layout
 *   @e2e footer-customization::partial-structured-config-optional-fields
 *   @e2e footer-customization::links-array-in-structured-config
 *   @e2e footer-customization::dashboard-with-inherit-mode-default
 *   @e2e footer-customization::dashboard-with-hidden-mode
 *   @e2e footer-customization::dashboard-with-custom-mode
 *   @e2e footer-customization::single-language-footer-default
 *   @e2e footer-customization::language-tagged-footer-variants
 *   @e2e footer-customization::locale-fallback-step-2-then-step-3
 *   @e2e footer-customization::locale-fallback-step-3-first-key
 *   @e2e footer-customization::language-variants-in-structured-config
 *   @e2e footer-customization::footer-prints-in-pdf
 *   @e2e footer-customization::print-specific-css
 *   @e2e footer-customization::footer-does-not-break-layout-on-print
 *   @e2e footer-customization::footer-inherits-theme-colors
 *   @e2e footer-customization::admin-overrides-footer-colors
 *   @e2e footer-customization::color-override-fallback
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { FooterSettings } from './support/footerSettings.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	readFooter,
	resetFooter,
	restoreFooter,
	writeFooter,
} from './support/footerSettings.ts'
import {
	adminContext,
	API,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = `${API}/admin/settings`
const FOOTER = 'footer.launchpad-footer'
const FOOTER_HTML = `${FOOTER} .launchpad-footer__html`
const GRID = '.workspace-shell__grid'

let admin: APIRequestContext
let footerSnapshot: FooterSettings | undefined
let flagSnapshot: { allowUserDash: boolean; allowMultiDash: boolean } | undefined
let adminDash: SeededDashboard | null = null
const principals: Principal[] = []

/**
 * Open LaunchPad on the active dashboard and wait for its grid.
 *
 * @param page a page logged in as the viewer.
 * @return the footer locator.
 */
async function openDashboard(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(page.locator(GRID)).toBeVisible({ timeout: 45_000 })
	return page.locator(FOOTER)
}

/**
 * A throwaway non-admin viewer with Nextcloud language `lang` and a personal
 * dashboard of their own, made active.
 *
 * @param lang the viewer's NC language, or null to leave the default.
 * @return the principal and its dashboard's id and uuid.
 */
async function viewer(
	lang: string | null,
): Promise<{ who: Principal; id: number; uuid: string }> {
	const who = await createPrincipal(admin, 'e2e-footer-view')
	principals.push(who)
	if (lang !== null) {
		const set = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(who.username)}`,
			{
				form: { key: 'language', value: lang },
			},
		)
		expect(set.status(), await set.text()).toBe(200)
	}
	const created = await who.api.post(`${API}/dashboard`, {
		data: { name: `E2E Footer Viewer ${RUN}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	const active = await who.api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(active.status(), await active.text()).toBeLessThan(300)
	return { who, id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Give a dashboard a primary language (dashboard-language-content): add the
 * variant, then promote it.
 *
 * @param api the owner's context.
 * @param uuid the dashboard.
 * @param lang the language to make primary.
 * @return nothing.
 */
async function setPrimaryLanguage(
	api: APIRequestContext,
	uuid: string,
	lang: string,
): Promise<void> {
	const created = await api.post(`${API}/dashboards/${uuid}/translations`, {
		data: { languageCode: lang, name: `E2E ${lang} ${RUN}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const promoted = await api.post(
		`${API}/dashboards/${uuid}/translations/${lang}/set-primary`,
	)
	expect(promoted.status(), await promoted.text()).toBe(200)
}

/**
 * Log in as a principal and open its active dashboard.
 *
 * @param browser the browser.
 * @param who the principal.
 * @return the session and the footer locator.
 */
async function openAs(browser: Browser, who: Principal) {
	const session = await loginAs(browser, who.username, who.password)
	const footer = await openDashboard(session.page)
	return { ...session, footer }
}

/**
 * The resolved colour a CSS custom property gives, measured on a probe
 * element so it is compared in the same `rgb()` form as computed styles.
 *
 * @param page the page.
 * @param property `background-color` or `color`.
 * @param variable the custom property, e.g. `--color-main-text`.
 * @return the computed colour.
 */
async function themeColour(
	page: Page,
	property: string,
	variable: string,
): Promise<string> {
	return page.evaluate(
		([prop, cssVar]) => {
			const probe = document.createElement('div')
			probe.style.setProperty(prop, `var(${cssVar})`)
			document.body.appendChild(probe)
			const value = getComputedStyle(probe).getPropertyValue(prop)
			probe.remove()
			return value
		},
		[property, variable],
	)
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

	adminDash = await seedActiveDashboard(admin, `E2E Footer ${RUN}`)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	await removeSeededDashboard(admin, adminDash)
	await restoreFooter(admin, footerSnapshot)
	if (flagSnapshot !== undefined) {
		await admin.put(SETTINGS, { data: flagSnapshot })
	}
	for (const who of principals) {
		await removePrincipal(admin, who)
	}
	await admin.dispose()
})

test.describe('global footer shown (REQ-FTR-001, REQ-FTR-002, REQ-FTR-004)', () => {
	// @e2e footer-customization::admin-enables-footer
	test('enabling the footer shows it on the next page load', async ({
		page,
	}) => {
		const marker = `E2E enabled ${RUN}`
		await resetFooter(admin, {
			footerEnabled: false,
			footerHtml: `<p>${marker}</p>`,
		})
		let footer = await openDashboard(page)
		await expect(footer).toHaveCount(0)

		await writeFooter(admin, { footerEnabled: true })
		footer = await openDashboard(page)
		await expect(footer).toBeVisible()
		await expect(footer).toContainText(marker)
	})

	// @e2e footer-customization::disabling-footer-hides-it-everywhere
	test('disabling the footer hides it on every inherit dashboard but not on a custom one', async ({
		page,
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>E2E global</p>',
		})
		const custom = await viewer(null)
		const put = await custom.who.api.put(`${API}/dashboard/${custom.id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml: '<p>E2E custom stays</p>',
			},
		})
		expect(put.status(), await put.text()).toBe(200)

		await expect(await openDashboard(page)).toContainText('E2E global')

		await writeFooter(admin, { footerEnabled: false })
		await expect(await openDashboard(page)).toHaveCount(0)

		const session = await openAs(browser, custom.who)
		try {
			await expect(session.footer).toBeVisible()
			await expect(session.footer).toContainText('E2E custom stays')
			await expect(session.footer).not.toContainText('E2E global')
		} finally {
			await session.context.close()
		}
	})

	// @e2e footer-customization::footer-renders-with-enabled-global-footer
	test('an enabled HTML footer renders below the grid with the stored HTML', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Copyright 2026</p>',
		})
		const footer = await openDashboard(page)
		await expect(footer).toBeVisible()
		expect(await page.locator(FOOTER_HTML).innerHTML()).toBe(
			'<p>Copyright 2026</p>',
		)

		const grid = await page.locator(GRID).boundingBox()
		const box = await footer.boundingBox()
		expect(grid).not.toBeNull()
		expect(box).not.toBeNull()
		expect(box!.y).toBeGreaterThanOrEqual(grid!.y + grid!.height - 1)
	})

	// @e2e footer-customization::admin-provides-valid-html
	test('the sanitised HTML is what renders', async ({ page }) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml:
				'<p>Copyright <strong>2026</strong><span>!</span></p><ul><li>x</li></ul>',
		})
		const stored = (await readFooter(admin)).footerHtml
		await openDashboard(page)
		expect(await page.locator(FOOTER_HTML).innerHTML()).toBe(stored)
		await expect(page.locator(`${FOOTER_HTML} span`)).toHaveCount(0)
		await expect(page.locator(`${FOOTER_HTML} strong`)).toHaveText('2026')
	})

	// @e2e footer-customization::external-links-get-rel-attribute
	test('a rendered external link carries rel and target', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: "<a href='https://example.com'>Link</a>",
		})
		await openDashboard(page)
		const link = page.locator(`${FOOTER_HTML} a`, { hasText: 'Link' })
		await expect(link).toHaveAttribute('href', 'https://example.com')
		await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
		await expect(link).toHaveAttribute('target', '_blank')
	})
})

test.describe('structured footer rendering (REQ-FTR-003, REQ-FTR-004)', () => {
	const FULL = {
		logoUrl: 'https://example.com/logo.png',
		organisation: 'ACME Corp',
		address: 'Main Street 123\n1234 AB Amsterdam',
		links: [{ label: 'Privacy', url: 'https://example.com/privacy' }],
		legal: 'All rights reserved',
		copyrightYear: 2026,
	}

	// @e2e footer-customization::footer-renders-structured-content-in-columns-layout
	test('columns layout is a 3-column grid: logo+org, address lines, links+legal', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerConfig: { ...FULL, layoutMode: 'columns' },
		})
		const footer = await openDashboard(page)
		const config = footer.locator('.launchpad-footer__config--columns')
		await expect(config).toBeVisible()
		expect(await config.evaluate((el) => getComputedStyle(el).display)).toBe(
			'grid',
		)
		const tracks = (
			await config.evaluate((el) => getComputedStyle(el).gridTemplateColumns)
		).split(' ')
		expect(tracks).toHaveLength(3)
		const widths = tracks.map((track) => parseFloat(track))
		expect(Math.max(...widths) - Math.min(...widths), 'three equal columns').toBeLessThan(1)
		const gap = await config.evaluate((el) => {
			const rem = parseFloat(
				getComputedStyle(document.documentElement).fontSize,
			)
			return parseFloat(getComputedStyle(el).columnGap) / rem
		})
		expect(gap).toBeCloseTo(2, 1)

		const cols = config.locator(':scope > .launchpad-footer__col')
		await expect(cols).toHaveCount(3)
		await expect(cols.nth(0).locator('img')).toHaveAttribute('src', FULL.logoUrl)
		await expect(cols.nth(0)).toContainText('ACME Corp')
		await expect(cols.nth(1).locator(':scope > div')).toHaveText([
			'Main Street 123',
			'1234 AB Amsterdam',
		])
		await expect(cols.nth(2).locator('ul li a')).toHaveText(['Privacy'])
		await expect(cols.nth(2).locator('.launchpad-footer__legal')).toContainText(
			'All rights reserved',
		)
		const linksBox = await cols.nth(2).locator('ul').boundingBox()
		const legalBox = await cols
			.nth(2)
			.locator('.launchpad-footer__legal')
			.boundingBox()
		expect(legalBox!.y).toBeGreaterThanOrEqual(
			linksBox!.y + linksBox!.height - 1,
		)
	})

	// @e2e footer-customization::footer-renders-structured-content-in-inline-layout
	test('inline layout is one separated row that wraps on a narrow screen', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerConfig: { ...FULL, layoutMode: 'inline' },
		})
		await page.setViewportSize({ width: 1600, height: 900 })
		const footer = await openDashboard(page)
		const config = footer.locator('.launchpad-footer__config--inline')
		await expect(config).toBeVisible()
		expect(await config.evaluate((el) => getComputedStyle(el).flexWrap)).toBe(
			'wrap',
		)

		const cols = config.locator(':scope > .launchpad-footer__col')
		// The row centres its items, so items of one row share their middle,
		// not their top.
		const rowTops = async () =>
			new Set(
				await cols.evaluateAll((els) =>
					els.map((el) => {
						const box = el.getBoundingClientRect()
						return Math.round(box.top + box.height / 2)
					}),
				),
			).size
		expect(await rowTops()).toBe(1)

		const separators = await cols.evaluateAll((els) =>
			els.map((el) => getComputedStyle(el, '::before').content),
		)
		expect(separators[0]).toBe('none')
		for (const content of separators.slice(1)) {
			expect(content).toMatch(/[·|•]/)
		}

		await page.setViewportSize({ width: 360, height: 800 })
		await expect.poll(rowTops).toBeGreaterThan(1)
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth
				> document.documentElement.clientWidth,
		)
		expect(overflow).toBe(false)
	})

	// @e2e footer-customization::partial-structured-config-optional-fields
	test('a partial config renders what it has and skips the rest', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerConfig: { organisation: 'ACME', layoutMode: 'inline' },
		})
		const footer = await openDashboard(page)
		await expect(footer.locator('.launchpad-footer__org')).toHaveText('ACME')
		await expect(footer.locator('img')).toHaveCount(0)
		await expect(footer.locator('.launchpad-footer__address')).toHaveCount(0)
		await expect(footer.locator('.launchpad-footer__links')).toHaveCount(0)
		await expect(footer.locator('.launchpad-footer__legal')).toHaveCount(0)
		await expect(footer).not.toContainText('undefined')
		await expect(footer).not.toContainText('null')
	})

	// @e2e footer-customization::links-array-in-structured-config
	test('each configured link renders as an anchor in a list', async ({
		page,
	}) => {
		const links = [
			{ label: 'Privacy', url: 'https://example.com/privacy' },
			{ label: 'Contact', url: 'https://example.com/contact' },
		]
		await resetFooter(admin, { footerEnabled: true, footerConfig: { links } })
		const footer = await openDashboard(page)
		const anchors = footer.locator('ul li a')
		await expect(anchors).toHaveText(['Privacy', 'Contact'])
		await expect(anchors.nth(0)).toHaveAttribute('href', links[0].url)
		await expect(anchors.nth(1)).toHaveAttribute('href', links[1].url)
	})
})

test.describe('per-dashboard footer rendering (REQ-FTR-006)', () => {
	// @e2e footer-customization::dashboard-with-inherit-mode-default
	test('an inherit dashboard renders the global footer', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>E2E inherited</p>',
		})
		const res = await admin.get(`${API}/dashboard/${adminDash!.id}`)
		const dashboard = (await res.json()).dashboard
		expect(dashboard.dashboardFooterMode).toBe('inherit')
		expect(dashboard.dashboardFooterHtml).toBeNull()

		await openDashboard(page)
		expect(await page.locator(FOOTER_HTML).innerHTML()).toBe(
			'<p>E2E inherited</p>',
		)
	})

	// @e2e footer-customization::dashboard-with-hidden-mode
	test('a hidden dashboard renders no footer while the global one is on', async ({
		page,
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>E2E global on</p>',
		})
		const hidden = await viewer(null)
		const put = await hidden.who.api.put(`${API}/dashboard/${hidden.id}`, {
			data: { dashboardFooterMode: 'hidden' },
		})
		expect(put.status(), await put.text()).toBe(200)
		const reread = await hidden.who.api.get(`${API}/dashboard/${hidden.id}`)
		expect((await reread.json()).dashboard.dashboardFooterMode).toBe('hidden')

		// The global footer is visibly on elsewhere.
		await expect(await openDashboard(page)).toContainText('E2E global on')

		const session = await openAs(browser, hidden.who)
		try {
			await expect(session.footer).toHaveCount(0)
			await expect(session.page.getByText('E2E global on')).toHaveCount(0)
		} finally {
			await session.context.close()
		}
	})

	// @e2e footer-customization::dashboard-with-custom-mode
	test('a custom dashboard renders its own sanitised footer to every viewer', async ({
		page,
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>E2E global</p>',
		})
		const put = await admin.put(`${API}/dashboard/${adminDash!.id}`, {
			data: {
				dashboardFooterMode: 'custom',
				dashboardFooterHtml:
					"<p onclick='steal()' class='x'>Custom footer</p><script>steal()</script>",
			},
		})
		expect(put.status(), await put.text()).toBe(200)
		const reread = await admin.get(`${API}/dashboard/${adminDash!.id}`)
		expect((await reread.json()).dashboard.dashboardFooterHtml).toBe(
			'<p>Custom footer</p>',
		)

		await openDashboard(page)
		expect(await page.locator(FOOTER_HTML).innerHTML()).toBe(
			'<p>Custom footer</p>',
		)
		await expect(page.locator(FOOTER)).not.toContainText('E2E global')

		// Any user: a view-only recipient sees the same custom footer.
		const reader = await createPrincipal(admin, 'e2e-footer-reader')
		principals.push(reader)
		await publishForRecipients(admin, adminDash!.id)
		await shareWith(admin, adminDash!.id, reader.username, 'view_only')
		const active = await reader.api.post(`${API}/dashboards/active`, {
			data: { uuid: adminDash!.uuid },
		})
		expect(active.status(), await active.text()).toBeLessThan(300)
		const session = await openAs(browser, reader)
		try {
			expect(await session.page.locator(FOOTER_HTML).innerHTML()).toBe(
				'<p>Custom footer</p>',
			)
		} finally {
			await session.context.close()
			await admin.put(`${API}/dashboard/${adminDash!.id}`, {
				data: { dashboardFooterMode: 'inherit' },
			})
		}
	})
})

test.describe('language variants (REQ-FTR-007)', () => {
	/**
	 * Open the footer as a fresh viewer in `lang`, optionally with a primary
	 * dashboard language, and return its rendered HTML.
	 *
	 * @param browser the browser.
	 * @param lang the viewer's NC language.
	 * @param primary the dashboard's primary language, or null.
	 * @return the footer's inner HTML.
	 */
	async function renderedFor(
		browser: Browser,
		lang: string,
		primary: string | null,
	): Promise<string> {
		const v = await viewer(lang)
		if (primary !== null) {
			await setPrimaryLanguage(v.who.api, v.uuid, primary)
		}
		const session = await openAs(browser, v.who)
		try {
			await expect(session.footer).toBeVisible()
			return await session.page.locator(FOOTER_HTML).innerHTML()
		} finally {
			await session.context.close()
		}
	}

	// @e2e footer-customization::single-language-footer-default
	test('a plain-string footer renders as-is for an nl viewer', async ({
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Welcome</p>',
		})
		expect(await renderedFor(browser, 'nl', null)).toBe('<p>Welcome</p>')
	})

	// @e2e footer-customization::language-tagged-footer-variants
	test('an nl viewer sees the nl variant', async ({ browser }) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: { en: '<p>Welcome</p>', nl: '<p>Welkom</p>' },
		})
		expect(await renderedFor(browser, 'nl', null)).toBe('<p>Welkom</p>')
	})

	// @e2e footer-customization::locale-fallback-step-2-then-step-3
	test('a de viewer falls back to the dashboard primary language fr', async ({
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: { en: '<p>English</p>', fr: '<p>Français</p>' },
		})
		// `en` is the first key: step 3 would answer English, so Français
		// proves step 2 ran first.
		expect(await renderedFor(browser, 'de', 'fr')).toBe('<p>Français</p>')
	})

	// @e2e footer-customization::locale-fallback-step-3-first-key
	test('a ja viewer on a de dashboard gets the first key, every time', async ({
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: { fr: '<p>Français</p>', nl: '<p>Welkom</p>' },
		})
		expect(await renderedFor(browser, 'ja', 'de')).toBe('<p>Français</p>')
		// Deterministic: a second viewer in the same situation sees the same.
		expect(await renderedFor(browser, 'ja', 'de')).toBe('<p>Français</p>')
	})

	// @e2e footer-customization::language-variants-in-structured-config
	test('an nl viewer sees the nl organisation of a structured footer', async ({
		browser,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerConfig: {
				organisation: { en: 'ACME', nl: 'ACME NL' },
				layoutMode: 'columns',
			},
		})
		const v = await viewer('nl')
		const session = await openAs(browser, v.who)
		try {
			await expect(
				session.footer.locator('.launchpad-footer__org'),
			).toHaveText('ACME NL')
		} finally {
			await session.context.close()
		}
	})
})

test.describe('print (REQ-FTR-008)', () => {
	// @e2e footer-customization::footer-prints-in-pdf
	test('the footer is in print output, not fixed, and legible in grayscale', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Copyright 2026</p>',
		})
		const footer = await openDashboard(page)
		await page.emulateMedia({ media: 'print' })
		await expect(footer).toBeVisible()
		await expect(footer).toContainText('Copyright 2026')
		expect(
			await footer.evaluate((el) => getComputedStyle(el).position),
		).not.toMatch(/fixed|sticky/)

		// Grayscale legibility: WCAG contrast of text against background >= 4.5.
		const ratio = await footer.evaluate((el) => {
			const rgb = (c: string) =>
				(c.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number)
			const lum = ([r, g, b]: number[]) => {
				const f = (v: number) => {
					const s = v / 255
					return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
				}
				return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)
			}
			let bgEl: Element | null = el
			let bg = 'rgba(0, 0, 0, 0)'
			while (bgEl && /rgba\(0, 0, 0, 0\)|transparent/.test(bg)) {
				bg = getComputedStyle(bgEl).backgroundColor
				bgEl = bgEl.parentElement
			}
			if (/rgba\(0, 0, 0, 0\)|transparent/.test(bg)) {
				bg = 'rgb(255, 255, 255)'
			}
			const a = lum(rgb(getComputedStyle(el).color))
			const b = lum(rgb(bg))
			return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)
		})
		expect(ratio).toBeGreaterThanOrEqual(4.5)

		const pdf = await page.pdf()
		expect(pdf.byteLength).toBeGreaterThan(0)
	})

	// @e2e footer-customization::print-specific-css
	test('in print the footer is displayed with its colours and images', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Printed</p><img src="https://example.com/logo.png" />',
			footerBackgroundColor: '#1a1a1a',
			footerTextColor: '#ffffff',
		})
		const footer = await openDashboard(page)
		await page.emulateMedia({ media: 'print' })
		const style = await footer.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				display: s.display,
				background: s.backgroundColor,
				colour: s.color,
			}
		})
		expect(style.display).toBe('block')
		expect(style.background).toBe('rgb(26, 26, 26)')
		expect(style.colour).toBe('rgb(255, 255, 255)')
		await expect(footer.locator('img')).toHaveAttribute(
			'src',
			'https://example.com/logo.png',
		)
		expect(
			await footer
				.locator('img')
				.evaluate((el) => getComputedStyle(el).display),
		).not.toBe('none')
	})

	// @e2e footer-customization::footer-does-not-break-layout-on-print
	test('in print the footer follows the content once and is not split', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Once only</p>',
		})
		const footer = await openDashboard(page)
		await page.emulateMedia({ media: 'print' })
		await expect(footer).toHaveCount(1)
		const style = await footer.evaluate((el) => {
			const s = getComputedStyle(el)
			return { position: s.position, breakInside: s.breakInside }
		})
		// A fixed element repeats on every printed page; a static one does not.
		expect(['static', 'relative']).toContain(style.position)
		expect(style.breakInside).toBe('avoid')
		const grid = await page.locator(GRID).boundingBox()
		const box = await footer.boundingBox()
		expect(box!.y).toBeGreaterThanOrEqual(grid!.y + grid!.height - 1)
	})
})

test.describe('theme colours (REQ-FTR-009)', () => {
	/*
	 * Nextcloud's theming exposes `--color-main-background` and
	 * `--color-main-text`, which DashboardFooter.vue's scoped style uses.
	 */

	// @e2e footer-customization::footer-inherits-theme-colors
	test('with no overrides the footer uses the theme colours', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Themed</p>',
		})
		const footer = await openDashboard(page)
		await expect(footer).toBeVisible()
		const computed = await footer.evaluate((el) => {
			const s = getComputedStyle(el)
			return { background: s.backgroundColor, colour: s.color }
		})
		expect(computed.background).toBe(
			await themeColour(
				page,
				'background-color',
				'--color-main-background',
			),
		)
		expect(computed.colour).toBe(
			await themeColour(page, 'color', '--color-main-text'),
		)
	})

	// @e2e footer-customization::admin-overrides-footer-colors
	test('admin colour overrides replace the theme colours', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Overridden</p>',
			footerBackgroundColor: '#1a1a1a',
			footerTextColor: '#ffffff',
		})
		const footer = await openDashboard(page)
		await expect(footer).toHaveCSS('background-color', 'rgb(26, 26, 26)')
		await expect(footer).toHaveCSS('color', 'rgb(255, 255, 255)')
		expect('rgb(26, 26, 26)').not.toBe(
			await themeColour(
				page,
				'background-color',
				'--color-main-background',
			),
		)
	})

	// @e2e footer-customization::color-override-fallback
	test('a background override alone leaves the text on the theme colour', async ({
		page,
	}) => {
		await resetFooter(admin, {
			footerEnabled: true,
			footerHtml: '<p>Half overridden</p>',
			footerBackgroundColor: '#1a1a1a',
			footerTextColor: null,
		})
		const footer = await openDashboard(page)
		await expect(footer).toHaveCSS('background-color', 'rgb(26, 26, 26)')
		await expect(footer).toHaveCSS(
			'color',
			await themeColour(page, 'color', '--color-main-text'),
		)
	})
})
