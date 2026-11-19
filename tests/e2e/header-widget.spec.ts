/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the header widget's background image
 * (openspec/specs/header-widget, REQ-HDR-003/004/006/007), written for
 * e2e-coverage-programme task 4.4: which source wins, the overlay modes, the
 * file-id preview route and its ACL, and the fallback when an image fails.
 * Text and CTA: `header-widget-content.spec.ts`. Accessibility and print:
 * `header-widget-a11y.spec.ts`. Picker and form: `header-widget-form.spec.ts`.
 *
 * Every test seeds its own dashboard (made active for the admin, whose
 * session the browser carries) and places header widgets on it through
 * `POST /api/dashboard/{id}/widgets` with exactly the `content` the scenario
 * names; `support/headerWidget.ts` registers those hooks and deletes the
 * dashboard after each test. The assertions read the rendered DOM: computed
 * styles, bounding boxes, network requests and the accessibility tree.
 *
 * Images are same-origin on purpose; see `support/headerWidget.ts` for why an
 * external host would make every load and fallback test pass or fail for the
 * wrong reason (the workspace CSP refuses it before a request is made).
 *
 * Gate-19 @e2e traceability:
 *   @e2e header-widget::file-id-overrides-url-when-both-set
 *   @e2e header-widget::url-used-when-file-id-is-null
 *   @e2e header-widget::neither-set-defaults-to-background-color
 *   @e2e header-widget::overlay-mode-none-no-overlay
 *   @e2e header-widget::overlay-mode-tint-semi-transparent-color
 *   @e2e header-widget::overlay-mode-gradient-bottom-linear-gradient
 *   @e2e header-widget::default-overlay-mode-based-on-image-presence
 *   @e2e header-widget::user-can-read-file
 *   @e2e header-widget::user-cannot-read-file
 *   @e2e header-widget::file-id-converted-to-preview-url
 *   @e2e header-widget::external-image-returns-404
 *   @e2e header-widget::image-load-timeout
 *   @e2e header-widget::invalid-image-mime-type
 *
 * @spec openspec/specs/header-widget/spec.md
 */

import type { Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	API,
	bg,
	cssOf,
	expectColourOnly,
	expectImageLoaded,
	headerSuite,
	LOGO_URL,
	probe,
	render,
	rgb,
} from './support/headerWidget.ts'
import { contextFor } from './support/principals.ts'

const RUN = Date.now()

test.setTimeout(120_000)

const suite = headerSuite({ withFile: true })
const { place } = suite

test.describe('REQ-HDR-003 image source precedence', () => {
	// @e2e header-widget::file-id-overrides-url-when-both-set
	test('a file id wins over a URL when both are set', async ({ page }) => {
		const requested: string[] = []
		page.on('request', (r) => requested.push(r.url()))
		const url = `${LOGO_URL}?e2e=${RUN}-precedence`
		const id = await place({
			title: 'Precedence',
			backgroundImageFileId: suite.fileId(),
			backgroundImageUrl: url,
		})
		const preview = page.waitForResponse((r) => r.url().includes(`/core/preview?fileId=${suite.fileId()}`))
		const [b] = await render(page, id)

		expect((await preview).status(), 'the file preview route is used').toBe(200)
		expect(await bg(b)).toContain(`/core/preview?fileId=${suite.fileId()}`)
		expect(await bg(b), 'the URL is ignored').not.toContain('logo.svg')
		expect(await probe(b).getAttribute('src')).not.toContain('logo.svg')
		await expect(probe(b)).toHaveAttribute('src', new RegExp(`/core/preview\\?fileId=${suite.fileId()}`))
		await expectImageLoaded(b)
		expect(requested, 'the ignored URL is never fetched').not.toContain(url)
	})

	// @e2e header-widget::url-used-when-file-id-is-null
	test('the URL is fetched and displayed when the file id is null', async ({ page }) => {
		// An absolute http(s) URL; same-origin because the workspace CSP
		// refuses foreign image hosts (see support/headerWidget.ts).
		const url = `${LOGO_URL}?e2e=${RUN}-url`
		const id = await place({
			title: 'From a URL',
			backgroundImageFileId: null,
			backgroundImageUrl: url,
		})
		const fetched = page.waitForResponse((r) => r.url() === url)
		const [b] = await render(page, id)

		expect((await fetched).status(), 'the URL is fetched').toBe(200)
		expect(await bg(b)).toBe(`url("${url}")`)
		await expectImageLoaded(b)
		// Still displayed after the load settles: it did not fall back.
		expect(await bg(b)).toBe(`url("${url}")`)
	})

	// @e2e header-widget::neither-set-defaults-to-background-color
	test('neither source set shows the background colour only', async ({ page }) => {
		const id = await place({
			title: 'Colour only',
			backgroundImageFileId: null,
			backgroundImageUrl: null,
			backgroundColor: '#123456',
		})
		const [b] = await render(page, id)

		expect(await bg(b)).toBe('none')
		expect(await cssOf(b, 'background-color')).toBe(rgb('#123456'))
		await expect(b.locator('img'), 'no image is requested at all').toHaveCount(0)
	})
})

test.describe('REQ-HDR-004 overlay rendering modes', () => {
	// @e2e header-widget::overlay-mode-none-no-overlay
	test('overlay none paints no overlay and shows the image at full opacity', async ({ page }) => {
		const id = await place({
			title: 'No overlay',
			backgroundImageUrl: LOGO_URL,
			backgroundColor: '#ff0000',
			overlayMode: 'none',
		})
		const [b] = await render(page, id)
		await expectImageLoaded(b)

		await expect(b.locator('.cn-header-widget__overlay'), 'no colour overlay').toHaveCount(0)
		expect(await bg(b)).toBe(`url("${LOGO_URL}")`)
		expect(await cssOf(b, 'opacity'), 'the image layer is at full opacity').toBe('1')
		// "backgroundColor MUST be ignored": nothing inside the banner paints
		// the configured colour over the image.
		const painted = await b.evaluate((root, red) => {
			return Array.from(root.querySelectorAll('*')).filter((el) => {
				const style = window.getComputedStyle(el)
				return style.backgroundColor === red || style.backgroundImage.includes(red)
			}).length
		}, rgb('#ff0000'))
		expect(painted, 'no layer above the image is painted in backgroundColor').toBe(0)
	})

	// @e2e header-widget::overlay-mode-tint-semi-transparent-color
	test('overlay tint lays a 40% black layer over the image, under the text', async ({ page }) => {
		const id = await place({
			title: 'Tinted',
			backgroundImageUrl: LOGO_URL,
			backgroundColor: '#000000',
			overlayMode: 'tint',
			overlayOpacity: 0.4,
		})
		const [b] = await render(page, id)
		const overlay = b.locator('.cn-header-widget__overlay')
		await expect(overlay).toHaveCount(1)

		expect(await cssOf(overlay, 'position')).toBe('absolute')
		expect(await cssOf(overlay, 'background-color')).toBe(rgb('#000000'))
		expect(Number(await cssOf(overlay, 'opacity'))).toBeCloseTo(0.4, 5)
		const [ob, bb] = [await overlay.boundingBox(), await b.boundingBox()]
		expect(ob, 'the overlay covers the whole banner').toEqual(bb)

		// The title is what a pointer at its centre hits: it sits above the overlay.
		const onTop = await b.locator('h2').evaluate((h2) => {
			const r = h2.getBoundingClientRect()
			const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
			return hit !== null && (hit === h2 || h2.contains(hit))
		})
		expect(onTop, 'text is positioned above the overlay div').toBe(true)
	})

	// @e2e header-widget::overlay-mode-gradient-bottom-linear-gradient
	test('overlay gradient-bottom runs from transparent at 50% to the colour at 100%', async ({ page }) => {
		const id = await place({
			title: 'Gradient',
			backgroundImageUrl: LOGO_URL,
			backgroundColor: '#000000',
			overlayMode: 'gradient-bottom',
		})
		const [b] = await render(page, id)
		const overlay = b.locator('.cn-header-widget__overlay')
		await expect(overlay).toHaveCount(1)

		const gradient = await cssOf(overlay, 'background-image')
		// Chromium normalises `to bottom` away and `transparent` to rgba(0,0,0,0).
		expect(gradient).toMatch(
			/^linear-gradient\((to bottom, )?(transparent|rgba\(0, 0, 0, 0\)) 50%, rgb\(0, 0, 0\) 100%\)$/,
		)
		expect(await overlay.boundingBox(), 'it spans the full widget height').toEqual(await b.boundingBox())
	})

	// @e2e header-widget::default-overlay-mode-based-on-image-presence
	test('an unset overlay mode tints when there is an image and is none without one', async ({ page }) => {
		const withImage = await place({ title: 'Image, no mode', backgroundImageUrl: LOGO_URL }, 0)
		const without = await place({ title: 'No image, no mode', backgroundColor: '#333333' }, 4)
		const [a, b] = await render(page, withImage, without)
		await expectImageLoaded(a)

		const tint = a.locator('.cn-header-widget__overlay')
		await expect(tint, 'image present: defaults to tint').toHaveCount(1)
		expect(Number(await cssOf(tint, 'opacity'))).toBeCloseTo(0.4, 5)
		expect(await cssOf(tint, 'background-image'), 'a tint, not a gradient').toBe('none')
		await expect(b.locator('.cn-header-widget__overlay'), 'no image: defaults to none').toHaveCount(0)
	})
})

test.describe('REQ-HDR-006 file id image with ACL validation', () => {
	// @e2e header-widget::user-can-read-file
	test('the owner of the file sees it through the preview route', async ({ page }) => {
		const id = await place({ title: 'My file', backgroundImageFileId: suite.fileId(), backgroundColor: '#444444' })
		const preview = page.waitForResponse((r) => r.url().includes(`/core/preview?fileId=${suite.fileId()}`))
		const [b] = await render(page, id)

		const res = await preview
		expect(res.status(), 'the preview route is constructed and answers').toBe(200)
		expect(res.headers()['content-type'] ?? '').toMatch(/^image\//)
		await expectImageLoaded(b)
		expect(await bg(b), 'and it is what the banner shows').toContain(`/core/preview?fileId=${suite.fileId()}`)
	})

	// @e2e header-widget::user-cannot-read-file
	test('a reader who cannot read the file sees the colour, with no error', async ({ browser }) => {
		// The file belongs to the admin and is shared with nobody. The reader
		// is an ordinary account given view access to the dashboard only.
		const id = await place({ title: 'Not your file', backgroundImageFileId: suite.fileId(), backgroundColor: '#225588' })

		// The allowed principal first: the route works, so a refusal below is
		// a refusal and not a broken route.
		const ownerView = await suite.api().get(`/index.php/core/preview?fileId=${suite.fileId()}&x=1024&y=512&a=1`)
		expect(ownerView.status(), 'the owner can read the preview').toBe(200)

		const reader = await provisionThrowawayUser('e2e-header-reader')
		try {
			const share = await suite.api().post(`${API}/dashboard/${suite.dashboard().id}/shares`, {
				data: { shareType: 'user', shareWith: reader.username, permissionLevel: 'view_only' },
			})
			expect(share.status(), await share.text()).toBeLessThan(300)

			const readerApi = await contextFor(reader.username, reader.password)
			try {
				const active = await readerApi.post(`${API}/dashboards/active`, { data: { uuid: suite.dashboard().uuid } })
				expect(active.status(), await active.text()).toBeLessThan(300)
			} finally {
				await readerApi.dispose()
			}

			const { context, page } = await loginAs(browser, reader.username, reader.password)
			try {
				const refused = page.waitForResponse((r) => r.url().includes(`/core/preview?fileId=${suite.fileId()}`))
				const [b] = await render(page, id)
				expect([403, 404], 'the preview route refuses this reader').toContain((await refused).status())
				await expectColourOnly(b, '#225588')
				await expect(b.getByRole('heading', { level: 2, name: 'Not your file' })).toBeVisible()
			} finally {
				await context.close()
			}
		} finally {
			await deprovisionUser(reader.username)
		}
	})

	// @e2e header-widget::file-id-converted-to-preview-url
	test('a file id becomes a Nextcloud preview URL for that file', async ({ page }) => {
		const id = await place({ title: 'Preview URL', backgroundImageFileId: suite.fileId() })
		const [b] = await render(page, id)

		const pattern = new RegExp(`/index\\.php/core/preview\\?fileId=${suite.fileId()}(&|$)`)
		await expect(probe(b)).toHaveAttribute('src', pattern)
		expect(await bg(b)).toMatch(pattern)
	})
})

test.describe('REQ-HDR-007 image load failure graceful fallback', () => {
	/**
	 * Seed a header whose image fails plus a control whose image loads, so a
	 * fallback is proven against a renderer that does paint images.
	 *
	 * @param page the page.
	 * @param url the failing image URL.
	 * @return the failing banner.
	 */
	async function failing(page: Page, url: string): Promise<Locator> {
		const broken = await place({
			title: 'Still here',
			subtitle: 'And so is this',
			backgroundImageUrl: url,
			backgroundColor: '#123456',
			cta: { label: 'Go', url: '/index.php/apps/launchpad/', style: 'primary' },
		}, 0)
		const control = await place({ title: 'Control', backgroundImageUrl: LOGO_URL }, 4)
		const [b, c] = await render(page, broken, control)
		await expectImageLoaded(c)
		expect(await bg(c), 'the control banner keeps its image').toBe(`url("${LOGO_URL}")`)
		return b
	}

	/**
	 * The title, subtitle and CTA are all still there.
	 *
	 * @param b the banner.
	 * @return nothing.
	 */
	async function expectContentIntact(b: Locator): Promise<void> {
		await expect(b.getByRole('heading', { level: 2, name: 'Still here' })).toBeVisible()
		await expect(b.getByText('And so is this')).toBeVisible()
		await expect(b.getByRole('link', { name: 'Go' })).toBeVisible()
	}

	// @e2e header-widget::external-image-returns-404
	test('a 404 image leaves the colour and all the content, with no broken image', async ({ page }) => {
		const url = `${BASE_URL}/core/img/e2e-missing-${RUN}.jpg`
		const missing = page.waitForResponse((r) => r.url() === url)
		const b = await failing(page, url)

		expect((await missing).status()).toBe(404)
		await expectColourOnly(b, '#123456')
		await expectContentIntact(b)
	})

	// @e2e header-widget::image-load-timeout
	test('an image that times out falls back to the colour and renders normally', async ({ page }) => {
		const url = `${BASE_URL}/core/img/e2e-slow-${RUN}.jpg`
		let hits = 0
		await page.route(url, (route) => {
			hits++
			return route.abort('timedout')
		})
		const b = await failing(page, url)

		await expectColourOnly(b, '#123456')
		expect(hits, 'the failure came from the timed-out request').toBeGreaterThan(0)
		await expectContentIntact(b)
	})

	// @e2e header-widget::invalid-image-mime-type
	test('a non-image file falls back to the colour and renders normally', async ({ page }) => {
		// Nextcloud ships robots.txt: a real text/plain file on the same origin.
		const url = `${BASE_URL}/robots.txt`
		const text = page.waitForResponse((r) => r.url() === url)
		const b = await failing(page, url)

		const res = await text
		expect(res.status()).toBe(200)
		expect(res.headers()['content-type'] ?? '').toMatch(/^text\//)
		await expectColourOnly(b, '#123456')
		await expectContentIntact(b)
	})
})
