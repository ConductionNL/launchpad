/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `case-handler` bundled demo showcase (REQ-DEMO-001..006).
 *
 * WHAT THE SHOWCASE IS. A sixth entry in `DemoShowcasesService::BUNDLED_IDS`,
 * shipped as `data/demo-showcases/case-handler/case-handler.zip`. Installing it
 * creates one read-only group dashboard carrying exactly four widgets: an
 * `object-list` over dossiq cases, an `nc-widget` proxying the Tasks app, a
 * `calendar`, and an `nc-widget` proxying unread mail.
 *
 * 🔴 WHY "EXACTLY FOUR" IS THE ASSERTION AND NOT A DETAIL
 * ======================================================
 * `DemoShowcasesService::partitionWidgets()` decides what actually lands.
 * A widget survives only if it carries a non-empty `tileType` (a LaunchPad
 * tile), or if its `widgetId` appears in Nextcloud's own dashboard registry
 * — `IManager::getWidgets()`. Everything else is dropped into
 * `skippedWidgets` and the install still answers success.
 *
 * None of this showcase's four widgets is a tile, and NONE of `object-list`,
 * `nc-widget` or `calendar` is a Nextcloud-registry id: they are LaunchPad's
 * own widget types, registered in the frontend bundle. Measured against the
 * live registry on the dev instance (`GET /ocs/v2.php/apps/dashboard/api/v1/widgets`),
 * which lists `tasks`, `mail`, `mail-unread`, `activity`, `spreed` and friends
 * — and no `calendar`, no `nc-widget`, no `object-list`.
 *
 * Measured on the same instance with the five existing showcases: installing
 * `van-der-berg` answered
 *
 *     {"installedDashboardUuid":"fc39…","skippedWidgets":["calendar"],"alreadyInstalled":false}
 *
 * — its three tiles and its `mail` widget landed (`mail` IS a Nextcloud registry
 * id), its `calendar` did not, and the response was still a success. A showcase built entirely from LaunchPad-native widget
 * types therefore installs an EMPTY dashboard and reports it as installed.
 *
 * Measured directly for THIS archive on 2026-09-11: `partitionWidgets()` from
 * ConductionNL/launchpad#605 (byte-identical to the deployed copy) called inside
 * the dev container on the shipped `case-handler.zip` payload, against the live
 * 39-widget registry, with a raw `mail-unread` row and a tile row added as
 * controls. Both controls came back valid; the showcase's own four came back
 * `skipped: ["object-list","nc-widget","calendar","nc-widget"]`.
 *
 * So the assertion here is deliberately not "the endpoint answered 200". It is
 * `skippedWidgets` being empty AND four placements existing AND their ids being
 * the right four. Any one of those alone can be green while the operator gets a
 * blank page.
 *
 * WHY `internalCalendars` IS CHECKED FOR SHAPE AND NOT FOR CONTENT. The
 * showcase ships the list EMPTY on purpose (ConductionNL/launchpad#605): the
 * calendar ids on the authoring instance mean nothing anywhere else, and
 * pointing a stranger's widget at calendar "1" is worse than asking them. That
 * is right, and the unit test on that change pins the empty list. The cost is
 * worth knowing though: an empty list is not an error, it fetches nothing and
 * returns zero events with zero failures, so until the installer picks a
 * calendar the tile reads like a quiet day. This spec asserts only that the
 * widget is configured as a calendar at all.
 *
 * WHAT THIS DOES NOT ASSERT. That the Tasks and Mail tiles render CONTENT. Both
 * are `nc-widget` proxies and the Tasks widget implements only `IWidget` with
 * `itemApiVersions: []`, so it can never serve the items API — it renders
 * through the legacy widget bridge or not at all, and that bridge is an
 * instance-level admin setting this spec has no business flipping (turning it
 * on makes the workspace load every enabled app's widget scripts, measured at
 * ~118 MB on this fleet). The frames are asserted; their innards are not.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */

import type { APIRequestContext, request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = `${BASE}/index.php/apps/launchpad/api`
const SHOWCASES = `${API}/admin/demo-showcases`
const APP_URL = '/index.php/apps/launchpad'

const SHOWCASE_ID = 'case-handler'

/**
 * The four widgets the showcase promises, keyed the way a placement carries
 * them: `widgetId` is the LaunchPad widget type, and for an `nc-widget` proxy
 * `content.widgetId` names the Nextcloud widget being proxied.
 */
const EXPECTED_WIDGETS = [
	{ widgetId: 'object-list', proxies: null },
	{ widgetId: 'nc-widget', proxies: 'tasks' },
	{ widgetId: 'calendar', proxies: null },
	{ widgetId: 'nc-widget', proxies: 'mail-unread' },
]

interface Placement {
	widgetId: string
	content: Record<string, unknown> | null
	customTitle: string | null
}

/** An admin-authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: {
	request: { newContext: typeof request.newContext }
}): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Resolve an installed dashboard's numeric id from its UUID.
 *
 * The install endpoint answers a UUID, but `GET /api/dashboard/{uuid}` is a
 * 404 — that route binds a numeric id, and the UUID-shaped route
 * (`/api/dashboards/{uuid}/resolved`) answers translations rather than
 * placements. Measured both ways on the dev instance before writing this.
 *
 * 🔴 `/api/dashboards/visible`, NOT `/api/dashboards`. The plain list returns
 * only the caller's OWN dashboards, and a showcase installs as a
 * `group_shared` dashboard owned by nobody, so it is never in that list. The
 * first CI run of this spec looked it up there and failed with "the install
 * reported uuid … but /api/dashboards does not list it" — a lookup bug that
 * would have stayed red after the showcase itself was fixed. Measured on the
 * dev instance with `van-der-berg`: absent from `/api/dashboards`, present in
 * `/api/dashboards/visible` and `/api/dashboards/group/default`, and
 * `/api/dashboard/{id}` then answers its placements.
 *
 * @param api an admin-authenticated request context.
 * @param uuid the dashboard UUID the install returned.
 * @return the dashboard's numeric id.
 */
async function idForUuid(api: APIRequestContext, uuid: string): Promise<number> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const items = (body.items ?? body) as Array<{ id: number; uuid: string }>
	const found = items.find((d) => d.uuid === uuid)
	expect(
		found,
		`the install reported uuid ${uuid} but /api/dashboards/visible does not list it`,
	).toBeTruthy()
	return Number(found!.id)
}

/** Read a dashboard and its placements by numeric id. */
async function readDashboard(
	api: APIRequestContext,
	id: number,
): Promise<{ dashboard: Record<string, unknown>; placements: Placement[] }> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return await res.json()
}

/*
 * NOT `serial`, and the install happens ONCE, in `beforeAll`. Under `serial` a
 * red install test skips every test after it, so a showcase that installs
 * empty would show one failure and hide the fact that the composition and the
 * rendered grid are wrong too. Installing up front lets each test report its
 * own verdict on the same install. If Playwright restarts the worker after a
 * failure it re-runs this hook, which uninstalls first, so the later tests
 * still measure a fresh install rather than a leftover.
 */
test.describe('demo showcase — case-handler', () => {
	let api: APIRequestContext
	let installStatus = 0
	let installBody: Record<string, unknown> = {}
	let installedUuid: string | null = null
	let previousActiveUuid: string | null = null

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })

		// The render test below switches the admin's active dashboard to the
		// showcase. Remember what it was, so `afterAll` can hand it back rather
		// than leave a preference pointing at a dashboard it then uninstalls.
		const active = await api.get(`${API}/dashboard`)
		if (active.ok()) {
			const uuid = (await active.json())?.dashboard?.uuid
			previousActiveUuid = typeof uuid === 'string' ? uuid : null
		}

		// Start from a known state. The uninstall is idempotent, so this is safe
		// whether or not an earlier run left the showcase behind, and without it
		// a re-run would take the `alreadyInstalled` path and measure nothing.
		await api.delete(`${SHOWCASES}/${SHOWCASE_ID}`)

		const res = await api.post(`${SHOWCASES}/${SHOWCASE_ID}/install`)
		installStatus = res.status()
		installBody = await res.json().catch(async () => ({ raw: await res.text() }))
		const uuid = String(installBody.installedDashboardUuid ?? '')
		installedUuid = /^[0-9a-f-]{36}$/.test(uuid) ? uuid : null
	})

	/*
	 * 🔴 UNINSTALL IN `afterAll`. The install writes a group-shared dashboard
	 * scoped to the `default` sentinel group, which means EVERY user of the
	 * instance sees it — including whatever spec runs next. Leaving it behind
	 * changes the dashboard other specs resolve to, and the uninstall is
	 * documented idempotent (REQ-DEMO-006), so calling it unconditionally is
	 * safe even when the install never happened.
	 */
	test.afterAll(async () => {
		if (api === undefined) {
			return
		}
		await api.delete(`${SHOWCASES}/${SHOWCASE_ID}`)
		if (previousActiveUuid !== null) {
			await api.post(`${API}/dashboards/active`, {
				data: { uuid: previousActiveUuid },
			})
		}
		await api.dispose()
	})

	// @e2e demo-data-showcases::a-showcase-declares-the-language-of-its-own-copy
	test('the showcase is listed among the bundled ones', async () => {
		const res = await api.get(SHOWCASES)
		expect(res.status(), await res.text()).toBe(200)
		const list = (await res.json()) as Array<Record<string, unknown>>

		const entry = list.find((s) => s.id === SHOWCASE_ID)
		expect(
			entry,
			`GET /api/admin/demo-showcases must offer "${SHOWCASE_ID}". `
				+ `It listed: ${list.map((s) => s.id).join(', ')}. A showcase absent `
				+ `from this list cannot be installed from the admin UI at all — the `
				+ `list is where the cards come from.`,
		).toBeTruthy()

		// A card with no name renders as an empty tile the admin cannot identify.
		expect(String(entry!.name ?? '').length).toBeGreaterThan(0)
		expect(String(entry!.description ?? '').length).toBeGreaterThan(0)
		expect(
			String(entry!.thumbnailUrl ?? ''),
			'the card needs a thumbnail path to render',
		).toContain(SHOWCASE_ID)

		/*
		 * The language is declared per showcase, not assumed for the set. The
		 * five organisation showcases are Dutch; `case-handler` is the first
		 * role showcase and is English because the widgets it places carry
		 * English labels. A gallery that labelled it "nl" would promise the
		 * admin a Dutch dashboard and install an English one.
		 */
		expect(entry!.language, 'case-handler declares English copy').toBe('en')
		const organisation = list.find((s) => s.id === 'gemeente-duin')
		expect(
			organisation?.language,
			'an organisation showcase stays Dutch next to the role showcase',
		).toBe('nl')
	})

	// @e2e demo-data-showcases::a-role-showcase-installs-as-a-read-only-group-dashboard
	test('installing it skips nothing', async () => {
		expect(installStatus, JSON.stringify(installBody)).toBeLessThan(300)
		const body = installBody
		expect(
			installedUuid,
			`the install must name the dashboard it created: ${JSON.stringify(body)}`,
		).toBeTruthy()

		/*
		 * THE ASSERTION. `partitionWidgets` silently drops any widget whose id
		 * is not in Nextcloud's dashboard registry and is not a tile, and the
		 * install still succeeds. Every widget in this showcase is a
		 * LaunchPad-native type, so this list being empty is the difference
		 * between the promised dashboard and a blank one.
		 */
		expect(
			body.skippedWidgets,
			`the install dropped widgets and reported success anyway: `
				+ `${JSON.stringify(body.skippedWidgets)}. `
				+ `DemoShowcasesService::partitionWidgets() only admits LaunchPad `
				+ `tiles and ids present in IManager::getWidgets(); object-list, `
				+ `nc-widget and calendar are none of those.`,
		).toEqual([])
	})

	// @e2e demo-data-showcases::a-role-showcase-installs-as-a-read-only-group-dashboard
	test('it installs a read-only group dashboard carrying the four promised widgets', async () => {
		expect(
			installedUuid,
			`the install in beforeAll produced no dashboard: ${JSON.stringify(installBody)}`,
		).toBeTruthy()
		const id = await idForUuid(api, installedUuid!)
		const { dashboard, placements } = await readDashboard(api, id)

		// Read-only, and shared with everyone rather than owned by the admin who
		// pressed Install (REQ-DASH-012 — the `default` sentinel group).
		expect(dashboard.type, 'a showcase installs as a group dashboard').toBe(
			'group_shared',
		)
		expect(
			dashboard.permissionLevel,
			'the showcase dashboard is read-only for its audience',
		).toBe('view_only')

		expect(
			placements.length,
			`expected the four promised widgets, got `
				+ `${placements.map((p) => p.widgetId).join(', ') || '(none)'}`,
		).toBe(4)

		// Compare as a multiset: layout order is the showcase author's business,
		// the composition is the contract.
		expect(placements.map((p) => p.widgetId).sort()).toEqual(
			EXPECTED_WIDGETS.map((w) => w.widgetId).sort(),
		)

		/*
		 * An `nc-widget` with the wrong `content.widgetId` is the failure this
		 * catches: the placement exists, the frame renders, the title reads
		 * "My tasks", and the proxy points somewhere else entirely. Nothing
		 * about the count would show it.
		 */
		const proxied = placements
			.filter((p) => p.widgetId === 'nc-widget')
			.map((p) => String(p.content?.widgetId ?? ''))
			.sort()
		expect(
			proxied,
			'the two nc-widget proxies must name the Tasks and unread-mail widgets',
		).toEqual(['mail-unread', 'tasks'])

		// The object-list must actually point at dossiq cases; an object-list
		// with no register renders an empty table and no error.
		const objectList = placements.find((p) => p.widgetId === 'object-list')
		expect(objectList?.content?.register, 'the case list reads dossiq').toBe(
			'dossiq',
		)
		expect(
			objectList?.content?.schema,
			'the case list reads the case schema',
		).toBe('case')

		/*
		 * The calendar must arrive configured AS a calendar: a placement with
		 * no `internalCalendars` key at all is a widget that never learned what
		 * it renders from. The list itself is shipped empty on purpose (see the
		 * file header), so its length is deliberately not asserted.
		 */
		const calendar = placements.find((p) => p.widgetId === 'calendar')
		expect(
			calendar?.content,
			'the calendar placement carries no content at all',
		).toBeTruthy()
		expect(
			Array.isArray(calendar!.content!.internalCalendars),
			'the calendar widget renders from content.internalCalendars',
		).toBe(true)
	})

	test('all four widgets render as frames on the installed dashboard', async ({
		page,
	}) => {
		expect(
			installedUuid,
			`the install in beforeAll produced no dashboard: ${JSON.stringify(installBody)}`,
		).toBeTruthy()

		// Make the showcase the active dashboard for this user, the way the
		// switcher does — the shell resolves through the per-user UUID
		// preference, not the legacy is_active column.
		const activate = await api.post(`${API}/dashboards/active`, {
			data: { uuid: installedUuid },
		})
		expect(activate.status(), await activate.text()).toBeLessThan(300)

		// `domcontentloaded`, not the default `load`: this instance takes 55-60s
		// to fire `load` on the workspace page, which is the entire configured
		// navigationTimeout. The selector wait below is the real gate.
		await page.goto(APP_URL, { waitUntil: 'domcontentloaded' })
		await page.waitForSelector(
			'.launchpad-floating-controls, .workspace-shell',
			{
				timeout: 20_000,
			},
		)

		/*
		 * Count the rendered grid items rather than trusting the API count
		 * again. A placement the frontend cannot map to a registered widget
		 * type is dropped at render time, which is a second, independent way
		 * to end up with fewer tiles than the showcase promised.
		 */
		const items = page.locator('.grid-stack-item')
		await expect(
			items,
			'the showcase promises four widgets; the grid rendered a different number',
		).toHaveCount(4, { timeout: 20_000 })
	})
})
