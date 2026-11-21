/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for custom profile fields and expertise in the
 * People widget (openspec/specs/people-widget REQ-PEX-002..004), written for
 * e2e-coverage-programme.
 *
 * For its duration the file replaces the instance's profile-field definitions
 * with two of its own, and puts the admin's definitions back afterwards:
 *
 *   e2e_expertise     "Expertise", tags, filled by the person, searchable,
 *                     shown in the widget, visible to everyone
 *   e2e_kostenplaats  "Kostenplaats", text, filled by the person, searchable,
 *                     shown in the widget, visible to the person's groups only
 *
 * People (all provisioned here):
 *
 *   Pieter  tags "subsidies", "Omgevingswet"; kostenplaats KP-…; group gp + gw
 *   Ilse    tag "Omgevingswet"; group gw
 *   Dirk    tag "Archief"; group gw
 *   Mirjam  group gp (shares a group with Pieter)
 *   Sanne   group gs (shares none with Pieter)
 *   Karin   a PRIVATE biography mentioning subsidies; group gk
 *   Kees    a biography mentioning subsidies at the default scope; group gk
 *   Joost   the person who fills in his own expertise through the settings
 *
 * Mirjam and Sanne are ordinary colleagues, so the spec grants
 * `people-widget.get-users` to everyone for its duration (see
 * support/peopleWidget.ts). The LDAP-sourced scenario is excluded in the spec:
 * the CI fixture has no directory server.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::pieter-adds-his-subjects
 *   @e2e people-widget::sanne-finds-the-subsidies-expert
 *   @e2e people-widget::clicking-a-tag-searches-for-it
 *   @e2e people-widget::private-biography-is-not-searchable
 *   @e2e people-widget::group-only-field
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { removeSeededDashboard } from './support/dashboardFixture.ts'
import {
	entryOf,
	grantPeopleListing,
	groupFilter,
	mustGetPeople,
	peopleWidget,
	personItem,
	provisionPerson,
	removeGroups,
	removePeople,
	restoreActionMatrix,
	searchBox,
	seedPeopleDashboard,
	stampedGroup,
} from './support/peopleWidget.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const COST_CENTRE = `KP-${STAMP}`
const DEFINITIONS = `${API}/profile-fields/definitions`
const OWN = `${API}/profile-fields/me`

const EXPERTISE = {
	key: 'e2e_expertise',
	label: 'Expertise',
	type: 'tags',
	source: 'self',
	searchable: true,
	shownInWidget: true,
	visibility: 'everyone',
}
const COST = {
	key: 'e2e_kostenplaats',
	label: 'Kostenplaats',
	type: 'text',
	source: 'self',
	searchable: true,
	shownInWidget: true,
	visibility: 'groups',
}

let admin: APIRequestContext
let matrixBefore: Record<string, string[]> | null = null
let definitionsBefore: unknown[] | null = null
const groups: Record<string, string> = {}
const who: Record<string, Person> = {}
let dashboard: SeededDashboard | null = null

test.setTimeout(120_000)

/**
 * Save a person's own custom field values through the API.
 *
 * @param person the person.
 * @param values field key to value (a list for tags).
 * @return nothing.
 */
async function saveOwn(person: Person, values: Record<string, unknown>): Promise<void> {
	const res = await person.api.put(OWN, { data: { values } })
	expect(res.status(), await res.text()).toBe(200)
}

test.beforeAll(async () => {
	test.setTimeout(360_000)
	admin = await adminContext()
	matrixBefore = await grantPeopleListing(admin)

	const current = await admin.get(DEFINITIONS)
	expect(current.status(), await current.text()).toBe(200)
	definitionsBefore = ((await current.json()).fields ?? []) as unknown[]
	const saved = await admin.put(DEFINITIONS, { data: { fields: [EXPERTISE, COST] } })
	expect(saved.status(), await saved.text()).toBe(200)

	for (const name of ['gp', 'gs', 'gw', 'gk', 'gn']) {
		groups[name] = await stampedGroup(admin, `e2e-pex-${name}`)
	}
	who.pieter = await provisionPerson(admin, 'e2e-pex-pieter', {
		displayName: `Pieter Postma ${STAMP}`,
		groups: [groups.gp, groups.gw],
	})
	who.ilse = await provisionPerson(admin, 'e2e-pex-ilse', {
		displayName: `Ilse Smit ${STAMP}`,
		groups: [groups.gw],
	})
	who.dirk = await provisionPerson(admin, 'e2e-pex-dirk', {
		displayName: `Dirk Hendriks ${STAMP}`,
		groups: [groups.gw],
	})
	who.mirjam = await provisionPerson(admin, 'e2e-pex-mirjam', {
		displayName: `Mirjam Kok ${STAMP}`,
		groups: [groups.gp],
	})
	who.sanne = await provisionPerson(admin, 'e2e-pex-sanne', {
		displayName: `Sanne Vos ${STAMP}`,
		groups: [groups.gs],
	})
	who.karin = await provisionPerson(admin, 'e2e-pex-karin', {
		displayName: `Karin Bos ${STAMP}`,
		groups: [groups.gk],
		self: {
			biographyScope: 'v2-private',
			biography: `Karin weet alles van subsidies ${STAMP}`,
		},
	})
	who.kees = await provisionPerson(admin, 'e2e-pex-kees', {
		displayName: `Kees Dekker ${STAMP}`,
		groups: [groups.gk],
		self: {
			biographyScope: 'v2-local',
			biography: `Kees weet alles van subsidies ${STAMP}`,
		},
	})
	who.joost = await provisionPerson(admin, 'e2e-pex-joost', {
		displayName: `Joost Jansen ${STAMP}`,
		groups: [groups.gn],
	})

	await saveOwn(who.pieter, { e2e_expertise: ['subsidies', 'Omgevingswet'], e2e_kostenplaats: COST_CENTRE })
	await saveOwn(who.ilse, { e2e_expertise: ['Omgevingswet'] })
	await saveOwn(who.dirk, { e2e_expertise: ['Archief'] })
})

test.afterAll(async () => {
	test.setTimeout(120_000)
	await removePeople(admin, Object.values(who))
	await removeGroups(admin, Object.values(groups))
	if (definitionsBefore !== null) {
		await admin.put(DEFINITIONS, { data: { fields: definitionsBefore } })
	}
	await restoreActionMatrix(admin, matrixBefore)
	await admin.dispose()
})

test.afterEach(async () => {
	await removeSeededDashboard(admin, dashboard)
	dashboard = null
})

/**
 * Open the admin's dashboard with a card-layout People widget on a group.
 *
 * @param page the page.
 * @param group the group to show.
 * @return the widget root.
 */
async function openCards(page: Page, group: string): Promise<Locator> {
	dashboard = await seedPeopleDashboard(admin, `E2E People expertise ${STAMP}`, [
		{ content: { layout: 'card', filters: groupFilter(group) } },
	])
	await gotoLaunchPad(page)
	const widget = peopleWidget(page, 'card')
	await expect(widget).toBeVisible({ timeout: 20_000 })
	return widget
}

/**
 * A tag shown on a card.
 *
 * @param card the card.
 * @param tag the tag text.
 * @return the tag element.
 */
function tagOn(card: Locator, tag: string): Locator {
	return card.getByRole('button', { name: new RegExp(`\\b${tag}\\b`, 'i') }).first()
}

/**
 * The custom field of one key on a people entry.
 *
 * @param entry the entry.
 * @param key the field key.
 * @return the field, or undefined.
 */
function customField(entry: Record<string, unknown> | undefined, key: string): Record<string, unknown> | undefined {
	const fields = (entry?.customFields ?? []) as Array<Record<string, unknown>>
	return fields.find((field) => field.key === key)
}

test.describe('REQ-PEX-002: a person lists their own expertise', () => {
	// @e2e people-widget::pieter-adds-his-subjects
	test('tags added and saved in the personal settings show on the person\'s card', async ({ browser, page }) => {
		const joost = who.joost
		const session = await loginAs(browser, joost.username, joost.password)
		try {
			const settings = session.page
			await settings.goto('/index.php/apps/launchpad/#user-settings')
			// NcAppSettingsSection prefixes the section id with `settings-section_`.
			const section = settings.locator('#settings-section_launchpad-profile-fields')
			await expect(section).toBeVisible({ timeout: 30_000 })

			// NcSelect draws its inputLabel outside the `.v-select` box; the
			// search input is the combobox it names.
			const input = section.getByRole('combobox', { name: 'Expertise' })
			const tags = section
				.locator('.v-select')
				.filter({ has: settings.getByRole('combobox', { name: 'Expertise' }) })
			for (const tag of ['subsidies', 'Omgevingswet']) {
				await input.click()
				await input.fill(tag)
				await input.press('Enter')
				await expect(tags.locator('.vs__selected').filter({ hasText: tag })).toBeVisible({ timeout: 5_000 })
			}
			await section.getByRole('button', { name: /^save$/i }).click()
			await expect(section.getByText('Your profile fields are saved.')).toBeVisible({ timeout: 15_000 })
		} finally {
			await session.context.close()
		}

		// Persisted, read back as Joost himself.
		const own = await joost.api.get(OWN)
		expect(own.status(), await own.text()).toBe(200)
		const expertise = ((await own.json()).fields as Array<Record<string, unknown>>)
			.find((field) => field.key === EXPERTISE.key)
		expect(expertise?.values).toEqual(['subsidies', 'Omgevingswet'])

		// And his card shows the two tags.
		const widget = await openCards(page, groups.gn)
		const card = personItem(widget, joost.displayName)
		await expect(card).toBeVisible({ timeout: 20_000 })
		await expect(tagOn(card, 'subsidies')).toBeVisible()
		await expect(tagOn(card, 'Omgevingswet')).toBeVisible()
	})
})

test.describe('REQ-PEX-003: search by profile and expertise', () => {
	// @e2e people-widget::sanne-finds-the-subsidies-expert
	test('"subsidie" finds Pieter by his tag, with the tag highlighted', async ({ page }) => {
		const widget = await openCards(page, groups.gw)
		await expect(personItem(widget, who.dirk.displayName)).toBeVisible({ timeout: 20_000 })

		// Neither Pieter's name nor an email holds "subsidie": only a search
		// that reaches his tags can find him.
		await searchBox(widget).fill('subsidie')
		const card = personItem(widget, who.pieter.displayName)
		await expect(card).toBeVisible({ timeout: 15_000 })
		await expect(personItem(widget, who.ilse.displayName)).toHaveCount(0)
		await expect(personItem(widget, who.dirk.displayName)).toHaveCount(0)

		// Highlighted: the matching tag is set apart from the one that does not match.
		const look = (tag: Locator) => tag.evaluate((el) => {
			const style = getComputedStyle(el)
			return `${style.fontWeight}|${style.backgroundColor}|${style.borderColor}|${el.className}`
		})
		await expect(tagOn(card, 'subsidies')).toBeVisible()
		expect(await look(tagOn(card, 'subsidies'))).not.toBe(await look(tagOn(card, 'Omgevingswet')))
	})

	// @e2e people-widget::clicking-a-tag-searches-for-it
	test('clicking a tag shows everyone with that tag', async ({ page }) => {
		const widget = await openCards(page, groups.gw)
		const pieter = personItem(widget, who.pieter.displayName)
		await expect(pieter).toBeVisible({ timeout: 20_000 })

		await tagOn(pieter, 'Omgevingswet').click()
		await expect(searchBox(widget)).toHaveValue('Omgevingswet')
		await expect(personItem(widget, who.pieter.displayName)).toBeVisible({ timeout: 15_000 })
		await expect(personItem(widget, who.ilse.displayName)).toBeVisible()
		await expect(personItem(widget, who.dirk.displayName)).toHaveCount(0)
	})
})

test.describe('REQ-PEX-004: visibility governs search and display', () => {
	// @e2e people-widget::private-biography-is-not-searchable
	test('a private biography neither matches a colleague\'s search nor shows to them', async () => {
		const sanne = who.sanne
		const found = await mustGetPeople(sanne.api, { q: `subsidies ${STAMP}`, limit: 100 })
		const uids = found.users.map((user) => user.uid)
		// Control: the same words in a biography at the default scope DO match,
		// so the miss below is the scope rule, not a search that never looks.
		expect(uids).toContain(who.kees.username)
		expect(uids).not.toContain(who.karin.username)

		const listed = await mustGetPeople(sanne.api, { filters: groupFilter(groups.gk) })
		expect(entryOf(listed, who.kees.username)?.biography).toBe(`Kees weet alles van subsidies ${STAMP}`)
		const karin = entryOf(listed, who.karin.username)
		expect(karin, 'Karin herself is still listed').toBeTruthy()
		expect(Object.keys(karin!)).not.toContain('biography')
		expect(JSON.stringify(karin)).not.toContain('subsidies')
	})

	// @e2e people-widget::group-only-field
	test('a groups-only field neither shows nor matches for someone outside those groups', async () => {
		const outside = await mustGetPeople(who.sanne.api, { filters: groupFilter(groups.gp) })
		const pieterOutside = entryOf(outside, who.pieter.username)
		expect(pieterOutside, 'Pieter is listed for Sanne').toBeTruthy()
		expect(customField(pieterOutside, COST.key)).toBeUndefined()
		expect(JSON.stringify(pieterOutside)).not.toContain(COST_CENTRE)
		const searchOutside = await mustGetPeople(who.sanne.api, { q: COST_CENTRE })
		expect(searchOutside.users.map((user) => user.uid)).not.toContain(who.pieter.username)

		// Control: Mirjam shares a group with Pieter, and sees and finds it.
		const inside = await mustGetPeople(who.mirjam.api, { filters: groupFilter(groups.gp) })
		expect(customField(entryOf(inside, who.pieter.username), COST.key)?.values).toEqual([COST_CENTRE])
		const searchInside = await mustGetPeople(who.mirjam.api, { q: COST_CENTRE })
		expect(searchInside.users.map((user) => user.uid)).toContain(who.pieter.username)
	})
})
