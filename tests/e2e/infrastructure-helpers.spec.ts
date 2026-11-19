/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the infrastructure helpers
 * (openspec/specs/infrastructure-helpers), written for
 * e2e-coverage-programme task 5.9.
 *
 * The helpers have no surface of their own, but each one decides an HTTP
 * outcome a person can reach, so every scenario that has one is measured
 * there, as a throwaway non-admin account:
 *
 *  - SlugGenerator decides the `slug` a new dashboard is stored with
 *    (`POST /api/dashboard`), and refuses a malformed explicit slug with 400.
 *    Every probe is a child of a per-run parent dashboard, so the root-slug
 *    de-duplication (`-2`, `-3`, ...) cannot change the value under test.
 *  - UserAttributeResolver decides `attribute` rules, which the read-only
 *    `POST /api/visibility/preview` evaluates against the logged-in caller.
 *    Each probe pairs the rule under test with a control that must come out
 *    the other way, so a preview that always answers the same cannot pass.
 *  - RequestDataExtractor decides what a tile is created with
 *    (`POST /api/dashboard/{id}/tile`) and which fields a placement update
 *    touches (`PUT /api/widgets/{id}`). The update probe leans on the
 *    acknowledgement prompt, which the updater clears on an explicit null:
 *    an extractor that stopped filtering nulls would erase it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e infrastructure-helpers::multi-word-name-becomes-dashed-slug
 *   @e2e infrastructure-helpers::special-characters-are-stripped
 *   @e2e infrastructure-helpers::empty-input-returns-empty-string
 *   @e2e infrastructure-helpers::over-length-input-is-truncated-without-trailing-dash
 *   @e2e infrastructure-helpers::validate-rejects-uppercase
 *   @e2e infrastructure-helpers::validate-accepts-grammar-match
 *   @e2e infrastructure-helpers::resolve-locale-falls-back-to-en
 *   @e2e infrastructure-helpers::unknown-attribute-returns-null
 *   @e2e infrastructure-helpers::operator-evaluation-equals-match
 *   @e2e infrastructure-helpers::operator-evaluation-unknown-operator-returns-false
 *   @e2e infrastructure-helpers::operator-evaluation-null-comparison-value-treated-as-empty-string
 *   @e2e infrastructure-helpers::extracttiledata-returns-defaults-for-missing-params
 *   @e2e infrastructure-helpers::extracttiledata-maps-textcolor-txtcolor-and-linkvalue-linkval
 *   @e2e infrastructure-helpers::extractplacementdata-filters-null-fields
 *
 * @spec openspec/specs/infrastructure-helpers/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()

type Dashboard = { id: number; uuid: string; name: string; slug: string | null }
type Placement = Record<string, unknown> & { id: number }
type Rule = {
	id: number
	ruleType: string
	ruleConfig: Record<string, unknown>
	isInclude: boolean
}
type Preview = {
	visible: boolean
	matchedIncludeRuleIds: number[]
	matchedExcludeRuleIds: number[]
}

let admin: APIRequestContext
let user: Principal | null = null
let parent: Dashboard

/**
 * The dashboard object out of a create or show response.
 *
 * @param body the decoded response body.
 * @return the dashboard.
 */
function dashboardOf(body: Record<string, unknown>): Dashboard {
	const dash = (body.dashboard ?? body) as Record<string, unknown>
	return {
		id: Number(dash.id),
		uuid: String(dash.uuid),
		name: String(dash.name),
		slug: (dash.slug ?? null) as string | null,
	}
}

/**
 * Create a dashboard as the test account and expect it to be accepted.
 *
 * @param data the create body.
 * @return the created dashboard.
 */
async function createDashboard(data: Record<string, unknown>): Promise<Dashboard> {
	const res = await user!.api.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBe(201)
	return dashboardOf(await res.json())
}

/**
 * Create a child of the per-run parent from a name alone, so the slug is the
 * one SlugGenerator derives.
 *
 * @param name the dashboard name.
 * @return the created dashboard.
 */
async function childNamed(name: string): Promise<Dashboard> {
	return createDashboard({ name, parentUuid: parent.uuid })
}

/**
 * The placements of a dashboard, as its owner reads them.
 *
 * @param dashboardId the dashboard.
 * @return its placements.
 */
async function placementsOf(dashboardId: number): Promise<Placement[]> {
	const res = await user!.api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).placements ?? []) as Placement[]
}

/**
 * Run the visibility preview as the test account.
 *
 * @param rules the rule set.
 * @return the preview.
 */
async function preview(rules: Rule[]): Promise<Preview> {
	const res = await user!.api.post(`${API}/visibility/preview`, {
		data: { rules, context: { groups: [] } },
	})
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as Preview
}

/**
 * One include attribute rule.
 *
 * @param id the correlation id echoed back by the preview.
 * @param attribute the user attribute.
 * @param operator the comparison operator.
 * @param value the comparison value.
 * @return the rule.
 */
function attributeRule(id: number, attribute: string, operator: string, value: string | null): Rule {
	return { id, ruleType: 'attribute', ruleConfig: { attribute, operator, value }, isInclude: true }
}

/**
 * Create an empty dashboard for a tile probe: the seeded default widgets are
 * removed, so the free-spot finder has no reason to move a tile off (0, 0).
 *
 * @param label a readable part of the name.
 * @return the dashboard.
 */
async function emptyDashboard(label: string): Promise<Dashboard> {
	const dash = await createDashboard({ name: `E2E Infra ${label} ${RUN}`, parentUuid: parent.uuid })
	for (const placement of await placementsOf(dash.id)) {
		const res = await user!.api.delete(`${API}/widgets/${placement.id}`)
		expect(res.status(), await res.text()).toBeLessThan(300)
	}
	expect(await placementsOf(dash.id)).toEqual([])
	return dash
}

test.beforeAll(async () => {
	admin = await adminContext()
	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)
	user = await createPrincipal(admin, 'e2e-infra')
	await assertActingAs(user.api, user.username)
	parent = await createDashboard({ name: `E2E Infra parent ${RUN}` })
})

test.afterAll(async () => {
	if (user && parent) {
		// Children before the parent; the account's removal sweeps the rest.
		const listed = await user.api.get(`${API}/dashboards`)
		if (listed.ok()) {
			const body = await listed.json()
			const rows = (Array.isArray(body) ? body : body.items ?? []) as Array<Record<string, unknown>>
			for (const row of rows.filter((r) => r.parentUuid === parent.uuid)) {
				await user.api.delete(`${API}/dashboard/${row.id}`)
			}
		}
		await user.api.delete(`${API}/dashboard/${parent.id}`)
	}
	await removePrincipal(admin, user)
	await admin.dispose()
})

test.describe.serial('SlugGenerator, as the slug a new dashboard is stored with (REQ-INFRA-001)', () => {
	// @e2e infrastructure-helpers::multi-word-name-becomes-dashed-slug
	test('a multi-word name becomes a dashed slug', async () => {
		const dash = await childNamed('Q1 Campaigns')
		expect(dash.slug).toBe('q1-campaigns')
	})

	// @e2e infrastructure-helpers::special-characters-are-stripped
	test('special characters are stripped from the slug', async () => {
		const dash = await childNamed('My Dashboard! (v2)')
		expect(dash.slug).toBe('my-dashboard-v2')
	})

	// @e2e infrastructure-helpers::empty-input-returns-empty-string
	test('a name with no legal characters yields no slug, and the dashboard is still created', async () => {
		// slugify('***') returns ''; DashboardFactory stores an empty result
		// as NULL. Any non-empty return would surface here as a string.
		const dash = await childNamed('***')
		expect(dash.name).toBe('***')
		expect(dash.slug).toBeNull()
	})

	// @e2e infrastructure-helpers::over-length-input-is-truncated-without-trailing-dash
	test('a 200-character name is cut to at most 128 characters with no trailing dash', async () => {
		// The space lands at index 127, so the cut at 128 ends on the dash
		// it became; only the re-trim keeps it off the stored slug.
		const name = `${'a'.repeat(127)} ${'b'.repeat(72)}`
		expect(name).toHaveLength(200)
		const dash = await childNamed(name)
		expect(dash.slug).not.toBeNull()
		expect(dash.slug!.length).toBeLessThanOrEqual(128)
		expect(dash.slug!.endsWith('-')).toBe(false)
		expect(dash.slug).toBe('a'.repeat(127))
	})

	// @e2e infrastructure-helpers::validate-rejects-uppercase
	test('an explicit slug with uppercase letters is refused with 400 and nothing is stored', async () => {
		const name = `E2E Infra uppercase ${RUN}`
		const refused = await user!.api.post(`${API}/dashboard`, {
			data: { name, parentUuid: parent.uuid, slug: 'LaunchPadboard' },
		})
		expect(refused.status(), await refused.text()).toBe(400)

		const tree = await user!.api.get(`${API}/dashboards`)
		expect(tree.status(), await tree.text()).toBe(200)
		expect(JSON.stringify(await tree.json())).not.toContain(name)

		// The same request with a lowercase slug is accepted, so the refusal
		// above is the grammar check and not a broken endpoint.
		const allowed = await createDashboard({ name, parentUuid: parent.uuid, slug: 'launchpadboard' })
		expect(allowed.slug).toBe('launchpadboard')
	})

	// @e2e infrastructure-helpers::validate-accepts-grammar-match
	test('an explicit slug that matches the grammar is accepted and stored verbatim', async () => {
		const dash = await createDashboard({
			name: `E2E Infra explicit ${RUN}`,
			parentUuid: parent.uuid,
			slug: 'q1-campaigns_v2',
		})
		expect(dash.slug).toBe('q1-campaigns_v2')
	})
})

test.describe.serial('UserAttributeResolver, as the attribute rules a preview evaluates (REQ-INFRA-002)', () => {
	// @e2e infrastructure-helpers::resolve-locale-falls-back-to-en
	test('a fresh account with no language preference resolves its locale to en', async () => {
		// The account was just created and never chose a language, so it has
		// no core/lang preference. Only the 'en' fallback matches rule 1; the
		// other two would match a missing, empty or differently-defaulted value.
		const result = await preview([
			attributeRule(1, 'locale', 'equals', 'en'),
			{ ...attributeRule(2, 'locale', 'not_equals', 'en'), isInclude: false },
			{ ...attributeRule(3, 'locale', 'equals', ''), isInclude: false },
		])
		expect(result).toEqual({ visible: true, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [] })
	})

	// @e2e infrastructure-helpers::unknown-attribute-returns-null
	test('an unknown attribute resolves to null, so even not_equals does not match', async () => {
		// Had the resolver answered any string for twitter_handle, not_equals
		// against a value no account has would match.
		const unknown = await preview([attributeRule(1, 'twitter_handle', 'not_equals', `nobody-${RUN}`)])
		expect(unknown).toEqual({ visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] })

		// Control: the same rule on a supported attribute does match.
		const known = await preview([attributeRule(1, 'locale', 'not_equals', `nobody-${RUN}`)])
		expect(known).toEqual({ visible: true, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [] })
	})

	// @e2e infrastructure-helpers::operator-evaluation-equals-match
	test('equals matches an equal value and only an equal value', async () => {
		const result = await preview([
			attributeRule(1, 'locale', 'equals', 'en'),
			attributeRule(2, 'locale', 'equals', 'nl'),
		])
		expect(result.visible).toBe(true)
		expect(result.matchedIncludeRuleIds).toEqual([1])
	})

	// @e2e infrastructure-helpers::operator-evaluation-unknown-operator-returns-false
	test('an unknown operator does not match and raises nothing', async () => {
		// preview() asserts 200: an exception would answer 400 "Preview failed".
		const result = await preview([attributeRule(1, 'locale', 'regex_match', '^en')])
		expect(result).toEqual({ visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] })

		const control = await preview([attributeRule(1, 'locale', 'starts_with', 'e')])
		expect(control).toEqual({ visible: true, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [] })
	})

	// @e2e infrastructure-helpers::operator-evaluation-null-comparison-value-treated-as-empty-string
	test('contains against a null value matches, as every string contains the empty string', async () => {
		const result = await preview([
			attributeRule(1, 'locale', 'contains', null),
			attributeRule(2, 'locale', 'contains', 'zz'),
		])
		expect(result.visible).toBe(true)
		expect(result.matchedIncludeRuleIds).toEqual([1])
	})
})

test.describe.serial('RequestDataExtractor, as what a tile and a placement update carry (REQ-INFRA-003)', () => {
	// @e2e infrastructure-helpers::extracttiledata-returns-defaults-for-missing-params
	test('a tile created with no parameters carries every default', async () => {
		const dash = await emptyDashboard('tile defaults')
		const res = await user!.api.post(`${API}/dashboard/${dash.id}/tile`, { data: {} })
		expect(res.status(), await res.text()).toBe(201)
		const tile = (await res.json()) as Placement
		expect(tile).toMatchObject({
			tileTitle: 'New Tile',
			tileIcon: 'icon-link',
			tileIconType: 'class',
			tileBackgroundColor: '#0082c9',
			tileTextColor: '#ffffff',
			tileLinkType: 'app',
			tileLinkValue: '',
			gridX: 0,
			gridY: 0,
		})

		// And that is what was stored, not only what was answered.
		const stored = (await placementsOf(dash.id)).find((p) => p.id === tile.id)
		expect(stored).toMatchObject({ tileTitle: 'New Tile', tileTextColor: '#ffffff', tileLinkValue: '', gridX: 0, gridY: 0 })
	})

	// @e2e infrastructure-helpers::extracttiledata-maps-textcolor-txtcolor-and-linkvalue-linkval
	test('textColor and linkValue reach the tile as its text colour and link value', async () => {
		const dash = await emptyDashboard('tile mapping')
		const res = await user!.api.post(`${API}/dashboard/${dash.id}/tile`, {
			data: { textColor: '#000', linkValue: '/foo' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const tile = (await res.json()) as Placement
		expect(tile.tileTextColor).toBe('#000')
		expect(tile.tileLinkValue).toBe('/foo')

		const stored = (await placementsOf(dash.id)).find((p) => p.id === tile.id)
		expect(stored).toMatchObject({ tileTextColor: '#000', tileLinkValue: '/foo' })
	})

	// @e2e infrastructure-helpers::extractplacementdata-filters-null-fields
	test('an update carrying only gridX changes gridX and nothing else', async () => {
		const dash = await emptyDashboard('placement filter')
		const created = await user!.api.post(`${API}/dashboard/${dash.id}/tile`, {
			data: { title: `Filter probe ${RUN}`, gridX: 0, gridY: 0 },
		})
		expect(created.status(), await created.text()).toBe(201)
		const tile = (await created.json()) as Placement

		// A prompt and a custom title the gridX-only update must leave alone.
		// The updater clears the prompt on an explicit null, so an extractor
		// that passed every field through, nulls included, would erase it.
		const prompt = `E2E filter prompt ${RUN}`
		const seeded = await user!.api.put(`${API}/widgets/${tile.id}`, {
			data: { acknowledgementPrompt: prompt, customTitle: `Custom ${RUN}` },
		})
		expect(seeded.status(), await seeded.text()).toBe(200)
		const before = (await placementsOf(dash.id)).find((p) => p.id === tile.id)!
		expect(before.acknowledgementPrompt).toBe(prompt)

		const moved = await user!.api.put(`${API}/widgets/${tile.id}`, { data: { gridX: 2 } })
		expect(moved.status(), await moved.text()).toBe(200)

		const after = (await placementsOf(dash.id)).find((p) => p.id === tile.id)!
		expect(after.gridX).toBe(2)
		const unchanged = (p: Placement) => {
			const { gridX: _gridX, version: _version, updatedAt: _updatedAt, ...rest } = p
			return rest
		}
		expect(unchanged(after)).toEqual(unchanged(before))
	})
})
