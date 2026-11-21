/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard stores its hierarchies (drill-down-cross-widget-filter,
 * REQ-CWF-011): stored through the dashboard API, returned on every read,
 * refused when malformed or sent by a viewer, cleared by an empty list, and
 * read by the filter bus of the open dashboard.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::an-owner-picks-the-nl-administrative-preset
 *   @e2e cross-widget-filter::stored-hierarchies-put-consuming-widgets-on-the-bus
 *   @e2e cross-widget-filter::a-malformed-descriptor-is-refused
 *   @e2e cross-widget-filter::a-viewer-cannot-change-the-hierarchies
 *   @e2e cross-widget-filter::clearing-the-hierarchies
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { NL_ADMINISTRATIVE, putHierarchies, readHierarchies, storeHierarchies } from './support/hierarchies.ts'
import { adminContext, assertActingAs, createPrincipal, removePrincipal, seedDashboardWithWidget, shareWith } from './support/principals.ts'
import { adminApi, openWorkspace, Seeds } from './support/widgetPlacements.ts'

test.describe('A dashboard stores its hierarchies', () => {
	test('@e2e cross-widget-filter::an-owner-picks-the-nl-administrative-preset the stored preset comes back on every read', async () => {
		const seeds = new Seeds(await adminApi())
		try {
			const dash = await seeds.dashboard(`E2E hierarchies stored ${Date.now()}`)
			expect(await readHierarchies(seeds.api, dash.id)).toEqual([])

			await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])

			expect(await readHierarchies(seeds.api, dash.id)).toEqual([NL_ADMINISTRATIVE])
			// A later edit that does not name them leaves them alone.
			const rename = await seeds.api.put(`/index.php/apps/launchpad/api/dashboard/${dash.id}`, { data: { name: 'Renamed' } })
			expect(rename.status(), await rename.text()).toBe(200)
			expect(await readHierarchies(seeds.api, dash.id)).toEqual([NL_ADMINISTRATIVE])
		} finally {
			await seeds.cleanup()
		}
	})

	test('@e2e cross-widget-filter::a-malformed-descriptor-is-refused each malformed value answers 400 and stores nothing', async () => {
		const seeds = new Seeds(await adminApi())
		try {
			const dash = await seeds.dashboard(`E2E hierarchies refused ${Date.now()}`)
			await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
			const malformed: Array<[unknown, string]> = [
				['nl-administrative', 'hierarchies must be a list'],
				[[{ levels: [{ dimension: 'gemeenteCode' }] }], 'hierarchies[0].id'],
				[[{ id: 'x', levels: [] }], 'hierarchies[0].levels must be a non-empty list'],
				[[{ id: 'x', levels: [{ label: 'Gemeente' }] }], 'hierarchies[0].levels[0].dimension'],
				[[{ id: 'x', levels: [{ dimension: 'a' }, { dimension: 'a' }] }], 'repeats dimension a'],
				[[{ id: 'x', levels: [{ dimension: 'a' }, { dimension: 'b', parentDimension: 'z' }] }], 'parentDimension must be the dimension of the level above'],
			]
			for (const [value, message] of malformed) {
				const res = await putHierarchies(seeds.api, dash.id, value)
				const text = await res.text()
				expect(res.status(), text).toBe(400)
				expect(text).toContain(message)
			}
			expect(await readHierarchies(seeds.api, dash.id)).toEqual([NL_ADMINISTRATIVE])
		} finally {
			await seeds.cleanup()
		}
	})

	test('@e2e cross-widget-filter::a-viewer-cannot-change-the-hierarchies a view-only recipient is refused', async () => {
		const admin = await adminContext()
		const viewer = await createPrincipal(admin, 'cwf-viewer')
		let dashboardId = 0
		try {
			await assertActingAs(viewer.api, viewer.username)
			const dash = await seedDashboardWithWidget(admin, `E2E hierarchies viewer ${Date.now()}`)
			dashboardId = dash.id
			await storeHierarchies(admin, dash.id, [NL_ADMINISTRATIVE])
			await shareWith(admin, dash.id, viewer.username, 'view_only')

			// The viewer reads them, so the viewer's filter bus uses them too.
			expect(await readHierarchies(viewer.api, dash.id)).toEqual([NL_ADMINISTRATIVE])

			const res = await putHierarchies(viewer.api, dash.id, [])
			expect(res.status(), await res.text()).toBe(403)
			expect(await readHierarchies(admin, dash.id)).toEqual([NL_ADMINISTRATIVE])
		} finally {
			if (dashboardId) {
				await admin.delete(`/index.php/apps/launchpad/api/dashboard/${dashboardId}`)
			}
			await removePrincipal(admin, viewer)
			await admin.dispose()
		}
	})

	test('@e2e cross-widget-filter::clearing-the-hierarchies an empty list removes them', async () => {
		const seeds = new Seeds(await adminApi())
		try {
			const dash = await seeds.dashboard(`E2E hierarchies cleared ${Date.now()}`)
			await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])

			await storeHierarchies(seeds.api, dash.id, [])

			expect(await readHierarchies(seeds.api, dash.id)).toEqual([])
		} finally {
			await seeds.cleanup()
		}
	})

	/**
	 * The off-bus warnings a page logs for consuming widgets.
	 *
	 * @param page the page.
	 * @return the live list of warnings.
	 */
	function offBusWarnings(page: Page): string[] {
		const warnings: string[] = []
		page.on('console', (message) => {
			if (message.text().includes('is not on the filter bus: consumes-unknown-dimension')) {
				warnings.push(message.text())
			}
		})
		return warnings
	}

	test('@e2e cross-widget-filter::stored-hierarchies-put-consuming-widgets-on-the-bus a consuming chart joins the bus on the stored hierarchy alone', async ({ page }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const dash = await seeds.dashboard(`E2E hierarchies bus ${Date.now()}`)
			await clearSeededWidgets(seeds.api, dash.id)
			const chart = await seeds.widget(dash, {
				widgetId: 'chart', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 4,
				content: { title: 'Wijken', chartKind: 'bar-list', series: [{ name: 'n', data: [3, 1] }], categories: ['a', 'b'], consumes: ['gemeenteCode'] },
			})

			// Control: without stored hierarchies the chart stays off the bus and says so.
			const before = offBusWarnings(page)
			await openWorkspace(page, chart.id)
			await expect.poll(() => before.length, { timeout: 20_000 }).toBeGreaterThan(0)

			await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
			const after = offBusWarnings(page)
			await page.reload()
			await openWorkspace(page, chart.id)
			await page.waitForTimeout(2_000)
			expect(after).toEqual([])
		} finally {
			await seeds.cleanup()
		}
	})
})
