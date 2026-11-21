/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Conditional visibility rules: storing, listing, changing and removing them
 * (REQ-VIS-001..004), what happens to them when their placement or dashboard
 * goes (REQ-VIS-011), and the admin overview of rule-bearing widgets
 * (REQ-VIS-012).
 *
 * How a rule then decides what one principal sees and another does not
 * (REQ-VIS-005..010) is in conditional-visibility-evaluation.spec.ts.
 *
 * WHO ACTS
 * ========
 * `alice` and `bob` are throwaway ordinary accounts, each owning a dashboard
 * of their own. alice is the owner; bob is the least privileged principal who
 * should be refused on alice's placements. The rule actions are granted to
 * every logged-in user for the run (see support/visibilityRules.ts for why),
 * so a refusal of bob comes from the ownership guard and not from the action
 * matrix. Every refusal re-reads as alice to prove nothing was written, and
 * proves alice herself is allowed in the same test.
 *
 * SUSPECTED BUGS THIS FILE RUNS INTO (each marked where it bites)
 * ===============================================================
 *  - Refusals on another user's placement answer 400 `Operation failed`, not
 *    403: `verifyDashboardOwnership()` throws a plain `Exception` and the
 *    controller maps every exception through `ResponseHelper::error()`, whose
 *    default status is 400 (lib/Service/PermissionService.php:617,
 *    lib/Controller/ResponseHelper.php:102).
 *  - An exclude rule cannot be stored on PostgreSQL: `is_include` is a
 *    smallint column and the entity binds it as a boolean (launchpad#96).
 *  - Rules are not deleted with their placement or dashboard
 *    (lib/Service/PlacementService.php:318, no foreign key in
 *    lib/Migration/RulesTableBuilder.php).
 *  - An unknown `ruleType` is accepted (no validation in RuleApiController).
 *  - The attribute `language` is not resolved: UserAttributeResolver knows
 *    `locale`, `email`, `displayName` and `quota` only.
 *  - With the shipped action matrix an ordinary owner cannot add a rule at all.
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { ActionMatrix, RuleInput } from './support/visibilityRules.ts'

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'
import {
	addTile,
	grantRuleActionsToAll,
	orphanedRuleCount,
	postRule,
	previewVisible,
	readActionMatrix,
	readPlacements,
	readRules,
	seedOwnDashboard,
	seedRule,
	setAccountField,
	shippedRuleActions,
	writeActionMatrix,
} from './support/visibilityRules.ts'

const STAMP = `${Date.now()}`
const YEAR = new Date().getUTCFullYear()
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad`

/** A date rule that matches today, whatever today is. */
const MATCHES_TODAY: RuleInput = {
	ruleType: 'date',
	ruleConfig: { startDate: `${YEAR - 1}-01-01` },
	isInclude: true,
}

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let aliceDash = { id: 0, uuid: '' }
let bobDash = { id: 0, uuid: '' }
let priorMatrix: ActionMatrix | null = null
const extraDashboards: Array<{ api: APIRequestContext; id: number }> = []
let row = 0

/** A fresh tile on alice's dashboard, so each test starts with no rules. */
async function alicePlacement(label: string): Promise<number> {
	row += 1
	return addTile(alice!.api, aliceDash.id, `[cv ${row}] ${label} ${STAMP}`, row)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	await allowPersonalDashboards(admin, true)
	priorMatrix = await grantRuleActionsToAll(admin)

	alice = await createPrincipal(admin, 'e2e-cv-alice')
	bob = await createPrincipal(admin, 'e2e-cv-bob')
	await assertActingAs(alice.api, alice.username)
	await assertActingAs(bob.api, bob.username)

	aliceDash = await seedOwnDashboard(alice, `E2E CV alice ${STAMP}`)
	bobDash = await seedOwnDashboard(bob, `E2E CV bob ${STAMP}`)
})

test.afterAll(async () => {
	for (const extra of extraDashboards) {
		await extra.api.delete(`${API}/dashboard/${extra.id}`)
	}
	if (alice && aliceDash.id) {
		await alice.api.delete(`${API}/dashboard/${aliceDash.id}`)
	}
	if (bob && bobDash.id) {
		await bob.api.delete(`${API}/dashboard/${bobDash.id}`)
	}
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	if (priorMatrix !== null) {
		await writeActionMatrix(admin, priorMatrix)
	}
	await admin.dispose()
})

test.describe('REQ-VIS-001 create a conditional rule', () => {
	// @e2e conditional-visibility::create-a-group-based-inclusion-rule
	test('an owner stores a group inclusion rule and gets the full rule back with 201', async () => {
		const pid = await alicePlacement('group include')
		const before = (await readPlacements(alice!.api, aliceDash.id)).find(
			(p) => Number(p.id) === pid,
		)
		expect(Number(before?.isVisible), 'CONTROL: a new tile starts visible').toBe(
			1,
		)

		const res = await postRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing', 'sales'] },
			isInclude: true,
		})
		expect(res.status(), await res.text()).toBe(201)
		const rule = await res.json()
		expect(Number(rule.id)).toBeGreaterThan(0)
		expect(Number(rule.widgetPlacementId)).toBe(pid)
		expect(rule.ruleType).toBe('group')
		expect(rule.ruleConfig).toEqual({ groups: ['marketing', 'sales'] })
		expect(rule.isInclude).toBe(true)
		expect(rule.createdAt, 'the full rule object carries createdAt').toBeTruthy()

		const stored = await readRules(alice!.api, pid)
		expect(stored.rules.map((r) => Number(r.id))).toEqual([Number(rule.id)])
		expect(Number(stored.rules[0].widgetPlacementId)).toBe(pid)

		// NOTE in the scenario: adding a rule leaves the placement's flag alone.
		const after = (await readPlacements(alice!.api, aliceDash.id)).find(
			(p) => Number(p.id) === pid,
		)
		expect(Number(after?.isVisible)).toBe(1)
	})

	// @e2e conditional-visibility::create-a-group-based-inclusion-rule
	test('with the action matrix as shipped, an ordinary owner may still add a rule to her own placement', async () => {
		// lib/actions.seed.json ships `rule.add-rule` open to every user; the
		// route checks placement ownership (REQ-VIS-001).
		const pid = await alicePlacement('shipped matrix')
		const granted = await readActionMatrix(admin)
		await writeActionMatrix(admin, { ...granted, ...shippedRuleActions() })
		try {
			const res = await postRule(alice!.api, pid, {
				ruleType: 'group',
				ruleConfig: { groups: ['marketing'] },
				isInclude: true,
			})
			expect(
				res.status(),
				`an owner adding a rule under the shipped matrix: ${await res.text()}`,
			).toBe(201)
		} finally {
			await writeActionMatrix(admin, granted)
		}
		expect(
			(await readRules(alice!.api, pid)).rules,
			'the rule the owner added must be stored',
		).toHaveLength(1)
	})

	// @e2e conditional-visibility::update-rule-configuration
	// @e2e conditional-visibility::delete-a-rule
	test('with the action matrix as shipped, an ordinary owner may change and remove her own rule', async () => {
		const pid = await alicePlacement('shipped matrix edit')
		const granted = await readActionMatrix(admin)
		await writeActionMatrix(admin, { ...granted, ...shippedRuleActions() })
		try {
			const added = await postRule(alice!.api, pid, {
				ruleType: 'group',
				ruleConfig: { groups: ['marketing'] },
				isInclude: true,
			})
			expect(added.status(), await added.text()).toBe(201)
			const ruleId = Number((await added.json()).id)

			const updated = await alice!.api.put(`/index.php/apps/launchpad/api/rules/${ruleId}`, {
				data: { ruleConfig: { groups: ['marketing', 'sales'] } },
			})
			expect(updated.status(), await updated.text()).toBe(200)
			expect((await readRules(alice!.api, pid)).rules[0].ruleConfig).toEqual({ groups: ['marketing', 'sales'] })

			const removed = await alice!.api.delete(`/index.php/apps/launchpad/api/rules/${ruleId}`)
			expect(removed.status(), await removed.text()).toBeLessThan(300)
		} finally {
			await writeActionMatrix(admin, granted)
		}
		expect((await readRules(alice!.api, pid)).rules, 'the removed rule is gone').toHaveLength(0)
	})

	// @e2e conditional-visibility::create-a-time-based-exclusion-rule
	test('a time exclusion rule is stored, and hides the widget only while its window matches', async () => {
		const pid = await alicePlacement('time exclude')
		// SUSPECTED BUG (launchpad#96): on PostgreSQL an isInclude:false body
		// is refused with 400 "Operation failed".
		const res = await postRule(alice!.api, pid, {
			ruleType: 'time',
			ruleConfig: { startTime: '18:00', endTime: '08:00' },
			isInclude: false,
		})
		expect(res.status(), await res.text()).toBe(201)
		const rule = await res.json()
		expect(rule.ruleType).toBe('time')
		expect(rule.ruleConfig).toEqual({ startTime: '18:00', endTime: '08:00' })
		expect(rule.isInclude, 'isInclude:false is an exclusion rule').toBe(false)

		// 18:00-08:00 never matches under the string comparison the NOTE
		// describes, so the exclusion does not hide anything yet...
		expect((await readRules(alice!.api, pid)).isVisible).toBe(true)

		// ...and once its window covers every minute of the day it matches, and
		// because it is an exclusion the widget is hidden.
		const widen = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: { ruleConfig: { startTime: '00:00', endTime: '23:59' } },
		})
		expect(widen.status(), await widen.text()).toBe(200)
		expect((await readRules(alice!.api, pid)).isVisible).toBe(false)
	})

	// @e2e conditional-visibility::create-a-date-based-inclusion-rule
	test('a date inclusion rule for December 2026 is stored and shows the widget only in December 2026', async () => {
		const pid = await alicePlacement('date include')
		const res = await postRule(alice!.api, pid, {
			ruleType: 'date',
			ruleConfig: { startDate: '2026-12-01', endDate: '2026-12-31' },
			isInclude: true,
		})
		expect(res.status(), await res.text()).toBe(201)

		const [stored] = (await readRules(alice!.api, pid)).rules
		expect(stored.ruleConfig).toEqual({
			startDate: '2026-12-01',
			endDate: '2026-12-31',
		})
		const asStored: RuleInput = {
			ruleType: stored.ruleType,
			ruleConfig: stored.ruleConfig,
			isInclude: stored.isInclude,
		}
		expect(
			await previewVisible(alice!.api, [asStored], {
				datetime: '2026-12-15T12:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(alice!.api, [asStored], {
				datetime: '2026-11-15T12:00:00',
			}),
		).toBe(false)
		expect(
			await previewVisible(alice!.api, [asStored], {
				datetime: '2027-01-01T12:00:00',
			}),
		).toBe(false)
	})

	// @e2e conditional-visibility::create-an-attribute-based-rule
	test('an attribute rule on language "nl" is stored and shows the widget to a Dutch user only', async () => {
		await setAccountField(admin, alice!.username, 'language', 'nl')
		await setAccountField(admin, bob!.username, 'language', 'en')
		const rule: RuleInput = {
			ruleType: 'attribute',
			ruleConfig: { attribute: 'language', operator: 'equals', value: 'nl' },
			isInclude: true,
		}

		const alicePid = await alicePlacement('attribute nl')
		const created = await postRule(alice!.api, alicePid, rule)
		expect(created.status(), await created.text()).toBe(201)
		expect((await created.json()).ruleConfig).toEqual(rule.ruleConfig)

		row += 1
		const bobPid = await addTile(
			bob!.api,
			bobDash.id,
			`[cv ${row}] attribute nl ${STAMP}`,
			row,
		)
		await seedRule(bob!.api, bobPid, rule)

		expect(
			(await readRules(bob!.api, bobPid)).isVisible,
			'bob speaks English: hidden',
		).toBe(false)
		// SUSPECTED BUG: UserAttributeResolver::getUserAttributeValue() has no
		// `language` branch (it reads the language under the name `locale`),
		// so the rule never matches and the Dutch user is refused too.
		expect(
			(await readRules(alice!.api, alicePid)).isVisible,
			'alice speaks Dutch: shown',
		).toBe(true)
	})

	// @e2e conditional-visibility::create-rule-with-invalid-ruletype
	test('an unknown ruleType is refused with 400 and nothing is stored', async () => {
		const pid = await alicePlacement('invalid type')
		// SUSPECTED BUG: no ruleType validation in RuleApiController::addRule();
		// "weather" is stored with 201.
		const res = await postRule(alice!.api, pid, {
			ruleType: 'weather',
			ruleConfig: {},
			isInclude: true,
		})
		const text = await res.text()
		expect(res.status(), text).toBe(400)
		expect(text, 'the error must say the ruleType is the problem').toMatch(
			/ruleType/i,
		)
		expect(
			(await readRules(alice!.api, pid)).rules.filter(
				(r) => r.ruleType === 'weather',
			),
			'the refused rule must not be stored',
		).toHaveLength(0)

		for (const ruleType of ['group', 'time', 'date', 'attribute']) {
			const ok = await postRule(alice!.api, pid, {
				ruleType,
				ruleConfig: {},
				isInclude: true,
			})
			expect(
				ok.status(),
				`${ruleType} must be accepted: ${await ok.text()}`,
			).toBe(201)
		}
		expect(
			(await readRules(alice!.api, pid)).rules.map((r) => r.ruleType).sort(),
		).toEqual(['attribute', 'date', 'group', 'time'])
	})

	// @e2e conditional-visibility::create-rule-on-another-users-placement
	test("another user adding a rule to the owner's placement is refused with 403", async () => {
		const pid = await alicePlacement('foreign create')
		const probe = await postRule(bob!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['bob-was-here'] },
			isInclude: true,
		})
		// SUSPECTED BUG: answered 400 "Operation failed" (see the file header).
		expect(probe.status(), await probe.text()).toBe(403)
		expect(
			(await readRules(alice!.api, pid)).rules,
			'nothing was written',
		).toHaveLength(0)

		await seedRule(alice!.api, pid, MATCHES_TODAY)
		expect((await readRules(alice!.api, pid)).rules).toHaveLength(1)
	})
})

test.describe('REQ-VIS-002 list conditional rules', () => {
	// @e2e conditional-visibility::list-rules-for-a-placement-with-multiple-rules
	test('all three rules come back with 200, each with every field', async () => {
		const pid = await alicePlacement('list three')
		await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		await seedRule(alice!.api, pid, {
			ruleType: 'time',
			ruleConfig: { startTime: '09:00', endTime: '17:00' },
			isInclude: true,
		})
		// SUSPECTED BUG (launchpad#96): the exclude rule cannot be stored on PostgreSQL.
		await seedRule(alice!.api, pid, {
			ruleType: 'date',
			ruleConfig: { startDate: '2026-07-01', endDate: '2026-07-31' },
			isInclude: false,
		})

		const res = await alice!.api.get(`${API}/widgets/${pid}/rules`)
		expect(res.status(), await res.text()).toBe(200)
		const rules = (await res.json()).rules as Array<Record<string, unknown>>
		expect(rules).toHaveLength(3)
		for (const rule of rules) {
			for (const key of [
				'id',
				'widgetPlacementId',
				'ruleType',
				'ruleConfig',
				'isInclude',
				'createdAt',
			]) {
				expect(
					rule,
					`rule ${JSON.stringify(rule)} lacks ${key}`,
				).toHaveProperty(key)
			}
			expect(Number(rule.widgetPlacementId)).toBe(pid)
		}
		expect(rules.map((r) => `${r.ruleType}:${r.isInclude}`).sort()).toEqual([
			'date:false',
			'group:true',
			'time:true',
		])
	})

	// @e2e conditional-visibility::list-rules-for-a-placement-with-no-rules
	test('a placement without rules lists an empty array with 200', async () => {
		const pid = await alicePlacement('list none')
		const res = await alice!.api.get(`${API}/widgets/${pid}/rules`)
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).rules).toEqual([])
	})

	// @e2e conditional-visibility::list-rules-for-another-users-placement
	test("another user listing the owner's rules is refused with 403 and sees none", async () => {
		const pid = await alicePlacement('foreign list')
		await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: [`secret-${STAMP}`] },
			isInclude: true,
		})

		const probe = await bob!.api.get(`${API}/widgets/${pid}/rules`)
		const text = await probe.text()
		// SUSPECTED BUG: answered 400 "Operation failed" (see the file header).
		expect(probe.status(), text).toBe(403)
		expect(text, 'no rule data may leak').not.toContain(`secret-${STAMP}`)

		const own = await readRules(alice!.api, pid)
		expect(own.rules).toHaveLength(1)
	})
})

test.describe('REQ-VIS-003 update a conditional rule', () => {
	// @e2e conditional-visibility::update-rule-configuration
	test('PUT replaces the ruleConfig and answers 200 with the updated rule', async () => {
		const pid = await alicePlacement('update config')
		const rule = await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		const res = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: { ruleConfig: { groups: ['marketing', 'sales', 'management'] } },
		})
		expect(res.status(), await res.text()).toBe(200)
		const updated = await res.json()
		expect(Number(updated.id)).toBe(Number(rule.id))
		expect(updated.ruleConfig).toEqual({
			groups: ['marketing', 'sales', 'management'],
		})

		const [stored] = (await readRules(alice!.api, pid)).rules
		expect(stored.ruleConfig).toEqual({
			groups: ['marketing', 'sales', 'management'],
		})
	})

	// @e2e conditional-visibility::change-rule-from-inclusion-to-exclusion
	test('turning a matching inclusion rule into an exclusion hides the widget', async () => {
		const pid = await alicePlacement('include to exclude')
		const rule = await seedRule(alice!.api, pid, MATCHES_TODAY)
		expect(
			(await readRules(alice!.api, pid)).isVisible,
			'CONTROL: the matching inclusion rule shows the widget',
		).toBe(true)

		// SUSPECTED BUG (launchpad#96): refused with 400 on PostgreSQL.
		const res = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: { isInclude: false },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).isInclude).toBe(false)

		const after = await readRules(alice!.api, pid)
		expect(after.rules[0].isInclude).toBe(false)
		expect(after.isVisible, 'the rule still matches, and now hides').toBe(false)
	})

	// @e2e conditional-visibility::update-rule-type
	test('changing the ruleType replaces the old config instead of merging it', async () => {
		const pid = await alicePlacement('update type')
		const rule = await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		const res = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: {
				ruleType: 'time',
				ruleConfig: { startTime: '09:00', endTime: '17:00' },
			},
		})
		expect(res.status(), await res.text()).toBe(200)
		const updated = await res.json()
		expect(updated.ruleType).toBe('time')
		expect(updated.ruleConfig, 'no `groups` may survive the change').toEqual({
			startTime: '09:00',
			endTime: '17:00',
		})

		const [stored] = (await readRules(alice!.api, pid)).rules
		expect(stored.ruleType).toBe('time')
		expect(stored.ruleConfig).toEqual({ startTime: '09:00', endTime: '17:00' })
	})

	// @e2e conditional-visibility::update-another-users-rule
	test("another user updating the owner's rule is refused with 403 and changes nothing", async () => {
		const pid = await alicePlacement('foreign update')
		const rule = await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		const probe = await bob!.api.put(`${API}/rules/${rule.id}`, {
			data: { ruleConfig: { groups: ['bob-was-here'] } },
		})
		// SUSPECTED BUG: answered 400 "Operation failed" (see the file header).
		expect(probe.status(), await probe.text()).toBe(403)
		const [unchanged] = (await readRules(alice!.api, pid)).rules
		expect(unchanged.ruleConfig, 'nothing was written').toEqual({
			groups: ['marketing'],
		})

		const own = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: { ruleConfig: { groups: ['sales'] } },
		})
		expect(own.status(), await own.text()).toBe(200)
		expect((await readRules(alice!.api, pid)).rules[0].ruleConfig).toEqual({
			groups: ['sales'],
		})
	})

	// @e2e conditional-visibility::partial-update-preserves-unspecified-fields
	test('a PUT carrying only isInclude leaves ruleType and ruleConfig alone', async () => {
		const pid = await alicePlacement('partial update')
		const rule = await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		// SUSPECTED BUG (launchpad#96): refused with 400 on PostgreSQL.
		const res = await alice!.api.put(`${API}/rules/${rule.id}`, {
			data: { isInclude: false },
		})
		expect(res.status(), await res.text()).toBe(200)

		const [stored] = (await readRules(alice!.api, pid)).rules
		expect(stored.isInclude).toBe(false)
		expect(stored.ruleType).toBe('group')
		expect(stored.ruleConfig).toEqual({ groups: ['marketing'] })
	})
})

test.describe('REQ-VIS-004 delete a conditional rule', () => {
	// @e2e conditional-visibility::delete-a-rule
	test('deleting one of three rules answers {"status":"ok"} and leaves the other two', async () => {
		const pid = await alicePlacement('delete one')
		const ids: number[] = []
		for (const groups of [['a'], ['b'], ['c']]) {
			ids.push(
				Number(
					(
						await seedRule(alice!.api, pid, {
							ruleType: 'group',
							ruleConfig: { groups },
							isInclude: true,
						})
					).id,
				),
			)
		}
		const res = await alice!.api.delete(`${API}/rules/${ids[1]}`)
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ status: 'ok' })

		const left = (await readRules(alice!.api, pid)).rules
			.map((r) => Number(r.id))
			.sort()
		expect(left).toEqual([ids[0], ids[2]].sort())
	})

	// @e2e conditional-visibility::delete-the-last-rule-on-a-placement
	test('deleting the only rule leaves isVisible at 1 and the widget shown', async () => {
		const pid = await alicePlacement('delete last')
		// A rule alice cannot match, so deleting it visibly changes the verdict.
		const rule = await seedRule(alice!.api, pid, {
			ruleType: 'group',
			ruleConfig: { groups: [`nobody-${STAMP}`] },
			isInclude: true,
		})
		expect(
			(await readRules(alice!.api, pid)).isVisible,
			'CONTROL: hidden while the rule exists',
		).toBe(false)

		const res = await alice!.api.delete(`${API}/rules/${rule.id}`)
		expect(res.status(), await res.text()).toBe(200)

		const after = await readRules(alice!.api, pid)
		expect(after.rules).toEqual([])
		expect(after.isVisible, 'no rules means always shown').toBe(true)
		const placement = (await readPlacements(alice!.api, aliceDash.id)).find(
			(p) => Number(p.id) === pid,
		)
		expect(
			Number(placement?.isVisible),
			'the placement flag is not touched',
		).toBe(1)
	})

	// @e2e conditional-visibility::delete-another-users-rule
	test("another user deleting the owner's rule is refused with 403 and the rule stays", async () => {
		const pid = await alicePlacement('foreign delete')
		const rule = await seedRule(alice!.api, pid, MATCHES_TODAY)

		const probe = await bob!.api.delete(`${API}/rules/${rule.id}`)
		// SUSPECTED BUG: answered 400 "Operation failed" (see the file header).
		expect(probe.status(), await probe.text()).toBe(403)
		expect(
			(await readRules(alice!.api, pid)).rules.map((r) => Number(r.id)),
			'nothing was deleted',
		).toEqual([Number(rule.id)])

		const own = await alice!.api.delete(`${API}/rules/${rule.id}`)
		expect(own.status(), await own.text()).toBe(200)
		expect((await readRules(alice!.api, pid)).rules).toEqual([])
	})
})

test.describe('REQ-VIS-011 rules go with their placement', () => {
	// @e2e conditional-visibility::delete-placement-cascades-to-rules
	test('deleting a placement deletes its five rules and leaves no orphans', async () => {
		const pid = await alicePlacement('cascade placement')
		for (let i = 0; i < 5; i++) {
			await seedRule(alice!.api, pid, {
				ruleType: 'group',
				ruleConfig: { groups: [`g${i}`] },
				isInclude: true,
			})
		}
		expect(
			(await readRules(alice!.api, pid)).rules,
			'CONTROL: five rules exist',
		).toHaveLength(5)
		const orphansBefore = await orphanedRuleCount(admin)

		const res = await alice!.api.delete(`${API}/widgets/${pid}`)
		expect(res.status(), await res.text()).toBeLessThan(300)
		expect(
			(await readPlacements(alice!.api, aliceDash.id)).some(
				(p) => Number(p.id) === pid,
			),
			'CONTROL: the placement is gone',
		).toBe(false)

		// SUSPECTED BUG: PlacementService::removePlacement() deletes the
		// placement row only, and the rules table has no foreign key.
		expect(
			await orphanedRuleCount(admin),
			'the five rules must be deleted with their placement',
		).toBe(orphansBefore)
	})

	// @e2e conditional-visibility::delete-dashboard-cascades-to-placements-and-rules
	test('deleting a dashboard deletes its three placements and their six rules', async () => {
		const created = await alice!.api.post(`${API}/dashboard`, {
			data: { name: `E2E CV cascade ${STAMP}` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dashId = Number((body.dashboard ?? body).id)
		extraDashboards.push({ api: alice!.api, id: dashId })

		const pids: number[] = []
		for (let i = 0; i < 3; i++) {
			const pid = await addTile(
				alice!.api,
				dashId,
				`[cascade ${i}] ${STAMP}`,
				i,
			)
			pids.push(pid)
			for (let j = 0; j < 2; j++) {
				await seedRule(alice!.api, pid, {
					ruleType: 'group',
					ruleConfig: { groups: [`g${i}${j}`] },
					isInclude: true,
				})
			}
		}
		const orphansBefore = await orphanedRuleCount(admin)

		const res = await alice!.api.delete(`${API}/dashboard/${dashId}`)
		expect(res.status(), await res.text()).toBeLessThan(300)
		const gone = await alice!.api.get(`${API}/dashboard/${dashId}`)
		expect(
			gone.status(),
			'CONTROL: the dashboard is gone',
		).toBeGreaterThanOrEqual(400)

		// SUSPECTED BUG: DashboardService::deleteDashboard() removes the
		// placements with deleteByDashboardId() and leaves their rules behind.
		expect(
			await orphanedRuleCount(admin),
			'the six rules must be deleted with their dashboard',
		).toBe(orphansBefore)
	})
})

test.describe('REQ-VIS-012 admin overview of widgets with visibility rules', () => {
	test.beforeEach(async ({ page }) => {
		await page
			.context()
			.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	})

	// @e2e conditional-visibility::the-overview-lists-rule-bearing-widgets
	test('the Versioning and audit tab lists all twelve rule-bearing placements with their counts', async ({
		page,
	}) => {
		const name = `E2E CV overview ${STAMP}`
		const created = await alice!.api.post(`${API}/dashboard`, { data: { name } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dashId = Number((body.dashboard ?? body).id)
		extraDashboards.push({ api: alice!.api, id: dashId })

		// Twelve placements; placement i carries (i % 3) + 1 include rules, and
		// every fourth also carries one exclude rule.
		const expected: string[] = []
		for (let i = 0; i < 12; i++) {
			const pid = await addTile(
				alice!.api,
				dashId,
				`[overview ${i}] ${STAMP}`,
				i,
			)
			const includes = (i % 3) + 1
			const excludes = i % 4 === 0 ? 1 : 0
			for (let j = 0; j < includes; j++) {
				await seedRule(alice!.api, pid, { ...MATCHES_TODAY })
			}
			for (let j = 0; j < excludes; j++) {
				// SUSPECTED BUG (launchpad#96): refused with 400 on PostgreSQL.
				await seedRule(alice!.api, pid, {
					...MATCHES_TODAY,
					isInclude: false,
				})
			}
			expected.push(`${includes + excludes}|${includes}|${excludes}`)
		}
		const widgetType = String(
			(await readPlacements(alice!.api, dashId))[0]?.widgetId ?? '',
		)
		expect(widgetType, 'CONTROL: the tiles have a widget type').not.toBe('')

		const api = await admin.get(`${API}/admin/widgets/with-rules`)
		expect(api.status(), await api.text()).toBe(200)
		const apiRows = (
			(await api.json()) as Array<Record<string, unknown>>
		).filter((r) => Number(r.dashboardId) === dashId)
		expect(apiRows).toHaveLength(12)

		await page.goto(SETTINGS_URL)
		const tab = adminTab(page, 'versioning-audit')
		await expect(tab).toBeVisible({ timeout: 20_000 })
		await tab.click()
		const overview = page.locator(
			'[data-test="conditional-visibility-overview"]',
		)
		await expect(
			overview.getByText('Widgets with visibility rules'),
		).toBeVisible({ timeout: 15_000 })

		const rows = overview
			.locator('[data-test="cv-overview-row"]')
			.filter({ hasText: name })
		await expect(rows).toHaveCount(12, { timeout: 15_000 })
		const seen: string[] = []
		for (let i = 0; i < 12; i++) {
			const cells = (await rows.nth(i).locator('td').allInnerTexts()).map(
				(c) => c.trim(),
			)
			expect(
				cells,
				'dashboard, widget type, rules, include, exclude',
			).toHaveLength(5)
			expect(cells[0]).toBe(name)
			expect(cells[1]).toBe(widgetType)
			seen.push(`${cells[2]}|${cells[3]}|${cells[4]}`)
		}
		expect(seen.sort()).toEqual([...expected].sort())
	})

	// @e2e conditional-visibility::no-rules-yet
	test('with no rule anywhere the overview shows "No visibility rules yet", and a table once one exists', async ({
		page,
	}) => {
		/*
		 * The GIVEN is instance-wide ("no widget placement has a conditional
		 * rule"), and this suite shares its instance with every other spec, so
		 * it cannot be made true by deleting other people's rules. The overview's
		 * single read is answered with an empty list instead; that is exactly
		 * what the server returns when no placement has a rule. The second half
		 * of the test drops the stub and proves the same tab shows a real row,
		 * so the empty state is not simply what the tab always shows.
		 */
		const pid = await alicePlacement('overview control')
		await seedRule(alice!.api, pid, MATCHES_TODAY)

		await page.route('**/apps/launchpad/api/admin/widgets/with-rules', (route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: '[]',
			}),
		)
		await page.goto(SETTINGS_URL)
		await adminTab(page, 'versioning-audit').click()
		const overview = page.locator(
			'[data-test="conditional-visibility-overview"]',
		)
		await expect(overview.getByText('No visibility rules yet')).toBeVisible({
			timeout: 15_000,
		})
		await expect(
			overview.locator('[data-test="cv-overview-table"]'),
		).toHaveCount(0)

		await page.unroute('**/apps/launchpad/api/admin/widgets/with-rules')
		await page.reload()
		await adminTab(page, 'versioning-audit').click()
		await expect(
			overview.locator('[data-test="cv-overview-table"]'),
		).toBeVisible({ timeout: 15_000 })
		await expect(overview.getByText('No visibility rules yet')).toHaveCount(0)
	})

	// @e2e conditional-visibility::a-non-administrator-cannot-read-the-overview
	test('an ordinary user reading the overview is refused with 403 and gets no rule data', async () => {
		const pid = await alicePlacement('overview refusal')
		await seedRule(alice!.api, pid, MATCHES_TODAY)

		const probe = await bob!.api.get(`${API}/admin/widgets/with-rules`)
		const text = await probe.text()
		expect(probe.status(), text).toBe(403)
		expect(text, 'no dashboard name may leak').not.toContain(
			`E2E CV alice ${STAMP}`,
		)
		expect(text).not.toContain('ruleCount')

		const allowed = await admin.get(`${API}/admin/widgets/with-rules`)
		expect(allowed.status(), await allowed.text()).toBe(200)
		const rows = (await allowed.json()) as Array<Record<string, unknown>>
		expect(rows.some((r) => Number(r.placementId) === pid)).toBe(true)
	})
})
