/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for openspec/specs/launchpad-mobile-remote-access, written
 * for e2e-coverage-programme task 4.18.
 *
 * Most of that capability was never built: no `mobileReady` field exists in
 * any registry, manifest or commit, so nothing hides a widget on a phone and
 * there is no "widgets hidden on mobile" notice. Those scenarios carry a
 * `never built` exclusion in the spec. What the spec ALSO says is that the
 * mobile shell's curated app list needs no new plumbing: an admin places an
 * existing quicklinks widget, scopes it to a role with an existing
 * visibility rule, and a phone shows it. That composition exists today and
 * is what this file proves, at a 375 px viewport.
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const API = '/index.php/apps/launchpad/api'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}
const STAMP = Date.now().toString(36)
const CURATED = [
	{ label: `Mail ${STAMP}`, url: '/index.php/apps/mail/' },
	{ label: `Files ${STAMP}`, url: '/index.php/apps/files/' },
]
const OTHER_ROLE_LINK = `Finance only ${STAMP}`

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let curatedId = 0
let otherRoleId = 0

/**
 * Seed a quicklinks placement scoped by one group include rule.
 *
 * @param links The quicklinks.
 * @param group The group the rule includes.
 * @param gridY The grid row.
 * @return The placement id.
 */
async function scopedQuicklinks(
	links: Array<{ label: string; url: string }>,
	group: string,
	gridY: number,
): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: {
			widgetId: 'quicklinks',
			gridX: 0,
			gridY,
			gridWidth: 6,
			gridHeight: 4,
			content: { links, showLabels: true },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()

	const rule = await admin.post(`${API}/widgets/${id}/rules`, {
		data: {
			ruleType: 'group',
			ruleConfig: { groups: [group] },
			isInclude: true,
		},
	})
	expect(rule.status(), `group rule: ${await rule.text()}`).toBeLessThan(300)
	return id
}

test.describe.configure({ mode: 'serial', timeout: 90_000 })

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	dashboard = await seedActiveDashboard(admin, `Mobile curated apps ${STAMP}`)
	// The viewer (admin) is in `admin`: this list is scoped to their role.
	curatedId = await scopedQuicklinks(CURATED, 'admin', 0)
	// A list scoped to a role the viewer does not hold: the control that
	// shows the rule, not mere placement, decides what the phone shows.
	otherRoleId = await scopedQuicklinks(
		[{ label: OTHER_ROLE_LINK, url: '/index.php/apps/files/' }],
		`e2e-no-such-role-${STAMP}`,
		4,
	)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard)
		await admin.dispose()
	}
})

// @e2e launchpad-mobile-remote-access::curated-app-list-comes-from-a-configured-widget-specter-source
test('REQ-MRA-006: on a 375 px phone the role-scoped quicklinks widget renders the curated app list', async ({
	page,
}) => {
	await page.setViewportSize({ width: 375, height: 812 })
	await page.goto('/index.php/apps/launchpad')

	const curated = page.locator(
		`[data-placement-id="${curatedId}"] .cn-quicklinks-widget`,
	)
	await expect(curated).toBeVisible({ timeout: 30_000 })
	const links = curated.locator('a.cn-quicklinks-widget__link')
	await expect(links).toHaveCount(CURATED.length)
	for (const [i, link] of CURATED.entries()) {
		await expect(links.nth(i)).toContainText(link.label)
		await expect(links.nth(i)).toHaveAttribute('href', link.url)
	}

	// The list fits the phone: the widget sits inside the
	// 375 px viewport width.
	const box = await curated.boundingBox()
	expect(box, 'the widget has a box').not.toBeNull()
	expect(box!.x).toBeGreaterThanOrEqual(0)
	expect(box!.x + box!.width).toBeLessThanOrEqual(375 + 1)

	// The role rule is what scopes it: the list for another role is absent.
	await expect(page.getByText(OTHER_ROLE_LINK)).toHaveCount(0)
	await expect(
		page.locator(`[data-placement-id="${otherRoleId}"] .cn-quicklinks-widget`),
	).toHaveCount(0)
})
