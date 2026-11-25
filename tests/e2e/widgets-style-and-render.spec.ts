/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two widgets scenarios (openspec/specs/widgets/spec.md) that were cited only
 * by `test.fixme`, which gate-19 does not count. They now run, here, without
 * serial mode: widgets-picker.spec.ts and widgets-rendering.spec.ts run
 * serially, so a red there would skip every test after it.
 *
 * style-editor-supports-background-and-border-options (REQ-WDG-008) is
 * expected green: the border, radius, padding and header-colour controls
 * came with nextcloud-vue #2376.
 *
 * conditional-widget-evaluated-at-render-time (REQ-WDG-005) is expected
 * green. The fixme said no read path evaluated the rules; since REQ-VIS-013
 * the dashboard read does (DashboardPayloads::renderedPlacements() calls
 * ConditionalService::visiblePlacements()). An owner or editor still gets a
 * hidden placement back, dimmed and marked `hiddenForViewers`, so "displayed
 * only if rules evaluate to show" is asserted for a view-only viewer, who is
 * the person the rules hide it from; the owner's dimmed view is cited in
 * conditional-visibility-render.spec.ts.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::style-editor-supports-background-and-border-options
 *   @e2e widgets::conditional-widget-evaluated-at-render-time
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'
import {
	addTile,
	readPlacements,
	readRules,
	seedOwnDashboard,
	seedRule,
} from './support/visibilityRules.ts'
import {
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
} from './support/widgets.ts'

const RUN = Date.now()
const STAMP = `E2E WidgetStyleRender ${RUN}`

interface CatalogWidget {
	id: string
	iconUrl: string | null
}

let admin: APIRequestContext
let allowBefore: boolean | null = null

test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
})

test.afterAll(async () => {
	await restoreAllowUserDashboards(admin, allowBefore)
	await admin.dispose()
})

/**
 * Open the cog's Edit on a placement, in edit mode.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return nothing.
 */
async function editViaCog(page: Page, placementId: number): Promise<void> {
	const cog = page.locator(
		`[data-testid="widget-placement-${placementId}"] [data-testid="cn-widget-edit-cog"]`,
	)
	await expect(cog).toBeVisible({ timeout: 10_000 })
	await cog.getByRole('button').first().click()
	await page.locator('[data-testid="cn-widget-edit-cog-edit"]').click()
}

test.describe('REQ-WDG-008 the style editor', () => {
	let dashboard: SeededDashboard | null = null

	test.afterEach(async () => {
		await removeSeededDashboard(admin, dashboard)
		dashboard = null
	})

	// @e2e widgets::style-editor-supports-background-and-border-options
	test('the style editor offers background, border style/width/colour/radius, padding and header colours', async ({
		page,
	}) => {
		dashboard = await seedActiveDashboard(admin, `${STAMP} style editor`)
		await clearSeededWidgets(admin, dashboard.id)
		const list = await admin.get(`${API}/widgets`)
		expect(list.status(), await list.text()).toBe(200)
		const catalog = (await list.json()) as CatalogWidget[]
		const widget = catalog.find((w) => w.iconUrl) ?? catalog[0]
		expect(widget, 'no Nextcloud widget on this server').toBeTruthy()
		const placement = await seedPlacement(admin, dashboard.id, {
			widgetId: widget!.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})

		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await enterEditMode(page)
		await editViaCog(page, placement.id)

		const editor = page.locator('[data-testid="cn-widget-style-editor"]')
		await expect(editor).toBeVisible({ timeout: 10_000 })
		await expect(
			editor.locator('[data-testid="cn-widget-style-color"]'),
		).toBeVisible()
		await expect(editor.getByLabel(/border style/i)).toBeVisible()
		for (const style of ['None', 'Solid', 'Dashed', 'Dotted']) {
			await editor.getByLabel(/border style/i).click()
			await expect(page.getByRole('option', { name: style })).toBeVisible()
			await page.keyboard.press('Escape')
		}
		await expect(editor.getByLabel(/border width/i)).toBeVisible()
		await expect(editor.getByLabel(/border colou?r/i)).toBeVisible()
		await expect(editor.getByLabel(/border radius/i)).toBeVisible()
		for (const side of ['top', 'right', 'bottom', 'left']) {
			await expect(
				editor.getByLabel(new RegExp(`padding.*${side}`, 'i')),
			).toBeVisible()
		}
		await expect(editor.getByLabel(/header background/i)).toBeVisible()
		await expect(editor.getByLabel(/header text/i)).toBeVisible()
	})
})

test.describe('REQ-WDG-005 rules are evaluated when the dashboard renders', () => {
	const principals: Principal[] = []

	test.afterEach(async () => {
		for (const who of principals.splice(0)) {
			await removePrincipal(admin, who).catch(() => null)
		}
	})

	// @e2e widgets::conditional-widget-evaluated-at-render-time
	test('a visible placement whose rules do not match is not displayed; one whose rules match is', async ({
		browser,
	}) => {
		const alice = await createPrincipal(admin, 'e2e-wdg-rules-owner')
		const bob = await createPrincipal(admin, 'e2e-wdg-rules-viewer')
		principals.push(alice, bob)
		const dashboard = await seedOwnDashboard(alice, `${STAMP} rules`)
		const matchingTitle = `${STAMP} matching`
		const failingTitle = `${STAMP} failing`
		const matching = await addTile(alice.api, dashboard.id, matchingTitle, 0)
		const failing = await addTile(alice.api, dashboard.id, failingTitle, 1)

		// Both placements are isVisible 1; only their rules differ.
		await seedRule(alice.api, matching, {
			ruleType: 'date',
			ruleConfig: { startDate: `${new Date().getUTCFullYear() - 1}-01-01` },
			isInclude: true,
		})
		await seedRule(alice.api, failing, {
			ruleType: 'group',
			ruleConfig: { groups: [`e2e-nobody-${RUN}`] },
			isInclude: true,
		})
		await publishForRecipients(alice.api, dashboard.id)
		await shareWith(alice.api, dashboard.id, bob.username, 'view_only')

		for (const id of [matching, failing]) {
			const stored = (await readPlacements(alice.api, dashboard.id)).find(
				(p) => Number(p.id) === id,
			)
			expect(Number(stored?.isVisible), `placement ${id} is isVisible 1`).toBe(1)
		}
		// The server's own evaluation, per placement.
		expect((await readRules(alice.api, matching)).isVisible).toBe(true)
		expect((await readRules(alice.api, failing)).isVisible).toBe(false)

		// The read the grid renders from evaluates the rules for the viewer.
		const served = (await readPlacements(bob.api, dashboard.id)).map((p) =>
			Number(p.id),
		)
		expect(served).toContain(matching)
		expect(served).not.toContain(failing)

		// And the rendered grid shows only the matching widget.
		const preference = await bob.api.post(`${API}/dashboards/active`, {
			data: { uuid: dashboard.uuid },
		})
		expect(preference.status(), await preference.text()).toBeLessThan(300)
		const { context, page } = await loginAs(browser, bob.username, bob.password)
		try {
			await page.goto('/index.php/apps/launchpad')
			await expect(
				page.locator('.launchpad-grid-item').filter({ hasText: matchingTitle }).first(),
			).toBeVisible({ timeout: 30_000 })
			await expect(
				page.locator('.launchpad-grid-item').filter({ hasText: failingTitle }),
			).toHaveCount(0)
			await expect(page.locator(`[data-placement-id="${failing}"]`)).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})
