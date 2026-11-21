/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a conditional visibility rule does to the dashboard each principal is
 * shown (conditional-visibility REQ-VIS-005..010): group, time, date and
 * attribute rules, how several rules combine, and the isVisible flag.
 *
 * EVERY TEST ASSERTS A SHOWN CASE AND A HIDDEN CASE
 * =================================================
 * A widget that is "hidden" for everyone, or "shown" to everyone, passes half
 * of these scenarios by accident. So every test puts the rule in front of two
 * principals (or two moments) for which the outcome must differ, and checks
 * both, in two places:
 *
 *  1. The server's verdict for the logged-in caller: `isVisible` from
 *     `GET /api/widgets/{id}/rules`, which runs
 *     `ConditionalService::checkRulesForPlacement()` for that caller. It is an
 *     owner-only read, so each principal owns a dashboard of their own and the
 *     same rule is put on a tile on each.
 *  2. The rendered dashboard: each principal is logged in once, in a browser,
 *     on their own dashboard. As its owner they still get a tile the rules
 *     hide, dimmed with the "Hidden for viewers" badge (REQ-VIS-013), so a
 *     shown tile is one without that badge and a hidden one carries it. Every
 *     page is gated on an anchor tile without rules first, so a verdict is
 *     never read off a page that has not rendered.
 *
 * Time and date rules cannot wait for the server clock to reach 14:30 or 15
 * December. For those the exact moment comes from `POST /api/visibility/preview`,
 * which takes a `datetime` and runs the same VisibilityChecker and
 * RuleEvaluatorService pipeline; the rendered half uses rules that match, or
 * cannot match, at any moment.
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */

import type { APIRequestContext, BrowserContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { ActionMatrix, RuleInput } from './support/visibilityRules.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	addToGroup,
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'
import {
	addTile,
	grantRuleActionsToAll,
	previewVisible,
	readPlacements,
	readRules,
	seedOwnDashboard,
	seedRule,
	setAccountField,
	writeActionMatrix,
} from './support/visibilityRules.ts'

const STAMP = `${Date.now()}`
const YEAR = new Date().getUTCFullYear()

const G = {
	marketing: `cv-marketing-${STAMP}`,
	sales: `cv-sales-${STAMP}`,
	engineering: `cv-engineering-${STAMP}`,
	contractors: `cv-contractors-${STAMP}`,
	allStaff: `cv-all-staff-${STAMP}`,
}

/** One logged-in principal with a dashboard of their own and a browser tab on it. */
interface Viewer {
	who: Principal
	dashboardId: number
	anchor: string
	context: BrowserContext | null
	page: Page | null
}

let admin: APIRequestContext
let priorMatrix: ActionMatrix | null = null
const groups: string[] = []
const viewers: Record<'alice' | 'bob' | 'carol' | 'dave', Viewer | null> = {
	alice: null,
	bob: null,
	carol: null,
	dave: null,
}
let row = 0

/* Rules that match, or cannot match, at any moment. */
const ALWAYS_TIME = { startTime: '00:00', endTime: '23:59' }
// An empty `days` list allows no day at all, so this never matches.
const NEVER_TIME = { startTime: '00:00', endTime: '23:59', days: [] as string[] }
const PAST_START = `${YEAR - 1}-01-01`
const FUTURE_START = `${YEAR + 1}-01-01`
const FUTURE_END = `${YEAR + 1}-12-31`
const PAST_END = `${YEAR - 1}-12-31`

function include(ruleType: string, ruleConfig: Record<string, unknown>): RuleInput {
	return { ruleType, ruleConfig, isInclude: true }
}

function exclude(ruleType: string, ruleConfig: Record<string, unknown>): RuleInput {
	return { ruleType, ruleConfig, isInclude: false }
}

function v(name: keyof typeof viewers): Viewer {
	const viewer = viewers[name]
	if (viewer === null) {
		throw new Error(`fixture ${name} was not set up`)
	}
	return viewer
}

/**
 * Put a tile carrying `rules` on a viewer's own dashboard.
 *
 * @param viewer whose dashboard.
 * @param label readable part of the title.
 * @param rules stored in order; each must be accepted with 201.
 * @return the placement id and the tile title.
 */
async function tileWith(
	viewer: Viewer,
	label: string,
	rules: RuleInput[],
): Promise<{ pid: number; title: string }> {
	row += 1
	const title = `[cv ${row}] ${label} ${STAMP}`
	const pid = await addTile(viewer.who.api, viewer.dashboardId, title, row)
	for (const rule of rules) {
		await seedRule(viewer.who.api, pid, rule)
	}
	return { pid, title }
}

/**
 * The server's verdict for the tile's owner.
 *
 * @param viewer the owner.
 * @param tile the tile.
 * @param shown the expected verdict.
 * @param why what the expectation means.
 */
async function expectVerdict(
	viewer: Viewer,
	tile: { pid: number },
	shown: boolean,
	why: string,
): Promise<void> {
	expect(
		(await readRules(viewer.who.api, tile.pid)).isVisible,
		`server verdict: ${why}`,
	).toBe(shown)
}

/**
 * Reload a viewer's dashboard and check which tiles are rendered.
 *
 * @param viewer the logged-in viewer.
 * @param tiles each tile title and whether it must be in the DOM.
 */
async function expectRendered(
	viewer: Viewer,
	tiles: Array<{ title: string; shown: boolean; why: string }>,
): Promise<void> {
	const page = viewer.page!
	await page.goto('/index.php/apps/launchpad/')
	await expect(
		page.locator('.launchpad-grid-item').filter({ hasText: viewer.anchor }),
		`${viewer.who.username}'s own dashboard must have rendered (anchor tile)`,
	).toHaveCount(1, { timeout: 30_000 })
	for (const tile of tiles) {
		const item = page
			.locator('.launchpad-grid-item')
			.filter({ hasText: tile.title })
		await expect(
			item,
			`${viewer.who.username} owns the dashboard, so the tile is rendered: ${tile.why}`,
		).toHaveCount(1)
		await expect(
			item.getByTestId('hidden-for-viewers-badge'),
			`${tile.shown ? 'no' : 'a'} "Hidden for viewers" badge for ${viewer.who.username}: ${tile.why}`,
		).toHaveCount(tile.shown ? 0 : 1)
	}
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser }) => {
	test.setTimeout(240_000)
	admin = await adminContext()
	await allowPersonalDashboards(admin, true)
	priorMatrix = await grantRuleActionsToAll(admin)

	for (const id of Object.values(G)) {
		groups.push(await createGroup(admin, id))
	}

	const memberships: Record<keyof typeof viewers, string[]> = {
		alice: [G.marketing],
		bob: [G.engineering],
		carol: [G.contractors, G.sales],
		dave: [G.engineering, G.marketing, G.allStaff],
	}
	for (const name of Object.keys(memberships) as Array<keyof typeof viewers>) {
		const who = await createPrincipal(admin, `e2e-cv-${name}`)
		viewers[name] = {
			who,
			dashboardId: 0,
			anchor: '',
			context: null,
			page: null,
		}
		await assertActingAs(who.api, who.username)
		for (const group of memberships[name]) {
			await addToGroup(admin, who.username, group)
		}
	}

	// Attributes the REQ-VIS-008 scenarios name.
	await setAccountField(admin, v('alice').who.username, 'language', 'nl')
	await setAccountField(admin, v('bob').who.username, 'language', 'en')
	await setAccountField(admin, v('carol').who.username, 'language', 'de')
	await setAccountField(
		admin,
		v('dave').who.username,
		'email',
		`${v('dave').who.username}@company.com`,
	)
	await setAccountField(
		admin,
		v('bob').who.username,
		'email',
		`${v('bob').who.username}@elsewhere.example`,
	)

	for (const name of Object.keys(viewers) as Array<keyof typeof viewers>) {
		const viewer = v(name)
		const dash = await seedOwnDashboard(viewer.who, `E2E CV ${name} ${STAMP}`)
		viewer.dashboardId = dash.id
		viewer.anchor = `[anchor] ${name} ${STAMP}`
		await addTile(viewer.who.api, dash.id, viewer.anchor, 0)
		const session = await loginAs(
			browser,
			viewer.who.username,
			viewer.who.password,
		)
		viewer.context = session.context
		viewer.page = session.page
	}
})

test.afterAll(async () => {
	for (const viewer of Object.values(viewers)) {
		if (viewer === null) {
			continue
		}
		await viewer.context?.close()
		if (viewer.dashboardId) {
			await viewer.who.api.delete(`${API}/dashboard/${viewer.dashboardId}`)
		}
		await removePrincipal(admin, viewer.who)
	}
	for (const group of groups) {
		await removeGroup(admin, group)
	}
	if (priorMatrix !== null) {
		await writeActionMatrix(admin, priorMatrix)
	}
	await admin.dispose()
})

test.describe('REQ-VIS-005 group rules', () => {
	// @e2e conditional-visibility::user-is-in-a-matching-group-inclusion-rule
	// @e2e conditional-visibility::user-is-not-in-any-matching-group-inclusion-rule
	test('a marketing member sees a marketing-or-sales tile; an engineering-only user does not', async () => {
		const rule = include('group', { groups: [G.marketing, G.sales] })
		const forAlice = await tileWith(v('alice'), 'group include', [rule])
		const forBob = await tileWith(v('bob'), 'group include', [rule])

		await expectVerdict(v('alice'), forAlice, true, 'alice is in marketing')
		await expectVerdict(v('bob'), forBob, false, 'bob is only in engineering')
		await expectRendered(v('alice'), [
			{ ...forAlice, shown: true, why: 'alice is in marketing' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'bob is only in engineering' },
		])
	})

	// @e2e conditional-visibility::user-is-in-a-matching-group-exclusion-rule
	test('a contractors exclusion hides the tile from a contractor and not from anyone else', async () => {
		// SUSPECTED BUG (launchpad#96): an exclude rule cannot be stored on PostgreSQL.
		const rule = exclude('group', { groups: [G.contractors] })
		const forCarol = await tileWith(v('carol'), 'group exclude', [rule])
		const forBob = await tileWith(v('bob'), 'group exclude', [rule])

		await expectVerdict(v('carol'), forCarol, false, 'carol is a contractor')
		await expectVerdict(v('bob'), forBob, true, 'bob is not a contractor')
		await expectRendered(v('carol'), [
			{ ...forCarol, shown: false, why: 'carol is a contractor' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: true, why: 'bob is not a contractor' },
		])
	})

	// @e2e conditional-visibility::user-in-multiple-groups-with-partial-match
	test('a user in three groups, one of them marketing, sees a marketing tile', async () => {
		const rule = include('group', { groups: [G.marketing] })
		const forDave = await tileWith(v('dave'), 'partial match', [rule])
		const forBob = await tileWith(v('bob'), 'partial match', [rule])

		await expectVerdict(
			v('dave'),
			forDave,
			true,
			'dave is in engineering, marketing and all-staff',
		)
		await expectVerdict(
			v('bob'),
			forBob,
			false,
			'bob shares engineering with dave but not marketing',
		)
		await expectRendered(v('dave'), [
			{ ...forDave, shown: true, why: 'one of three groups matches' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'no group matches' },
		])
	})

	// @e2e conditional-visibility::empty-groups-array-in-rule-config
	test('an inclusion rule with an empty groups list matches nobody', async () => {
		const empty = await tileWith(v('dave'), 'empty groups', [
			include('group', { groups: [] }),
		])
		const control = await tileWith(v('dave'), 'marketing control', [
			include('group', { groups: [G.marketing] }),
		])

		await expectVerdict(
			v('dave'),
			empty,
			false,
			'an empty groups list never matches',
		)
		await expectVerdict(
			v('dave'),
			control,
			true,
			'CONTROL: the same user passes a real group rule',
		)
		await expectRendered(v('dave'), [
			{ ...empty, shown: false, why: 'an empty groups list never matches' },
			{
				...control,
				shown: true,
				why: 'CONTROL: the same user passes a real group rule',
			},
		])
	})
})

test.describe('REQ-VIS-006 time rules', () => {
	// @e2e conditional-visibility::current-time-is-within-the-time-window-inclusion-rule
	// @e2e conditional-visibility::current-time-is-outside-the-time-window-inclusion-rule
	test('a 09:00-17:00 inclusion shows the widget at 14:30 and hides it at 20:00', async () => {
		const office = [include('time', { startTime: '09:00', endTime: '17:00' })]
		expect(
			await previewVisible(v('bob').who.api, office, {
				datetime: '2026-11-18T14:30:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, office, {
				datetime: '2026-11-18T20:00:00',
			}),
		).toBe(false)

		const inside = await tileWith(v('bob'), 'time inside', [
			include('time', ALWAYS_TIME),
		])
		const outside = await tileWith(v('bob'), 'time outside', [
			include('time', NEVER_TIME),
		])
		await expectVerdict(v('bob'), inside, true, 'now is inside 00:00-23:59')
		await expectVerdict(v('bob'), outside, false, 'now is on no allowed day')
		await expectRendered(v('bob'), [
			{ ...inside, shown: true, why: 'inside the window' },
			{ ...outside, shown: false, why: 'outside the window' },
		])
	})

	// @e2e conditional-visibility::time-window-spanning-midnight-known-limitation
	test('a 22:00-06:00 inclusion shows the widget at 02:00 and not at noon', async () => {
		const night = [include('time', { startTime: '22:00', endTime: '06:00' })]
		expect(
			await previewVisible(v('bob').who.api, night, {
				datetime: '2026-11-18T12:00:00',
			}),
		).toBe(false)
		// SUSPECTED BUG (the scenario's own KNOWN LIMITATION): the string
		// comparison `>= '22:00' && <= '06:00'` is never true, so the widget is
		// hidden at 02:00 too.
		expect(
			await previewVisible(v('bob').who.api, night, {
				datetime: '2026-11-18T02:00:00',
			}),
		).toBe(true)
	})

	// @e2e conditional-visibility::time-evaluation-uses-server-timezone-not-configurable
	test('a time rule is read on the server clock, and a timezone field changes nothing', async () => {
		const plain = [include('time', { startTime: '09:00', endTime: '17:00' })]
		// If `timezone` were honoured, 08:00 server time would be 13:30 in
		// Kolkata, inside the window.
		const zoned = [
			include('time', {
				startTime: '09:00',
				endTime: '17:00',
				timezone: 'Asia/Kolkata',
			}),
		]
		const eight = { datetime: '2026-11-18T08:00:00' }
		const ten = { datetime: '2026-11-18T10:00:00' }

		expect(
			await previewVisible(v('bob').who.api, plain, eight),
			'08:00 is before 09:00',
		).toBe(false)
		expect(
			await previewVisible(v('bob').who.api, zoned, eight),
			'timezone must be ignored',
		).toBe(false)
		expect(await previewVisible(v('bob').who.api, plain, ten)).toBe(true)
		expect(await previewVisible(v('bob').who.api, zoned, ten)).toBe(true)
	})

	// @e2e conditional-visibility::time-rule-with-day-of-week-filter
	test('a weekday-only 09:00-17:00 inclusion hides the widget on Saturday and shows it on Friday', async () => {
		const weekdays = [
			include('time', {
				startTime: '09:00',
				endTime: '17:00',
				days: ['mon', 'tue', 'wed', 'thu', 'fri'],
			}),
		]
		// 2026-11-21 is a Saturday, 2026-11-20 a Friday; both at 10:00, inside the window.
		expect(
			await previewVisible(v('bob').who.api, weekdays, {
				datetime: '2026-11-21T10:00:00',
			}),
		).toBe(false)
		expect(
			await previewVisible(v('bob').who.api, weekdays, {
				datetime: '2026-11-20T10:00:00',
			}),
		).toBe(true)

		const anyDay = await tileWith(v('bob'), 'every day', [
			include('time', {
				...ALWAYS_TIME,
				days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
			}),
		])
		const noDay = await tileWith(v('bob'), 'no day', [
			include('time', NEVER_TIME),
		])
		await expectVerdict(v('bob'), anyDay, true, 'today is in the list')
		await expectVerdict(v('bob'), noDay, false, 'today is not in the list')
		await expectRendered(v('bob'), [
			{ ...anyDay, shown: true, why: 'today is an allowed day' },
			{ ...noDay, shown: false, why: 'today is not an allowed day' },
		])
	})

	// @e2e conditional-visibility::time-rule-without-day-filter
	test('without a days list a Saturday at 10:00 is inside 09:00-17:00 and 20:00 is not', async () => {
		const office = [include('time', { startTime: '09:00', endTime: '17:00' })]
		expect(
			await previewVisible(v('bob').who.api, office, {
				datetime: '2026-11-21T10:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, office, {
				datetime: '2026-11-21T20:00:00',
			}),
		).toBe(false)
	})

	// @e2e conditional-visibility::time-rule-with-default-start-and-end-times
	test('a time rule without start and end matches at any minute of the day', async () => {
		const open = [include('time', {})]
		for (const at of ['00:00', '12:00', '23:59']) {
			expect(
				await previewVisible(v('bob').who.api, open, {
					datetime: `2026-11-18T${at}:00`,
				}),
				at,
			).toBe(true)
		}
		// The same open rule as an exclusion matches too, so it hides.
		expect(
			await previewVisible(v('bob').who.api, [exclude('time', {})], {
				datetime: '2026-11-18T12:00:00',
			}),
		).toBe(false)

		const live = await tileWith(v('bob'), 'time defaults', [include('time', {})])
		const control = await tileWith(v('bob'), 'time defaults control', [
			include('time', NEVER_TIME),
		])
		await expectVerdict(v('bob'), live, true, 'the defaults cover the whole day')
		await expectVerdict(
			v('bob'),
			control,
			false,
			'CONTROL: a time rule can hide',
		)
		await expectRendered(v('bob'), [
			{ ...live, shown: true, why: 'the defaults cover the whole day' },
			{ ...control, shown: false, why: 'CONTROL: a time rule can hide' },
		])
	})
})

test.describe('REQ-VIS-007 date rules', () => {
	// @e2e conditional-visibility::current-date-is-within-the-date-range-inclusion-rule
	// @e2e conditional-visibility::current-date-is-outside-the-date-range-inclusion-rule
	test('a December 2026 inclusion shows the widget on 15 December and hides it on 15 November', async () => {
		const december = [
			include('date', { startDate: '2026-12-01', endDate: '2026-12-31' }),
		]
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2026-12-15T12:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2026-11-15T12:00:00',
			}),
		).toBe(false)

		const inside = await tileWith(v('bob'), 'date inside', [
			include('date', { startDate: PAST_START, endDate: FUTURE_END }),
		])
		const outside = await tileWith(v('bob'), 'date outside', [
			include('date', { startDate: FUTURE_START, endDate: FUTURE_END }),
		])
		await expectVerdict(v('bob'), inside, true, 'today is in the range')
		await expectVerdict(v('bob'), outside, false, 'today is before the range')
		await expectRendered(v('bob'), [
			{ ...inside, shown: true, why: 'today is in the range' },
			{ ...outside, shown: false, why: 'today is before the range' },
		])
	})

	// @e2e conditional-visibility::open-ended-date-range-no-end-date
	test('a range with only a start date matches every day from it on', async () => {
		const fromJan = [include('date', { startDate: '2026-01-01' })]
		expect(
			await previewVisible(v('bob').who.api, fromJan, {
				datetime: '2027-06-15T12:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, fromJan, {
				datetime: '2025-12-31T12:00:00',
			}),
		).toBe(false)

		const started = await tileWith(v('bob'), 'no end started', [
			include('date', { startDate: PAST_START }),
		])
		const notYet = await tileWith(v('bob'), 'no end not yet', [
			include('date', { startDate: FUTURE_START }),
		])
		await expectVerdict(v('bob'), started, true, 'the start date has passed')
		await expectVerdict(v('bob'), notYet, false, 'the start date is still ahead')
		await expectRendered(v('bob'), [
			{ ...started, shown: true, why: 'the start date has passed' },
			{ ...notYet, shown: false, why: 'the start date is still ahead' },
		])
	})

	// @e2e conditional-visibility::open-ended-date-range-no-start-date
	test('a range with only an end date matches every day up to it', async () => {
		const untilDec = [include('date', { endDate: '2026-12-31' })]
		expect(
			await previewVisible(v('bob').who.api, untilDec, {
				datetime: '2025-06-15T12:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, untilDec, {
				datetime: '2027-01-01T12:00:00',
			}),
		).toBe(false)

		const running = await tileWith(v('bob'), 'no start running', [
			include('date', { endDate: FUTURE_END }),
		])
		const ended = await tileWith(v('bob'), 'no start ended', [
			include('date', { endDate: PAST_END }),
		])
		await expectVerdict(v('bob'), running, true, 'the end date is still ahead')
		await expectVerdict(v('bob'), ended, false, 'the end date has passed')
		await expectRendered(v('bob'), [
			{ ...running, shown: true, why: 'the end date is still ahead' },
			{ ...ended, shown: false, why: 'the end date has passed' },
		])
	})

	// @e2e conditional-visibility::date-range-boundary-inclusivity
	test('the start and end dates are both inside the range, the days around them are not', async () => {
		const december = [
			include('date', { startDate: '2026-12-01', endDate: '2026-12-31' }),
		]
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2026-12-01T00:00:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2026-12-31T23:59:00',
			}),
		).toBe(true)
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2026-11-30T23:59:00',
			}),
		).toBe(false)
		expect(
			await previewVisible(v('bob').who.api, december, {
				datetime: '2027-01-01T00:00:00',
			}),
		).toBe(false)
	})
})

test.describe('REQ-VIS-008 attribute rules', () => {
	// @e2e conditional-visibility::attribute-matches-with-equals-operator
	// @e2e conditional-visibility::attribute-does-not-match-with-equals-operator
	test('a language-equals-nl inclusion shows the tile to the Dutch user and hides it from the English one', async () => {
		const rule = include('attribute', {
			attribute: 'language',
			operator: 'equals',
			value: 'nl',
		})
		const forAlice = await tileWith(v('alice'), 'language nl', [rule])
		const forBob = await tileWith(v('bob'), 'language nl', [rule])

		await expectVerdict(v('bob'), forBob, false, 'bob has language en')
		// SUSPECTED BUG: UserAttributeResolver has no `language` attribute (the
		// language is resolved under the name `locale`), so this rule matches
		// nobody. The editor's own placeholder for the field is "language".
		await expectVerdict(v('alice'), forAlice, true, 'alice has language nl')
		await expectRendered(v('alice'), [
			{ ...forAlice, shown: true, why: 'alice has language nl' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'bob has language en' },
		])
	})

	// @e2e conditional-visibility::attribute-with-notequals-operator
	test('a language-not-equals-en inclusion shows the tile to a German user and not to an English one', async () => {
		const rule = include('attribute', {
			attribute: 'language',
			operator: 'not_equals',
			value: 'en',
		})
		const forCarol = await tileWith(v('carol'), 'language not en', [rule])
		const forBob = await tileWith(v('bob'), 'language not en', [rule])

		await expectVerdict(v('bob'), forBob, false, 'bob has language en')
		// SUSPECTED BUG: as above, `language` resolves to null and never matches.
		await expectVerdict(v('carol'), forCarol, true, 'carol has language de')
		await expectRendered(v('carol'), [
			{ ...forCarol, shown: true, why: 'de is not en' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'en is en' },
		])
	})

	// @e2e conditional-visibility::attribute-with-contains-operator
	test('an email-contains-@company.com inclusion shows the tile to a company address only', async () => {
		const rule = include('attribute', {
			attribute: 'email',
			operator: 'contains',
			value: '@company.com',
		})
		const forDave = await tileWith(v('dave'), 'email contains', [rule])
		const forBob = await tileWith(v('bob'), 'email contains', [rule])

		await expectVerdict(
			v('dave'),
			forDave,
			true,
			'dave has a @company.com address',
		)
		await expectVerdict(v('bob'), forBob, false, 'bob has an address elsewhere')
		await expectRendered(v('dave'), [
			{ ...forDave, shown: true, why: 'a @company.com address' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'an address elsewhere' },
		])
	})

	// @e2e conditional-visibility::non-existent-attribute
	test('a rule on an attribute nobody has never matches: an inclusion hides, an exclusion shows', async () => {
		const included = await tileWith(v('bob'), 'department include', [
			include('attribute', {
				attribute: 'department',
				operator: 'equals',
				value: 'finance',
			}),
		])
		// SUSPECTED BUG (launchpad#96): an exclude rule cannot be stored on PostgreSQL.
		const excluded = await tileWith(v('bob'), 'department exclude', [
			exclude('attribute', {
				attribute: 'department',
				operator: 'equals',
				value: 'finance',
			}),
		])

		await expectVerdict(
			v('bob'),
			included,
			false,
			'an unknown attribute never matches an inclusion',
		)
		await expectVerdict(
			v('bob'),
			excluded,
			true,
			'an unknown attribute never matches an exclusion',
		)
		await expectRendered(v('bob'), [
			{ ...included, shown: false, why: 'inclusion on an unknown attribute' },
			{ ...excluded, shown: true, why: 'exclusion on an unknown attribute' },
		])
	})
})

test.describe('REQ-VIS-009 several rules together', () => {
	// @e2e conditional-visibility::multiple-include-rules-at-least-one-matches
	test('two group inclusions show the tile to a user who matches only the second', async () => {
		const rules = [
			include('group', { groups: [G.marketing] }),
			include('group', { groups: [G.sales] }),
		]
		const forCarol = await tileWith(v('carol'), 'marketing or sales', rules)
		const forBob = await tileWith(v('bob'), 'marketing or sales', rules)

		await expectVerdict(
			v('carol'),
			forCarol,
			true,
			'carol is in sales, not marketing',
		)
		await expectVerdict(v('bob'), forBob, false, 'bob is in neither')
		await expectRendered(v('carol'), [
			{ ...forCarol, shown: true, why: 'one inclusion matches' },
		])
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'no inclusion matches' },
		])
	})

	// @e2e conditional-visibility::multiple-include-rules-none-match
	test('a marketing inclusion and an office-hours inclusion hide the tile from a non-member at 20:00', async () => {
		const pair = [
			include('group', { groups: [G.marketing] }),
			include('time', { startTime: '09:00', endTime: '17:00' }),
		]
		expect(
			await previewVisible(v('bob').who.api, pair, {
				groups: [],
				datetime: '2026-11-18T20:00:00',
			}),
		).toBe(false)
		expect(
			await previewVisible(v('bob').who.api, pair, {
				groups: [G.marketing],
				datetime: '2026-11-18T20:00:00',
			}),
		).toBe(true)

		const rules = [
			include('group', { groups: [G.marketing] }),
			include('time', NEVER_TIME),
		]
		const forBob = await tileWith(v('bob'), 'none match', rules)
		const forAlice = await tileWith(v('alice'), 'none match', rules)
		await expectVerdict(v('bob'), forBob, false, 'neither inclusion matches bob')
		await expectVerdict(
			v('alice'),
			forAlice,
			true,
			'CONTROL: the group inclusion matches alice',
		)
		await expectRendered(v('bob'), [
			{ ...forBob, shown: false, why: 'no inclusion matches' },
		])
		await expectRendered(v('alice'), [
			{ ...forAlice, shown: true, why: 'one inclusion matches' },
		])
	})

	// @e2e conditional-visibility::exclude-rule-overrides-include
	test('a matching date exclusion hides a tile whose group inclusion matches', async () => {
		const july = [
			include('group', { groups: [G.marketing] }),
			exclude('date', { startDate: '2026-07-01', endDate: '2026-07-31' }),
		]
		expect(
			await previewVisible(v('alice').who.api, july, {
				groups: [G.marketing],
				datetime: '2026-07-15T12:00:00',
			}),
		).toBe(false)
		expect(
			await previewVisible(v('alice').who.api, july, {
				groups: [G.marketing],
				datetime: '2026-08-15T12:00:00',
			}),
		).toBe(true)

		// SUSPECTED BUG (launchpad#96): an exclude rule cannot be stored on PostgreSQL.
		const overridden = await tileWith(v('alice'), 'exclude overrides', [
			include('group', { groups: [G.marketing] }),
			exclude('date', { startDate: PAST_START, endDate: FUTURE_END }),
		])
		const notOverridden = await tileWith(v('alice'), 'exclude not yet', [
			include('group', { groups: [G.marketing] }),
			exclude('date', { startDate: FUTURE_START, endDate: FUTURE_END }),
		])
		await expectVerdict(
			v('alice'),
			overridden,
			false,
			'the exclusion matches today',
		)
		await expectVerdict(
			v('alice'),
			notOverridden,
			true,
			'the exclusion does not match today',
		)
		await expectRendered(v('alice'), [
			{ ...overridden, shown: false, why: 'the exclusion matches today' },
			{
				...notOverridden,
				shown: true,
				why: 'the exclusion does not match today',
			},
		])
	})

	// @e2e conditional-visibility::no-rules-on-placement-with-isvisible1
	test('a visible placement without rules is shown', async () => {
		const bare = await tileWith(v('bob'), 'no rules', [])
		const ruled = await tileWith(v('bob'), 'no rules control', [
			include('group', { groups: [G.marketing] }),
		])
		const placement = (
			await readPlacements(v('bob').who.api, v('bob').dashboardId)
		).find((p) => Number(p.id) === bare.pid)
		expect(Number(placement?.isVisible), 'GIVEN isVisible: 1').toBe(1)
		expect((await readRules(v('bob').who.api, bare.pid)).rules).toEqual([])

		await expectVerdict(v('bob'), bare, true, 'no rules means no restriction')
		await expectVerdict(
			v('bob'),
			ruled,
			false,
			'CONTROL: a rule bob fails hides',
		)
		await expectRendered(v('bob'), [
			{ ...bare, shown: true, why: 'no rules means no restriction' },
			{ ...ruled, shown: false, why: 'CONTROL: a rule bob fails hides' },
		])
	})

	// @e2e conditional-visibility::no-include-rules-but-exclude-rules-exist
	test('exclusions alone show the tile until one of them matches', async () => {
		// SUSPECTED BUG (launchpad#96): an exclude rule cannot be stored on PostgreSQL.
		const quiet = await tileWith(v('bob'), 'exclude only quiet', [
			exclude('group', { groups: [G.contractors] }),
			exclude('date', { startDate: FUTURE_START }),
		])
		const firing = await tileWith(v('bob'), 'exclude only firing', [
			exclude('group', { groups: [G.contractors] }),
			exclude('date', { startDate: PAST_START }),
		])
		await expectVerdict(v('bob'), quiet, true, 'no exclusion matches')
		await expectVerdict(
			v('bob'),
			firing,
			false,
			'CONTROL: one exclusion matches',
		)
		await expectRendered(v('bob'), [
			{ ...quiet, shown: true, why: 'no exclusion matches' },
			{ ...firing, shown: false, why: 'one exclusion matches' },
		])
	})
})

test.describe('REQ-VIS-010 evaluation pipeline', () => {
	const MATCHING = [
		include('date', { startDate: PAST_START }),
		include('time', ALWAYS_TIME),
		include('group', { groups: [G.engineering] }),
	]

	// @e2e conditional-visibility::widget-with-isvisible0-bypasses-rule-evaluation
	test('a placement with isVisible 0 is hidden even though all three of its rules match', async () => {
		const off = await tileWith(v('bob'), 'flag off', MATCHING)
		const on = await tileWith(v('bob'), 'flag on', MATCHING)
		const res = await v('bob').who.api.put(`${API}/widgets/${off.pid}`, {
			data: { isVisible: 0 },
		})
		expect(res.status(), await res.text()).toBe(200)
		const flags = new Map(
			(await readPlacements(v('bob').who.api, v('bob').dashboardId)).map(
				(p) => [Number(p.id), Number(p.isVisible)],
			),
		)
		expect(flags.get(off.pid), 'GIVEN isVisible: 0').toBe(0)
		expect(flags.get(on.pid), 'CONTROL: isVisible: 1').toBe(1)

		await expectVerdict(
			v('bob'),
			on,
			true,
			'CONTROL: the same three rules show a visible placement',
		)
		await expectVerdict(
			v('bob'),
			off,
			false,
			'isVisible 0 hides regardless of rules',
		)
		await expectRendered(v('bob'), [
			{ ...on, shown: true, why: 'isVisible 1 and every rule matches' },
			{ ...off, shown: false, why: 'isVisible 0' },
		])
	})

	// @e2e conditional-visibility::widget-with-isvisible1-and-rules-triggers-evaluation
	test('a visible placement with two rules is decided by those rules', async () => {
		const failing = await tileWith(v('bob'), 'two rules failing', [
			include('group', { groups: [G.marketing] }),
			include('time', NEVER_TIME),
		])
		const passing = await tileWith(v('bob'), 'two rules passing', [
			include('group', { groups: [G.marketing] }),
			include('group', { groups: [G.engineering] }),
		])
		expect(
			(await readRules(v('bob').who.api, failing.pid)).rules,
			'both rules are loaded',
		).toHaveLength(2)
		await expectVerdict(v('bob'), failing, false, 'neither rule matches')
		await expectVerdict(v('bob'), passing, true, 'one rule matches')
		await expectRendered(v('bob'), [
			{ ...failing, shown: false, why: 'neither rule matches' },
			{ ...passing, shown: true, why: 'one rule matches' },
		])
	})

	// @e2e conditional-visibility::widget-with-isvisible1-and-no-rules-is-always-visible
	test('a visible placement with no rules is displayed to every principal', async () => {
		const tiles = [] as Array<{
			name: keyof typeof viewers
			tile: { pid: number; title: string }
		}>
		for (const name of ['alice', 'bob'] as const) {
			tiles.push({ name, tile: await tileWith(v(name), 'always visible', []) })
		}
		const control = await tileWith(v('alice'), 'always visible control', [
			include('group', { groups: [G.engineering] }),
		])
		for (const { name, tile } of tiles) {
			const read = await readRules(v(name).who.api, tile.pid)
			expect(read.rules).toEqual([])
			expect(read.isVisible, `${name}: no rules`).toBe(true)
		}
		await expectVerdict(
			v('alice'),
			control,
			false,
			'CONTROL: alice is not in engineering',
		)
		await expectRendered(v('alice'), [
			{ ...tiles[0].tile, shown: true, why: 'no rules' },
			{ ...control, shown: false, why: 'CONTROL: a rule alice fails' },
		])
		await expectRendered(v('bob'), [
			{ ...tiles[1].tile, shown: true, why: 'no rules' },
		])
	})
})
