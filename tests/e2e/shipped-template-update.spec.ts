/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Updating an installed ready-made template in place (admin-templates
 * REQ-TMPL-020): the administrator's dry run writes nothing, the update
 * raises the recorded version and re-syncs the member's copy, and the member
 * still sees the template with its compulsory widgets afterwards.
 *
 * WHAT THIS CANNOT STAGE. "A newer version ships" is a file on the server.
 * A browser test cannot place one, so the version on record is lowered through
 * Nextcloud's app-config API instead: the shipped file is then "newer" while
 * its widgets are the installed ones. So the diff here is empty by
 * construction ("The widgets stay as they are."); the pairing of changed
 * widgets is ShippedTemplateUpdateServiceTest's. What this does prove is the
 * whole path on a real instance: listing flag, dry run, update, re-sync, and
 * the member's page afterwards.
 *
 * The administrator acts through the API; the member through the browser.
 *
 * @e2e openspec/specs/admin-templates/spec.md#req-tmpl-020
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
const VERSION_KEY =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/shipped_template_version_mijn-werkdag'

let api: APIRequestContext
let group = ''
let member: { username: string; password: string } | null = null
let templateId: number | null = null
let shippedVersion = 0
let allowBefore: boolean | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const settings = await api.get(`${API}/admin/settings`)
	expect(settings.ok(), 'admin settings must be readable').toBe(true)
	allowBefore = (await settings.json()).allowUserDashboards === true
	const put = await api.put(`${API}/admin/settings`, {
		data: { allowUserDashboards: true },
	})
	expect(put.ok(), 'personal dashboards must switch on').toBe(true)

	group = `e2e-update-${Date.now()}`
	const madeGroup = await api.post('/ocs/v1.php/cloud/groups', {
		form: { groupid: group },
	})
	expect(madeGroup.ok(), 'the group must be created').toBe(true)
	member = await provisionThrowawayUser('e2e-update-lid')
	const joined = await api.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
		{ form: { groupid: group } },
	)
	expect(joined.ok(), 'the member must join the group').toBe(true)

	// A fresh install this spec owns; it becomes the recorded one.
	const installed = await api.post(
		`${API}/admin/templates/shipped/mijn-werkdag/install`,
		{ data: { force: true } },
	)
	expect(installed.ok(), `install answered ${installed.status()}`).toBe(true)
	const body = await installed.json()
	templateId = body.id
	shippedVersion = body.version
	const targeted = await api.put(`${API}/admin/templates/${templateId}`, {
		data: { targetGroups: [group] },
	})
	expect(targeted.ok(), 'the template must take the group').toBe(true)
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

async function shippedRow() {
	const listing = await api.get(`${API}/admin/templates/shipped`)
	expect(listing.ok()).toBe(true)
	// The listing is the bare array (ResponseHelper::success wraps nothing).
	const rows = (await listing.json()) as Array<{
		id: string
		isInstalled: boolean
		installedVersion: number | null
		updateAvailable: boolean
	}>
	return rows.find((r) => r.id === 'mijn-werkdag')!
}

test('the member gets a copy and the listing offers no update yet', async ({
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
	} finally {
		await context.close()
	}

	const row = await shippedRow()
	expect(row.isInstalled).toBe(true)
	expect(row.installedVersion).toBe(shippedVersion)
	expect(row.updateAvailable).toBe(false)
})

// @e2e admin-templates::a-dry-run-writes-nothing
test('with an older version on record, a dry run reports and writes nothing', async () => {
	const lowered = await api.post(VERSION_KEY, {
		form: { value: String(shippedVersion - 1) },
	})
	expect(lowered.ok(), 'the recorded version must be lowered').toBe(true)
	expect((await shippedRow()).updateAvailable).toBe(true)

	const dry = await api.post(
		`${API}/admin/templates/shipped/mijn-werkdag/update`,
		{ data: { dryRun: true } },
	)
	expect(dry.ok(), `dry run answered ${dry.status()}`).toBe(true)
	const plan = await dry.json()
	expect(plan.dryRun).toBe(true)
	expect(plan.applied).toBe(false)
	expect(plan.upToDate).toBe(false)
	expect(plan.installedVersion).toBe(shippedVersion - 1)
	expect(plan.version).toBe(shippedVersion)
	expect(plan.added).toEqual([])
	expect(plan.removed).toEqual([])
	expect(plan.changed).toEqual([])
	expect(plan.unchanged).toBeGreaterThan(0)
	expect(plan.copies).toBe(1)

	const row = await shippedRow()
	expect(row.installedVersion).toBe(shippedVersion - 1)
	expect(row.updateAvailable).toBe(true)
})

// @e2e admin-templates::nothing-to-do
test('the update raises the version, re-syncs the copy, and the member still sees the template', async ({
	browser,
}) => {
	const update = await api.post(
		`${API}/admin/templates/shipped/mijn-werkdag/update`,
	)
	expect(update.ok(), `update answered ${update.status()}`).toBe(true)
	const result = await update.json()
	expect(result.applied).toBe(true)
	expect(result.version).toBe(shippedVersion)
	expect(result.resync).toEqual({ async: false, affectedCount: 0, totalCopies: 1 })

	const row = await shippedRow()
	expect(row.installedVersion).toBe(shippedVersion)
	expect(row.updateAvailable).toBe(false)

	// Up to date now: a second call changes nothing and says so.
	const again = await (
		await api.post(`${API}/admin/templates/shipped/mijn-werkdag/update`)
	).json()
	expect(again.upToDate).toBe(true)
	expect(again.applied).toBe(false)

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
		const mine = await context.request.get(`${API}/dashboard`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const body = await mine.json()
		expect(body.dashboard.basedOnTemplate).toBe(templateId)
		expect(
			body.placements.filter(
				(p: { isCompulsory: number }) => p.isCompulsory === 1,
			).length,
		).toBeGreaterThanOrEqual(2)
	} finally {
		await context.close()
	}
})
