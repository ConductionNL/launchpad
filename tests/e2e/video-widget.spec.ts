/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `video` widget's hosted players, its
 * form, aspect ratio and empty/error states (openspec/specs/video-widget),
 * written for the e2e-coverage-programme change. The Nextcloud Files source
 * lives in `video-widget-files.spec.ts`.
 *
 * The admin gets one throwaway dashboard. A scenario either drives the Add
 * Widget / Edit Widget modal the way a person does, or seeds the placement it
 * needs over the API and asserts the rendered cell. No player host is ever
 * reached: `stubExternalPlayers()` answers them with an empty page, so `src`,
 * `sandbox` and geometry are real while the network stays local.
 *
 * WHAT THE PRODUCT DOES NOT DO (and so where these tests are red)
 * ================================================================
 * The spec puts URL parsing, defaults and the autoplay/mute invariant on the
 * server. LaunchPad stores the placement's `content` verbatim
 * (`WidgetApiController::contentToPersist`) and the parsing happens in the
 * shared form (`CnVideoWidgetForm` / `videoUrlParser.js`). The scenarios that
 * name the server are asserted through the server, not through the form.
 *
 * Gate-19 @e2e traceability:
 *   @e2e video-widget::widget-stores-youtube-url
 *   @e2e video-widget::no-cookie-setting-default
 *   @e2e video-widget::placement-update-modifies-video-config
 *   @e2e video-widget::config-defaults-are-applied
 *   @e2e video-widget::youtube-url-is-normalized-to-embed-form
 *   @e2e video-widget::vimeo-url-is-normalized
 *   @e2e video-widget::canonical-url-is-cached-in-widget-config
 *   @e2e video-widget::time-offsets-are-preserved-for-youtube
 *   @e2e video-widget::youtube-iframe-uses-proper-sandbox
 *   @e2e video-widget::vimeo-iframe-sandbox
 *   @e2e video-widget::csp-compliance
 *   @e2e video-widget::iframes-respect-aspect-ratio
 *   @e2e video-widget::autoplay-with-muted-enforces-mute
 *   @e2e video-widget::youtube-iframe-autoplay
 *   @e2e video-widget::aspect-ratio-options
 *   @e2e video-widget::aspect-ratio-css-property-modern
 *   @e2e video-widget::portrait-video-916
 *   @e2e video-widget::empty-state-no-video-configured
 *   @e2e video-widget::invalid-url-error
 *
 * @spec openspec/specs/video-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'
import {
	addVideoByUrl,
	boxOf,
	cellOf,
	openEditDialog,
	openVideoForm,
	openWorkspace,
	removePlacements,
	seedVideo,
	storedContent,
	stubExternalPlayers,
	submitVideoForm,
	switchIn,
	videoWidgetOf,
} from './support/videoWidget.ts'

const RUN = Date.now()
const YT_EMBED = 'https://www.youtube.com/embed/ABC123'
const VIMEO_EMBED = 'https://player.vimeo.com/video/12345'

/** Every default the spec's Data Model names. */
const DEFAULTS = {
	autoplay: false,
	muted: true,
	loop: false,
	controls: true,
	aspectRatio: '16:9',
}

/** Sandbox tokens the scenarios forbid: no breaking out, no forms, no popups. */
const FORBIDDEN_SANDBOX = ['allow-top-navigation', 'allow-top-navigation-by-user-activation', 'allow-forms', 'allow-popups']

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const seeded: number[] = []

test.beforeAll(async () => {
	api = await adminContext()
	dashboard = await seedActiveDashboard(api, `E2E Video ${RUN}`)
})

test.afterAll(async () => {
	await removePlacements(api, seeded)
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

test.afterEach(async () => {
	await removePlacements(api, seeded)
})

test.beforeEach(async ({ page }) => {
	await stubExternalPlayers(page)
})

/**
 * Seed a video placement on this file's dashboard and remember it for cleanup.
 *
 * @param content the placement's content.
 * @param size the grid size.
 * @param size.w grid width.
 * @param size.h grid height.
 * @return the placement id.
 */
async function seed(content: Record<string, unknown>, size?: { w: number, h: number }): Promise<number> {
	const id = await seedVideo(api, dashboard!.id, content, size)
	seeded.push(id)
	return id
}

/**
 * The rendered iframe of a placement, and its parsed `src`.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the iframe and its URL.
 */
async function iframeOf(page: Page, placementId: number): Promise<{ iframe: Locator, src: URL }> {
	const widget = await videoWidgetOf(page, placementId)
	const iframe = widget.locator('iframe')
	await expect(iframe, 'a hosted source must render an iframe').toBeAttached()
	const raw = await iframe.getAttribute('src')
	expect(raw, 'the iframe must carry a src').toBeTruthy()
	return { iframe, src: new URL(raw!) }
}

/**
 * The tokens of an iframe's `sandbox` attribute.
 *
 * @param iframe the iframe.
 * @return the tokens, lowercased.
 */
async function sandboxTokens(iframe: Locator): Promise<string[]> {
	const raw = await iframe.getAttribute('sandbox')
	expect(raw, 'a hosted-platform iframe must be sandboxed').not.toBeNull()
	return raw!.toLowerCase().split(/\s+/).filter(Boolean)
}

/**
 * Assert the sandbox the two sandbox scenarios name.
 *
 * Required: `allow-scripts allow-same-origin`. Forbidden: top navigation,
 * forms and popups. `allow-presentation` is the one extra token admitted,
 * because decision D4 of the archived 2026-05-03-video-widget change
 * grants it for fullscreen; any other
 * token is a widening the spec does not allow.
 *
 * @param iframe the iframe.
 * @return nothing.
 */
async function expectStrictSandbox(iframe: Locator): Promise<void> {
	const tokens = await sandboxTokens(iframe)
	expect(tokens).toEqual(expect.arrayContaining(['allow-scripts', 'allow-same-origin']))
	for (const forbidden of FORBIDDEN_SANDBOX) {
		expect(tokens, `the sandbox must not grant ${forbidden}`).not.toContain(forbidden)
	}
	expect(
		tokens.filter((t) => !['allow-scripts', 'allow-same-origin', 'allow-presentation'].includes(t)),
		'no sandbox capability beyond scripts, same-origin and (decision D4) presentation',
	).toEqual([])
	expect(await iframe.getAttribute('allowfullscreen'), 'the iframe must allow fullscreen').not.toBeNull()
}

/**
 * Assert a box has the given width:height ratio, within 2%.
 *
 * @param box the box.
 * @param box.width its width.
 * @param box.height its height.
 * @param w the ratio's width term.
 * @param h the ratio's height term.
 * @param what what the box is, for the message.
 * @return nothing.
 */
function expectRatio(box: { width: number, height: number }, w: number, h: number, what: string): void {
	expect(box.width, `${what} has no width`).toBeGreaterThan(0)
	expect(box.height, `${what} has no height`).toBeGreaterThan(0)
	const actual = box.width / box.height
	expect(Math.abs(actual - w / h) / (w / h), `${what} is ${box.width}x${box.height}, not ${w}:${h}`).toBeLessThan(0.02)
}

/**
 * Assert one box fills another (100% width and height), within a pixel.
 *
 * @param inner the filling element's box.
 * @param outer the container's box.
 * @param what what is filling, for the message.
 * @return nothing.
 */
function expectFills(inner: { width: number, height: number }, outer: { width: number, height: number }, what: string): void {
	expect(Math.abs(inner.width - outer.width), `${what} width must be 100% of its container`).toBeLessThanOrEqual(1)
	expect(Math.abs(inner.height - outer.height), `${what} height must be 100% of its container`).toBeLessThanOrEqual(1)
}

/**
 * Reload the workspace so the grid renders what is stored.
 *
 * @param page the page.
 * @return nothing.
 */
async function reloadWorkspace(page: Page): Promise<void> {
	await page.reload()
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

test.describe('video widget: storing a configuration (REQ-VID-002, REQ-VID-010)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::widget-stores-youtube-url
	// @e2e video-widget::no-cookie-setting-default
	test('a YouTube watch URL typed into the form is stored as the canonical embed URL with every default', async ({ page }) => {
		await openWorkspace(page)
		const placement = await addVideoByUrl(page, api, dashboard!.id, 'https://youtube.com/watch?v=ABC123')
		seeded.push(Number(placement.id))

		const content = placement.content ?? {}
		expect(content).toMatchObject({ sourceType: 'youtube', videoUrl: YT_EMBED, ...DEFAULTS })
		// The scenario writes `posterUrl: null`; the spec's own Data Model
		// says the default is `''`. Either is "no poster"; a URL is not.
		expect(content.posterUrl ?? '', 'no poster was configured').toBe('')

		// REQ-VID-010: with the no-cookie setting not configured, the render
		// uses the standard host, not youtube-nocookie.com.
		await reloadWorkspace(page)
		const { src } = await iframeOf(page, Number(placement.id))
		expect(src.origin).toBe('https://www.youtube.com')
		expect(src.pathname).toBe('/embed/ABC123')
	})

	// @e2e video-widget::placement-update-modifies-video-config
	test('editing a placement sends PUT /api/widgets/{id}, stores the new config and renders it', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, ...DEFAULTS, posterUrl: '' })
		await openWorkspace(page)
		await enterEditMode(page)

		const dialog = await openEditDialog(page, id)
		const url = dialog.getByLabel(/^video url/i)
		await expect(url).toHaveValue(YT_EMBED)
		await url.fill('https://vimeo.com/76979871')
		await expect(dialog.getByText('Detected: Vimeo', { exact: true })).toBeVisible()

		const put = page.waitForRequest((r) =>
			r.method() === 'PUT' && new URL(r.url()).pathname.endsWith(`/api/widgets/${id}`))
		await dialog.locator('[data-testid="add-widget-save"]').click()
		await put
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })

		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored).toMatchObject({ sourceType: 'vimeo', videoUrl: 'https://player.vimeo.com/video/76979871' })

		// "the change MUST take effect on the next render".
		await reloadWorkspace(page)
		const { src } = await iframeOf(page, id)
		expect(`${src.origin}${src.pathname}`).toBe('https://player.vimeo.com/video/76979871')
	})

	// @e2e video-widget::config-defaults-are-applied
	test('a placement submitted with only sourceType and videoUrl is stored with the backend defaults', async () => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED })

		const stored = await storedContent(api, dashboard!.id, id)
		expect.soft(stored.autoplay, 'autoplay default').toBe(false)
		expect.soft(stored.muted, 'muted default').toBe(true)
		expect.soft(stored.loop, 'loop default').toBe(false)
		expect.soft(stored.controls, 'controls default').toBe(true)
		expect.soft(stored.aspectRatio, 'aspectRatio default').toBe('16:9')
		expect.soft('posterUrl' in stored, 'posterUrl default (null) must be stored').toBe(true)
		expect.soft(stored.posterUrl ?? null, 'posterUrl default').toBeNull()
	})
})

test.describe('video widget: URL normalisation (REQ-VID-003, REQ-VID-005)', () => {
	test.describe.configure({ timeout: 180_000 })

	// @e2e video-widget::youtube-url-is-normalized-to-embed-form
	test('every YouTube URL format is stored and rendered as https://www.youtube.com/embed/ABC123', async ({ page }) => {
		await openWorkspace(page)
		const cases: Array<[string, RegExp]> = [
			['https://www.youtube.com/watch?v=ABC123', /^https:\/\/www\.youtube\.com\/embed\/ABC123$/],
			['https://youtu.be/ABC123', /^https:\/\/www\.youtube\.com\/embed\/ABC123$/],
			// "time offset (`t=30s`) MAY be appended as `?start=30`".
			['https://youtube.com/watch?v=ABC123&t=30s', /^https:\/\/www\.youtube\.com\/embed\/ABC123(\?start=30)?$/],
		]
		const ids: number[] = []
		for (const [raw, canonical] of cases) {
			const placement = await addVideoByUrl(page, api, dashboard!.id, raw)
			seeded.push(Number(placement.id))
			ids.push(Number(placement.id))
			expect(String(placement.content?.videoUrl), `stored form of ${raw}`).toMatch(canonical)
			expect(placement.content?.sourceType).toBe('youtube')
		}

		await reloadWorkspace(page)
		for (const id of ids) {
			const { src } = await iframeOf(page, id)
			expect(`${src.origin}${src.pathname}`).toBe(YT_EMBED)
		}
	})

	// @e2e video-widget::vimeo-url-is-normalized
	test('both Vimeo URL formats are stored and rendered as https://player.vimeo.com/video/12345', async ({ page }) => {
		await openWorkspace(page)
		const ids: number[] = []
		for (const raw of ['https://vimeo.com/12345', 'https://player.vimeo.com/video/12345']) {
			const placement = await addVideoByUrl(page, api, dashboard!.id, raw)
			seeded.push(Number(placement.id))
			ids.push(Number(placement.id))
			expect(placement.content, `stored form of ${raw}`).toMatchObject({ sourceType: 'vimeo', videoUrl: VIMEO_EMBED })
		}

		await reloadWorkspace(page)
		for (const id of ids) {
			const { src } = await iframeOf(page, id)
			expect(`${src.origin}${src.pathname}`).toBe(VIMEO_EMBED)
		}
	})

	// @e2e video-widget::canonical-url-is-cached-in-widget-config
	test('the stored canonical URL is what renders, with no re-parse on render', async ({ page }) => {
		await openWorkspace(page)
		const placement = await addVideoByUrl(page, api, dashboard!.id, 'https://www.youtube.com/watch?v=CACHE01')
		const id = Number(placement.id)
		seeded.push(id)
		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored.videoUrl, 'the canonical embed form is what is stored').toBe('https://www.youtube.com/embed/CACHE01')

		// Render: count any call to a parse endpoint, then compare the iframe
		// to the stored value.
		const parses: string[] = []
		page.on('request', (r) => {
			if (/\/api\/widgets\/video\/parse/.test(r.url())) {
				parses.push(r.url())
			}
		})
		await reloadWorkspace(page)
		const { src } = await iframeOf(page, id)
		expect(`${src.origin}${src.pathname}`, 'the iframe is built from the stored URL').toBe(String(stored.videoUrl))
		expect(parses, 'rendering must not re-parse the URL').toEqual([])
	})

	// @e2e video-widget::time-offsets-are-preserved-for-youtube
	test('a t=120s offset becomes ?start=120 in the stored URL and in the player', async ({ page }) => {
		await openWorkspace(page)
		const placement = await addVideoByUrl(page, api, dashboard!.id, 'https://www.youtube.com/watch?v=ABC123&t=120s')
		const id = Number(placement.id)
		seeded.push(id)
		expect(placement.content?.videoUrl).toBe('https://www.youtube.com/embed/ABC123?start=120')

		// "the render MUST jump to 120 seconds on play": the player starts
		// where its `start` parameter says, so the parameter must reach it.
		await reloadWorkspace(page)
		const { src } = await iframeOf(page, id)
		expect(`${src.origin}${src.pathname}`).toBe(YT_EMBED)
		expect(src.searchParams.get('start')).toBe('120')
	})
})

test.describe('video widget: iframe embedding (REQ-VID-007)', () => {
	// @e2e video-widget::youtube-iframe-uses-proper-sandbox
	test('a YouTube placement renders a sandboxed, fullscreen-capable iframe on the canonical URL', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED })
		await openWorkspace(page)
		const { iframe, src } = await iframeOf(page, id)

		await expectStrictSandbox(iframe)
		// The scenario's markup is `src="https://www.youtube.com/embed/ABC123"`
		// for a placement that does not autoplay.
		expect.soft(src.toString(), 'the iframe src is the canonical embed URL').toBe(YT_EMBED)
	})

	// @e2e video-widget::vimeo-iframe-sandbox
	test('a Vimeo placement renders the same sandboxed, fullscreen-capable iframe', async ({ page }) => {
		const id = await seed({ sourceType: 'vimeo', videoUrl: VIMEO_EMBED })
		await openWorkspace(page)
		const { iframe, src } = await iframeOf(page, id)

		expect(`${src.origin}${src.pathname}`).toBe(VIMEO_EMBED)
		await expectStrictSandbox(iframe)
	})

	// @e2e video-widget::csp-compliance
	test('the workspace CSP frame-src admits the player origins, and the browser loads both frames', async ({ page }) => {
		await page.addInitScript(() => {
			const store = window as unknown as { __csp: Array<{ directive: string, blocked: string }> }
			store.__csp = []
			document.addEventListener('securitypolicyviolation', (e) => {
				store.__csp.push({ directive: e.effectiveDirective, blocked: e.blockedURI })
			})
		})
		const yt = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, muted: false })
		const vimeo = await seed({ sourceType: 'vimeo', videoUrl: VIMEO_EMBED, muted: false })

		const response = await page.goto('/index.php/apps/launchpad')
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		const csp = response?.headers()['content-security-policy'] ?? ''
		const frameSrc = csp.split(';').map((d) => d.trim()).find((d) => d.startsWith('frame-src')) ?? ''
		expect(frameSrc, `no frame-src directive in ${csp}`).not.toBe('')
		const sources = frameSrc.split(/\s+/).slice(1)

		for (const id of [yt, vimeo]) {
			const { src } = await iframeOf(page, id)
			expect(sources, `frame-src must admit ${src.origin}`).toContain(src.origin)
			// The stub answers the request only if CSP let the browser make it.
			await expect.poll(async () => {
				const frame = page.frames().find((f) => f.url().startsWith(`${src.origin}${src.pathname}`))
				return frame ? frame.title().catch(() => '') : ''
			}, { message: `the ${src.origin} frame did not load`, timeout: 15_000 }).toBe('stubbed player')
		}

		const violations = await page.evaluate(() =>
			(window as unknown as { __csp: Array<{ directive: string, blocked: string }> }).__csp)
		expect(violations.filter((v) => /^(frame|child)-src$/.test(v.directive)), 'no frame was blocked by CSP').toEqual([])
	})

	// @e2e video-widget::iframes-respect-aspect-ratio
	test('a 4:3 placement keeps its container at 4:3 and the iframe fills it', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, aspectRatio: '4:3' }, { w: 6, h: 8 })
		await openWorkspace(page)
		const { iframe } = await iframeOf(page, id)
		const container = cellOf(page, id).locator('.cn-video-widget__media')

		const outer = await boxOf(container)
		expectRatio(outer, 4, 3, 'the iframe container')
		expectFills(await boxOf(iframe), outer, 'the iframe')
	})
})

test.describe('video widget: playback options (REQ-VID-008)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::autoplay-with-muted-enforces-mute
	test('turning autoplay on forces muted, tells the person why, and the server keeps the invariant', async ({ page }) => {
		await openWorkspace(page)
		const dialog = await openVideoForm(page)
		await dialog.getByLabel(/^video url/i).fill('https://www.youtube.com/watch?v=ABC123')

		const muted = switchIn(dialog, 'Muted')
		const autoplay = switchIn(dialog, 'Autoplay')
		await muted.toggle.click()
		await expect(muted.input, 'precondition: the person unmuted first').not.toBeChecked()

		await autoplay.toggle.click()
		await expect(autoplay.input).toBeChecked()
		await expect(dialog.getByText('Autoplay requires muting', { exact: true })).toBeVisible()
		await expect(muted.input, 'autoplay forces muted back on').toBeChecked()

		const placement = await submitVideoForm(page, dialog, api, dashboard!.id)
		seeded.push(Number(placement.id))
		expect(placement.content).toMatchObject({ autoplay: true, muted: true })

		// "the backend MUST enforce `muted: true`" — a client that skips the
		// form must not be able to store autoplay unmuted.
		const raw = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, autoplay: true, muted: false })
		expect((await storedContent(api, dashboard!.id, raw)).muted, 'the server enforces muted when autoplay is on').toBe(true)
	})

	// @e2e video-widget::youtube-iframe-autoplay
	test('an autoplaying muted YouTube placement appends ?autoplay=1&mute=1', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, autoplay: true, muted: true })
		await openWorkspace(page)
		const { src } = await iframeOf(page, id)
		expect(src.toString()).toBe(`${YT_EMBED}?autoplay=1&mute=1`)
	})
})

test.describe('video widget: aspect ratio (REQ-VID-009)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::aspect-ratio-options
	test('the dropdown offers exactly 16:9, 4:3, 1:1 and 9:16, and each renders true on desktop and mobile', async ({ page }) => {
		await openWorkspace(page)
		const dialog = await openVideoForm(page)
		const select = dialog.locator('.cn-video-form .v-select').first()
		await expect(select.locator('.vs__selected'), 'a new placement starts at 16:9').toHaveText(/16:9/)
		await select.locator('.vs__dropdown-toggle').click()
		const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
		await expect(options.first()).toBeVisible({ timeout: 5_000 })
		expect((await options.allTextContents()).map((s) => s.trim())).toEqual(['16:9', '4:3', '1:1', '9:16'])
		await page.keyboard.press('Escape')
		await dialog.getByRole('button', { name: /^cancel$/i }).click().catch(() => null)

		const ratios: Array<[string, number, number]> = [['16:9', 16, 9], ['4:3', 4, 3], ['1:1', 1, 1], ['9:16', 9, 16]]
		const ids: Array<[number, number, number]> = []
		for (const [ratio, w, h] of ratios) {
			ids.push([await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, aspectRatio: ratio }, { w: 4, h: 10 }), w, h])
		}

		for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
			await page.setViewportSize(viewport)
			await reloadWorkspace(page)
			for (const [id, w, h] of ids) {
				const { iframe } = await iframeOf(page, id)
				const box = await boxOf(cellOf(page, id).locator('.cn-video-widget__media'))
				expectRatio(box, w, h, `the ${w}:${h} container at ${viewport.width}px`)
				expectFills(await boxOf(iframe), box, `the ${w}:${h} iframe at ${viewport.width}px`)
			}
		}
	})

	// @e2e video-widget::aspect-ratio-css-property-modern
	test('a 16:9 container uses the CSS aspect-ratio property and the iframe fills it', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, aspectRatio: '16:9' })
		await openWorkspace(page)
		const { iframe } = await iframeOf(page, id)
		const container = cellOf(page, id).locator('.cn-video-widget__media')

		const aspect = await container.evaluate((el) => getComputedStyle(el).aspectRatio)
		expect(aspect.replace(/\s+/g, ' ').trim()).toBe('16 / 9')
		const outer = await boxOf(container)
		expectRatio(outer, 16, 9, 'the 16:9 container')
		expectFills(await boxOf(iframe), outer, 'the iframe')
	})

	// @e2e video-widget::portrait-video-916
	test('a 9:16 placement in a landscape cell is height-constrained and undistorted', async ({ page }) => {
		const id = await seed({ sourceType: 'youtube', videoUrl: YT_EMBED, aspectRatio: '9:16' }, { w: 6, h: 3 })
		await openWorkspace(page)
		const { iframe } = await iframeOf(page, id)
		const widget = cellOf(page, id).locator('.cn-video-widget')
		const media = cellOf(page, id).locator('.cn-video-widget__media')

		const frame = await boxOf(widget)
		const box = await boxOf(media)
		// Not distorted: still 9:16, and the player fills it.
		expectRatio(box, 9, 16, 'the portrait container')
		expectFills(await boxOf(iframe), box, 'the portrait iframe')
		// Constrained: its height follows the cell, so the whole player is
		// on screen instead of being cut off at the bottom of the cell.
		expect(box.height, 'the portrait container must not be taller than its cell').toBeLessThanOrEqual(frame.height + 1)
		expect(box.y, 'the portrait container must start inside its cell').toBeGreaterThanOrEqual(frame.y - 1)
	})
})

test.describe('video widget: empty and invalid states (REQ-VID-011)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::empty-state-no-video-configured
	test('a placement with no source shows a centred empty state with help text and, in edit mode, "Configure now"', async ({ page }) => {
		const id = await seed({ sourceType: null, videoUrl: '', fileId: null })
		await openWorkspace(page)
		const widget = await videoWidgetOf(page, id)

		await expect(widget.getByText('No video URL configured', { exact: true })).toBeVisible()
		await expect(widget.locator('iframe, video'), 'nothing is embedded').toHaveCount(0)

		// Centred: the placeholder's content sits in the middle of the cell.
		const centre = await widget.evaluate((root) => {
			const ph = root.querySelector('.cn-video-widget__placeholder')
			if (!ph) {
				return null
			}
			const rects = [...ph.children].map((c) => c.getBoundingClientRect())
			const r = root.getBoundingClientRect()
			const top = Math.min(...rects.map((x) => x.top))
			const bottom = Math.max(...rects.map((x) => x.bottom))
			const left = Math.min(...rects.map((x) => x.left))
			const right = Math.max(...rects.map((x) => x.right))
			return { dx: (left + right) / 2 - (r.left + r.right) / 2, dy: (top + bottom) / 2 - (r.top + r.bottom) / 2 }
		})
		expect(centre, 'the empty state has a placeholder').not.toBeNull()
		expect(Math.abs(centre!.dx), 'horizontally centred').toBeLessThanOrEqual(8)
		expect(Math.abs(centre!.dy), 'vertically centred').toBeLessThanOrEqual(8)

		await expect.soft(widget.getByText('Edit the widget to add a video', { exact: true }), 'the help text').toBeVisible()

		await enterEditMode(page)
		const configure = cellOf(page, id).getByText('Configure now', { exact: true })
		await expect(configure, 'edit mode offers a "Configure now" link').toBeVisible()
		await configure.click()
		await expect(page.getByRole('dialog', { name: /edit widget/i }).locator('.cn-video-form')).toBeVisible({ timeout: 10_000 })
	})

	// @e2e video-widget::invalid-url-error
	test('"not a valid URL" is refused by the parse endpoint, shown under the field, and blocks saving', async ({ page }) => {
		await openWorkspace(page)
		const dialog = await openVideoForm(page)
		const parsed = page.waitForResponse((r) => /\/api\/widgets\/video\/parse/.test(r.url()), { timeout: 10_000 })
			.catch(() => null)

		const field = dialog.getByLabel(/^video url/i)
		await field.fill('not a valid URL')
		await field.press('Tab')

		await expect(dialog.locator('[data-testid="add-widget-save"]'), 'the save button is disabled').toBeDisabled()

		const error = dialog.locator('.cn-video-form [role="alert"]').first()
		await expect(error, 'an error is shown with the form').toBeVisible()
		const fieldBox = await boxOf(field)
		expect((await boxOf(error)).y, 'the error sits below the URL field').toBeGreaterThanOrEqual(fieldBox.y + fieldBox.height - 1)
		await expect.soft(error, 'the error is the parse endpoint\'s').toHaveText('Invalid URL format')

		const response = await parsed
		expect.soft(response, 'POST /api/widgets/video/parse was called').not.toBeNull()
		if (response) {
			expect.soft(response.status()).toBe(400)
			expect.soft(await response.json()).toMatchObject({ isValid: false, error: 'Invalid URL format' })
		}
	})
})
