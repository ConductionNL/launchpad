/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard hierarchy as an API contract: parents, slugs, computed paths
 * and breadcrumbs, the tree endpoint, path resolution, cycle and depth
 * guards, sibling ordering and the cascade-delete guard
 * (openspec/specs/dashboards/spec.md, REQ-DASH-023..030).
 *
 * These requirements ARE the HTTP contract of POST/PUT/DELETE /api/dashboard
 * and GET /api/dashboards/{tree,by-path}, so the tests drive the API. Every
 * account is a throwaway non-admin (an administrator sees every user's draft
 * dashboards in its visible set, REQ-DASH-036, which would blur the ownership
 * assertions). Root slugs share one namespace across all users
 * (DashboardService::freeRootSlug), so every root name carries this run's
 * stamp.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::create-a-child-dashboard
 *   @e2e dashboards::root-dashboard-has-null-parent
 *   @e2e dashboards::non-existent-parent-returns-400
 *   @e2e dashboards::changing-parent-moves-the-subtree
 *   @e2e dashboards::reading-a-dashboard-includes-parent-reference
 *   @e2e dashboards::null-parent-is-preserved-in-serialization
 *   @e2e dashboards::slug-auto-generation-from-name
 *   @e2e dashboards::slug-uniqueness-among-siblings
 *   @e2e dashboards::custom-slug-override
 *   @e2e dashboards::slug-validation-characters
 *   @e2e dashboards::reading-a-dashboard-includes-slug
 *   @e2e dashboards::slug-update-does-not-auto-regenerate-on-name-change
 *   @e2e dashboards::compute-path-for-root-dashboard
 *   @e2e dashboards::compute-path-for-nested-dashboard
 *   @e2e dashboards::path-updates-when-parent-changes
 *   @e2e dashboards::breadcrumbs-from-root-to-target
 *   @e2e dashboards::root-dashboard-has-single-item-breadcrumbs
 *   @e2e dashboards::breadcrumbs-accessible-via-api
 *   @e2e dashboards::tree-endpoint-returns-nested-structure
 *   @e2e dashboards::tree-endpoint-respects-user-ownership
 *   @e2e dashboards::tree-endpoint-includes-sort-order
 *   @e2e dashboards::resolve-path-to-dashboard
 *   @e2e dashboards::path-not-found-returns-404
 *   @e2e dashboards::user-cannot-access-other-users-dashboard-via-path
 *   @e2e dashboards::path-is-case-insensitive
 *   @e2e dashboards::trailing-slash-is-optional
 *   @e2e dashboards::cycle-detection-on-parent-update
 *   @e2e dashboards::self-parenting-is-rejected
 *   @e2e dashboards::max-depth-exceeded-on-create
 *   @e2e dashboards::max-depth-exceeded-on-parent-update
 *   @e2e dashboards::exactly-5-levels-is-allowed
 *   @e2e dashboards::default-sort-order-on-create
 *   @e2e dashboards::custom-sort-order-on-create
 *   @e2e dashboards::sort-order-respected-in-tree
 *   @e2e dashboards::tie-breaking-by-name
 *   @e2e dashboards::update-sort-order-via-put
 *   @e2e dashboards::delete-parent-without-cascade-returns-409
 *   @e2e dashboards::delete-parent-with-cascade-deletes-subtree
 *   @e2e dashboards::delete-childless-dashboard-has-no-guard
 *   @e2e dashboards::cascade-parameter-is-case-insensitive
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow, TreeNode } from './support/dashboardHierarchy.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	allowManyPersonalDashboards,
	byPath,
	createDashboard,
	postDashboard,
	putDashboard,
	readDashboard,
	readTree,
	removeTree,
	visibleRows,
} from './support/dashboardHierarchy.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const T = Date.now()
const MSG_PARENT = 'Parent dashboard not found'
const MSG_CYCLE = 'Setting this parent would create a cycle'
const MSG_DEPTH = 'Cannot exceed maximum tree depth of 5 levels'
const MSG_SLUG_TAKEN = 'Slug must be unique among siblings'

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let alice: Principal | null = null
let bob: Principal | null = null
const principals: Principal[] = []
const roots: Array<{ api: APIRequestContext; id: number }> = []

/**
 * Create a root dashboard for a principal, named with this run's stamp, and
 * remember it for cleanup.
 *
 * @param who the owner.
 * @param label the readable part of the name.
 * @param extra more body fields.
 * @return the dashboard.
 */
async function root(
	who: Principal,
	label: string,
	extra: Record<string, unknown> = {},
): Promise<DashboardRow> {
	const dash = await createDashboard(who.api, { name: `${label} ${T}`, ...extra })
	roots.push({ api: who.api, id: dash.id })
	return dash
}

/**
 * Create a child dashboard under a parent.
 *
 * @param who the owner.
 * @param parent the parent.
 * @param name the child's name.
 * @param extra more body fields.
 * @return the dashboard.
 */
async function child(
	who: Principal,
	parent: DashboardRow,
	name: string,
	extra: Record<string, unknown> = {},
): Promise<DashboardRow> {
	return createDashboard(who.api, { name, parentUuid: parent.uuid, ...extra })
}

/**
 * A fresh throwaway principal, removed in afterAll.
 *
 * @param prefix the account prefix.
 * @return the principal.
 */
async function fresh(prefix: string): Promise<Principal> {
	const principal = await createPrincipal(admin, prefix)
	principals.push(principal)
	await assertActingAs(principal.api, principal.username)
	return principal
}

/**
 * The uuids of the dashboards a principal owns, from its visible set.
 *
 * @param who the principal.
 * @return the owned uuids.
 */
async function ownedUuids(who: Principal): Promise<Set<string>> {
	const rows = await visibleRows(who.api)
	return new Set(
		rows.filter((row) => row.userId === who.username).map((row) => row.uuid),
	)
}

/**
 * Every uuid anywhere in a tree.
 *
 * @param nodes the nodes.
 * @return the uuids, depth first.
 */
function allUuids(nodes: TreeNode[]): string[] {
	return nodes.flatMap((node) => [node.uuid, ...allUuids(node.children)])
}

/**
 * The node of a uuid anywhere in a tree.
 *
 * @param nodes the nodes.
 * @param uuid the uuid.
 * @return the node, or undefined.
 */
function findNode(nodes: TreeNode[], uuid: string): TreeNode | undefined {
	for (const node of nodes) {
		if (node.uuid === uuid) {
			return node
		}
		const below = findNode(node.children, uuid)
		if (below) {
			return below
		}
	}
	return undefined
}

test.describe('dashboards — hierarchy, slugs, paths, tree and cascade (REQ-DASH-023..030)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		alice = await fresh('e2e-tree-alice')
		bob = await fresh('e2e-tree-bob')
	})

	test.afterAll(async () => {
		for (const { api, id } of roots.reverse()) {
			await removeTree(api, id)
		}
		for (const principal of principals) {
			await removePrincipal(admin, principal)
		}
		await restoreSettings?.()
		await admin?.dispose()
	})

	// ── REQ-DASH-023 ────────────────────────────────────────────────────

	// @e2e dashboards::create-a-child-dashboard
	test('a child dashboard is created under its parent, two levels deep', async () => {
		const marketing = await root(alice!, 'Marketing child')
		const created = await postDashboard(alice!.api, {
			name: 'Q1 Campaigns',
			parentUuid: marketing.uuid,
		})

		expect(created.status, JSON.stringify(created.body)).toBe(201)
		const q1 = created.dashboard!
		expect(q1.name).toBe('Q1 Campaigns')
		expect(q1.uuid).toBeTruthy()
		expect(q1.parentUuid).toBe(marketing.uuid)

		// Depth from root is 2: the trail is the root and the child.
		const read = await readDashboard(alice!.api, q1.id)
		expect(read.status).toBe(200)
		const crumbs = read.body.breadcrumbs as Array<{ uuid: string }>
		expect(crumbs.map((crumb) => crumb.uuid)).toEqual([marketing.uuid, q1.uuid])
	})

	// @e2e dashboards::root-dashboard-has-null-parent
	test('a dashboard created without a parent is a root', async () => {
		const marketing = await root(alice!, 'Marketing root')
		expect(marketing.parentUuid).toBeNull()

		const tree = await readTree(alice!.api)
		expect(tree.map((node) => node.uuid)).toContain(marketing.uuid)
	})

	// @e2e dashboards::non-existent-parent-returns-400
	test('a parent that does not exist is refused with 400 and nothing is created', async () => {
		const name = `Orphan child ${T}`
		const created = await postDashboard(alice!.api, {
			name,
			parentUuid: 'uuid-nonexistent',
		})

		expect(created.status, JSON.stringify(created.body)).toBe(400)
		expect(created.body.error).toBe('invalid_argument')
		expect(created.body.message).toBe(MSG_PARENT)
		expect((await visibleRows(alice!.api)).map((row) => row.name)).not.toContain(
			name,
		)
	})

	// @e2e dashboards::changing-parent-moves-the-subtree
	test('changing the parent moves the dashboard and its subtree', async () => {
		const marketing = await root(alice!, 'Marketing move')
		const finance = await root(alice!, 'Finance move')
		const q1 = await child(alice!, marketing, 'Q1 Campaigns')
		const kickoff = await child(alice!, q1, 'Kickoff')
		expect(
			(await byPath(alice!.api, `${marketing.slug}/q1-campaigns`)).dashboard
				?.path,
		).toBe(`/${marketing.slug}/q1-campaigns`)

		const moved = await putDashboard(alice!.api, q1.id, {
			parentUuid: finance.uuid,
		})
		expect(moved.status, JSON.stringify(moved.body)).toBe(200)
		expect((moved.body.dashboard as DashboardRow).parentUuid).toBe(finance.uuid)
		expect((await readDashboard(alice!.api, q1.id)).dashboard?.parentUuid).toBe(
			finance.uuid,
		)

		const atNew = await byPath(alice!.api, `${finance.slug}/q1-campaigns`)
		expect(atNew.status).toBe(200)
		expect(atNew.dashboard?.uuid).toBe(q1.uuid)
		expect(atNew.dashboard?.path).toBe(`/${finance.slug}/q1-campaigns`)
		expect(
			(await byPath(alice!.api, `${marketing.slug}/q1-campaigns`)).status,
		).toBe(404)

		// The subtree went with it.
		const grandchild = await byPath(
			alice!.api,
			`${finance.slug}/q1-campaigns/kickoff`,
		)
		expect(grandchild.dashboard?.uuid).toBe(kickoff.uuid)
		expect(grandchild.dashboard?.path).toBe(
			`/${finance.slug}/q1-campaigns/kickoff`,
		)
	})

	// @e2e dashboards::reading-a-dashboard-includes-parent-reference
	test('reading a child returns its parent reference', async () => {
		const marketing = await root(alice!, 'Marketing read')
		const q1 = await child(alice!, marketing, 'Q1 Campaigns')

		const read = await readDashboard(alice!.api, q1.id)
		expect(read.status).toBe(200)
		expect(read.dashboard?.parentUuid).toBe(marketing.uuid)
	})

	// @e2e dashboards::null-parent-is-preserved-in-serialization
	test('a root serialises parentUuid as an explicit null', async () => {
		const marketing = await root(alice!, 'Marketing null')

		const res = await alice!.api.get(`${API}/dashboard/${marketing.id}`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Object.hasOwn(body.dashboard, 'parentUuid')).toBe(true)
		expect(body.dashboard.parentUuid).toBeNull()
	})

	// ── REQ-DASH-024 ────────────────────────────────────────────────────

	// @e2e dashboards::slug-auto-generation-from-name
	test('the slug is generated from the name: lowercased, spaces to dashes, at most 128 characters', async () => {
		const q1 = await root(alice!, 'Q1 Campaigns')
		expect(q1.slug).toBe(`q1-campaigns-${T}`)
		expect((await readDashboard(alice!.api, q1.id)).dashboard?.slug).toBe(
			`q1-campaigns-${T}`,
		)

		// A very long name is cut to 128 characters (as a child, so the
		// truncated slug cannot collide with another run's root).
		const long = await child(alice!, q1, 'Long '.repeat(40))
		expect(long.slug).toHaveLength(128)
		expect(long.slug).toMatch(/^long-(long-)*lon/)
	})

	// @e2e dashboards::slug-uniqueness-among-siblings
	test('a second sibling with the same slug is refused with 400 and not created', async () => {
		const marketing = await root(alice!, 'Marketing siblings')
		const first = await child(alice!, marketing, 'Q1 Campaigns')
		expect(first.slug).toBe('q1-campaigns')

		const second = await postDashboard(alice!.api, {
			name: 'Q1 Campaigns',
			parentUuid: marketing.uuid,
		})
		expect(second.status, JSON.stringify(second.body)).toBe(400)
		expect(second.body.message).toBe(MSG_SLUG_TAKEN)

		const node = findNode(await readTree(alice!.api), marketing.uuid)
		expect(node?.children.map((kid) => kid.uuid)).toEqual([first.uuid])
	})

	// @e2e dashboards::custom-slug-override
	test('a supplied slug is used instead of one generated from the name', async () => {
		const custom = await root(alice!, 'Q1 Campaigns custom', {
			slug: `q1-custom-${T}`,
		})
		expect(custom.slug).toBe(`q1-custom-${T}`)
		expect((await readDashboard(alice!.api, custom.id)).dashboard?.slug).toBe(
			`q1-custom-${T}`,
		)
	})

	// @e2e dashboards::slug-validation-characters
	test('a slug with characters other than letters, digits, dash and underscore is refused with 400', async () => {
		const before = (await visibleRows(alice!.api)).length

		for (const bad of ['q1 campaigns!', 'q1.dot', 'q1/slash']) {
			const refused = await postDashboard(alice!.api, { slug: bad })
			expect(refused.status, `${bad}: ${JSON.stringify(refused.body)}`).toBe(
				400,
			)
			expect(refused.body.error).toBe('invalid_argument')
		}
		expect((await visibleRows(alice!.api)).length).toBe(before)

		// Alphanumerics, a dash and an underscore are accepted.
		const ok = await root(alice!, 'Slug grammar', { slug: `q1_ok-${T}` })
		expect(ok.slug).toBe(`q1_ok-${T}`)
	})

	// @e2e dashboards::reading-a-dashboard-includes-slug
	test('reading a dashboard returns its slug', async () => {
		const q1 = await root(alice!, 'Slug read', {
			slug: `q1-campaigns-read-${T}`,
		})
		const read = await readDashboard(alice!.api, q1.id)
		expect(read.status).toBe(200)
		expect(read.dashboard?.slug).toBe(`q1-campaigns-read-${T}`)
	})

	// @e2e dashboards::slug-update-does-not-auto-regenerate-on-name-change
	test('renaming a dashboard keeps its slug', async () => {
		const q1 = await root(alice!, 'Q1 Campaigns rename', { slug: `q1-${T}` })

		const renamed = await putDashboard(alice!.api, q1.id, {
			name: `Q2 Campaigns ${T}`,
		})
		expect(renamed.status, JSON.stringify(renamed.body)).toBe(200)

		const read = await readDashboard(alice!.api, q1.id)
		expect(read.dashboard?.name).toBe(`Q2 Campaigns ${T}`)
		expect(read.dashboard?.slug).toBe(`q1-${T}`)
		expect(read.dashboard?.slug).not.toBe(`q2-campaigns-${T}`)
	})

	// ── REQ-DASH-025 ────────────────────────────────────────────────────

	// @e2e dashboards::compute-path-for-root-dashboard
	test('the path of a root is its slug with a leading slash', async () => {
		const marketing = await root(alice!, 'Marketing path')
		const slug = String(marketing.slug)

		expect((await byPath(alice!.api, slug)).dashboard?.path).toBe(`/${slug}`)
		const computed = await alice!.api.get(
			`${API}/dashboards/${marketing.uuid}/path`,
		)
		expect(computed.status()).toBe(200)
		expect((await computed.json()).path).toBe(`/${slug}`)
	})

	// @e2e dashboards::compute-path-for-nested-dashboard
	test('the path of a nested dashboard joins the slug chain from the root', async () => {
		const marketing = await root(alice!, 'Marketing nested')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')

		const expected = `/${marketing.slug}/campaigns/q1`
		expect(
			(await byPath(alice!.api, `${marketing.slug}/campaigns/q1`)).dashboard
				?.path,
		).toBe(expected)
		const computed = await alice!.api.get(`${API}/dashboards/${q1.uuid}/path`)
		expect((await computed.json()).path).toBe(expected)
	})

	// @e2e dashboards::path-updates-when-parent-changes
	test('the path follows a change of parent on the next read', async () => {
		const marketing = await root(alice!, 'Marketing repath')
		const finance = await root(alice!, 'Finance repath')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')
		const pathOf = async () =>
			(
				await (
					await alice!.api.get(`${API}/dashboards/${q1.uuid}/path`)
				).json()
			).path
		expect(await pathOf()).toBe(`/${marketing.slug}/campaigns/q1`)

		const moved = await putDashboard(alice!.api, q1.id, {
			parentUuid: finance.uuid,
		})
		expect(moved.status, JSON.stringify(moved.body)).toBe(200)

		expect(await pathOf()).toBe(`/${finance.slug}/q1`)
		expect(
			(await byPath(alice!.api, `${finance.slug}/q1`)).dashboard?.path,
		).toBe(`/${finance.slug}/q1`)
	})

	// @e2e dashboards::breadcrumbs-from-root-to-target
	test('the breadcrumbs run from the root to the dashboard, with uuid, name and slug', async () => {
		const marketing = await root(alice!, 'Marketing crumbs')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')

		const resolved = await byPath(alice!.api, `${marketing.slug}/campaigns/q1`)
		expect(resolved.status).toBe(200)
		expect(resolved.dashboard?.breadcrumbs).toEqual([
			{ uuid: marketing.uuid, name: marketing.name, slug: marketing.slug },
			{ uuid: campaigns.uuid, name: 'Campaigns', slug: 'campaigns' },
			{ uuid: q1.uuid, name: 'Q1', slug: 'q1' },
		])
	})

	// @e2e dashboards::root-dashboard-has-single-item-breadcrumbs
	test('a root has a single breadcrumb: itself', async () => {
		const marketing = await root(alice!, 'Marketing single')

		const resolved = await byPath(alice!.api, String(marketing.slug))
		expect(resolved.dashboard?.breadcrumbs).toEqual([
			{ uuid: marketing.uuid, name: marketing.name, slug: marketing.slug },
		])
	})

	// @e2e dashboards::breadcrumbs-accessible-via-api
	test('the breadcrumbs come with the path lookup and with the dashboard read', async () => {
		const marketing = await root(alice!, 'Marketing api crumbs')
		const q1 = await child(alice!, marketing, 'Q1')

		const resolved = await byPath(alice!.api, `${marketing.slug}/q1`)
		expect(Array.isArray(resolved.dashboard?.breadcrumbs)).toBe(true)
		expect((resolved.dashboard?.breadcrumbs as unknown[]).length).toBe(2)

		const read = await readDashboard(alice!.api, q1.id)
		expect(read.body.breadcrumbs).toEqual([
			{
				uuid: marketing.uuid,
				name: marketing.name,
				slug: marketing.slug,
				hidden: false,
			},
			{ uuid: q1.uuid, name: 'Q1', slug: 'q1', hidden: false },
		])
	})

	// ── REQ-DASH-026 ────────────────────────────────────────────────────

	// @e2e dashboards::tree-endpoint-returns-nested-structure
	test('the tree endpoint nests children under their roots', async () => {
		const carol = await fresh('e2e-tree-nest')
		const marketing = await root(carol, 'Marketing nest')
		const campaigns = await child(carol, marketing, 'Campaigns')
		const finance = await root(carol, 'Finance nest')
		const budget = await child(carol, finance, 'Budget')

		const tree = await readTree(carol.api)
		const owned = await ownedUuids(carol)
		const mine = tree.filter((node) => owned.has(node.uuid))
		expect(mine.map((node) => node.uuid).sort()).toEqual(
			[marketing.uuid, finance.uuid].sort(),
		)

		const m = mine.find((node) => node.uuid === marketing.uuid)!
		expect(m).toMatchObject({
			uuid: marketing.uuid,
			name: marketing.name,
			slug: marketing.slug,
		})
		expect(m.children).toHaveLength(1)
		expect(m.children[0]).toMatchObject({
			uuid: campaigns.uuid,
			name: 'Campaigns',
			slug: 'campaigns',
			children: [],
		})

		const f = mine.find((node) => node.uuid === finance.uuid)!
		expect(f).toMatchObject({
			uuid: finance.uuid,
			name: finance.name,
			slug: finance.slug,
		})
		expect(f.children).toHaveLength(1)
		expect(f.children[0]).toMatchObject({
			uuid: budget.uuid,
			name: 'Budget',
			slug: 'budget',
			children: [],
		})

		// Every node of the tree carries the four fields.
		const check = (nodes: TreeNode[]): void => {
			for (const node of nodes) {
				expect(Object.keys(node)).toEqual(
					expect.arrayContaining(['uuid', 'name', 'slug', 'children']),
				)
				expect(Array.isArray(node.children)).toBe(true)
				check(node.children)
			}
		}
		check(tree)
	})

	// @e2e dashboards::tree-endpoint-respects-user-ownership
	test("the tree holds only the caller's own dashboards, never another user's", async () => {
		const ann = await fresh('e2e-tree-ann')
		const ben = await fresh('e2e-tree-ben')
		const annRoots = [
			await root(ann, 'Ann one'),
			await root(ann, 'Ann two'),
			await root(ann, 'Ann three'),
		]
		const benRoots = [await root(ben, 'Ben one'), await root(ben, 'Ben two')]

		const tree = await readTree(ann.api)
		const everything = allUuids(tree)
		for (const dash of annRoots) {
			expect(tree.map((node) => node.uuid)).toContain(dash.uuid)
		}
		for (const dash of benRoots) {
			expect(everything).not.toContain(dash.uuid)
		}

		// Of the personal dashboards, exactly Ann's three are roots; anything
		// else in the tree is a dashboard Ann may see (the instance's group
		// dashboards), never a personal one of somebody else.
		const visible = await visibleRows(ann.api)
		const visibleUuids = new Set(visible.map((row) => row.uuid))
		for (const uuid of everything) {
			expect(
				visibleUuids.has(uuid),
				`tree node ${uuid} is not visible to Ann`,
			).toBe(true)
		}
		const personal = visible.filter(
			(row) => row.source === 'user' || row.userId !== null,
		)
		expect(personal.every((row) => row.userId === ann.username)).toBe(true)
		const owned = await ownedUuids(ann)
		expect(tree.filter((node) => owned.has(node.uuid))).toHaveLength(3)
	})

	// @e2e dashboards::tree-endpoint-includes-sort-order
	test('tree siblings are sorted by sortOrder, ties broken by name', async () => {
		const dee = await fresh('e2e-tree-sort')
		const ten = await root(dee, 'Sort ten', { sortOrder: 10 })
		const five = await root(dee, 'Sort five', { sortOrder: 5 })
		const twenty = await root(dee, 'Sort twenty', { sortOrder: 20 })
		const tieZ = await root(dee, 'Zz tie', { sortOrder: 15 })
		const tieA = await root(dee, 'Aa tie', { sortOrder: 15 })

		const owned = new Set(
			[ten, five, twenty, tieZ, tieA].map((dash) => dash.uuid),
		)
		const order = (await readTree(dee.api)).filter((node) =>
			owned.has(node.uuid),
		)
		expect(order.map((node) => node.uuid)).toEqual([
			five.uuid,
			ten.uuid,
			tieA.uuid,
			tieZ.uuid,
			twenty.uuid,
		])
		expect(order.map((node) => node.sortOrder)).toEqual([5, 10, 15, 15, 20])
	})

	// ── REQ-DASH-027 ────────────────────────────────────────────────────

	// @e2e dashboards::resolve-path-to-dashboard
	test('a slug path resolves to the dashboard there, with its path and breadcrumbs', async () => {
		const marketing = await root(alice!, 'Marketing resolve')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')

		const resolved = await byPath(alice!.api, `${marketing.slug}/campaigns/q1`)
		expect(resolved.status).toBe(200)
		expect(resolved.dashboard?.uuid).toBe(q1.uuid)
		expect(resolved.dashboard?.name).toBe('Q1')
		expect(resolved.dashboard?.path).toBe(`/${marketing.slug}/campaigns/q1`)
		expect(
			(resolved.dashboard?.breadcrumbs as Array<{ uuid: string }>).map(
				(crumb) => crumb.uuid,
			),
		).toEqual([marketing.uuid, campaigns.uuid, q1.uuid])
	})

	// @e2e dashboards::path-not-found-returns-404
	test('a path with no dashboard answers 404 "Dashboard not found at path"', async () => {
		const marketing = await root(alice!, 'Marketing missing')
		await child(alice!, marketing, 'Campaigns')

		const missing = await byPath(alice!.api, `${marketing.slug}/campaigns/q2`)
		expect(missing.status).toBe(404)
		expect(missing.body.error).toBe('not_found')
		expect(missing.body.message).toBe('Dashboard not found at path')
	})

	// @e2e dashboards::user-cannot-access-other-users-dashboard-via-path
	test("a path never hands one user another user's dashboard", async () => {
		// Root slugs are one namespace for all users, so Bob holds the slug
		// first and Alice's same-named root is moved off it
		// (DashboardService::freeRootSlug). Alice asking for Bob's slug must
		// not get Bob's dashboard; her own resolves under her own slug.
		const bobs = await root(bob!, 'Shared slug')
		const alices = await root(alice!, 'Shared slug')
		expect(alices.slug).not.toBe(bobs.slug)

		const asAlice = await byPath(alice!.api, String(bobs.slug))
		expect(asAlice.status).toBe(404)
		expect(asAlice.body.message).toBe('Dashboard not found at path')
		expect(JSON.stringify(asAlice.body)).not.toContain(bobs.uuid)

		const own = await byPath(alice!.api, String(alices.slug))
		expect(own.status).toBe(200)
		expect(own.dashboard?.uuid).toBe(alices.uuid)

		// Control: the same path does resolve for its owner.
		const asBob = await byPath(bob!.api, String(bobs.slug))
		expect(asBob.status).toBe(200)
		expect(asBob.dashboard?.uuid).toBe(bobs.uuid)
	})

	// @e2e dashboards::path-is-case-insensitive
	test('a path resolves whatever its letter case', async () => {
		const marketing = await root(alice!, 'Marketing case')

		const upper = await byPath(alice!.api, String(marketing.slug).toUpperCase())
		expect(upper.status).toBe(200)
		expect(upper.dashboard?.uuid).toBe(marketing.uuid)
	})

	// @e2e dashboards::trailing-slash-is-optional
	test('a trailing slash on the path is ignored', async () => {
		const marketing = await root(alice!, 'Marketing slash')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')

		const withSlash = await byPath(alice!.api, `${marketing.slug}/campaigns/q1/`)
		expect(withSlash.status).toBe(200)
		expect(withSlash.dashboard?.uuid).toBe(q1.uuid)
	})

	// ── REQ-DASH-028 ────────────────────────────────────────────────────

	// @e2e dashboards::cycle-detection-on-parent-update
	test('making a dashboard the child of its own descendant is refused with 400', async () => {
		const a = await root(alice!, 'Cycle A')
		const b = await child(alice!, a, 'B')
		const c = await child(alice!, b, 'C')

		const refused = await putDashboard(alice!.api, a.id, { parentUuid: c.uuid })
		expect(refused.status, JSON.stringify(refused.body)).toBe(400)
		expect(refused.body.message).toBe(MSG_CYCLE)
		expect(
			(await readDashboard(alice!.api, a.id)).dashboard?.parentUuid,
		).toBeNull()
	})

	// @e2e dashboards::self-parenting-is-rejected
	test('a dashboard cannot be its own parent', async () => {
		const marketing = await root(alice!, 'Self parent')

		const refused = await putDashboard(alice!.api, marketing.id, {
			parentUuid: marketing.uuid,
		})
		expect(refused.status, JSON.stringify(refused.body)).toBe(400)
		expect(refused.body.message).toBe(MSG_CYCLE)
		expect(
			(await readDashboard(alice!.api, marketing.id)).dashboard?.parentUuid,
		).toBeNull()
	})

	// @e2e dashboards::max-depth-exceeded-on-create
	test('a sixth level is refused on create and nothing is made', async () => {
		const a = await root(alice!, 'Depth create A')
		const b = await child(alice!, a, 'B')
		const c = await child(alice!, b, 'C')
		const d = await child(alice!, c, 'D')
		const e = await child(alice!, d, 'E')

		const refused = await postDashboard(alice!.api, {
			name: 'F',
			parentUuid: e.uuid,
		})
		expect(refused.status, JSON.stringify(refused.body)).toBe(400)
		expect(refused.body.message).toBe(MSG_DEPTH)
		expect(findNode(await readTree(alice!.api), e.uuid)?.children).toEqual([])
		expect((await byPath(alice!.api, `${a.slug}/b/c/d/e/f`)).status).toBe(404)
	})

	// @e2e dashboards::max-depth-exceeded-on-parent-update
	test('nesting a subtree so the tree passes five levels is refused and the parent is kept', async () => {
		const a = await root(alice!, 'Depth move A')
		const b = await child(alice!, a, 'B')
		const c = await child(alice!, b, 'C')
		const d = await child(alice!, c, 'D')
		const x = await root(alice!, 'Depth move X')
		const y = await child(alice!, x, 'Y')
		await child(alice!, y, 'Z')

		const refused = await putDashboard(alice!.api, x.id, { parentUuid: d.uuid })
		expect(refused.status, JSON.stringify(refused.body)).toBe(400)
		expect(refused.body.message).toBe(MSG_DEPTH)
		expect(
			(await readDashboard(alice!.api, x.id)).dashboard?.parentUuid,
		).toBeNull()
	})

	// @e2e dashboards::exactly-5-levels-is-allowed
	test('five levels are allowed, and a new root beside them too', async () => {
		const build = async (label: string) => {
			const a = await root(alice!, label)
			let parent = a
			for (const name of ['B', 'C', 'D', 'E']) {
				parent = await child(alice!, parent, name)
			}
			return { a, e: parent }
		}

		const first = await build('Five A')
		expect(
			(await byPath(alice!.api, `${first.a.slug}/b/c/d/e`)).dashboard?.uuid,
		).toBe(first.e.uuid)

		const created = await postDashboard(alice!.api, { name: `NewRoot ${T}` })
		expect(created.status, JSON.stringify(created.body)).toBe(201)
		roots.push({ api: alice!.api, id: created.dashboard!.id })
		expect(created.dashboard?.parentUuid).toBeNull()

		// A second independent five-level tree coexists.
		const second = await build('Five B')
		expect(
			(await byPath(alice!.api, `${second.a.slug}/b/c/d/e`)).dashboard?.uuid,
		).toBe(second.e.uuid)
	})

	// ── REQ-DASH-029 ────────────────────────────────────────────────────

	// @e2e dashboards::default-sort-order-on-create
	test('sortOrder defaults to 0', async () => {
		const marketing = await root(alice!, 'Sort default')
		expect(marketing.sortOrder).toBe(0)
		expect(
			(await readDashboard(alice!.api, marketing.id)).dashboard?.sortOrder,
		).toBe(0)
	})

	// @e2e dashboards::custom-sort-order-on-create
	test('a supplied sortOrder is stored', async () => {
		const marketing = await root(alice!, 'Sort custom', { sortOrder: 100 })
		expect(marketing.sortOrder).toBe(100)
		expect(
			(await readDashboard(alice!.api, marketing.id)).dashboard?.sortOrder,
		).toBe(100)
	})

	// @e2e dashboards::sort-order-respected-in-tree
	test('roots come back from the tree in sortOrder', async () => {
		const eve = await fresh('e2e-tree-order')
		const twenty = await root(eve, 'Order twenty', { sortOrder: 20 })
		const five = await root(eve, 'Order five', { sortOrder: 5 })
		const fifteen = await root(eve, 'Order fifteen', { sortOrder: 15 })

		const owned = new Set([twenty.uuid, five.uuid, fifteen.uuid])
		const order = (await readTree(eve.api)).filter((node) =>
			owned.has(node.uuid),
		)
		expect(order.map((node) => node.sortOrder)).toEqual([5, 15, 20])
		expect(order.map((node) => node.uuid)).toEqual([
			five.uuid,
			fifteen.uuid,
			twenty.uuid,
		])
	})

	// @e2e dashboards::tie-breaking-by-name
	test('siblings with the same sortOrder are ordered by name', async () => {
		const parent = await root(alice!, 'Tie parent')
		const zebra = await child(alice!, parent, 'Zebra')
		const alpha = await child(alice!, parent, 'Alice')

		const node = findNode(await readTree(alice!.api), parent.uuid)
		expect(node?.children.map((kid) => kid.name)).toEqual(['Alice', 'Zebra'])
		expect(node?.children.map((kid) => kid.uuid)).toEqual([
			alpha.uuid,
			zebra.uuid,
		])
	})

	// @e2e dashboards::update-sort-order-via-put
	test('PUT changes sortOrder and the tree follows', async () => {
		const parent = await root(alice!, 'Resort parent')
		const ten = await child(alice!, parent, 'Ten', { sortOrder: 10 })
		const thirty = await child(alice!, parent, 'Thirty', { sortOrder: 30 })
		expect(
			findNode(await readTree(alice!.api), parent.uuid)?.children.map(
				(kid) => kid.uuid,
			),
		).toEqual([ten.uuid, thirty.uuid])

		const updated = await putDashboard(alice!.api, ten.id, { sortOrder: 50 })
		expect(updated.status, JSON.stringify(updated.body)).toBe(200)
		expect((updated.body.dashboard as DashboardRow).sortOrder).toBe(50)
		expect((await readDashboard(alice!.api, ten.id)).dashboard?.sortOrder).toBe(
			50,
		)

		const children =
			findNode(await readTree(alice!.api), parent.uuid)?.children ?? []
		expect(children.map((kid) => kid.uuid)).toEqual([thirty.uuid, ten.uuid])
		expect(children.map((kid) => kid.sortOrder)).toEqual([30, 50])
	})

	// ── REQ-DASH-030 ────────────────────────────────────────────────────

	// @e2e dashboards::delete-parent-without-cascade-returns-409
	test('deleting a parent without cascade answers 409 with the child count and deletes nothing', async () => {
		const marketing = await root(alice!, 'Guard parent')
		await child(alice!, marketing, 'One')
		await child(alice!, marketing, 'Two')
		await child(alice!, marketing, 'Three')

		const res = await alice!.api.delete(`${API}/dashboard/${marketing.id}`)
		expect(res.status()).toBe(409)
		const body = await res.json()
		expect(body.error).toBe('dashboard_has_children')
		expect(body.message).toBe(
			'Dashboard has 3 children. Use ?cascade=true to delete the subtree.',
		)
		expect(body.childCount).toBe(3)

		expect((await readDashboard(alice!.api, marketing.id)).status).toBe(200)
		expect(
			findNode(await readTree(alice!.api), marketing.uuid)?.children,
		).toHaveLength(3)
	})

	// @e2e dashboards::delete-parent-with-cascade-deletes-subtree
	test('deleting with ?cascade=true removes the subtree and its placements', async () => {
		const marketing = await root(alice!, 'Cascade parent')
		const campaigns = await child(alice!, marketing, 'Campaigns')
		const q1 = await child(alice!, campaigns, 'Q1')
		const widget = await alice!.api.post(`${API}/dashboard/${q1.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: 'cascade' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		const placementId = Number((await widget.json()).id)
		const styleBefore = await alice!.api.put(`${API}/widgets/${placementId}`, {
			data: { gridX: 0 },
		})
		expect(styleBefore.status(), await styleBefore.text()).toBe(200)

		const res = await alice!.api.delete(
			`${API}/dashboard/${marketing.id}?cascade=true`,
		)
		expect(res.status()).toBe(200)
		expect((await res.json()).status).toBe('ok')

		for (const dash of [marketing, campaigns, q1]) {
			expect(
				(await readDashboard(alice!.api, dash.id)).status,
				dash.name,
			).toBe(404)
		}
		// The placement went with its dashboard: nobody may change it any more.
		const styleAfter = await alice!.api.put(`${API}/widgets/${placementId}`, {
			data: { gridX: 0 },
		})
		expect(styleAfter.status()).toBe(403)
	})

	// @e2e dashboards::delete-childless-dashboard-has-no-guard
	test('a dashboard without children is deleted with or without cascade', async () => {
		const plain = await root(alice!, 'Childless plain')
		const flagged = await root(alice!, 'Childless flagged')

		const one = await alice!.api.delete(`${API}/dashboard/${plain.id}`)
		expect(one.status()).toBe(200)
		const two = await alice!.api.delete(
			`${API}/dashboard/${flagged.id}?cascade=true`,
		)
		expect(two.status()).toBe(200)

		expect((await readDashboard(alice!.api, plain.id)).status).toBe(404)
		expect((await readDashboard(alice!.api, flagged.id)).status).toBe(404)
	})

	// @e2e dashboards::cascade-parameter-is-case-insensitive
	test('?cascade=TRUE and ?cascade=Cascade both delete the subtree', async () => {
		for (const flag of ['TRUE', 'Cascade']) {
			const parent = await root(alice!, `Case ${flag}`)
			const kid = await child(alice!, parent, 'Kid')

			const res = await alice!.api.delete(
				`${API}/dashboard/${parent.id}?cascade=${flag}`,
			)
			expect(res.status(), flag).toBe(200)
			expect((await readDashboard(alice!.api, parent.id)).status, flag).toBe(
				404,
			)
			expect((await readDashboard(alice!.api, kid.id)).status, flag).toBe(404)
		}
	})
})
