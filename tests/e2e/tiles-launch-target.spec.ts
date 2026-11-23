/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * How a tile launches and where it opens (tiles REQ-TLT-001..003, REQ-TOB-001,
 * -002, -004, -005): a program tile, a remote-desktop web gateway, the single
 * sign-on editor, "Opens in" set in the tile editor, the dashboard's default
 * target, the rule for dashboards without one, the editor's refusal of the
 * panel for a host that may not be framed, and quick search opening a panel
 * tile.
 *
 * A throwaway account owns one dashboard whose tiles are placed through
 * `POST /api/dashboard/{id}/tile` (the GIVEN). The tests then do what the
 * author and the reader do on the page. Outside addresses are answered by
 * `context.route` so a click can be followed without leaving the test network.
 * The program-scheme list and the sign-on templates are admin settings: read
 * first, set, and put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::word-tile
 *   @e2e tiles::web-gateway
 *   @e2e tiles::author-cannot-type-an-identity-provider-address
 *   @e2e tiles::author-makes-a-web-tile-open-in-the-same-tab
 *   @e2e tiles::dashboard-default-applies-to-tiles-without-their-own-choice
 *   @e2e tiles::upgrade-keeps-todays-behaviour
 *   @e2e tiles::editor-explains-why-the-panel-is-unavailable
 *   @e2e tiles::search-result-opens-in-the-panel
 *
 * @spec openspec/specs/tiles/spec.md
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Locator,
	Page,
} from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	readPlacements,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const WORD = `Word ${STAMP}`
const WORD_ADDRESS = 'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx'
const GATEWAY = `Werkplek ${STAMP}`
const GATEWAY_URL = 'https://desktop.gemeente.nl/guacamole/#/client/werkplek'
const SSO = `Salarisstrook ${STAMP}`
const INTRANET = `Intranet ${STAMP}`
const ZAAK = `Zaaksysteem ${STAMP}`
const AGENDA = `Agenda ${STAMP}`
const PORTAL = `Portaal ${STAMP}`
const FILES = `Bestanden ${STAMP}`
const ENTRA =
	'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'

let admin: APIRequestContext
let own: OwnDashboard
let schemesBefore: unknown = []
let templatesBefore: unknown = []
const ids: Record<string, number> = {}
let placements = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	const current = await settings.json()
	schemesBefore = (current.data ?? current).tileAllowedSchemes ?? []
	templatesBefore = (current.data ?? current).ssoLaunchTemplates ?? []
	const set = await admin.put(`${API}/admin/settings`, {
		data: {
			tileAllowedSchemes: ['ms-word'],
			ssoLaunchTemplates: [
				{ key: 'entra', name: 'Microsoft Entra ID', urlTemplate: ENTRA },
			],
		},
	})
	expect(set.status(), await set.text()).toBe(200)

	own = await setUpOwnDashboard('e2e-tile-launch', `E2E tile launch ${STAMP}`)
	const tiles: Array<[string, Record<string, unknown>]> = [
		[WORD, { linkType: 'program', linkValue: WORD_ADDRESS, gridX: 0, gridY: 0 }],
		[
			GATEWAY,
			{
				linkType: 'remote-desktop',
				linkValue: '',
				gridX: 2,
				gridY: 0,
				content: { remote: { mode: 'gateway', url: GATEWAY_URL } },
			},
		],
		[
			SSO,
			{
				linkType: 'sso',
				linkValue: '',
				gridX: 4,
				gridY: 0,
				content: { sso: { template: 'entra', appId: 'a1b2c3' } },
			},
		],
		[
			INTRANET,
			{
				linkType: 'url',
				linkValue: 'https://intranet.example.nl/',
				gridX: 6,
				gridY: 0,
			},
		],
		[
			ZAAK,
			{
				linkType: 'url',
				linkValue: 'https://zaken.example.nl/',
				gridX: 8,
				gridY: 0,
			},
		],
		[AGENDA, { linkType: 'app', linkValue: 'activity', gridX: 10, gridY: 0 }],
		[
			PORTAL,
			{
				linkType: 'url',
				linkValue: 'https://portal.example.com/',
				gridX: 0,
				gridY: 2,
			},
		],
		[
			FILES,
			{
				linkType: 'app',
				linkValue: 'files',
				gridX: 2,
				gridY: 2,
				linkTarget: 'side-panel',
			},
		],
	]
	for (const [title, data] of tiles) {
		const res = await own.owner.api.post(`${API}/dashboard/${own.id}/tile`, {
			data: { title, ...data },
		})
		expect(res.status(), `${title}: ${await res.text()}`).toBe(201)
		ids[title] = Number((await res.json()).id)
	}
	const search = await own.owner.api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: 'search',
			gridX: 0,
			gridY: 4,
			gridWidth: 6,
			gridHeight: 2,
			content: { placeholder: '', fallbackTarget: '' },
		},
	})
	expect(search.status(), await search.text()).toBe(201)
	placements = Object.keys(ids).length + 1
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	if (admin) {
		await admin.put(`${API}/admin/settings`, {
			data: {
				tileAllowedSchemes: schemesBefore,
				ssoLaunchTemplates: templatesBefore,
			},
		})
		await admin.dispose()
	}
})

/**
 * The link of a tile.
 *
 * @param page The page.
 * @param title The tile title.
 * @return The anchor.
 */
function link(page: Page, title: string): Locator {
	return page.locator(
		`.grid-stack-item[gs-id="${ids[title]}"] a.tile-widget__link`,
	)
}

/**
 * Open the dashboard as its owner, with outside hosts answered locally.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function open(
	browser: Browser,
): Promise<{ context: BrowserContext; page: Page }> {
	const session = await openOwnDashboard(browser, own, placements)
	for (const host of [
		'intranet.example.nl',
		'zaken.example.nl',
		'desktop.gemeente.nl',
	]) {
		await session.context.route(`https://${host}/**`, (route) =>
			route.fulfill({
				status: 200,
				contentType: 'text/html',
				body: `<title>${host}</title>`,
			}),
		)
	}
	return session
}

/**
 * Open the tile editor for a tile, entering edit mode first.
 *
 * @param page The page.
 * @param title The tile title.
 * @return The editor dialog.
 */
async function openEditor(page: Page, title: string): Promise<Locator> {
	await enterEditMode(page)
	await page
		.locator(`.grid-stack-item[gs-id="${ids[title]}"] .tile-widget`)
		.getByRole('button', { name: 'Tile menu' })
		.click()
	await page.getByRole('menuitem', { name: 'Edit tile' }).click()
	const editor = page.getByRole('dialog', { name: 'Edit Tile' })
	await expect(editor).toBeVisible()
	await expect(editor.getByLabel('Title')).toHaveValue(title)
	return editor
}

/**
 * The stored content of a placement.
 *
 * @param id The placement id.
 * @return Its content.
 */
async function storedContent(id: number): Promise<Record<string, any>> {
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === id,
	)!
	return typeof stored.content === 'string'
		? JSON.parse(stored.content)
		: stored.content
}

// @e2e tiles::word-tile
test('a Word program tile hands its ms-word address to the browser and then says what to check', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const word = link(page, WORD)
		// The browser can only be handed the address: the anchor carries it as is.
		await expect(word).toHaveAttribute('href', WORD_ADDRESS)
		await expect(word).toHaveAttribute('target', '_self')
		const hint = page.locator(
			`.grid-stack-item[gs-id="${ids[WORD]}"] .tile-widget__hint`,
		)
		await expect(hint).toHaveCount(0)
		await word.click({ noWaitAfter: true })
		await expect(hint).toHaveText(
			'Nothing happened? The program may not be installed on this computer.',
		)
		await expect(hint).toHaveAttribute('role', 'status')
	} finally {
		await context.close()
	}
})

// @e2e tiles::web-gateway
test('a remote-desktop tile in gateway mode opens the gateway address in a new tab', async ({
	browser,
}) => {
	// Signing in at the gateway is the organisation's identity provider's job;
	// what LaunchPad owns is opening exactly this address, with no credential.
	const { context, page } = await open(browser)
	try {
		const gateway = link(page, GATEWAY)
		await expect(gateway).toHaveAttribute('href', GATEWAY_URL)
		await expect(gateway).toHaveAttribute('target', '_blank')
		await expect(gateway).toHaveAttribute('rel', 'noopener noreferrer')
		const [tab] = await Promise.all([
			context.waitForEvent('page'),
			gateway.click(),
		])
		await tab.waitForURL((url) =>
			url.href.startsWith('https://desktop.gemeente.nl/guacamole/'),
		)
		expect(tab.url()).toBe(GATEWAY_URL)
	} finally {
		await context.close()
	}
})

// @e2e tiles::author-cannot-type-an-identity-provider-address
test('the single sign-on editor offers a template and an app id, and no address field', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const editor = await openEditor(page, SSO)
		await expect(
			editor.locator('[data-testid="tile-sso-template"]'),
		).toBeVisible()
		await expect(
			editor.locator('[data-testid="tile-sso-template"]'),
		).toContainText('Microsoft Entra ID')
		await expect(
			editor
				.locator(
					'[data-testid="tile-sso-app-id"] input, input[data-testid="tile-sso-app-id"]',
				)
				.first(),
		).toHaveValue('a1b2c3')
		await expect(editor.getByLabel('URL', { exact: true })).toHaveCount(0)
		await expect(
			editor.locator(
				'[data-testid="tile-remote-url"], [data-testid="tile-program-address"]',
			),
		).toHaveCount(0)
		// No text field anywhere in the editor holds an identity-provider address.
		const values = await editor
			.locator('input[type="text"], input[type="url"], input:not([type])')
			.evaluateAll((inputs) =>
				inputs.map((input) => (input as HTMLInputElement).value),
			)
		expect(values.filter((value) => /^https?:\/\//.test(value))).toEqual([])
	} finally {
		await context.close()
	}
})

// @e2e tiles::author-makes-a-web-tile-open-in-the-same-tab
test('setting "Opens in" to Same tab in the editor makes the web tile open in the current tab', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await expect(link(page, INTRANET)).toHaveAttribute('target', '_blank')
		const editor = await openEditor(page, INTRANET)
		await editor
			.locator('[data-test="tile-editor-opens-in"]')
			.getByText('Same tab', { exact: true })
			.click()
		const saved = page.waitForResponse(
			(res) =>
				res.request().method() === 'PUT'
				&& res.url().includes(`/api/widgets/${ids[INTRANET]}`),
		)
		await editor.getByRole('button', { name: 'Save' }).click()
		expect((await saved).status()).toBe(200)
		expect((await storedContent(ids[INTRANET])).linkTarget).toBe('same-tab')

		// Back on the Workspace page in view mode, the click stays in this tab.
		await page
			.locator(MORE_MENU)
			.locator('[data-testid="launchpad-more-menu-trigger"]')
			.click()
		await page.locator('[data-testid="launchpad-more-menu-item-edit"]').click()
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
		await expect(link(page, INTRANET)).toHaveAttribute('target', '_self')
		const pages = context.pages().length
		await link(page, INTRANET).click()
		await page.waitForURL('https://intranet.example.nl/')
		expect(context.pages()).toHaveLength(pages)
	} finally {
		await context.close()
	}
})

// @e2e tiles::upgrade-keeps-todays-behaviour
test('with no dashboard default and no tile target, a web tile opens a new tab and an app tile the same tab', async ({
	browser,
}) => {
	const dashboard = await own.owner.api.get(`${API}/dashboard/${own.id}`)
	const body = await dashboard.json()
	expect(
		(body.dashboard ?? body.data?.dashboard ?? body).defaultLinkTarget ?? null,
	).toBeNull()
	expect((await storedContent(ids[ZAAK])).linkTarget ?? null).toBeNull()
	expect((await storedContent(ids[AGENDA])).linkTarget ?? null).toBeNull()

	const { context, page } = await open(browser)
	try {
		await expect(link(page, ZAAK)).toHaveAttribute('target', '_blank')
		const [tab] = await Promise.all([
			context.waitForEvent('page'),
			link(page, ZAAK).click(),
		])
		await tab.waitForURL('https://zaken.example.nl/')
		await tab.close()

		await expect(link(page, AGENDA)).toHaveAttribute('target', '_self')
		await link(page, AGENDA).click()
		await page.waitForURL(/\/apps\/activity/)
		expect(context.pages()).toHaveLength(1)
	} finally {
		await context.close()
	}
})

// @e2e tiles::dashboard-default-applies-to-tiles-without-their-own-choice
test('a dashboard default of Same tab makes a tile without its own target open in the current tab', async ({
	browser,
}) => {
	const set = await own.owner.api.put(`${API}/dashboard/${own.id}`, {
		data: { defaultLinkTarget: 'same-tab' },
	})
	expect(set.status(), await set.text()).toBe(200)
	try {
		expect((await storedContent(ids[ZAAK])).linkTarget ?? null).toBeNull()
		const { context, page } = await open(browser)
		try {
			await expect(link(page, ZAAK)).toHaveAttribute('target', '_self')
			await link(page, ZAAK).click()
			await page.waitForURL('https://zaken.example.nl/')
			expect(context.pages()).toHaveLength(1)
		} finally {
			await context.close()
		}
	} finally {
		await own.owner.api.put(`${API}/dashboard/${own.id}`, {
			data: { defaultLinkTarget: null },
		})
	}
})

// @e2e tiles::editor-explains-why-the-panel-is-unavailable
test('for a host that may not be framed the editor disables "Panel beside the tiles" and says why', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const editor = await openEditor(page, PORTAL)
		const opensIn = editor.locator('[data-test="tile-editor-opens-in"]')
		await expect(
			opensIn.getByRole('radio', { name: 'Panel beside the tiles' }),
		).toBeDisabled({ timeout: 15_000 })
		await expect(
			opensIn.locator('[data-test="tile-editor-panel-refusal"]'),
		).toHaveText('This address cannot open in the panel.')
		// The other choices stay available.
		await expect(opensIn.getByRole('radio', { name: 'New tab' })).toBeEnabled()
	} finally {
		await context.close()
	}
})

// @e2e tiles::search-result-opens-in-the-panel
test('Enter on a panel tile in quick search opens it in the panel beside the tiles', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const input = page.locator('[data-test="quick-search-input"]')
		await expect(input).toBeVisible()
		await input.click()
		await input.fill('best')
		await expect(
			page
				.locator('[data-test="quick-search-option"]')
				.filter({ hasText: FILES }),
		).toBeVisible()
		const pages = context.pages().length
		await page.keyboard.press('Enter')

		const panel = page.locator('[data-test="tile-side-panel"]')
		await expect(panel).toBeVisible({ timeout: 10_000 })
		await expect(
			panel.locator('[data-test="tile-side-panel-frame"]'),
		).toHaveAttribute('src', /\/apps\/files/)
		expect(page.url()).toMatch(/\/apps\/launchpad/)
		expect(context.pages()).toHaveLength(pages)
	} finally {
		await context.close()
	}
})
