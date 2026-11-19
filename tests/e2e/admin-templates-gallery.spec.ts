/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The template gallery, the template metadata fields and the preview-image
 * upload (admin-templates REQ-TMPL-014, -016, -017). All three are HTTP
 * contracts, so they are driven through the API: the gallery as an ordinary
 * signed-in account, the upload as the admin, and its refusal as an ordinary
 * account re-read by the admin.
 *
 * The instance is shared, so other templates may be in the gallery. Every
 * category this file uses carries its run stamp, and the sort assertions are
 * made on this file's templates' order within the whole answer.
 *
 * `templateCategory` and `templateDescription` have no writer in the
 * template API (PUT /api/admin/templates takes neither); the dashboard
 * importer sets them (REQ-EXIM-012), so that is how they are seeded.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::list-all-templates-in-gallery
 *   @e2e admin-templates::filter-gallery-by-category
 *   @e2e admin-templates::default-sort-order
 *   @e2e admin-templates::sort-by-recency
 *   @e2e admin-templates::gallery-includes-category-null-templates
 *   @e2e admin-templates::template-metadata-in-gallery-response
 *   @e2e admin-templates::metadata-persists-across-updates
 *   @e2e admin-templates::metadata-fields-are-nullable
 *   @e2e admin-templates::upload-preview-image-for-a-template
 *   @e2e admin-templates::non-admin-cannot-upload-preview-image
 *   @e2e admin-templates::upload-replaces-previous-preview-image
 *   @e2e admin-templates::invalid-file-format-is-rejected
 *   @e2e admin-templates::preview-image-url-in-gallery-response
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { readFileSync } from 'fs'
import * as path from 'path'
import {
	createTemplate,
	deleteTemplates,
	getTemplate,
	importTemplate,
	listTemplates,
	textWidget,
} from './support/adminTemplates.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E Gallery ${RUN}`
const PNG = readFileSync(path.join(__dirname, 'fixtures', 'tiny.png'))
const PNG_URL = `data:image/png;base64,${PNG.toString('base64')}`
const GIF_URL =
	'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
const SVG_URL = `data:image/svg+xml;base64,${Buffer.from(
	'<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1"/></svg>',
).toString('base64')}`
const PDF_URL = `data:application/pdf;base64,${Buffer.from('%PDF-1.4\n%%EOF\n').toString('base64')}`
const RESOURCE_URL =
	/^\/apps\/launchpad\/resource\/resource_[0-9a-f]{13}[0-9.]+\.png$/

interface GalleryEntry {
	uuid: string
	name: string
	description: string | null
	category: string | null
	previewImage: string | null
	gridColumns: number
	widgetCount: number
	lastUpdatedAt: string
	[key: string]: unknown
}

let admin: APIRequestContext
let alice: Principal | null = null
const made: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	alice = await createPrincipal(admin, 'e2e-gallery')
	await assertActingAs(alice.api, alice.username)
})

test.afterAll(async () => {
	await deleteTemplates(admin, made)
	await removePrincipal(admin, alice)
	await admin.dispose()
})

/** Import a template this file owns. */
async function seed(
	name: string,
	options: Parameters<typeof importTemplate>[2] = {},
): Promise<TemplateRow> {
	const row = await importTemplate(admin, `${STAMP} ${name}`, options)
	made.push(row.id)
	return row
}

/** The gallery as the ordinary account sees it. */
async function gallery(
	query = '',
): Promise<{ status: number; entries: GalleryEntry[]; body: any }> {
	const res = await alice!.api.get(`${API}/templates/gallery${query}`)
	const body = await res.json()
	return { status: res.status(), entries: body.templates ?? [], body }
}

/** Only this file's entries, in the order the gallery gave them. */
function ours(entries: GalleryEntry[]): GalleryEntry[] {
	return entries.filter((e) => e.name.startsWith(STAMP))
}

/** Upload a preview image as the admin. */
async function upload(uuid: string, base64: string, as: APIRequestContext = admin) {
	return as.post(`${API}/admin/templates/${uuid}/preview-image`, {
		data: { base64 },
	})
}

const GALLERY_KEYS = [
	'category',
	'description',
	'gridColumns',
	'lastUpdatedAt',
	'name',
	'previewImage',
	'uuid',
	'widgetCount',
]

test.describe('the gallery (REQ-TMPL-014)', () => {
	// @e2e admin-templates::list-all-templates-in-gallery
	test('a signed-in account lists every template with the gallery fields only', async () => {
		const marketing = await seed('List marketing', {
			templateCategory: `e2e-mkt-${RUN}`,
			widgets: [
				textWidget(`${STAMP} l1`, { x: 0, y: 0 }, { isCompulsory: 1 }),
				textWidget(`${STAMP} l2`, { x: 4, y: 0 }),
			],
		})
		const engineering = await seed('List engineering', {
			templateCategory: `e2e-eng-${RUN}`,
			widgets: [textWidget(`${STAMP} l3`, { x: 0, y: 0 })],
		})
		const none = await seed('List none')

		const { status, entries, body } = await gallery()
		expect(status).toBe(200)
		expect(body.status).toBe('success')
		const mine = ours(entries).filter((e) =>
			[marketing.uuid, engineering.uuid, none.uuid].includes(e.uuid),
		)
		expect(mine).toHaveLength(3)
		for (const entry of mine) {
			expect(Object.keys(entry).sort()).toEqual(GALLERY_KEYS)
			expect(entry.gridColumns).toBe(12)
			expect(typeof entry.lastUpdatedAt).toBe('string')
		}
		const byUuid = new Map(mine.map((e) => [e.uuid, e]))
		expect(byUuid.get(marketing.uuid)?.category).toBe(`e2e-mkt-${RUN}`)
		expect(byUuid.get(marketing.uuid)?.widgetCount).toBe(2)
		expect(byUuid.get(engineering.uuid)?.category).toBe(`e2e-eng-${RUN}`)
		expect(byUuid.get(engineering.uuid)?.widgetCount).toBe(1)
		expect(byUuid.get(none.uuid)?.category).toBeNull()
		expect(byUuid.get(none.uuid)?.widgetCount).toBe(0)
		expect(byUuid.get(none.uuid)?.previewImage).toBeNull()
		// A list view, not a render: no widget tree, no compulsory flags.
		const raw = JSON.stringify(mine)
		expect(raw).not.toContain('isCompulsory')
		expect(raw).not.toContain('placements')
		expect(raw).not.toContain(`${STAMP} l1`)
	})

	// @e2e admin-templates::filter-gallery-by-category
	test('?category= answers only the templates in that category', async () => {
		const cat = `e2e-filter-mkt-${RUN}`
		const a = await seed('Filter m1', { templateCategory: cat })
		const b = await seed('Filter m2', { templateCategory: cat })
		await seed('Filter e1', { templateCategory: `e2e-filter-eng-${RUN}` })
		await seed('Filter e2', { templateCategory: `e2e-filter-eng-${RUN}` })
		await seed('Filter none')

		const { status, entries } = await gallery(
			`?category=${encodeURIComponent(cat)}`,
		)
		expect(status).toBe(200)
		expect(entries).toHaveLength(2)
		expect(entries.map((e) => e.uuid).sort()).toEqual([a.uuid, b.uuid].sort())
		expect(entries.every((e) => e.category === cat)).toBe(true)
	})

	// @e2e admin-templates::default-sort-order
	test('without a sort, entries go by category with no category last, then by name', async () => {
		const first = `e2e-sort-a-${RUN}`
		const second = `e2e-sort-b-${RUN}`
		await seed('Sort Zulu', { templateCategory: second })
		await seed('Sort Alpha', { templateCategory: second })
		await seed('Sort Mike', { templateCategory: first })
		await seed('Sort Bravo')
		await seed('Sort Echo', { templateCategory: first })
		await seed('Sort Alpha none')

		const { status, entries } = await gallery()
		expect(status).toBe(200)
		const sortRows = ours(entries).filter((e) => e.name.includes(' Sort '))
		expect(sortRows.map((e) => e.name.replace(`${STAMP} `, ''))).toEqual([
			'Sort Echo',
			'Sort Mike',
			'Sort Alpha',
			'Sort Zulu',
			'Sort Alpha none',
			'Sort Bravo',
		])
		// Null last across the whole answer, not just ours.
		const firstNull = entries.findIndex((e) => e.category === null)
		if (firstNull >= 0) {
			expect(entries.slice(firstNull).every((e) => e.category === null)).toBe(
				true,
			)
		}
	})

	// @e2e admin-templates::sort-by-recency
	test('?sort=updatedAt answers the most recently updated first', async () => {
		const older = await seed('Recency older')
		const middle = await seed('Recency middle')
		const newest = await seed('Recency newest')
		// updatedAt has second resolution: touch them a second apart, in order.
		for (const row of [older, middle, newest]) {
			await new Promise((resolve) => setTimeout(resolve, 1_100))
			const res = await admin.put(`${API}/admin/templates/${row.id}`, {
				data: { name: row.name },
			})
			expect(res.status()).toBe(200)
		}

		const { status, entries } = await gallery('?sort=updatedAt')
		expect(status).toBe(200)
		const mine = entries.filter((e) =>
			[older.uuid, middle.uuid, newest.uuid].includes(e.uuid),
		)
		expect(mine.map((e) => e.uuid)).toEqual([
			newest.uuid,
			middle.uuid,
			older.uuid,
		])
		const stamps = entries.map((e) => e.lastUpdatedAt)
		expect([...stamps].sort().reverse()).toEqual(stamps)
	})

	// @e2e admin-templates::gallery-includes-category-null-templates
	test('a template with no category is listed unfiltered and left out of a category', async () => {
		const plain = await seed('Null category')
		const cat = `e2e-null-mkt-${RUN}`
		await seed('Null sibling', { templateCategory: cat })

		const all = await gallery()
		expect(all.entries.find((e) => e.uuid === plain.uuid)?.category).toBeNull()
		const filtered = await gallery(`?category=${encodeURIComponent(cat)}`)
		expect(filtered.entries.map((e) => e.uuid)).not.toContain(plain.uuid)
		expect(filtered.entries).toHaveLength(1)
	})
})

test.describe('metadata fields (REQ-TMPL-016)', () => {
	// @e2e admin-templates::template-metadata-in-gallery-response
	test('category, description and preview image come back exactly as stored', async () => {
		const row = await seed('Metadata', {
			templateCategory: `e2e-meta-${RUN}`,
			templateDescription: 'Use for campaign planning',
		})
		const up = await upload(row.uuid, PNG_URL)
		expect(up.status()).toBe(200)

		const stored = (await getTemplate(admin, row.id)).template
		expect(stored.templateCategory).toBe(`e2e-meta-${RUN}`)
		expect(stored.templateDescription).toBe('Use for campaign planning')
		expect(stored.templatePreviewImage).toMatch(RESOURCE_URL)

		const entry = (await gallery()).entries.find((e) => e.uuid === row.uuid)
		expect(entry?.category).toBe(stored.templateCategory)
		expect(entry?.description).toBe(stored.templateDescription)
		expect(entry?.previewImage).toBe(stored.templatePreviewImage)
	})

	// @e2e admin-templates::metadata-persists-across-updates
	test('renaming a template through PUT keeps its category', async () => {
		const row = await seed('Persist', { templateCategory: 'engineering' })
		const res = await admin.put(`${API}/admin/templates/${row.id}`, {
			data: { name: `${STAMP} Persist renamed` },
		})
		expect(res.status()).toBe(200)
		expect((await res.json()).templateCategory).toBe('engineering')
		const stored = (await getTemplate(admin, row.id)).template
		expect(stored.name).toBe(`${STAMP} Persist renamed`)
		expect(stored.templateCategory).toBe('engineering')
	})

	// @e2e admin-templates::metadata-fields-are-nullable
	test('a template without metadata answers the fields as null everywhere', async () => {
		const row = await createTemplate(admin, { name: `${STAMP} Bare` })
		made.push(row.id)
		for (const field of [
			'templateCategory',
			'templateDescription',
			'templatePreviewImage',
		]) {
			expect(row, `create answer ${field}`).toHaveProperty(field, null)
		}

		const read = await admin.get(`${API}/admin/templates/${row.id}`)
		expect(read.status()).toBe(200)
		const listed = (await listTemplates(admin)).find((t) => t.id === row.id)
		const stored = (await read.json()).template
		for (const field of [
			'templateCategory',
			'templateDescription',
			'templatePreviewImage',
		]) {
			expect(stored, `get ${field}`).toHaveProperty(field, null)
			expect(listed, `list ${field}`).toHaveProperty(field, null)
		}

		const { status, entries } = await gallery()
		expect(status).toBe(200)
		const entry = entries.find((e) => e.uuid === row.uuid)
		expect(entry).toHaveProperty('category', null)
		expect(entry).toHaveProperty('previewImage', null)
		expect(entry).toHaveProperty('description', null)
	})
})

test.describe('preview image upload (REQ-TMPL-017)', () => {
	// @e2e admin-templates::upload-preview-image-for-a-template
	test('the admin uploads a PNG; it is stored as a resource and written to the template', async () => {
		const row = await seed('Preview')
		const res = await upload(row.uuid, PNG_URL)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body).sort()).toEqual(['previewImage', 'status'])
		expect(body.status).toBe('success')
		expect(body.previewImage).toMatch(RESOURCE_URL)

		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBe(body.previewImage)

		const served = await admin.get(`/index.php${body.previewImage}`)
		expect(served.status()).toBe(200)
		expect(served.headers()['content-type']).toContain('image/png')
		expect(Buffer.compare(await served.body(), PNG)).toBe(0)
	})

	// @e2e admin-templates::non-admin-cannot-upload-preview-image
	test('an ordinary account is refused with 403 and the column is untouched; the admin may', async () => {
		const row = await seed('Preview refused')
		const refused = await upload(row.uuid, PNG_URL, alice!.api)
		expect(refused.status()).toBe(403)
		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBeNull()

		const allowed = await upload(row.uuid, PNG_URL)
		expect(allowed.status()).toBe(200)
		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBe((await allowed.json()).previewImage)
	})

	// @e2e admin-templates::upload-replaces-previous-preview-image
	test('a second upload replaces the URL in the column', async () => {
		const row = await seed('Preview replace')
		const first = (await (await upload(row.uuid, PNG_URL)).json()).previewImage
		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBe(first)

		const res = await upload(row.uuid, PNG_URL)
		expect(res.status()).toBe(200)
		const second = (await res.json()).previewImage
		expect(second).toMatch(RESOURCE_URL)
		expect(second).not.toBe(first)
		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBe(second)
		expect((await admin.get(`/index.php${second}`)).status()).toBe(200)
	})

	// @e2e admin-templates::invalid-file-format-is-rejected
	test('a PDF is refused with 400 invalid_image; PNG, GIF and SVG are taken', async () => {
		const row = await seed('Preview formats')
		const refused = await upload(row.uuid, PDF_URL)
		expect(refused.status()).toBe(400)
		const body = await refused.json()
		expect(body.status).toBe('error')
		expect(body.error).toBe('invalid_image')
		expect(
			(await getTemplate(admin, row.id)).template.templatePreviewImage,
		).toBeNull()

		for (const [label, dataUrl] of [
			['png', PNG_URL],
			['gif', GIF_URL],
			['svg', SVG_URL],
		]) {
			const ok = await upload(row.uuid, dataUrl)
			expect(ok.status(), `${label} answered ${ok.status()}`).toBe(200)
			expect((await ok.json()).previewImage).toMatch(
				new RegExp(`\\.${label}$`),
			)
		}
	})

	// @e2e admin-templates::preview-image-url-in-gallery-response
	test('the gallery carries the new preview URL at once, and it serves', async () => {
		const row = await seed('Preview gallery')
		const url = (await (await upload(row.uuid, PNG_URL)).json()).previewImage

		const entry = (await gallery()).entries.find((e) => e.uuid === row.uuid)
		expect(entry?.previewImage).toBe(url)
		const served = await alice!.api.get(`/index.php${url}`)
		expect(served.status()).toBe(200)
		expect(served.headers()['content-type']).toContain('image/png')
	})
})
