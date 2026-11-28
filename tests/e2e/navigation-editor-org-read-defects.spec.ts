/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two org-navigation scenarios (openspec/specs/navigation-editor-org) whose
 * remaining case was asserted only by a `test.fixme`, which gate-19 does not
 * count and which never ran. They run here, in a file without serial mode,
 * because navigation-editor-org-api.spec.ts and
 * navigation-editor-org-editor.spec.ts run serially and a red there would
 * skip every test after it.
 *
 * all-users-tree-is-visible-to-every-user (REQ-ONAV-002), with the action
 * matrix the instance ships rather than one the test grants. Expected green:
 * when the fixme was written lib/actions.seed.json held both reads as
 * ["admin"]; it now holds ["admin", "@all"] and lib/Repair/ApplyActionBaseline.php
 * widens an older stored matrix the same way. The test in the API file
 * grants the reads first, so it could not see a regression of the shipped
 * matrix; this one can.
 *
 * group-visibility-multi-select (REQ-ONAV-007), on a node restricted to
 * groups the administrator is NOT in. EXPECTED RED until the product is
 * fixed: the editor loads its working tree through the same group-filtered
 * GET every user gets (AdminOrgNavigationController::getOrgNavigation() ->
 * OrgNavigationService::filterTreeByUserGroups(), which has no administrator
 * bypass), so the node never reaches the editor and the next wholesale Save
 * deletes it. The test in the editor file puts the admin in one of the
 * node's groups to get round this.
 *
 * The trees, the position and the action matrix are recorded in beforeAll and
 * put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::all-users-tree-is-visible-to-every-user
 *   @e2e navigation-editor-org::group-visibility-multi-select
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Snapshot } from './support/orgNavigation.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import {
	adminTree,
	navNode,
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
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = '/index.php/settings/admin/launchpad'
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

let admin: APIRequestContext
let snap: Snapshot | null = null
const principals: Principal[] = []
const groups: string[] = []

// A brand-new account's first request builds its home folder.
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	snap = await snapshot(admin)
})

test.afterAll(async () => {
	await restore(admin, snap)
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const id of groups) {
		await removeGroup(admin, id)
	}
	await admin.dispose()
})

/**
 * A throwaway Nextcloud group, removed in afterAll.
 *
 * @param label the readable part of the id.
 * @return the group id.
 */
async function freshGroup(label: string): Promise<string> {
	const id = await createGroup(admin, `e2e-onav-${label}-${RUN}`)
	groups.push(id)
	return id
}

/**
 * Open Beheer > Org navigation and wait for the editor to load its tree.
 *
 * @param page the page.
 * @return nothing.
 */
async function openEditor(page: Page): Promise<void> {
	const loaded = page.waitForResponse(
		(r) =>
			r.request().method() === 'GET'
			// The editor reads its whole-tree copy (REQ-ONAV-007).
			&& /\/api\/admin\/org-navigation\/editor\?/.test(r.url()),
		{ timeout: 30_000 },
	)
	await page.goto(SETTINGS)
	const tab = adminTab(page, 'org-navigation')
	await expect(tab).toBeVisible({ timeout: 30_000 })
	await tab.click()
	await expect(page.locator('.org-nav-editor')).toBeVisible()
	expect((await loaded).status()).toBe(200)
}

test.describe('REQ-ONAV-002 read access with the shipped matrix', () => {
	// @e2e navigation-editor-org::all-users-tree-is-visible-to-every-user
	test('two ordinary users in different groups both read the full tree', async () => {
		// The matrix snapshot() recorded is the instance's own; nothing here
		// grants the reads.
		const g1 = await freshGroup('g1')
		const g2 = await freshGroup('g2')
		const userA = await createPrincipal(admin, 'e2e-onav-shipped-a')
		const userB = await createPrincipal(admin, 'e2e-onav-shipped-b')
		principals.push(userA, userB)
		await assertActingAs(userA.api, userA.username)
		await assertActingAs(userB.api, userB.username)
		await addToGroup(admin, userA.username, g1)
		await addToGroup(admin, userB.username, g2)

		const tree = [
			navNode(`E2E Shipped ${RUN}`, {
				url: '/apps/launchpad/shipped',
				children: [navNode(`E2E Shipped child ${RUN}`)],
			}),
			navNode(`E2E Shipped 2 ${RUN}`),
		]
		await seedTree(admin, tree)

		const adminPosition = await readPosition(admin)
		expect(adminPosition.status).toBe(200)
		for (const user of [userA, userB]) {
			const read = await readTree(user.api)
			expect(read.status, JSON.stringify(read.body)).toBe(200)
			expect(read.body).toEqual({ tree, language: 'nl' })

			const position = await readPosition(user.api)
			expect(position.status, JSON.stringify(position.body)).toBe(200)
			expect(position.body).toEqual(adminPosition.body)
		}
	})
})

test.describe('REQ-ONAV-007 a node in groups the admin is not in', () => {
	// @e2e navigation-editor-org::group-visibility-multi-select
	// EXPECTED RED: the editor's GET filters the tree by the admin's groups.
	test('the node opens in the editor with its groups selected, and an unrelated Save keeps it', async ({
		page,
	}) => {
		const marketing = await freshGroup('mkt')
		const sales = await freshGroup('sales')
		const hidden = navNode(`E2E Elsewhere ${RUN}`, {
			groupVisibility: [marketing, sales],
		})
		const open = navNode(`E2E Open ${RUN}`)
		await seedTree(admin, [hidden, open])
		await openEditor(page)

		const strip = page.locator(
			`li.org-nav-row[data-test-id="${hidden.id}"] > .org-nav-row__visibility`,
		)
		const select = strip.locator('select.org-nav-row__groups')
		await expect(select).toBeVisible()
		const selected = await select
			.locator('option')
			.evaluateAll((els) =>
				els
					.filter((el) => (el as HTMLOptionElement).selected)
					.map((el) => (el as HTMLOptionElement).value),
			)
		expect(selected.sort()).toEqual([marketing, sales].sort())

		// Saving an unrelated edit must not drop it. The admin joins one of
		// its groups only now, so the re-read can see it.
		const put = page.waitForResponse(
			(r) =>
				r.request().method() === 'PUT'
				&& /\/api\/admin\/org-navigation\?/.test(r.url()),
			{ timeout: 30_000 },
		)
		await page.locator('[data-test="org-nav-add-link"]').click()
		await page.locator('[data-test="org-nav-save"]').click()
		expect((await put).status()).toBe(200)
		await addToGroup(admin, ADMIN_USER, marketing)
		expect((await adminTree(admin)).map((n) => n.id)).toContain(hidden.id)
	})
})
