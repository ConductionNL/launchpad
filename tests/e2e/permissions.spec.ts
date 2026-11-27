/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Permission levels on a user's OWN copy of an admin template
 * (REQ-PERM-001, REQ-PERM-002, REQ-PERM-003, REQ-PERM-004, REQ-PERM-005,
 * REQ-PERM-007, REQ-PERM-008, REQ-PERM-010).
 *
 * `dashboard-permission-levels.spec.ts` reaches a restricted level through a
 * SHARE: the admin owns the dashboard and alice holds a view-only, add-only or
 * full share. That proves what a share recipient may do, and it cannot reach
 * the rest of this spec: a share recipient is not the owner (so metadata edits
 * and deleting are refused for a different reason), a share carries no
 * compulsory widgets, and a share has no `basedOnTemplate` to resolve through.
 *
 * WHERE THE LEVELS COME FROM HERE. The documented flow, the same one
 * `template-reaches-member.spec.ts` drives: an administrator adds the shipped
 * `mijn-werkdag` template (two compulsory widgets, `header` and `attention`,
 * and six that are not) and gives it to a group. A member who owns nothing yet
 * asks for their dashboard and gets a personal copy: `type: user`, owned by
 * them, `basedOnTemplate` pointing at the template, every placement cloned
 * with its `isCompulsory` flag. Two templates are installed, one per group:
 *
 *   - `add_only`, for carol (API probes) and bea (browser probes);
 *   - `view_only`, for dave, who therefore OWNS a view-only dashboard.
 *
 * The copy is made by `GET /api/dashboard` (the `resolveTemplateRung` step of
 * `getEffectiveDashboard`), which the page itself calls on first load.
 *
 * 🔴 THE TESTS RUN IN ORDER AND SHARE STATE. The template's level is changed by
 * the resolution and propagation tests (and restored where a later test needs
 * it), and the last test deletes the add-only template. Every refusal is
 * probed by the user who holds the level, re-read as that user (the OWNER of
 * the copy) to prove nothing was written, and paired with an operation the
 * same user may do on the same dashboard.
 *
 * Every account, group and template this file makes is removed in `afterAll`.
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const STAMP = Date.now()
const SETTINGS = `${API}/admin/settings`

interface Placement {
	id: number
	widgetId: string
	isCompulsory: number
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	customTitle?: string | null
	showTitle?: number
	content?: Record<string, unknown>
}

interface Envelope {
	dashboard: {
		id: number
		uuid: string
		name: string
		description?: string | null
		userId: string | null
		basedOnTemplate: number | null
		permissionLevel: string | null
	}
	placements: Placement[]
	permissionLevel: string
}

interface Member {
	principal: Principal
	dashboardId: number
}

test.describe.configure({ mode: 'serial', timeout: 180_000 })

test.describe('permission levels on a template copy the user owns', () => {
	let admin: APIRequestContext
	let allowBefore: boolean | null = null
	let addGroup: string | null = null
	let viewGroup: string | null = null
	let addTemplate: number | null = null
	let viewTemplate: number | null = null
	let carol: Member | null = null
	let bea: Member | null = null
	let dave: Member | null = null
	let bob: Principal | null = null

	/** Install a fresh copy of the shipped template for one group, at a level. */
	async function installTemplate(group: string, level: string): Promise<number> {
		const installed = await admin.post(
			`${API}/admin/templates/shipped/mijn-werkdag/install`,
			{ data: { force: true } },
		)
		expect(installed.status(), await installed.text()).toBe(201)
		const id = Number((await installed.json()).id)
		const targeted = await admin.put(`${API}/admin/templates/${id}`, {
			data: { targetGroups: [group], permissionLevel: level },
		})
		expect(targeted.status(), await targeted.text()).toBe(200)
		expect((await targeted.json()).permissionLevel).toBe(level)
		return id
	}

	/** Set a template's level as the admin, as the Templates page does. */
	async function setTemplateLevel(id: number, level: string): Promise<void> {
		const res = await admin.put(`${API}/admin/templates/${id}`, {
			data: { permissionLevel: level },
		})
		expect(res.status(), await res.text()).toBe(200)
	}

	/**
	 * A brand-new member of `group` and the copy they get on first asking.
	 *
	 * @param group the group the template targets.
	 * @param templateId the template the copy must be made from.
	 * @param prefix a readable account prefix.
	 * @return the member and the id of their copy.
	 */
	async function memberWithCopy(
		group: string,
		templateId: number,
		prefix: string,
	): Promise<Member> {
		const principal = await createPrincipal(admin, prefix)
		await assertActingAs(principal.api, principal.username)
		await addToGroup(admin, principal.username, group)

		const res = await principal.api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = (await res.json()) as Envelope
		expect(body.dashboard.userId, 'the copy must belong to the member').toBe(
			principal.username,
		)
		expect(
			body.dashboard.basedOnTemplate,
			'the member must get a copy of THIS template',
		).toBe(templateId)
		return { principal, dashboardId: Number(body.dashboard.id) }
	}

	/** A dashboard as `who` reads it: the row, its placements, the effective level. */
	async function read(who: Principal, id: number): Promise<Envelope> {
		const res = await who.api.get(`${API}/dashboard/${id}`)
		expect(res.status(), await res.text()).toBe(200)
		return (await res.json()) as Envelope
	}

	/** The template's own placements, read by the admin. */
	async function templatePlacements(id: number): Promise<Placement[]> {
		const res = await admin.get(`${API}/admin/templates/${id}`)
		expect(res.status(), await res.text()).toBe(200)
		return (await res.json()).placements ?? []
	}

	const compulsory = (ps: Placement[]) =>
		ps.filter((p) => Number(p.isCompulsory) === 1)
	const optional = (ps: Placement[]) =>
		ps.filter((p) => Number(p.isCompulsory) === 0)
	const widgetIds = (ps: Placement[]) => ps.map((p) => p.widgetId).sort()

	test.beforeAll(async () => {
		admin = await adminContext()

		const settings = await admin.get(SETTINGS)
		expect(settings.status(), await settings.text()).toBe(200)
		allowBefore = (await settings.json()).allowUserDashboards === true
		// A member is only handed a copy when personal dashboards are on.
		const on = await admin.put(SETTINGS, { data: { allowUserDash: true } })
		expect(on.status(), await on.text()).toBe(200)

		addGroup = await createGroup(admin, `e2e-perm-add-${STAMP}`)
		viewGroup = await createGroup(admin, `e2e-perm-view-${STAMP}`)
		addTemplate = await installTemplate(addGroup, 'add_only')
		viewTemplate = await installTemplate(viewGroup, 'view_only')

		carol = await memberWithCopy(addGroup, addTemplate, 'e2e-perm-carol')
		bea = await memberWithCopy(addGroup, addTemplate, 'e2e-perm-bea')
		dave = await memberWithCopy(viewGroup, viewTemplate, 'e2e-perm-dave')

		// bob is in neither group: the stranger REQ-PERM-010 is about.
		bob = await createPrincipal(admin, 'e2e-perm-bob')
		await assertActingAs(bob.api, bob.username)
	})

	test.afterAll(async () => {
		if (admin === undefined) {
			return
		}
		for (const id of [addTemplate, viewTemplate]) {
			if (id !== null) {
				await admin.delete(`${API}/admin/templates/${id}`)
			}
		}
		for (const member of [carol, bea, dave]) {
			await removePrincipal(admin, member?.principal ?? null)
		}
		await removePrincipal(admin, bob)
		await removeGroup(admin, addGroup)
		await removeGroup(admin, viewGroup)
		if (allowBefore !== null) {
			await admin.put(SETTINGS, { data: { allowUserDash: allowBefore } })
		}
		await admin.dispose()
	})

	// ── REQ-PERM-004: compulsory widgets ──────────────────────────────────

	// @e2e permissions::compulsory-flag-inherited-from-template
	test('the copy carries the template compulsory flags, and keeps them', async () => {
		const fromTemplate = compulsory(await templatePlacements(addTemplate!))
		expect(
			fromTemplate.length,
			'the shipped template must have compulsory widgets to inherit',
		).toBeGreaterThan(0)

		const copy = await read(carol!.principal, carol!.dashboardId)
		expect(copy.dashboard.userId).toBe(carol!.principal.username)
		expect(widgetIds(compulsory(copy.placements))).toEqual(
			widgetIds(fromTemplate),
		)
		// Some widgets are NOT compulsory, so the flag was copied, not forced.
		expect(optional(copy.placements).length).toBeGreaterThan(0)

		// It persists: a second read of the stored copy says the same.
		const again = await read(carol!.principal, carol!.dashboardId)
		expect(widgetIds(compulsory(again.placements))).toEqual(
			widgetIds(fromTemplate),
		)
	})

	// ── REQ-PERM-008: effective level resolution ──────────────────────────

	// @e2e permissions::template-based-permission-resolution
	test('the effective level is the template level, not the copy own column', async () => {
		const before = await read(carol!.principal, carol!.dashboardId)
		expect(before.dashboard.basedOnTemplate).toBe(addTemplate)
		expect(before.permissionLevel).toBe('add_only')

		// Make the template disagree with the copy's own column: whatever the
		// resolver now answers can only have come from the template.
		await setTemplateLevel(addTemplate!, 'view_only')
		try {
			const during = await read(carol!.principal, carol!.dashboardId)
			expect(
				during.dashboard.permissionLevel,
				'the copy own column is untouched',
			).toBe('add_only')
			expect(
				during.permissionLevel,
				'the effective level must come from the source template',
			).toBe('view_only')

			// And it is enforced at that level, not just reported.
			const refused = await carol!.principal.api.post(
				`${API}/dashboard/${carol!.dashboardId}/widgets`,
				{
					data: {
						widgetId: 'text',
						gridX: 0,
						gridY: 40,
						gridWidth: 2,
						gridHeight: 2,
					},
				},
			)
			expect(refused.status(), await refused.text()).toBe(403)
		} finally {
			await setTemplateLevel(addTemplate!, 'add_only')
		}
		expect(
			(await read(carol!.principal, carol!.dashboardId)).permissionLevel,
		).toBe('add_only')
	})

	// ── REQ-PERM-002 / REQ-PERM-006: compulsory removal on add_only ───────

	// @e2e permissions::add-only-user-cannot-remove-compulsory-widgets
	// @e2e permissions::api-rejects-compulsory-widget-deletion-on-add-only-dashboard
	test('an add-only owner cannot delete a compulsory widget, and it stays', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)
		expect(copy.permissionLevel).toBe('add_only')
		const locked = compulsory(copy.placements)[0]
		const loose = optional(copy.placements)[0]
		expect(locked, 'a compulsory widget to probe with').toBeTruthy()
		expect(loose, 'a non-compulsory widget for the control').toBeTruthy()

		const res = await carol!.principal.api.delete(`${API}/widgets/${locked.id}`)
		const text = await res.text()
		expect(
			res.status(),
			`an add-only owner deleted a compulsory widget: ${text}`,
		).toBe(403)
		expect
			.soft(
				String(JSON.parse(text).error ?? ''),
				'the 403 must say compulsory widgets cannot be removed',
			)
			.toMatch(/compulsory/i)

		const after = await read(carol!.principal, carol!.dashboardId)
		expect(
			after.placements.some((p) => p.id === locked.id),
			'a refused delete must leave the compulsory widget in place',
		).toBe(true)

		// Control: the same request, same owner, same dashboard, on a widget
		// that is not compulsory.
		const allowed = await carol!.principal.api.delete(
			`${API}/widgets/${loose.id}`,
		)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(
			(await read(carol!.principal, carol!.dashboardId)).placements.some(
				(p) => p.id === loose.id,
			),
		).toBe(false)
	})

	// @e2e permissions::users-cannot-change-the-compulsory-flag
	test('an owner cannot clear the compulsory flag on their copy', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)
		const locked = compulsory(copy.placements)[0]

		const res = await carol!.principal.api.put(`${API}/widgets/${locked.id}`, {
			data: { isCompulsory: 0 },
		})
		// The spec allows either: the field is ignored (200) or refused (403).
		expect([200, 403], await res.text()).toContain(res.status())

		const after = await read(carol!.principal, carol!.dashboardId)
		const placement = after.placements.find((p) => p.id === locked.id)
		expect(Number(placement?.isCompulsory), 'isCompulsory must remain 1').toBe(1)

		// And it still behaves as compulsory.
		const remove = await carol!.principal.api.delete(
			`${API}/widgets/${locked.id}`,
		)
		expect(remove.status(), await remove.text()).toBe(403)
	})

	// @e2e permissions::add-only-user-can-add-conditional-rules
	test('an add-only owner can add a conditional rule to a widget', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)
		const target = optional(copy.placements)[0]
		expect(target, 'a non-compulsory widget to put a rule on').toBeTruthy()

		const res = await carol!.principal.api.post(
			`${API}/widgets/${target.id}/rules`,
			{
				data: {
					ruleType: 'group',
					ruleConfig: { groups: [addGroup] },
					isInclude: true,
				},
			},
		)
		expect(res.status(), await res.text()).toBe(201)
		const ruleId = Number((await res.json()).id)

		const rules = await carol!.principal.api.get(
			`${API}/widgets/${target.id}/rules`,
		)
		expect(rules.status(), await rules.text()).toBe(200)
		expect(
			((await rules.json()).rules ?? []).map((r: { id: number }) =>
				Number(r.id),
			),
		).toContain(ruleId)
	})

	// @e2e permissions::placement-ownership-check-via-dashboard
	// @e2e permissions::dashboard-ownership-check
	test('a stranger cannot put a rule on a widget of a dashboard they do not own', async () => {
		/*
		 * The rule routes are the HTTP surface of
		 * `verifyPlacementOwnership()`, which looks up the placement, takes its
		 * dashboardId and calls `verifyDashboardOwnership()`. Nothing else in
		 * the app calls either method, so this is where both scenarios are
		 * observable.
		 */
		const copy = await read(carol!.principal, carol!.dashboardId)
		const target = optional(copy.placements)[0]
		const rulesUrl = `${API}/widgets/${target.id}/rules`
		const count = async () =>
			((await (await carol!.principal.api.get(rulesUrl)).json()).rules ?? [])
				.length
		const before = await count()

		const res = await bob!.api.post(rulesUrl, {
			data: {
				ruleType: 'group',
				ruleConfig: { groups: ['admin'] },
				isInclude: true,
			},
		})
		const status = res.status()
		expect(
			status >= 400 && status < 500,
			`a stranger added a rule: ${await res.text()}`,
		).toBe(true)
		// SUSPECTED BUG: the spec requires HTTP 403 for every permission
		// refusal (Non-Functional Requirements, Security). RuleApiController
		// catches the "Access denied" exception and answers
		// ResponseHelper::error()'s default 400 "Operation failed".
		expect.soft(status, 'an ownership refusal must be 403').toBe(403)

		expect(await count(), 'a refused rule must not be stored').toBe(before)

		// Control: the owner may.
		const allowed = await carol!.principal.api.post(rulesUrl, {
			data: {
				ruleType: 'group',
				ruleConfig: { groups: ['admin'] },
				isInclude: true,
			},
		})
		expect(allowed.status(), await allowed.text()).toBe(201)
		expect(await count()).toBe(before + 1)
	})

	// @e2e permissions::user-tries-to-escalate-permission-level
	test('an add-only owner cannot write themselves a full level', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)

		const res = await carol!.principal.api.put(
			`${API}/dashboard/${carol!.dashboardId}`,
			{ data: { name: copy.dashboard.name, permissionLevel: 'full' } },
		)
		expect(res.status(), await res.text()).toBe(200)

		const after = await read(carol!.principal, carol!.dashboardId)
		expect(
			after.dashboard.permissionLevel,
			'the stored level is unchanged',
		).toBe('add_only')
		expect(after.permissionLevel, 'the effective level is unchanged').toBe(
			'add_only',
		)

		// And she still cannot do what full would allow.
		const locked = compulsory(after.placements)[0]
		const remove = await carol!.principal.api.delete(
			`${API}/widgets/${locked.id}`,
		)
		expect(remove.status(), await remove.text()).toBe(403)
	})

	// @e2e permissions::non-compulsory-widgets-on-template-derived-dashboards
	test('on an add-only copy every optional widget can go and no compulsory one can', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)
		const locked = compulsory(copy.placements)
		const loose = optional(copy.placements)
		expect(locked.length).toBeGreaterThan(0)
		expect(loose.length).toBeGreaterThan(0)

		for (const p of loose) {
			const res = await carol!.principal.api.delete(`${API}/widgets/${p.id}`)
			expect(res.status(), `optional ${p.widgetId}: ${await res.text()}`).toBe(
				200,
			)
		}
		for (const p of locked) {
			const res = await carol!.principal.api.delete(`${API}/widgets/${p.id}`)
			expect(
				res.status(),
				`compulsory ${p.widgetId}: ${await res.text()}`,
			).toBe(403)
		}

		const after = await read(carol!.principal, carol!.dashboardId)
		expect(after.placements.map((p) => p.id).sort()).toEqual(
			locked.map((p) => p.id).sort(),
		)
	})

	// ── REQ-PERM-002 / REQ-PERM-004 in the browser ─────────────────────────

	/** Log a member in and open their dashboard. */
	async function openAs(browser: Browser, who: Principal) {
		const session = await loginAs(browser, who.username, who.password)
		await session.page.goto('/index.php/apps/launchpad/')
		await expect(session.page.locator(MORE_MENU)).toBeVisible({
			timeout: 60_000,
		})
		await expect(
			session.page.locator('.launchpad-grid-item').first(),
		).toBeVisible({
			timeout: 30_000,
		})
		return session
	}

	/** The More menu's item ids, read with the menu open. */
	async function moreMenuItems(page: Page): Promise<string[]> {
		await page
			.locator(MORE_MENU)
			.locator('[data-testid="launchpad-more-menu-trigger"]')
			.click()
		await expect(
			page.locator('[data-testid="launchpad-more-menu-panel"]'),
		).toBeVisible()
		const ids = await page
			.locator('[data-testid^="launchpad-more-menu-item-"]')
			.evaluateAll((els) =>
				els.map((el) => el.getAttribute('data-testid') ?? ''),
			)
		await page.keyboard.press('Escape')
		return ids
	}

	/** Open a widget's edit cog and list its menu items by name. */
	async function cogItems(page: Page, placementId: number): Promise<string[]> {
		const trigger = page
			.locator(
				`[data-testid="widget-placement-${placementId}"] .launchpad-widget__cog button`,
			)
			.first()
		await trigger.click()
		// Read only the menu this trigger opened: a menu another widget left
		// open would otherwise answer for this one.
		const menuId = await trigger.getAttribute('aria-controls')
		const menu = menuId ? page.locator(`[id="${menuId}"]`) : page.getByRole('menu')
		const items = menu.getByRole('menuitem')
		await expect(items.first()).toBeVisible({ timeout: 5_000 })
		const names = (await items.allInnerTexts()).map((s) => s.trim())
		// Close it with Escape sent to the menu itself: a page-level Escape
		// can miss it when focus sits elsewhere, and clicking the trigger
		// after an Escape that did land reopens the menu.
		await expect(async () => {
			if (await menu.isVisible()) {
				await menu.press('Escape')
			}
			await expect(menu).toBeHidden({ timeout: 1_000 })
		}).toPass({ timeout: 5_000 })
		return names
	}

	// @e2e permissions::add-only-user-can-move-and-resize-widgets
	test('an add-only owner moves a widget on the grid, and the move is saved', async ({
		browser,
	}) => {
		// A widget of her own at the bottom, so the move has room.
		const added = await bea!.principal.api.post(
			`${API}/dashboard/${bea!.dashboardId}/widgets`,
			{
				data: {
					widgetId: 'text',
					gridX: 0,
					gridY: 60,
					gridWidth: 2,
					gridHeight: 2,
				},
			},
		)
		expect(added.status(), await added.text()).toBe(201)
		const placementId = Number((await added.json()).id)

		const { context, page } = await openAs(browser, bea!.principal)
		try {
			await enterEditMode(page)
			const item = page.locator(`[gs-id="${placementId}"]`)
			await item.scrollIntoViewIfNeeded()
			await expect(item).toBeVisible({ timeout: 10_000 })
			const startX = parseInt((await item.getAttribute('gs-x')) ?? '0', 10)

			await item.focus()
			await page.keyboard.press('Enter')
			await expect(
				page.locator('[data-testid="widget-context-menu"]'),
			).toBeVisible()
			await page.locator('[data-testid="ctx-move"]').focus()
			await page.keyboard.press('Enter')
			const panel = page.locator('[data-test="widget-move-panel"]')
			await expect(panel).toBeVisible({ timeout: 5_000 })

			const saved = page.waitForResponse(
				(r) =>
					r.request().method() === 'PUT'
					&& r.url().endsWith(`/api/dashboard/${bea!.dashboardId}`),
			)
			await page.keyboard.press('ArrowRight')
			await page.keyboard.press('ArrowRight')
			await page.keyboard.press('Enter')
			await expect(panel).not.toBeVisible({ timeout: 5_000 })
			expect(
				(await saved).status(),
				'the grid must be allowed to save the move',
			).toBe(200)

			const movedX = parseInt((await item.getAttribute('gs-x')) ?? '0', 10)
			expect(movedX, 'the grid must allow the move').not.toBe(startX)

			const stored = (
				await read(bea!.principal, bea!.dashboardId)
			).placements.find((p) => p.id === placementId)
			expect(Number(stored?.gridX), 'the new position must be persisted').toBe(
				movedX,
			)
		} finally {
			await context.close()
		}
	})

	// @e2e permissions::add-only-user-cannot-remove-compulsory-widgets-via-ui
	test('in edit mode an add-only owner is not offered Delete on a compulsory widget', async ({
		browser,
	}) => {
		const copy = await read(bea!.principal, bea!.dashboardId)
		expect(copy.permissionLevel).toBe('add_only')
		const locked = compulsory(copy.placements)[0]
		const loose = optional(copy.placements).find(
			(p) => !p.widgetId.startsWith('tile'),
		)
		expect(locked).toBeTruthy()
		expect(loose).toBeTruthy()

		const { context, page } = await openAs(browser, bea!.principal)
		try {
			await enterEditMode(page)

			// Control: a non-compulsory widget offers Delete, so the locator
			// below finds the option when it is there.
			expect(await cogItems(page, loose!.id)).toContain('Delete widget')

			// The spec says the option MUST NOT be available: WidgetEditMenu
			// and WidgetContextMenu leave Delete/Remove out through
			// Views.vue's isRemovablePlacement().
			expect
				.soft(await cogItems(page, locked.id), 'compulsory widget cog')
				.not.toContain('Delete widget')

			await page
				.locator(`.launchpad-grid-item[data-placement-id="${locked.id}"]`)
				.click({ button: 'right' })
			await expect(
				page.locator('[data-testid="widget-context-menu"]'),
			).toBeVisible()
			await expect(
				page.locator('[data-testid="ctx-remove"]'),
				'compulsory widget context menu offers Remove',
			).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// ── REQ-PERM-001 / REQ-PERM-007 on a dashboard the user owns ──────────

	// @e2e permissions::view-only-user-sees-the-dashboard
	test('a view-only owner sees every widget, in view mode, with no Edit', async ({
		browser,
	}) => {
		const copy = await read(dave!.principal, dave!.dashboardId)
		expect(copy.permissionLevel).toBe('view_only')
		expect(copy.placements.length).toBeGreaterThan(0)

		// A list or number whose register is not installed here hides itself
		// by design (REQ-TMPL-022): the template's dossiq tile on this stack.
		const shown = copy.placements.filter(
			(p) => !(['object-list', 'stat'].includes(p.widgetId) && p.content?.hideWhenUnavailable === true),
		)
		expect(shown.length).toBeGreaterThan(0)

		const { context, page } = await openAs(browser, dave!.principal)
		try {
			for (const p of shown) {
				const cell = page.locator(
					`.launchpad-grid-item[data-placement-id="${p.id}"]`,
				)
				await expect(cell, `${p.widgetId} must be rendered`).toHaveCount(1)
				await expect(
					cell.locator('.launchpad-widget'),
					`${p.widgetId} must render its widget`,
				).toHaveCount(1)
			}
			await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)

			const items = await moreMenuItems(page)
			// The menu itself rendered: an item every viewer gets is there.
			expect(items).toContain('launchpad-more-menu-item-set-default')
			expect(items, 'no Edit button for a view-only dashboard').not.toContain(
				'launchpad-more-menu-item-edit',
			)
		} finally {
			await context.close()
		}
	})

	// @e2e permissions::view-only-dashboard-hides-all-editing-ui
	test('a view-only owner is offered none of the editing controls', async ({
		browser,
	}) => {
		const copy = await read(dave!.principal, dave!.dashboardId)
		expect(copy.permissionLevel).toBe('view_only')

		const { context, page } = await openAs(browser, dave!.principal)
		try {
			const items = await moreMenuItems(page)
			expect(items).toContain('launchpad-more-menu-item-set-default')
			for (const hidden of ['edit', 'add-widget']) {
				expect(items, `${hidden} must not be offered`).not.toContain(
					`launchpad-more-menu-item-${hidden}`,
				)
			}

			// No per-widget edit cog, no editing state, no context menu.
			await expect(
				page.locator('[data-testid="cn-widget-edit-cog"]'),
			).toHaveCount(0)
			await expect(page.locator('.launchpad-widget--editing')).toHaveCount(0)
			await page
				.locator(
					`.launchpad-grid-item[data-placement-id="${copy.placements[0].id}"]`,
				)
				.click({ button: 'right' })
			await expect(
				page.locator('[data-testid="widget-context-menu"]'),
			).toHaveCount(0)

			// No drag or resize handles.
			await expect(page.locator('.ui-resizable-handle:visible')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e permissions::view-only-user-can-edit-dashboard-name
	test('a view-only owner can rename their dashboard, but not add to it', async () => {
		const name = `E2E Perm renamed ${STAMP}`
		const res = await dave!.principal.api.put(
			`${API}/dashboard/${dave!.dashboardId}`,
			{
				data: { name },
			},
		)
		expect(res.status(), await res.text()).toBe(200)
		const after = await read(dave!.principal, dave!.dashboardId)
		expect(after.dashboard.name).toBe(name)
		expect(after.permissionLevel).toBe('view_only')

		// Contrast: the level still holds for widgets.
		const add = await dave!.principal.api.post(
			`${API}/dashboard/${dave!.dashboardId}/widgets`,
			{
				data: {
					widgetId: 'text',
					gridX: 0,
					gridY: 40,
					gridWidth: 2,
					gridHeight: 2,
				},
			},
		)
		expect(add.status(), await add.text()).toBe(403)
	})

	// @e2e permissions::metadata-editing-separate-from-widget-editing
	test('a view-only owner may change the description but not the layout', async () => {
		const before = await read(dave!.principal, dave!.dashboardId)
		const p = before.placements[0]

		const meta = await dave!.principal.api.put(
			`${API}/dashboard/${dave!.dashboardId}`,
			{
				data: { description: `described ${STAMP}` },
			},
		)
		expect(meta.status(), await meta.text()).toBe(200)

		const layout = await dave!.principal.api.put(
			`${API}/dashboard/${dave!.dashboardId}`,
			{
				data: {
					name: `E2E Perm layout ${STAMP}`,
					placements: [
						{
							id: p.id,
							gridX: p.gridX + 1,
							gridY: p.gridY,
							gridWidth: p.gridWidth,
							gridHeight: p.gridHeight,
						},
					],
				},
			},
		)
		expect(layout.status(), await layout.text()).toBe(403)

		const after = await read(dave!.principal, dave!.dashboardId)
		expect(after.dashboard.description).toBe(`described ${STAMP}`)
		expect(after.dashboard.name, 'a refused layout update writes nothing').toBe(
			before.dashboard.name,
		)
		const moved = after.placements.find((q) => q.id === p.id)
		expect(Number(moved?.gridX)).toBe(Number(p.gridX))
	})

	// @e2e permissions::view-only-user-can-delete-the-dashboard
	test('a view-only owner can delete their dashboard', async () => {
		expect(
			(await read(dave!.principal, dave!.dashboardId)).permissionLevel,
		).toBe('view_only')

		const res = await dave!.principal.api.delete(
			`${API}/dashboard/${dave!.dashboardId}`,
		)
		expect(res.status(), await res.text()).toBe(200)

		const gone = await dave!.principal.api.get(
			`${API}/dashboard/${dave!.dashboardId}`,
		)
		expect(gone.status()).toBe(404)
	})

	// ── REQ-PERM-008 / REQ-PERM-003: the admin changes the template ───────

	// @e2e permissions::admin-template-changes-propagate-dynamically
	test('raising the template to full raises every copy at once', async () => {
		for (const m of [carol!, bea!]) {
			expect((await read(m.principal, m.dashboardId)).permissionLevel).toBe(
				'add_only',
			)
		}

		await setTemplateLevel(addTemplate!, 'full')

		for (const m of [carol!, bea!]) {
			const after = await read(m.principal, m.dashboardId)
			expect(
				after.permissionLevel,
				`${m.principal.username} resolves full`,
			).toBe('full')
			// Nothing was copied or migrated: the copies' own column is as it was.
			expect(after.dashboard.permissionLevel).toBe('add_only')
			expect(after.dashboard.basedOnTemplate).toBe(addTemplate)
		}
	})

	// @e2e permissions::full-permission-user-can-remove-compulsory-widgets
	test('at full, an owner can delete a compulsory widget', async () => {
		const copy = await read(carol!.principal, carol!.dashboardId)
		expect(copy.permissionLevel).toBe('full')
		const locked = compulsory(copy.placements)
		expect(
			locked.length,
			'two compulsory widgets: one to delete, one for later',
		).toBeGreaterThan(1)

		const res = await carol!.principal.api.delete(
			`${API}/widgets/${locked[0].id}`,
		)
		expect(res.status(), await res.text()).toBe(200)
		expect(
			(await read(carol!.principal, carol!.dashboardId)).placements.some(
				(p) => p.id === locked[0].id,
			),
		).toBe(false)
	})

	// @e2e permissions::full-permission-user-sees-all-editing-ui
	test('at full, Edit is offered and every widget, compulsory too, offers Delete', async ({
		browser,
	}) => {
		const copy = await read(bea!.principal, bea!.dashboardId)
		expect(copy.permissionLevel).toBe('full')
		const locked = compulsory(copy.placements)[0]
		expect(locked).toBeTruthy()

		const { context, page } = await openAs(browser, bea!.principal)
		try {
			const items = await moreMenuItems(page)
			expect(items).toContain('launchpad-more-menu-item-edit')
			expect(items).toContain('launchpad-more-menu-item-add-widget')

			await enterEditMode(page)
			// Every widget has its edit cog in edit mode.
			const widgets = await page
				.locator('.launchpad-grid-item .launchpad-widget')
				.count()
			expect(widgets).toBeGreaterThan(0)
			await expect(
				page.locator('.launchpad-grid-item .launchpad-widget__cog'),
			).toHaveCount(widgets)

			const cog = await cogItems(page, locked.id)
			expect(cog).toContain('Edit widget')
			expect(cog).toContain('Delete widget')

			await page
				.locator(`.launchpad-grid-item[data-placement-id="${locked.id}"]`)
				.click({ button: 'right' })
			await expect(
				page.locator('[data-testid="widget-context-menu"]'),
			).toBeVisible()
			for (const option of [
				'ctx-edit',
				'ctx-remove',
				'ctx-visibility-rules',
			]) {
				await expect(page.locator(`[data-testid="${option}"]`)).toBeVisible()
			}
		} finally {
			await context.close()
		}
	})

	// @e2e permissions::template-deleted-fallback-to-dashboard-level
	test('when the template is deleted, the copy falls back to its own level', async () => {
		// The template is at full now; the copy's own column says add_only.
		expect(
			(await read(carol!.principal, carol!.dashboardId)).permissionLevel,
		).toBe('full')

		const del = await admin.delete(`${API}/admin/templates/${addTemplate}`)
		expect(del.status(), await del.text()).toBe(200)
		const deletedId = addTemplate
		addTemplate = null

		const after = await read(carol!.principal, carol!.dashboardId)
		expect(
			after.dashboard.basedOnTemplate,
			'the copy still names its template',
		).toBe(deletedId)
		expect(after.permissionLevel, 'falls back to the copy own add_only').toBe(
			'add_only',
		)

		// And add_only is what is enforced: the remaining compulsory widget stays.
		const locked = compulsory(after.placements)[0]
		expect(locked).toBeTruthy()
		const res = await carol!.principal.api.delete(`${API}/widgets/${locked.id}`)
		expect(res.status(), await res.text()).toBe(403)
	})
})
