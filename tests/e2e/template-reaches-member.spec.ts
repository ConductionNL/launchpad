/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A member of a group an admin template targets SEES that template when they
 * open LaunchPad for the first time (admin-templates REQ-TMPL-019, REQ-TMPL-018).
 *
 * WHY THIS IS A BROWSER TEST. The feature had unit tests on every part and did
 * not work: the page decides what to show from one resolution chain
 * (`resolveActiveDashboard`), and templates were only known to the other
 * (`GET /api/dashboard`), which the page never reaches when the first says
 * "nothing" and never needs when the first says "the dashboard for everyone".
 * Only a real first visit joins the two. Found on 5 October 2026 by a live
 * check, with everything green.
 *
 * It drives the documented flow: an administrator adds the shipped template
 * and gives it to a group; a brand-new member opens LaunchPad. It does not
 * assume the instance is empty: the seeded dashboard for everyone may be
 * there, and the template must still win.
 *
 * @e2e openspec/specs/admin-templates/spec.md#req-tmpl-019
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request as pwRequest, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'

let api: APIRequestContext
let group = ''
let member: { username: string; password: string } | null = null
let outsider: { username: string; password: string } | null = null
let templateId: number | null = null
let allowBefore: boolean | null = null

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const settings = await api.get(`${API}/admin/settings`)
	expect(settings.ok(), 'admin settings must be readable').toBe(true)
	allowBefore = (await settings.json()).allowUserDashboards === true
	// A member can only be handed a copy, with its compulsory widgets, when
	// personal dashboards are on. The view-only case is the PHPUnit's.
	const put = await api.put(`${API}/admin/settings`, {
		data: { allowUserDashboards: true },
	})
	expect(put.ok(), 'personal dashboards must switch on').toBe(true)

	group = `e2e-werkdag-${Date.now()}`
	const madeGroup = await api.post('/ocs/v1.php/cloud/groups', {
		form: { groupid: group },
	})
	expect(madeGroup.ok(), 'the group must be created').toBe(true)

	member = await provisionThrowawayUser('e2e-werkdag-lid')
	outsider = await provisionThrowawayUser('e2e-werkdag-buiten')
	const joined = await api.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
		{ form: { groupid: group } },
	)
	expect(joined.ok(), 'the member must join the group').toBe(true)

	// The documented flow: add the shipped template (a fresh copy, so this
	// spec owns it), then give it to the group as the Templates page does.
	const installed = await api.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{
			data: { force: true },
		},
	)
	expect(installed.ok(), `install answered ${installed.status()}`).toBe(true)
	templateId = (await installed.json()).id
	const targeted = await api.put(`${API}/admin/templates/${templateId}`, {
		data: { targetGroups: [group] },
	})
	expect(targeted.ok(), 'the template must take the group').toBe(true)
})

test.afterAll(async () => {
	if (templateId !== null) {
		await api.delete(`${API}/admin/templates/${templateId}`)
	}
	for (const user of [member, outsider]) {
		if (user !== null) {
			await deprovisionUser(user.username)
		}
	}
	if (group !== '') {
		await api.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(group)}`)
	}
	if (allowBefore !== null) {
		await api.put(`${API}/admin/settings`, {
			data: { allowUserDashboards: allowBefore },
		})
	}
	await api.dispose()
})

// @e2e admin-templates::a-new-member-sees-the-template-although-a-dashboard-for-everyone-exists
test('a new member of the group sees the template on their first visit', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		member!.username,
		member!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')

		// The header widget of the template, and the page is not the empty state.
		await expect(
			page.getByText('Mijn werkdag', { exact: true }).first(),
		).toBeVisible({ timeout: 30_000 })
		await expect(page.getByText('No dashboards available')).toHaveCount(0)

		// What the server holds for this member: one personal copy made from
		// THIS template, with the template's compulsory widgets.
		const mine = await context.request.get(`${API}/dashboard`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		expect(mine.ok()).toBe(true)
		const body = await mine.json()
		expect(body.dashboard.type).toBe('user')
		expect(body.dashboard.userId).toBe(member!.username)
		expect(body.dashboard.basedOnTemplate).toBe(templateId)
		expect(body.permissionLevel).toBe('add_only')
		const compulsory = body.placements.filter(
			(p: { isCompulsory: number }) => p.isCompulsory === 1,
		)
		expect(compulsory.length).toBeGreaterThanOrEqual(2)
		expect(compulsory.map((p: { widgetId: string }) => p.widgetId)).toContain(
			'header',
		)
	} finally {
		await context.close()
	}
})

test('a second visit shows the same copy and makes no second one', async ({
	browser,
}) => {
	const { context, page } = await loginAs(
		browser,
		member!.username,
		member!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(
			page.getByText('Mijn werkdag', { exact: true }).first(),
		).toBeVisible({ timeout: 30_000 })

		const visible = await context.request.get(`${API}/dashboards/visible`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const items = (await visible.json()).items as Array<{
			basedOnTemplate: number | null
		}>
		expect(items.filter((d) => d.basedOnTemplate === templateId)).toHaveLength(1)
	} finally {
		await context.close()
	}
})

test('someone outside the group does not get the template', async ({ browser }) => {
	const { context, page } = await loginAs(
		browser,
		outsider!.username,
		outsider!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await page.waitForSelector(
			'.workspace-shell, .launchpad-floating-controls',
			{ timeout: 30_000 },
		)

		const visible = await context.request.get(`${API}/dashboards/visible`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const items = (await visible.json()).items as Array<{
			basedOnTemplate: number | null
		}>
		expect(items.filter((d) => d.basedOnTemplate === templateId)).toHaveLength(0)
	} finally {
		await context.close()
	}
})
