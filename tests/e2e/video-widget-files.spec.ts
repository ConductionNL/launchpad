/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `video` widget's Nextcloud Files source
 * (`sourceType: 'nc-file'`) and its localised messages
 * (openspec/specs/video-widget), written for the e2e-coverage-programme
 * change. Hosted players, the form and aspect ratio live in
 * `video-widget.spec.ts`.
 *
 * Real files are uploaded into the admin's home over WebDAV — a two-second
 * VP8 clip (`fixtures/tiny.webm`) where playback matters, small dummy files
 * where only the MIME type does — and every placement points at a real file
 * id. Access is probed with real throwaway accounts that view the admin's
 * dashboard through a view-only share, so the viewer is a logged-in user who
 * genuinely cannot read the file.
 *
 * HOW THE FILE IS RESOLVED
 * ========================
 * The renderer (nc-vue `CnVideoWidget`) plays `content.fileStreamingUrl`.
 * LaunchPad's `VideoHost` asks `GET /api/widgets/video/{placementId}/file`
 * at render time, for the viewer, and hands CnVideoWidget either the
 * streaming URL or the message; the stored placement never carries a URL.
 *
 * Gate-19 @e2e traceability:
 *   @e2e video-widget::widget-stores-nextcloud-file-reference
 *   @e2e video-widget::nextcloud-file-type-always-allowed
 *   @e2e video-widget::file-is-readable-by-viewer
 *   @e2e video-widget::file-is-not-readable-by-viewer
 *   @e2e video-widget::file-not-accessible-error
 *   @e2e video-widget::file-mime-type-is-video
 *   @e2e video-widget::file-mime-type-is-not-video
 *   @e2e video-widget::file-is-deleted
 *   @e2e video-widget::file-not-found-error
 *   @e2e video-widget::html5-video-autoplay
 *   @e2e video-widget::loop-is-applied-per-platform
 *   @e2e video-widget::controls-toggle
 *   @e2e video-widget::poster-image-fallback
 *   @e2e video-widget::all-error-messages-are-localized
 *
 * @spec openspec/specs/video-widget/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'
import {
	API,
	cellOf,
	deleteFile,
	ncFileContent,
	openVideoForm,
	openWorkspace,
	purgeTrash,
	removePlacements,
	seedVideo,
	shareFileWith,
	stubExternalPlayers,
	submitVideoForm,
	unshareFile,
	uploadFile,
	videoWidgetOf,
} from './support/videoWidget.ts'

const RUN = `e2e-video-${Date.now()}`
const CLIP = fs.readFileSync(path.join(__dirname, 'fixtures', 'tiny.webm'))
const CLIP_NAME = `${RUN}-clip.webm`
const PLACEHOLDER = '.cn-video-widget__placeholder-label'

/** A download or streaming address for a file, in any of Nextcloud's forms. */
const FILE_URL = /remote\.php\/(dav|webdav)|\/download\b|api\/v1\/files\/\d+\/content|"fileStreamingUrl"\s*:\s*"[^"]+"/

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let clipId = 0
const seeded: number[] = []
const uploaded: string[] = []

test.beforeAll(async () => {
	api = await adminContext()
	dashboard = await seedActiveDashboard(api, `E2E Video Files ${Date.now()}`)
	clipId = (await upload(CLIP_NAME, CLIP)).fileId
})

test.afterAll(async () => {
	await removePlacements(api, seeded)
	for (const name of uploaded.splice(0)) {
		await deleteFile(api, name)
	}
	await purgeTrash(api, RUN)
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
 * Upload a file into the admin's home and remember it for cleanup.
 *
 * @param name the file name.
 * @param body its bytes.
 * @return its id and MIME type.
 */
async function upload(name: string, body: Buffer): Promise<{ fileId: number, mimeType: string }> {
	const result = await uploadFile(api, name, body)
	uploaded.push(name)
	return result
}

/**
 * Seed a placement on this file's dashboard and remember it for cleanup.
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
 * The `<video>` of an nc-file placement, once the widget rendered.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the video element.
 */
async function videoOf(page: Page, placementId: number): Promise<Locator> {
	const widget = await videoWidgetOf(page, placementId)
	const video = widget.locator('video.cn-video-widget__video')
	await expect(
		video,
		`placement ${placementId} shows "${await widget.locator(PLACEHOLDER).textContent().catch(() => '')}" instead of a <video>`,
	).toBeAttached({ timeout: 15_000 })
	return video
}

/**
 * Log a principal in, point their active dashboard at this file's
 * dashboard (shared with them view-only), and open it.
 *
 * @param browser the browser.
 * @param who the principal.
 * @param placementId a placement that must be on screen.
 * @return their browser context and page.
 */
async function openAs(browser: Browser, who: Principal, placementId: number): Promise<{ context: BrowserContext, page: Page }> {
	await publishForRecipients(api, dashboard!.id)
	await shareWith(api, dashboard!.id, who.username, 'view_only')
	const active = await who.api.post(`${API}/dashboards/active`, { data: { uuid: dashboard!.uuid } })
	expect(active.status(), await active.text()).toBeLessThan(300)

	const session = await loginAs(browser, who.username, who.password)
	await stubExternalPlayers(session.page)
	await session.page.goto('/index.php/apps/launchpad')
	await expect(session.page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await expect(cellOf(session.page, placementId), `${who.username} does not see the shared placement`).toBeAttached({ timeout: 20_000 })
	return session
}

/**
 * Stop a `<video>` that cannot decode its bytes from swapping itself for the
 * "failed to load" placeholder, so the element the file service produced
 * stays inspectable. Capture-phase on `window` runs before the element's own
 * listener even though media `error` events do not bubble.
 *
 * @param page the page, before it navigates.
 * @return nothing.
 */
async function keepUndecodableVideos(page: Page): Promise<void> {
	await page.addInitScript(() => {
		window.addEventListener('error', (e) => {
			if (e.target instanceof HTMLVideoElement) {
				e.stopImmediatePropagation()
			}
		}, true)
	})
}

test.describe('video widget: storing a Nextcloud file (REQ-VID-002, REQ-VID-004)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::widget-stores-nextcloud-file-reference
	// @e2e video-widget::nextcloud-file-type-always-allowed
	test('a Nextcloud file picked in the form is stored by file id with the defaults, without a domain check', async ({ page }) => {
		await openWorkspace(page)
		const dialog = await openVideoForm(page)
		// An absolute address on THIS instance: its host is on no video
		// allow-list, so a domain check applied to nc-file would refuse it.
		await dialog.getByLabel(/^video url/i).fill(`${BASE_URL}/index.php/apps/files/?fileid=${clipId}`)
		await expect(dialog.getByText('Detected: Nextcloud File', { exact: true })).toBeVisible()
		await dialog.getByLabel(/^nextcloud file id/i).fill(String(clipId))

		const placement = await submitVideoForm(page, dialog, api, dashboard!.id)
		seeded.push(Number(placement.id))

		const content = placement.content ?? {}
		expect(content).toMatchObject({
			sourceType: 'nc-file',
			fileId: clipId,
			autoplay: false,
			muted: true,
			loop: false,
			controls: true,
			aspectRatio: '16:9',
		})
		// "`posterUrl` MUST be absent for `nc-file` type (or null)": no
		// poster was chosen, so it must hold no URL.
		expect(content.posterUrl ?? '', 'no poster URL is stored').toBe('')
	})
})

test.describe('video widget: file access (REQ-VID-006, REQ-VID-011)', () => {
	test.describe.configure({ timeout: 150_000 })

	// @e2e video-widget::file-is-readable-by-viewer
	test('the owner of a video file sees it embedded and loadable', async ({ page }) => {
		const id = await seed(ncFileContent(clipId))
		await openWorkspace(page)
		const video = await videoOf(page, id)

		const src = await video.getAttribute('src')
		expect(src, 'the video has a streaming URL').toBeTruthy()
		await expect.poll(() => video.evaluate((v: HTMLVideoElement) => v.readyState), {
			message: 'the streaming URL must serve the file (metadata loads)',
			timeout: 15_000,
		}).toBeGreaterThanOrEqual(1)
		await expect(cellOf(page, id).locator(PLACEHOLDER), 'no error placeholder').toHaveCount(0)
	})

	// @e2e video-widget::file-is-not-readable-by-viewer
	// @e2e video-widget::file-not-accessible-error
	test('a viewer who lost read access sees "Video not accessible" and no file URL reaches the page', async ({ browser }) => {
		const id = await seed(ncFileContent(clipId))
		const bob = await createPrincipal(api, 'e2e-video-bob')
		let session: { context: BrowserContext, page: Page } | null = null
		try {
			// "has lost read permission": bob could read the file, then not.
			const share = await shareFileWith(api, CLIP_NAME, bob.username)
			await unshareFile(api, share)

			const responses: string[] = []
			session = await openAs(browser, bob, id)
			session.page.on('response', async (r) => {
				if (r.url().includes('/apps/launchpad/') && (r.headers()['content-type'] ?? '').includes('json')) {
					responses.push(await r.text().catch(() => ''))
				}
			})
			await session.page.reload()
			const widget = await videoWidgetOf(session.page, id)

			await expect(widget.locator(PLACEHOLDER)).toHaveText('Video not accessible')
			await expect(widget.locator('video, iframe'), 'nothing is embedded').toHaveCount(0)
			expect(await widget.evaluate((el) => el.outerHTML), 'no file URL in the widget DOM').not.toMatch(FILE_URL)
			// Every LaunchPad response that carries this placement to bob.
			const carrying = responses.filter((body) => new RegExp(`"fileId"\\s*:\\s*${clipId}\\b`).test(body))
			expect(carrying.length, 'precondition: the placement reached bob through the API').toBeGreaterThan(0)
			for (const body of carrying) {
				expect(body, 'no download URL in the placement data sent to the viewer').not.toMatch(FILE_URL)
			}
		} finally {
			await session?.context.close()
			await removePrincipal(api, bob)
		}
	})

	// @e2e video-widget::file-mime-type-is-video
	test('files of every video MIME type the spec lists are accepted and given a streaming URL', async ({ page }) => {
		const kinds: Array<[string, string]> = [
			['mp4', 'video/mp4'],
			['webm', 'video/webm'],
			['ogv', 'video/ogg'],
			['mov', 'video/quicktime'],
			['avi', 'video/x-msvideo'],
		]
		const ids: number[] = []
		for (const [ext, mime] of kinds) {
			const file = await upload(`${RUN}-kind.${ext}`, Buffer.from(`e2e ${mime} placeholder bytes`))
			expect(file.mimeType, `precondition: Nextcloud types .${ext} as ${mime}`).toBe(mime)
			ids.push(await seed(ncFileContent(file.fileId), { w: 3, h: 3 }))
		}

		// The dummy bytes do not decode; what is under test is the file
		// service accepting the type, not the codec.
		await keepUndecodableVideos(page)
		await openWorkspace(page)
		for (const id of ids) {
			const video = await videoOf(page, id)
			expect(await video.getAttribute('src'), `placement ${id} got a streaming URL`).toBeTruthy()
			await expect(cellOf(page, id).getByText('File is not a video')).toHaveCount(0)
		}
	})

	// @e2e video-widget::file-mime-type-is-not-video
	test('a PDF is refused with "File is not a video"', async ({ page }) => {
		const pdf = await upload(`${RUN}-document.pdf`, Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'))
		expect(pdf.mimeType, 'precondition: Nextcloud types it as a PDF').toBe('application/pdf')
		const id = await seed(ncFileContent(pdf.fileId))

		await openWorkspace(page)
		const widget = await videoWidgetOf(page, id)
		await expect(widget.locator(PLACEHOLDER)).toHaveText('File is not a video')
		await expect(widget.locator('video'), 'a non-video is not embedded').toHaveCount(0)
	})

	// @e2e video-widget::file-is-deleted
	// @e2e video-widget::file-not-found-error
	test('a placement whose file was deleted shows "Video file not found" and offers removal in edit mode', async ({ page }) => {
		const name = `${RUN}-deleted.webm`
		const gone = await upload(name, CLIP)
		const id = await seed(ncFileContent(gone.fileId))
		await deleteFile(api, name)

		await openWorkspace(page)
		const widget = await videoWidgetOf(page, id)
		await expect(widget.locator(PLACEHOLDER)).toHaveText('Video file not found')
		await expect(widget.locator('video'), 'nothing is embedded').toHaveCount(0)

		await enterEditMode(page)
		await cellOf(page, id).locator('[data-testid="cn-widget-edit-cog"] button').first().click()
		await expect(
			page.getByRole('menuitem', { name: /remove widget|delete widget/i })
				.or(page.getByRole('button', { name: /remove widget|delete widget/i }))
				.first(),
			'edit mode offers to remove the broken widget',
		).toBeVisible({ timeout: 5_000 })
	})
})

test.describe('video widget: HTML5 playback options (REQ-VID-008, REQ-VID-011)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e video-widget::html5-video-autoplay
	test('an autoplaying nc-file video carries autoplay and muted, and starts playing', async ({ page }) => {
		const id = await seed(ncFileContent(clipId, { autoplay: true, muted: true }))
		await openWorkspace(page)
		const video = await videoOf(page, id)

		expect(await video.evaluate((v) => v.hasAttribute('autoplay')), 'the autoplay attribute').toBe(true)
		// `muted` is not a reflected attribute: the framework sets the DOM
		// property, which is exactly what the browser's autoplay policy reads.
		expect(await video.evaluate((v: HTMLVideoElement) => v.muted), 'the video is muted').toBe(true)
		// The two-second clip can play to its end before the first poll, after
		// which it is paused again: `played` keeps the ranges it played.
		await expect.poll(() => video.evaluate((v: HTMLVideoElement) => !v.paused || v.played.length > 0), {
			message: 'a muted autoplay video must start on its own',
			timeout: 15_000,
		}).toBe(true)
	})

	// @e2e video-widget::loop-is-applied-per-platform
	test('loop adds the loop attribute to HTML5 video and loop=1&playlist=ID to YouTube', async ({ page }) => {
		const yt = await seed({ sourceType: 'youtube', videoUrl: 'https://www.youtube.com/embed/ABC123', loop: true })
		const file = await seed(ncFileContent(clipId, { loop: true }))
		await openWorkspace(page)

		const iframe = (await videoWidgetOf(page, yt)).locator('iframe')
		const src = new URL((await iframe.getAttribute('src'))!)
		expect.soft(src.searchParams.get('loop'), 'YouTube loop=1').toBe('1')
		expect.soft(src.searchParams.get('playlist'), 'YouTube needs playlist=<id> to loop one video').toBe('ABC123')

		const video = await videoOf(page, file)
		expect(await video.evaluate((v) => v.hasAttribute('loop')), 'HTML5 loop attribute').toBe(true)
	})

	// @e2e video-widget::controls-toggle
	test('controls: false omits the controls attribute on HTML5 video; controls: true keeps it', async ({ page }) => {
		const hidden = await seed(ncFileContent(clipId, { controls: false }), { w: 4, h: 4 })
		const shown = await seed(ncFileContent(clipId, { controls: true }), { w: 4, h: 4 })
		await openWorkspace(page)

		expect(await (await videoOf(page, hidden)).evaluate((v) => v.hasAttribute('controls')), 'controls: false').toBe(false)
		expect(await (await videoOf(page, shown)).evaluate((v) => v.hasAttribute('controls')), 'controls: true').toBe(true)
	})

	// @e2e video-widget::poster-image-fallback
	test('a poster URL is the video\'s preview, and a broken one falls back to a video icon', async ({ page }) => {
		const poster = `${BASE_URL}/core/img/logo/logo.svg`
		const good = await seed(ncFileContent(clipId, { posterUrl: poster }), { w: 4, h: 4 })
		const broken = await seed(ncFileContent(clipId, { posterUrl: `${BASE_URL}/core/img/${RUN}-missing-poster.png` }), { w: 4, h: 4 })
		await openWorkspace(page)

		const video = await videoOf(page, good)
		expect(await video.getAttribute('poster')).toBe(poster)

		const fallback = await videoWidgetOf(page, broken)
		await videoOf(page, broken)
		await expect(fallback.locator('.video-icon'), 'a generic video icon replaces the broken poster').toBeVisible({ timeout: 10_000 })
		await expect(fallback.locator(PLACEHOLDER), 'a broken poster is not an error state').toHaveCount(0)
	})
})

test.describe('video widget: localisation (REQ-VID-011)', () => {
	test.describe.configure({ timeout: 180_000 })

	// @e2e video-widget::all-error-messages-are-localized
	test('the empty, inaccessible and invalid-URL messages render in Dutch for a Dutch user and in English otherwise', async ({ browser, page }) => {
		const catalogue = (locale: string): Record<string, string> =>
			JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'l10n', `${locale}.json`), 'utf-8')).translations
		const en = catalogue('en')
		const nl = catalogue('nl')

		const empty = await seed({ sourceType: null, videoUrl: '', fileId: null }, { w: 4, h: 3 })
		const locked = await seed(ncFileContent(clipId), { w: 4, h: 3 })

		// English first: the admin's own view. The form's message is read
		// from the page rather than written here, so this test does not
		// decide which wording the invalid-URL scenario requires.
		await openWorkspace(page)
		await expect((await videoWidgetOf(page, empty)).locator(PLACEHOLDER)).toHaveText('No video URL configured')
		const form = await openVideoForm(page)
		await form.getByLabel(/^video url/i).fill('not a valid URL')
		const englishAlert = form.locator('.cn-video-form [role="alert"]').first()
		await expect(englishAlert).toBeVisible()
		const invalidUrl = (await englishAlert.textContent() ?? '').trim()

		const messages = ['No video URL configured', 'Video not accessible', invalidUrl]
		for (const key of messages) {
			expect(en[key], `l10n/en.json registers "${key}"`).toBeTruthy()
			expect(nl[key], `l10n/nl.json registers "${key}"`).toBeTruthy()
			expect(nl[key], `l10n/nl.json translates "${key}"`).not.toBe(key)
		}

		const dutch = await createPrincipal(api, 'e2e-video-nl')
		let session: { context: BrowserContext, page: Page } | null = null
		let own: number | null = null
		try {
			const lang = await api.put(`/ocs/v1.php/cloud/users/${encodeURIComponent(dutch.username)}`, {
				form: { key: 'language', value: 'nl' },
			})
			expect(lang.status(), await lang.text()).toBe(200)

			// The Dutch user cannot read the admin's clip, so both placements
			// are in a message state for them.
			session = await openAs(browser, dutch, empty)
			await expect((await videoWidgetOf(session.page, empty)).locator(PLACEHOLDER)).toHaveText(nl['No video URL configured'])
			await expect((await videoWidgetOf(session.page, locked)).locator(PLACEHOLDER)).toHaveText(nl['Video not accessible'])

			// The form's own error, on a dashboard the Dutch user owns.
			const created = await dutch.api.post(`${API}/dashboard`, { data: { name: `E2E Video NL ${RUN}` } })
			expect(created.status(), await created.text()).toBeLessThan(300)
			const mine = (await created.json()).dashboard ?? {}
			own = Number(mine.id)
			const active = await dutch.api.post(`${API}/dashboards/active`, { data: { uuid: mine.uuid } })
			expect(active.status(), await active.text()).toBeLessThan(300)
			await openWorkspace(session.page)
			const dialog = await openVideoForm(session.page)
			await dialog.getByRole('textbox').first().fill('not a valid URL')
			await expect(dialog.locator('.cn-video-form [role="alert"]').first()).toHaveText(nl[invalidUrl])
		} finally {
			await session?.context.close()
			if (own !== null) {
				await dutch.api.delete(`${API}/dashboard/${own}`)
			}
			await removePrincipal(api, dutch)
		}
	})
})
