/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for REQ-PPL-015: listing people is open to
 * every user (action baseline v9) and follows Nextcloud's user-enumeration
 * settings (Administration > Sharing).
 *
 * Three people, all named after this run's stamp so a search finds only
 * them: Pieter and Anna share a group, Bram is in another one. Pieter is an
 * ordinary user; the file does NOT grant `people-widget.get-users`, because
 * the shipped baseline must already let him list people.
 *
 * The enumeration keys are core app values; the file reads them first and
 * puts them back afterwards. It runs serially: the keys are instance-wide.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::every-user-may-list-people
 *   @e2e people-widget::listing-restricted-to-own-groups
 *   @e2e people-widget::enumeration-off-lists-nobody
 *
 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
 */

import type { APIRequestContext } from '@playwright/test'
import type { Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { describeRun, runOcc } from './support/occ.ts'
import { getPeople, mustGetPeople, provisionPerson, removeGroups, removePeople, stampedGroup } from './support/peopleWidget.ts'
import { adminContext } from './support/principals.ts'

const STAMP = `Enum${Date.now()}`
const ALLOW = 'shareapi_allow_share_dialog_user_enumeration'
const TO_GROUP = 'shareapi_restrict_user_enumeration_to_group'

test.describe.configure({ mode: 'serial' })

let admin: APIRequestContext
let pieter: Person
let anna: Person
let bram: Person
let groups: string[] = []
const saved: Record<string, string | null> = {}

/**
 * Read a core app value, or null when it is not set.
 *
 * @param key the key.
 * @return the value.
 */
async function coreValue(key: string): Promise<string | null> {
	const result = await runOcc('config:app:get', 'core', key)
	return result.code === 0 ? result.stdout.trim() : null
}

/**
 * Set (or delete, for null) a core app value.
 *
 * @param key the key.
 * @param value the value, or null to delete it.
 * @return nothing.
 */
async function setCoreValue(key: string, value: string | null): Promise<void> {
	const result = value === null
		? await runOcc('config:app:delete', 'core', key)
		: await runOcc('config:app:set', 'core', key, `--value=${value}`)
	if (value !== null && result.code !== 0) {
		throw new Error(`could not set core ${key}=${value}\n${describeRun(result)}`)
	}
}

/**
 * The display names Pieter finds when he searches for this run's stamp.
 *
 * @return the names, sorted.
 */
async function namesPieterFinds(): Promise<string[]> {
	const page = await mustGetPeople(pieter.api, { q: STAMP, limit: 100 })
	return page.users.map((user) => String(user.displayName)).sort()
}

test.beforeAll(async () => {
	admin = await adminContext()
	for (const key of [ALLOW, TO_GROUP]) {
		saved[key] = await coreValue(key)
	}
	const wonen = await stampedGroup(admin, 'e2e-enum-wonen')
	const zorg = await stampedGroup(admin, 'e2e-enum-zorg')
	groups = [wonen, zorg]
	pieter = await provisionPerson(admin, 'e2e-enum-pieter', { displayName: `${STAMP} Pieter`, groups: [wonen] })
	anna = await provisionPerson(admin, 'e2e-enum-anna', { displayName: `${STAMP} Anna`, groups: [wonen] })
	bram = await provisionPerson(admin, 'e2e-enum-bram', { displayName: `${STAMP} Bram`, groups: [zorg] })
})

test.afterAll(async () => {
	for (const key of [ALLOW, TO_GROUP]) {
		await setCoreValue(key, saved[key] ?? null)
	}
	await removePeople(admin, [pieter, anna, bram])
	await removeGroups(admin, groups)
	await admin.dispose()
})

test('an ordinary user may list people and sees everyone while enumeration is open @e2e people-widget::every-user-may-list-people', async () => {
	await setCoreValue(ALLOW, 'yes')
	await setCoreValue(TO_GROUP, 'no')

	const { status, body } = await getPeople(pieter.api, { limit: 1 })
	expect(status, JSON.stringify(body)).toBe(200)
	expect(await namesPieterFinds()).toEqual([`${STAMP} Anna`, `${STAMP} Bram`, `${STAMP} Pieter`])
})

test('restricted to own groups, only members of the viewer\'s groups are listed @e2e people-widget::listing-restricted-to-own-groups', async () => {
	await setCoreValue(ALLOW, 'yes')
	await setCoreValue(TO_GROUP, 'yes')

	expect(await namesPieterFinds()).toEqual([`${STAMP} Anna`, `${STAMP} Pieter`])
})

test('with enumeration off nobody is listed @e2e people-widget::enumeration-off-lists-nobody', async () => {
	await setCoreValue(ALLOW, 'no')

	expect(await namesPieterFinds()).toEqual([])
	const page = await mustGetPeople(pieter.api, { limit: 10 })
	expect(page.users).toEqual([])
})
