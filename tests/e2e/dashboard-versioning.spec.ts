/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The HTTP contract of dashboard version history (REQ-VERS-001..008), written
 * for e2e-coverage-programme task 2.3. The version-history dialog is driven in
 * dashboard-versioning-ui.spec.ts; this file covers what has no control of its
 * own: the snapshot a save takes, the four version endpoints, their refusals,
 * and retention.
 *
 * HOW A "STATE" IS MADE. Restoring replaces a dashboard's placements with the
 * ones a snapshot holds (`DashboardVersionService::applySnapshotPayload`). So
 * the states these tests tell apart are sets of text widgets, each carrying a
 * marker text: state k is the widgets `state-1` .. `state-k`. Widgets are added
 * through `POST /api/dashboard/{id}/widgets`, which takes no snapshot, so the
 * only versions that exist are the ones a test asks for.
 *
 * WHO OWNS WHAT. The data tests use a dashboard the administrator owns, so a
 * failure there is about versions and not about access. The refusal tests use
 * a throwaway owner (alice), a throwaway stranger (bob, neither owner nor
 * admin), and the admin, each over basic auth through `contextFor`: bob is
 * refused, the record is re-read to prove nothing changed, and the owner and
 * the admin are shown to succeed in the same test.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-versioning::automatic-snapshot-on-put
 *   @e2e dashboard-versioning::debouncing-rapid-edits
 *   @e2e dashboard-versioning::failed-put-does-not-create-snapshot
 *   @e2e dashboard-versioning::snapshot-captures-full-state
 *   @e2e dashboard-versioning::create-a-named-snapshot
 *   @e2e dashboard-versioning::explicit-snapshot-bypasses-debounce
 *   @e2e dashboard-versioning::empty-note-is-valid
 *   @e2e dashboard-versioning::only-owners-and-admins-can-create
 *   @e2e dashboard-versioning::list-versions-for-a-dashboard-with-history
 *   @e2e dashboard-versioning::list-versions-for-a-dashboard-with-no-snapshots
 *   @e2e dashboard-versioning::only-owners-and-admins-can-list
 *   @e2e dashboard-versioning::fetch-a-specific-version-snapshot
 *   @e2e dashboard-versioning::fetch-non-existent-version
 *   @e2e dashboard-versioning::only-owners-and-admins-can-fetch
 *   @e2e dashboard-versioning::restore-a-previous-version
 *   @e2e dashboard-versioning::restore-is-reversible
 *   @e2e dashboard-versioning::restore-to-the-current-version-is-a-no-op
 *   @e2e dashboard-versioning::restore-non-existent-version
 *   @e2e dashboard-versioning::only-owners-and-admins-can-restore
 *   @e2e dashboard-versioning::restore-updates-the-modified-timestamp
 *   @e2e dashboard-versioning::automatic-pruning-in-database-backend
 *   @e2e dashboard-versioning::pruning-does-not-affect-versionnumber-sequence
 *   @e2e dashboard-versioning::edge-case-exactly-50-versions
 *   @e2e dashboard-versioning::automatic-snapshots-do-not-emit-activity
 *   @e2e dashboard-versioning::database-backend-returns-versions-from-table
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = `E2E Versions ${Date.now()}`

interface Dash {
	id: number
	uuid: string
}

interface VersionRow {
	id: number
	dashboardUuid: string
	versionNumber: number
	createdBy: string
	createdAt: string
	note: string | null
	sizeBytes: number
}

interface Placement {
	id: number
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	content: { text?: string }
}

let admin: APIRequestContext
let originalAllowUserDash: unknown = null
const created: Array<{ api: APIRequestContext; id: number }> = []

/**
 * Create a dashboard as `api` and remember it for cleanup.
 *
 * @param api the owner's context.
 * @param name the dashboard name.
 * @return its id and uuid.
 */
async function createDashboard(api: APIRequestContext, name: string): Promise<Dash> {
	const res = await api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	const dash = body.dashboard ?? body
	const id = Number(dash.id)
	expect(id, JSON.stringify(body)).toBeGreaterThan(0)
	created.push({ api, id })
	await clearSeededWidgets(api, id)
	return { id, uuid: String(dash.uuid) }
}

/**
 * Add a text widget carrying `text`, at column `gridX`.
 *
 * @param api a context that may edit the dashboard.
 * @param dashboardId the dashboard.
 * @param text the marker text.
 * @param gridX the column.
 * @return the placement id.
 */
async function addTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
	gridX = 0,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

/**
 * Take an explicit snapshot, asserting the 201 the spec names.
 *
 * @param api a context allowed to snapshot.
 * @param uuid the dashboard uuid.
 * @param note the note, or undefined for none.
 * @return the new version row.
 */
async function snapshot(
	api: APIRequestContext,
	uuid: string,
	note?: string,
): Promise<VersionRow> {
	const res = await api.post(
		`${API}/dashboards/${uuid}/versions`,
		note === undefined ? {} : { data: { note } },
	)
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()).version
}

/**
 * Read the version list envelope, asserting 200.
 *
 * @param api the reader.
 * @param uuid the dashboard uuid.
 * @return the `{versions, modeSupported}` envelope.
 */
async function listVersions(
	api: APIRequestContext,
	uuid: string,
): Promise<{ versions: VersionRow[]; modeSupported: boolean }> {
	const res = await api.get(`${API}/dashboards/${uuid}/versions`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Fetch one version, asserting 200, with its snapshot decoded.
 *
 * @param api the reader.
 * @param uuid the dashboard uuid.
 * @param versionNumber the version.
 * @return the version row, the raw snapshot string and the decoded snapshot.
 */
async function fetchVersion(
	api: APIRequestContext,
	uuid: string,
	versionNumber: number,
): Promise<{
	version: VersionRow
	raw: string
	snapshot: { dashboard: Record<string, unknown>; placements: Placement[] }
}> {
	const res = await api.get(`${API}/dashboards/${uuid}/versions/${versionNumber}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(typeof body.snapshot, JSON.stringify(body)).toBe('string')
	return {
		version: body.version,
		raw: body.snapshot,
		snapshot: JSON.parse(body.snapshot),
	}
}

/**
 * Read a dashboard and its placements as its owner (or the admin).
 *
 * @param api the reader.
 * @param id the dashboard id.
 * @return the dashboard record and its placements.
 */
async function readDashboard(
	api: APIRequestContext,
	id: number,
): Promise<{ dashboard: Record<string, unknown>; placements: Placement[] }> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return { dashboard: body.dashboard, placements: body.placements }
}

/** The marker texts of a placement list, sorted. */
function markers(placements: Placement[]): string[] {
	return placements.map((p) => String(p.content?.text ?? '')).sort()
}

/** The version numbers of a list, in the order the server gave them. */
function numbers(versions: VersionRow[]): number[] {
	return versions.map((v) => v.versionNumber)
}

/** `state-1` .. `state-k`. */
function stateMarkers(k: number): string[] {
	return Array.from({ length: k }, (_, i) => `state-${i + 1}`).sort()
}

/**
 * A dashboard with five versions: version k holds widgets state-1..state-k,
 * and the current content is state 5, equal to version 5.
 *
 * @param api the owner (the admin).
 * @param label a name suffix.
 * @return the dashboard.
 */
async function seedFiveStates(api: APIRequestContext, label: string): Promise<Dash> {
	const dash = await createDashboard(api, `${STAMP} ${label}`)
	for (let k = 1; k <= 5; k++) {
		await addTextWidget(api, dash.id, `state-${k}`, k - 1)
		const v = await snapshot(api, dash.uuid, `v${k}`)
		expect(v.versionNumber).toBe(k)
	}
	return dash
}

/** Wait, in real time. */
async function sleep(ms: number): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, ms))
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	originalAllowUserDash = (await settings.json()).allowUserDashboards ?? null
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)
})

test.afterAll(async () => {
	for (const { api, id } of created.reverse()) {
		await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	if (originalAllowUserDash !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: originalAllowUserDash },
		})
	}
	await admin.dispose()
})

test.describe('REQ-VERS-001 snapshots taken by a save', () => {
	test('a PUT takes one snapshot of the dashboard as it was before the update', async () => {
		// @e2e dashboard-versioning::automatic-snapshot-on-put
		const dash = await createDashboard(admin, `${STAMP} auto before`)
		const placementId = await addTextWidget(admin, dash.id, 'auto-widget', 0)
		expect((await listVersions(admin, dash.uuid)).versions).toEqual([])

		const put = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: {
				name: `${STAMP} auto after`,
				placements: [
					{
						id: placementId,
						gridX: 3,
						gridY: 1,
						gridWidth: 2,
						gridHeight: 2,
					},
				],
			},
		})
		expect(put.status(), await put.text()).toBe(200)

		// THEN a version exists, created by the saver.
		const { versions } = await listVersions(admin, dash.uuid)
		expect(numbers(versions)).toEqual([1])
		expect(versions[0].createdBy).toBe(ADMIN_USER)

		// AND it holds the full widget tree and metadata as they were BEFORE
		// the update: the old name and the widget at its old position.
		const { snapshot: snap } = await fetchVersion(admin, dash.uuid, 1)
		expect(snap.dashboard.uuid).toBe(dash.uuid)
		expect(snap.dashboard.name).toBe(`${STAMP} auto before`)
		expect(snap.placements).toHaveLength(1)
		expect(snap.placements[0].content.text).toBe('auto-widget')
		expect(snap.placements[0].gridX).toBe(0)
		expect(snap.placements[0].gridY).toBe(0)
	})

	test('five PUTs in a burst take one snapshot; a PUT after 60 seconds takes another', async () => {
		// @e2e dashboard-versioning::debouncing-rapid-edits
		// The debounce window is 60 real seconds, so this test waits it out.
		test.setTimeout(180_000)
		const dash = await createDashboard(admin, `${STAMP} burst`)
		await addTextWidget(admin, dash.id, 'burst-widget')

		for (let i = 1; i <= 5; i++) {
			const put = await admin.put(`${API}/dashboard/${dash.id}`, {
				data: { name: `${STAMP} burst ${i}` },
			})
			expect(put.status(), await put.text()).toBe(200)
		}
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])

		await sleep(62_000)

		const late = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} burst late` },
		})
		expect(late.status(), await late.text()).toBe(200)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([
			2, 1,
		])
	})

	test('a PUT refused with 400 records no snapshot and changes nothing', async () => {
		// @e2e dashboard-versioning::failed-put-does-not-create-snapshot
		const dash = await createDashboard(admin, `${STAMP} failed put`)
		const placementId = await addTextWidget(admin, dash.id, 'failed-widget', 0)

		// `defaultLinkTarget` is validated after the name is patched in memory
		// and before anything is written, so the whole save is refused.
		const put = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: {
				name: `${STAMP} must not land`,
				defaultLinkTarget: 'not-a-target',
				placements: [
					{
						id: placementId,
						gridX: 5,
						gridY: 5,
						gridWidth: 2,
						gridHeight: 2,
					},
				],
			},
		})
		expect(put.status(), await put.text()).toBe(400)
		expect((await put.json()).error).toBe('invalid_argument')

		// THEN no snapshot.
		expect((await listVersions(admin, dash.uuid)).versions).toEqual([])

		// AND the content is unchanged.
		const after = await readDashboard(admin, dash.id)
		expect(after.dashboard.name).toBe(`${STAMP} failed put`)
		expect(after.placements).toHaveLength(1)
		expect(after.placements[0].gridX).toBe(0)
		expect(after.placements[0].gridY).toBe(0)

		// Control: the same save without the bad field does take a snapshot,
		// so the empty list above is about the failure, not a dead hook.
		const ok = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} failed put ok` },
		})
		expect(ok.status(), await ok.text()).toBe(200)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])
	})

	test('the snapshot holds all four widgets and the new metadata, and restores to that state', async () => {
		// @e2e dashboard-versioning::snapshot-captures-full-state
		const dash = await createDashboard(admin, `${STAMP} full state`)
		const ids: number[] = []
		for (let i = 0; i < 4; i++) {
			ids.push(await addTextWidget(admin, dash.id, `full-${i}`, i * 2))
		}

		const moved = ids.map((id, i) => ({
			id,
			gridX: 0,
			gridY: i * 2,
			gridWidth: 3,
			gridHeight: 2,
		}))
		const put = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: {
				name: `${STAMP} full state renamed`,
				description: 'described by the save',
				placements: moved,
			},
		})
		expect(put.status(), await put.text()).toBe(200)

		const { versions } = await listVersions(admin, dash.uuid)
		expect(numbers(versions)).toEqual([1])
		const { snapshot: snap } = await fetchVersion(admin, dash.uuid, 1)

		// THEN all four widgets and the new metadata, exactly as persisted.
		const persisted = await readDashboard(admin, dash.id)
		expect(snap.dashboard.name).toBe(`${STAMP} full state renamed`)
		expect(snap.dashboard.description).toBe('described by the save')
		expect(snap.placements).toHaveLength(4)
		const grid = (list: Placement[]): string[] =>
			list
				.map(
					(p) =>
						`${p.content.text}@${p.gridX},${p.gridY},${p.gridWidth},${p.gridHeight}`,
				)
				.sort()
		expect(grid(snap.placements)).toEqual(grid(persisted.placements))
		expect(grid(snap.placements)).toEqual([
			'full-0@0,0,3,2',
			'full-1@0,2,3,2',
			'full-2@0,4,3,2',
			'full-3@0,6,3,2',
		])

		// AND it is complete and restorable: move everything again, save a
		// later version so version 1 is not the newest, restore version 1.
		const again = ids.map((id) => ({
			id,
			gridX: 6,
			gridY: 0,
			gridWidth: 1,
			gridHeight: 1,
		}))
		const put2 = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: { placements: again },
		})
		expect(put2.status(), await put2.text()).toBe(200)
		await snapshot(admin, dash.uuid, 'moved away')

		const restore = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/1/restore`,
		)
		expect(restore.status(), await restore.text()).toBe(200)
		expect(grid((await readDashboard(admin, dash.id)).placements)).toEqual(
			grid(snap.placements),
		)
	})
})

test.describe('REQ-VERS-002 explicit snapshots', () => {
	test('a named snapshot answers 201 with its number and is listed at once', async () => {
		// @e2e dashboard-versioning::create-a-named-snapshot
		const dash = await createDashboard(admin, `${STAMP} named`)
		await addTextWidget(admin, dash.id, 'named-widget')

		const res = await admin.post(`${API}/dashboards/${dash.uuid}/versions`, {
			data: { note: 'Before major redesign' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const { version } = await res.json()
		expect(version.versionNumber).toBe(1)
		expect(version.note).toBe('Before major redesign')

		const { versions } = await listVersions(admin, dash.uuid)
		expect(versions).toHaveLength(1)
		expect(versions[0]).toMatchObject({
			versionNumber: 1,
			note: 'Before major redesign',
			createdBy: ADMIN_USER,
		})
	})

	test('an explicit snapshot inside the debounce window is still taken', async () => {
		// @e2e dashboard-versioning::explicit-snapshot-bypasses-debounce
		const dash = await createDashboard(admin, `${STAMP} bypass`)
		await addTextWidget(admin, dash.id, 'bypass-widget')

		const put = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} bypass saved` },
		})
		expect(put.status(), await put.text()).toBe(200)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])

		// A second save inside the window is debounced ...
		const put2 = await admin.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} bypass saved again` },
		})
		expect(put2.status(), await put2.text()).toBe(200)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])

		// ... but an explicit snapshot is not.
		const res = await admin.post(`${API}/dashboards/${dash.uuid}/versions`, {
			data: { note: 'inside the window' },
		})
		expect(res.status(), await res.text()).toBe(201)
		expect((await res.json()).version.versionNumber).toBe(2)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([
			2, 1,
		])
	})

	test('an empty or absent note is stored as null and answers 201', async () => {
		// @e2e dashboard-versioning::empty-note-is-valid
		const dash = await createDashboard(admin, `${STAMP} empty note`)

		const empty = await admin.post(`${API}/dashboards/${dash.uuid}/versions`, {
			data: { note: '' },
		})
		expect(empty.status(), await empty.text()).toBe(201)
		expect((await empty.json()).version.note).toBeNull()

		const absent = await admin.post(`${API}/dashboards/${dash.uuid}/versions`, {
			data: {},
		})
		expect(absent.status(), await absent.text()).toBe(201)
		expect((await absent.json()).version.note).toBeNull()

		const { versions } = await listVersions(admin, dash.uuid)
		expect(versions.map((v) => v.note)).toEqual([null, null])
	})
})

test.describe('REQ-VERS-002..005 refusals, probed as a signed-in stranger', () => {
	let alice: Principal | null = null
	let bob: Principal | null = null

	test.beforeAll(async () => {
		alice = await createPrincipal(admin, 'e2e-vers-alice')
		bob = await createPrincipal(admin, 'e2e-vers-bob')
		await assertActingAs(alice.api, alice.username)
		await assertActingAs(bob.api, bob.username)
	})

	test.afterAll(async () => {
		// alice's dashboards go with her account; drop them first anyway.
		for (const entry of created.filter((c) => c.api === alice?.api)) {
			await entry.api
				.delete(`${API}/dashboard/${entry.id}`)
				.catch(() => undefined)
		}
		created.splice(
			0,
			created.length,
			...created.filter((c) => c.api !== alice?.api),
		)
		await removePrincipal(admin, alice)
		await removePrincipal(admin, bob)
	})

	test('only the owner and an admin may create a snapshot', async () => {
		// @e2e dashboard-versioning::only-owners-and-admins-can-create
		const dash = await createDashboard(alice!.api, `${STAMP} alice create`)

		const refused = await bob!.api.post(
			`${API}/dashboards/${dash.uuid}/versions`,
			{
				data: { note: 'bob was here' },
			},
		)
		expect(refused.status(), await refused.text()).toBe(403)
		expect((await listVersions(admin, dash.uuid)).versions).toEqual([])

		// The admin may.
		await snapshot(admin, dash.uuid, 'by the admin')
		// The owner may.
		await snapshot(alice!.api, dash.uuid, 'by the owner')
		const { versions } = await listVersions(admin, dash.uuid)
		expect(versions.map((v) => v.note)).toEqual(['by the owner', 'by the admin'])
	})

	test('only the owner and an admin may list versions', async () => {
		// @e2e dashboard-versioning::only-owners-and-admins-can-list
		const dash = await createDashboard(alice!.api, `${STAMP} alice list`)
		await snapshot(admin, dash.uuid, 'listed note')

		const refused = await bob!.api.get(`${API}/dashboards/${dash.uuid}/versions`)
		expect(refused.status(), await refused.text()).toBe(403)
		const refusedBody = await refused.text()
		expect(refusedBody).not.toContain('versions')
		expect(refusedBody).not.toContain('listed note')

		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])
		expect(
			numbers((await listVersions(alice!.api, dash.uuid)).versions),
		).toEqual([1])
	})

	test('only the owner and an admin may fetch a snapshot', async () => {
		// @e2e dashboard-versioning::only-owners-and-admins-can-fetch
		const dash = await createDashboard(alice!.api, `${STAMP} alice fetch`)
		await addTextWidget(alice!.api, dash.id, 'secret-widget')
		for (let i = 1; i <= 3; i++) {
			await snapshot(admin, dash.uuid, `fetch ${i}`)
		}

		const refused = await bob!.api.get(
			`${API}/dashboards/${dash.uuid}/versions/3`,
		)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await refused.text()).not.toContain('secret-widget')

		expect(
			markers((await fetchVersion(admin, dash.uuid, 3)).snapshot.placements),
		).toEqual(['secret-widget'])
		expect(
			markers(
				(await fetchVersion(alice!.api, dash.uuid, 3)).snapshot.placements,
			),
		).toEqual(['secret-widget'])
	})

	test('only the owner and an admin may restore, and a refused restore changes nothing', async () => {
		// @e2e dashboard-versioning::only-owners-and-admins-can-restore
		const dash = await createDashboard(alice!.api, `${STAMP} alice restore`)
		for (let k = 1; k <= 3; k++) {
			await addTextWidget(alice!.api, dash.id, `state-${k}`, k - 1)
			await snapshot(admin, dash.uuid, `v${k}`)
		}
		await addTextWidget(alice!.api, dash.id, 'state-4', 3)
		const before = await readDashboard(alice!.api, dash.id)
		const idsBefore = before.placements.map((p) => p.id).sort()

		const refused = await bob!.api.post(
			`${API}/dashboards/${dash.uuid}/versions/3/restore`,
		)
		expect(refused.status(), await refused.text()).toBe(403)

		// Nothing moved: same widgets, same rows, no pre-restore version.
		const after = await readDashboard(alice!.api, dash.id)
		expect(markers(after.placements)).toEqual(stateMarkers(4))
		expect(after.placements.map((p) => p.id).sort()).toEqual(idsBefore)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([
			3, 2, 1,
		])

		// The admin may restore.
		const byAdmin = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/2/restore`,
		)
		expect(byAdmin.status(), await byAdmin.text()).toBe(200)
		expect(
			markers((await readDashboard(alice!.api, dash.id)).placements),
		).toEqual(stateMarkers(2))

		// The owner may restore.
		const byOwner = await alice!.api.post(
			`${API}/dashboards/${dash.uuid}/versions/3/restore`,
		)
		expect(byOwner.status(), await byOwner.text()).toBe(200)
		expect(
			markers((await readDashboard(alice!.api, dash.id)).placements),
		).toEqual(stateMarkers(3))
	})
})

test.describe('REQ-VERS-003 / REQ-VERS-008 listing', () => {
	test('five snapshots list newest first with their metadata, from the version table', async () => {
		// @e2e dashboard-versioning::list-versions-for-a-dashboard-with-history
		// @e2e dashboard-versioning::database-backend-returns-versions-from-table
		const dash = await seedFiveStates(admin, 'list five')

		const res = await admin.get(`${API}/dashboards/${dash.uuid}/versions`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(body.modeSupported).toBe(true)
		expect(body.versions).toHaveLength(5)
		expect(numbers(body.versions)).toEqual([5, 4, 3, 2, 1])

		for (const [i, v] of (body.versions as VersionRow[]).entries()) {
			expect(Object.keys(v)).toEqual(
				expect.arrayContaining([
					'versionNumber',
					'createdBy',
					'createdAt',
					'note',
					'sizeBytes',
				]),
			)
			expect(Number.isInteger(v.versionNumber)).toBe(true)
			expect(Number.isInteger(v.id)).toBe(true)
			expect(v.dashboardUuid).toBe(dash.uuid)
			expect(v.createdBy).toBe(ADMIN_USER)
			expect(v.createdAt).toMatch(/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/)
			expect(v.note).toBe(`v${5 - i}`)
			expect(v.sizeBytes).toBeGreaterThan(0)
			// The list is metadata only; the body is fetched per version.
			expect(v).not.toHaveProperty('snapshotJson')
		}
	})

	test('a dashboard with no snapshots answers 200 with an empty list, not 404', async () => {
		// @e2e dashboard-versioning::list-versions-for-a-dashboard-with-no-snapshots
		const dash = await createDashboard(admin, `${STAMP} no history`)
		const res = await admin.get(`${API}/dashboards/${dash.uuid}/versions`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(body.versions).toEqual([])
		expect(body.modeSupported).toBe(true)
	})
})

test.describe('REQ-VERS-004 fetching one version', () => {
	test('version 3 answers 200 with its snapshot, identical to the dashboard while it is the latest', async () => {
		// @e2e dashboard-versioning::fetch-a-specific-version-snapshot
		const dash = await createDashboard(admin, `${STAMP} fetch three`)
		for (let k = 1; k <= 3; k++) {
			await addTextWidget(admin, dash.id, `state-${k}`, k - 1)
			await snapshot(admin, dash.uuid, `v${k}`)
		}

		const fetched = await fetchVersion(admin, dash.uuid, 3)
		expect(fetched.version.versionNumber).toBe(3)
		// The body is the stored JSON, as stored: its size is what the list reports.
		const listed = (await listVersions(admin, dash.uuid)).versions.find(
			(v) => v.versionNumber === 3,
		)
		expect(Buffer.byteLength(fetched.raw, 'utf8')).toBe(listed?.sizeBytes)

		// Version 3 is the latest, so it equals the dashboard as it is now.
		const current = await readDashboard(admin, dash.id)
		const shape = (list: Placement[]): string[] =>
			list
				.map(
					(p) =>
						`${p.id}:${p.widgetId}:${p.content.text}@${p.gridX},${p.gridY},${p.gridWidth},${p.gridHeight}`,
				)
				.sort()
		expect(shape(fetched.snapshot.placements)).toEqual(shape(current.placements))
		expect(fetched.snapshot.dashboard.uuid).toBe(current.dashboard.uuid)
		expect(fetched.snapshot.dashboard.name).toBe(current.dashboard.name)
		expect(fetched.snapshot.dashboard.updatedAt).toBe(
			current.dashboard.updatedAt,
		)
	})

	test('an unknown version number answers 404 "Version not found"', async () => {
		// @e2e dashboard-versioning::fetch-non-existent-version
		const dash = await seedFiveStates(admin, 'fetch missing')
		const res = await admin.get(`${API}/dashboards/${dash.uuid}/versions/99`)
		expect(res.status(), await res.text()).toBe(404)
		expect((await res.json()).error).toBe('Version not found')
	})
})

test.describe('REQ-VERS-005 restoring', () => {
	test('restoring version 3 saves the current state as version 6 first, then applies version 3', async () => {
		// @e2e dashboard-versioning::restore-a-previous-version
		const dash = await seedFiveStates(admin, 'restore three')

		const res = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/3/restore`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.version.versionNumber).toBe(3)
		expect(markers(JSON.parse(body.snapshot).placements)).toEqual(
			stateMarkers(3),
		)

		// The dashboard now holds version 3's content.
		expect(markers((await readDashboard(admin, dash.id)).placements)).toEqual(
			stateMarkers(3),
		)

		// The pre-restore snapshot is the newest version, number 6, and holds
		// what version 5 held.
		const { versions } = await listVersions(admin, dash.uuid)
		expect(numbers(versions)).toEqual([6, 5, 4, 3, 2, 1])
		expect(versions[0].note).toBe('pre-restore')
		expect(
			markers((await fetchVersion(admin, dash.uuid, 6)).snapshot.placements),
		).toEqual(stateMarkers(5))
	})

	test('restoring the pre-restore version undoes the restore and is itself saved first', async () => {
		// @e2e dashboard-versioning::restore-is-reversible
		const dash = await seedFiveStates(admin, 'reversible')
		const first = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/3/restore`,
		)
		expect(first.status(), await first.text()).toBe(200)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)[0]).toBe(6)

		const back = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/6/restore`,
		)
		expect(back.status(), await back.text()).toBe(200)

		// THEN the content is version 5's again.
		expect(markers((await readDashboard(admin, dash.id)).placements)).toEqual(
			stateMarkers(5),
		)

		// AND version 7 holds the state before this reversal (version 3's).
		const { versions } = await listVersions(admin, dash.uuid)
		expect(numbers(versions)).toEqual([7, 6, 5, 4, 3, 2, 1])
		expect(
			markers((await fetchVersion(admin, dash.uuid, 7)).snapshot.placements),
		).toEqual(stateMarkers(3))
	})

	test('restoring the current version answers 200 and takes no snapshot', async () => {
		// @e2e dashboard-versioning::restore-to-the-current-version-is-a-no-op
		const dash = await seedFiveStates(admin, 'restore current')
		const before = await readDashboard(admin, dash.id)

		const res = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/5/restore`,
		)
		expect(res.status(), await res.text()).toBe(200)

		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([
			5, 4, 3, 2, 1,
		])
		const after = await readDashboard(admin, dash.id)
		// Same rows, not re-inserted copies: the content was not touched.
		expect(after.placements.map((p) => p.id).sort()).toEqual(
			before.placements.map((p) => p.id).sort(),
		)
		expect(markers(after.placements)).toEqual(stateMarkers(5))
	})

	test('restoring an unknown version answers 404 and modifies nothing', async () => {
		// @e2e dashboard-versioning::restore-non-existent-version
		const dash = await seedFiveStates(admin, 'restore missing')
		const before = await readDashboard(admin, dash.id)

		const res = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/99/restore`,
		)
		expect(res.status(), await res.text()).toBe(404)
		expect((await res.json()).error).toBe('Version not found')

		const after = await readDashboard(admin, dash.id)
		expect(after.placements.map((p) => p.id).sort()).toEqual(
			before.placements.map((p) => p.id).sort(),
		)
		expect(after.dashboard.updatedAt).toBe(before.dashboard.updatedAt)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([
			5, 4, 3, 2, 1,
		])
	})

	test('a restore stamps the dashboard updatedAt with the time of the restore', async () => {
		// @e2e dashboard-versioning::restore-updates-the-modified-timestamp
		const dash = await createDashboard(admin, `${STAMP} restore stamp`)
		await addTextWidget(admin, dash.id, 'state-1', 0)
		await snapshot(admin, dash.uuid, 'v1')
		await addTextWidget(admin, dash.id, 'state-2', 1)
		await snapshot(admin, dash.uuid, 'v2')
		const before = String(
			(await readDashboard(admin, dash.id)).dashboard.updatedAt,
		)

		// Timestamps have one-second resolution.
		await sleep(1_500)
		const res = await admin.post(
			`${API}/dashboards/${dash.uuid}/versions/1/restore`,
		)
		expect(res.status(), await res.text()).toBe(200)

		const after = String(
			(await readDashboard(admin, dash.id)).dashboard.updatedAt,
		)
		expect(after).not.toBe(before)
		expect(after > before).toBe(true)
		// "The current timestamp": no earlier than the pre-restore version the
		// same request stamped with the server's clock.
		const preRestore = (await listVersions(admin, dash.uuid)).versions[0]
		expect(preRestore.note).toBe('pre-restore')
		expect(
			after.replace('T', ' ').slice(0, 19)
				>= preRestore.createdAt.replace('T', ' ').slice(0, 19),
		).toBe(true)
	})
})

test.describe('REQ-VERS-006 retention', () => {
	test('past 50 versions the oldest are pruned and numbering stays monotonic', async () => {
		// @e2e dashboard-versioning::edge-case-exactly-50-versions
		// @e2e dashboard-versioning::automatic-pruning-in-database-backend
		// @e2e dashboard-versioning::pruning-does-not-affect-versionnumber-sequence
		test.setTimeout(300_000)
		const dash = await createDashboard(admin, `${STAMP} retention`)
		await addTextWidget(admin, dash.id, 'retention-widget')
		const range = (from: number, to: number): number[] =>
			Array.from({ length: from - to + 1 }, (_, i) => from - i)

		for (let i = 1; i <= 50; i++) {
			await snapshot(admin, dash.uuid)
		}
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual(
			range(50, 1),
		)

		// Exactly 50, one more: version 51 is created and version 1 pruned.
		expect((await snapshot(admin, dash.uuid)).versionNumber).toBe(51)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual(
			range(51, 2),
		)
		expect(
			(await admin.get(`${API}/dashboards/${dash.uuid}/versions/1`)).status(),
		).toBe(404)

		// On to 60: versions 1..10 are gone, 11..60 remain, none renumbered.
		for (let i = 52; i <= 60; i++) {
			expect((await snapshot(admin, dash.uuid)).versionNumber).toBe(i)
		}
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual(
			range(60, 11),
		)
		for (const gone of [1, 5, 10]) {
			expect(
				(
					await admin.get(
						`${API}/dashboards/${dash.uuid}/versions/${gone}`,
					)
				).status(),
			).toBe(404)
		}
		expect(
			(await fetchVersion(admin, dash.uuid, 11)).version.versionNumber,
		).toBe(11)

		// The next snapshot is 61, not 51, and the sequence from 12 is unbroken.
		expect((await snapshot(admin, dash.uuid)).versionNumber).toBe(61)
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual(
			range(61, 12),
		)
	})
})

test.describe('REQ-VERS-007 activity', () => {
	let carol: Principal | null = null

	test.afterAll(async () => {
		await removePrincipal(admin, carol)
	})

	test('an automatic snapshot adds nothing to the activity stream', async () => {
		// @e2e dashboard-versioning::automatic-snapshots-do-not-emit-activity
		// A fresh account, so its stream holds only what this test causes.
		carol = await createPrincipal(admin, 'e2e-vers-carol')
		await assertActingAs(carol.api, carol.username)
		const dash = await createDashboard(carol.api, `${STAMP} carol activity`)

		/** carol's LaunchPad activity entries; 304 is the empty stream. */
		const launchpadActivity = async (): Promise<
			Array<Record<string, unknown>>
		> => {
			const res = await carol!.api.get(
				'/ocs/v2.php/apps/activity/api/v2/activity/all?format=json&limit=200',
			)
			expect([200, 304], await res.text()).toContain(res.status())
			if (res.status() === 304) {
				return []
			}
			const data = (await res.json()).ocs?.data ?? []
			return (data as Array<Record<string, unknown>>).filter(
				(a) => a.app === 'launchpad',
			)
		}

		const before = await launchpadActivity()
		const put = await carol.api.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} carol activity saved` },
		})
		expect(put.status(), await put.text()).toBe(200)

		// The snapshot was taken ...
		expect(numbers((await listVersions(admin, dash.uuid)).versions)).toEqual([1])
		// ... and the stream did not grow, and holds no restore event.
		const after = await launchpadActivity()
		expect(after.length).toBe(before.length)
		expect(after.filter((a) => a.type === 'dashboard_restored')).toEqual([])
	})
})
