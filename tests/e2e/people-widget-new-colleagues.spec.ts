/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the "New colleagues" selection of the
 * People widget: the `people-widget` delta of the open change
 * `people-new-colleagues` (REQ-PEX-005, REQ-PPL-014), which gate 19 counts as
 * `people-widget::<slug>`. Written for e2e-coverage-programme (task 4.9).
 *
 * WHAT IS BUILT (development, 2026-11-25)
 * =======================================
 * The `date` profile-field type, the single start-date marker
 * (`ProfileFieldService`, the admin Profile fields tab),
 * `GET /api/people/new` (`NewColleaguesController`, `NewColleagues`), and in
 * LaunchPad the form's "New colleagues" choice (`PeopleWidgetForm`, offered
 * only when a start date field exists) and the list (`PeopleHost`, which
 * reads `selectionMode: 'new'` and adds a "Starts: <weekday>" line to a
 * colleague who has not started yet). All three scenarios are expected green.
 *
 * DATES
 * =====
 * The scenario fixes today at 5 October 2026. A test cannot move the server's
 * clock, and `NewColleagues` reads `gmdate('Y-m-d')`, so every start date is
 * an offset from today in UTC with the same shape: Thomas 7 days ago, Lotte 4
 * days ago, Yasmina 7 days ahead (inside the 14-day default), and two controls
 * outside the default window (40 days ago, 20 days ahead) who must not show.
 *
 * For its duration the file replaces the instance's profile-field
 * definitions with its own and puts the admin's back afterwards, as
 * `people-widget-profile-fields.spec.ts` does. It also grants
 * `people-widget.get-users` to everyone, which the endpoint checks.
 *
 * Gate-19 @e2e traceability (each tag sits on the test that proves it):
 *   people-widget::three-new-colleagues-on-the-intranet
 *   people-widget::no-start-date-field
 * `people-widget::start-date-from-ldap` is excluded in the delta spec: the CI
 * fixture has no directory server.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { gotoLaunchPad, openAddWidgetModal } from './fixtures/widget-flow.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	addPeoplePlacement,
	grantPeopleListing,
	peopleWidget,
	personItem,
	personItems,
	provisionPerson,
	removePeople,
	restoreActionMatrix,
} from './support/peopleWidget.ts'
import { adminContext, API, publishForRecipients } from './support/principals.ts'

const STAMP = `${Date.now()}`
const DEFINITIONS = `${API}/profile-fields/definitions`
const OWN = `${API}/profile-fields/me`
const NEW_COLLEAGUES = `${API}/people/new`

/** The start date field: a date, filled by the person, visible to everyone. */
const START_FIELD = {
	key: 'e2e_in_dienst_sinds',
	label: 'In dienst sinds',
	type: 'date',
	source: 'self',
	searchable: false,
	shownInWidget: false,
	visibility: 'everyone',
	startDate: true,
}

/** The same field, NOT marked as the start date. */
const PLAIN_DATE_FIELD = { ...START_FIELD, startDate: false }

const NEW_LABEL = /new colleagues|nieuwe collega/i

let admin: APIRequestContext
let matrixBefore: Record<string, string[]> | null = null
let definitionsBefore: unknown[] | null = null
const who: Record<string, Person> = {}
const dashboards: Array<SeededDashboard | null> = []

test.setTimeout(120_000)

/**
 * A calendar date `days` from today, in UTC, as `YYYY-MM-DD`.
 *
 * @param days offset in days (negative for the past).
 * @return the date.
 */
function isoFromToday(days: number): string {
	const now = new Date()
	const utc = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate())
	return new Date(utc + days * 86_400_000).toISOString().slice(0, 10)
}

/**
 * The weekday of an ISO date, in English and Dutch, as one alternation.
 *
 * @param iso the date.
 * @return e.g. `Monday|maandag`.
 */
function weekdayOf(iso: string): string {
	const date = new Date(`${iso}T12:00:00Z`)
	const name = (locale: string) => new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(date)
	return `${name('en')}|${name('nl')}`
}

/**
 * Replace the instance's profile-field definitions.
 *
 * @param fields the definitions.
 * @return nothing.
 */
async function useDefinitions(fields: unknown[]): Promise<void> {
	const saved = await admin.put(DEFINITIONS, { data: { fields } })
	expect(saved.status(), await saved.text()).toBe(200)
}

/**
 * Save a person's own start date.
 *
 * @param person the person.
 * @param date the date, `YYYY-MM-DD`.
 * @return nothing.
 */
async function saveStartDate(person: Person, date: string): Promise<void> {
	const res = await person.api.put(OWN, { data: { values: { [START_FIELD.key]: date } } })
	expect(res.status(), await res.text()).toBe(200)
}

/** Start dates, by person key. */
const STARTS = {
	thomas: isoFromToday(-7),
	lotte: isoFromToday(-4),
	yasmina: isoFromToday(7),
	longAgo: isoFromToday(-40),
	farAhead: isoFromToday(20),
}

test.beforeAll(async () => {
	test.setTimeout(300_000)
	admin = await adminContext()
	matrixBefore = await grantPeopleListing(admin)

	const current = await admin.get(DEFINITIONS)
	expect(current.status(), await current.text()).toBe(200)
	definitionsBefore = ((await current.json()).fields ?? []) as unknown[]
	await useDefinitions([START_FIELD])

	who.pieter = await provisionPerson(admin, 'e2e-pnc-pieter', { displayName: `Pieter Postma ${STAMP}` })
	who.thomas = await provisionPerson(admin, 'e2e-pnc-thomas', {
		displayName: `Thomas Brouwer ${STAMP}`,
		self: { role: 'Jurist', organisation: 'Team Vergunningen' },
	})
	who.lotte = await provisionPerson(admin, 'e2e-pnc-lotte', {
		displayName: `Lotte Verhoeven ${STAMP}`,
		self: { role: 'Communicatieadviseur', organisation: 'Team Communicatie' },
	})
	who.yasmina = await provisionPerson(admin, 'e2e-pnc-yasmina', {
		displayName: `Yasmina El Amrani ${STAMP}`,
		self: { role: 'Beleidsadviseur', organisation: 'Team Wonen' },
	})
	who.longAgo = await provisionPerson(admin, 'e2e-pnc-old', { displayName: `Gert Langgeleden ${STAMP}` })
	who.farAhead = await provisionPerson(admin, 'e2e-pnc-later', { displayName: `Femke Laterdan ${STAMP}` })

	for (const key of Object.keys(STARTS) as Array<keyof typeof STARTS>) {
		await saveStartDate(who[key], STARTS[key])
	}
})

test.afterAll(async () => {
	test.setTimeout(120_000)
	for (const dashboard of dashboards) {
		await removeSeededDashboard(admin, dashboard)
	}
	await removePeople(admin, Object.values(who))
	if (definitionsBefore !== null) {
		await admin.put(DEFINITIONS, { data: { fields: definitionsBefore } })
	}
	await restoreActionMatrix(admin, matrixBefore)
	await admin.dispose()
})

/** The uids of this file's people, in the order an answer lists them. */
function ours(users: Array<Record<string, unknown>>): string[] {
	const mine = new Set(Object.values(who).map((person) => person.username))
	return users.map((user) => String(user.id)).filter((id) => mine.has(id))
}

test.describe('REQ-PPL-014: the people widget lists new colleagues', () => {
	// @e2e people-widget::three-new-colleagues-on-the-intranet
	test('the endpoint lists Yasmina (not started yet), then Lotte, then Thomas, and no one outside the window', async () => {
		await useDefinitions([START_FIELD])
		const res = await who.pieter.api.get(NEW_COLLEAGUES)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.configured).toBe(true)
		// The defaults the scenario names.
		expect(body.since).toBe(30)
		expect(body.until).toBe(14)

		const users = body.users as Array<Record<string, unknown>>
		expect(ours(users)).toEqual([who.yasmina.username, who.lotte.username, who.thomas.username])

		const entry = (person: Person) => users.find((user) => user.id === person.username)!
		expect(entry(who.yasmina).startDate).toBe(STARTS.yasmina)
		expect(entry(who.yasmina).starts, 'Yasmina has not started yet').toBe(true)
		expect(entry(who.lotte).starts).toBe(false)
		expect(entry(who.thomas).starts).toBe(false)
		expect(entry(who.yasmina).displayName).toBe(who.yasmina.displayName)
	})

	// @e2e people-widget::three-new-colleagues-on-the-intranet
	test('Pieter opens the intranet and "New colleagues" lists Yasmina with her start day, then Lotte, then Thomas, each with role and team', async ({
		browser,
	}) => {
		await useDefinitions([START_FIELD])
		const dashboard = await seedActiveDashboard(admin, `Intranet Zuiddrecht ${STAMP}`)
		dashboards.push(dashboard)
		await addPeoplePlacement(admin, dashboard.id, {
			content: { layout: 'list', selectionMode: 'new', showFields: ['role', 'organisation'] },
		})
		await publishForRecipients(admin, dashboard.id)
		const share = await admin.post(`${API}/dashboard/${dashboard.id}/shares`, {
			data: { shareType: 'user', shareWith: who.pieter.username, permissionLevel: 'view_only' },
		})
		expect(share.status(), await share.text()).toBeLessThan(300)
		const active = await who.pieter.api.post(`${API}/dashboards/active`, {
			data: { uuid: dashboard.uuid },
			timeout: 60_000,
		})
		expect(active.status(), await active.text()).toBeLessThan(300)

		const { context, page } = await loginAs(browser, who.pieter.username, who.pieter.password)
		try {
			await gotoLaunchPad(page)
			const widget = peopleWidget(page)
			await expect(widget).toBeVisible({ timeout: 30_000 })
			await expect(page.getByText(NEW_LABEL).first()).toBeVisible({ timeout: 15_000 })

			const yasmina = personItem(widget, who.yasmina.displayName)
			const lotte = personItem(widget, who.lotte.displayName)
			const thomas = personItem(widget, who.thomas.displayName)
			await expect(yasmina).toBeVisible({ timeout: 20_000 })
			await expect(lotte).toBeVisible()
			await expect(thomas).toBeVisible()

			// Outside the default window (30 back, 14 ahead): not listed.
			await expect(personItem(widget, who.longAgo.displayName)).toHaveCount(0)
			await expect(personItem(widget, who.farAhead.displayName)).toHaveCount(0)
			// Nor is Pieter, who has no start date.
			await expect(personItem(widget, who.pieter.displayName)).toHaveCount(0)

			// Newest start first.
			const order = (await personItems(widget).allInnerTexts())
				.map((text) => [who.yasmina, who.lotte, who.thomas].findIndex((p) => text.includes(p.displayName)))
				.filter((index) => index >= 0)
			expect(order).toEqual([0, 1, 2])

			// Yasmina is marked with the day she starts; the others have started.
			await expect(yasmina).toContainText(new RegExp(`(begint|starts):?\\s+(on\\s+)?(${weekdayOf(STARTS.yasmina)})`, 'i'))
			await expect(lotte).not.toContainText(/begint|starts/i)
			await expect(thomas).not.toContainText(/begint|starts/i)

			// Each with role and team.
			await expect(yasmina).toContainText('Beleidsadviseur')
			await expect(yasmina).toContainText('Team Wonen')
			await expect(lotte).toContainText('Communicatieadviseur')
			await expect(lotte).toContainText('Team Communicatie')
			await expect(thomas).toContainText('Jurist')
			await expect(thomas).toContainText('Team Vergunningen')
		} finally {
			await context.close()
		}
	})

	// @e2e people-widget::no-start-date-field
	test('with no field marked as the start date, the people form offers no "New colleagues"', async ({ page }) => {
		// A date field exists, but none is the start date.
		await useDefinitions([PLAIN_DATE_FIELD])
		const read = await admin.get(DEFINITIONS)
		const fields = ((await read.json()).fields ?? []) as Array<Record<string, unknown>>
		expect(fields.filter((field) => field.startDate === true)).toHaveLength(0)

		// The server agrees there is no start date field.
		const answer = await admin.get(NEW_COLLEAGUES)
		expect(answer.status(), await answer.text()).toBe(200)
		const body = await answer.json()
		expect(body.configured).toBe(false)
		expect(body.users).toEqual([])

		// The modal is reached through a personal dashboard's row menu.
		dashboards.push(await seedActiveDashboard(admin, `E2E People new form ${STAMP}`))
		await gotoLaunchPad(page)
		const dialog = await openPeopleForm(page)

		// Nothing in the form names the mode...
		await expect(dialog.getByText(NEW_LABEL)).toHaveCount(0)
		// ...and no option of any of its selects does.
		await expect(dialog.locator('option').filter({ hasText: NEW_LABEL })).toHaveCount(0)
		const selects = dialog.locator('.cn-people-widget-form .v-select')
		const count = await selects.count()
		expect(count, 'the people form renders its selects').toBeGreaterThan(0)
		for (let index = 0; index < count; index++) {
			await selects.nth(index).locator('.vs__dropdown-toggle').click()
			await expect(page.locator('.vs__dropdown-option').first()).toBeVisible({ timeout: 5_000 })
			await expect(page.locator('.vs__dropdown-option').filter({ hasText: NEW_LABEL })).toHaveCount(0)
			await page.keyboard.press('Escape')
		}
		await expect(page.getByRole('radio', { name: NEW_LABEL })).toHaveCount(0)
	})
})

/**
 * Open the Add Widget modal with the People type chosen.
 *
 * @param page the page, on the workspace.
 * @return the dialog.
 */
async function openPeopleForm(page: Page): Promise<Locator> {
	await openAddWidgetModal(page, 'people')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog.getByLabel(/group filter/i)).toBeVisible({ timeout: 10_000 })
	return dialog
}
