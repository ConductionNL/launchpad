/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget REST contract: discovery, items, and placement create / update /
 * remove / batch (openspec/specs/widgets/spec.md, REQ-WDG-001..008), written
 * for the e2e-coverage-programme.
 *
 * WHY THE API. These scenarios ARE the HTTP contract: status codes, defaults
 * on a created row, full-replacement semantics, who is refused. Every write is
 * re-read through `GET /api/dashboard/{id}`; every refusal is probed by an
 * ordinary signed-in stranger (bob), re-read as the owner, and paired with the
 * owner (alice, also an ordinary account) doing the same thing successfully.
 *
 * ORACLES FROM NEXTCLOUD ITSELF. "Every widget Nextcloud registers" and "items
 * in Nextcloud's item format" are checked against Nextcloud's own dashboard OCS
 * API (`/ocs/v2.php/apps/dashboard/api/...`) rather than against a hard-coded
 * list, so the test means the same thing on any app set.
 *
 * WHY ALICE ADDS `recommendations`. A spec elsewhere (add-widget-modal) leaves a
 * `default` role-feature restriction on the shared instance that allows only
 * `activity` and `recommendations` for non-admins. `recommendations` is
 * allowed with or without that row, so the owner's writes do not depend on it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::list-all-available-widgets
 *   @e2e widgets::widget-list-includes-v1-and-v2-widgets
 *   @e2e widgets::widget-list-updates-when-apps-are-installed
 *   @e2e widgets::widget-formatting-via-widgetformatter
 *   @e2e widgets::fetch-items-for-a-v2-widget
 *   @e2e widgets::fetch-items-for-a-v1-widget
 *   @e2e widgets::fetch-items-for-unknown-widget
 *   @e2e widgets::widget-items-endpoint-requires-no-csrf
 *   @e2e widgets::add-a-widget-to-a-dashboard
 *   @e2e widgets::add-a-widget-with-custom-title-and-styling
 *   @e2e widgets::add-widget-to-another-users-dashboard
 *   @e2e widgets::add-widget-with-non-existent-widgetid
 *   @e2e widgets::update-widget-position-and-size
 *   @e2e widgets::update-style-configuration
 *   @e2e widgets::update-placement-on-another-users-dashboard
 *   @e2e widgets::update-tile-specific-fields
 *   @e2e widgets::remove-a-widget-placement
 *   @e2e widgets::remove-a-compulsory-widget-with-full-permission
 *   @e2e widgets::remove-a-compulsory-widget-without-full-permission
 *   @e2e widgets::remove-another-users-widget-placement
 *   @e2e widgets::remove-widget-cascade-deletes-conditional-rules
 *   @e2e widgets::auto-assign-sort-order-on-creation
 *   @e2e widgets::batch-update-after-grid-rearrangement
 *   @e2e widgets::batch-update-with-mixed-placement-types
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Browser } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { OwnDashboard, Placement } from './support/widgets.ts'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { expectOpenDashboard } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	contextFor,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'
import {
	createOwnDashboard,
	placementOf,
	placementsOf,
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
	seedTile,
} from './support/widgets.ts'

const STAMP = `E2E Widgets ${Date.now()}`
const NC_DASHBOARD = '/ocs/v2.php/apps/dashboard/api'
const ALLOWED_WIDGET = 'recommendations'

/** One entry of Nextcloud's own `GET .../api/v1/widgets`. */
interface NcWidget {
	id: string
	title: string
	order: number
	item_api_versions?: number[]
}

/** One entry of LaunchPad's `GET /api/widgets`. */
interface LpWidget {
	id: string
	title: string
	order: number
	iconClass: string
	iconUrl: string | null
	widgetUrl: string | null
	itemIconsRound: boolean
	itemApiVersions: number[]
	reloadInterval: number
	buttons: unknown[]
}

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let aliceDash: OwnDashboard
let allowBefore: boolean | null = null

/**
 * A new dashboard with no widgets: creating one places the default bundle,
 * which these tests would otherwise count and collide with.
 *
 * @param api the owner's context.
 * @param name the dashboard name.
 * @return the dashboard.
 */
async function emptyDashboard(api: APIRequestContext, name: string): Promise<OwnDashboard> {
	const dash = await createOwnDashboard(api, name)
	await clearSeededWidgets(api, dash.id)
	return dash
}

/**
 * Nextcloud's own widget catalogue for the admin, keyed by id.
 *
 * @return the widgets Nextcloud registers.
 */
async function ncWidgets(): Promise<Record<string, NcWidget>> {
	const res = await admin.get(`${NC_DASHBOARD}/v1/widgets?format=json`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).ocs.data as Record<string, NcWidget>
}

/**
 * LaunchPad's widget catalogue for the admin (unfiltered: admins are exempt
 * from role-feature restrictions).
 *
 * @return the response status and body.
 */
async function lpWidgets(): Promise<{ status: number; body: LpWidget[] }> {
	const res = await admin.get(`${API}/widgets`)
	return { status: res.status(), body: (await res.json()) as LpWidget[] }
}

/**
 * Enabled app ids, from the provisioning API.
 *
 * @return the ids.
 */
async function enabledApps(): Promise<string[]> {
	const res = await admin.get('/ocs/v1.php/cloud/apps?filter=enabled&format=json')
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).ocs.data.apps as string[]
}

/**
 * Enable or disable a shipped app.
 *
 * @param appId the app.
 * @param enabled the wanted state.
 * @return nothing.
 */
async function setAppEnabled(appId: string, enabled: boolean): Promise<void> {
	const url = `/ocs/v1.php/cloud/apps/${appId}?format=json`
	const res = enabled ? await admin.post(url) : await admin.delete(url)
	expect(res.status(), await res.text()).toBe(200)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	await allowPersonalDashboards(admin, true)
	alice = await createPrincipal(admin, 'e2e-wdg-alice')
	bob = await createPrincipal(admin, 'e2e-wdg-bob')
	await assertActingAs(alice.api, alice.username)
	await assertActingAs(bob.api, bob.username)
	aliceDash = await emptyDashboard(alice.api, `${STAMP} alice`)
})

test.afterAll(async () => {
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	await restoreAllowUserDashboards(admin, allowBefore)
	await admin.dispose()
})

test.describe('REQ-WDG-001 discover available widgets', () => {
	// @e2e widgets::list-all-available-widgets
	test('GET /api/widgets lists every widget Nextcloud registers, with id, title and iconUrl', async () => {
		const nc = await ncWidgets()
		const { status, body } = await lpWidgets()

		expect(status).toBe(200)
		expect(Array.isArray(body)).toBe(true)
		expect(
			Object.keys(nc).length,
			'Nextcloud registers no widgets at all',
		).toBeGreaterThan(0)
		// Every widget every enabled app registers, no more and no fewer.
		expect(body.map((w) => w.id).sort()).toEqual(Object.keys(nc).sort())
		for (const widget of body) {
			expect(widget).toHaveProperty('id')
			expect(widget).toHaveProperty('iconUrl')
			expect(widget.title, `${widget.id} has no title`).toBe(
				nc[widget.id].title,
			)
		}
	})

	// @e2e widgets::widget-list-includes-v1-and-v2-widgets
	test('each widget states which item API versions it implements, as Nextcloud does', async () => {
		const nc = await ncWidgets()
		const { status, body } = await lpWidgets()
		expect(status).toBe(200)

		for (const widget of body) {
			expect(Array.isArray(widget.itemApiVersions)).toBe(true)
			expect(
				[...widget.itemApiVersions].sort(),
				`${widget.id} reports the wrong API versions`,
			).toEqual([...(nc[widget.id].item_api_versions ?? [])].sort())
		}
		// Both kinds are present on a stock server: a v2 widget and one
		// that answers through the v1 interface.
		expect(body.some((w) => w.itemApiVersions.includes(2))).toBe(true)
		expect(body.some((w) => w.itemApiVersions.includes(1))).toBe(true)
	})

	// @e2e widgets::widget-list-updates-when-apps-are-installed
	test('an app that comes up adds its widget and every earlier widget stays', async () => {
		// `recommendations` ships with Nextcloud and registers one widget under
		// its own id (`weather_status` no longer registers any). Taking it down
		// and bringing it back up is the installable event a shared CI instance
		// can perform without the app store.
		const app = 'recommendations'
		const wasEnabled = (await enabledApps()).includes(app)
		try {
			await setAppEnabled(app, false)
			const without = await lpWidgets()
			expect(without.status).toBe(200)
			expect(without.body.map((w) => w.id)).not.toContain(app)

			await setAppEnabled(app, true)
			const withIt = await lpWidgets()
			expect(withIt.status).toBe(200)
			const ids = withIt.body.map((w) => w.id)
			expect(ids).toContain(app)
			for (const earlier of without.body.map((w) => w.id)) {
				expect(ids, `${earlier} disappeared`).toContain(earlier)
			}
		} finally {
			await setAppEnabled(app, wasEnabled)
		}
	})

	// @e2e widgets::widget-formatting-via-widgetformatter
	test('every widget carries the standard frontend fields and the list is sorted by order', async () => {
		const nc = await ncWidgets()
		const { status, body } = await lpWidgets()
		expect(status).toBe(200)

		for (const widget of body) {
			expect(Object.keys(widget).sort()).toEqual([
				'buttons',
				'iconClass',
				'iconUrl',
				'id',
				'itemApiVersions',
				'itemIconsRound',
				'order',
				'reloadInterval',
				'title',
				'widgetUrl',
			])
			expect(widget.order).toBe(nc[widget.id].order)
			expect(Array.isArray(widget.buttons)).toBe(true)
			expect(typeof widget.itemIconsRound).toBe('boolean')
			expect(typeof widget.reloadInterval).toBe('number')
		}
		const orders = body.map((w) => w.order)
		expect(orders).toEqual([...orders].sort((a, b) => a - b))
	})
})

test.describe('REQ-WDG-002 fetch widget items', () => {
	let statusUser: { username: string; password: string } | null = null
	const message = `${STAMP} status`

	test.beforeAll(async () => {
		// A user with a fresh status gives the `user_status` widget an item
		// for the admin, so "the items" is never an empty list.
		statusUser = await provisionThrowawayUser('e2e-wdg-status')
		const as = await contextFor(statusUser.username, statusUser.password)
		try {
			const online = await as.put(
				'/ocs/v2.php/apps/user_status/api/v1/user_status/status?format=json',
				{
					data: { statusType: 'online' },
				},
			)
			expect(online.status(), await online.text()).toBe(200)
			const custom = await as.put(
				'/ocs/v2.php/apps/user_status/api/v1/user_status/message/custom?format=json',
				{
					data: { message },
				},
			)
			expect(custom.status(), await custom.text()).toBe(200)
		} finally {
			await as.dispose()
		}
	})

	test.afterAll(async () => {
		if (statusUser) {
			await deprovisionUser(statusUser.username)
		}
	})

	// @e2e widgets::fetch-items-for-a-v2-widget
	test("a v2 widget answers with the items Nextcloud's v2 loader returns, in the widget item format", async () => {
		const nc = await ncWidgets()
		expect(nc.user_status?.item_api_versions ?? []).toContain(2)

		const res = await admin.get(
			`${API}/widgets/items?widgets[]=user_status&limit=7`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const items = body.user_status.items as Array<Record<string, unknown>>
		expect(items.length).toBeGreaterThan(0)
		for (const item of items) {
			for (const key of ['title', 'subtitle', 'link', 'iconUrl']) {
				expect(item, `item lacks ${key}`).toHaveProperty(key)
			}
		}
		expect(items.map((i) => i.title)).toContain(statusUser!.username)

		const ncItems = await admin.get(
			`${NC_DASHBOARD}/v2/widget-items?widgets[]=user_status&limit=7&format=json`,
		)
		expect(ncItems.status()).toBe(200)
		const expected = (await ncItems.json()).ocs.data.user_status.items as Array<
			Record<string, unknown>
		>
		expect(items.map((i) => [i.title, i.subtitle, i.link])).toEqual(
			expected.map((i) => [i.title, i.subtitle, i.link]),
		)
	})

	// @e2e widgets::fetch-items-for-a-v1-widget
	test('a v1-only widget answers through the v1 loader, or says it loads no items', async () => {
		const nc = await ncWidgets()
		const v1Only = Object.values(nc).find((w) => {
			const versions = w.item_api_versions ?? []
			return versions.includes(1) && !versions.includes(2)
		})
		const noApi = Object.values(nc).find(
			(w) => (w.item_api_versions ?? []).length === 0,
		)
		const widget = v1Only ?? noApi
		expect(widget, 'no widget on this server lacks the v2 API').toBeTruthy()

		const res = await admin.get(
			`${API}/widgets/items?widgets[]=${encodeURIComponent(widget!.id)}`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const entry = (await res.json())[widget!.id]
		expect(Array.isArray(entry.items)).toBe(true)

		if (v1Only) {
			// THEN: the v1 callback mechanism — the same items Nextcloud's v1
			// endpoint returns for this widget.
			const ncItems = await admin.get(
				`${NC_DASHBOARD}/v1/widget-items?widgets[]=${encodeURIComponent(widget!.id)}&format=json`,
			)
			expect(ncItems.status()).toBe(200)
			const expected = ((await ncItems.json()).ocs.data[widget!.id]
				?? []) as Array<Record<string, unknown>>
			expect(
				entry.items.map((i: Record<string, unknown>) => [i.title, i.link]),
			).toEqual(expected.map((i) => [i.title, i.link]))
		} else {
			// OR: the widget does not support item loading.
			expect(entry).toEqual({
				items: [],
				emptyContentMessage: '',
				halfEmptyContentMessage: '',
			})
		}
	})

	// @e2e widgets::fetch-items-for-unknown-widget
	test('an unknown widget id is skipped and the valid one in the same request still answers', async () => {
		const res = await admin.get(
			`${API}/widgets/items?widgets[]=nonexistent_widget&widgets[]=user_status`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body)).toEqual(['user_status'])
		expect(body.user_status.items.length).toBeGreaterThan(0)
	})

	// @e2e widgets::widget-items-endpoint-requires-no-csrf
	test('the items endpoint answers a session request that carries no CSRF token', async ({
		page,
	}) => {
		// `page.request` rides the admin's browser session cookie and sends
		// neither `requesttoken` nor `OCS-APIRequest`, the two things that
		// satisfy Nextcloud's CSRF check.
		await page.goto('/index.php/apps/launchpad/')
		const items = await page.request.get(
			`${API}/widgets/items?widgets[]=user_status`,
		)
		expect(items.status(), await items.text()).toBe(200)
		expect(Object.keys(await items.json())).toEqual(['user_status'])

		// Control: the catalogue route is NOT exempt, so the same request to
		// it fails the CSRF check. Without this the 200 above could mean the
		// session simply was not checked.
		const list = await page.request.get(`${API}/widgets`)
		expect(list.status()).toBe(412)
	})
})

test.describe('REQ-WDG-003 add a widget to a dashboard', () => {
	// @e2e widgets::add-a-widget-to-a-dashboard
	test('POST creates a placement at the given cell with the documented defaults', async () => {
		const dash = await emptyDashboard(alice!.api, `${STAMP} add`)
		const res = await alice!.api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: ALLOWED_WIDGET,
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 4,
			},
		})
		expect(res.status(), await res.text()).toBe(201)
		const created = (await res.json()) as Placement
		expect(created).toMatchObject({
			dashboardId: dash.id,
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
			customTitle: null,
			showTitle: 1,
			isVisible: 1,
			isCompulsory: 0,
			sortOrder: 0,
		})
		expect(created.id).toBeGreaterThan(0)
		// The full placement object, not a stub.
		for (const key of [
			'styleConfig',
			'customIcon',
			'content',
			'createdAt',
			'updatedAt',
		]) {
			expect(created).toHaveProperty(key)
		}

		const stored = await placementOf(alice!.api, dash.id, created.id)
		expect(stored).toMatchObject({
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
			customTitle: null,
			showTitle: 1,
			isVisible: 1,
			isCompulsory: 0,
			sortOrder: 0,
		})
		await alice!.api.delete(`${API}/dashboard/${dash.id}`)
	})

	// @e2e widgets::add-a-widget-with-custom-title-and-styling
	test('title and style are not taken on create; a follow-up PUT sets them', async () => {
		const res = await alice!.api.post(
			`${API}/dashboard/${aliceDash.id}/widgets`,
			{
				data: {
					widgetId: ALLOWED_WIDGET,
					gridX: 0,
					gridY: 8,
					gridWidth: 4,
					gridHeight: 4,
					customTitle: 'Ignored on create',
					styleConfig: { backgroundColor: '#000000' },
				},
			},
		)
		expect(res.status(), await res.text()).toBe(201)
		const created = (await res.json()) as Placement
		expect(created.customTitle).toBeNull()
		expect(created.styleConfig).toEqual({})

		const style = { backgroundColor: '#ffeecc', borderStyle: 'dashed' }
		const put = await alice!.api.put(`${API}/widgets/${created.id}`, {
			data: { customTitle: 'Weather Today', styleConfig: style },
		})
		expect(put.status(), await put.text()).toBe(200)

		const stored = await placementOf(alice!.api, aliceDash.id, created.id)
		expect(stored?.customTitle).toBe('Weather Today')
		expect(stored?.styleConfig).toEqual(style)
	})

	// @e2e widgets::add-widget-to-another-users-dashboard
	test('a stranger is refused with 403 and nothing lands; the owner succeeds', async () => {
		const before = await placementsOf(alice!.api, aliceDash.id)

		const probe = await bob!.api.post(
			`${API}/dashboard/${aliceDash.id}/widgets`,
			{
				data: {
					widgetId: ALLOWED_WIDGET,
					gridX: 0,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 4,
				},
			},
		)
		expect(probe.status(), await probe.text()).toBe(403)
		expect(
			(await placementsOf(alice!.api, aliceDash.id)).map((p) => p.id),
		).toEqual(before.map((p) => p.id))

		const own = await alice!.api.post(
			`${API}/dashboard/${aliceDash.id}/widgets`,
			{
				data: {
					widgetId: ALLOWED_WIDGET,
					gridX: 0,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 4,
				},
			},
		)
		expect(own.status(), await own.text()).toBe(201)
		expect(await placementsOf(alice!.api, aliceDash.id)).toHaveLength(
			before.length + 1,
		)
	})

	// @e2e widgets::add-widget-with-non-existent-widgetid
	test('an unregistered widget id is accepted and stored as given', async () => {
		// As the admin, who is exempt from role-feature restrictions: the
		// scenario is about registration, not about an allow-list.
		const dash = await emptyDashboard(admin, `${STAMP} fake`)
		try {
			const res = await admin.post(`${API}/dashboard/${dash.id}/widgets`, {
				data: {
					widgetId: 'fake_widget',
					gridX: 0,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 4,
				},
			})
			expect(res.status(), await res.text()).toBe(201)
			const created = (await res.json()) as Placement
			expect((await placementOf(admin, dash.id, created.id))?.widgetId).toBe(
				'fake_widget',
			)
		} finally {
			await admin.delete(`${API}/dashboard/${dash.id}`)
		}
	})
})

test.describe('REQ-WDG-004 update a widget placement', () => {
	// @e2e widgets::update-widget-position-and-size
	test('PUT moves and resizes the placement and answers 200 with it', async () => {
		const dash = await emptyDashboard(alice!.api, `${STAMP} move`)
		const placement = await seedPlacement(alice!.api, dash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const res = await alice!.api.put(`${API}/widgets/${placement.id}`, {
			data: { gridX: 4, gridY: 2, gridWidth: 6, gridHeight: 3 },
		})
		expect(res.status(), await res.text()).toBe(200)
		const body = (await res.json()) as Placement
		expect(body).toMatchObject({
			id: placement.id,
			gridX: 4,
			gridY: 2,
			gridWidth: 6,
			gridHeight: 3,
		})
		expect(await placementOf(alice!.api, dash.id, placement.id)).toMatchObject({
			gridX: 4,
			gridY: 2,
			gridWidth: 6,
			gridHeight: 3,
		})
		await alice!.api.delete(`${API}/dashboard/${dash.id}`)
	})

	// @e2e widgets::update-style-configuration
	test('styleConfig is replaced whole, never merged', async () => {
		const placement = await seedPlacement(alice!.api, aliceDash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 20,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(placement.styleConfig).toEqual({})
		// A key the second write does not name: it must be gone afterwards.
		const first = await alice!.api.put(`${API}/widgets/${placement.id}`, {
			data: {
				styleConfig: {
					padding: { top: 8, right: 8, bottom: 8, left: 8 },
					backgroundColor: '#000000',
				},
			},
		})
		expect(first.status(), await first.text()).toBe(200)

		const replacement = {
			backgroundColor: '#ffffff',
			borderRadius: '12',
			borderStyle: 'solid',
			borderColor: '#cccccc',
			borderWidth: 1,
		}
		const second = await alice!.api.put(`${API}/widgets/${placement.id}`, {
			data: { styleConfig: replacement },
		})
		expect(second.status(), await second.text()).toBe(200)
		expect(
			(await placementOf(alice!.api, aliceDash.id, placement.id))?.styleConfig,
		).toEqual(replacement)
	})

	// @e2e widgets::update-placement-on-another-users-dashboard
	test("a stranger's PUT is refused with 403 and changes nothing; the owner's succeeds", async () => {
		const placement = await seedPlacement(alice!.api, aliceDash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 30,
			gridWidth: 4,
			gridHeight: 4,
		})
		const probe = await bob!.api.put(`${API}/widgets/${placement.id}`, {
			data: { customTitle: 'bob was here', gridX: 6 },
		})
		expect(probe.status(), await probe.text()).toBe(403)
		const untouched = await placementOf(alice!.api, aliceDash.id, placement.id)
		expect(untouched?.customTitle).toBeNull()
		expect(untouched?.gridX).toBe(placement.gridX)

		const own = await alice!.api.put(`${API}/widgets/${placement.id}`, {
			data: { customTitle: 'alice' },
		})
		expect(own.status(), await own.text()).toBe(200)
		expect(
			(await placementOf(alice!.api, aliceDash.id, placement.id))?.customTitle,
		).toBe('alice')
	})

	// @e2e widgets::update-tile-specific-fields
	test('tile fields and grid fields are applied together in one PUT', async () => {
		const tile = await seedTile(alice!.api, aliceDash.id, {
			title: `${STAMP} tile`,
			linkType: 'url',
			linkValue: 'https://example.org/',
			gridX: 0,
			gridY: 40,
		})
		expect(tile.tileType).toBe('custom')

		const res = await alice!.api.put(`${API}/widgets/${tile.id}`, {
			data: {
				tileTitle: 'Renamed tile',
				tileBackgroundColor: '#123456',
				tileTextColor: '#fedcba',
				tileLinkValue: 'https://example.org/renamed',
				gridX: 6,
				gridWidth: 3,
			},
		})
		expect(res.status(), await res.text()).toBe(200)

		const stored = await placementOf(alice!.api, aliceDash.id, tile.id)
		expect(stored).toMatchObject({
			tileType: 'custom',
			tileTitle: 'Renamed tile',
			tileBackgroundColor: '#123456',
			tileTextColor: '#fedcba',
			tileLinkValue: 'https://example.org/renamed',
			gridX: 6,
			gridWidth: 3,
		})
	})
})

test.describe('REQ-WDG-005 remove a widget from a dashboard', () => {
	// @e2e widgets::remove-a-widget-placement
	test('DELETE answers 200 and the placement is gone', async () => {
		const placement = await seedPlacement(alice!.api, aliceDash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 50,
			gridWidth: 4,
			gridHeight: 4,
		})
		const res = await alice!.api.delete(`${API}/widgets/${placement.id}`)
		expect(res.status(), await res.text()).toBe(200)
		expect(
			await placementOf(alice!.api, aliceDash.id, placement.id),
		).toBeUndefined()
	})

	// @e2e widgets::remove-another-users-widget-placement
	test("a stranger's DELETE is refused with 403 and the placement stays; the owner's succeeds", async () => {
		const placement = await seedPlacement(alice!.api, aliceDash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 60,
			gridWidth: 4,
			gridHeight: 4,
		})
		const probe = await bob!.api.delete(`${API}/widgets/${placement.id}`)
		expect(probe.status(), await probe.text()).toBe(403)
		expect(
			await placementOf(alice!.api, aliceDash.id, placement.id),
		).toBeDefined()

		const own = await alice!.api.delete(`${API}/widgets/${placement.id}`)
		expect(own.status(), await own.text()).toBe(200)
		expect(
			await placementOf(alice!.api, aliceDash.id, placement.id),
		).toBeUndefined()
	})

	// @e2e widgets::remove-widget-cascade-deletes-conditional-rules
	test('removing a placement removes its three conditional rules', async () => {
		// Was test.fixme: nothing cascaded. WidgetPlacementMapper::delete() now
		// deletes the placement's rules first (deleteRulesOfPlacementsWhere(),
		// the rules table has no foreign key). Orphaned rules are observable
		// only as the `orphaned_conditional_rules` count of the cleanup dry
		// run, which is what this measures.
		const orphans = async (): Promise<number> => {
			const res = await admin.post(`${API}/admin/cleanup/purge`, {
				data: { categories: ['orphaned_conditional_rules'], dryRun: true },
			})
			expect(res.status(), await res.text()).toBe(200)
			return Number(
				(await res.json()).purgedByCategory.orphaned_conditional_rules ?? 0,
			)
		}

		const placement = await seedPlacement(alice!.api, aliceDash.id, {
			widgetId: ALLOWED_WIDGET,
			gridX: 0,
			gridY: 70,
			gridWidth: 4,
			gridHeight: 4,
		})
		const year = new Date().getUTCFullYear()
		for (const startDate of [
			`${year - 3}-01-01`,
			`${year - 2}-01-01`,
			`${year - 1}-01-01`,
		]) {
			const rule = await alice!.api.post(
				`${API}/widgets/${placement.id}/rules`,
				{
					data: {
						ruleType: 'date',
						ruleConfig: { startDate },
						isInclude: true,
					},
				},
			)
			expect(rule.status(), await rule.text()).toBeLessThan(300)
		}
		const rules = await alice!.api.get(`${API}/widgets/${placement.id}/rules`)
		expect((await rules.json()).rules).toHaveLength(3)

		const before = await orphans()
		const removed = await alice!.api.delete(`${API}/widgets/${placement.id}`)
		expect(removed.status(), await removed.text()).toBe(200)
		expect(await orphans()).toBe(before)
	})
})

test.describe('REQ-WDG-005 compulsory widgets (copies of an admin template)', () => {
	// A compulsory placement only exists on a user's copy of an admin
	// template, so these build one the way `template-reaches-member` does:
	// the shipped "Mijn werkdag" template, given to a fresh group, and a new
	// member's first visit makes their copy.
	test.describe.configure({ timeout: 240_000 })

	let group = ''
	let templateId: number | null = null
	const members: Array<{ username: string; password: string }> = []

	/**
	 * Provision a group member, let their first visit build the copy, and
	 * return the copy's placements and level as the member reads them.
	 *
	 * @param browser the browser.
	 * @return the member, their API context and their copy.
	 */
	async function memberWithCopy(browser: Browser) {
		const member = await provisionThrowawayUser('e2e-wdg-member')
		members.push(member)
		const joined = await admin.post(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
			{ form: { groupid: group } },
		)
		expect(joined.ok(), 'the member must join the group').toBe(true)

		const { context, page } = await loginAs(
			browser,
			member.username,
			member.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			await expectOpenDashboard(page, 'Mijn werkdag', 60_000)
		} finally {
			await context.close()
		}
		const api = await contextFor(member.username, member.password)
		const res = await api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.dashboard.basedOnTemplate).toBe(templateId)
		return {
			api,
			dashboardId: Number(body.dashboard.id),
			permissionLevel: String(body.permissionLevel),
			placements: body.placements as Placement[],
		}
	}

	test.beforeAll(async () => {
		group = `e2e-wdg-${Date.now()}`
		const madeGroup = await admin.post('/ocs/v1.php/cloud/groups', {
			form: { groupid: group },
		})
		expect(madeGroup.ok(), 'the group must be created').toBe(true)
		const installed = await admin.post(
			`${API}/admin/templates/shipped/mijn-werkdag/install`,
			{
				data: { force: true },
			},
		)
		expect(installed.ok(), `install answered ${installed.status()}`).toBe(true)
		templateId = (await installed.json()).id
	})

	test.afterAll(async () => {
		if (templateId !== null) {
			await admin.delete(`${API}/admin/templates/${templateId}`)
		}
		for (const member of members) {
			await deprovisionUser(member.username)
		}
		if (group !== '') {
			await admin.delete(
				`/ocs/v1.php/cloud/groups/${encodeURIComponent(group)}`,
			)
		}
	})

	/**
	 * Give the template to the group at a permission level.
	 *
	 * @param permissionLevel the level copies take.
	 * @return nothing.
	 */
	async function targetAt(permissionLevel: string): Promise<void> {
		const res = await admin.put(`${API}/admin/templates/${templateId}`, {
			data: { targetGroups: [group], permissionLevel },
		})
		expect(res.status(), await res.text()).toBe(200)
	}

	// Guard for the scenario below while its message clause is pending: the
	// refusal itself, re-read, and the non-compulsory control.
	test('add_only: a compulsory widget is refused with 403 and stays; a non-compulsory one can go', async ({
		browser,
	}) => {
		await targetAt('add_only')
		const copy = await memberWithCopy(browser)
		try {
			expect(copy.permissionLevel).toBe('add_only')
			const compulsory = copy.placements.find((p) => p.isCompulsory === 1)
			expect(
				compulsory,
				'the template copy has no compulsory widget',
			).toBeDefined()

			const refused = await copy.api.delete(`${API}/widgets/${compulsory!.id}`)
			expect(refused.status(), await refused.text()).toBe(403)
			expect(
				await placementOf(copy.api, copy.dashboardId, compulsory!.id),
			).toBeDefined()

			// canRemoveWidget checks the compulsory flag, not the level alone:
			// a widget the member adds themselves comes off the same copy.
			const mine = await seedPlacement(copy.api, copy.dashboardId, {
				widgetId: ALLOWED_WIDGET,
				gridX: 0,
				gridY: 90,
				gridWidth: 4,
				gridHeight: 4,
			})
			const allowed = await copy.api.delete(`${API}/widgets/${mine.id}`)
			expect(allowed.status(), await allowed.text()).toBe(200)
			expect(
				await placementOf(copy.api, copy.dashboardId, mine.id),
			).toBeUndefined()
		} finally {
			await copy.api.dispose()
		}
	})

	// @e2e widgets::remove-a-compulsory-widget-without-full-permission
	test('add_only: the refusal says compulsory widgets cannot be removed', async ({
		browser,
	}) => {
		// Was test.fixme: the refusal carried the generic "Access denied".
		// WidgetApiController now passes PermissionService::widgetRefusal() to
		// ResponseHelper::forbidden(), which says "Compulsory widgets cannot
		// be removed at this permission level" for add_only.
		await targetAt('add_only')
		const copy = await memberWithCopy(browser)
		try {
			expect(copy.permissionLevel).toBe('add_only')
			const compulsory = copy.placements.find((p) => p.isCompulsory === 1)
			expect(compulsory).toBeDefined()

			const refused = await copy.api.delete(`${API}/widgets/${compulsory!.id}`)
			expect(refused.status()).toBe(403)
			expect(JSON.stringify(await refused.json())).toMatch(/compulsory/i)
			expect(
				await placementOf(copy.api, copy.dashboardId, compulsory!.id),
			).toBeDefined()

			const mine = await seedPlacement(copy.api, copy.dashboardId, {
				widgetId: ALLOWED_WIDGET,
				gridX: 0,
				gridY: 90,
				gridWidth: 4,
				gridHeight: 4,
			})
			expect(
				(await copy.api.delete(`${API}/widgets/${mine.id}`)).status(),
			).toBe(200)
		} finally {
			await copy.api.dispose()
		}
	})

	// @e2e widgets::remove-a-compulsory-widget-with-full-permission
	test('full: a compulsory widget can be removed', async ({ browser }) => {
		await targetAt('full')
		const copy = await memberWithCopy(browser)
		try {
			expect(copy.permissionLevel).toBe('full')
			const compulsory = copy.placements.find((p) => p.isCompulsory === 1)
			expect(
				compulsory,
				'the template copy has no compulsory widget',
			).toBeDefined()

			const res = await copy.api.delete(`${API}/widgets/${compulsory!.id}`)
			expect(res.status(), await res.text()).toBe(200)
			expect(
				await placementOf(copy.api, copy.dashboardId, compulsory!.id),
			).toBeUndefined()
		} finally {
			await copy.api.dispose()
		}
	})
})

test.describe('REQ-WDG-007 sort order', () => {
	// @e2e widgets::auto-assign-sort-order-on-creation
	test('a new placement on a dashboard that already has three gets sortOrder 0', async () => {
		// No write path sets a placement's sortOrder (it is not among the
		// PUT fields in lib/Controller/RequestDataExtractor.php:60), so the
		// three existing placements are what the API can make; the clause
		// under test is the new one's default, which the NOTE pins at 0.
		const dash = await emptyDashboard(alice!.api, `${STAMP} sort`)
		for (const gridY of [0, 4, 8]) {
			await seedPlacement(alice!.api, dash.id, {
				widgetId: ALLOWED_WIDGET,
				gridX: 0,
				gridY,
				gridWidth: 4,
				gridHeight: 4,
			})
		}
		const res = await alice!.api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: ALLOWED_WIDGET,
				gridX: 4,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 4,
			},
		})
		expect(res.status(), await res.text()).toBe(201)
		const created = (await res.json()) as Placement
		expect(created.sortOrder).toBe(0)
		expect((await placementOf(alice!.api, dash.id, created.id))?.sortOrder).toBe(
			0,
		)
		expect(await placementsOf(alice!.api, dash.id)).toHaveLength(4)
		await alice!.api.delete(`${API}/dashboard/${dash.id}`)
	})
})

test.describe('REQ-WDG-008 batch update placements', () => {
	// @e2e widgets::batch-update-after-grid-rearrangement
	test('PUT /api/dashboard/{id} with a placements array moves all four', async () => {
		const dash = await emptyDashboard(alice!.api, `${STAMP} batch`)
		const seeded: Placement[] = []
		for (const gridX of [0, 4, 8]) {
			seeded.push(
				await seedPlacement(alice!.api, dash.id, {
					widgetId: ALLOWED_WIDGET,
					gridX,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 4,
				}),
			)
		}
		seeded.push(
			await seedPlacement(alice!.api, dash.id, {
				widgetId: ALLOWED_WIDGET,
				gridX: 0,
				gridY: 4,
				gridWidth: 4,
				gridHeight: 4,
			}),
		)

		const target = seeded.map((p, i) => ({
			id: p.id,
			gridX: (i % 2) * 6,
			gridY: 10 + Math.floor(i / 2) * 3,
			gridWidth: 6,
			gridHeight: 3,
		}))
		const res = await alice!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { placements: target },
		})
		expect(res.status(), await res.text()).toBe(200)

		const stored = await placementsOf(alice!.api, dash.id)
		expect(stored).toHaveLength(4)
		for (const want of target) {
			expect(stored.find((p) => p.id === want.id)).toMatchObject(want)
		}
		await alice!.api.delete(`${API}/dashboard/${dash.id}`)
	})

	// @e2e widgets::batch-update-with-mixed-placement-types
	test('the batch moves three widgets and two tiles and leaves the tile fields alone', async () => {
		const dash = await emptyDashboard(alice!.api, `${STAMP} mixed`)
		const widgets: Placement[] = []
		for (const gridX of [0, 4, 8]) {
			widgets.push(
				await seedPlacement(alice!.api, dash.id, {
					widgetId: ALLOWED_WIDGET,
					gridX,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 4,
				}),
			)
		}
		const tiles: Placement[] = []
		for (const [i, gridX] of [0, 2].entries()) {
			tiles.push(
				await seedTile(alice!.api, dash.id, {
					title: `${STAMP} tile ${i}`,
					linkType: 'url',
					linkValue: `https://example.org/${i}`,
					bgColor: '#112233',
					textColor: '#ddeeff',
					gridX,
					gridY: 4,
				}),
			)
		}
		const tilesBefore = (await placementsOf(alice!.api, dash.id)).filter(
			(p) => p.tileType === 'custom',
		)
		expect(tilesBefore).toHaveLength(2)

		const all = [...widgets, ...tiles]
		const target = all.map((p, i) => ({
			id: p.id,
			gridX: (i % 3) * 4,
			gridY: 20 + Math.floor(i / 3) * 4,
			gridWidth: 4,
			gridHeight: 4,
		}))
		const res = await alice!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { placements: target },
		})
		expect(res.status(), await res.text()).toBe(200)

		const stored = await placementsOf(alice!.api, dash.id)
		expect(stored).toHaveLength(5)
		for (const want of target) {
			expect(stored.find((p) => p.id === want.id)).toMatchObject(want)
		}
		for (const before of tilesBefore) {
			const after = stored.find((p) => p.id === before.id)!
			for (const key of [
				'tileType',
				'tileTitle',
				'tileIcon',
				'tileIconType',
				'tileBackgroundColor',
				'tileTextColor',
				'tileLinkType',
				'tileLinkValue',
			] as const) {
				expect(after[key], `${key} of tile ${before.id} changed`).toEqual(
					before[key],
				)
			}
		}
		await alice!.api.delete(`${API}/dashboard/${dash.id}`)
	})
})
