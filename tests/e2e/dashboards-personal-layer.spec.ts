/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A person hides widgets on a shared dashboard for themselves only, brings
 * them back one at a time, or resets their whole view
 * (openspec/specs/dashboards/spec.md, REQ-PERSUI-001..002).
 *
 * Olga owns the dashboard "Team" with three text widgets, publishes it and
 * shares it with Pieter and Sanne at view level, all through the API. Pieter
 * then works through the widget menu and the "Hidden (n)" control; Sanne, in
 * her own browser session, proves that what Pieter does stays his.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::hide-one-widget
 *   @e2e dashboards::show-one-again
 *   @e2e dashboards::reset
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowManyPersonalDashboards,
	publication,
	removeTree,
} from './support/dashboardHierarchy.ts'
import {
	adminContext,
	API,
	createPrincipal,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const T = Date.now()

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let olga: Principal | null = null
let pieter: Principal | null = null
let sanne: Principal | null = null
let team: { id: number; uuid: string } | null = null
const widgets: number[] = []

/**
 * Pieter's personal layer on Team, straight from the API.
 *
 * @return the layer.
 */
async function pieterLayer(): Promise<{
	hidden: number[]
	overrides: Record<string, Record<string, number>>
	hasLayer: boolean
}> {
	const res = await pieter!.api.get(`${API}/dashboards/${team!.id}/personal-layer`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return {
		hidden: (body.hidden ?? []).map(Number),
		overrides: Array.isArray(body.overrides) ? {} : (body.overrides ?? {}),
		hasLayer: body.hasLayer === true,
	}
}

/**
 * Replace Pieter's personal layer (seeding only).
 *
 * @param hidden the placements he hid.
 * @param overrides the placements he moved.
 * @return nothing.
 */
async function seedLayer(
	hidden: number[],
	overrides: Record<number, Record<string, number>> = {},
): Promise<void> {
	const res = await pieter!.api.put(
		`${API}/dashboards/${team!.id}/personal-layer`,
		{ data: { hidden, overrides } },
	)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The layout a principal reads for Team, per placement id.
 *
 * @param who the principal.
 * @return gridX/gridY per placement.
 */
async function layoutFor(
	who: Principal,
): Promise<Map<number, { gridX: number; gridY: number }>> {
	const res = await who.api.get(`${API}/dashboard/${team!.id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return new Map(
		(body.placements as Array<{ id: number; gridX: number; gridY: number }>).map(
			(p) => [
				Number(p.id),
				{ gridX: Number(p.gridX), gridY: Number(p.gridY) },
			],
		),
	)
}

/**
 * Log a principal in with Team open.
 *
 * @param browser the browser.
 * @param who the principal.
 * @return the context and page.
 */
async function openTeam(browser: Browser, who: Principal) {
	const preference = await who.api.post(`${API}/dashboards/default`, {
		data: { uuid: team!.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
	const session = await loginAs(browser, who.username, who.password)
	await session.page.goto('/index.php/apps/launchpad')
	await expect(
		session.page.getByTestId('launchpad-dashboard-region'),
	).toHaveAttribute('data-dashboard-uuid', team!.uuid, { timeout: 30_000 })
	return session
}

/**
 * A widget on the page by placement id.
 *
 * @param page the page.
 * @param id the placement id.
 * @return the widget.
 */
function widget(page: Page, id: number) {
	return page.getByTestId(`widget-placement-${id}`)
}

test.describe('dashboards — hide widgets for yourself (REQ-PERSUI-001..002)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		olga = await createPrincipal(admin, 'e2e-pers-olga')
		pieter = await createPrincipal(admin, 'e2e-pers-pieter')
		sanne = await createPrincipal(admin, 'e2e-pers-sanne')

		const seeded = await seedDashboardWithWidget(olga.api, `Team ${T}`)
		team = { id: seeded.id, uuid: seeded.uuid }
		widgets.push(seeded.placementId)
		for (const [y, text] of [
			[3, 'second'],
			[6, 'third'],
		] as const) {
			const res = await olga.api.post(`${API}/dashboard/${team.id}/widgets`, {
				data: {
					widgetId: 'text',
					gridX: 0,
					gridY: y,
					gridWidth: 4,
					gridHeight: 2,
					content: { text, contentMode: 'markdown' },
				},
			})
			expect(res.status(), await res.text()).toBeLessThan(300)
			widgets.push(Number((await res.json()).id))
		}

		await publication(olga.api, team.uuid, 'publish')
		await shareWith(olga.api, team.id, pieter.username, 'view_only')
		await shareWith(olga.api, team.id, sanne.username, 'view_only')
	})

	test.afterAll(async () => {
		await removeTree(olga?.api as APIRequestContext, team?.id)
		await removePrincipal(admin, olga)
		await removePrincipal(admin, pieter)
		await removePrincipal(admin, sanne)
		await restoreSettings?.()
		await admin?.dispose()
	})

	test.beforeEach(async () => {
		const res = await pieter!.api.delete(
			`${API}/dashboards/${team!.id}/personal-layer`,
		)
		expect(res.status(), await res.text()).toBeLessThan(300)
	})

	// @e2e dashboards::hide-one-widget
	test('"Hide for me" removes the widget for Pieter only; Sanne still sees it', async ({
		browser,
	}) => {
		const [weather] = widgets
		const pieterSession = await openTeam(browser, pieter!)
		try {
			const page = pieterSession.page
			await expect(widget(page, weather)).toBeVisible()
			await widget(page, weather)
				.getByRole('button', { name: 'Widget options' })
				.click()
			const saved = page.waitForResponse(
				(res) =>
					res.request().method() === 'PUT'
					&& res.url().endsWith(`/dashboards/${team!.id}/personal-layer`),
			)
			await page.getByTestId('widget-hide-for-me').click()
			expect((await saved).status()).toBe(200)

			await expect(widget(page, weather)).toHaveCount(0)
			await expect(page.getByTestId('hidden-widgets-toggle')).toHaveText(
				'Hidden (1)',
			)
			for (const other of widgets.slice(1)) {
				await expect(widget(page, other)).toBeVisible()
			}
			// It stays gone after a reload: the layer was saved.
			await page.reload()
			await expect(page.getByTestId('launchpad-dashboard-region')).toBeVisible(
				{ timeout: 30_000 },
			)
			await expect(widget(page, widgets[1])).toBeVisible()
			await expect(widget(page, weather)).toHaveCount(0)
		} finally {
			await pieterSession.context.close()
		}
		expect((await pieterLayer()).hidden).toEqual([weather])

		const sanneSession = await openTeam(browser, sanne!)
		try {
			await expect(widget(sanneSession.page, weather)).toBeVisible()
			await expect(
				sanneSession.page.getByTestId('hidden-widgets'),
			).toHaveCount(0)
		} finally {
			await sanneSession.context.close()
		}
		expect([...(await layoutFor(olga!)).keys()]).toContain(weather)
	})

	// @e2e dashboards::show-one-again
	test('"Show again" on one of two hidden widgets brings it back and the control reads "Hidden (1)"', async ({
		browser,
	}) => {
		const [first, second] = widgets
		await seedLayer([first, second])

		const { context, page } = await openTeam(browser, pieter!)
		try {
			await expect(widget(page, first)).toHaveCount(0)
			await expect(widget(page, second)).toHaveCount(0)
			const toggle = page.getByTestId('hidden-widgets-toggle')
			await expect(toggle).toHaveText('Hidden (2)')

			await toggle.click()
			await expect(page.getByTestId('hidden-show-again')).toHaveCount(2)
			await page.getByTestId('hidden-show-again').first().click()

			await expect(toggle).toHaveText('Hidden (1)')
			// The count changes before the widget that came back renders, so
			// wait until exactly one of the two is on the page before asking
			// which one it is.
			await expect
				.poll(async () => (await widget(page, first).count()) + (await widget(page, second).count()))
				.toBe(1)
			const back = (await widget(page, first).count()) > 0 ? first : second
			const still = back === first ? second : first
			await expect(widget(page, back)).toBeVisible()
			await expect(widget(page, still)).toHaveCount(0)
			expect((await pieterLayer()).hidden).toEqual([still])
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::reset
	test('"Reset my view", confirmed, brings back both hidden widgets and the moved one to the shared layout', async ({
		browser,
	}) => {
		const [first, second, third] = widgets
		const shared = await layoutFor(olga!)
		await seedLayer([first, second], { [third]: { gridX: 8, gridY: 10 } })
		expect((await layoutFor(pieter!)).get(third)).toEqual({
			gridX: 8,
			gridY: 10,
		})

		const { context, page } = await openTeam(browser, pieter!)
		try {
			await expect(page.getByTestId('hidden-widgets-toggle')).toHaveText(
				'Hidden (2)',
			)
			await page.getByTestId('hidden-widgets-toggle').click()
			await page.getByTestId('hidden-reset').click()

			const dialog = page.getByRole('dialog', { name: 'Reset your view?' })
			await expect(dialog).toBeVisible()
			const reset = page.waitForResponse(
				(res) =>
					res.request().method() === 'DELETE'
					&& res.url().endsWith(`/dashboards/${team!.id}/personal-layer`),
			)
			await dialog.getByRole('button', { name: 'Reset my view' }).click()
			expect((await reset).status()).toBeLessThan(300)

			for (const id of widgets) {
				await expect(widget(page, id)).toBeVisible()
			}
			await expect(page.getByTestId('hidden-widgets')).toHaveCount(0)
		} finally {
			await context.close()
		}

		const layer = await pieterLayer()
		expect(layer.hasLayer).toBe(false)
		expect(layer.hidden).toEqual([])
		expect((await layoutFor(pieter!)).get(third)).toEqual(shared.get(third))
	})
})
