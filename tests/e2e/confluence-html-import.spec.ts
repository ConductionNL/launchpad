/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the Confluence HTML export importer
 * (openspec/specs/confluence-html-import), written for e2e-coverage-programme
 * task 5.6.
 *
 * Every test builds a Confluence "HTML Export" ZIP in memory
 * (`support/zipArchive.ts`), uploads it to the admin-only routes
 *
 *   POST /api/admin/import/confluence/dry-run   (multipart `file`)
 *   POST /api/admin/import/confluence           (multipart `file`, `?parentUuid=`)
 *
 * and then READS THE RESULT BACK through the dashboard API: the list
 * (`GET /api/dashboards`), the single dashboard with its placements
 * (`GET /api/dashboard/{id}`) and the tree (`GET /api/dashboards/tree`).
 *
 * WHY THE READ-BACK IS THE POINT. A past audit (#617) found that Confluence
 * import never saved a dashboard: every page's placement insert failed on a
 * NOT NULL column, the page rolled back, and the import reported it skipped.
 * The response counts looked plausible the whole time. So no test here trusts
 * `createdDashboardCount` alone: it finds the dashboards by name, opens them,
 * and asserts the text widget the importer was supposed to write.
 *
 * Server-side warnings the spec says MUST be logged are read from the admin
 * log download (`/index.php/settings/admin/log/download`), the one place an
 * HTTP client can observe `LoggerInterface::warning()`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e confluence-html-import::extract-archive-metadata
 *   @e2e confluence-html-import::missing-indexhtml-is-an-error
 *   @e2e confluence-html-import::nested-page-directories-are-supported
 *   @e2e confluence-html-import::attachments-and-shared-images-are-tracked
 *   @e2e confluence-html-import::breadcrumb-drives-the-parent-assignment
 *   @e2e confluence-html-import::directory-nesting-fills-the-gap-when-breadcrumb-is-absent
 *   @e2e confluence-html-import::indexhtml-provides-sibling-order-only
 *   @e2e confluence-html-import::root-pages-have-null-parent
 *   @e2e confluence-html-import::create-dashboard-from-page
 *   @e2e confluence-html-import::dashboard-inherits-parent-from-page-hierarchy
 *   @e2e confluence-html-import::root-confluence-pages-become-root-dashboards
 *   @e2e confluence-html-import::caller-supplied-root-parent-slots-all-roots-beneath-it
 *   @e2e confluence-html-import::text-display-widget-captures-page-body-via-selector-waterfall
 *   @e2e confluence-html-import::widget-html-is-sanitised-before-storage
 *   @e2e confluence-html-import::rewrite-link-to-sibling-page
 *   @e2e confluence-html-import::cross-space-links-are-rewritten
 *   @e2e confluence-html-import::links-to-non-existent-pages-are-logged
 *   @e2e confluence-html-import::external-links-are-untouched
 *   @e2e confluence-html-import::acimage-becomes-a-plain-img-tag
 *   @e2e confluence-html-import::plain-html-img-tags-survive-sanitisation
 *   @e2e confluence-html-import::panel-type-macros-render-as-styled-blocks
 *   @e2e confluence-html-import::code-macro-renders-as-preformatted-block
 *   @e2e confluence-html-import::expand-macro-renders-as-collapsible-block
 *   @e2e confluence-html-import::unrecognised-macros-receive-fallback-placeholder
 *   @e2e confluence-html-import::dry-run-returns-page-count-and-asset-folder
 *   @e2e confluence-html-import::dry-run-surfaces-parser-warnings
 *   @e2e confluence-html-import::sync-import-returns-counts
 *   @e2e confluence-html-import::async-path-is-documented-as-future-work
 *   @e2e confluence-html-import::per-page-failure-is-captured-in-the-response
 *   @e2e confluence-html-import::final-report-includes-all-errors
 *   @e2e confluence-html-import::second-import-creates-new-dashboards
 *   @e2e confluence-html-import::asset-folder-timestamp-differs-across-runs
 *   @e2e confluence-html-import::allow-listed-tags-are-preserved
 *   @e2e confluence-html-import::disallowed-tags-are-unwrapped
 *   @e2e confluence-html-import::script-bearing-tags-are-dropped-with-their-content
 *   @e2e confluence-html-import::event-handlers-and-javascript-urls-are-stripped
 *   @e2e confluence-html-import::allow-list-covers-the-documented-vocabulary
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { ZipEntry } from './support/zipArchive.ts'

import { expect, test } from '@playwright/test'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'
import { buildZip } from './support/zipArchive.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

const RUN = Date.now()

/**
 * Every dashboard this file creates has a name starting with this stamp, so
 * the sweep after each test is exact. No ` : ` in it: the importer strips a
 * `Space : ` prefix from Confluence titles at the first ` : `.
 */
const STAMP = `E2E-Confluence-${RUN}`

/** The asset-folder shape: `LaunchPad/Imports/<date>T<time>`. */
const ASSET_FOLDER = /^LaunchPad\/Imports\/\d{4}-\d{2}-\d{2}T\d{2}[-:]\d{2}[-:]\d{2}/

/** The importer's per-page widget defaults (REQ-CFLI-003). */
const STYLE_DEFAULTS = {
	fontSize: '14px',
	color: 'var(--color-main-text)',
	backgroundColor: 'transparent',
	textAlign: 'left',
}

/** Long enough for a 100-page synchronous import on a slow CI instance. */
const BIG_IMPORT_TIMEOUT = 240_000

interface Dashboard {
	id: number
	uuid: string
	name: string
	userId: string
	parentUuid: string | null
	slug: string | null
	sortOrder: number
	updatedAt?: string
}

interface Placement {
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	styleConfig: Record<string, unknown>
}

interface TreeNode {
	uuid: string
	name: string
	children: TreeNode[]
}

interface Outcome {
	status: number
	body: any
}

/**
 * One Confluence export page, laid out the way the HTML Export writes it:
 * a `Space : Title` title, the breadcrumb outside the content, and the body
 * inside `div#main-content`.
 *
 * @param title the page title, without the space prefix.
 * @param body the HTML inside `div#main-content`.
 * @param breadcrumb optional `<ol class="breadcrumbs">…</ol>` markup.
 * @return the whole page.
 */
function confluencePage(title: string, body: string, breadcrumb = ''): string {
	return '<!DOCTYPE html><html><head>'
		+ `<title>SPACE : ${title}</title></head><body>`
		+ `<div id="breadcrumb-section">${breadcrumb}</div>`
		+ `<div id="main-content">${body}</div>`
		+ '</body></html>'
}

/**
 * A breadcrumb trail as Confluence renders it: every ancestor a link, the
 * current page plain text.
 *
 * @param ancestors the ancestor page files, root first.
 * @param current the current page's label.
 * @param attr the `<ol>` identifying attribute (`class` or `id` form).
 * @return the `<ol>` markup.
 */
function breadcrumbs(
	ancestors: string[],
	current: string,
	attr = 'class="breadcrumbs"',
): string {
	const links = ancestors
		.map((href) => `<li><a href="${href}">${href}</a></li>`)
		.join('')
	return `<ol ${attr}>${links}<li>${current}</li></ol>`
}

/**
 * An `index.html` that links every given page, in that order.
 *
 * @param hrefs the page paths, relative to the archive root.
 * @return the index markup.
 */
function indexHtml(hrefs: string[]): string {
	const items = hrefs.map((href) => `<li><a href="${href}">${href}</a></li>`)
	return `<html><body><ul>${items.join('')}</ul></body></html>`
}

/**
 * A Confluence export archive: `index.html` linking every page, then the pages.
 *
 * @param pages archive path → page HTML.
 * @param extra further entries (attachments, images, other files).
 * @return the ZIP bytes.
 */
function exportArchive(
	pages: Record<string, string>,
	extra: ZipEntry[] = [],
): Buffer {
	return buildZip([
		{ name: 'index.html', content: indexHtml(Object.keys(pages)) },
		...Object.entries(pages).map(([name, content]) => ({ name, content })),
		...extra,
	])
}

/**
 * `count` root pages named `<prefix> NNN`, filed as `SPACE/<id>NNN.html`.
 *
 * @param prefix the stamped title prefix.
 * @param id a filename-safe page-id prefix.
 * @param count how many pages.
 * @return archive path → page HTML.
 */
function rootPages(
	prefix: string,
	id: string,
	count: number,
): Record<string, string> {
	const pages: Record<string, string> = {}
	for (let i = 1; i <= count; i++) {
		const n = String(i).padStart(3, '0')
		pages[`SPACE/${id}${n}.html`] = confluencePage(`${prefix} ${n}`, `<p>${n}</p>`)
	}
	return pages
}

/**
 * Upload an archive to one of the two Confluence routes.
 *
 * @param api the principal to upload as.
 * @param route `import` or `dry-run`.
 * @param archive the ZIP bytes.
 * @param query an optional query string, without the `?`.
 * @return the status and parsed body.
 */
async function upload(
	api: APIRequestContext,
	route: 'import' | 'dry-run',
	archive: Buffer,
	query = '',
): Promise<Outcome> {
	const path = route === 'dry-run'
		? `${API}/admin/import/confluence/dry-run`
		: `${API}/admin/import/confluence`
	const res = await api.post(query === '' ? path : `${path}?${query}`, {
		multipart: {
			file: { name: 'export.zip', mimeType: 'application/zip', buffer: archive },
		},
		timeout: BIG_IMPORT_TIMEOUT,
	})
	const raw = await res.text()
	let body: any
	try {
		body = JSON.parse(raw)
	} catch {
		body = raw
	}
	return { status: res.status(), body }
}

/**
 * Every dashboard the principal owns whose name starts with `prefix`.
 *
 * @param api the principal.
 * @param prefix the name prefix.
 * @return the matching dashboards.
 */
async function dashboardsNamed(
	api: APIRequestContext,
	prefix: string,
): Promise<Dashboard[]> {
	const res = await api.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const items: Dashboard[] = Array.isArray(body) ? body : body.items ?? []
	return items.filter((d) => String(d.name ?? '').startsWith(prefix))
}

/** Every dashboard the principal owns, however named. */
async function allDashboards(api: APIRequestContext): Promise<Dashboard[]> {
	return dashboardsNamed(api, '')
}

/**
 * The single dashboard the principal owns with exactly this name.
 *
 * @param list the dashboards to search.
 * @param name the exact name.
 * @return the dashboard; fails the test when absent or ambiguous.
 */
function only(list: Dashboard[], name: string): Dashboard {
	const hits = list.filter((d) => d.name === name)
	expect(hits.map((d) => d.name), `exactly one dashboard named "${name}"`).toEqual([name])
	return hits[0]
}

/**
 * Open a dashboard and return its placements.
 *
 * @param api the principal.
 * @param dashboard the dashboard.
 * @return its placements.
 */
async function placementsOf(
	api: APIRequestContext,
	dashboard: Dashboard,
): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboard.id}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).placements ?? []
}

/**
 * The HTML the importer stored in a dashboard's one text widget.
 *
 * @param api the principal.
 * @param dashboard the imported dashboard.
 * @return `styleConfig.text` of its only placement.
 */
async function storedHtml(
	api: APIRequestContext,
	dashboard: Dashboard,
): Promise<string> {
	const placements = await placementsOf(api, dashboard)
	expect(placements.length, `${dashboard.name} carries one widget`).toBe(1)
	expect(placements[0].widgetId).toBe('text')
	return String(placements[0].styleConfig?.text ?? '')
}

/**
 * The admin-visible dashboard tree.
 *
 * @param api the principal.
 * @return the root nodes.
 */
async function tree(api: APIRequestContext): Promise<TreeNode[]> {
	const res = await api.get(`${API}/dashboards/tree`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Array.isArray(body) ? body : body.tree ?? body.items ?? []
}

/**
 * Find a node anywhere in the tree.
 *
 * @param nodes the level to search.
 * @param uuid the dashboard UUID.
 * @return the node, or null.
 */
function findNode(nodes: TreeNode[], uuid: string): TreeNode | null {
	for (const node of nodes) {
		if (node.uuid === uuid) {
			return node
		}
		const below = findNode(node.children ?? [], uuid)
		if (below !== null) {
			return below
		}
	}
	return null
}

/**
 * The lines of the Nextcloud log, read through the admin download.
 *
 * @param api an admin context.
 * @return the log lines.
 */
async function serverLog(api: APIRequestContext): Promise<string[]> {
	const res = await api.get('/index.php/settings/admin/log/download', {
		timeout: 120_000,
	})
	expect(res.status(), 'the admin log download is the only HTTP view of the log').toBe(200)
	return (await res.text()).split('\n')
}

/**
 * Delete every dashboard the principal owns under the prefix. Children may
 * refuse to go before (or vanish with) their parents, so it repeats.
 *
 * @param api the owner.
 * @param prefix the name prefix.
 * @return nothing.
 */
async function sweep(api: APIRequestContext, prefix: string): Promise<void> {
	for (let pass = 0; pass < 8; pass++) {
		const res = await api.get(`${API}/dashboards`)
		if (!res.ok()) {
			return
		}
		const body = await res.json()
		const items: Dashboard[] = Array.isArray(body) ? body : body.items ?? []
		const left = items.filter((d) => String(d.name ?? '').startsWith(prefix))
		if (left.length === 0) {
			return
		}
		// Deepest first: a child is deleted before its parent is asked to go.
		for (const d of left.reverse()) {
			await api.delete(`${API}/dashboard/${d.id}`)
		}
	}
}

test.describe('confluence-html-import', () => {
	let admin: APIRequestContext
	let member: Principal | null = null

	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)
		member = await createPrincipal(admin, 'cfl-member')
		await assertActingAs(member.api, member.username)
	})

	test.afterEach(async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT)
		await sweep(admin, STAMP)
		if (member !== null) {
			await sweep(member.api, STAMP)
		}
	})

	test.afterAll(async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT)
		if (admin === undefined) {
			return
		}
		await sweep(admin, STAMP)
		await removePrincipal(admin, member)
		await admin.dispose()
	})

	// ── REQ-CFLI-001 archive structure ──────────────────────────────────

	// @e2e confluence-html-import::extract-archive-metadata
	test('every entry gets its role: index, page, attachment, image', async () => {
		const title = `${STAMP} meta Page`
		const archive = buildZip([
			{ name: 'index.html', content: indexHtml(['SPACE-KEY/page-id.html']) },
			{ name: 'SPACE-KEY/page-id.html', content: confluencePage(title, '<p>meta body</p>') },
			{ name: 'attachments/page-id/image.png', content: Buffer.from([0x89, 0x50, 0x4e, 0x47]) },
			{ name: 'images/icon.png', content: Buffer.from([0x89, 0x50, 0x4e, 0x47]) },
		])

		// index.html is the index, not a page; the two PNGs are assets.
		const dry = await upload(admin, 'dry-run', archive)
		expect(dry.status, JSON.stringify(dry.body)).toBe(200)
		expect(dry.body.pageCount).toBe(1)
		expect(dry.body.estimatedDashboards).toBe(1)
		expect(dry.body.attachmentCount).toBe(2)
		expect(dry.body.warnings).toEqual([])

		const run = await upload(admin, 'import', archive)
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(1)

		// Exactly the page became a dashboard: not index.html, not an asset.
		const made = await dashboardsNamed(admin, `${STAMP} meta`)
		expect(made.map((d) => d.name)).toEqual([title])
		expect(await storedHtml(admin, made[0])).toBe('<p>meta body</p>')

		// The archive is only read: the same upload parses the same way again.
		const again = await upload(admin, 'dry-run', archive)
		expect(again.status).toBe(200)
		expect([again.body.pageCount, again.body.attachmentCount]).toEqual([1, 2])
	})

	// @e2e confluence-html-import::missing-indexhtml-is-an-error
	test('an archive without index.html is refused with 400 and imports nothing', async () => {
		const title = `${STAMP} noindex Page`
		const archive = buildZip([
			{ name: 'SPACE/page.html', content: confluencePage(title, '<p>x</p>') },
		])

		for (const route of ['dry-run', 'import'] as const) {
			const res = await upload(admin, route, archive)
			expect(res.status, `${route}: ${JSON.stringify(res.body)}`).toBe(400)
			expect(res.body.error).toBe('index.html not found in archive')
		}

		expect(await dashboardsNamed(admin, `${STAMP} noindex`)).toEqual([])

		// The same page WITH an index imports, so the 400 was the index.
		const fixed = await upload(admin, 'import', exportArchive({
			'SPACE/page.html': confluencePage(title, '<p>x</p>'),
		}))
		expect(fixed.status, JSON.stringify(fixed.body)).toBe(200)
		only(await dashboardsNamed(admin, `${STAMP} noindex`), title)
	})

	// @e2e confluence-html-import::nested-page-directories-are-supported
	test('pages in nested space directories are found by their .html extension', async () => {
		const one = `${STAMP} nested One`
		const two = `${STAMP} nested Two`
		const archive = exportArchive(
			{
				'SPACE1/page1.html': confluencePage(one, '<p>first page</p>'),
				'SPACE1/SUB/page2.html': confluencePage(two, '<p>second page</p>'),
			},
			[{ name: 'SPACE1/SUB/notes.txt', content: 'not a page' }],
		)

		const dry = await upload(admin, 'dry-run', archive)
		expect(dry.status).toBe(200)
		expect(dry.body.pageCount).toBe(2)

		const run = await upload(admin, 'import', archive)
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(2)

		const made = await dashboardsNamed(admin, `${STAMP} nested`)
		expect(await storedHtml(admin, only(made, one))).toBe('<p>first page</p>')
		expect(await storedHtml(admin, only(made, two))).toBe('<p>second page</p>')
	})

	// @e2e confluence-html-import::attachments-and-shared-images-are-tracked
	test('attachments/ and images/ entries are both counted as assets', async () => {
		const page = { 'SPACE/page-1.html': confluencePage(`${STAMP} assets Page`, '<p>a</p>') }
		const png = Buffer.from([0x89, 0x50, 0x4e, 0x47])
		const attachment = { name: 'attachments/page-1/diagram.png', content: png }
		const image = { name: 'images/icon.png', content: png }

		const both = await upload(admin, 'dry-run', exportArchive(page, [attachment, image]))
		expect(both.status).toBe(200)
		expect(both.body.attachmentCount).toBe(2)

		// Each path is tracked on its own, not just counted as "two files".
		const onlyAttachment = await upload(admin, 'dry-run', exportArchive(page, [attachment]))
		expect(onlyAttachment.body.attachmentCount).toBe(1)
		const onlyImage = await upload(admin, 'dry-run', exportArchive(page, [image]))
		expect(onlyImage.body.attachmentCount).toBe(1)
		const none = await upload(admin, 'dry-run', exportArchive(page))
		expect(none.body.attachmentCount).toBe(0)
	})

	// ── REQ-CFLI-002 hierarchy ──────────────────────────────────────────

	// @e2e confluence-html-import::breadcrumb-drives-the-parent-assignment
	test('the last breadcrumb link is the parent, in both breadcrumb spellings', async () => {
		const root = `${STAMP} crumb Root`
		const section = `${STAMP} crumb Section A`
		const current = `${STAMP} crumb Current Page`
		const byId = `${STAMP} crumb By Id`

		const run = await upload(admin, 'import', exportArchive({
			'SPACE/root.html': confluencePage(root, '<p>root</p>'),
			'SPACE/page-100.html': confluencePage(
				section,
				'<p>section</p>',
				breadcrumbs(['root.html'], 'Section A'),
			),
			'SPACE/current.html': confluencePage(
				current,
				'<p>current</p>',
				breadcrumbs(['root.html', 'page-100.html'], 'Current Page'),
			),
			'SPACE/by-id.html': confluencePage(
				byId,
				'<p>by id</p>',
				breadcrumbs(['page-100.html'], 'By Id', 'id="breadcrumbs"'),
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(4)

		const made = await dashboardsNamed(admin, `${STAMP} crumb`)
		const rootD = only(made, root)
		const sectionD = only(made, section)
		expect(rootD.parentUuid).toBeNull()
		expect(sectionD.parentUuid).toBe(rootD.uuid)
		// The immediate parent (page-100), not the trail's root.
		expect(only(made, current).parentUuid).toBe(sectionD.uuid)
		expect(only(made, byId).parentUuid).toBe(sectionD.uuid)
	})

	// @e2e confluence-html-import::directory-nesting-fills-the-gap-when-breadcrumb-is-absent
	test('SPACE.html parents SPACE/*.html without a breadcrumb; a breadcrumb wins', async () => {
		const space = `${STAMP} dir Space`
		const child = `${STAMP} dir Child 456`
		const other = `${STAMP} dir Other`
		const crumbed = `${STAMP} dir Crumbed 457`

		const run = await upload(admin, 'import', exportArchive({
			'SPACE.html': confluencePage(space, '<p>space</p>'),
			'SPACE/page-456.html': confluencePage(child, '<p>child</p>'),
			'SPACE/other.html': confluencePage(other, '<p>other</p>', breadcrumbs(['SPACE.html'], 'Other')),
			'SPACE/page-457.html': confluencePage(
				crumbed,
				'<p>crumbed</p>',
				breadcrumbs(['SPACE.html', 'other.html'], 'Crumbed'),
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const made = await dashboardsNamed(admin, `${STAMP} dir`)
		const spaceD = only(made, space)
		expect(only(made, child).parentUuid).toBe(spaceD.uuid)
		// Directory nesting would say SPACE; the breadcrumb says other.
		expect(only(made, crumbed).parentUuid).toBe(only(made, other).uuid)
	})

	// @e2e confluence-html-import::indexhtml-provides-sibling-order-only
	test('index.html sets the 0-based sibling order and never the parent', async () => {
		const z = `${STAMP} order Z`
		const b = `${STAMP} order B`
		const a = `${STAMP} order A`
		const c = `${STAMP} order C`

		// A is NESTED under B in the index; that must not make B its parent.
		const index = '<html><body><ul>'
			+ '<li><a href="SPACE/page-z.html">Z</a></li>'
			+ '<li><a href="SPACE/page-b.html">B</a>'
			+ '<ul><li><a href="SPACE/page-a.html">A</a></li></ul></li>'
			+ '<li><a href="SPACE/page-c.html">C</a></li>'
			+ '</ul></body></html>'
		const run = await upload(admin, 'import', buildZip([
			{ name: 'index.html', content: index },
			{ name: 'SPACE/page-a.html', content: confluencePage(a, '<p>a</p>') },
			{ name: 'SPACE/page-c.html', content: confluencePage(c, '<p>c</p>') },
			{ name: 'SPACE/page-b.html', content: confluencePage(b, '<p>b</p>') },
			{ name: 'SPACE/page-z.html', content: confluencePage(z, '<p>z</p>') },
		]))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(4)

		const made = await dashboardsNamed(admin, `${STAMP} order`)
		// Position of each href match in the file, regardless of nesting depth.
		expect([z, b, a, c].map((name) => only(made, name).sortOrder)).toEqual([0, 1, 2, 3])
		for (const name of [z, b, a, c]) {
			expect(only(made, name).parentUuid, `${name} is a root`).toBeNull()
		}
	})

	// @e2e confluence-html-import::root-pages-have-null-parent
	test('a page with no breadcrumb and no parent file is a root dashboard', async () => {
		const solo = `${STAMP} solo Page`
		const run = await upload(admin, 'import', exportArchive({
			'LONE/solo.html': confluencePage(solo, '<p>solo</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const soloD = only(await dashboardsNamed(admin, `${STAMP} solo`), solo)
		expect(soloD.parentUuid).toBeNull()
		const roots = await tree(admin)
		expect(roots.map((n) => n.uuid), 'listed at the top of the tree').toContain(soloD.uuid)
	})

	// ── REQ-CFLI-003 page → dashboard ───────────────────────────────────

	// @e2e confluence-html-import::create-dashboard-from-page
	test('a page becomes a dashboard named after it and owned by the importer', async () => {
		const title = `${STAMP} Architecture Overview`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/arch.html': confluencePage(title, '<p>The architecture.</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(1)

		const made = only(await dashboardsNamed(admin, `${STAMP} Architecture`), title)
		expect(made.name).toBe(title)
		expect(made.userId).toBe(ADMIN_USER)
		expect(await storedHtml(admin, made)).toBe('<p>The architecture.</p>')
	})

	// @e2e confluence-html-import::dashboard-inherits-parent-from-page-hierarchy
	test('a child page is created under its parent page\'s dashboard, in the tree too', async () => {
		const planning = `${STAMP} inherit Planning`
		const goals = `${STAMP} inherit Q1 Goals`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/q1-goals.html': confluencePage(goals, '<p>goals</p>', breadcrumbs(['planning.html'], 'Q1 Goals')),
			'SPACE/planning.html': confluencePage(planning, '<p>planning</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(2)

		const made = await dashboardsNamed(admin, `${STAMP} inherit`)
		const planningD = only(made, planning)
		const goalsD = only(made, goals)
		expect(goalsD.parentUuid).toBe(planningD.uuid)

		const node = findNode(await tree(admin), planningD.uuid)
		expect(node, 'planning is in the tree').not.toBeNull()
		expect((node?.children ?? []).map((n) => n.uuid)).toEqual([goalsD.uuid])
	})

	// @e2e confluence-html-import::root-confluence-pages-become-root-dashboards
	test('without parentUuid a top-level page is a root dashboard', async () => {
		const top = `${STAMP} toplevel Home`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/home.html': confluencePage(top, '<p>home</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(only(await dashboardsNamed(admin, `${STAMP} toplevel`), top).parentUuid).toBeNull()
	})

	// @e2e confluence-html-import::caller-supplied-root-parent-slots-all-roots-beneath-it
	test('?parentUuid slots every root page beneath it; an unknown one falls back to root', async () => {
		// The parent is itself an import, so this needs no other write route.
		const host = `${STAMP} slot Host`
		const seeded = await upload(admin, 'import', exportArchive({
			'SPACE/host.html': confluencePage(host, '<p>host</p>'),
		}))
		expect(seeded.status).toBe(200)
		const hostD = only(await dashboardsNamed(admin, `${STAMP} slot`), host)

		const r1 = `${STAMP} slot Root One`
		const r2 = `${STAMP} slot Root Two`
		const kid = `${STAMP} slot Kid`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/r1.html': confluencePage(r1, '<p>r1</p>'),
			'SPACE/r2.html': confluencePage(r2, '<p>r2</p>'),
			'SPACE/kid.html': confluencePage(kid, '<p>kid</p>', breadcrumbs(['r1.html'], 'Kid')),
		}), `parentUuid=${encodeURIComponent(hostD.uuid)}`)
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(3)

		const made = await dashboardsNamed(admin, `${STAMP} slot`)
		expect(only(made, r1).parentUuid).toBe(hostD.uuid)
		expect(only(made, r2).parentUuid).toBe(hostD.uuid)
		// A non-root keeps its own page parent.
		expect(only(made, kid).parentUuid).toBe(only(made, r1).uuid)

		// An unknown parent: a logged warning and root-level placement.
		const ghost = `00000000-0000-4000-8000-${String(RUN).slice(-12).padStart(12, '0')}`
		const orphan = `${STAMP} slot Orphan`
		const fallback = await upload(admin, 'import', exportArchive({
			'SPACE/orphan.html': confluencePage(orphan, '<p>orphan</p>'),
		}), `parentUuid=${ghost}`)
		expect(fallback.status, JSON.stringify(fallback.body)).toBe(200)
		expect(fallback.body.createdDashboardCount).toBe(1)
		expect(only(await dashboardsNamed(admin, `${STAMP} slot`), orphan).parentUuid).toBeNull()

		const warned = (await serverLog(admin)).filter((line) => line.includes(ghost))
		expect(warned.length, `a log line names the unknown parent ${ghost}`).toBeGreaterThan(0)
		expect(warned.join('\n')).toMatch(/parent dashboard not found/i)
	})

	// @e2e confluence-html-import::text-display-widget-captures-page-body-via-selector-waterfall
	test('the body comes from the first non-empty selector, minus navigation, in a 12x12 text widget', async () => {
		const p = `${STAMP} wf`
		const html = (title: string, body: string): string =>
			`<html><head><title>SPACE : ${title}</title></head><body>${body}</body></html>`
		const chrome = '<div id="pagetreesearch">NAV-1</div>'
			+ '<div class="breadcrumbs">NAV-2</div>'
			+ '<div class="pageSection">NAV-3</div>'
			+ '<form name="pagetreesearchform">NAV-4</form>'
			+ '<form class="aui">NAV-5</form>'
			+ '<nav>NAV-6</nav>'
			+ '<div class="page-metadata">NAV-7</div>'

		// Each page puts the LOWER-priority container first in the document,
		// so document order cannot be what picks the winner.
		const cases: Array<[string, string, string]> = [
			['main-content', `<div class="wiki-content"><p>LOSER</p></div><div id="main-content"><p>WON</p>${chrome}</div>`, '<p>WON</p>'],
			['wiki-content', `<div class="page-content"><p>LOSER</p></div><div class="wiki-content"><p>WON</p>${chrome}</div>`, '<p>WON</p>'],
			['page-content', `<div id="content"><p>LOSER</p></div><div class="page-content"><p>WON</p>${chrome}</div>`, '<p>WON</p>'],
			['content', `<main><p>LOSER</p></main><div id="content"><p>WON</p>${chrome}</div>`, '<p>WON</p>'],
			['main', `<article><p>LOSER</p></article><main><p>WON</p>${chrome}</main>`, '<p>WON</p>'],
			['article', `<article><p>WON</p>${chrome}</article>`, '<p>WON</p>'],
			['empty-first', '<div id="main-content">   </div><div class="wiki-content"><p>WON</p></div>', '<p>WON</p>'],
			['body', '<p>WON</p>', '<p>WON</p>'],
		]
		const pages: Record<string, string> = {}
		for (const [tag, body] of cases) {
			pages[`SPACE/wf-${tag}.html`] = html(`${p} ${tag}`, body)
		}
		// No <body> at all: the raw HTML is the last resort. Without a <title>
		// the page id is the name, so the id carries the stamp.
		pages[`SPACE/${STAMP}-wf-raw.html`] = '<p>WON</p>'

		const run = await upload(admin, 'import', exportArchive(pages))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(cases.length + 1)

		const made = await dashboardsNamed(admin, STAMP)
		const expected: Array<[string, string]> = [
			...cases.map(([tag, , out]) => [`${p} ${tag}`, out] as [string, string]),
			[`${STAMP}-wf-raw`, '<p>WON</p>'],
		]
		for (const [name, out] of expected) {
			const placements = await placementsOf(admin, only(made, name))
			expect(placements.length, `${name}: one widget`).toBe(1)
			const [w] = placements
			expect(w.widgetId, name).toBe('text')
			expect(
				[Number(w.gridX), Number(w.gridY), Number(w.gridWidth), Number(w.gridHeight)],
				`${name}: full-width at the origin`,
			).toEqual([0, 0, 12, 12])
			expect(w.styleConfig, name).toEqual({ text: out, ...STYLE_DEFAULTS })
		}
	})

	// @e2e confluence-html-import::widget-html-is-sanitised-before-storage
	test('the stored widget HTML has the script and its payload removed', async () => {
		const title = `${STAMP} sanitise Page`
		const archive = buildZip([
			{ name: 'index.html', content: indexHtml(['SPACE/s.html']) },
			{
				name: 'SPACE/s.html',
				content: `<html><head><title>SPACE : ${title}</title></head><body>`
					+ '<div id="main-content"><p>Safe <script>alert(1)</script> text</p></div></body></html>',
			},
		])
		const run = await upload(admin, 'import', archive)
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} sanitise`), title))
		expect(stored).toBe('<p>Safe  text</p>')
		// Safe for v-html: nothing executable survives.
		expect(stored).not.toMatch(/<script|alert\(|\son\w+=|javascript:/i)
	})

	// ── REQ-CFLI-004 links ──────────────────────────────────────────────

	// @e2e confluence-html-import::rewrite-link-to-sibling-page
	test('a link to a sibling page points at that page\'s new dashboard', async () => {
		const from = `${STAMP} sib From`
		const to = `${STAMP} sib To`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/page-123.html': confluencePage(from, '<a href="page-456.html">See Also</a>'),
			'SPACE/page-456.html': confluencePage(to, '<p>target</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const made = await dashboardsNamed(admin, `${STAMP} sib`)
		const target = only(made, to)
		expect(await storedHtml(admin, only(made, from)))
			.toBe(`<a href="/apps/launchpad/dashboard/${target.uuid}">See Also</a>`)
	})

	// @e2e confluence-html-import::cross-space-links-are-rewritten
	test('a link into another space is rewritten too', async () => {
		const from = `${STAMP} xspace From`
		const to = `${STAMP} xspace Target`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/from.html': confluencePage(from, '<a href="OTHER-SPACE/page-789.html">External Page</a>'),
			'OTHER-SPACE/page-789.html': confluencePage(to, '<p>other space</p>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const made = await dashboardsNamed(admin, `${STAMP} xspace`)
		expect(await storedHtml(admin, only(made, from)))
			.toBe(`<a href="/apps/launchpad/dashboard/${only(made, to).uuid}">External Page</a>`)
	})

	// @e2e confluence-html-import::links-to-non-existent-pages-are-logged
	test('a link to a page not in the import warns and is kept as-is', async () => {
		const title = `${STAMP} dangling Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/dangling.html': confluencePage(title, '<a href="page-999.html">Missing Page</a>'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.warnings).toContain('Link to unknown Confluence page: page-999.html')

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} dangling`), title))
		expect(stored).toBe('<a href="page-999.html">Missing Page</a>')
	})

	// @e2e confluence-html-import::external-links-are-untouched
	test('external, mailto, tel, root-relative and fragment links keep their href', async () => {
		const title = `${STAMP} external Page`
		const hrefs = [
			'https://example.com',
			'mailto:someone@example.com',
			'tel:+31201234567',
			'/apps/files',
			'#section-2',
		]
		const body = hrefs.map((href, i) => `<a href="${href}">L${i}</a>`).join(' ')
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/external.html': confluencePage(title, body),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} external`), title))
		expect(stored).toBe(body)
	})

	// ── REQ-CFLI-005 images ─────────────────────────────────────────────

	// @e2e confluence-html-import::acimage-becomes-a-plain-img-tag
	test('an ac:image attachment is stored as a plain img', async () => {
		const title = `${STAMP} acimage Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/acimage.html': confluencePage(
				title,
				'<ac:image><ri:attachment ri:filename="diagram.png"/></ac:image>',
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} acimage`), title))
		expect(stored).toBe('<img src="diagram.png" alt="diagram.png">')
	})

	// @e2e confluence-html-import::plain-html-img-tags-survive-sanitisation
	test('a plain img keeps its relative src and its alt', async () => {
		const title = `${STAMP} plainimg Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/plainimg.html': confluencePage(title, '<img src="attachments/page-1/diagram.png" alt="">'),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} plainimg`), title))
		expect(stored).toBe('<img src="attachments/page-1/diagram.png" alt="">')
		expect(stored).not.toMatch(/src="(https?:|\/)/)
	})

	// ── REQ-CFLI-006 macros ─────────────────────────────────────────────

	// @e2e confluence-html-import::panel-type-macros-render-as-styled-blocks
	test('info, note, warning, tip, error and panel macros become styled blocks', async () => {
		const types = ['info', 'note', 'warning', 'tip', 'error', 'panel']
		const title = `${STAMP} panels Page`
		const body = types
			.map((type) => `<ac:structured-macro ac:name="${type}"><ac:rich-text-body><p>${type} body</p></ac:rich-text-body></ac:structured-macro>`)
			.join('')
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/panels.html': confluencePage(title, body),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} panels`), title))
		expect(stored).toBe(
			types.map((type) => `<div class="confluence-panel-${type}"><p>${type} body</p></div>`).join(''),
		)
	})

	// @e2e confluence-html-import::code-macro-renders-as-preformatted-block
	test('a code macro becomes pre/code with the language class', async () => {
		const title = `${STAMP} code Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/code.html': confluencePage(
				title,
				'<ac:structured-macro ac:name="code"><ac:parameter ac:name="language">java</ac:parameter>'
					+ '<ac:plain-text-body><![CDATA[int x = 1;]]></ac:plain-text-body></ac:structured-macro>',
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} code`), title))
		expect(stored).toBe('<pre><code class="language-java">int x = 1;</code></pre>')
	})

	// @e2e confluence-html-import::expand-macro-renders-as-collapsible-block
	test('an expand macro becomes details/summary', async () => {
		const title = `${STAMP} expand Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/expand.html': confluencePage(
				title,
				'<ac:structured-macro ac:name="expand"><ac:parameter ac:name="title">More</ac:parameter>'
					+ '<ac:rich-text-body><p>hidden</p></ac:rich-text-body></ac:structured-macro>',
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} expand`), title))
		expect(stored).toBe('<details><summary>More</summary><p>hidden</p></details>')
	})

	// @e2e confluence-html-import::unrecognised-macros-receive-fallback-placeholder
	test('an unregistered macro leaves a placeholder naming it', async () => {
		const title = `${STAMP} unknownmacro Page`
		const run = await upload(admin, 'import', exportArchive({
			'SPACE/sql.html': confluencePage(
				title,
				'<ac:structured-macro ac:name="sql"><ac:plain-text-body><![CDATA[select 1]]></ac:plain-text-body></ac:structured-macro>',
			),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)

		const stored = await storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} unknownmacro`), title))
		expect(stored).toBe('<div class="confluence-unsupported-macro">Unsupported macro: <code>sql</code></div>')
	})

	// ── REQ-CFLI-007 dry-run ────────────────────────────────────────────

	// @e2e confluence-html-import::dry-run-returns-page-count-and-asset-folder
	test('dry-run reports 150 pages and 42 attachments and writes nothing', async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT)
		const pages = rootPages(`${STAMP} dry`, 'dry', 150)
		const assets: ZipEntry[] = []
		for (let i = 1; i <= 42; i++) {
			assets.push({ name: `attachments/dry${i}/file${i}.png`, content: Buffer.from([i]) })
		}
		const archive = exportArchive(pages, assets)

		// The least privileged logged-in account is refused, and wrote nothing.
		const refused = await upload(member!.api, 'dry-run', archive)
		expect(refused.status, 'a non-admin reached the dry-run').toBe(403)

		const before = (await allDashboards(admin)).length
		const dry = await upload(admin, 'dry-run', archive)
		expect(dry.status, JSON.stringify(dry.body)).toBe(200)
		expect(Object.keys(dry.body).sort()).toEqual(
			['assetFolder', 'attachmentCount', 'estimatedDashboards', 'pageCount', 'warnings'],
		)
		expect(dry.body.pageCount).toBe(150)
		expect(dry.body.attachmentCount).toBe(42)
		expect(dry.body.estimatedDashboards).toBe(150)
		expect(dry.body.warnings).toEqual([])
		expect(dry.body.assetFolder).toMatch(ASSET_FOLDER)

		// No dashboards (so no widgets), for the admin or the member.
		expect(await dashboardsNamed(admin, `${STAMP} dry`)).toEqual([])
		expect((await allDashboards(admin)).length).toBe(before)
		expect(await dashboardsNamed(member!.api, STAMP)).toEqual([])

		// No files: the asset folder it names does not exist.
		const folder = await admin.fetch(
			`/remote.php/dav/files/${encodeURIComponent(ADMIN_USER)}/${dry.body.assetFolder}`,
			{ method: 'PROPFIND', headers: { Depth: '0' } },
		)
		expect(folder.status(), 'dry-run created the asset folder').toBe(404)
	})

	// @e2e confluence-html-import::dry-run-surfaces-parser-warnings
	test('dry-run carries one warning per page it could not parse', async () => {
		const archive = exportArchive({
			'SPACE/good.html': confluencePage(`${STAMP} warn Good`, '<p>good</p>'),
			'SPACE/blank-one.html': '   ',
			'SPACE/blank-two.html': '',
		})
		const dry = await upload(admin, 'dry-run', archive)
		expect(dry.status, JSON.stringify(dry.body)).toBe(200)
		expect(dry.body.pageCount).toBe(1)
		expect(dry.body.warnings.length).toBe(2)
		expect(dry.body.warnings.join('\n')).toContain('SPACE/blank-one.html')
		expect(dry.body.warnings.join('\n')).toContain('SPACE/blank-two.html')
	})

	// ── REQ-CFLI-008 synchronous import ─────────────────────────────────

	// @e2e confluence-html-import::sync-import-returns-counts
	test('import answers 200 with the counts, and the dashboards already exist', async () => {
		const prefix = `${STAMP} sync`
		const archive = exportArchive(rootPages(prefix, 'sync', 3))

		const refused = await upload(member!.api, 'import', archive)
		expect(refused.status, 'a non-admin reached the import').toBe(403)
		expect(await dashboardsNamed(member!.api, STAMP)).toEqual([])
		expect(await dashboardsNamed(admin, prefix)).toEqual([])

		const run = await upload(admin, 'import', archive)
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(Object.keys(run.body).sort()).toEqual(
			['assetFolder', 'createdDashboardCount', 'errors', 'skippedPageCount', 'warnings'],
		)
		expect(run.body.createdDashboardCount).toBe(3)
		expect(run.body.skippedPageCount).toBe(0)
		expect(run.body.errors).toEqual([])
		expect(run.body.warnings).toEqual([])
		expect(run.body.assetFolder).toMatch(ASSET_FOLDER)

		// Processed in the request: the dashboards and their widgets are
		// there the moment the response is, with no job to wait for.
		const made = await dashboardsNamed(admin, prefix)
		expect(made.map((d) => d.name).sort()).toEqual([`${prefix} 001`, `${prefix} 002`, `${prefix} 003`])
		for (const d of made) {
			expect(await storedHtml(admin, d)).toBe(`<p>${d.name.slice(-3)}</p>`)
		}
	})

	// @e2e confluence-html-import::async-path-is-documented-as-future-work
	test('more than 100 pages still import synchronously: 200, not 202 and a job', async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT)
		const prefix = `${STAMP} big`
		const run = await upload(admin, 'import', exportArchive(rootPages(prefix, 'big', 101)))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(101)
		expect(run.body.skippedPageCount).toBe(0)
		expect(run.body).not.toHaveProperty('jobId')

		expect((await dashboardsNamed(admin, prefix)).length).toBe(101)
	})

	// ── REQ-CFLI-009 resilience ─────────────────────────────────────────

	/**
	 * A chain d1 → … → d5 (the deepest the tree allows), so any page whose
	 * breadcrumb puts it under d5 fails on the depth cap: a real, per-page
	 * failure the importer cannot avoid.
	 *
	 * @param prefix the stamped title prefix.
	 * @param id a filename-safe page-id prefix.
	 * @return archive path → page HTML for d1..d5.
	 */
	function depthChain(prefix: string, id: string): Record<string, string> {
		const pages: Record<string, string> = {}
		for (let level = 1; level <= 5; level++) {
			const ancestors = []
			for (let up = 1; up < level; up++) {
				ancestors.push(`${id}d${up}.html`)
			}
			pages[`SPACE/${id}d${level}.html`] = confluencePage(
				`${prefix} depth ${level}`,
				`<p>d${level}</p>`,
				level === 1 ? '' : breadcrumbs(ancestors, `d${level}`),
			)
		}
		return pages
	}

	/**
	 * A page placed under d5 by its breadcrumb, so it cannot be created.
	 *
	 * @param prefix the stamped title prefix.
	 * @param id the chain's page-id prefix.
	 * @param name the failing page's own id suffix.
	 * @return the page HTML.
	 */
	function tooDeep(prefix: string, id: string, name: string): string {
		const ancestors = [1, 2, 3, 4, 5].map((up) => `${id}d${up}.html`)
		return confluencePage(`${prefix} ${name}`, `<p>${name}</p>`, breadcrumbs(ancestors, name))
	}

	// @e2e confluence-html-import::per-page-failure-is-captured-in-the-response
	test('one failing page is rolled back, reported, logged, and the rest import', async () => {
		const prefix = `${STAMP} fail`
		const id = `f${RUN}`
		const failing = `${id}deep`
		const pages = depthChain(prefix, id)
		pages[`SPACE/${failing}.html`] = tooDeep(prefix, id, 'Too Deep')
		// Its child: the parent never imported, so it lands at the root.
		pages[`SPACE/${id}after.html`] = confluencePage(
			`${prefix} After`,
			'<p>after</p>',
			breadcrumbs([`${failing}.html`], 'After'),
		)
		pages[`SPACE/${id}sibling.html`] = confluencePage(`${prefix} Sibling`, '<p>sibling</p>')

		const run = await upload(admin, 'import', exportArchive(pages))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(7)
		expect(run.body.skippedPageCount).toBe(1)
		expect(run.body.errors).toEqual([{ pageId: failing, reason: expect.stringMatching(/\S/) }])

		const made = await dashboardsNamed(admin, prefix)
		// Rolled back: no row for the failed page survives.
		expect(made.filter((d) => d.name === `${prefix} Too Deep`)).toEqual([])
		// The rest carried on, widgets and all.
		expect(only(made, `${prefix} After`).parentUuid).toBeNull()
		expect(await storedHtml(admin, only(made, `${prefix} After`))).toBe('<p>after</p>')
		expect(await storedHtml(admin, only(made, `${prefix} Sibling`))).toBe('<p>sibling</p>')
		expect(await storedHtml(admin, only(made, `${prefix} depth 5`))).toBe('<p>d5</p>')

		// Logged as a warning with the page id and the exception.
		const logged = (await serverLog(admin)).filter((line) => line.includes(failing))
		expect(logged.length, `a log line names ${failing}`).toBeGreaterThan(0)
		const text = logged.join('\n')
		expect(text).toMatch(/Confluence page skipped during import/)
		expect(text).toContain(run.body.errors[0].reason)
		// Nextcloud writes level 2 for warning().
		expect(text).toMatch(/"level"\s*:\s*2/)
	})

	// @e2e confluence-html-import::final-report-includes-all-errors
	test('100 pages with 5 failing: 95 created, 5 skipped, 5 errors', async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT)
		const prefix = `${STAMP} report`
		const id = `r${RUN}`
		const pages = { ...depthChain(prefix, id), ...rootPages(prefix, `${id}root`, 90) }
		const failing: string[] = []
		for (let i = 1; i <= 5; i++) {
			const pageId = `${id}deep${i}`
			failing.push(pageId)
			pages[`SPACE/${pageId}.html`] = tooDeep(prefix, id, `Too Deep ${i}`)
		}
		expect(Object.keys(pages).length).toBe(100)

		const run = await upload(admin, 'import', exportArchive(pages))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(95)
		expect(run.body.skippedPageCount).toBe(5)
		expect(run.body.errors.length).toBe(5)
		expect(run.body.errors.map((e: { pageId: string }) => e.pageId).sort()).toEqual(failing.sort())

		expect((await dashboardsNamed(admin, prefix)).length).toBe(95)
	})

	// ── REQ-CFLI-010 re-import ──────────────────────────────────────────

	// @e2e confluence-html-import::second-import-creates-new-dashboards
	test('importing the same archive again creates new dashboards with suffixed slugs', async () => {
		test.setTimeout(BIG_IMPORT_TIMEOUT * 2)
		const prefix = `${STAMP} again`
		const archive = exportArchive(rootPages(prefix, 'p', 100))

		const first = await upload(admin, 'import', archive)
		expect(first.status, JSON.stringify(first.body)).toBe(200)
		expect(first.body.createdDashboardCount).toBe(100)
		const firstRun = await dashboardsNamed(admin, prefix)
		expect(firstRun.length).toBe(100)
		/** The fields an in-place update would change. */
		const fingerprint = (d: Dashboard): string =>
			JSON.stringify([d.id, d.name, d.slug, d.parentUuid, d.sortOrder, d.updatedAt])
		const snapshot = new Map(firstRun.map((d) => [d.uuid, fingerprint(d)]))

		const second = await upload(admin, 'import', archive)
		expect(second.status, JSON.stringify(second.body)).toBe(200)
		expect(second.body.createdDashboardCount).toBe(100)
		expect(second.body.errors).toEqual([])

		const afterSecond = await dashboardsNamed(admin, prefix)
		expect(afterSecond.length).toBe(200)
		// The first run's dashboards are untouched, not updated in place.
		for (const [uuid, json] of snapshot) {
			const still = afterSecond.find((d) => d.uuid === uuid)
			expect(still, `first-run dashboard ${uuid} is gone`).toBeDefined()
			expect(fingerprint(still as Dashboard), `first-run dashboard ${uuid} changed`).toBe(json)
		}
		const fresh = afterSecond.filter((d) => !snapshot.has(d.uuid))
		expect(fresh.length).toBe(100)
		expect(new Set(fresh.map((d) => d.uuid)).size, 'every new UUID is distinct').toBe(100)

		// Slug collision: the second run's slug is the first's plus the page id.
		const firstOf = firstRun.find((d) => d.name === `${prefix} 001`)!
		const secondOf = fresh.find((d) => d.name === `${prefix} 001`)!
		expect(secondOf.slug).toBe(`${firstOf.slug}-p001`)

		// Even that taken: a third run adds a timestamp segment, and does not crash.
		const third = await upload(admin, 'import', archive)
		expect(third.status, JSON.stringify(third.body)).toBe(200)
		expect(third.body.createdDashboardCount).toBe(100)
		const thirdOf = (await dashboardsNamed(admin, `${prefix} 001`))
			.filter((d) => d.slug !== firstOf.slug && d.slug !== secondOf.slug)
		expect(thirdOf.length).toBe(1)
		expect(thirdOf[0].slug).toMatch(new RegExp(`^${firstOf.slug}-p001-\\d+$`))
	})

	// @e2e confluence-html-import::asset-folder-timestamp-differs-across-runs
	test('two imports of one archive report different asset folders', async () => {
		const archive = exportArchive(rootPages(`${STAMP} folder`, 'folder', 1))
		const one = await upload(admin, 'import', archive)
		const two = await upload(admin, 'import', archive)
		expect([one.status, two.status]).toEqual([200, 200])
		expect(one.body.assetFolder).toMatch(ASSET_FOLDER)
		expect(two.body.assetFolder).toMatch(ASSET_FOLDER)
		expect(two.body.assetFolder, 'each run needs its own folder').not.toBe(one.body.assetFolder)
	})

	// ── REQ-CFLI-012 sanitisation ───────────────────────────────────────

	/**
	 * Import one page and return the HTML stored in its widget.
	 *
	 * @param tag a distinct word for the page title.
	 * @param body the page body.
	 * @return the stored widget HTML.
	 */
	async function importBody(tag: string, body: string): Promise<string> {
		const title = `${STAMP} ${tag} Page`
		const run = await upload(admin, 'import', exportArchive({
			[`SPACE/${tag}.html`]: confluencePage(title, body),
		}))
		expect(run.status, JSON.stringify(run.body)).toBe(200)
		expect(run.body.createdDashboardCount).toBe(1)
		return storedHtml(admin, only(await dashboardsNamed(admin, `${STAMP} ${tag}`), title))
	}

	// @e2e confluence-html-import::allow-listed-tags-are-preserved
	test('allow-listed formatting survives unchanged', async () => {
		const body = '<p>text</p> <strong>bold</strong> <em>italic</em> <a href="/link">link</a> <ul><li>item</li></ul>'
		expect(await importBody('allowed', body)).toBe(body)
	})

	// @e2e confluence-html-import::disallowed-tags-are-unwrapped
	test('a disallowed wrapper is removed and its text kept', async () => {
		const stored = await importBody('unwrap', '<p><button>click</button> <label>me</label></p>')
		expect(stored).toBe('<p>click me</p>')
	})

	// @e2e confluence-html-import::script-bearing-tags-are-dropped-with-their-content
	test('script, style, iframe, object, embed and noscript go with their content', async () => {
		const stored = await importBody(
			'dropped',
			'<p>kept</p><script>PAYLOAD1</script><style>PAYLOAD2</style><iframe>PAYLOAD3</iframe>'
				+ '<object>PAYLOAD4</object><embed src="x.swf"><noscript>PAYLOAD6</noscript>',
		)
		expect(stored).toBe('<p>kept</p>')
		expect(stored).not.toMatch(/PAYLOAD|<script|<style|<iframe|<object|<embed|<noscript/i)
	})

	// @e2e confluence-html-import::event-handlers-and-javascript-urls-are-stripped
	test('on* handlers and javascript: URLs are removed', async () => {
		const stored = await importBody(
			'handlers',
			'<a href="javascript:alert(1)" onclick="alert(2)">x</a> <img src="javascript:alert(4)" onerror="alert(3)">',
		)
		expect(stored).toBe('<a>x</a> <img>')
		expect(stored).not.toMatch(/\son\w+=|javascript:/i)
	})

	// @e2e confluence-html-import::allow-list-covers-the-documented-vocabulary
	test('every documented tag survives, with only its allowed attributes', async () => {
		const body = '<h1 id="x">H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>'
			+ '<p style="color:red">P <strong>S</strong> <em>E</em> <b>B</b> <i>I</i><br>'
			+ '<span class="s" data-x="1">SP</span> <a href="/x" title="t" target="_blank" rel="r">A</a> '
			+ '<img src="i.png" alt="al" title="ti" width="10" height="20" data-x="1"></p>'
			+ '<ul><li>U</li></ul><ol><li>O</li></ol><blockquote cite="c">Q</blockquote>'
			+ '<pre class="p" id="pp"><code class="c" id="cc">C</code></pre>'
			+ '<table border="1"><thead><tr><th colspan="2" style="x">TH</th></tr></thead>'
			+ '<tbody><tr><td rowspan="1" colspan="2" width="3">TD</td></tr></tbody></table>'
			+ '<div class="d" id="i">D</div><details open><summary>SUM</summary>DET</details>'
		const expected = '<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>'
			+ '<p>P <strong>S</strong> <em>E</em> <b>B</b> <i>I</i><br>'
			+ '<span class="s">SP</span> <a href="/x" title="t">A</a> '
			+ '<img src="i.png" alt="al" title="ti" width="10" height="20"></p>'
			+ '<ul><li>U</li></ul><ol><li>O</li></ol><blockquote>Q</blockquote>'
			+ '<pre class="p"><code class="c">C</code></pre>'
			+ '<table><thead><tr><th colspan="2">TH</th></tr></thead>'
			+ '<tbody><tr><td rowspan="1" colspan="2">TD</td></tr></tbody></table>'
			+ '<div class="d">D</div><details><summary>SUM</summary>DET</details>'
		expect(await importBody('vocabulary', body)).toBe(expected)
	})
})
