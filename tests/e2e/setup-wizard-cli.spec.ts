/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for `occ launchpad:setup --config=<file>`
 * (openspec/specs/setup-wizard, REQ-WIZ-010), written for the
 * e2e-coverage-programme, task 3.5.
 *
 * WHY THIS IS AN E2E AND NOT ONLY A UNIT TEST
 * ==========================================
 * REQ-WIZ-010 was excluded with "an occ command reading a YAML file on the
 * server cannot be driven from Playwright". It can, and the suite already does
 * it: support/occ.ts runs occ on the instance under test and writes files
 * there, which is how cli-commands.spec.ts covers the `launchpad:*` commands.
 * tests/Unit/Command/SetupCommandTest.php feeds the command a mocked wizard
 * service and a mocked settings service, so it cannot see what an operator's
 * deploy script sees: the process exit code, the lines on the real console,
 * and what the real services store. Here the YAML file is written on the
 * instance, the real occ binary runs the command, and the outcome is read back
 * through the API a person's browser uses.
 *
 * WHAT IS EXPECTED RED
 * ====================
 * lib/Command/SetupCommand.php applies Step 2 only. Steps 3, 4 and 5 print
 * "skipped (capability pending)" when the file configures them and do
 * nothing, although the wizard modal installs demos (Step 3) and gives a group
 * the admin role (Step 4) since #1920. So in "CLI command accepts YAML config
 * file" the Step 3, Step 4 and Step 5 clauses are expected red; they are soft
 * assertions, so the Step 2 and Step 6 clauses are still reported.
 *
 * Two inputs of "CLI command validates YAML schema" are expected red as well:
 * SetupCommand::readConfig() only refuses a top level that is not an array,
 * and a YAML list IS an array in PHP, so a list at the top level runs and
 * completes the wizard; and applyGroupOrderStep() only refuses a
 * `group_priority_order` that is not an array, so a map of strings is stored
 * as the group order.
 *
 * WHAT THIS FILE CHANGES, AND PUTS BACK
 * ====================================
 * The command writes the `group_order` and `setup_wizard_complete` rows of
 * `oc_launchpad_admin_settings`. Both are snapshotted in beforeAll and put back
 * exactly (support/adminSettingRows.ts) in afterAll. Every test starts by
 * removing the `setup_wizard_complete` row and requiring the state endpoint
 * to read `complete: false`, so "the wizard completes" is shown by the command
 * and not inherited from an earlier run. The footer settings, the role rows and
 * the installed showcases are snapshotted and restored too, and the two groups
 * and the YAML files are removed. The suite runs with one worker, so no other
 * file reads these rows while this one changes them. The file is NOT serial on
 * purpose: the first test is expected red, and in serial mode its failure
 * would skip the five after it. After a failure Playwright tears the worker
 * down (afterAll puts everything back) and the next test starts a fresh one,
 * whose beforeAll snapshots the restored state.
 *
 * Gate-19 @e2e traceability:
 *   @e2e setup-wizard::cli-command-accepts-yaml-config-file
 *   @e2e setup-wizard::cli-command-validates-yaml-schema
 *   @e2e setup-wizard::an-old-config-that-still-sets-storagebackend-keeps-working
 *   @e2e setup-wizard::cli-command-is-verbose-by-default
 *   @e2e setup-wizard::cli-command-skips-optional-steps-if-not-in-config
 *   @e2e setup-wizard::cli-command-is-idempotent
 *
 * @spec openspec/specs/setup-wizard/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SettingRow } from './support/adminSettingRows.ts'
import type { FooterSettings } from './support/footerSettings.ts'
import type { RunResult } from './support/occ.ts'

import { expect, test } from '@playwright/test'
import {
	dropSettingRows,
	putBackSettingRows,
	settingRows,
} from './support/adminSettingRows.ts'
import { readFooter, restoreFooter } from './support/footerSettings.ts'
import {
	appValue,
	describeRun,
	removeOnInstance,
	runOcc,
	writeOnInstance,
} from './support/occ.ts'
import { adminContext, API, createGroup, removeGroup } from './support/principals.ts'

const RUN = Date.now()
const WIZARD_API = `${API}/admin/setup-wizard`
const GROUPS_API = `${API}/admin/groups`
const SHOWCASES_API = `${API}/admin/demo-showcases`
const ROLES_API = `${API}/admin/roles`
const COMPLETE_KEY = 'setup_wizard_complete'
const GROUP_ORDER_KEY = 'group_order'
const CONTENT_STORAGE_KEY = 'content_storage'
const GROUP_A = `e2e-cli-a-${RUN}`
const GROUP_B = `e2e-cli-b-${RUN}`
const FINAL_LINE = 'Setup wizard completed successfully.'

type WizardState = {
	complete: boolean
	currentRecommendedStep: number
	stepStatuses: Record<string, string>
}
type Showcase = { id: string; name: string; isInstalled: boolean }
type RoleRow = {
	id: number
	groupId?: string | null
	role: string
	assignedBy?: string
}

let admin: APIRequestContext
let storedComplete: SettingRow[] = []
let storedGroupOrder: SettingRow[] = []
let storedFooter: FooterSettings | undefined
let rolesBefore: RoleRow[] = []
let showcasesBefore: Showcase[] = []
const written: string[] = []

/**
 * A GET as the administrator that must answer 200, returning its JSON.
 *
 * @param url the API path.
 * @return the parsed body.
 */
async function readJson<T>(url: string): Promise<T> {
	const res = await admin.get(url)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * The wizard state, as the admin page reads it.
 *
 * @return the state.
 */
async function readState(): Promise<WizardState> {
	return readJson<WizardState>(`${WIZARD_API}/state`)
}

/**
 * The stored group priority order, as the admin page reads it.
 *
 * @return the active group ids in order.
 */
async function readGroupOrder(): Promise<string[]> {
	return (await readJson<{ active: string[] }>(GROUPS_API)).active
}

/**
 * Write the group priority order through the admin API.
 *
 * @param groups group ids in priority order.
 * @return nothing.
 */
async function setGroupOrder(groups: string[]): Promise<void> {
	const res = await admin.post(GROUPS_API, { data: { groups } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Write a YAML file on the instance and remember it for removal.
 *
 * @param name a name unique within this file.
 * @param yaml the file content.
 * @return its path on the instance.
 */
async function yamlOnInstance(name: string, yaml: string): Promise<string> {
	const file = `/tmp/launchpad-e2e-setup-${RUN}-${name}.yaml`
	await writeOnInstance(file, yaml)
	written.push(file)
	return file
}

/**
 * Run `occ launchpad:setup --config=<file>`.
 *
 * @param file the YAML path on the instance.
 * @return the run.
 */
async function setup(file: string): Promise<RunResult> {
	return runOcc('launchpad:setup', `--config=${file}`)
}

/**
 * The non-empty output lines of a run, both streams, trimmed.
 *
 * @param result the run.
 * @return the lines in order.
 */
function lines(result: RunResult): string[] {
	return `${result.stdout}\n${result.stderr}`
		.split('\n')
		.map((line) => line.trim())
		.filter((line) => line !== '')
}

/**
 * The GIVEN every test needs: the wizard reads as not completed, so a
 * completed state afterwards is the command's doing.
 *
 * @return nothing.
 */
async function wizardNotCompleted(): Promise<void> {
	await dropSettingRows(COMPLETE_KEY)
	expect((await readState()).complete).toBe(false)
}

/**
 * Two showcases that are not installed yet, to name in `demo_packages`.
 *
 * @return their ids.
 */
async function twoUninstalledShowcases(): Promise<string[]> {
	const free = (await readJson<Showcase[]>(SHOWCASES_API)).filter(
		(s) => !s.isInstalled,
	)
	expect(
		free.length,
		'the instance needs two showcases that are not installed',
	).toBeGreaterThanOrEqual(2)
	return [free[0].id, free[1].id]
}

/**
 * The ids of the installed showcases.
 *
 * @return the ids, sorted.
 */
async function installedShowcases(): Promise<string[]> {
	return (await readJson<Showcase[]>(SHOWCASES_API))
		.filter((s) => s.isInstalled)
		.map((s) => s.id)
		.sort()
}

/**
 * The role rows added since beforeAll.
 *
 * @return the new rows.
 */
async function newRoles(): Promise<RoleRow[]> {
	const known = new Set(rolesBefore.map((r) => r.id))
	return (await readJson<RoleRow[]>(ROLES_API)).filter((r) => !known.has(r.id))
}

/**
 * How many dashboards the administrator has, from `GET /api/dashboards`
 * (`{items, quota}`, or a bare array where the quota service is absent).
 *
 * @return the count.
 */
async function dashboardCount(): Promise<number> {
	const body = await readJson<unknown[] | { items: unknown[] }>(
		`${API}/dashboards`,
	)
	return Array.isArray(body) ? body.length : body.items.length
}

/**
 * A YAML list of strings, quoted.
 *
 * @param values the strings.
 * @return the flow-style list.
 */
function yamlList(values: string[]): string {
	return `[${values.map((v) => JSON.stringify(v)).join(', ')}]`
}

test.beforeAll(async () => {
	admin = await adminContext()
	storedComplete = await settingRows(COMPLETE_KEY)
	storedGroupOrder = await settingRows(GROUP_ORDER_KEY)
	storedFooter = await readFooter(admin)
	rolesBefore = await readJson<RoleRow[]>(ROLES_API)
	showcasesBefore = await readJson<Showcase[]>(SHOWCASES_API)
	await createGroup(admin, GROUP_A)
	await createGroup(admin, GROUP_B)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	const wasInstalled = new Set(
		showcasesBefore.filter((s) => s.isInstalled).map((s) => s.id),
	)
	for (const showcase of await readJson<Showcase[]>(SHOWCASES_API)) {
		if (showcase.isInstalled && !wasInstalled.has(showcase.id)) {
			await admin.delete(`${SHOWCASES_API}/${encodeURIComponent(showcase.id)}`)
		}
	}
	for (const row of await newRoles()) {
		await admin.delete(`${ROLES_API}/${row.id}`)
	}
	await restoreFooter(admin, storedFooter)
	await putBackSettingRows(storedGroupOrder, GROUP_ORDER_KEY)
	await putBackSettingRows(storedComplete, COMPLETE_KEY)
	for (const file of written) {
		await removeOnInstance(file)
	}
	await removeGroup(admin, GROUP_A)
	await removeGroup(admin, GROUP_B)
	await admin.dispose()
})

test('a full setup.yaml configures every step and completes the wizard', async () => {
	// @e2e setup-wizard::cli-command-accepts-yaml-config-file
	await wizardNotCompleted()
	await setGroupOrder([GROUP_B])
	const demos = await twoUninstalledShowcases()
	const organisation = `E2E CLI ${RUN}`
	const file = await yamlOnInstance(
		'full',
		[
			`group_priority_order: ${yamlList([GROUP_A, GROUP_B])}`,
			`demo_packages: ${yamlList(demos)}`,
			`admin_role_group: ${JSON.stringify(GROUP_A)}`,
			'footer_config:',
			`  organisation: ${JSON.stringify(organisation)}`,
			'  layoutMode: "columns"',
			'',
		].join('\n'),
	)

	const result = await setup(file)
	expect(result.code, describeRun(result)).toBe(0)

	// Step 2: the group priority order is the file's.
	expect(await readGroupOrder()).toEqual([GROUP_A, GROUP_B])
	// Step 6: the wizard is complete.
	expect((await readState()).complete).toBe(true)

	// Steps 3 to 5 are expected red: SetupCommand prints "skipped (capability
	// pending)" for each and applies nothing.
	const installed = await installedShowcases()
	for (const id of demos) {
		expect.soft(installed, `Step 3 installs ${id}`).toContain(id)
	}
	expect
		.soft(await newRoles(), 'Step 4 gives the group the Dashboard Admin role')
		.toEqual([expect.objectContaining({ groupId: GROUP_A, role: 'admin' })])
	expect
		.soft(
			(await readFooter(admin)).footerConfig,
			'Step 5 stores the footer config from the file',
		)
		.toMatchObject({ organisation, layoutMode: 'columns' })
})

test('a setup.yaml that is not a map, or whose group order is not a list, is refused', async () => {
	// @e2e setup-wizard::cli-command-validates-yaml-schema
	await setGroupOrder([GROUP_B])
	const invalid: Array<[string, string]> = [
		['a scalar at the top level', 'just a string\n'],
		['a list at the top level', `- ${GROUP_A}\n- ${GROUP_B}\n`],
		[
			'a string as the group order',
			`group_priority_order: ${JSON.stringify(GROUP_A)}\n`,
		],
		[
			'a map as the group order',
			`group_priority_order:\n  first: ${JSON.stringify(GROUP_A)}\n`,
		],
	]

	for (const [index, [label, yaml]] of invalid.entries()) {
		await wizardNotCompleted()
		const result = await setup(await yamlOnInstance(`invalid-${index}`, yaml))
		const out = lines(result)
		expect
			.soft(
				out.some((line) => line.startsWith('Invalid setup.yaml:')),
				`${label}: an error starting with "Invalid setup.yaml:"\n${describeRun(result)}`,
			)
			.toBe(true)
		expect
			.soft(result.code, `${label}: a non-zero exit\n${describeRun(result)}`)
			.not.toBe(0)
	}
})

test('a config written for the seven-step wizard still runs and ignores storage_backend', async () => {
	// @e2e setup-wizard::an-old-config-that-still-sets-storagebackend-keeps-working
	await wizardNotCompleted()
	await setGroupOrder([GROUP_B])
	const storageRowsBefore = await settingRows(CONTENT_STORAGE_KEY)
	const storageValueBefore = await appValue(CONTENT_STORAGE_KEY)
	const file = await yamlOnInstance(
		'old',
		[
			'storage_backend: "groupfolder"',
			`group_priority_order: ${yamlList([GROUP_A, GROUP_B])}`,
			'',
		].join('\n'),
	)

	const result = await setup(file)
	const out = lines(result)
	expect(
		out.some(
			(line) =>
				line.includes('storage_backend')
				&& line.includes('no longer used')
				&& line.includes('ignored'),
		),
		describeRun(result),
	).toBe(true)
	expect(result.code, describeRun(result)).toBe(0)

	// The other steps are applied.
	expect(await readGroupOrder()).toEqual([GROUP_A, GROUP_B])
	expect((await readState()).complete).toBe(true)

	// No content_storage is written, neither as a LaunchPad setting row nor as
	// an app value.
	expect(await settingRows(CONTENT_STORAGE_KEY)).toEqual(storageRowsBefore)
	expect(await appValue(CONTENT_STORAGE_KEY)).toBe(storageValueBefore)
})

test('the command prints a progress line per step and a final message', async () => {
	// @e2e setup-wizard::cli-command-is-verbose-by-default
	await wizardNotCompleted()
	// A different order first, so Step 2 has something to do and says "done".
	await setGroupOrder([GROUP_B])
	const file = await yamlOnInstance(
		'verbose',
		`group_priority_order: ${yamlList([GROUP_A, GROUP_B])}\n`,
	)

	// No -v: verbose by default.
	const result = await setup(file)
	expect(result.code, describeRun(result)).toBe(0)
	const out = lines(result)

	const stepLines = out.filter((line) => /^Step \d: /.test(line))
	expect(
		stepLines.map((line) => line.slice(0, 'Step n'.length)),
		describeRun(result),
	).toEqual(['Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6'])
	expect(stepLines[0]).toBe('Step 1: Welcome... done')
	expect(stepLines[1]).toBe('Step 2: Group order... done')
	for (const line of stepLines) {
		expect(line, 'each step line names the step and its outcome').toMatch(
			/^Step \d: [A-Z][A-Za-z ]+\.\.\. \S/,
		)
	}
	expect(out[out.length - 1]).toBe(FINAL_LINE)
})

test('a setup.yaml with only the group order skips Steps 4 and 5 and completes', async () => {
	// @e2e setup-wizard::cli-command-skips-optional-steps-if-not-in-config
	await wizardNotCompleted()
	await setGroupOrder([GROUP_B])
	const footerBefore = await readFooter(admin)
	const rolesNow = await readJson<RoleRow[]>(ROLES_API)
	const file = await yamlOnInstance(
		'only-groups',
		`group_priority_order: ${yamlList([GROUP_A, GROUP_B])}\n`,
	)

	const result = await setup(file)
	expect(result.code, describeRun(result)).toBe(0)
	const out = lines(result)
	expect(
		out.some((line) => /error|failed/i.test(line)),
		describeRun(result),
	).toBe(false)

	// Steps 4 and 5 are skipped and change nothing.
	expect(out).toContainEqual(expect.stringMatching(/^Step 4: .*\.\.\. skipped/))
	expect(out).toContainEqual(expect.stringMatching(/^Step 5: .*\.\.\. skipped/))
	expect(await readJson<RoleRow[]>(ROLES_API)).toEqual(rolesNow)
	expect(await readFooter(admin)).toEqual(footerBefore)

	// Step 2 runs with the file's value.
	expect(out).toContain('Step 2: Group order... done')
	expect(await readGroupOrder()).toEqual([GROUP_A, GROUP_B])

	// The wizard completes.
	expect(out[out.length - 1]).toBe(FINAL_LINE)
	expect((await readState()).complete).toBe(true)
})

test('running the same setup.yaml twice succeeds and duplicates nothing', async () => {
	// @e2e setup-wizard::cli-command-is-idempotent
	await wizardNotCompleted()
	await setGroupOrder([GROUP_B])
	const demos = await twoUninstalledShowcases()
	const file = await yamlOnInstance(
		'twice',
		[
			`group_priority_order: ${yamlList([GROUP_A, GROUP_B])}`,
			`demo_packages: ${yamlList(demos)}`,
			`admin_role_group: ${JSON.stringify(GROUP_A)}`,
			'',
		].join('\n'),
	)

	const first = await setup(file)
	expect(first.code, describeRun(first)).toBe(0)
	const orderAfterFirst = await readGroupOrder()
	const rolesAfterFirst = await readJson<RoleRow[]>(ROLES_API)
	const installedAfterFirst = await installedShowcases()
	const dashboardsAfterFirst = await dashboardCount()

	const second = await setup(file)
	expect(second.code, describeRun(second)).toBe(0)
	expect(lines(second)).toContain(
		'Step 2: Group order... already configured, skipping',
	)
	expect(lines(second)[lines(second).length - 1]).toBe(FINAL_LINE)

	// Nothing is applied twice: the same order, no second role row for the
	// group, no showcase installed again, no dashboard added.
	expect(await readGroupOrder()).toEqual(orderAfterFirst)
	expect(await readJson<RoleRow[]>(ROLES_API)).toEqual(rolesAfterFirst)
	expect(
		(await readJson<RoleRow[]>(ROLES_API)).filter(
			(r) => r.groupId === GROUP_A && r.role === 'admin',
		).length,
	).toBeLessThanOrEqual(1)
	expect(await installedShowcases()).toEqual(installedAfterFirst)
	expect(await dashboardCount()).toBe(dashboardsAfterFirst)
	expect((await readState()).complete).toBe(true)
})
