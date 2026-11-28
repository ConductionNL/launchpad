/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget visibility rules apply when a dashboard renders (REQ-VIS-013,
 * launchpad#98): a viewer does not get a widget the rules hide for them, the
 * owner still sees it, dimmed with a "Hidden for viewers" badge, and a public
 * share leaves it out.
 *
 * alice owns the dashboard and is in no group; bob gets it shared at
 * `view_only`. The subject tile carries one include rule for a group neither
 * of them is in, the anchor tile carries none, so the anchor is the control
 * that the dashboard rendered at all.
 *
 * Gate-19 @e2e traceability:
 *   @e2e conditional-visibility::a-viewer-does-not-get-a-hidden-widget
 *   @e2e conditional-visibility::the-owner-sees-a-hidden-widget-dimmed
 *   @e2e conditional-visibility::a-public-share-leaves-a-hidden-widget-out
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { readAllowUserDashboards, setAllowUserDashboards } from './support/adminTemplates.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	adminContext,
	API,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	shareWith,
} from './support/principals.ts'
import { addTile, readPlacements, seedOwnDashboard, seedRule } from './support/visibilityRules.ts'

const RUN = Date.now()
const GROUP = `cvr-staff-${RUN}`
const SUBJECT = `CVR subject ${RUN}`
const ANCHOR = `CVR anchor ${RUN}`

let admin: APIRequestContext
let alice: Principal
let bob: Principal
let dashboard: { id: number; uuid: string }
let subjectId: number
let anchorId: number
/** The admin flag as it was before this file, put back afterwards. */
let originalAllowUserDashboards: boolean | null = null

test.describe('conditional visibility at render time (REQ-VIS-013)', () => {
	test.beforeAll(async () => {
		admin = await adminContext()
		// alice makes a personal dashboard, refused with personal_dashboards_disabled
		// while the admin flag is off (its default on a fresh instance).
		originalAllowUserDashboards = await readAllowUserDashboards(admin)
		if (!originalAllowUserDashboards) {
			await setAllowUserDashboards(admin, true)
		}
		await createGroup(admin, GROUP)
		alice = await createPrincipal(admin, 'cvr-alice')
		bob = await createPrincipal(admin, 'cvr-bob')

		dashboard = await seedOwnDashboard(alice, `CVR board ${RUN}`)
		anchorId = await addTile(alice.api, dashboard.id, ANCHOR, 0)
		subjectId = await addTile(alice.api, dashboard.id, SUBJECT, 1)
		await seedRule(alice.api, subjectId, {
			ruleType: 'group',
			ruleConfig: { groups: [GROUP] },
			isInclude: true,
		})
		await publishForRecipients(alice.api, dashboard.id)
		await shareWith(alice.api, dashboard.id, bob.username, 'view_only')
	})

	test.afterAll(async () => {
		for (const who of [alice, bob]) {
			if (who) {
				await removePrincipal(admin, who).catch(() => null)
			}
		}
		await removeGroup(admin, GROUP).catch(() => null)
		if (originalAllowUserDashboards === false) {
			await setAllowUserDashboards(admin, false).catch(() => null)
		}
		await admin.dispose()
	})

	// @e2e conditional-visibility::a-viewer-does-not-get-a-hidden-widget
	test('a viewer does not get the widget the rules hide for them', async ({ browser }) => {
		const ids = (await readPlacements(bob.api, dashboard.id)).map((p) => Number(p.id))
		expect(ids, 'CONTROL: the rule-free anchor reaches the viewer').toContain(anchorId)
		expect(ids, 'the hidden widget must not reach the viewer').not.toContain(subjectId)

		const preference = await bob.api.post(`${API}/dashboards/default`, {
			data: { uuid: dashboard.uuid },
		})
		expect(preference.status(), await preference.text()).toBeLessThan(300)
		const { context, page } = await loginAs(browser, bob.username, bob.password)
		try {
			await page.goto('/index.php/apps/launchpad')
			await expect(
				page.locator('.launchpad-grid-item').filter({ hasText: ANCHOR }).first(),
			).toBeVisible({ timeout: 30_000 })
			await expect(page.locator('.launchpad-grid-item').filter({ hasText: SUBJECT })).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e conditional-visibility::the-owner-sees-a-hidden-widget-dimmed
	test('the owner still sees the hidden widget, dimmed and marked hidden for viewers', async ({ browser }) => {
		const placements = await readPlacements(alice.api, dashboard.id)
		const subject = placements.find((p) => Number(p.id) === subjectId)
		const anchor = placements.find((p) => Number(p.id) === anchorId)
		expect(subject?.hiddenForViewers, 'the owner gets the hidden widget, marked').toBe(true)
		expect(anchor?.hiddenForViewers, 'CONTROL: the rule-free anchor is not marked').toBe(false)

		const { context, page } = await loginAs(browser, alice.username, alice.password)
		try {
			await page.goto('/index.php/apps/launchpad')
			const item = page.locator('.launchpad-grid-item').filter({ hasText: SUBJECT }).first()
			await expect(item).toBeVisible({ timeout: 30_000 })
			await expect(item).toHaveClass(/launchpad-grid-item--hidden-for-viewers/)
			await expect(item.getByTestId('hidden-for-viewers-badge')).toHaveText('Hidden for viewers')
			const anchorItem = page.locator('.launchpad-grid-item').filter({ hasText: ANCHOR }).first()
			await expect(anchorItem.getByTestId('hidden-for-viewers-badge')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e conditional-visibility::a-public-share-leaves-a-hidden-widget-out
	test('a public share leaves the hidden widget out for a visitor', async () => {
		const created = await alice.api.post(`${API}/dashboards/${dashboard.uuid}/public-share`, {
			data: { expiresAt: '2099-12-31T23:59:59Z' },
		})
		expect(created.status(), await created.text()).toBe(201)
		const token = (await created.json()).token

		const visitor = await request.newContext({
			baseURL: BASE_URL,
			storageState: { cookies: [], origins: [] },
		})
		try {
			const res = await visitor.get(`/index.php/apps/launchpad/s/${token}/data`)
			expect(res.status(), await res.text()).toBe(200)
			const ids = ((await res.json()).placements ?? []).map((p: { id: number }) => Number(p.id))
			expect(ids, 'CONTROL: the anchor is on the shared page').toContain(anchorId)
			expect(ids, 'the hidden widget must not be on the shared page').not.toContain(subjectId)
		} finally {
			await visitor.dispose()
		}
	})
})
