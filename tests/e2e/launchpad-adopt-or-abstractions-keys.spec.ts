/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Key list is single-source-of-truth" (openspec/specs/launchpad-adopt-or-abstractions,
 * REQ "AdminSetting keys MUST live under a single typed list"), written for
 * e2e-coverage-programme task 5.8.
 *
 * It was excluded as a source-organisation rule with nothing to observe. The
 * deployed app folder is reachable through support/occ.ts, the way
 * launchpad-adopt-or-abstractions-declared.spec.ts reads info.xml and the
 * class headers there, so the rule is judged on the code the instance runs:
 * every key in the deployed `lib/Db/AdminSettingKey.php` is named in that
 * file's docblock, and no other file under the deployed `lib/` declares a
 * constant holding the same string literal (an alias such as
 * `AdminSetting::KEY_X = AdminSettingKey::X->value` is not a literal and is
 * allowed for BC, as the requirement says).
 *
 * EXPECTED RED until the product is fixed (found 2026-11-25 on development):
 *  - lib/Service/AnnouncementService.php declares
 *    `EDITOR_GROUPS_SETTING = 'announcement_editor_groups'`, a second literal
 *    for AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS;
 *  - the AdminSettingKey docblock's "Groups:" list names 15 of the 35 cases.
 *
 * Gate-19 @e2e traceability:
 *   @e2e launchpad-adopt-or-abstractions::key-list-is-single-source-of-truth
 *
 * @spec openspec/specs/launchpad-adopt-or-abstractions/spec.md
 */

import { expect, test } from '@playwright/test'
import {
	appPathOnInstance,
	describeRun,
	onInstance,
	readOnInstance,
} from './support/occ.ts'

/** The enum, relative to the app folder. */
const KEY_FILE = 'lib/Db/AdminSettingKey.php'

// @e2e launchpad-adopt-or-abstractions::key-list-is-single-source-of-truth
test('every AdminSettingKey case is in its docblock, and no other deployed lib/ file declares a constant with the same key literal', async () => {
	const appPath = await appPathOnInstance()
	const source = await readOnInstance(`${appPath}/${KEY_FILE}`)

	// GIVEN the AdminSettingKey const-list: a backed enum of string cases.
	expect(source, 'AdminSettingKey is a string-backed enum').toMatch(/\benum\s+AdminSettingKey\s*:\s*string\b/)
	const cases = [...source.matchAll(/^\s*case\s+(\w+)\s*=\s*'([^']+)'\s*;/gm)]
		.map((match) => ({ name: match[1], value: match[2] }))
	expect(cases.length, 'the enum carries its cases').toBeGreaterThan(30)
	expect(
		new Set(cases.map((entry) => entry.value)).size,
		'no two cases share a key string',
	).toBe(cases.length)

	// THEN every key is in the list AND in the docblock: the doc comments that
	// precede the enum declaration name each case.
	const declaration = source.search(/\benum\s+AdminSettingKey\b/)
	const docblocks = [...source.slice(0, declaration).matchAll(/\/\*\*[\s\S]*?\*\//g)]
		.map((match) => match[0])
		.join('\n')
	expect(docblocks, 'the enum has a docblock').not.toBe('')
	expect.soft(
		cases.filter((entry) => !new RegExp(`\\b${entry.name}\\b`).test(docblocks)).map((entry) => entry.name),
		'every AdminSettingKey case is named in the enum\'s docblock',
	).toEqual([])

	// AND no other file in lib/ defines a duplicate string literal for the same
	// logical key: a class constant (typed or not) whose value is a key string.
	const values = cases.map((entry) => entry.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
	const pattern = `const\\s+(\\w+\\s+)?\\w+\\s*=\\s*['"](${values.join('|')})['"]`
	const listing = await onInstance('grep', '-rnE', '--include=*.php', pattern, `${appPath}/lib`)
	// grep exits 1 for "no match"; anything above that is a failed run.
	expect(listing.code, describeRun(listing)).toBeLessThan(2)
	const duplicates = listing.stdout
		.split('\n')
		.map((line) => line.trim())
		.filter(Boolean)
		.map((line) => line.slice(appPath.length + 1))
		.filter((line) => !line.startsWith(`${KEY_FILE}:`))
	expect.soft(duplicates, 'no other lib/ file declares a constant holding an AdminSettingKey string').toEqual([])
})
