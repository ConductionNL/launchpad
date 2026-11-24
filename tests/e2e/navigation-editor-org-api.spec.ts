/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the org navigation tree's HTTP contract:
 * storage, group filtering, write validation, the position default and URL
 * scheme validation (openspec/specs/navigation-editor-org, REQ-ONAV-001..004
 * and REQ-ONAV-011), written for the e2e-coverage-programme.
 *
 * These scenarios ARE an API contract (status codes, messages, filtered
 * bodies), so they are driven through `/api/admin/org-navigation` directly.
 * Every write is re-read through the GET, which reads the per-language file
 * `OrgNavigationService::getTree` decodes. Refusals are probed by an ordinary
 * signed-in account, re-read as the admin, and paired with the admin's
 * success in the same test.
 *
 * The trees, the position and the action matrix are recorded in beforeAll and
 * put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::position-defaults-to-hidden
 *   @e2e navigation-editor-org::tree-persists-to-file
 *   @e2e navigation-editor-org::tree-respects-max-depth-3
 *   @e2e navigation-editor-org::label-is-required-per-node
 *   @e2e navigation-editor-org::all-users-tree-is-visible-to-every-user
 *   @e2e navigation-editor-org::admin-only-subtree-filtered-for-non-admin
 *   @e2e navigation-editor-org::user-in-one-of-multiple-groups-sees-node
 *   @e2e navigation-editor-org::hidden-parent-hides-children
 *   @e2e navigation-editor-org::admin-saves-valid-tree
 *   @e2e navigation-editor-org::depth-exceeded-returns-400
 *   @e2e navigation-editor-org::duplicate-node-ids-return-400
 *   @e2e navigation-editor-org::non-admin-cannot-write
 *   @e2e navigation-editor-org::javascript-url-is-rejected
 *   @e2e navigation-editor-org::javascript-url-rejected
 *   @e2e navigation-editor-org::data-url-rejected
 *   @e2e navigation-editor-org::https-url-allowed
 *   @e2e navigation-editor-org::relative-path-allowed
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { NavNode, Snapshot } from './support/orgNavigation.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	adminTree,
	depthOf,
	grantReadsToEveryone,
	idsOf,
	navNode,
	putTree,
	readPosition,
	readTree,
	restore,
	seedTree,
	snapshot,
} from './support/orgNavigation.ts'
import {
	addToGroup,
	adminContext,
	assertActingAs,
	assignRole,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
	removeRole,
} from './support/principals.ts'

const RUN = Date.now()

let admin: APIRequestContext
let snap: Snapshot | null = null
let firstPosition: { status: number; body: any } | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	// Read before anything in this suite writes the position: this is the
	// value a fresh installation answers with.
	firstPosition = await readPosition(admin)
	snap = await snapshot(admin)
})

test.afterAll(async () => {
	await restore(admin, snap)
	await admin.dispose()
})

/**
 * Assert a PUT was refused with 400 and the exact `{error}` body.
 *
 * @param tree the payload.
 * @param error the message, or a matcher for it.
 * @return nothing.
 */
async function expectRejected(tree: unknown, error: string | RegExp): Promise<void> {
	const res = await putTree(admin, tree)
	expect(res.status(), await res.text()).toBe(400)
	const body = await res.json()
	expect(Object.keys(body)).toEqual(['error'])
	if (typeof error === 'string') {
		expect(body.error).toBe(error)
	} else {
		expect(body.error).toMatch(error)
	}
}

test.describe
	.serial('navigation-editor-org — position default (REQ-ONAV-004)', () => {
	// @e2e navigation-editor-org::position-defaults-to-hidden
	test('a fresh installation answers {position: "hidden"}', async () => {
		// Recorded before this suite wrote anything. On the CI instance nothing
		// has written the setting yet, so this is the unset default from
		// AdminOrgNavigationController::readPosition.
		expect(firstPosition?.status).toBe(200)
		expect(firstPosition?.body).toEqual({ position: 'hidden' })
	})
})

test.describe.serial('navigation-editor-org — storage (REQ-ONAV-001)', () => {
	// @e2e navigation-editor-org::tree-persists-to-file
	test('a saved tree of 2 sections and 3 subsections reads back as the same JSON', async () => {
		const tree = [
			navNode(`E2E Section A ${RUN}`, {
				children: [
					navNode(`E2E Sub A1 ${RUN}`, { url: '/apps/launchpad/a1' }),
					navNode(`E2E Sub A2 ${RUN}`, {
						url: 'https://example.com/a2',
						openInNewTab: true,
					}),
				],
			}),
			navNode(`E2E Section B ${RUN}`, {
				icon: 'folder',
				children: [
					navNode(`E2E Sub B1 ${RUN}`, { url: '/apps/launchpad/b1' }),
				],
			}),
		]
		const saved = await putTree(admin, tree)
		expect(saved.status(), await saved.text()).toBe(200)

		const { status, body } = await readTree(admin, 'nl')
		expect(status).toBe(200)
		expect(body).toEqual({ tree, language: 'nl' })
		expect(body.tree).toHaveLength(2)
		expect(body.tree.flatMap((n: NavNode) => n.children)).toHaveLength(3)
	})

	// @e2e navigation-editor-org::tree-respects-max-depth-3
	test('a three-level tree is kept, a fourth level is never stored', async () => {
		const three = [
			navNode(`E2E Root ${RUN}`, {
				children: [
					navNode(`E2E Child ${RUN}`, {
						children: [
							navNode(`E2E Grandchild ${RUN}`, {
								url: '/apps/launchpad/g',
							}),
						],
					}),
				],
			}),
		]
		await seedTree(admin, three)
		const stored = await adminTree(admin)
		expect(stored).toEqual(three)
		expect(depthOf(stored)).toBe(3)

		const four = structuredClone(three)
		four[0].children![0].children![0].children = [navNode(`E2E Great ${RUN}`)]
		await expectRejected(four, 'Tree depth cannot exceed 3 levels')

		const after = await adminTree(admin)
		expect(after).toEqual(three)
		expect(depthOf(after)).toBeLessThanOrEqual(3)
	})

	// @e2e navigation-editor-org::label-is-required-per-node
	test('a node without a label is refused with 400 naming the label', async () => {
		const kept = [navNode(`E2E Kept ${RUN}`)]
		await seedTree(admin, kept)

		const missing = navNode('x')
		delete missing.label
		await expectRejected([missing], /label/)
		await expectRejected([navNode('   ')], /label/)
		expect(await adminTree(admin)).toEqual(kept)

		// The same node with a label is accepted, so the refusal was the label.
		const labelled = [{ ...missing, label: `E2E Labelled ${RUN}` }]
		await seedTree(admin, labelled)
		expect(await adminTree(admin)).toEqual(labelled)
	})
})

test.describe
	.serial('navigation-editor-org — group filtering (REQ-ONAV-002)', () => {
	const groups = {
		g1: `e2e-onav-g1-${RUN}`,
		g2: `e2e-onav-g2-${RUN}`,
		marketing: `e2e-onav-marketing-${RUN}`,
		sales: `e2e-onav-sales-${RUN}`,
		restricted: `e2e-onav-restricted-${RUN}`,
	}
	let userA: Principal | null = null
	let userB: Principal | null = null
	let created: string[] = []

	test.beforeAll(async () => {
		for (const id of Object.values(groups)) {
			created.push(await createGroup(admin, id))
		}
		userA = await createPrincipal(admin, 'e2e-onav-a')
		userB = await createPrincipal(admin, 'e2e-onav-b')
		await addToGroup(admin, userA.username, groups.g1)
		await addToGroup(admin, userA.username, groups.sales)
		await addToGroup(admin, userA.username, groups.restricted)
		await addToGroup(admin, userB.username, groups.g2)
		await assertActingAs(userA.api, userA.username)
		await assertActingAs(userB.api, userB.username)
		await grantReadsToEveryone(admin)
	})

	test.afterAll(async () => {
		await removePrincipal(admin, userA)
		await removePrincipal(admin, userB)
		for (const id of created) {
			await removeGroup(admin, id)
		}
		created = []
	})

	// @e2e navigation-editor-org::all-users-tree-is-visible-to-every-user
	test('a tree visible to all reaches users of different groups unfiltered', async () => {
		const tree = [
			navNode(`E2E Everyone ${RUN}`, {
				children: [
					navNode(`E2E Everyone child ${RUN}`, {
						url: '/apps/launchpad/e',
					}),
				],
			}),
			navNode(`E2E Everyone link ${RUN}`, { url: 'https://example.com' }),
		]
		await seedTree(admin, tree)

		for (const user of [userA!, userB!]) {
			const { status, body } = await readTree(user.api)
			expect(status, `${user.username}: ${JSON.stringify(body)}`).toBe(200)
			expect(body).toEqual({ tree, language: 'nl' })
		}
	})

	// @e2e navigation-editor-org::admin-only-subtree-filtered-for-non-admin
	test('an admin-only section and its children are absent for a non-admin', async () => {
		const section = navNode(`E2E Admin only ${RUN}`, {
			groupVisibility: ['admin'],
			children: [
				navNode(`E2E Admin child 1 ${RUN}`, { url: '/apps/launchpad/x1' }),
				navNode(`E2E Admin child 2 ${RUN}`, { url: '/apps/launchpad/x2' }),
			],
		})
		const open = navNode(`E2E Open ${RUN}`, { url: '/apps/launchpad/open' })
		await seedTree(admin, [section, open])

		const { status, body } = await readTree(userB!.api)
		expect(status).toBe(200)
		expect(body.tree).toEqual([open])
		for (const id of idsOf([section])) {
			expect(JSON.stringify(body)).not.toContain(id)
		}

		// The admin, a member of 'admin', still gets the section: the filter
		// removed it for the user, the file still has it.
		expect(await adminTree(admin)).toEqual([section, open])
	})

	// @e2e navigation-editor-org::user-in-one-of-multiple-groups-sees-node
	test('a member of one listed group sees the section', async () => {
		const section = navNode(`E2E Marketing or sales ${RUN}`, {
			groupVisibility: [groups.marketing, groups.sales],
			children: [
				navNode(`E2E Pipeline ${RUN}`, { url: '/apps/launchpad/pipeline' }),
			],
		})
		await seedTree(admin, [section])

		// userA is in sales, not in marketing.
		const seen = await readTree(userA!.api)
		expect(seen.status).toBe(200)
		expect(seen.body.tree).toEqual([section])

		// userB is in neither, so the section is not simply visible to all.
		const unseen = await readTree(userB!.api)
		expect(unseen.status).toBe(200)
		expect(unseen.body.tree).toEqual([])
	})

	// @e2e navigation-editor-org::hidden-parent-hides-children
	test('children visible to all disappear with a hidden parent', async () => {
		const children = [1, 2, 3].map((i) =>
			navNode(`E2E Restricted child ${i} ${RUN}`, {
				url: `/apps/launchpad/r${i}`,
			}),
		)
		const section = navNode(`E2E Restricted ${RUN}`, {
			groupVisibility: [groups.restricted],
			children,
		})
		await seedTree(admin, [section])

		// userB is not in 'restricted': neither the section nor any child.
		const hidden = await readTree(userB!.api)
		expect(hidden.status).toBe(200)
		expect(hidden.body.tree).toEqual([])
		for (const id of idsOf([section])) {
			expect(JSON.stringify(hidden.body)).not.toContain(id)
		}

		// userA is: the section and all three children.
		const shown = await readTree(userA!.api)
		expect(shown.status).toBe(200)
		expect(shown.body.tree).toEqual([section])
		expect(shown.body.tree[0].children).toHaveLength(3)
	})
})

// The read with the matrix the instance ships (all-users-tree-is-visible-to-every-user)
// runs in navigation-editor-org-read-defects.spec.ts, outside serial mode.

test.describe
	.serial('navigation-editor-org — write validation (REQ-ONAV-003)', () => {
	let viewer: Principal | null = null
	let roleId: number | null = null

	test.beforeAll(async () => {
		viewer = await createPrincipal(admin, 'e2e-onav-viewer')
		await assertActingAs(viewer.api, viewer.username)
		roleId = await assignRole(admin, { userId: viewer.username, role: 'viewer' })
	})

	test.afterAll(async () => {
		await removeRole(admin, roleId)
		await removePrincipal(admin, viewer)
	})

	// @e2e navigation-editor-org::admin-saves-valid-tree
	test('2 sections of 2 links each: 200 with the tree, and it is stored', async () => {
		const tree = ['One', 'Two'].map((s) =>
			navNode(`E2E Section ${s} ${RUN}`, {
				children: [
					navNode(`E2E ${s} link a ${RUN}`, {
						url: `/apps/launchpad/${s}/a`,
					}),
					navNode(`E2E ${s} link b ${RUN}`, {
						url: `/apps/launchpad/${s}/b`,
					}),
				],
			}),
		)
		const res = await putTree(admin, tree)
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ tree, language: 'nl' })

		// No ?lang= means nl; the GET reads nl.json back.
		const reread = await admin.get(
			'/index.php/apps/launchpad/api/admin/org-navigation',
		)
		expect(reread.status()).toBe(200)
		expect(await reread.json()).toEqual({ tree, language: 'nl' })
	})

	// @e2e navigation-editor-org::depth-exceeded-returns-400
	test('a four-level tree is refused with the depth message and not stored', async () => {
		const kept = [navNode(`E2E Kept depth ${RUN}`)]
		await seedTree(admin, kept)

		const four = [
			navNode(`E2E L1 ${RUN}`, {
				children: [
					navNode(`E2E L2 ${RUN}`, {
						children: [
							navNode(`E2E L3 ${RUN}`, {
								children: [navNode(`E2E L4 ${RUN}`)],
							}),
						],
					}),
				],
			}),
		]
		await expectRejected(four, 'Tree depth cannot exceed 3 levels')
		expect(await adminTree(admin)).toEqual(kept)
	})

	// @e2e navigation-editor-org::duplicate-node-ids-return-400
	test('the same id at two depths is refused naming a duplicate id', async () => {
		const kept = [navNode(`E2E Kept dup ${RUN}`)]
		await seedTree(admin, kept)

		const parent = navNode(`E2E Parent ${RUN}`)
		parent.children = [navNode(`E2E Child ${RUN}`, { id: parent.id })]
		const res = await putTree(admin, [parent])
		expect(res.status(), await res.text()).toBe(400)
		const { error } = await res.json()
		expect(error).toMatch(/duplicate/i)
		expect(error).toMatch(/\bid\b/i)
		expect(await adminTree(admin)).toEqual(kept)
	})

	// @e2e navigation-editor-org::non-admin-cannot-write
	test('a viewer is refused with 403, the admin is not', async () => {
		const kept = [navNode(`E2E Before viewer ${RUN}`)]
		await seedTree(admin, kept)
		const role = await viewer!.api.get('/index.php/apps/launchpad/api/me/role')
		expect(role.status(), await role.text()).toBe(200)
		expect((await role.json()).role).toBe('viewer')

		const attempt = [
			navNode(`E2E By viewer ${RUN}`, { url: '/apps/launchpad/v' }),
		]
		const refused = await putTree(viewer!.api, attempt)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await adminTree(admin)).toEqual(kept)

		const allowed = await putTree(admin, attempt)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(await adminTree(admin)).toEqual(attempt)
	})

	// @e2e navigation-editor-org::javascript-url-is-rejected
	test('javascript:alert(1) is refused with the scheme message', async () => {
		const kept = [navNode(`E2E Kept js ${RUN}`)]
		await seedTree(admin, kept)
		await expectRejected(
			[navNode(`E2E Js ${RUN}`, { url: 'javascript:alert(1)' })],
			'URL scheme is not allowed',
		)
		expect(await adminTree(admin)).toEqual(kept)
	})
})

test.describe
	.serial('navigation-editor-org — URL scheme validation (REQ-ONAV-011)', () => {
	test.beforeAll(async () => {
		await seedTree(admin, [navNode(`E2E Kept scheme ${RUN}`)])
	})

	// @e2e navigation-editor-org::javascript-url-rejected
	test('mixed-case JavaScript: is refused with the scheme message', async () => {
		const before = await adminTree(admin)
		await expectRejected(
			[navNode(`E2E Xss ${RUN}`, { url: 'JavaScript:alert("xss")' })],
			'URL scheme is not allowed',
		)
		expect(await adminTree(admin)).toEqual(before)
	})

	// @e2e navigation-editor-org::data-url-rejected
	test('a data: URL is refused with 400', async () => {
		const before = await adminTree(admin)
		await expectRejected(
			[
				navNode(`E2E Data ${RUN}`, {
					url: 'data:text/html,<script>alert(1)</script>',
				}),
			],
			'URL scheme is not allowed',
		)
		expect(await adminTree(admin)).toEqual(before)
	})

	// @e2e navigation-editor-org::https-url-allowed
	test('an https URL is accepted and stored', async () => {
		const tree = [
			navNode(`E2E Secure ${RUN}`, { url: 'https://example.com/secure' }),
		]
		const res = await putTree(admin, tree)
		expect(res.status(), await res.text()).toBe(200)
		const stored = await adminTree(admin)
		expect(stored).toEqual(tree)
		expect(stored[0].url).toBe('https://example.com/secure')
	})

	// @e2e navigation-editor-org::relative-path-allowed
	test('a relative path is accepted and stored', async () => {
		const tree = [
			navNode(`E2E Relative ${RUN}`, {
				url: '/apps/launchpad/dashboards/sales',
			}),
		]
		const res = await putTree(admin, tree)
		expect(res.status(), await res.text()).toBe(200)
		const stored = await adminTree(admin)
		expect(stored).toEqual(tree)
		expect(stored[0].url).toBe('/apps/launchpad/dashboards/sales')
	})
})
