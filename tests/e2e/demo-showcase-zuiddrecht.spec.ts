/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The two Zuiddrecht showcases (REQ-DEMO-001), as a colleague meets them:
 *
 *   zuiddrecht-intranet    a group dashboard everyone may add widgets to,
 *                          and not edit or delete (permissionLevel add_only)
 *   zuiddrecht-management  register counts, offering no editing (view_only)
 *
 * The colleague is a throwaway account with no role, so what it may do is
 * what the dashboard's permission level gives it and nothing more. Both
 * showcases install as `default`-group dashboards everyone sees; afterAll
 * uninstalls them again so other specs resolve to what they had.
 *
 * The four stat widgets of Managementinformatie read the dossiq, pipelinq
 * and decidiq registers. The test pins that each one counts from its
 * register; the number itself needs those apps installed, so it is read
 * only where the register answers.
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminContext, API, createPrincipal, removePrincipal } from './support/principals.ts'

const SHOWCASES = `${API}/admin/demo-showcases`
const APP = '/index.php/apps/launchpad'

interface Placement {
	id: number
	widgetId: string
	content: Record<string, unknown> | null
}

interface Read {
	dashboard: Record<string, unknown>
	placements: Placement[]
	permissionLevel: string
}

/**
 * Install a showcase from a known state and answer its dashboard uuid.
 *
 * @param admin The administrator's context.
 * @param id The showcase id.
 * @return The installed dashboard's uuid.
 */
async function install(admin: APIRequestContext, id: string): Promise<string> {
	await admin.delete(`${SHOWCASES}/${id}`)
	const res = await admin.post(`${SHOWCASES}/${id}/install`)
	expect(res.status(), await res.text()).toBeLessThan(300)
	const uuid = String((await res.json()).installedDashboardUuid ?? '')
	expect(uuid).toMatch(/^[0-9a-f-]{36}$/)
	return uuid
}

/**
 * The numeric id of a dashboard the caller sees, by uuid.
 *
 * @param api The caller.
 * @param uuid The dashboard uuid.
 * @return Its id.
 */
async function idFor(api: APIRequestContext, uuid: string): Promise<number> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const found = ((body.items ?? body) as Array<{ id: number; uuid: string }>).find((d) => d.uuid === uuid)
	expect(found, `dashboard ${uuid} is in the colleague's list`).toBeTruthy()
	return Number(found!.id)
}

/**
 * Read a dashboard as the caller.
 *
 * @param api The caller.
 * @param id The dashboard id.
 * @return The dashboard, its placements and the caller's permission level.
 */
async function read(api: APIRequestContext, id: number): Promise<Read> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return await res.json()
}

test.describe('demo showcases — Zuiddrecht intranet and management information', () => {
	let admin: APIRequestContext
	let colleague: Principal
	let intranetUuid = ''
	let managementUuid = ''

	test.beforeAll(async () => {
		admin = await adminContext()
		intranetUuid = await install(admin, 'zuiddrecht-intranet')
		managementUuid = await install(admin, 'zuiddrecht-management')
		colleague = await createPrincipal(admin, 'zdcollega')
	})

	test.afterAll(async () => {
		if (admin === undefined) {
			return
		}
		await admin.delete(`${SHOWCASES}/zuiddrecht-intranet`)
		await admin.delete(`${SHOWCASES}/zuiddrecht-management`)
		await removePrincipal(admin, colleague ?? null)
		await admin.dispose()
	})

	// @e2e demo-data-showcases::the-zuiddrecht-intranet-lets-colleagues-add-widgets
	test('a colleague may add a widget to Intranet Zuiddrecht, and not edit or delete it', async () => {
		const id = await idFor(colleague.api, intranetUuid)
		const before = await read(colleague.api, id)
		expect(before.dashboard.name).toBe('Intranet Zuiddrecht')
		expect(before.dashboard.type).toBe('group_shared')
		expect(before.permissionLevel).toBe('add_only')

		const added = await colleague.api.post(`${API}/dashboard/${id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 40, gridWidth: 4, gridHeight: 2, content: { text: 'Taart in de kantine' } },
		})
		expect(added.status(), await added.text()).toBeLessThan(300)
		const placementId = Number((await added.json()).id)
		expect((await read(colleague.api, id)).placements.map((p) => p.id)).toContain(placementId)

		const renamed = await colleague.api.put(`${API}/dashboard/${id}`, { data: { name: 'Ons intranet' } })
		expect(renamed.status(), 'renaming is editing the dashboard').toBe(403)
		const deleted = await colleague.api.delete(`${API}/dashboard/${id}`)
		expect(deleted.status(), 'deleting the dashboard').toBe(403)

		const after = await read(colleague.api, id)
		expect(after.dashboard.name).toBe('Intranet Zuiddrecht')

		// Leave the showcase as it was installed.
		const removed = await admin.delete(`${API}/widgets/${placementId}`)
		expect(removed.status(), await removed.text()).toBeLessThan(300)
	})

	// @e2e demo-data-showcases::the-management-information-is-read-only
	test('Managementinformatie shows the register counts to a colleague and offers no editing', async ({ browser }) => {
		const id = await idFor(colleague.api, managementUuid)
		const dashboard = await read(colleague.api, id)
		expect(dashboard.dashboard.name).toBe('Managementinformatie')
		expect(dashboard.permissionLevel).toBe('view_only')

		const counts = dashboard.placements
			.filter((p) => p.widgetId === 'stat')
			.map((p) => {
				const source = (p.content?.source ?? {}) as Record<string, unknown>
				return `${p.content?.label}=${source.register}/${source.schema}:${source.metric}`
			})
			.sort()
		expect(counts).toEqual([
			'Besluiten=decidiq/decision:count',
			'Klantcontacten=pipelinq/ticket:count',
			'Lopende zaken=dossiq/case:count',
			'Vergaderingen=decidiq/meeting:count',
		])

		const added = await colleague.api.post(`${API}/dashboard/${id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 40, gridWidth: 4, gridHeight: 2, content: { text: 'x' } },
		})
		expect(added.status(), 'a view-only dashboard takes no widget').toBe(403)
		const stat = dashboard.placements.find((p) => p.widgetId === 'stat')!
		const restyled = await colleague.api.put(`${API}/widgets/${stat.id}`, { data: { content: { ...stat.content, label: 'x' } } })
		expect(restyled.status(), 'a view-only dashboard changes no widget').toBe(403)
		expect((await read(colleague.api, id)).placements.length).toBe(dashboard.placements.length)

		const { context, page } = await loginAs(browser, colleague.username, colleague.password)
		try {
			await page.goto(`${APP}/${String(dashboard.dashboard.slug)}`)
			for (const label of ['Lopende zaken', 'Klantcontacten', 'Besluiten', 'Vergaderingen']) {
				await expect(page.getByText(label, { exact: true }).first()).toBeVisible({ timeout: 30_000 })
			}
			await expect(page.getByTestId('launchpad-edit-bar')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})
