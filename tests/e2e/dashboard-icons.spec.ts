/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard icon convention (openspec/specs/dashboard-icons), observed
 * where a person sees it: the icon next to each row of the dashboard switcher
 * and the icon field of the dashboard settings modal.
 *
 * WHERE THE CODE UNDER TEST LIVES
 * ===============================
 * The spec still names `src/constants/dashboardIcons.js`, `IconRenderer` and
 * `IconPicker`. None of those exist in this repository (`git log --all` has
 * no such file): the registry, `getIconComponent`, `isCustomIconUrl` and
 * `DEFAULT_ICON` are in `@conduction/nextcloud-vue`
 * (`components/CnWidgetGrid/widgetIcons.js`, re-exported through
 * `components/CnIconPicker/dashboardIcons.js`), the renderer is that
 * library's `CnDashboardIcon`, and the picker in LaunchPad's dashboard
 * settings is the library's `CnIconBrowser` (src/modals/DashboardConfigModal.vue
 * around line 151). These tests therefore drive the consumers:
 *
 *  - every switcher row renders `<CnDashboardIcon :name="dashboard.icon">`
 *    inside `.dashboard-switcher-sidebar__icon`
 *    (src/components/Workspace/DashboardSwitcherSidebar.vue:148-150). A
 *    registry name renders the vue-material-design-icons component, whose
 *    root carries `material-design-icon <kebab-name>-icon`, so the class tells
 *    exactly WHICH component `getIconComponent` returned. A URL renders an
 *    `<img :src="name" :alt="alt || 'icon'">`.
 *  - the dashboard settings modal (row cog -> "Dashboard settings") hosts the
 *    picker: an inline `.cn-icon-browser` labelled "Icon", whose preview is
 *    `.cn-icon-browser-panel__preview-icon`, whose Icons tab lists
 *    `.cn-icon-browser-panel__cell` buttons, and whose Custom tab carries
 *    `input.cn-icon-browser-panel__file-input` (upload via
 *    `POST /api/resources`, src/services/resourceService.js) and the
 *    `.cn-icon-browser-panel__error[role="alert"]` error line.
 *
 * Every dashboard is seeded through the API with the icon value under test,
 * named with a per-run stamp, and deleted in afterAll. The allow-personal-
 * dashboards flag is read first and restored afterwards.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-icons::resolve-a-built-in-name
 *   @e2e dashboard-icons::resolve-an-unknown-name-falls-back-to-default
 *   @e2e dashboard-icons::default-icon-is-viewdashboard
 *   @e2e dashboard-icons::registry-contains-all-15-named-icons
 *   @e2e dashboard-icons::null-input
 *   @e2e dashboard-icons::undefined-input
 *   @e2e dashboard-icons::empty-string-input
 *   @e2e dashboard-icons::picker-reflects-registry-size
 *   @e2e dashboard-icons::url-inputs-return-true
 *   @e2e dashboard-icons::registry-names-return-false
 *   @e2e dashboard-icons::null-and-empty-inputs-return-false
 *   @e2e dashboard-icons::url-input-yields-a-null-component
 *   @e2e dashboard-icons::unknown-built-in-name-still-returns-the-default-component
 *   @e2e dashboard-icons::render-a-built-in-icon
 *   @e2e dashboard-icons::render-a-custom-url-icon
 *   @e2e dashboard-icons::render-with-null-name-falls-back-to-default
 *   @e2e dashboard-icons::switching-from-built-in-to-custom
 *   @e2e dashboard-icons::switching-from-custom-back-to-built-in
 *   @e2e dashboard-icons::upload-error-preserves-previous-value
 *   @e2e dashboard-icons::mixed-values-across-rows-render-without-migration
 *   @e2e dashboard-icons::switching-a-dashboards-icon-between-forms-is-a-plain-update
 *
 * @spec openspec/specs/dashboard-icons/spec.md
 */

import type { APIRequestContext, Locator, Page, Route } from '@playwright/test'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { dashboardsFromApi } from './support/dashboardFirstLoad.ts'
import { DASHBOARD_ROWS, openDashboardSettings, openDashboardSwitcher, switchToDashboard } from './support/dashboardSwitcher.ts'
import { adminContext, contextFor } from './support/principals.ts'

const API = '/index.php/apps/launchpad/api'
const SETTINGS = `${API}/admin/settings`
const RUN = Date.now()

/**
 * The settings modal's title field. `data-testid` sits on NcTextField, whose
 * input field binds `$attrs` onto the `<input>` (inheritAttrs: false), so the
 * attribute lands on the input; the descendant form covers a wrapper too.
 */
const NAME_INPUT =
	'input[data-testid="dashboard-name-input"], [data-testid="dashboard-name-input"] input'

/** The fifteen names REQ-ICON-001 requires the registry to hold. */
const REQUIRED_NAMES = [
	'ViewDashboard',
	'Home',
	'ChartBar',
	'Cog',
	'AccountGroup',
	'Calendar',
	'FileDocument',
	'Bell',
	'Star',
	'Heart',
	'BookOpenVariant',
	'Lightbulb',
	'RocketLaunch',
	'Earth',
	'Briefcase',
]

/** A seeded dashboard and the icon value it was given. */
interface Seeded {
	id: number
	uuid: string
	name: string
}

let api: APIRequestContext
let originalAllowUserDashboards: boolean | null = null
const created: number[] = []

/**
 * The CSS class vue-material-design-icons puts on a component's root, e.g.
 * `BookOpenVariant` -> `book-open-variant-icon` (see any file under
 * node_modules/vue-material-design-icons/*.vue).
 *
 * @param name a registry name.
 * @return the class selector, with its leading dot.
 */
function mdiClass(name: string): string {
	return `.material-design-icon.${name.replace(/([a-z\d])([A-Z])/g, '$1-$2').toLowerCase()}-icon`
}

/**
 * The registry keys of the `DASHBOARD_ICONS` module the page's bundle is
 * built from: the ESM build webpack resolves for `@conduction/nextcloud-vue`
 * (package.json `module`). Read from the module source because the registry
 * itself is never written into the DOM; the picker is what must mirror it.
 *
 * @return the keys, in declaration order.
 */
function registryKeys(): string[] {
	const file = path.resolve(
		__dirname,
		'..',
		'..',
		'node_modules',
		'@conduction',
		'nextcloud-vue',
		'dist',
		'esm',
		'components',
		'CnWidgetGrid',
		'widgetIcons.js',
	)
	const source = fs.readFileSync(file, 'utf8')
	const block = source.match(
		/DASHBOARD_ICONS\s*=\s*Object\.freeze\(\{([\s\S]*?)\}\)/,
	)
	if (block === null) {
		throw new Error(`no DASHBOARD_ICONS literal in ${file}`)
	}
	return [...block[1].matchAll(/^\s*([A-Za-z][A-Za-z0-9]*)\s*:/gm)].map(
		(m) => m[1],
	)
}

/**
 * Create a personal dashboard for the admin with an icon value.
 *
 * `''` cannot be created directly (create treats it as "no icon",
 * DashboardService::applyNewIcon), so it is written with a PUT afterwards,
 * which stores the string as given (DashboardService::applyIconUpdate).
 *
 * @param label readable part of the name; the run stamp is appended.
 * @param icon the icon value; null or omitted leaves the column NULL.
 * @return the dashboard.
 */
async function createDashboard(
	label: string,
	icon: string | null = null,
): Promise<Seeded> {
	const name = `E2E Icon ${label} ${RUN}`
	const data: Record<string, unknown> = { name }
	if (icon !== null && icon !== '') {
		data.icon = icon
	}
	const res = await api.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const seeded = { id: Number(dash.id), uuid: String(dash.uuid), name }
	expect(seeded.id, `no id in ${JSON.stringify(body)}`).toBeTruthy()
	created.push(seeded.id)

	if (icon === '') {
		const put = await api.put(`${API}/dashboard/${seeded.id}`, {
			data: { icon: '' },
		})
		expect(put.status(), await put.text()).toBe(200)
	}

	// CONTROL: the stored value is exactly the one under test, so a row that
	// renders "right" cannot be doing so because the seed silently differed.
	expect(await storedIcon(seeded)).toBe(icon)
	return seeded
}

/**
 * Read a dashboard back as the owner.
 *
 * @param dashboard the dashboard.
 * @return the serialised dashboard and its placements.
 */
async function readBack(
	dashboard: Seeded,
): Promise<{ dashboard: Record<string, unknown>; placements: unknown[] }> {
	const res = await api.get(`${API}/dashboard/${dashboard.id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const payload = body.data ?? body
	return { dashboard: payload.dashboard, placements: payload.placements ?? [] }
}

/**
 * The stored `icon` column of a dashboard.
 *
 * @param dashboard the dashboard.
 * @return the raw value.
 */
async function storedIcon(dashboard: Seeded): Promise<unknown> {
	return (await readBack(dashboard)).dashboard.icon
}

/**
 * Make a dashboard the admin's active one, through BOTH mechanisms (see
 * tests/e2e/support/dashboardFixture.ts for why one is not enough).
 *
 * @param dashboard the dashboard.
 * @return nothing.
 */
async function activate(dashboard: Seeded): Promise<void> {
	const legacy = await api.post(`${API}/dashboard/${dashboard.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const pref = await api.post(`${API}/dashboards/active`, {
		data: { uuid: dashboard.uuid },
	})
	expect(pref.status(), await pref.text()).toBeLessThan(300)
}

/**
 * The icon slot of a dashboard's row in More's full Dashboards list (the
 * dashboards sidebar is gone, more-menu-favourites).
 *
 * @param page the page.
 * @param name the dashboard name.
 * @return the `.lp-more-menu__icon` span.
 */
function rowIcon(page: Page, name: string): Locator {
	return page
		.locator(DASHBOARD_ROWS)
		.filter({ has: page.locator('.lp-more-menu__label', { hasText: new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }) })
		.locator('.lp-more-menu__icon')
}

/**
 * Assert an icon slot renders exactly the given registry component as an
 * `<svg>`, and no `<img>`.
 *
 * @param slot the icon slot.
 * @param registryName the registry name whose component must render.
 * @return nothing.
 */
async function expectMdi(slot: Locator, registryName: string): Promise<void> {
	await expect(slot.locator(`${mdiClass(registryName)} svg`)).toHaveCount(1)
	await expect(slot.locator('svg path').first()).toHaveAttribute('d', /\S/)
	await expect(slot.locator('img')).toHaveCount(0)
}

/**
 * Assert an icon slot renders the URL as an `<img>` and no MDI component.
 *
 * @param slot the icon slot.
 * @param url the URL.
 * @return nothing.
 */
async function expectImg(slot: Locator, url: string): Promise<void> {
	await expect(slot.locator('img')).toHaveCount(1)
	await expect(slot.locator('img')).toHaveAttribute('src', url)
	// No consumer here passes `alt`, so this is the renderer's own fallback:
	// it must still give screen readers something.
	await expect(slot.locator('img')).toHaveAttribute('alt', /\S/)
	await expect(slot.locator('.material-design-icon')).toHaveCount(0)
	await expect(slot.locator('svg')).toHaveCount(0)
}

/**
 * Open the start page with the switcher open, collecting uncaught page errors
 * so "MUST NOT throw" has something to be measured against.
 *
 * @param page the page.
 * @return the live list of uncaught errors.
 */
async function openSwitcher(page: Page): Promise<Error[]> {
	const errors: Error[] = []
	page.on('pageerror', (error) => errors.push(error))
	// The URL icons under test point at example.com; keep the run offline.
	await page.route(/^https?:\/\/example\.com\//, (route) => route.abort())
	await page.goto('/index.php/apps/launchpad')
	await openDashboardSwitcher(page)
	return errors
}

/**
 * Open the settings modal of the ACTIVE dashboard from its switcher row and
 * return its icon picker.
 *
 * @param page the page.
 * @param dashboard the dashboard, already activated.
 * @return the `.cn-icon-browser` labelled "Icon".
 */
async function openIconPicker(page: Page, dashboard: Seeded): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	await switchToDashboard(page, dashboard.name)
	await openDashboardSettings(page)
	const nameInput = page.locator(NAME_INPUT).first()
	await expect(nameInput).toBeVisible({ timeout: 10_000 })
	// CONTROL: the modal is editing THIS dashboard, not another one.
	await expect(nameInput).toHaveValue(dashboard.name)
	// The innermost visible browser: the modal keeps other tabs' pickers in
	// the DOM, and a wrapper may carry the same class.
	const picker = page
		.locator('.cn-icon-browser:not(:has(.cn-icon-browser))')
		.filter({ visible: true })
		.filter({
			has: page.locator('.cn-icon-browser__field-label', {
				hasText: /^Icon$/,
			}),
		})
	await expect(picker).toHaveCount(1)
	return picker
}

/**
 * Save the settings modal and wait for the PUT and for the modal to close.
 *
 * @param page the page.
 * @param dashboard the dashboard being edited.
 * @return the PUT request body.
 */
async function saveModal(
	page: Page,
	dashboard: Seeded,
): Promise<Record<string, unknown>> {
	const put = page.waitForRequest(
		(req) =>
			req.method() === 'PUT'
			&& new URL(req.url()).pathname.endsWith(
				`/apps/launchpad/api/dashboard/${dashboard.id}`,
			),
	)
	await page.locator('[data-testid="dashboard-save-button"]').click()
	const body = (await put).postDataJSON() as Record<string, unknown>
	await expect(page.locator(NAME_INPUT)).toHaveCount(0, { timeout: 10_000 })
	return body
}

/**
 * The tab of the picker with the given label.
 *
 * @param picker the picker.
 * @param label the tab text.
 * @return the tab.
 */
function pickerTab(picker: Locator, label: string): Locator {
	return picker.getByRole('tab', { name: label, exact: true })
}

/**
 * Remove the `icon` key from one dashboard in the dashboard lists the
 * switcher is built from (`GET /api/dashboards` and `/api/dashboards/visible`,
 * src/services/api.js), so the renderer receives `undefined` for it.
 *
 * @param page the page, before navigation.
 * @param uuid the dashboard whose key is removed.
 * @return a reader for how many records lost their key (a CONTROL).
 */
async function stripIconFromLists(page: Page, uuid: string): Promise<() => number> {
	let stripped = 0
	const walk = (node: unknown): void => {
		if (Array.isArray(node)) {
			node.forEach(walk)
		} else if (node !== null && typeof node === 'object') {
			const obj = node as Record<string, unknown>
			if (obj.uuid === uuid && 'icon' in obj) {
				delete obj.icon
				stripped++
			}
			Object.values(obj).forEach(walk)
		}
	}
	// The switcher's first list comes from the page's initial state; drop it
	// so the list is fetched, and the fetch is what this rewrites.
	await dashboardsFromApi(page)
	await page.route(
		(url) =>
			/\/apps\/launchpad\/api\/dashboards(\/visible)?$/.test(url.pathname),
		async (route: Route) => {
			const response = await route.fetch()
			const json = await response.json()
			walk(json)
			await route.fulfill({ response, json })
		},
	)
	return () => stripped
}

test.describe('dashboard-icons: the icon convention as rendered', () => {
	test.beforeAll(async () => {
		api = await adminContext()
		const read = await api.get(SETTINGS)
		expect(read.status(), await read.text()).toBe(200)
		const settings = await read.json()
		originalAllowUserDashboards = Boolean(
			settings.allowUserDashboards ?? settings.data?.allowUserDashboards,
		)
		const enable = await api.put(SETTINGS, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBe(200)
	})

	test.afterAll(async () => {
		for (const id of created) {
			await api.delete(`${API}/dashboard/${id}`)
		}
		if (originalAllowUserDashboards !== null) {
			await api.put(SETTINGS, {
				data: { allowUserDash: originalAllowUserDashboards },
			})
		}
		await api?.dispose()
	})

	// @e2e dashboard-icons::resolve-a-built-in-name
	// @e2e dashboard-icons::render-a-built-in-icon
	test('a registry name renders that registry component as an svg, never an img', async ({
		page,
	}) => {
		const star = await createDashboard('Star', 'Star')
		const host = await createDashboard('Host Builtin')
		await activate(host)

		await openSwitcher(page)
		const slot = rowIcon(page, star.name)
		await expect(slot).toBeVisible()
		// getIconComponent('Star') returned vue-material-design-icons/Star.vue:
		// that component, and only it, roots on `.star-icon`.
		await expectMdi(slot, 'Star')
		// CONTROL: it is not the default glyph standing in for it.
		await expect(slot.locator(mdiClass('ViewDashboard'))).toHaveCount(0)
	})

	// @e2e dashboard-icons::resolve-an-unknown-name-falls-back-to-default
	// @e2e dashboard-icons::unknown-built-in-name-still-returns-the-default-component
	test('an unknown name renders the default component, not an img and not nothing', async ({
		page,
	}) => {
		const unknown = await createDashboard('NonExistent', 'NonExistent')
		const notReg = await createDashboard(
			'NotARegistryEntry',
			'NotARegistryEntry',
		)
		const control = await createDashboard('Control Heart', 'Heart')
		const host = await createDashboard('Host Unknown')
		await activate(host)

		await openSwitcher(page)
		for (const dashboard of [unknown, notReg]) {
			const slot = rowIcon(page, dashboard.name)
			await expect(slot).toBeVisible()
			// DEFAULT_ICON's component (ViewDashboard), and not null: a null
			// component would leave the slot empty or fall through to <img>.
			await expectMdi(slot, 'ViewDashboard')
		}
		// CONTROL: a known name in the same list does not also fall back, so the
		// assertion above is measuring the fallback and not a blanket default.
		await expectMdi(rowIcon(page, control.name), 'Heart')
		await expect(
			rowIcon(page, control.name).locator(mdiClass('ViewDashboard')),
		).toHaveCount(0)
	})

	// @e2e dashboard-icons::default-icon-is-viewdashboard
	test('a dashboard created through the UI stores DEFAULT_ICON, which is "ViewDashboard" and renders', async ({
		browser,
	}) => {
		// The create form is the empty state's "Create dashboard" (Views.vue
		// handleCreateDashboard); the switcher's "Add dashboard" forks the
		// active dashboard instead (REQ-DASH-020) and never opens it. A fresh
		// account still lands on the shared default dashboard, so its first
		// load is answered as "nothing to show" to reach the empty state.
		const account = await provisionThrowawayUser('e2e-icon-create')
		const own = await contextFor(account.username, account.password)
		const { context, page } = await loginAs(
			browser,
			account.username,
			account.password,
		)
		const name = `E2E Icon Created ${RUN}`
		try {
			const visible = (url: URL): boolean =>
				url.pathname.endsWith('/apps/launchpad/api/dashboards/visible')
			const active = (url: URL): boolean =>
				url.pathname.endsWith('/apps/launchpad/api/dashboard')
			// The first load comes from the page's initial state; drop it so
			// the two stubbed answers below are what the page reads.
			await dashboardsFromApi(page)
			await page.route(visible, (route) =>
				route.fulfill({ json: { items: [], quota: { maxDashboards: 0, dashboardsUsed: 0 } } }),
			)
			await page.route(active, (route) =>
				route.request().method() === 'GET'
					? route.fulfill({ json: null })
					: route.continue(),
			)

			await page.goto('/index.php/apps/launchpad')
			await expect(page.getByText('No dashboard yet')).toBeVisible({
				timeout: 30_000,
			})
			await page.getByRole('button', { name: 'Create dashboard' }).click()
			const nameInput = page.locator(NAME_INPUT).first()
			await expect(nameInput).toBeVisible({ timeout: 10_000 })
			await nameInput.fill(name)

			// The create form seeds `form.icon = DEFAULT_ICON`
			// (src/modals/DashboardConfigModal.vue, the isCreate branch of the open
			// watcher), so the POST carries the constant's value verbatim.
			const post = page.waitForResponse(
				(res) =>
					res.request().method() === 'POST'
					&& new URL(res.url()).pathname.endsWith(
						'/apps/launchpad/api/dashboard',
					),
			)
			await page.locator('[data-testid="dashboard-save-button"]').click()
			const response = await post
			expect(response.status(), await response.text()).toBeLessThan(300)
			const sent = response.request().postDataJSON() as Record<string, unknown>
			const body = await response.json()
			const dash = body.dashboard ?? body.data?.dashboard

			expect(sent.icon).toBe('ViewDashboard')
			const read = await own.get(`${API}/dashboard/${Number(dash.id)}`)
			expect(read.status(), await read.text()).toBe(200)
			const stored = await read.json()
			expect((stored.data ?? stored).dashboard.icon).toBe('ViewDashboard')

			// DASHBOARD_ICONS[DEFAULT_ICON] is defined: the stored name resolves
			// to a real component in the row, not to an empty slot.
			await expect(page.locator(NAME_INPUT)).toHaveCount(0, { timeout: 10_000 })
			await page.unroute(visible)
			await page.unroute(active)
			await page.reload()
			await openDashboardSwitcher(page)
			await expectMdi(rowIcon(page, name), 'ViewDashboard')
		} finally {
			await context.close()
			await own.dispose()
			await deprovisionUser(account.username)
		}
	})

	// @e2e dashboard-icons::registry-contains-all-15-named-icons
	test('every one of the fifteen required names resolves to its own component', async ({
		page,
	}) => {
		const rows: Array<{ name: string; dashboard: Seeded }> = []
		for (const name of REQUIRED_NAMES) {
			rows.push({
				name,
				dashboard: await createDashboard(`Reg ${name}`, name),
			})
		}
		const host = await createDashboard('Host Registry')
		await activate(host)

		await openSwitcher(page)
		const rendered = new Set<string>()
		for (const { name, dashboard } of rows) {
			const slot = rowIcon(page, dashboard.name)
			await expectMdi(slot, name)
			if (name !== 'ViewDashboard') {
				// A missing key would fall back to the default glyph instead.
				await expect(slot.locator(mdiClass('ViewDashboard'))).toHaveCount(0)
			}
			rendered.add(name)
		}
		expect(rendered.size).toBeGreaterThanOrEqual(15)
	})

	// @e2e dashboard-icons::null-input
	// @e2e dashboard-icons::render-with-null-name-falls-back-to-default
	test('a NULL icon renders the default component without throwing', async ({
		page,
	}) => {
		const nullIcon = await createDashboard('Null')
		const host = await createDashboard('Host Null', 'Star')
		await activate(host)

		const errors = await openSwitcher(page)
		const slot = rowIcon(page, nullIcon.name)
		await expect(slot).toBeVisible()
		// Not an empty placeholder: a real svg with a drawn path.
		await expectMdi(slot, 'ViewDashboard')
		expect(errors.map(String)).toEqual([])
	})

	// @e2e dashboard-icons::undefined-input
	test('a dashboard record with no icon key at all renders the default without throwing', async ({
		page,
	}) => {
		const undef = await createDashboard('Undefined', 'Star')
		const host = await createDashboard('Host Undefined')
		await activate(host)

		// The API always serialises `icon`, so `undefined` only reaches the
		// renderer if the key is missing from the payload. Remove it from the
		// list the switcher is built from.
		const stripped = await stripIconFromLists(page, undef.uuid)

		const errors = await openSwitcher(page)
		const slot = rowIcon(page, undef.name)
		await expect(slot).toBeVisible()
		// CONTROL: the key really was removed; the stored value is 'Star', so a
		// default glyph here can only come from the undefined input.
		expect(stripped()).toBeGreaterThan(0)
		await expectMdi(slot, 'ViewDashboard')
		await expect(slot.locator(mdiClass('Star'))).toHaveCount(0)
		expect(errors.map(String)).toEqual([])
	})

	// @e2e dashboard-icons::empty-string-input
	test('an empty-string icon renders the default component without throwing', async ({
		page,
	}) => {
		const empty = await createDashboard('Empty', '')
		const host = await createDashboard('Host Empty')
		await activate(host)

		const errors = await openSwitcher(page)
		const slot = rowIcon(page, empty.name)
		await expect(slot).toBeVisible()
		await expectMdi(slot, 'ViewDashboard')
		expect(errors.map(String)).toEqual([])
	})

	// @e2e dashboard-icons::url-inputs-return-true
	// @e2e dashboard-icons::registry-names-return-false
	// @e2e dashboard-icons::null-and-empty-inputs-return-false
	test('only values starting with "/" or "http" are treated as URLs', async ({
		page,
	}) => {
		const urls = [
			'/apps/launchpad/resource/abc123.png',
			'https://example.com/icon.svg',
			'http://example.com/icon.png',
		]
		const urlDashboards: Array<{ url: string; dashboard: Seeded }> = []
		for (const [index, url] of urls.entries()) {
			urlDashboards.push({
				url,
				dashboard: await createDashboard(`Url ${index}`, url),
			})
		}
		const star = await createDashboard('Disc Star', 'Star')
		const viewDashboard = await createDashboard(
			'Disc ViewDashboard',
			'ViewDashboard',
		)
		const nullIcon = await createDashboard('Disc Null')
		const empty = await createDashboard('Disc Empty', '')
		const undef = await createDashboard('Disc Undefined', 'Heart')
		const host = await createDashboard('Host Disc')
		await activate(host)

		const stripped = await stripIconFromLists(page, undef.uuid)

		await openSwitcher(page)
		// isCustomIconUrl(...) === true: rendered through <img>.
		for (const { url, dashboard } of urlDashboards) {
			await expectImg(rowIcon(page, dashboard.name), url)
		}
		// isCustomIconUrl('Star') / ('ViewDashboard') === false: a component.
		await expectMdi(rowIcon(page, star.name), 'Star')
		await expectMdi(rowIcon(page, viewDashboard.name), 'ViewDashboard')
		// null, '' and undefined === false: no <img>, the default component.
		expect(stripped()).toBeGreaterThan(0)
		for (const dashboard of [nullIcon, empty, undef]) {
			await expectMdi(rowIcon(page, dashboard.name), 'ViewDashboard')
		}
	})

	// @e2e dashboard-icons::url-input-yields-a-null-component
	// @e2e dashboard-icons::render-a-custom-url-icon
	test('a URL renders as an img with that src and never as the default icon', async ({
		page,
	}) => {
		const x = await createDashboard('Url X', '/apps/launchpad/resource/x.png')
		const abc = await createDashboard(
			'Url Abc',
			'/apps/launchpad/resource/abc.png',
		)
		const host = await createDashboard('Host Url')
		await activate(host)

		await openSwitcher(page)
		for (const [dashboard, url] of [
			[x, '/apps/launchpad/resource/x.png'],
			[abc, '/apps/launchpad/resource/abc.png'],
		] as const) {
			const slot = rowIcon(page, dashboard.name)
			await expect(slot).toBeVisible()
			// null from getIconComponent means "<img>", NOT "use the default":
			// no ViewDashboard (or any MDI) component next to the image.
			await expectImg(slot, url)
			await expect(slot.locator(mdiClass('ViewDashboard'))).toHaveCount(0)
		}
	})

	// @e2e dashboard-icons::mixed-values-across-rows-render-without-migration
	test('Star, a URL and NULL side by side render as svg, img and default svg', async ({
		page,
	}) => {
		const star = await createDashboard('Mixed Star', 'Star')
		const url = await createDashboard(
			'Mixed Url',
			'/apps/launchpad/resource/x.png',
		)
		const nullIcon = await createDashboard('Mixed Null')
		const host = await createDashboard('Host Mixed')
		await activate(host)

		await openSwitcher(page)
		await expectMdi(rowIcon(page, star.name), 'Star')
		await expectImg(rowIcon(page, url.name), '/apps/launchpad/resource/x.png')
		await expectMdi(rowIcon(page, nullIcon.name), 'ViewDashboard')

		// No migration or transformation: rendering left every stored value
		// exactly as it was written.
		expect(await storedIcon(star)).toBe('Star')
		expect(await storedIcon(url)).toBe('/apps/launchpad/resource/x.png')
		expect(await storedIcon(nullIcon)).toBeNull()
	})

	// @e2e dashboard-icons::switching-a-dashboards-icon-between-forms-is-a-plain-update
	test('switching Star to a URL and back is one plain icon write, nothing else changes', async ({
		page,
	}) => {
		const d1 = await createDashboard('Plain Update', 'Star')
		const host = await createDashboard('Host Plain')
		await activate(host)
		const url = '/apps/launchpad/resource/y.png'
		const before = await readBack(d1)

		const put = await api.put(`${API}/dashboard/${d1.id}`, {
			data: { icon: url },
		})
		expect(put.status(), await put.text()).toBe(200)
		const after = await readBack(d1)

		// The column holds the bare string: no `{kind, value}` object.
		expect(after.dashboard.icon).toBe(url)
		// Only `icon` (and the row's own updatedAt) changed on the row...
		const strip = (row: Record<string, unknown>): Record<string, unknown> => {
			const { icon: _icon, updatedAt: _updatedAt, ...rest } = row
			return rest
		}
		expect(strip(after.dashboard)).toEqual(strip(before.dashboard))
		// ...and nothing hanging off the dashboard was touched.
		expect(after.placements).toEqual(before.placements)

		await openSwitcher(page)
		await expectImg(rowIcon(page, d1.name), url)

		// And back again, with the same plain write.
		const back = await api.put(`${API}/dashboard/${d1.id}`, {
			data: { icon: 'Star' },
		})
		expect(back.status(), await back.text()).toBe(200)
		const restored = await readBack(d1)
		expect(restored.dashboard.icon).toBe('Star')
		expect(strip(restored.dashboard)).toEqual(strip(before.dashboard))
		await page.reload()
		await openDashboardSwitcher(page)
		await expectMdi(rowIcon(page, d1.name), 'Star')
	})

	// @e2e dashboard-icons::picker-reflects-registry-size
	test('the icon picker offers exactly the registry, one entry per key', async ({
		page,
	}) => {
		const keys = registryKeys()
		// CONTROL: the registry was really read, and is the one REQ-ICON-001 names.
		expect(keys.length).toBeGreaterThanOrEqual(15)
		for (const name of REQUIRED_NAMES) {
			expect(keys).toContain(name)
		}

		const target = await createDashboard('Picker Size', 'Star')
		await activate(target)
		const picker = await openIconPicker(page, target)
		await pickerTab(picker, 'Icons').click()

		const cells = picker.locator(
			'.cn-icon-browser-panel__icons:visible .cn-icon-browser-panel__cell',
		)
		await expect(cells.first()).toBeVisible()
		// One entry per registry key, with nothing capped away.
		await expect(cells).toHaveCount(keys.length)
		await expect(
			picker.locator('.cn-icon-browser-panel__hint:visible'),
		).toHaveCount(0)
		const deCamel = (key: string): string =>
			key.replace(/([a-z\d])([A-Z])/g, '$1 $2')
		const labels = await cells.evaluateAll((nodes) =>
			nodes.map((node) => node.getAttribute('aria-label') ?? ''),
		)
		expect([...labels].sort()).toEqual(keys.map(deCamel).sort())

		// Each entry's VALUE is a registry key: pick one and read what is stored.
		await picker
			.locator('.cn-icon-browser-panel__cell[aria-label="Home"]')
			.click()
		const sent = await saveModal(page, target)
		expect(keys).toContain(sent.icon)
		expect(await storedIcon(target)).toBe('Home')
	})

	// @e2e dashboard-icons::switching-from-built-in-to-custom
	test('uploading in the picker switches a Star icon to the uploaded URL', async ({
		page,
	}) => {
		const target = await createDashboard('Picker To Custom', 'Star')
		await activate(target)
		const picker = await openIconPicker(page, target)
		const preview = picker.locator('.cn-icon-browser-panel__preview-icon')

		// Before: the preview shows the Star svg.
		await expect.soft(preview.locator('svg')).toHaveCount(1)
		await expect(preview.locator('img')).toHaveCount(0)

		await pickerTab(picker, 'Custom').click()
		const upload = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& new URL(res.url()).pathname.endsWith(
					'/apps/launchpad/api/resources',
				),
		)
		await picker
			.locator('input.cn-icon-browser-panel__file-input')
			.setInputFiles(path.join(__dirname, 'fixtures', 'tiny.png'))
		const uploaded = await upload
		expect(uploaded.status(), await uploaded.text()).toBeLessThan(300)
		const url = String((await uploaded.json()).url)
		expect(url).toMatch(/^\/apps\/launchpad\/resource\//)

		// After: the preview is the uploaded image, not an svg.
		await expect(preview.locator('img')).toHaveAttribute('src', url)
		await expect(preview.locator('svg')).toHaveCount(0)

		// The v-model is the URL: it is what the save sends and what is stored.
		const sent = await saveModal(page, target)
		expect(sent.icon).toBe(url)
		expect(await storedIcon(target)).toBe(url)
		await page.reload()
		await openDashboardSwitcher(page)
		await expectImg(rowIcon(page, target.name), url)
	})

	// @e2e dashboard-icons::switching-from-custom-back-to-built-in
	test('picking Home in the picker switches a URL icon back to the registry name', async ({
		page,
	}) => {
		const url = '/apps/launchpad/resource/abc.png'
		const target = await createDashboard('Picker To Builtin', url)
		await activate(target)
		const picker = await openIconPicker(page, target)
		const preview = picker.locator('.cn-icon-browser-panel__preview-icon')

		// Before: the preview is the image.
		await expect(preview.locator('img')).toHaveAttribute('src', url)

		await pickerTab(picker, 'Icons').click()
		await picker
			.locator('input.cn-icon-browser-panel__search:visible')
			.fill('Home')
		const home = picker.locator(
			'.cn-icon-browser-panel__cell[aria-label="Home"]',
		)
		await expect(home).toBeVisible()
		await home.click()

		// After: an svg preview, no image.
		await expect(preview.locator('svg')).toHaveCount(1)
		await expect(preview.locator('img')).toHaveCount(0)

		// The v-model is the registry name "Home".
		const sent = await saveModal(page, target)
		expect(sent.icon).toBe('Home')
		expect(await storedIcon(target)).toBe('Home')
		await page.reload()
		await openDashboardSwitcher(page)
		await expectMdi(rowIcon(page, target.name), 'Home')
	})

	// @e2e dashboard-icons::upload-error-preserves-previous-value
	test('a failed upload shows an error and keeps the Star icon', async ({
		page,
	}) => {
		const target = await createDashboard('Picker Upload Error', 'Star')
		await activate(target)

		let attempts = 0
		await page.route(
			(url) => url.pathname.endsWith('/apps/launchpad/api/resources'),
			async (route) => {
				if (route.request().method() !== 'POST') {
					await route.continue()
					return
				}
				attempts++
				await route.fulfill({
					status: 400,
					contentType: 'application/json',
					body: JSON.stringify({
						status: 'error',
						error: 'invalid_image_format',
						message: 'Upload refused by dashboard-icons.spec.ts',
					}),
				})
			},
		)

		const picker = await openIconPicker(page, target)
		const preview = picker.locator('.cn-icon-browser-panel__preview-icon')
		await pickerTab(picker, 'Custom').click()
		await picker
			.locator('input.cn-icon-browser-panel__file-input')
			.setInputFiles(path.join(__dirname, 'fixtures', 'tiny.png'))

		// A visible error state.
		const error = picker.locator('.cn-icon-browser-panel__error[role="alert"]')
		await expect(error).toBeVisible({ timeout: 15_000 })
		await expect(error).toContainText(
			'Upload refused by dashboard-icons.spec.ts',
		)
		// CONTROL: the upload really was attempted (and refused) exactly once.
		expect(attempts).toBe(1)

		// The preview still shows the Star icon, and no image.
		await expect(preview.locator('img')).toHaveCount(0)
		await expect.soft(preview.locator('svg')).toHaveCount(1)

		// The v-model is still "Star": it is what the save sends and stores.
		const sent = await saveModal(page, target)
		expect(sent.icon).toBe('Star')
		expect(await storedIcon(target)).toBe('Star')
	})
})
