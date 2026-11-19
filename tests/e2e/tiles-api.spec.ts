/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tile HTTP contracts (tiles REQ-TILE-001, -002, -005, -007, REQ-TLT-001,
 * REQ-TOB-001): the deprecated reusable-tile endpoints, placing a tile on a
 * dashboard, who may do that, the defaults a tile placement gets, the colours,
 * the program-scheme refusal and the link-target refusal.
 *
 * These scenarios ARE API contracts, so they are probed over HTTP with basic
 * auth as throwaway accounts. Every refusal is probed with the least
 * privileged logged-in account, re-read as the owner to prove nothing was
 * written, and paired with the allowed account succeeding. Admin settings this
 * file changes are read first and put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::write-endpoints-return-410-gone
 *   @e2e tiles::list-tiles-when-none-exist
 *   @e2e tiles::place-a-tile-on-a-dashboard
 *   @e2e tiles::place-the-same-tile-on-multiple-dashboards
 *   @e2e tiles::user-cannot-add-tile-to-another-users-dashboard
 *   @e2e tiles::tile-placement-defaults
 *   @e2e tiles::tile-placement-on-view-only-dashboard-blocked
 *   @e2e tiles::valid-hex-colors-accepted
 *   @e2e tiles::invalid-color-format-rejected
 *   @e2e tiles::default-colors-when-not-specified
 *   @e2e tiles::script-scheme-is-refused
 *   @e2e tiles::unknown-target-is-refused
 *
 * @spec openspec/specs/tiles/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	readPlacements,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const STAMP = `${Date.now()}`
const REPLACEMENT = 'POST /api/dashboards/{uuid}/widgets with type:tile'

let admin: APIRequestContext
let own: OwnDashboard
let stranger: Principal

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	own = await setUpOwnDashboard('e2e-tile-api', `E2E tile api ${STAMP}`)
	stranger = await createPrincipal(admin, 'e2e-tile-stranger')
	await assertActingAs(stranger.api, stranger.username)
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	await removePrincipal(admin, stranger)
	await admin.dispose()
})

/**
 * The list a `GET /api/tiles` answers with.
 *
 * @param api The caller.
 * @return The tiles.
 */
async function listTiles(api: APIRequestContext): Promise<unknown[]> {
	const res = await api.get(`${API}/tiles`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const list = Array.isArray(body) ? body : body.data
	expect(Array.isArray(list), JSON.stringify(body)).toBe(true)
	return list as unknown[]
}

/**
 * Place a tile and return the server's answer.
 *
 * @param api The caller.
 * @param dashboardId The dashboard.
 * @param data The request body.
 * @return Status and body.
 */
async function placeTile(
	api: APIRequestContext,
	dashboardId: number,
	data: Record<string, unknown>,
): Promise<{ status: number; body: Record<string, any> }> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/tile`, { data })
	const text = await res.text()
	let body: Record<string, any>
	try {
		body = JSON.parse(text)
	} catch {
		body = { raw: text }
	}
	return {
		status: res.status(),
		body: body.data && body.data.id ? body.data : body,
	}
}

// @e2e tiles::write-endpoints-return-410-gone
test('POST, PUT and DELETE on /api/tiles answer 410 Gone with the replacement and write nothing', async () => {
	const before = await listTiles(admin)

	const writes = [
		admin.post(`${API}/tiles`, {
			data: {
				title: `E2E gone ${STAMP}`,
				linkType: 'url',
				linkValue: 'https://example.org',
			},
		}),
		admin.put(`${API}/tiles/1`, { data: { title: `E2E gone ${STAMP}` } }),
		admin.delete(`${API}/tiles/1`),
	]
	for (const pending of writes) {
		const res = await pending
		expect(res.status(), res.url()).toBe(410)
		const body = await res.json()
		expect(body.status).toBe('gone')
		expect(typeof body.message).toBe('string')
		expect(body.message.length).toBeGreaterThan(0)
		expect(body.replacement).toBe(REPLACEMENT)
	}

	expect(await listTiles(admin)).toEqual(before)
})

// @e2e tiles::list-tiles-when-none-exist
test('an account without tiles gets 200 and an empty list', async () => {
	expect(await listTiles(stranger.api)).toEqual([])
})

// @e2e tiles::place-a-tile-on-a-dashboard
test.fixme('a tile posted with the documented body becomes a custom tile placement carrying that data', async () => {
	// Product disagrees: the server reads `title`, `icon`, `iconType`,
	// `bgColor`, `textColor`, `linkType`, `linkValue` (lib/Controller/RequestDataExtractor.php:36-45),
	// not the `tileTitle`/`tileIcon`/... names this scenario posts, so the
	// placement is stored as "New Tile" with the default icon, colours and link.
	const { status, body } = await placeTile(own.owner.api, own.id, {
		tileTitle: 'My Files',
		tileIcon: 'icon-folder',
		tileIconType: 'class',
		tileBackgroundColor: '#3b82f6',
		tileTextColor: '#ffffff',
		tileLinkType: 'app',
		tileLinkValue: '/apps/files',
		gridX: 0,
		gridY: 0,
		gridWidth: 2,
		gridHeight: 2,
	})
	expect(status).toBe(201)
	expect(body.tileType).toBe('custom')
	expect(body.widgetId).toMatch(/^tile-[0-9a-f]{13}$/)
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === Number(body.id),
	)
	expect(stored).toMatchObject({
		tileType: 'custom',
		tileTitle: 'My Files',
		tileIcon: 'icon-folder',
		tileIconType: 'class',
		tileBackgroundColor: '#3b82f6',
		tileTextColor: '#ffffff',
		tileLinkType: 'app',
		tileLinkValue: '/apps/files',
	})
})

// @e2e tiles::tile-placement-defaults
test('a tile placement is 2x2, not compulsory and visible by default', async () => {
	const { status, body } = await placeTile(own.owner.api, own.id, {
		title: `E2E defaults ${STAMP}`,
		linkType: 'url',
		linkValue: 'https://example.org/',
		gridX: 0,
		gridY: 20,
	})
	expect(status, JSON.stringify(body)).toBe(201)
	expect(body.tileType).toBe('custom')
	expect(body.widgetId).toMatch(/^tile-[0-9a-f]{13}$/)

	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === Number(body.id),
	)!
	expect(Number(stored.gridWidth)).toBe(2)
	expect(Number(stored.gridHeight)).toBe(2)
	expect(Number(stored.isCompulsory)).toBe(0)
	expect(Number(stored.isVisible)).toBe(1)
	expect(stored.tileTitle).toBe(`E2E defaults ${STAMP}`)
})

// @e2e tiles::place-the-same-tile-on-multiple-dashboards
test('the same tile placed on two dashboards is two independent copies', async () => {
	const second = await own.owner.api.post(`${API}/dashboard`, {
		data: { name: `E2E second ${STAMP}` },
	})
	expect(second.status(), await second.text()).toBeLessThan(300)
	const json = await second.json()
	const otherId = Number((json.dashboard ?? json.data?.dashboard ?? json).id)
	try {
		const tile = {
			title: `E2E twice ${STAMP}`,
			icon: 'icon-folder',
			iconType: 'class',
			bgColor: '#3b82f6',
			textColor: '#ffffff',
			linkType: 'app',
			linkValue: 'files',
			gridX: 4,
			gridY: 20,
		}
		const first = await placeTile(own.owner.api, own.id, tile)
		const copy = await placeTile(own.owner.api, otherId, tile)
		expect(first.status).toBe(201)
		expect(copy.status).toBe(201)
		expect(Number(copy.body.id)).not.toBe(Number(first.body.id))
		expect(copy.body.widgetId).not.toBe(first.body.widgetId)
		for (const placed of [first.body, copy.body]) {
			expect(placed).toMatchObject({
				tileTitle: tile.title,
				tileIcon: 'icon-folder',
				tileLinkValue: 'files',
			})
		}

		const removed = await own.owner.api.delete(`${API}/widgets/${first.body.id}`)
		expect(removed.status(), await removed.text()).toBeLessThan(300)

		expect(
			(await readPlacements(own.owner.api, own.id)).some(
				(p) => Number(p.id) === Number(first.body.id),
			),
		).toBe(false)
		const kept = (await readPlacements(own.owner.api, otherId)).find(
			(p) => Number(p.id) === Number(copy.body.id),
		)
		expect(kept).toMatchObject({
			tileType: 'custom',
			tileTitle: tile.title,
			tileLinkValue: 'files',
		})
	} finally {
		await own.owner.api.delete(`${API}/dashboard/${otherId}`)
	}
})

// @e2e tiles::user-cannot-add-tile-to-another-users-dashboard
test("someone else cannot place a tile on a person's dashboard; the owner can", async () => {
	const before = (await readPlacements(own.owner.api, own.id)).length
	const refused = await placeTile(stranger.api, own.id, {
		title: `E2E intruder ${STAMP}`,
		linkType: 'url',
		linkValue: 'https://example.org/',
	})
	expect(refused.status).toBe(403)
	const after = await readPlacements(own.owner.api, own.id)
	expect(after).toHaveLength(before)
	expect(after.some((p) => p.tileTitle === `E2E intruder ${STAMP}`)).toBe(false)

	const allowed = await placeTile(own.owner.api, own.id, {
		title: `E2E owner ${STAMP}`,
		linkType: 'url',
		linkValue: 'https://example.org/',
		gridX: 8,
		gridY: 20,
	})
	expect(allowed.status).toBe(201)
	expect(await readPlacements(own.owner.api, own.id)).toHaveLength(before + 1)
})

// @e2e tiles::tile-placement-on-view-only-dashboard-blocked
test('a view-only share refuses a tile with 403; the owner of the dashboard can add it', async () => {
	const seeded = await seedDashboardWithWidget(
		admin,
		`E2E view-only tiles ${STAMP}`,
	)
	try {
		await shareWith(admin, seeded.id, stranger.username, 'view_only')
		const before = (await readPlacements(admin, seeded.id)).length
		const refused = await placeTile(stranger.api, seeded.id, {
			title: `E2E viewer ${STAMP}`,
			linkType: 'url',
			linkValue: 'https://example.org/',
		})
		expect(refused.status).toBe(403)
		expect(await readPlacements(admin, seeded.id)).toHaveLength(before)

		const allowed = await placeTile(admin, seeded.id, {
			title: `E2E owner tile ${STAMP}`,
			linkType: 'url',
			linkValue: 'https://example.org/',
			gridX: 6,
			gridY: 0,
		})
		expect(allowed.status).toBe(201)
		expect(await readPlacements(admin, seeded.id)).toHaveLength(before + 1)
	} finally {
		await admin.delete(`${API}/dashboard/${seeded.id}`)
	}
})

// @e2e tiles::valid-hex-colors-accepted
test('hex background and text colours are accepted and stored', async () => {
	const { status, body } = await placeTile(own.owner.api, own.id, {
		title: `E2E colours ${STAMP}`,
		bgColor: '#3b82f6',
		textColor: '#ffffff',
		linkType: 'url',
		linkValue: 'https://example.org/',
		gridX: 0,
		gridY: 24,
	})
	expect(status, JSON.stringify(body)).toBe(201)
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === Number(body.id),
	)!
	expect(stored.tileBackgroundColor).toBe('#3b82f6')
	expect(stored.tileTextColor).toBe('#ffffff')
})

// @e2e tiles::invalid-color-format-rejected
test.fixme('a background colour that is not a colour is refused with 400', async () => {
	// Product disagrees: no colour validation exists — TileUpdater::applyTileConfig
	// stores `bgColor` as given (lib/Service/TileUpdater.php:66-68). The
	// scenario itself notes "Color format validation is NOT currently implemented".
	const before = (await readPlacements(own.owner.api, own.id)).length
	const { status } = await placeTile(own.owner.api, own.id, {
		title: `E2E bad colour ${STAMP}`,
		bgColor: 'not-a-color',
		linkType: 'url',
		linkValue: 'https://example.org/',
	})
	expect(status).toBe(400)
	expect(await readPlacements(own.owner.api, own.id)).toHaveLength(before)
})

// @e2e tiles::default-colors-when-not-specified
test('without colours a tile gets #0082c9 on #ffffff', async () => {
	const { status, body } = await placeTile(own.owner.api, own.id, {
		title: `E2E no colours ${STAMP}`,
		linkType: 'url',
		linkValue: 'https://example.org/',
		gridX: 2,
		gridY: 24,
	})
	expect(status, JSON.stringify(body)).toBe(201)
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === Number(body.id),
	)!
	expect(stored.tileBackgroundColor).toBe('#0082c9')
	expect(stored.tileTextColor).toBe('#ffffff')
})

// @e2e tiles::script-scheme-is-refused
test('a program tile with a javascript: address is refused with 400 whatever the administrator allows', async () => {
	const settings = await admin.get(`${API}/admin/settings`)
	const current = await settings.json()
	const schemesBefore = ((current.data ?? current).tileAllowedSchemes
		?? []) as string[]
	try {
		// An administrator cannot even put javascript on the list.
		const listed = await admin.put(`${API}/admin/settings`, {
			data: { tileAllowedSchemes: ['javascript', 'ms-word'] },
		})
		expect(listed.status()).toBe(400)
		const set = await admin.put(`${API}/admin/settings`, {
			data: { tileAllowedSchemes: ['ms-word'] },
		})
		expect(set.status(), await set.text()).toBe(200)

		const before = (await readPlacements(own.owner.api, own.id)).length
		const refused = await placeTile(own.owner.api, own.id, {
			title: `E2E script ${STAMP}`,
			linkType: 'program',
			linkValue: 'javascript:alert(1)',
		})
		expect(refused.status).toBe(400)
		expect(await readPlacements(own.owner.api, own.id)).toHaveLength(before)

		const allowed = await placeTile(own.owner.api, own.id, {
			title: `E2E word ${STAMP}`,
			linkType: 'program',
			linkValue: 'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx',
			gridX: 4,
			gridY: 24,
		})
		expect(allowed.status, JSON.stringify(allowed.body)).toBe(201)
		expect(await readPlacements(own.owner.api, own.id)).toHaveLength(before + 1)
	} finally {
		await admin.put(`${API}/admin/settings`, {
			data: { tileAllowedSchemes: schemesBefore },
		})
	}
})

// @e2e tiles::unknown-target-is-refused
test('PUT /api/widgets/{id} with linkTarget "popup" answers 400 and keeps the stored target', async () => {
	const { status, body } = await placeTile(own.owner.api, own.id, {
		title: `E2E target ${STAMP}`,
		linkType: 'url',
		linkValue: 'https://example.org/',
		gridX: 6,
		gridY: 24,
	})
	expect(status).toBe(201)
	const id = Number(body.id)
	const set = await own.owner.api.put(`${API}/widgets/${id}`, {
		data: { content: { linkTarget: 'new-tab' } },
	})
	expect(set.status(), await set.text()).toBe(200)

	const refused = await own.owner.api.put(`${API}/widgets/${id}`, {
		data: { content: { linkTarget: 'popup' } },
	})
	expect(refused.status()).toBe(400)

	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === id,
	)!
	const content =
		typeof stored.content === 'string'
			? JSON.parse(stored.content)
			: stored.content
	expect(content.linkTarget).toBe('new-tab')
})
