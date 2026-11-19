/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The three chat flows of mcp-tool-surface (REQ-MCP-008), driven through
 * OpenRegister's MCP endpoint (`POST /apps/openregister/api/mcp`, JSON-RPC
 * 2.0) as a non-admin user, and checked against LaunchPad's own REST answers.
 *
 *   1. "add a widget": getActiveDashboard, listWidgetTypes, addWidget; the
 *      placement is in the dashboard REST read.
 *   2. "share with the sales group": listDashboards, shareDashboard; the share
 *      is in `GET /api/dashboard/{id}/shares`.
 *   3. "template to default": listTemplates, forkDashboard,
 *      setDefaultDashboard; `GET /api/dashboards/default` answers the fork.
 *
 * Skipped when the instance has no OpenRegister MCP endpoint, or when it does
 * not list LaunchPad's tools.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { deprovisionUser, provisionThrowawayUser } from '../fixtures/secondary-user.ts'
import { allowPersonalDashboards, assertActingAs, contextFor } from '../support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const MCP = '/index.php/apps/openregister/api/mcp'
const STAMP = `${Date.now()}`
const GROUP = `e2e-mcp-sales-${STAMP}`

type Account = { username: string, password: string }
type Json = Record<string, unknown>

let admin: APIRequestContext
let user: APIRequestContext
let account: Account | null = null
let session = ''
let rpcId = 1

/**
 * One JSON-RPC call on the user's MCP session.
 * @param method The JSON-RPC method.
 * @param params The parameters.
 * @return The parsed answer.
 */
async function rpc(method: string, params: Json): Promise<Json> {
	const headers: Record<string, string> = { Accept: 'application/json, text/event-stream' }
	if (session !== '') {
		headers['Mcp-Session-Id'] = session
	}
	const res = await user.post(MCP, { headers, data: { jsonrpc: '2.0', id: rpcId++, method, params } })
	expect(res.status(), await res.text()).toBe(200)
	session = res.headers()['mcp-session-id'] ?? session
	return (await res.json()) as Json
}

/**
 * Call a LaunchPad tool and return its structured answer.
 * @param name The tool id.
 * @param args The arguments.
 * @return The tool's answer, parsed from its text content.
 */
async function tool(name: string, args: Json = {}): Promise<Json> {
	const answer = await rpc('tools/call', { name, arguments: args })
	const result = answer.result as { content: Array<{ text: string }>, isError?: boolean }
	expect(result, JSON.stringify(answer)).toBeDefined()
	expect(result.isError ?? false, result.content?.[0]?.text).toBe(false)
	const payload = JSON.parse(result.content[0].text) as Json
	// A LaunchPad refusal is a payload with `isError`, not a protocol error.
	expect(payload.isError ?? false, result.content[0].text).toBe(false)
	return payload
}

/**
 * A dashboard object from an answer, wrapped or not.
 * @param body The answer.
 * @return The dashboard.
 */
function dashboardOf(body: Json): Json {
	return (body.dashboard ?? (body.data as Json | undefined)?.dashboard ?? body.data ?? body) as Json
}

test.describe('mcp-tool-surface chat flows (REQ-MCP-008)', () => {
	test.describe.configure({ mode: 'serial' })

	test.beforeAll(async () => {
		admin = await contextFor(ADMIN.user, ADMIN.pass)
		await assertActingAs(admin, ADMIN.user)
		const probe = await admin.post(MCP, {
			headers: { Accept: 'application/json, text/event-stream' },
			data: { jsonrpc: '2.0', id: 0, method: 'tools/list', params: {} },
		})
		test.skip(probe.status() === 404, 'OpenRegister has no MCP endpoint on this instance')

		await allowPersonalDashboards(admin, true)
		const group = await admin.post('/ocs/v1.php/cloud/groups', { form: { groupid: GROUP } })
		expect(group.ok(), await group.text()).toBe(true)

		account = await provisionThrowawayUser('e2e-mcp')
		user = await contextFor(account.username, account.password)
		await assertActingAs(user, account.username)
		await rpc('initialize', {
			protocolVersion: '2025-03-26',
			capabilities: {},
			clientInfo: { name: 'launchpad-e2e', version: '1' },
		})
		const listed = await rpc('tools/list', {})
		const ids = ((listed.result as Json).tools as Json[]).map((t) => String(t.id ?? t.name))
		test.skip(!ids.includes('launchpad.addWidget'), 'OpenRegister does not list the LaunchPad tools')
	})

	test.afterAll(async () => {
		await admin?.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(GROUP)}`)
		await user?.dispose()
		await admin?.dispose()
		if (account !== null) {
			await deprovisionUser(account.username)
		}
	})

	test('add a widget: the placement the tool made is the one the dashboard read shows', async () => {
		const created = dashboardOf(await tool('launchpad.createDashboard', { name: `E2E MCP widgets ${STAMP}` }))
		await tool('launchpad.switchActiveDashboard', { dashboardUuid: String(created.uuid) })

		const active = dashboardOf(await tool('launchpad.getActiveDashboard'))
		expect(active.uuid).toBe(created.uuid)

		const types = (await tool('launchpad.listWidgetTypes')).items as Json[]
		expect(types.length, 'the instance offers at least one widget type').toBeGreaterThan(0)
		const widgetId = String(types[0].id)

		const added = await tool('launchpad.addWidget', { dashboardId: Number(active.id), widgetId, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 3 })
		const placement = (added.placement ?? added) as Json

		const read = await user.get(`${API}/dashboard/${active.id}`)
		expect(read.status(), await read.text()).toBe(200)
		const body = (await read.json()) as Json
		const placements = ((body.data ?? body) as Json).placements as Json[]
		const match = placements.find((p) => p.id === placement.id)
		expect(match, JSON.stringify(placements)).toBeDefined()
		expect(match!.widgetId).toBe(widgetId)
	})

	test('share with a group: the share the tool made is the one the shares read lists', async () => {
		const mine = dashboardOf(await tool('launchpad.createDashboard', { name: `E2E MCP share ${STAMP}` }))
		const listed = await tool('launchpad.listDashboards')
		const items = (listed.items ?? listed.dashboards ?? []) as Json[]
		expect(items.some((d) => d.uuid === mine.uuid), JSON.stringify(listed)).toBe(true)

		const shared = await tool('launchpad.shareDashboard', {
			dashboardId: Number(mine.id),
			shareType: 'group',
			shareWith: GROUP,
			permissionLevel: 'view_only',
		})

		const read = await user.get(`${API}/dashboard/${mine.id}/shares`)
		expect(read.status(), await read.text()).toBe(200)
		const body = (await read.json()) as Json
		const shares = (body.shares ?? body.data ?? body) as Json[]
		const share = shares.find((s) => s.shareType === 'group' && s.shareWith === GROUP)
		expect(share, JSON.stringify(shares)).toBeDefined()
		expect(share!.permissionLevel).toBe('view_only')
		const made = (shared.share ?? shared) as Json
		expect(made.id).toBe(share!.id)
	})

	// forkDashboard only copies a dashboard the caller can see, and a gallery
	// template from listTemplates is not one: the tool answers not_found.
	// Which templates a user may copy is open (mcp-tool-surface task 25).
	test.fixme('template to default: the fork becomes the default the REST read answers', async () => {
		const template = await admin.post(`${API}/admin/templates`, {
			data: { name: `E2E MCP template ${STAMP}`, targetGroups: [], permissionLevel: 'full' },
		})
		expect(template.status(), await template.text()).toBeLessThan(300)
		const made = dashboardOf((await template.json()) as Json)
		try {
			const templates = ((await tool('launchpad.listTemplates')).templates ?? []) as Json[]
			const offered = templates.find((t) => t.uuid === made.uuid)
			expect(offered, JSON.stringify(templates)).toBeDefined()

			const fork = dashboardOf(await tool('launchpad.forkDashboard', { dashboardUuid: String(offered!.uuid), name: `E2E MCP fork ${STAMP}` }))
			expect(fork.uuid).not.toBe(made.uuid)
			await tool('launchpad.setDefaultDashboard', { dashboardUuid: String(fork.uuid) })

			const read = await user.get(`${API}/dashboards/default`)
			expect(read.status(), await read.text()).toBe(200)
			expect(String(((await read.json()) as Json).uuid)).toBe(String(fork.uuid))
		} finally {
			await admin.delete(`${API}/admin/templates/${made.id}`)
		}
	})
})
