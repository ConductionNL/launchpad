/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the org navigation admin editor
 * (openspec/specs/navigation-editor-org, REQ-ONAV-001 node ids, REQ-ONAV-007
 * and REQ-ONAV-012), written for the e2e-coverage-programme.
 *
 * The editor lives in Nextcloud's admin settings, Beheer > Org navigation
 * (`AdminSettings.vue` tab `org-navigation`, `OrgNavigationEditor.vue`,
 * `OrgNavigationEditorRow.vue`). Every scenario is driven through its own
 * controls; the API only seeds the starting tree and re-reads what Save wrote.
 *
 * The trees, the position and the action matrix are recorded in beforeAll and
 * put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::node-id-is-uuid
 *   @e2e navigation-editor-org::dragmove-to-reorder-at-same-level
 *   @e2e navigation-editor-org::add-child-enforces-depth-limit
 *   @e2e navigation-editor-org::group-visibility-multi-select
 *   @e2e navigation-editor-org::save-valid-tree-persists
 *   @e2e navigation-editor-org::save-with-error-prevents-persist
 *   @e2e navigation-editor-org::dutch-translation-available
 *   @e2e navigation-editor-org::english-translation-fallback
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { NavNode, Snapshot } from './support/orgNavigation.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import {
	adminTree,
	navNode,
	putTree,
	restore,
	seedTree,
	snapshot,
	UUID_RE,
} from './support/orgNavigation.ts'
import {
	addToGroup,
	adminContext,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = '/index.php/apps/launchpad/admin'
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

let admin: APIRequestContext
let snap: Snapshot | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	snap = await snapshot(admin)
})

test.afterAll(async () => {
	await restore(admin, snap)
	await admin.dispose()
})

/**
 * Open Beheer > Org navigation and wait for the editor to load its tree.
 *
 * @param page the page.
 * @return the editor.
 */
async function openEditor(page: Page): Promise<Locator> {
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
	const editor = page.locator('.org-nav-editor')
	await expect(editor).toBeVisible()
	expect((await loaded).status()).toBe(200)
	return editor
}

/**
 * The editor row of one node.
 *
 * @param page the page.
 * @param id the node id.
 * @return the row's `<li>`.
 */
function row(page: Page, id: string): Locator {
	return page.locator(`li.org-nav-row[data-test-id="${id}"]`)
}

/**
 * The row's own control strip (not its children's).
 *
 * @param page the page.
 * @param id the node id.
 * @return the strip.
 */
function controls(page: Page, id: string): Locator {
	return row(page, id).locator(':scope > .org-nav-row__row')
}

/**
 * The row's own visibility strip.
 *
 * @param page the page.
 * @param id the node id.
 * @return the strip.
 */
function visibility(page: Page, id: string): Locator {
	return row(page, id).locator(':scope > .org-nav-row__visibility')
}

/**
 * The labels of the top-level rows, in rendered order.
 *
 * @param page the page.
 * @return the label input values.
 */
async function topLabels(page: Page): Promise<string[]> {
	return page
		.locator(
			'ul.org-nav-editor__tree > li.org-nav-row > .org-nav-row__row > input.org-nav-row__label',
		)
		.evaluateAll((els) => els.map((el) => (el as HTMLInputElement).value))
}

/**
 * Click Save and wait for the PUT it sends.
 *
 * @param page the page.
 * @return the PUT's status.
 */
async function clickSave(page: Page): Promise<number> {
	const put = page.waitForResponse(
		(r) =>
			r.request().method() === 'PUT'
			&& /\/api\/admin\/org-navigation\?/.test(r.url()),
		{ timeout: 30_000 },
	)
	await page.locator('[data-test="org-nav-save"]').click()
	return (await put).status()
}

test.describe.serial('navigation-editor-org — admin editor (REQ-ONAV-007)', () => {
	// @e2e navigation-editor-org::node-id-is-uuid
	test('a node added in the editor is saved with a UUID id', async ({ page }) => {
		await seedTree(admin, [])
		await openEditor(page)
		await expect(page.locator('.org-nav-editor__empty')).toBeVisible()

		await page.locator('[data-test="org-nav-add-link"]').click()
		await expect(page.locator('li.org-nav-row')).toHaveCount(1)
		expect(await clickSave(page)).toBe(200)

		const stored = await adminTree(admin)
		expect(stored).toHaveLength(1)
		expect(stored[0].id).toMatch(UUID_RE)
		expect(stored[0]).toMatchObject({ label: 'New link', url: '/' })

		// And the server holds the rule, not only the editor.
		const refused = await putTree(admin, [{ ...stored[0], id: 'not-a-uuid' }])
		expect(refused.status()).toBe(400)
		expect(await refused.json()).toEqual({
			error: 'Node id must be a valid UUID',
		})
		expect(await adminTree(admin)).toEqual(stored)
	})

	// @e2e navigation-editor-org::dragmove-to-reorder-at-same-level
	test('Move up on B turns [A, B, C] into [B, A, C]', async ({ page }) => {
		const [a, b, c] = ['A', 'B', 'C'].map((l) => navNode(`E2E ${l} ${RUN}`))
		await seedTree(admin, [a, b, c])
		await openEditor(page)
		await expect.poll(() => topLabels(page)).toEqual([a.label, b.label, c.label])

		await controls(page, b.id).getByRole('button', { name: 'Move up' }).click()
		await expect.poll(() => topLabels(page)).toEqual([b.label, a.label, c.label])

		// It is the tree's order, not only the rows': Save writes it.
		expect(await clickSave(page)).toBe(200)
		expect((await adminTree(admin)).map((n) => n.id)).toEqual([b.id, a.id, c.id])
	})

	// @e2e navigation-editor-org::add-child-enforces-depth-limit
	test('Add child is disabled on the deepest row, with the depth tooltip', async ({
		page,
	}) => {
		const l3 = navNode(`E2E Depth 3 ${RUN}`, { url: '/apps/launchpad/d3' })
		const l2 = navNode(`E2E Depth 2 ${RUN}`, { children: [l3] })
		const l1 = navNode(`E2E Depth 1 ${RUN}`, { children: [l2] })
		await seedTree(admin, [l1])
		await openEditor(page)

		const deepest = controls(page, l3.id).getByRole('button', {
			name: 'Add child',
		})
		await expect(deepest).toBeVisible()
		await expect(deepest).toBeDisabled()
		await expect(deepest).toHaveAttribute(
			'title',
			/Tree depth cannot exceed 3 levels/,
		)

		// One level up it is still allowed, so the limit is the depth.
		const above = controls(page, l2.id).getByRole('button', {
			name: 'Add child',
		})
		await expect(above).toBeEnabled()
		await expect(above).not.toHaveAttribute('title', /Tree depth/)
	})

	test.describe.serial('group visibility', () => {
		const marketing = `e2e-onav-marketing-${RUN}`
		const sales = `e2e-onav-sales-${RUN}`
		const elsewhere = `e2e-onav-elsewhere-${RUN}`
		let created: string[] = []

		test.beforeAll(async () => {
			for (const id of [marketing, sales, elsewhere]) {
				created.push(await createGroup(admin, id))
			}
			// The editor reads the tree through the group-filtered GET, so the
			// admin has to be in one of the node's groups to see the node at
			// all; navigation-editor-org-read-defects.spec.ts covers the case where
			// the admin is in none of them.
			await addToGroup(admin, ADMIN_USER, sales)
		})

		test.afterAll(async () => {
			for (const id of created) {
				await removeGroup(admin, id)
			}
			created = []
		})

		// @e2e navigation-editor-org::group-visibility-multi-select
		test('the selector lists every group with the node’s groups selected, and the toggle clears them', async ({
			page,
		}) => {
			const node = navNode(`E2E Grouped ${RUN}`, {
				groupVisibility: [marketing, sales],
			})
			await seedTree(admin, [node])

			const listed = await admin.get('/ocs/v1.php/cloud/groups?format=json')
			expect(listed.status()).toBe(200)
			const allGroups: string[] = (await listed.json()).ocs.data.groups

			await openEditor(page)
			const strip = visibility(page, node.id)
			const everyone = strip.getByRole('checkbox', {
				name: 'Visible to everyone',
			})
			await expect(everyone).not.toBeChecked()

			const select = strip.locator('select.org-nav-row__groups')
			await expect(select).toBeVisible()
			const options = await select
				.locator('option')
				.evaluateAll((els) =>
					els.map((el) => (el as HTMLOptionElement).value),
				)
			expect([...options].sort()).toEqual([...allGroups].sort())
			const selected = await select
				.locator('option')
				.evaluateAll((els) =>
					els
						.filter((el) => (el as HTMLOptionElement).selected)
						.map((el) => (el as HTMLOptionElement).value),
				)
			expect(selected.sort()).toEqual([marketing, sales].sort())

			// The toggle clears the array.
			await everyone.check()
			await expect(select).toHaveCount(0)
			expect(await clickSave(page)).toBe(200)
			const stored = await adminTree(admin)
			expect(stored).toEqual([{ ...node, groupVisibility: null }])
		})

		// A node in groups the admin is not in runs, expected red, in
		// navigation-editor-org-read-defects.spec.ts.
	})

	// @e2e navigation-editor-org::save-valid-tree-persists
	test('adding, reordering and restricting in the editor, then Save, writes that tree', async ({
		page,
	}) => {
		await seedTree(admin, [])
		await openEditor(page)

		await page.locator('[data-test="org-nav-add-section"]').click()
		await page.locator('[data-test="org-nav-add-section"]').click()
		const labels = page.locator(
			'ul.org-nav-editor__tree > li.org-nav-row > .org-nav-row__row > input.org-nav-row__label',
		)
		await expect(labels).toHaveCount(2)
		const alpha = `E2E Alpha ${RUN}`
		const beta = `E2E Beta ${RUN}`
		await labels.nth(0).fill(alpha)
		await labels.nth(1).fill(beta)

		// Reorder: Beta up.
		const second = page
			.locator('ul.org-nav-editor__tree > li.org-nav-row')
			.nth(1)
		await second
			.locator(':scope > .org-nav-row__row')
			.getByRole('button', { name: 'Move up' })
			.click()
		await expect.poll(() => topLabels(page)).toEqual([beta, alpha])

		// Restrict Beta (now first) to the admin group.
		const first = page.locator('ul.org-nav-editor__tree > li.org-nav-row').nth(0)
		const strip = first.locator(':scope > .org-nav-row__visibility')
		await strip.getByRole('checkbox', { name: 'Visible to everyone' }).uncheck()
		await strip.locator('select.org-nav-row__groups').selectOption(['admin'])

		expect(await clickSave(page)).toBe(200)
		await expect(page.locator('.org-nav-editor__success')).toHaveText(
			'Navigation saved successfully.',
		)
		await expect(page.locator('[data-test="org-nav-editor-error"]')).toHaveCount(
			0,
		)

		const stored = await adminTree(admin, 'nl')
		expect(stored).toHaveLength(2)
		expect(stored[0]).toMatchObject({
			label: beta,
			url: null,
			groupVisibility: ['admin'],
			children: [],
		})
		expect(stored[1]).toMatchObject({
			label: alpha,
			url: null,
			groupVisibility: null,
			children: [],
		})
		expect(stored[0].id).toMatch(UUID_RE)
		expect(stored[1].id).toMatch(UUID_RE)
	})

	// @e2e navigation-editor-org::save-with-error-prevents-persist
	test('a save the server refuses shows its error and writes nothing', async ({
		page,
	}) => {
		const a = navNode(`E2E Valid A ${RUN}`)
		const c = navNode(`E2E Valid C ${RUN}`, { url: '/apps/launchpad/c' })
		const b = navNode(`E2E Valid B ${RUN}`, { children: [c] })
		const seeded: NavNode[] = [a, b]
		await seedTree(admin, seeded)
		await openEditor(page)

		// The editor cannot build a duplicate id or a fourth level through its
		// own controls (Add child is disabled at depth 3, ids are fresh UUIDs),
		// so the duplicate is put into the outgoing payload: the child takes
		// its root's id. The server's real validator answers.
		await page.route(
			(url) => url.pathname.endsWith('/api/admin/org-navigation'),
			async (route) => {
				if (route.request().method() !== 'PUT') {
					return route.continue()
				}
				const body = route.request().postDataJSON()
				body.tree[1].children[0].id = body.tree[0].id
				return route.continue({ postData: JSON.stringify(body) })
			},
		)

		await controls(page, a.id)
			.locator('input.org-nav-row__label')
			.fill(`E2E Edited ${RUN}`)
		expect(await clickSave(page)).toBe(400)

		const error = page.locator('[data-test="org-nav-editor-error"]')
		await expect(error).toBeVisible()
		await expect(error).toContainText('duplicate')
		await expect(error).toContainText(a.id)
		await expect(page.locator('.org-nav-editor__success')).toHaveCount(0)

		expect(await adminTree(admin)).toEqual(seeded)
	})
})

test.describe.serial('navigation-editor-org — translations (REQ-ONAV-012)', () => {
	let dutchAdmin: Principal | null = null

	test.beforeAll(async () => {
		dutchAdmin = await createPrincipal(admin, 'e2e-onav-nl')
		await addToGroup(admin, dutchAdmin.username, 'admin')
		const lang = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(dutchAdmin.username)}`,
			{ form: { key: 'language', value: 'nl' } },
		)
		expect(lang.status(), await lang.text()).toBe(200)
		await seedTree(admin, [navNode(`E2E Dutch ${RUN}`)])
	})

	test.afterAll(async () => {
		await removePrincipal(admin, dutchAdmin)
	})

	// @e2e navigation-editor-org::dutch-translation-available
	test('an admin whose language is Dutch gets the editor in Dutch', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(
			browser,
			dutchAdmin!.username,
			dutchAdmin!.password,
		)
		try {
			const editor = await openEditor(page)
			await expect(editor.locator('h3')).toHaveText('Organisatienavigatie')
			await expect(
				page.locator('[data-test="org-nav-add-section"]'),
			).toHaveText('Sectie toevoegen')
			await expect(page.locator('[data-test="org-nav-add-link"]')).toHaveText(
				'Koppeling toevoegen',
			)
			await expect(page.locator('[data-test="org-nav-save"]')).toHaveText(
				'Opslaan',
			)
			await expect(editor.getByText('Positie', { exact: true })).toBeVisible()
			await expect(editor.getByText('Taal', { exact: true })).toBeVisible()
			const row = page.locator('li.org-nav-row').first()
			await expect(
				row.getByRole('button', { name: 'Verwijderen' }),
			).toBeVisible()
			await expect(
				row.getByRole('button', { name: 'Omhoog verplaatsen' }),
			).toBeVisible()
			await expect(row.getByText('Zichtbaar voor iedereen')).toBeVisible()
			await expect(
				page.getByRole('button', { name: 'Add section' }),
			).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e navigation-editor-org::english-translation-fallback
	test('a string missing from the Dutch catalogue falls back to English', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(
			browser,
			dutchAdmin!.username,
			dutchAdmin!.password,
		)
		try {
			// Every editor string is translated (parity), so the GIVEN is built:
			// the served Dutch catalogue loses its "Add section" entry.
			let stripped = false
			await context.route(
				/\/launchpad\/l10n\/nl\.js(\?.*)?$/,
				async (route) => {
					const response = await route.fetch()
					const original = await response.text()
					const body = original.replace(
						/^\s*"Add section"\s*:\s*"[^"]*",?\s*$/m,
						'',
					)
					stripped = body !== original
					await route.fulfill({ response, body })
				},
			)

			await openEditor(page)
			expect(stripped).toBe(true)
			await expect(
				page.locator('[data-test="org-nav-add-section"]'),
			).toHaveText('Add section')
			// The rest of the catalogue still loaded, so the fallback is per string.
			await expect(page.locator('[data-test="org-nav-add-link"]')).toHaveText(
				'Koppeling toevoegen',
			)
		} finally {
			await context.close()
		}
	})
})
