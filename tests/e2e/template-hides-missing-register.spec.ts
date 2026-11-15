/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A list of an app that is not on the instance is hidden for the employee,
 * not shown as an error (admin-templates REQ-TMPL-022). The e2e instance has
 * no pipelinq, so the shipped template's "Mijn tickets" list must not appear,
 * and neither must the list widget's error line.
 *
 * The spec stops, rather than passes by accident, when pipelinq IS installed:
 * then the list is rightly there and this proves nothing.
 *
 * @e2e openspec/specs/admin-templates/spec.md#req-tmpl-022
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
let templateId: number | null = null
let allowBefore: boolean | null = null

test.setTimeout(180_000)

test.beforeAll(async () => {
	api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const probe = await api.get(
		'/index.php/apps/openregister/api/objects/pipelinq/ticket?_limit=1',
	)
	test.skip(
		probe.status() !== 404,
		`pipelinq's register answered ${probe.status()}, so the hide rule has nothing to hide here`,
	)

	const settings = await api.get(`${API}/admin/settings`)
	allowBefore = (await settings.json()).allowUserDashboards === true
	await api.put(`${API}/admin/settings`, { data: { allowUserDashboards: true } })

	group = `e2e-hide-${Date.now()}`
	expect(
		(
			await api.post('/ocs/v1.php/cloud/groups', { form: { groupid: group } })
		).ok(),
	).toBe(true)
	member = await provisionThrowawayUser('e2e-hide-lid')
	await api.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
		{ form: { groupid: group } },
	)

	const installed = await api.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{ data: { force: true } },
	)
	expect(installed.ok(), `install answered ${installed.status()}`).toBe(true)
	templateId = (await installed.json()).id
	await api.put(`${API}/admin/templates/${templateId}`, {
		data: { targetGroups: [group] },
	})
})

test.afterAll(async () => {
	if (templateId !== null) {
		await api.delete(`${API}/admin/templates/${templateId}`)
	}
	if (member !== null) {
		await deprovisionUser(member.username)
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

// @e2e admin-templates::an-employee-on-an-instance-without-pipelinq-does-not-see-the-ticket-list
test('the ticket list is not shown and no error line appears', async ({
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
		// The dossiq lists are on the page (dossiq's register may or may not
		// be here; the heading is, either way, because they do not hide on
		// anything but a 404 and dossiq's is the e2e instance's own case).
		await expect(page.getByText('Mijn zaken', { exact: true })).toBeVisible()
		// Give the probe and the hide time to settle, then hold the page to it.
		await page.waitForTimeout(3_000)
		await expect(page.getByText('Mijn tickets', { exact: true })).toHaveCount(0)
		await expect(
			page.getByText('Wacht op uw stem', { exact: true }),
		).toHaveCount(0)
		await expect(page.getByText('Could not load these records')).toHaveCount(0)

		// The copy still holds the list: hidden on the page, not dropped.
		const mine = await context.request.get(`${API}/dashboard`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const body = await mine.json()
		expect(
			body.placements.filter(
				(p: { customTitle: string | null }) =>
					p.customTitle === 'Mijn tickets',
			),
		).toHaveLength(1)
	} finally {
		await context.close()
	}
})
