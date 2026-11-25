/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the resource-uploads capability
 * (openspec/specs/resource-uploads), written for e2e-coverage-programme
 * task 2.2.
 *
 * The capability is an HTTP contract: two admin-only upload endpoints
 * (`POST /api/resources` with a base64 data URL, `POST /api/resources/upload`
 * with a raw multipart file), the non-OCS streamer `GET /resource/{filename}`
 * and the listing `GET /api/resources`. The one place a person meets it is the
 * image widget's file picker, which uploads through the multipart endpoint;
 * that scenario is driven through the Add Widget dialog. Everything else is
 * asserted at the HTTP layer with the exact status and envelope the spec
 * names.
 *
 * The SVG sanitiser scenarios (REQ-RES-010..012) are asserted through the real
 * pipeline: the SVG is uploaded, then the persisted bytes are read back
 * through the serve route, so what is checked is what a dashboard would
 * actually render.
 *
 * Refusals are probed with a throwaway, signed-in, non-admin account over
 * basic auth (`contextFor`), the resource listing is re-read as admin to show
 * nothing was written, and the admin succeeds with the same body in the same
 * test.
 *
 * CLEAN-UP LIMIT: resources are flat app-data files with no delete endpoint
 * (appinfo/routes.php has only POST /api/resources, POST
 * /api/resources/upload, GET /api/resources and GET /resource/{filename}), so
 * the files the successful uploads create cannot be removed over HTTP. Every
 * payload is kept small except the one size-cap scenario that needs ~4.5 MB.
 * Accounts, dashboards and the allow-personal-dashboards flag are restored.
 *
 * Gate-19 @e2e traceability:
 *   @e2e resource-uploads::admin-uploads-a-png
 *   @e2e resource-uploads::non-admin-rejected
 *   @e2e resource-uploads::multipart-upload-rejected
 *   @e2e resource-uploads::disallowed-type-rejected
 *   @e2e resource-uploads::missing-data-url-prefix-rejected
 *   @e2e resource-uploads::mixed-case-declared-type-accepted
 *   @e2e resource-uploads::oversize-payload-rejected
 *   @e2e resource-uploads::mime-mismatch-rejected
 *   @e2e resource-uploads::corrupt-raster-bytes-rejected
 *   @e2e resource-uploads::storage-location
 *   @e2e resource-uploads::error-envelope-shape
 *   @e2e resource-uploads::stable-error-enum
 *   @e2e resource-uploads::serve-a-png
 *   @e2e resource-uploads::serve-an-svg-with-correct-content-type
 *   @e2e resource-uploads::missing-file-returns-404
 *   @e2e resource-uploads::path-traversal-rejected
 *   @e2e resource-uploads::unauthenticated-request-rejected
 *   @e2e resource-uploads::list-with-one-resource
 *   @e2e resource-uploads::clean-svg-accepted
 *   @e2e resource-uploads::sanitiser-strips-malicious-content-but-accepts-upload
 *   @e2e resource-uploads::unparseable-bytes-rejected
 *   @e2e resource-uploads::size-cap-measured-after-sanitisation
 *   @e2e resource-uploads::script-element-removed
 *   @e2e resource-uploads::foreignobject-and-nested-iframe-removed
 *   @e2e resource-uploads::whitelisted-elements-preserved-with-their-structure
 *   @e2e resource-uploads::event-handler-attributes-always-stripped
 *   @e2e resource-uploads::non-whitelisted-attribute-stripped
 *   @e2e resource-uploads::whitelisted-geometry-attributes-preserved
 *   @e2e resource-uploads::javascript-href-stripped
 *   @e2e resource-uploads::data-href-stripped
 *   @e2e resource-uploads::style-with-expression-stripped
 *   @e2e resource-uploads::style-with-urldata-stripped
 *   @e2e resource-uploads::safe-https-href-preserved
 *   @e2e resource-uploads::billion-laughs-entity-expansion-bounded
 *   @e2e resource-uploads::admin-uploads-a-png-file
 *   @e2e resource-uploads::missing-file-field-rejected
 *   @e2e resource-uploads::oversize-file-rejected
 *
 * @spec openspec/specs/resource-uploads/spec.md
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, request as pwRequest, test } from '@playwright/test'
import { deflateSync } from 'zlib'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { closeSidebar, gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const RUN = Date.now()

/** `POST`/`GET /api/resources` (REQ-RES-001, REQ-RES-007). */
const RESOURCES = `${API}/resources`
/** `POST /api/resources/upload` (REQ-RES-014). */
const UPLOAD = `${API}/resources/upload`
/** `ResourceService::MAX_BYTES` — the 5 MB cap of REQ-RES-003. */
const MAX_BYTES = 5 * 1024 * 1024
/** The 1-year cache directive of REQ-RES-006. */
const CACHE_CONTROL = 'public, max-age=31536000'
/** REQ-RES-004's filename pattern. */
const NAME_PATTERN = /^resource_[a-f0-9.]+\.(jpeg|jpg|png|gif|svg|webp)$/
/** The stable error enum of REQ-RES-005. */
const ERROR_ENUM = [
	'forbidden',
	'unsupported_media_type',
	'invalid_data_url',
	'invalid_image_format',
	'file_too_large',
	'mime_mismatch',
	'corrupt_image',
	'invalid_svg',
	'storage_failure',
]

/** What Nextcloud answers a non-admin on an `AuthorizedAdminSetting` route. */
const NOT_ADMIN = 'Logged in account must be an admin, a sub admin or gotten special right to access this setting'
/** What Nextcloud answers a request without a session. */
const NOT_LOGGED_IN = 'Current user is not logged in'

const SVG_NS = 'xmlns="http://www.w3.org/2000/svg"'
const XLINK_NS = 'xmlns:xlink="http://www.w3.org/1999/xlink"'

// ─────────────────────────────────────────────────────────────────────────────
// Payload builders
// ─────────────────────────────────────────────────────────────────────────────

const CRC_TABLE = (() => {
	const table = new Array<number>(256)
	for (let n = 0; n < 256; n++) {
		let c = n
		for (let k = 0; k < 8; k++) {
			c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
		}
		table[n] = c >>> 0
	}
	return table
})()

function crc32(buf: Buffer): number {
	let c = 0xffffffff
	for (const byte of buf) {
		c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8)
	}
	return (c ^ 0xffffffff) >>> 0
}

function pngChunk(type: string, data: Buffer): Buffer {
	const length = Buffer.alloc(4)
	length.writeUInt32BE(data.length)
	const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
	const crc = Buffer.alloc(4)
	crc.writeUInt32BE(crc32(body))
	return Buffer.concat([length, body, crc])
}

/**
 * A real, decodable RGB PNG of the given size, so `getimagesizefromstring`
 * on the server reports `image/png` with these dimensions.
 */
function makePng(width: number, height: number): Buffer {
	const header = Buffer.alloc(13)
	header.writeUInt32BE(width, 0)
	header.writeUInt32BE(height, 4)
	header[8] = 8 // bit depth
	header[9] = 2 // colour type RGB
	const row = Buffer.alloc(1 + width * 3)
	for (let x = 0; x < width; x++) {
		row[1 + x * 3] = (x * 7) & 0xff
		row[2 + x * 3] = 0x80
		row[3 + x * 3] = (RUN + x) & 0xff
	}
	const raw = Buffer.concat(Array.from({ length: height }, () => row))
	return Buffer.concat([
		Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
		pngChunk('IHDR', header),
		pngChunk('IDAT', deflateSync(raw)),
		pngChunk('IEND', Buffer.alloc(0)),
	])
}

/**
 * A minimal JPEG: SOI, a JFIF APP0, a 1×1 baseline SOF0 and EOI. That is all
 * `getimagesizefromstring` reads, and it reports `image/jpeg` for it.
 */
const JPEG = Buffer.from([
	0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10, 0x4a, 0x46, 0x49, 0x46, 0x00, 0x01, 0x01,
	0x00, 0x00, 0x01, 0x00, 0x01, 0x00, 0x00, 0xff, 0xc0, 0x00, 0x0b, 0x08, 0x00,
	0x01, 0x00, 0x01, 0x01, 0x01, 0x11, 0x00, 0xff, 0xd9,
])

/**
 * Bytes that are no image of any kind. Plain text, not random bytes: PHP's
 * `getimagesizefromstring` reads random bytes that start with 0x00 as WBMP
 * now and then, which turned `corrupt_image` into `mime_mismatch` at random.
 */
function garbage(size: number): Buffer {
	return Buffer.alloc(size, 'not an image ')
}

function dataUrl(type: string, bytes: Buffer | string): string {
	return `data:image/${type};base64,${Buffer.from(bytes).toString('base64')}`
}

function svgDoc(inner: string, extraNs = ''): string {
	return `<svg ${SVG_NS}${extraNs ? ` ${extraNs}` : ''}>${inner}</svg>`
}

// ─────────────────────────────────────────────────────────────────────────────
// HTTP helpers
// ─────────────────────────────────────────────────────────────────────────────

function postBase64(api: APIRequestContext, url: string): Promise<APIResponse> {
	return api.post(RESOURCES, { data: { base64: url } })
}

function postFile(
	api: APIRequestContext,
	name: string,
	buffer: Buffer,
	mimeType = 'image/png',
): Promise<APIResponse> {
	return api.post(UPLOAD, {
		multipart: { file: { name, mimeType, buffer } },
	})
}

/** GET a resource by the logical `/apps/launchpad/resource/<name>` url. */
function fetchServed(api: APIRequestContext, url: string): Promise<APIResponse> {
	return api.get(`/index.php${url}`)
}

interface Uploaded {
	url: string
	name: string
	size: number
}

/** Assert REQ-RES-001's success envelope and return it. */
async function expectSuccess(res: APIResponse, ext: string): Promise<Uploaded> {
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.status).toBe('success')
	expect(body.name).toMatch(NAME_PATTERN)
	expect(body.name.endsWith(`.${ext}`), body.name).toBe(true)
	expect(body.url).toBe(`/apps/launchpad/resource/${body.name}`)
	expect(Number.isInteger(body.size)).toBe(true)
	return { url: body.url, name: body.name, size: body.size }
}

/** Assert REQ-RES-005's error envelope with an exact status and code. */
async function expectError(
	res: APIResponse,
	status: number,
	code: string,
	message?: string,
): Promise<{ status: string; error: string; message: string }> {
	const text = await res.text()
	expect(res.status(), text).toBe(status)
	const body = JSON.parse(text)
	expect(body.status, text).toBe('error')
	expect(body.error, text).toBe(code)
	expect(typeof body.message, text).toBe('string')
	expect(body.message.length, text).toBeGreaterThan(0)
	if (message !== undefined) {
		expect(body.message).toBe(message)
	}
	return body
}

/**
 * Assert Nextcloud's own refusal: its status and `{message}` body, sent before
 * LaunchPad's controller runs.
 *
 * @param res the response.
 * @param status the expected status, 401 or 403.
 * @param message Nextcloud's message.
 */
async function expectNextcloudRefusal(
	res: APIResponse,
	status: number,
	message: string,
): Promise<void> {
	const text = await res.text()
	expect(res.status(), text).toBe(status)
	expect(JSON.parse(text), text).toEqual({ message })
}

/** Every resource name the listing reports, read as admin. */
async function resourceNames(api: APIRequestContext): Promise<Set<string>> {
	const res = await api.get(RESOURCES)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return new Set(
		(body.resources ?? []).map((entry: { name: string }) => entry.name),
	)
}

/** Fail when the resources folder gained a file since `before` was taken. */
async function expectNothingWritten(
	api: APIRequestContext,
	before: Set<string>,
): Promise<void> {
	const after = await resourceNames(api)
	const added = [...after].filter((name) => !before.has(name))
	expect(added, 'a refused upload wrote a resource file').toEqual([])
}

/** Upload an SVG as admin and return what the serve route hands back. */
async function sanitiseThroughUpload(
	api: APIRequestContext,
	svg: string,
): Promise<string> {
	const uploaded = await expectSuccess(
		await postBase64(api, dataUrl('svg+xml', svg)),
		'svg',
	)
	const served = await fetchServed(api, uploaded.url)
	expect(served.status()).toBe(200)
	return served.text()
}

/** The first start tag of `element` in `svg`, or fail. */
function tagOf(svg: string, element: string): string {
	const match = svg.match(new RegExp(`<${element}\\b[^>]*>`, 'i'))
	expect(match, `no <${element}> in ${svg}`).not.toBeNull()
	return (match as RegExpMatchArray)[0]
}

// ─────────────────────────────────────────────────────────────────────────────
// Suite
// ─────────────────────────────────────────────────────────────────────────────

let admin: APIRequestContext

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
})

test.afterAll(async () => {
	await admin?.dispose()
})

test.describe('resource-uploads: base64 upload (REQ-RES-001..005)', () => {
	test('an admin uploads a 200×200 PNG and the file is served back byte for byte', async () => {
		// @e2e resource-uploads::admin-uploads-a-png
		const png = makePng(200, 200)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('png', png)),
			'png',
		)
		expect(uploaded.size).toBe(png.length)

		// A file exists at the app-data path the url names.
		const served = await fetchServed(admin, uploaded.url)
		expect(served.status()).toBe(200)
		expect(Buffer.compare(await served.body(), png)).toBe(0)
	})

	test('a signed-in non-admin is refused with Nextcloud\'s 403 and writes nothing', async () => {
		// @e2e resource-uploads::non-admin-rejected
		test.setTimeout(120_000)
		let user: Principal | null = null
		try {
			user = await createPrincipal(admin, `res-upl-${RUN}`)
			await assertActingAs(user.api, user.username)
			const body = dataUrl('png', makePng(8, 8))
			const before = await resourceNames(admin)

			await expectNextcloudRefusal(await postBase64(user.api, body), 403, NOT_ADMIN)
			await expectNothingWritten(admin, before)

			// The allowed principal succeeds with the same body.
			await expectSuccess(await postBase64(admin, body), 'png')
		} finally {
			await removePrincipal(admin, user)
		}
	})

	test('a multipart body on the base64 endpoint is refused with 415', async () => {
		// @e2e resource-uploads::multipart-upload-rejected
		const res = await admin.post(RESOURCES, {
			multipart: { base64: dataUrl('png', makePng(8, 8)) },
		})
		await expectError(
			res,
			415,
			'unsupported_media_type',
			'Use JSON body with base64 field',
		)
	})

	test('a disallowed declared type is refused with invalid_image_format', async () => {
		// @e2e resource-uploads::disallowed-type-rejected
		const before = await resourceNames(admin)
		// A real BMP header, so only the declared type can be the reason.
		const bmp = Buffer.concat([Buffer.from('BM', 'ascii'), Buffer.alloc(60)])
		await expectError(
			await postBase64(admin, dataUrl('bmp', bmp)),
			400,
			'invalid_image_format',
		)
		await expectNothingWritten(admin, before)
	})

	test('raw base64 without a data: prefix is refused with invalid_data_url', async () => {
		// @e2e resource-uploads::missing-data-url-prefix-rejected
		const before = await resourceNames(admin)
		await expectError(
			await postBase64(admin, makePng(8, 8).toString('base64')),
			400,
			'invalid_data_url',
		)
		await expectNothingWritten(admin, before)
	})

	test('an uppercase declared type is treated as png and persisted as .png', async () => {
		// @e2e resource-uploads::mixed-case-declared-type-accepted
		const png = makePng(16, 16)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('PNG', png)),
			'png',
		)
		expect(uploaded.name).toMatch(/\.png$/)
		const served = await fetchServed(admin, uploaded.url)
		expect(served.status()).toBe(200)
		expect(served.headers()['content-type']).toMatch(/^image\/png(;|$)/)
		expect(Buffer.compare(await served.body(), png)).toBe(0)
	})

	test('a payload decoding to 6 MB is refused before the image library sees it', async () => {
		// @e2e resource-uploads::oversize-payload-rejected
		test.setTimeout(120_000)
		// Control: the same kind of garbage, small enough to reach the image
		// library, is refused as corrupt.
		await expectError(
			await postBase64(admin, dataUrl('png', garbage(4096))),
			400,
			'corrupt_image',
		)

		// Over the cap, the same bytes are refused as too large instead. Had
		// `getimagesizefromstring` run first, this would read corrupt_image
		// too, so the code proves the size check came first.
		const before = await resourceNames(admin)
		await expectError(
			await postBase64(admin, dataUrl('png', garbage(6 * 1024 * 1024))),
			400,
			'file_too_large',
			'Maximum size is 5MB',
		)
		await expectNothingWritten(admin, before)
	})

	test('a JPEG declared as png is refused with mime_mismatch', async () => {
		// @e2e resource-uploads::mime-mismatch-rejected
		// Control: the same bytes declared as what they are are accepted, so
		// the server's image library does read them as image/jpeg.
		await expectSuccess(await postBase64(admin, dataUrl('jpeg', JPEG)), 'jpeg')

		const before = await resourceNames(admin)
		await expectError(
			await postBase64(admin, dataUrl('png', JPEG)),
			400,
			'mime_mismatch',
		)
		await expectNothingWritten(admin, before)
	})

	test('garbage bytes declared as png are refused with corrupt_image', async () => {
		// @e2e resource-uploads::corrupt-raster-bytes-rejected
		const before = await resourceNames(admin)
		await expectError(
			await postBase64(admin, dataUrl('png', garbage(512))),
			400,
			'corrupt_image',
		)
		await expectNothingWritten(admin, before)
	})

	test('an upload lands in app data and not in the admin’s own files', async () => {
		// @e2e resource-uploads::storage-location
		const png = makePng(12, 12)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('png', png)),
			'png',
		)
		expect(uploaded.name).toMatch(NAME_PATTERN)

		// Served by the app-data streamer, which reads only
		// `<appdata>/resources/` (ResourceServeService::findFile).
		const served = await fetchServed(admin, uploaded.url)
		expect(served.status()).toBe(200)
		expect(Buffer.compare(await served.body(), png)).toBe(0)

		// Not anywhere in the admin's Files. Positive control first: a file
		// that IS in the admin's Files is found by the same search, so an
		// empty search result is not a broken search.
		const probe = `resource_probe_${RUN}.txt`
		const probePath = `/remote.php/dav/files/${encodeURIComponent(ADMIN_USER)}/${probe}`
		const put = await admin.put(probePath, { data: 'probe' })
		expect([201, 204], await put.text()).toContain(put.status())
		try {
			const searchFor = (name: string) =>
				admin.fetch('/remote.php/dav/', {
					method: 'SEARCH',
					headers: { 'Content-Type': 'text/xml' },
					data:
						'<?xml version="1.0" encoding="UTF-8"?>'
						+ '<d:searchrequest xmlns:d="DAV:"><d:basicsearch>'
						+ '<d:select><d:prop><d:displayname/></d:prop></d:select>'
						+ `<d:from><d:scope><d:href>/files/${ADMIN_USER}</d:href>`
						+ '<d:depth>infinity</d:depth></d:scope></d:from>'
						+ '<d:where><d:like><d:prop><d:displayname/></d:prop>'
						+ `<d:literal>${name}</d:literal></d:like></d:where>`
						+ '</d:basicsearch></d:searchrequest>',
				})

			const control = await searchFor(probe)
			expect(control.status()).toBe(207)
			expect(await control.text()).toContain(probe)

			const search = await searchFor(uploaded.name)
			expect(search.status()).toBe(207)
			expect(await search.text()).not.toContain(uploaded.name)
		} finally {
			await admin.delete(probePath)
		}
	})

	test('every rejection answers status/error/message with a documented code and no leaked internals', async () => {
		// @e2e resource-uploads::error-envelope-shape
		// @e2e resource-uploads::stable-error-enum
		test.setTimeout(180_000)
		// storage_failure cannot be provoked over HTTP; its envelope is pinned by
		// ResourceControllerTest::testUnexpectedThrowableIsMaskedAsStorageFailure.
		// A non-admin is refused by Nextcloud before LaunchPad answers, in
		// Nextcloud's own shape; the refusal tests below pin that.
		const probes: Array<[string, number, Promise<APIResponse>]> = [
			[
				'unsupported_media_type',
				415,
				admin.post(RESOURCES, { multipart: { base64: 'x' } }),
			],
			['invalid_data_url', 400, postBase64(admin, 'not a data url')],
			[
				'invalid_image_format',
				400,
				postBase64(admin, dataUrl('tiff', makePng(8, 8))),
			],
			[
				'file_too_large',
				400,
				postBase64(admin, dataUrl('png', garbage(MAX_BYTES + 1))),
			],
			['mime_mismatch', 400, postBase64(admin, dataUrl('gif', JPEG))],
			[
				'corrupt_image',
				400,
				postBase64(admin, dataUrl('webp', garbage(256))),
			],
			[
				'invalid_svg',
				400,
				postBase64(admin, dataUrl('svg+xml', '<svg><unclosed')),
			],
		]

		for (const [code, status, pending] of probes) {
			const res = await pending
			const text = await res.text()
			expect(res.status(), `${code}: ${text}`).toBe(status)
			const body = JSON.parse(text)
			expect(Object.keys(body), `${code}: ${text}`).toEqual(
				expect.arrayContaining(['status', 'error', 'message']),
			)
			expect(body.status).toBe('error')
			expect(ERROR_ENUM, `${code}: ${text}`).toContain(body.error)
			expect(body.error).toBe(code)
			expect(typeof body.message).toBe('string')
			expect(text, `${code} leaked internals`).not.toMatch(
				/Stack trace|Exception|\.php|#\d+ \/|libxml|DOMDocument/i,
			)
		}
	})
})

test.describe('resource-uploads: serving and listing (REQ-RES-006, REQ-RES-007)', () => {
	test('a PNG is served with image/png, a one-year cache and its exact bytes', async () => {
		// @e2e resource-uploads::serve-a-png
		test.setTimeout(120_000)
		const png = makePng(20, 20)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('png', png)),
			'png',
		)

		// "any authenticated user" — a non-admin, not only the uploader.
		let user: Principal | null = null
		try {
			user = await createPrincipal(admin, `res-srv-${RUN}`)
			await assertActingAs(user.api, user.username)
			for (const api of [admin, user.api]) {
				const res = await fetchServed(api, uploaded.url)
				expect(res.status()).toBe(200)
				expect(res.headers()['content-type']).toMatch(/^image\/png(;|$)/)
				expect(res.headers()['cache-control']).toBe(CACHE_CONTROL)
				expect(Buffer.compare(await res.body(), png)).toBe(0)
			}
		} finally {
			await removePrincipal(admin, user)
		}
	})

	test('an SVG is served as image/svg+xml', async () => {
		// @e2e resource-uploads::serve-an-svg-with-correct-content-type
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('svg+xml', svgDoc('<circle r="3"/>'))),
			'svg',
		)
		const res = await fetchServed(admin, uploaded.url)
		expect(res.status()).toBe(200)
		const type = res.headers()['content-type']
		expect(type).toMatch(/^image\/svg\+xml(;|$)/)
		expect(type).not.toContain('application/svg+xml')
	})

	test('a missing file is a bare 404', async () => {
		// @e2e resource-uploads::missing-file-returns-404
		const res = await admin.get(
			'/index.php/apps/launchpad/resource/does-not-exist.png',
		)
		expect(res.status()).toBe(404)
		expect(['', '[]', '{}']).toContain((await res.text()).trim())
	})

	test('an encoded ../../etc/passwd is a 404 and never system file contents', async () => {
		// @e2e resource-uploads::path-traversal-rejected
		const res = await admin.get(
			'/index.php/apps/launchpad/resource/..%2F..%2Fetc%2Fpasswd',
		)
		expect(res.status()).toBe(404)
		expect(await res.text()).not.toMatch(/root:.*:0:0:/)
	})

	test('a logged-out browser is sent to login and gets no resource bytes', async ({
		browser,
	}) => {
		// @e2e resource-uploads::unauthenticated-request-rejected
		const png = makePng(10, 10)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('png', png)),
			'png',
		)
		// Control: the resource exists and is served to a signed-in user.
		expect((await fetchServed(admin, uploaded.url)).status()).toBe(200)

		const context = await browser.newContext({
			storageState: { cookies: [], origins: [] },
		})
		try {
			const page = await context.newPage()
			const response = await page.goto(`${BASE_URL}/index.php${uploaded.url}`)
			expect(page.url()).toMatch(/\/login/)
			expect(response).not.toBeNull()
			const body = await (response as NonNullable<typeof response>).body()
			expect(body.subarray(0, 4).toString('hex')).not.toBe('89504e47')
			expect(body.includes(png)).toBe(false)
		} finally {
			await context.close()
		}
	})

	test('the listing reports an uploaded file with name, url, size and an ISO modifiedAt', async () => {
		// @e2e resource-uploads::list-with-one-resource
		const png = makePng(24, 24)
		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('png', png)),
			'png',
		)

		const res = await admin.get(RESOURCES)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(body.status).toBe('success')
		const resources: Array<{
			name: string
			url: string
			size: number
			modifiedAt: string
		}> = body.resources
		const entry = resources.find((r) => r.name === uploaded.name)
		expect(entry, `${uploaded.name} missing from the listing`).toBeDefined()
		expect(entry).toEqual({
			name: uploaded.name,
			url: `/apps/launchpad/resource/${uploaded.name}`,
			size: png.length,
			modifiedAt: expect.stringMatching(
				/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/,
			),
		})

		// The instance is shared, so other resources may exist; the listing is
		// newest first and the file just uploaded carries the newest stamp,
		// i.e. it is what resources[0] is (ties at second precision aside).
		const stamps = resources.map((r) => Date.parse(r.modifiedAt))
		expect(stamps.every((s) => !Number.isNaN(s))).toBe(true)
		expect([...stamps].sort((a, b) => b - a)).toEqual(stamps)
		expect(Date.parse((entry as { modifiedAt: string }).modifiedAt)).toBe(
			stamps[0],
		)
	})
})

test.describe('resource-uploads: SVG pipeline (REQ-RES-009, REQ-RES-013)', () => {
	test('a clean SVG is accepted and its sanitised serialisation is what is persisted', async () => {
		// @e2e resource-uploads::clean-svg-accepted
		// The endpoint is admin-only (REQ-RES-001), so "alice" is an admin.
		const original =
			'<?xml version="1.0" encoding="UTF-8"?>\n'
			+ svgDoc('<circle cx="5" cy="5" r="5" fill="blue"/>')
		const res = await postBase64(admin, dataUrl('svg+xml', original))
		const uploaded = await expectSuccess(res, 'svg')
		expect(uploaded.url).toMatch(
			/^\/apps\/launchpad\/resource\/resource_[a-f0-9.]+\.svg$/,
		)

		const served = await (await fetchServed(admin, uploaded.url)).text()
		// The sanitiser serialises the root element only, so the XML prolog
		// of the original is gone: the persisted bytes are its output.
		expect(served).not.toBe(original)
		expect(served.startsWith('<?xml')).toBe(false)
		expect(tagOf(served, 'circle')).toMatch(/\br="5"/)
		expect(uploaded.size).toBe(Buffer.byteLength(served))
	})

	test('a script inside an SVG is stripped and the upload still succeeds', async () => {
		// @e2e resource-uploads::sanitiser-strips-malicious-content-but-accepts-upload
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<script>alert(1)</script><circle r="5"/>'),
		)
		expect(served).not.toContain('<script')
		expect(served).not.toContain('alert')
		expect(tagOf(served, 'circle')).toMatch(/\br="5"/)
	})

	test('non-XML bytes declared as SVG are refused with invalid_svg and write nothing', async () => {
		// @e2e resource-uploads::unparseable-bytes-rejected
		const before = await resourceNames(admin)
		const body = await expectError(
			await postBase64(admin, dataUrl('svg+xml', garbage(300))),
			400,
			'invalid_svg',
		)
		expect(body.status).toBe('error')
		await expectNothingWritten(admin, before)
	})

	test('a 5.8 MB SVG that sanitises below 5 MB is accepted', async () => {
		// @e2e resource-uploads::size-cap-measured-after-sanitisation
		test.setTimeout(180_000)
		const kept = 'a'.repeat(4_500_000)
		const stripped = 'b'.repeat(1_300_000)
		const svg = svgDoc(`<desc>${kept}</desc><script>${stripped}</script>`)
		expect(Buffer.byteLength(svg)).toBeGreaterThan(MAX_BYTES)

		const uploaded = await expectSuccess(
			await postBase64(admin, dataUrl('svg+xml', svg)),
			'svg',
		)
		expect(uploaded.size).toBeLessThanOrEqual(MAX_BYTES)
		expect(uploaded.size).toBeGreaterThanOrEqual(kept.length)

		const served = await (await fetchServed(admin, uploaded.url)).text()
		expect(Buffer.byteLength(served)).toBe(uploaded.size)
		expect(served).not.toContain('bbbb')
		expect(served).toContain(kept)
	})

	test('a billion-laughs SVG returns promptly with an envelope instead of exhausting memory', async () => {
		// @e2e resource-uploads::billion-laughs-entity-expansion-bounded
		const levels = ['<!ENTITY lol0 "lol">']
		for (let i = 1; i <= 9; i++) {
			levels.push(`<!ENTITY lol${i} "${`&lol${i - 1};`.repeat(10)}">`)
		}
		const svg =
			'<?xml version="1.0"?>'
			+ `<!DOCTYPE svg [${levels.join('')}]>`
			+ svgDoc('<title>&lol9;</title><circle r="1"/>')

		const started = Date.now()
		const res = await postBase64(admin, dataUrl('svg+xml', svg))
		const elapsed = Date.now() - started
		const text = await res.text()

		// A memory-exhausted worker answers 500 (or not at all); a bounded
		// parse answers the envelope: accepted, or refused as invalid_svg.
		expect(elapsed, 'the parse did not return in bounded time').toBeLessThan(
			15_000,
		)
		expect([200, 400], text).toContain(res.status())
		const body = JSON.parse(text)
		expect(body.status).toBe(res.status() === 200 ? 'success' : 'error')
		if (res.status() === 400) {
			expect(body.error).toBe('invalid_svg')
		} else {
			// Nothing was amplified into the persisted file.
			expect(body.size).toBeLessThan(64 * 1024)
		}
	})
})

test.describe('resource-uploads: SVG whitelist (REQ-RES-010..012)', () => {
	test('<script> is removed and <circle r="5"/> survives', async () => {
		// @e2e resource-uploads::script-element-removed
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<script>alert(1)</script><circle r="5"/>'),
		)
		expect(served).toMatch(/<circle\b[^>]*\br="5"[^>]*\/>/)
		expect(served).not.toContain('<script')
		expect(served).not.toContain('alert')
	})

	test('<foreignObject> and its nested <iframe> are removed', async () => {
		// @e2e resource-uploads::foreignobject-and-nested-iframe-removed
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc(
				'<foreignObject><iframe src="http://attacker"/></foreignObject>'
					+ '<circle r="2"/>',
			),
		)
		expect(served.toLowerCase()).not.toContain('foreignobject')
		expect(served.toLowerCase()).not.toContain('iframe')
		expect(served).not.toContain('attacker')
		expect(served).toContain('<circle')
	})

	test('whitelisted <defs>/<linearGradient>/<g>/<path> keep their nesting', async () => {
		// @e2e resource-uploads::whitelisted-elements-preserved-with-their-structure
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc(
				'<defs><linearGradient id="lg"><stop offset="0" stop-color="red"/>'
					+ '</linearGradient></defs>'
					+ '<g id="grp"><path d="M0 0 L10 10" fill="url(#lg)"/></g>',
			),
		)
		expect(served).toMatch(
			/<defs>\s*<linearGradient\b[^>]*id="lg"[^>]*>\s*<stop\b[^>]*\/>\s*<\/linearGradient>\s*<\/defs>/i,
		)
		expect(served).toMatch(
			/<g\b[^>]*id="grp"[^>]*>\s*<path\b[^>]*d="M0 0 L10 10"[^>]*\/>\s*<\/g>/,
		)
	})

	test('onclick and onload are stripped while r="5" stays on the same <circle>', async () => {
		// @e2e resource-uploads::event-handler-attributes-always-stripped
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<circle onclick="alert(1)" onload="x()" r="5"/>'),
		)
		const circle = tagOf(served, 'circle')
		expect(circle).not.toMatch(/onclick|onload/i)
		expect(circle).toMatch(/\br="5"/)
	})

	test('a non-whitelisted data-payload attribute is stripped and r="5" stays', async () => {
		// @e2e resource-uploads::non-whitelisted-attribute-stripped
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<circle data-payload="x" r="5"/>'),
		)
		const circle = tagOf(served, 'circle')
		expect(circle).not.toContain('data-payload')
		expect(circle).toMatch(/\br="5"/)
	})

	test('all six geometry attributes of a <rect> are preserved', async () => {
		// @e2e resource-uploads::whitelisted-geometry-attributes-preserved
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc(
				'<rect x="10" y="20" width="100" height="50" fill="red" stroke="black"/>',
			),
		)
		const rect = tagOf(served, 'rect')
		for (const attr of [
			'x="10"',
			'y="20"',
			'width="100"',
			'height="50"',
			'fill="red"',
			'stroke="black"',
		]) {
			expect(rect).toMatch(new RegExp(`(\\s)${attr}`))
		}
	})

	test('a javascript: xlink:href is removed and the <use> element stays', async () => {
		// @e2e resource-uploads::javascript-href-stripped
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<use xlink:href="javascript:alert(1)"/>', XLINK_NS),
		)
		const use = tagOf(served, 'use')
		expect(use).not.toMatch(/href=/)
		expect(served).not.toContain('javascript:')
	})

	test('a data: href is removed and the <image> element stays', async () => {
		// @e2e resource-uploads::data-href-stripped
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<image href="data:image/svg+xml;base64,PHN2Zy8+"/>'),
		)
		const image = tagOf(served, 'image')
		expect(image).not.toMatch(/href=/)
		expect(served).not.toContain('data:image')
	})

	test('a style with expression() is removed entirely and width/height stay', async () => {
		// @e2e resource-uploads::style-with-expression-stripped
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc(
				'<rect style="width: expression(alert(1))" width="10" height="10"/>',
			),
		)
		const rect = tagOf(served, 'rect')
		expect(rect).not.toContain('style=')
		expect(rect).toMatch(/\swidth="10"/)
		expect(rect).toMatch(/\sheight="10"/)
		expect(served).not.toContain('expression(')
	})

	test('a style with url(data:) is removed entirely and the <g>/<path> stay', async () => {
		// @e2e resource-uploads::style-with-urldata-stripped
		// `<` must be escaped inside an XML attribute; the parsed value is the
		// scenario's literal `url(data:text/html,<script>alert(1)</script>)`.
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc(
				'<g style="background: url(data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;)">'
					+ '<path d="M0 0"/></g>',
			),
		)
		const g = tagOf(served, 'g')
		expect(g).not.toContain('style=')
		expect(served).toMatch(/<g\b[^>]*>\s*<path\b[^>]*d="M0 0"[^>]*\/>\s*<\/g>/)
		expect(served).not.toContain('url(data:')
	})

	test('an https href passes through unchanged', async () => {
		// @e2e resource-uploads::safe-https-href-preserved
		const served = await sanitiseThroughUpload(
			admin,
			svgDoc('<image href="https://example.com/logo.png"/>'),
		)
		expect(tagOf(served, 'image')).toContain(
			'href="https://example.com/logo.png"',
		)
	})
})

test.describe('resource-uploads: multipart upload (REQ-RES-014)', () => {
	test.describe('through the image widget', () => {
		let seeded: SeededDashboard | null = null
		let priorAllowUserDash: boolean | null = null

		let restoreWidgetRestriction: (() => Promise<void>) | null = null

		test.beforeAll(async () => {
			restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
			const settings = await admin.get(`${API}/admin/settings`)
			expect(settings.status(), await settings.text()).toBe(200)
			priorAllowUserDash = (await settings.json()).allowUserDashboards === true
			seeded = await seedActiveDashboard(admin, `E2E resource upload ${RUN}`)
		})

		test.afterAll(async () => {
			await restoreWidgetRestriction?.()
		})

		test.afterAll(async () => {
			await removeSeededDashboard(admin, seeded)
			if (priorAllowUserDash === false) {
				await admin.put(`${API}/admin/settings`, {
					data: { allowUserDash: false },
				})
			}
		})

		test('the image widget sends the picked PNG as raw multipart and renders the hosted file', async ({
			page,
		}) => {
			// @e2e resource-uploads::admin-uploads-a-png-file
			test.setTimeout(120_000)
			const png = makePng(200, 200)

			await gotoLaunchPad(page)
			await openSidebar(page)
			const row = page.locator(
				'[data-source="user"].dashboard-switcher-sidebar__item',
				{ hasText: (seeded as SeededDashboard).name },
			)
			await expect(row).toBeVisible({ timeout: 8_000 })
			await row.locator('.dashboard-row-actions button').first().click()
			await page.getByRole('menuitem', { name: /^add widget$/i }).click()
			await chooseAndAdd(page.locator('[data-testid="widget-picker-type-image"]'))
			const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
			await expect(dialog).toBeVisible({ timeout: 10_000 })
			await dialog.locator('.cn-image-widget-form__file-input').setInputFiles({
				name: 'square.png',
				mimeType: 'image/png',
				buffer: png,
			})

			const uploadRequest = page.waitForRequest(
				(req) =>
					req.method() === 'POST'
					&& req.url().includes('/api/resources/upload'),
			)
			const uploadResponse = page.waitForResponse(
				(res) =>
					res.request().method() === 'POST'
					&& res.url().includes('/api/resources/upload'),
			)
			const addBtn = dialog.getByRole('button', { name: /^add$/i })
			await expect(addBtn).toBeEnabled({ timeout: 5_000 })
			await addBtn.click()

			// The widget posts multipart to the raw endpoint, not JSON base64.
			const req = await uploadRequest
			expect(await req.headerValue('content-type')).toMatch(
				/^multipart\/form-data; boundary=/,
			)

			const res = await uploadResponse
			expect(res.status()).toBe(200)
			const body = await res.json()
			expect(body.status).toBe('success')
			expect(body.name).toMatch(/^resource_[a-f0-9.]+\.png$/)
			expect(body.url).toBe(`/apps/launchpad/resource/${body.name}`)
			// This endpoint stores the multipart bytes as received, with no
			// decoding step: a size and content equal to the raw PNG prove the
			// file travelled as raw bytes and not as a base64 string.
			expect(body.size).toBe(png.length)
			const served = await fetchServed(admin, body.url)
			expect(served.status()).toBe(200)
			expect(Buffer.compare(await served.body(), png)).toBe(0)

			await expect(dialog).not.toBeVisible({ timeout: 8_000 })
			await closeSidebar(page)
			await expect(
				page.locator(`.cn-image-widget__img[src*="${body.name}"]`).first(),
			).toBeVisible({ timeout: 8_000 })
		})
	})

	test('a signed-in non-admin posting a file is refused with Nextcloud\'s 403 and writes nothing', async () => {
		// @e2e resource-uploads::non-admin-rejected
		test.setTimeout(120_000)
		let user: Principal | null = null
		try {
			user = await createPrincipal(admin, `res-mp-${RUN}`)
			await assertActingAs(user.api, user.username)
			const png = makePng(8, 8)
			const before = await resourceNames(admin)

			await expectNextcloudRefusal(
				await postFile(user.api, 'x.png', png),
				403,
				NOT_ADMIN,
			)
			await expectNothingWritten(admin, before)

			await expectSuccess(await postFile(admin, 'x.png', png), 'png')
		} finally {
			await removePrincipal(admin, user)
		}
	})

	test('an anonymous file post is refused with Nextcloud\'s 401 and writes nothing', async () => {
		// @e2e resource-uploads::unauthenticated-request-rejected
		const anonymous = await pwRequest.newContext({
			baseURL: BASE_URL,
			storageState: { cookies: [], origins: [] },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})
		try {
			const png = makePng(8, 8)
			const before = await resourceNames(admin)

			await expectNextcloudRefusal(
				await postFile(anonymous, 'x.png', png),
				401,
				NOT_LOGGED_IN,
			)
			await expectNothingWritten(admin, before)

			await expectSuccess(await postFile(admin, 'x.png', png), 'png')
		} finally {
			await anonymous.dispose()
		}
	})

	test('a multipart post without a file field is refused with no_file', async () => {
		// @e2e resource-uploads::missing-file-field-rejected
		const before = await resourceNames(admin)
		const res = await admin.post(UPLOAD, {
			multipart: {
				image: {
					name: 'x.png',
					mimeType: 'image/png',
					buffer: makePng(8, 8),
				},
			},
		})
		await expectError(res, 400, 'no_file')
		await expectNothingWritten(admin, before)
	})

	test('a file over 5 MB is refused with file_too_large and writes nothing', async () => {
		// @e2e resource-uploads::oversize-file-rejected
		test.setTimeout(120_000)
		const before = await resourceNames(admin)
		await expectError(
			await postFile(admin, 'big.png', garbage(MAX_BYTES + 1024)),
			400,
			'file_too_large',
		)
		await expectNothingWritten(admin, before)
	})
})
