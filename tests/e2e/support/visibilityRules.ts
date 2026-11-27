/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the conditional-visibility specs: an owner's own dashboard with
 * tiles on it, the four rule endpoints, the preview endpoint and the orphaned
 * rule count.
 *
 * WHY THE ACTION MATRIX IS OPENED UP FIRST
 * ========================================
 * `lib/actions.seed.json` once shipped `rule.get-rules`, `rule.add-rule`,
 * `rule.update-rule` and `rule.delete-rule` as `["admin"]`; it now opens all
 * four to every user, with the ownership check per placement. With the old matrix a
 * non-admin owner is refused with 403 by the ACTION check, before
 * `PermissionService::verifyPlacementOwnership()` ever runs. Two things follow.
 *
 *  1. Every refusal probed with an ordinary user would read 403 whether or not
 *     the ownership guard works, so it would prove nothing about ownership.
 *  2. No ordinary user could own a rule at all, so nothing about evaluating a
 *     rule for one principal and not another could be observed.
 *
 * So these specs grant the four actions to `@all` (what the matrix calls
 * "every authenticated user") for their own duration and put the previous
 * matrix back afterwards. The shipped default itself is checked by one
 * separate test, which reads it from the seed file rather than restating it.
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'
import type { Principal } from './principals.ts'

import { expect } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { API } from './principals.ts'

/** The four actions the rule endpoints check. */
export const RULE_ACTIONS = [
	'rule.get-rules',
	'rule.add-rule',
	'rule.update-rule',
	'rule.delete-rule',
] as const

export type ActionMatrix = Record<string, string[]>

/** A rule as the API takes it. */
export interface RuleInput {
	ruleType: string
	ruleConfig: Record<string, unknown>
	isInclude: boolean
}

/** A rule as the API returns it. */
export interface StoredRule {
	id: number
	widgetPlacementId: number
	ruleType: string
	ruleConfig: Record<string, unknown>
	isInclude: boolean
	createdAt: string | null
}

/**
 * Read the stored action matrix.
 *
 * @param admin an admin context.
 * @return the matrix as stored.
 */
export async function readActionMatrix(
	admin: APIRequestContext,
): Promise<ActionMatrix> {
	const res = await admin.get(`${API}/admin/action-matrix`)
	expect(res.status(), `reading the action matrix: ${await res.text()}`).toBe(200)
	return ((await res.json()).matrix ?? {}) as ActionMatrix
}

/**
 * Replace the stored action matrix.
 *
 * @param admin an admin context.
 * @param matrix the whole matrix to store.
 * @return nothing; fails the test when the write is refused.
 */
export async function writeActionMatrix(
	admin: APIRequestContext,
	matrix: ActionMatrix,
): Promise<void> {
	const res = await admin.put(`${API}/admin/action-matrix`, {
		data: { matrix },
	})
	expect(res.status(), `writing the action matrix: ${await res.text()}`).toBe(200)
}

/**
 * Grant the four rule actions to every authenticated user.
 *
 * @param admin an admin context.
 * @return the matrix as it was before, for {@link writeActionMatrix} to restore.
 */
export async function grantRuleActionsToAll(
	admin: APIRequestContext,
): Promise<ActionMatrix> {
	const prior = await readActionMatrix(admin)
	const next: ActionMatrix = { ...prior }
	for (const action of RULE_ACTIONS) {
		next[action] = ['admin', '@all']
	}
	await writeActionMatrix(admin, next)
	return prior
}

/**
 * The rule actions exactly as `lib/actions.seed.json` ships them.
 *
 * Read from the file, not restated here, so a fix to the seed turns the
 * shipped-default test green without anyone editing the test.
 *
 * @return the seed's entry for each rule action (`["admin"]` when absent).
 */
export function shippedRuleActions(): ActionMatrix {
	const raw = fs.readFileSync(
		path.resolve(process.cwd(), 'lib/actions.seed.json'),
		'utf8',
	)
	const actions = (JSON.parse(raw).actions ?? {}) as ActionMatrix
	const shipped: ActionMatrix = {}
	for (const action of RULE_ACTIONS) {
		shipped[action] = actions[action] ?? ['admin']
	}
	return shipped
}

/**
 * Create a dashboard owned by `owner` and make it the one their shell opens.
 *
 * Both activation mechanisms are called; see support/dashboardFixture.ts for
 * why one is not enough.
 *
 * @param owner the principal who owns it.
 * @param name the dashboard name.
 * @return its id and uuid.
 */
export async function seedOwnDashboard(
	owner: Principal,
	name: string,
): Promise<{ id: number; uuid: string }> {
	const created = await owner.api.post(`${API}/dashboard`, { data: { name } })
	expect(
		created.status(),
		`creating ${name}: ${await created.text()}`,
	).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()

	const activate = await owner.api.post(`${API}/dashboard/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await owner.api.post(`${API}/dashboards/default`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	return { id, uuid: String(dash.uuid) }
}

/**
 * Put a link tile on a dashboard. A tile renders its title, which is what the
 * rendered-dashboard assertions look for.
 *
 * @param api the owner's context.
 * @param dashboardId the dashboard.
 * @param title the tile title; must be unique to the run.
 * @param gridY the row, so tiles do not land on top of each other.
 * @return the placement id.
 */
export async function addTile(
	api: APIRequestContext,
	dashboardId: number,
	title: string,
	gridY: number,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: {
			title,
			linkType: 'url',
			linkValue: 'https://example.invalid/',
			gridX: 0,
			gridY,
			gridWidth: 2,
			gridHeight: 1,
		},
	})
	expect(res.status(), `adding tile ${title}: ${await res.text()}`).toBeLessThan(
		300,
	)
	const id = Number((await res.json()).id)
	expect(id, `no placement id for tile ${title}`).toBeTruthy()
	return id
}

/** POST a rule and hand back the raw response. */
export async function postRule(
	api: APIRequestContext,
	placementId: number,
	rule: RuleInput | Record<string, unknown>,
): Promise<APIResponse> {
	return api.post(`${API}/widgets/${placementId}/rules`, { data: rule })
}

/**
 * Store a rule and require HTTP 201.
 *
 * @param api the owner's context.
 * @param placementId the placement.
 * @param rule the rule.
 * @return the stored rule as the API returned it.
 */
export async function seedRule(
	api: APIRequestContext,
	placementId: number,
	rule: RuleInput,
): Promise<StoredRule> {
	const res = await postRule(api, placementId, rule)
	expect(
		res.status(),
		`storing a ${rule.ruleType} ${rule.isInclude ? 'include' : 'exclude'} rule on `
			+ `placement ${placementId}: ${await res.text()}`,
	).toBe(201)
	return (await res.json()) as StoredRule
}

/**
 * Read a placement's rules and the server's verdict for the CALLER.
 *
 * `GET /api/widgets/{id}/rules` returns `isVisible` from
 * `ConditionalService::checkRulesForPlacement()` evaluated for the logged-in
 * user, so read by two different owners it is the per-principal outcome.
 *
 * @param api the owner's context.
 * @param placementId the placement.
 * @return the rules and the verdict.
 */
export async function readRules(
	api: APIRequestContext,
	placementId: number,
): Promise<{ rules: StoredRule[]; isVisible: boolean }> {
	const res = await api.get(`${API}/widgets/${placementId}/rules`)
	expect(
		res.status(),
		`reading rules of ${placementId}: ${await res.text()}`,
	).toBe(200)
	const body = await res.json()
	return { rules: (body.rules ?? []) as StoredRule[], isVisible: body.isVisible }
}

/**
 * Ask `POST /api/visibility/preview` for a verdict at a chosen moment.
 *
 * The preview runs the same `VisibilityChecker` / `RuleEvaluatorService`
 * pipeline as the render-time check, with the clock and the group set
 * supplied by the caller. It is how a time or date rule is evaluated at
 * 14:30 or on 15 December without waiting for the server clock to get there.
 * `datetime` without an offset is read in the server's own timezone.
 *
 * @param api any logged-in context.
 * @param rules the candidate rules.
 * @param context the groups and the moment.
 * @return the verdict.
 */
export async function previewVisible(
	api: APIRequestContext,
	rules: RuleInput[],
	context: { groups?: string[]; datetime?: string },
): Promise<boolean> {
	const res = await api.post(`${API}/visibility/preview`, {
		data: { rules, context },
	})
	expect(res.status(), `preview: ${await res.text()}`).toBe(200)
	const body = await res.json()
	expect(typeof body.visible, `preview verdict: ${JSON.stringify(body)}`).toBe(
		'boolean',
	)
	return body.visible as boolean
}

/**
 * The placements on a dashboard, as its owner reads them.
 *
 * @param api the owner's context.
 * @param dashboardId the dashboard.
 * @return the placements.
 */
export async function readPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.dashboard?.placements ?? []) as Array<
		Record<string, unknown>
	>
}

/**
 * How many rule rows point at a placement that no longer exists.
 *
 * Read through a DRY-RUN purge, not `/api/admin/cleanup/scan`: the scan serves
 * a cached number for five minutes, so a before/after pair of scans would
 * compare the same value with itself. See tests/e2e/ci/dashboard-share-api.spec.ts.
 *
 * @param admin an admin context.
 * @return the orphaned rule count.
 */
export async function orphanedRuleCount(admin: APIRequestContext): Promise<number> {
	const res = await admin.post(`${API}/admin/cleanup/purge`, {
		data: { categories: ['orphaned_conditional_rules'], dryRun: true },
	})
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.dryRun, 'the orphan count must come from a dry run').toBe(true)
	return Number(body.purgedByCategory?.orphaned_conditional_rules ?? 0)
}

/**
 * Set one Nextcloud account field (`language`, `email`, ...) as the admin.
 *
 * @param admin an admin context.
 * @param username the account.
 * @param key the OCS field.
 * @param value its new value.
 * @return nothing; fails the test when refused.
 */
export async function setAccountField(
	admin: APIRequestContext,
	username: string,
	key: string,
	value: string,
): Promise<void> {
	const res = await admin.put(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}?format=json`,
		{ form: { key, value } },
	)
	expect(res.status(), `setting ${key} of ${username}: ${await res.text()}`).toBe(
		200,
	)
	const body = await res.json().catch(() => null)
	if (body?.ocs?.meta?.statuscode !== undefined) {
		expect(
			body.ocs.meta.statuscode,
			`setting ${key} of ${username}: ${JSON.stringify(body.ocs.meta)}`,
		).toBe(100)
	}
}
