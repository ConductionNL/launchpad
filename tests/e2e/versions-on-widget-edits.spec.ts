/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Leaving edit mode keeps the state from before editing as a version
 * (REQ-VERS-010, REVIEW-LAUNCHPAD item 18).
 *
 * Ruben changed and saved 'My Dashboard' and the Versions tab said "No versions
 * saved yet": widget changes go through the widget endpoints, which never kept
 * a version. An edit session (edit mode's presence, from join to leave) now
 * keeps the dashboard as it was when editing began and, when editing ends and
 * something changed, stores that state as one version. Restoring it undoes the
 * session.
 *
 * The API scenarios drive the session the way edit mode does (presence join and
 * leave); the browser scenario enters edit mode and presses Done.
 *
 * Scenarios covered:
 *   @e2e versions-on-widget-edits::changing-a-widget-keeps-a-version
 *   @e2e versions-on-widget-edits::several-changes-in-one-session-keep-one-version
 *   @e2e versions-on-widget-edits::editing-without-changes-keeps-no-version
 *   @e2e versions-on-widget-edits::done-in-the-edit-bar-keeps-the-version
 *
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/specs/dashboard-versioning/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { quietFirstVisit } from './support/firstVisit.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = `E2E Edit versions ${Date.now()}`

interface Dash {
	id: number
	uuid: string
}

interface Placement {
	id: number
	widgetId: string
	customTitle: string | null
	content: { text?: string } | null
}

let admin: APIRequestContext
let originalAllowUserDash: unknown = null
const created: Array<{ api: APIRequestContext; id: number }> = []

async function createDashboard(api: APIRequestContext, name: string): Promise<Dash> {
	const res = await api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	const dash = body.dashboard ?? body
	const id = Number(dash.id)
	created.push({ api, id })
	await clearSeededWidgets(api, id)
	return { id, uuid: String(dash.uuid) }
}

async function addNotes(api: APIRequestContext, dashboardId: number, title: string, gridX = 0): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
			content: { text: `${title} text`, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	const titled = await api.put(`${API}/widgets/${id}`, { data: { customTitle: title } })
	expect(titled.status(), await titled.text()).toBe(200)
	return id
}

async function placements(api: APIRequestContext, dashboardId: number): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return ((body.data ?? body).placements ?? []) as Placement[]
}

async function versions(api: APIRequestContext, uuid: string): Promise<Array<{ versionNumber: number; createdBy: string }>> {
	const res = await api.get(`${API}/dashboards/${uuid}/versions`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).versions
}

async function beginEditing(api: APIRequestContext, uuid: string): Promise<void> {
	const res = await api.post(`${API}/dashboards/${uuid}/presence`)
	expect(res.status(), await res.text()).toBe(200)
}

async function endEditing(api: APIRequestContext, uuid: string): Promise<void> {
	const res = await api.delete(`${API}/dashboards/${uuid}/presence`)
	expect(res.status(), await res.text()).toBe(204)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	originalAllowUserDash = (await settings.json()).allowUserDashboards ?? null
	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBe(200)
})

test.afterAll(async () => {
	for (const { api, id } of created) {
		await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	if (originalAllowUserDash !== null) {
		await admin.put(`${API}/admin/settings`, { data: { allowUserDash: originalAllowUserDash } })
	}
	await admin.dispose()
})

test.describe('REQ-VERS-010 leaving edit mode keeps the state from before editing', () => {
	// @e2e versions-on-widget-edits::changing-a-widget-keeps-a-version
	test('renaming a widget in edit mode keeps one version, and restoring it brings the old title back', async () => {
		const dash = await createDashboard(admin, `${STAMP} rename`)
		const notes = await addNotes(admin, dash.id, 'Today')
		expect(await versions(admin, dash.uuid), 'setup keeps no version outside edit mode').toHaveLength(0)

		await beginEditing(admin, dash.uuid)
		const renamed = await admin.put(`${API}/widgets/${notes}`, { data: { customTitle: 'This week' } })
		expect(renamed.status(), await renamed.text()).toBe(200)
		await endEditing(admin, dash.uuid)

		const list = await versions(admin, dash.uuid)
		expect(list).toHaveLength(1)
		expect(list[0].createdBy).toBe(ADMIN_USER)

		const restored = await admin.post(`${API}/dashboards/${dash.uuid}/versions/${list[0].versionNumber}/restore`)
		expect(restored.status(), await restored.text()).toBe(200)
		const titles = (await placements(admin, dash.id)).map((p) => p.customTitle)
		expect(titles).toEqual(['Today'])
	})

	// @e2e versions-on-widget-edits::several-changes-in-one-session-keep-one-version
	test('adding, removing and configuring in one session keeps one version of the state before it', async () => {
		const dash = await createDashboard(admin, `${STAMP} several`)
		const first = await addNotes(admin, dash.id, 'One', 0)
		const second = await addNotes(admin, dash.id, 'Two', 2)
		await addNotes(admin, dash.id, 'Three', 4)

		await beginEditing(admin, dash.uuid)
		await addNotes(admin, dash.id, 'Four', 6)
		expect((await admin.delete(`${API}/widgets/${second}`)).status()).toBe(200)
		expect((await admin.put(`${API}/widgets/${first}`, { data: { customTitle: 'One, renamed' } })).status()).toBe(200)
		await beginEditing(admin, dash.uuid) // a presence renewal mid-session keeps the first state
		await endEditing(admin, dash.uuid)

		const list = await versions(admin, dash.uuid)
		expect(list).toHaveLength(1)
		const kept = await admin.get(`${API}/dashboards/${dash.uuid}/versions/${list[0].versionNumber}`)
		expect(kept.status(), await kept.text()).toBe(200)
		const body = await kept.json()
		const snapshot = typeof body.snapshot === 'string' ? JSON.parse(body.snapshot) : body.snapshot
		const titles = (snapshot.placements as Placement[]).map((p) => p.customTitle).sort()
		expect(titles, 'the version holds the three widgets as they were before the session').toEqual(['One', 'Three', 'Two'])
	})

	// @e2e versions-on-widget-edits::editing-without-changes-keeps-no-version
	test('entering and leaving edit mode without a change keeps no version', async () => {
		const dash = await createDashboard(admin, `${STAMP} unchanged`)
		await addNotes(admin, dash.id, 'Today')

		await beginEditing(admin, dash.uuid)
		await endEditing(admin, dash.uuid)

		expect(await versions(admin, dash.uuid)).toHaveLength(0)
	})

	// @e2e versions-on-widget-edits::done-in-the-edit-bar-keeps-the-version
	test('Done in the edit bar ends the session and the Versions list shows the version', async ({ browser }) => {
		test.setTimeout(180_000)
		let owner: Principal | null = null
		try {
			owner = await createPrincipal(admin, 'e2e-editver')
			await assertActingAs(owner.api, owner.username)
			await quietFirstVisit(owner.api)
			const made = await owner.api.post(`${API}/dashboard`, { data: { name: `${STAMP} done` } })
			expect(made.status(), await made.text()).toBe(201)
			const body = await made.json()
			const dash = { id: Number(body.dashboard.id), uuid: String(body.dashboard.uuid) }
			created.push({ api: owner.api, id: dash.id })
			expect((await owner.api.post(`${API}/dashboard/${dash.id}/activate`)).status()).toBeLessThan(300)
			expect((await owner.api.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })).status()).toBeLessThan(300)
			await clearSeededWidgets(owner.api, dash.id)
			const notes = await addNotes(owner.api, dash.id, 'Today')

			const { context, page } = await loginAs(browser, owner.username, owner.password)
			try {
				await page.goto('/index.php/apps/launchpad/')
				const joined = page.waitForResponse((r) => r.request().method() === 'POST'
					&& new URL(r.url()).pathname.endsWith(`/api/dashboards/${dash.uuid}/presence`))
				await enterEditMode(page)
				expect((await joined).status()).toBe(200)

				// The change itself, as the widget's settings save it.
				const renamed = await owner.api.put(`${API}/widgets/${notes}`, { data: { customTitle: 'This week' } })
				expect(renamed.status(), await renamed.text()).toBe(200)

				const left = page.waitForResponse((r) => r.request().method() === 'DELETE'
					&& new URL(r.url()).pathname.endsWith(`/api/dashboards/${dash.uuid}/presence`))
				await page.locator('[data-testid="launchpad-edit-bar-done"]').click()
				expect((await left).status()).toBe(204)
				await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
			} finally {
				await context.close()
			}

			const list = await versions(owner.api, dash.uuid)
			expect(list).toHaveLength(1)
			expect(list[0].createdBy).toBe(owner.username)
		} finally {
			if (owner) {
				await removePrincipal(admin, owner)
			}
		}
	})
})
