/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `launchpad:*` occ commands
 * (openspec/specs/cli-commands), written for e2e-coverage-programme task 5.3.
 *
 * Every scenario in this spec used to be excluded with "Playwright cannot run
 * occ". It can: this suite already runs occ on the instance under test
 * (support/scheduledExports.ts, dashboard-kiosk-mode.spec.ts). The commands
 * here run on the REAL instance, through the real occ binary and Symfony
 * console, which is exactly what the golden unit tests cannot see: they feed
 * a command in-memory buffers, so they cannot tell which stream a line lands
 * on under `--quiet`, what the process exit code is, or whether the audit line
 * reaches Nextcloud's log.
 *
 * How occ is reached is in support/occ.ts (LAUNCHPAD_E2E_OCC, a container
 * name, `php occ` in the server root, or the `nextcloud` container). The API
 * is used to seed and tear down (dashboards, shares, locks, versions, groups)
 * and to confirm what a command wrote or deleted.
 *
 * Settings this file changes are read first and put back: the personal
 * dashboard flags, Nextcloud's `loglevel` (lowered to info only while an audit
 * line is being looked for), and the recorded shipped install of
 * `mijn-werkdag`. The signed-release scenario writes `appinfo/signature.json`
 * into the app folder for the length of one test, so it refuses a shared
 * instance.
 *
 * Scenarios still excluded carry their reason in the spec: features that were
 * never built (feed tokens, exit 3, exit 5, `--json` on import), a migration
 * whose body is empty, an instance without the language-content table, an
 * exception no input can provoke, and the two code-review conventions.
 *
 * The commands that predate this spec (launchpad:export, launchpad:import)
 * are not left to their golden tests alone: one test exports a dashboard
 * through the one and imports it back through the other.
 *
 * An installed template older than the shipped one is staged, not needed: the
 * installed version is an app value (rewound through occ) and the installed
 * widgets are rows (changed through support/instanceDb.ts), which is exactly
 * what an instance that installed an older release holds.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cli-commands::discover-all-launchpad-commands
 *   @e2e cli-commands::sibling-specs-all-fall-under-the-namespace
 *   @e2e cli-commands::commands-with-multi-level-verbs
 *   @e2e cli-commands::namespace-not-shared-with-other-apps
 *   @e2e cli-commands::quiet-mode-suppresses-progress-but-not-errors
 *   @e2e cli-commands::json-flag-emits-structured-output-only
 *   @e2e cli-commands::no-interaction-flag-skips-prompts-in-ci
 *   @e2e cli-commands::global-flags-inherited-by-every-command
 *   @e2e cli-commands::list-dashboards-with-optional-filters
 *   @e2e cli-commands::show-one-dashboards-full-widget-tree
 *   @e2e cli-commands::delete-without-cascade-fails-when-children-exist
 *   @e2e cli-commands::delete-with-cascade-removes-children
 *   @e2e cli-commands::debug-share-prints-all-sharing-state-for-support
 *   @e2e cli-commands::export-translatable-strings-to-pot
 *   @e2e cli-commands::a-signed-release-is-never-written-to
 *   @e2e cli-commands::the-pot-file-goes-where-the-operator-says
 *   @e2e cli-commands::copy-navigation-fails-on-unknown-language
 *   @e2e cli-commands::success-exit-code-0
 *   @e2e cli-commands::invalid-argument-exit-code-2
 *   @e2e cli-commands::resource-not-found-exit-code-4
 *   @e2e cli-commands::successful-json-response
 *   @e2e cli-commands::stdout-clean-of-non-json-bytes
 *   @e2e cli-commands::commands-are-registered-at-install
 *   @e2e cli-commands::new-sibling-spec-command-added-to-infoxml
 *   @e2e cli-commands::one-line-description-in-occ-list
 *   @e2e cli-commands::detailed-help-via-help
 *   @e2e cli-commands::help-documents-global-flags
 *   @e2e cli-commands::no-undocumented-or-hidden-commands
 *   @e2e cli-commands::examples-are-realistic
 *   @e2e cli-commands::audit-log-on-success
 *   @e2e cli-commands::audit-log-truncates-long-args
 *   @e2e cli-commands::byuser-is-cli-for-cron-invoked-commands
 *   @e2e cli-commands::full-command-listing-is-alphabetical
 *   @e2e cli-commands::pre-existing-commands-are-not-broken
 *   @e2e cli-commands::filtering-the-listing-by-sub-namespace
 *   @e2e cli-commands::occ-list-links-to-help-for-each-command
 *   @e2e cli-commands::a-deploy-script-installs-the-template-for-a-group
 *   @e2e cli-commands::an-unknown-group-stops-the-install
 *   @e2e cli-commands::an-update-refuses-the-options-that-change-who-gets-the-template
 *   @e2e cli-commands::an-unknown-template-id-lists-the-shipped-ones
 *   @e2e cli-commands::an-operator-updates-the-installed-template-and-reads-what-changed
 *
 * @spec openspec/specs/cli-commands/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { RunResult } from './support/occ.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import * as path from 'node:path'
import { refuseOnSharedInstance } from './shared-instance.ts'
import {
	listTemplates,
	readAppConfig,
	writeAppConfig,
} from './support/adminTemplates.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	allowManyPersonalDashboards,
	createDashboard,
	publication,
	readDashboard,
} from './support/dashboardHierarchy.ts'
import { selectRows, updateRows } from './support/instanceDb.ts'
import {
	appPathOnInstance,
	appValue,
	describeRun,
	existsOnInstance,
	logDuring,
	onInstance,
	readOnInstance,
	removeOnInstance,
	runOcc,
	setIntAppValue,
	writeOnInstance,
} from './support/occ.ts'
import {
	adminContext,
	API,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E CLI ${RUN}`
const CHECKOUT = path.resolve(__dirname, '..', '..')
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

/** The commands that extend CommandBase, so carry the three global flags. */
const COMMAND_BASE = [
	'launchpad:dashboard:list',
	'launchpad:dashboard:show',
	'launchpad:dashboard:delete',
	'launchpad:dashboard:debug-share',
	'launchpad:i18n:export-strings',
	'launchpad:i18n:migrate-language-structure',
	'launchpad:i18n:copy-navigation',
]

/** The shipped-install record of mijn-werkdag, put back in afterAll. */
const SHIPPED_KEYS = [
	'shipped_template_mijn-werkdag',
	'shipped_template_version_mijn-werkdag',
]

/** One command as `occ list --format=json` describes it. */
interface ListedCommand {
	name: string
	description: string
	help: string
	hidden?: boolean
	definition: {
		arguments: Record<string, { name: string }>
		options: Record<string, { name: string; shortcut: string }>
	}
}

/** The envelope every `--json` run must print (REQ-CLI-007). */
interface Envelope {
	success: boolean
	exitCode: number
	data: any
	errors: Array<{
		code: string
		message: string
		context?: Record<string, unknown>
	}>
}

let admin: APIRequestContext
let alice: Principal | null = null
let restoreSettings: (() => Promise<void>) | null = null
const dashboards: number[] = []
const groups: string[] = []
const templates: number[] = []
const shippedBefore: Record<string, string> = {}

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	restoreSettings = await allowManyPersonalDashboards(admin)
	alice = await createPrincipal(admin, 'e2e-cli-alice')
	for (const key of SHIPPED_KEYS) {
		shippedBefore[key] = await readAppConfig(admin, key)
	}
})

test.afterAll(async () => {
	for (const id of [...dashboards].reverse()) {
		await admin
			.delete(`${API}/dashboard/${id}?cascade=true`)
			.catch(() => undefined)
	}
	if (alice !== null) {
		const owned = await alice.api
			.get(`${API}/dashboards/visible`)
			.catch(() => null)
		if (owned !== null && owned.ok()) {
			const body = await owned.json()
			const rows = (
				Array.isArray(body) ? body : (body.items ?? [])
			) as DashboardRow[]
			for (const row of rows.filter((r) => String(r.name).startsWith(STAMP))) {
				await alice.api
					.delete(`${API}/dashboard/${row.id}?cascade=true`)
					.catch(() => undefined)
			}
		}
	}
	for (const id of templates) {
		await admin.delete(`${API}/admin/templates/${id}`).catch(() => undefined)
	}
	for (const key of SHIPPED_KEYS) {
		await writeAppConfig(admin, key, shippedBefore[key] ?? '').catch(
			() => undefined,
		)
	}
	for (const g of groups) {
		await removeGroup(admin, g)
	}
	await removePrincipal(admin, alice)
	if (restoreSettings !== null) {
		await restoreSettings()
	}
	await admin.dispose()
})

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a dashboard as `api`, remembered for teardown when the admin owns it.
 *
 * @param api the owner.
 * @param label a name suffix.
 * @param extra more create-body fields (parentUuid, ...).
 * @return the dashboard.
 */
async function dashboard(
	api: APIRequestContext,
	label: string,
	extra: Record<string, unknown> = {},
): Promise<DashboardRow> {
	const row = await createDashboard(api, { name: `${STAMP} ${label}`, ...extra })
	if (api === admin) {
		dashboards.push(Number(row.id))
	}
	return row
}

/**
 * Parse a `--json` run's stdout as the envelope, insisting that stdout is
 * nothing but that one JSON object.
 *
 * @param result the run.
 * @return the envelope.
 */
function envelope(result: RunResult): Envelope {
	const text = result.stdout.trim()
	expect(
		text.startsWith('{') && text.endsWith('}'),
		`stdout is not one JSON object\n${describeRun(result)}`,
	).toBe(true)
	let parsed: Envelope
	try {
		parsed = JSON.parse(text) as Envelope
	} catch (error) {
		throw new Error(
			`stdout does not parse as JSON (${String(error)})\n${describeRun(result)}`,
			{ cause: error },
		)
	}
	expect(Object.keys(parsed).sort(), describeRun(result)).toEqual([
		'data',
		'errors',
		'exitCode',
		'success',
	])
	return parsed
}

/**
 * The `launchpad:*` commands as `occ list <namespace> --format=json` lists them.
 *
 * @param namespace the namespace to list.
 * @return the commands.
 */
async function listedCommands(namespace = 'launchpad'): Promise<ListedCommand[]> {
	const result = await runOcc('list', namespace, '--format=json')
	expect(result.code, describeRun(result)).toBe(0)
	return (JSON.parse(result.stdout).commands ?? []) as ListedCommand[]
}

/**
 * The rows of the plain `occ list <namespace>` listing: name, description and
 * the column the description starts at.
 *
 * @param namespace the namespace to list.
 * @return the rows, in printed order.
 */
async function listedRows(
	namespace: string,
): Promise<
	Array<{ name: string; description: string; column: number; line: string }>
> {
	const result = await runOcc('list', namespace)
	expect(result.code, describeRun(result)).toBe(0)
	const lines = result.stdout.split('\n')
	const start = lines.findIndex((line) =>
		/^Available commands for the "[^"]+" namespace:/.test(line),
	)
	expect(
		start,
		`no namespace section in occ list\n${describeRun(result)}`,
	).toBeGreaterThanOrEqual(0)
	const rows: Array<{
		name: string
		description: string
		column: number
		line: string
	}> = []
	for (const line of lines.slice(start + 1)) {
		const match = /^ {2}(\S+)( +)(.*)$/.exec(line)
		if (match === null) {
			if (line.trim() === '') {
				continue
			}
			break
		}
		rows.push({
			name: match[1],
			description: match[3],
			column: 2 + match[1].length + match[2].length,
			line,
		})
	}
	return rows
}

/** The `Help:` section of a `--help` run. */
function helpSection(stdout: string): string {
	const index = stdout.indexOf('\nHelp:\n')
	return index === -1 ? '' : stdout.slice(index + '\nHelp:\n'.length)
}

/** The audit lines among log messages, for one command. */
function auditLines(messages: string[], command: string): string[] {
	return messages.filter((m) => m.startsWith(`[launchpad] cli ${command} `))
}

// ---------------------------------------------------------------------------
// REQ-CLI-001 Namespace convention
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-001 namespace convention', () => {
	// @e2e cli-commands::discover-all-launchpad-commands
	test('occ list launchpad lists every command, prefixed, described and sorted', async () => {
		const rows = await listedRows('launchpad')
		expect(
			rows.length,
			'occ list launchpad listed nothing',
		).toBeGreaterThanOrEqual(8)
		for (const row of rows) {
			expect(row.name, row.line).toMatch(/^launchpad:/)
			expect(
				row.description.trim(),
				`${row.name} has no one-line description`,
			).not.toBe('')
		}
		const names = rows.map((r) => r.name)
		expect(names).toEqual([...names].sort())
	})

	// @e2e cli-commands::sibling-specs-all-fall-under-the-namespace
	test("the sibling specs' commands sit under launchpad: without a collision", async () => {
		const all = await runOcc('list', '--format=json')
		expect(all.code, describeRun(all)).toBe(0)
		const everyName = (
			(JSON.parse(all.stdout).commands ?? []) as ListedCommand[]
		).map((c) => c.name)
		for (const name of [
			'launchpad:export',
			'launchpad:import',
			'launchpad:import:confluence',
			'launchpad:demo-showcases:install',
		]) {
			expect(
				everyName.filter((n) => n === name),
				`${name} is registered once`,
			).toHaveLength(1)
		}
		const launchpad = everyName.filter((n) => n.startsWith('launchpad:'))
		expect(new Set(launchpad).size, 'two launchpad commands share a name').toBe(
			launchpad.length,
		)
	})

	// @e2e cli-commands::commands-with-multi-level-verbs
	test('launchpad:i18n:copy-navigation is recognised and occ list launchpad:i18n lists only i18n commands', async () => {
		const run = await runOcc(
			'launchpad:i18n:copy-navigation',
			'--from=nl',
			'--to=en',
			'--json',
		)
		expect(`${run.stdout}${run.stderr}`, describeRun(run)).not.toMatch(
			/is not defined|There are no commands defined/,
		)
		expect([0, 4], describeRun(run)).toContain(run.code)
		expect(envelope(run).exitCode).toBe(run.code)

		const i18n = await listedCommands('launchpad:i18n')
		const names = i18n.map((c) => c.name)
		expect(names).toEqual(
			expect.arrayContaining([
				'launchpad:i18n:copy-navigation',
				'launchpad:i18n:export-strings',
				'launchpad:i18n:migrate-language-structure',
			]),
		)
		for (const name of names) {
			expect(name).toMatch(/^launchpad:i18n:/)
		}
	})

	// @e2e cli-commands::namespace-not-shared-with-other-apps
	test('occ list launchpad shows only launchpad commands, though other apps have commands', async () => {
		const all = await runOcc('list', '--format=json')
		expect(all.code, describeRun(all)).toBe(0)
		const others = ((JSON.parse(all.stdout).commands ?? []) as ListedCommand[])
			.map((c) => c.name)
			.filter((n) => n.includes(':') && !n.startsWith('launchpad:'))
		expect(
			others.length,
			'the instance registers commands of other apps',
		).toBeGreaterThan(0)

		const launchpad = await listedCommands('launchpad')
		expect(launchpad.length).toBeGreaterThan(0)
		for (const command of launchpad) {
			expect(command.name).toMatch(/^launchpad:/)
		}
		expect(launchpad.map((c) => c.name)).not.toEqual(
			expect.arrayContaining([others[0]]),
		)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-002 Global flags
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-002 global flags', () => {
	// @e2e cli-commands::quiet-mode-suppresses-progress-but-not-errors
	test('--quiet prints nothing on success and still prints the error on stderr', async () => {
		await dashboard(admin, 'quiet')
		const ok = await runOcc('launchpad:dashboard:list', '--quiet')
		expect(ok.code, describeRun(ok)).toBe(0)
		expect(ok.stdout.trim(), describeRun(ok)).toBe('')

		const missing = `e2e-cli-nobody-${RUN}`
		const failed = await runOcc(
			'launchpad:dashboard:list',
			'--quiet',
			`--user=${missing}`,
		)
		expect(failed.code, describeRun(failed)).toBe(4)
		expect(failed.stdout.trim(), describeRun(failed)).toBe('')
		expect(failed.stderr, describeRun(failed)).toContain(
			`User not found: ${missing}`,
		)
	})

	// @e2e cli-commands::json-flag-emits-structured-output-only
	test('--json prints one JSON object and nothing else, with special characters escaped', async () => {
		const name = `${STAMP} json "quoted" back\\slash tab\t ümlaut`
		const row = await createDashboard(admin, { name })
		dashboards.push(Number(row.id))
		expect(row.name, 'the API kept the name as sent').toBe(name)

		const result = await runOcc('launchpad:dashboard:list', '--json')
		expect(result.code, describeRun(result)).toBe(0)
		const parsed = envelope(result)
		expect(parsed.success).toBe(true)
		const listed = (
			parsed.data.dashboards as Array<Record<string, string>>
		).find((d) => d.uuid === row.uuid)
		expect(listed, 'the seeded dashboard is listed').toBeDefined()
		expect(
			listed!.name,
			'the name survives the JSON round trip byte for byte',
		).toBe(name)
	})

	// @e2e cli-commands::no-interaction-flag-skips-prompts-in-ci
	test('--no-interaction deletes without asking and exits 0', async () => {
		const row = await dashboard(admin, 'no-interaction')
		const result = await runOcc(
			'launchpad:dashboard:delete',
			row.uuid,
			'--no-interaction',
		)
		expect(result.code, describeRun(result)).toBe(0)
		expect(`${result.stdout}${result.stderr}`, describeRun(result)).not.toMatch(
			/\(y\/N\)|\[y\/N\]/i,
		)
		expect(result.stdout, describeRun(result)).toContain(
			`Deleted dashboard ${row.uuid}`,
		)
		expect(
			(await readDashboard(admin, Number(row.id))).status,
			'the dashboard is gone',
		).toBe(404)
	})

	// @e2e cli-commands::global-flags-inherited-by-every-command
	test('every CommandBase command documents and honours --quiet, --json and --no-interaction', async () => {
		for (const name of COMMAND_BASE) {
			const help = await runOcc(name, '--help')
			expect(help.code, describeRun(help)).toBe(0)
			expect(help.stdout, `${name} --help`).toMatch(/--json\b/)
			expect(help.stdout, `${name} --help`).toMatch(/-q, --quiet\b/)
			expect(help.stdout, `${name} --help`).toMatch(/-n, --no-interaction\b/)
		}
		// And they function, on a child that adds no flag code of its own.
		const missing = '00000000-0000-4000-8000-000000000000'
		const json = await runOcc(
			'launchpad:dashboard:debug-share',
			missing,
			'--json',
			'--quiet',
			'--no-interaction',
		)
		expect(json.code, describeRun(json)).toBe(4)
		expect(envelope(json).exitCode).toBe(4)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-003 Dashboard management
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-003 dashboard management', () => {
	// @e2e cli-commands::list-dashboards-with-optional-filters
	test("--user and --status narrow the listing to that owner's published dashboards", async () => {
		// A new dashboard is a draft (REQ-DASH-031): publish the first one.
		const published = await dashboard(alice!.api, 'alice published')
		const madePublic = await publication(alice!.api, published.uuid, 'publish')
		expect(madePublic.status, JSON.stringify(madePublic.body)).toBeLessThan(
			300,
		)
		const draft = await dashboard(alice!.api, 'alice draft')
		const unpublished = await publication(alice!.api, draft.uuid, 'unpublish')
		expect(unpublished.status, JSON.stringify(unpublished.body)).toBeLessThan(
			300,
		)
		const draftNow = await readDashboard(alice!.api, Number(draft.id))
		expect(
			draftNow.dashboard?.publicationStatus,
			'precondition: the second dashboard is not published',
		).not.toBe('published')
		const others = await dashboard(admin, 'admin published')

		const plain = await runOcc(
			'launchpad:dashboard:list',
			`--user=${alice!.username}`,
			'--status=published',
		)
		expect(plain.code, describeRun(plain)).toBe(0)
		expect(plain.stdout).toContain(published.uuid)
		expect(plain.stdout).not.toContain(draft.uuid)
		expect(plain.stdout).not.toContain(others.uuid)

		const json = await runOcc(
			'launchpad:dashboard:list',
			`--user=${alice!.username}`,
			'--status=published',
			'--json',
		)
		expect(json.code, describeRun(json)).toBe(0)
		const rows = envelope(json).data.dashboards as Array<Record<string, string>>
		expect(rows.map((r) => r.uuid)).toEqual([published.uuid])
		expect(rows[0]).toMatchObject({
			uuid: published.uuid,
			name: published.name,
			owner: alice!.username,
			publicationStatus: 'published',
		})
	})

	// @e2e cli-commands::show-one-dashboards-full-widget-tree
	test('show prints the dashboard metadata and its widget placements', async () => {
		// An empty dashboard: without `widgets: 'none'` a create seeds the
		// default bundle (REQ-DWB-001) and show lists those as well.
		const row = await dashboard(admin, 'show', { widgets: 'none' })
		const added = await admin.post(`${API}/dashboard/${row.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 1,
				gridY: 2,
				gridWidth: 3,
				gridHeight: 2,
				content: { text: `cli show ${RUN}`, contentMode: 'markdown' },
			},
		})
		expect(added.status(), await added.text()).toBeLessThan(300)

		const plain = await runOcc('launchpad:dashboard:show', row.uuid)
		expect(plain.code, describeRun(plain)).toBe(0)
		expect(plain.stdout).toContain(row.uuid)
		expect(plain.stdout).toContain(`cli show ${RUN}`)

		const json = await runOcc('launchpad:dashboard:show', row.uuid, '--json')
		expect(json.code, describeRun(json)).toBe(0)
		const parsed = envelope(json)
		expect(parsed.success).toBe(true)
		expect(parsed.errors).toEqual([])
		const shown = parsed.data.dashboard as Record<string, unknown>
		expect(shown).toMatchObject({
			uuid: row.uuid,
			name: row.name,
			userId: ADMIN_USER,
		})
		for (const key of ['type', 'publicationStatus', 'gridColumns']) {
			expect(shown, `dashboard.${key}`).toHaveProperty(key)
		}
		const widgets = parsed.data.widgets as Array<Record<string, unknown>>
		expect(widgets).toHaveLength(1)
		expect(widgets[0]).toMatchObject({
			widgetId: 'text',
			gridX: 1,
			gridY: 2,
			gridWidth: 3,
			gridHeight: 2,
		})
		expect(
			JSON.stringify(widgets[0]),
			'the placement carries its config',
		).toContain(`cli show ${RUN}`)
	})

	// @e2e cli-commands::delete-without-cascade-fails-when-children-exist
	test('delete without --cascade refuses a dashboard with children, exit 2', async () => {
		const parent = await dashboard(admin, 'parent no cascade')
		const child = await dashboard(admin, 'child no cascade', {
			parentUuid: parent.uuid,
		})

		const result = await runOcc(
			'launchpad:dashboard:delete',
			parent.uuid,
			'--no-interaction',
		)
		expect(result.code, describeRun(result)).toBe(2)
		expect(result.stderr, describeRun(result)).toContain(
			'Use --cascade to also delete child dashboards',
		)
		expect(
			(await readDashboard(admin, Number(parent.id))).status,
			'the parent is kept',
		).toBe(200)
		expect(
			(await readDashboard(admin, Number(child.id))).status,
			'the child is kept',
		).toBe(200)
	})

	// @e2e cli-commands::delete-with-cascade-removes-children
	test('delete --cascade removes the dashboard and its two children, exit 0, and audits it', async () => {
		const parent = await dashboard(admin, 'parent cascade')
		const one = await dashboard(admin, 'child one', { parentUuid: parent.uuid })
		const two = await dashboard(admin, 'child two', { parentUuid: parent.uuid })

		let result: RunResult | null = null
		const messages = await logDuring(async () => {
			result = await runOcc(
				'launchpad:dashboard:delete',
				parent.uuid,
				'--cascade',
				'--no-interaction',
			)
		})
		expect(result!.code, describeRun(result!)).toBe(0)
		for (const row of [parent, one, two]) {
			expect(
				(await readDashboard(admin, Number(row.id))).status,
				`${row.name} is gone`,
			).toBe(404)
		}
		const lines = auditLines(messages, 'dashboard:delete')
		expect(lines, messages.join('\n')).toHaveLength(1)
		expect(lines[0]).toContain(parent.uuid)
		expect(lines[0]).toContain('--cascade')
		expect(lines[0]).toMatch(/ exitCode=0 durationMs=\d+ byUser=\S+$/)
	})

	// @e2e cli-commands::debug-share-prints-all-sharing-state-for-support
	test('debug-share reports the shares, the lock, the version count and the view count', async () => {
		const row = await dashboard(admin, 'debug share')
		await shareWith(admin, Number(row.id), alice!.username, 'view_only')
		const version = await admin.post(`${API}/dashboards/${row.uuid}/versions`, {
			data: { note: 'cli debug-share' },
		})
		expect(version.status(), await version.text()).toBe(201)
		const lock = await admin.post(`${API}/dashboards/${row.uuid}/lock`)
		expect(lock.status(), await lock.text()).toBe(200)

		try {
			const result = await runOcc(
				'launchpad:dashboard:debug-share',
				row.uuid,
				'--json',
			)
			expect(result.code, describeRun(result)).toBe(0)
			const data = envelope(result).data as Record<string, any>
			for (const key of [
				'shares',
				'locked',
				'lockedBy',
				'lockedAt',
				'versionCount',
				'viewCount',
			]) {
				expect(data, `data.${key}`).toHaveProperty(key)
			}
			const share = (data.shares as Array<Record<string, unknown>>).find(
				(s) => s.shareWith === alice!.username,
			)
			expect(share, JSON.stringify(data.shares)).toBeDefined()
			expect(share!.permissionLevel).toBe('view_only')
			expect(
				share!.createdAt,
				'the share row says when it was made',
			).toBeTruthy()
			expect(data.locked, 'the dashboard is locked right now').toBe(true)
			expect(data.lockedBy).toBe(ADMIN_USER)
			expect(data.lockedAt, 'lockedAt').toBeTruthy()
			expect(
				data.versionCount,
				'one version was saved',
			).toBeGreaterThanOrEqual(1)
			expect(typeof data.viewCount).toBe('number')
		} finally {
			await admin
				.delete(`${API}/dashboards/${row.uuid}/lock`)
				.catch(() => undefined)
		}
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-005 i18n management
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-005 i18n management', () => {
	/** A string lib/ wraps for translation, so any scan of lib/ finds it. */
	const KNOWN_MSGID = 'msgid "A dashboard is shared with you"'

	// @e2e cli-commands::export-translatable-strings-to-pot
	test('export-strings writes l10n/launchpad.pot on a development checkout', async () => {
		const app = await appPathOnInstance()
		const signed = await existsOnInstance(`${app}/appinfo/signature.json`)
		test.skip(
			signed,
			'the instance runs a signed release; that case is the "signed release is never written to" scenario',
		)

		const pot = `${app}/l10n/launchpad.pot`
		const before = (await existsOnInstance(pot))
			? await readOnInstance(pot)
			: null
		try {
			const result = await runOcc('launchpad:i18n:export-strings')
			expect(result.code, describeRun(result)).toBe(0)
			const written = await readOnInstance(pot)
			expect(written).toContain('msgid ""')
			expect(written).toContain(KNOWN_MSGID)
			expect(
				(written.match(/^msgid "/gm) ?? []).length,
				'many strings were found',
			).toBeGreaterThan(20)
		} finally {
			if (before === null) {
				await removeOnInstance(pot)
			} else {
				await writeOnInstance(pot, before)
			}
		}
	})

	// @e2e cli-commands::a-signed-release-is-never-written-to
	test('on a signed release export-strings writes nothing into the app folder and exits 2', async () => {
		refuseOnSharedInstance(
			BASE_URL,
			'the signed-release export-strings test',
			'it writes appinfo/signature.json into the app folder for the length of the test.',
		)
		const app = await appPathOnInstance()
		const signature = `${app}/appinfo/signature.json`
		const created = !(await existsOnInstance(signature))
		const marker = `/tmp/launchpad-e2e-cli-marker-${RUN}`
		if (created) {
			await writeOnInstance(
				signature,
				'{"hashes":{},"signature":"e2e","certificate":"e2e"}\n',
			)
		}
		try {
			await writeOnInstance(marker, 'marker\n')
			// find -newer has one-second resolution on some filesystems.
			await new Promise((resolve) => setTimeout(resolve, 1100))

			const bare = await runOcc('launchpad:i18n:export-strings')
			expect(bare.code, describeRun(bare)).toBe(2)
			expect(`${bare.stdout}${bare.stderr}`, describeRun(bare)).toContain(
				'--output',
			)

			const inside = await runOcc(
				'launchpad:i18n:export-strings',
				`--output=${app}/l10n/e2e-${RUN}.pot`,
			)
			expect(inside.code, describeRun(inside)).toBe(2)
			expect(
				`${inside.stdout}${inside.stderr}`,
				describeRun(inside),
			).toContain('--output')

			const changed = await onInstance(
				'find',
				app,
				'(',
				'-path',
				`${app}/node_modules`,
				'-o',
				'-path',
				`${app}/vendor`,
				'-o',
				'-path',
				`${app}/.git`,
				')',
				'-prune',
				'-o',
				'-type',
				'f',
				'-newer',
				marker,
				'-print',
			)
			expect(changed.code, describeRun(changed)).toBe(0)
			const files = changed.stdout
				.split('\n')
				.filter((f) => f.trim() !== '' && f !== signature)
			expect(files, 'files created or changed in the app folder').toEqual([])
		} finally {
			await removeOnInstance(marker)
			await removeOnInstance(`${app}/l10n/e2e-${RUN}.pot`)
			if (created) {
				await removeOnInstance(signature)
			}
		}
	})

	// @e2e cli-commands::the-pot-file-goes-where-the-operator-says
	test('--output=<path> writes there and --output=- prints the POT, both exit 0', async () => {
		const target = `/tmp/launchpad-e2e-${RUN}.pot`
		try {
			const toFile = await runOcc(
				'launchpad:i18n:export-strings',
				`--output=${target}`,
			)
			expect(toFile.code, describeRun(toFile)).toBe(0)
			expect(await readOnInstance(target)).toContain(KNOWN_MSGID)
		} finally {
			await removeOnInstance(target)
		}

		const toStdout = await runOcc('launchpad:i18n:export-strings', '--output=-')
		expect(toStdout.code, describeRun(toStdout)).toBe(0)
		expect(toStdout.stdout).toContain('msgid ""')
		expect(toStdout.stdout).toContain(KNOWN_MSGID)
	})

	// @e2e cli-commands::copy-navigation-fails-on-unknown-language
	test('copy-navigation from a language with no navigation exits 4 and names it', async () => {
		const result = await runOcc(
			'launchpad:i18n:copy-navigation',
			'--from=xx',
			'--to=en',
		)
		expect(result.code, describeRun(result)).toBe(4)
		expect(result.stderr, describeRun(result)).toContain(
			"No navigation tree found for language 'xx'",
		)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-006 Exit codes
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-006 exit code contract', () => {
	// @e2e cli-commands::success-exit-code-0
	test('a clean dashboard:list exits 0', async () => {
		const result = await runOcc('launchpad:dashboard:list')
		expect(result.code, describeRun(result)).toBe(0)
	})

	// @e2e cli-commands::invalid-argument-exit-code-2
	test('dashboard:show not-a-uuid exits 2 and says why on stderr', async () => {
		const result = await runOcc('launchpad:dashboard:show', 'not-a-uuid')
		expect(result.code, describeRun(result)).toBe(2)
		expect(result.stderr, describeRun(result)).toContain(
			"Invalid UUID format: 'not-a-uuid'",
		)
	})

	// @e2e cli-commands::resource-not-found-exit-code-4
	test('dashboard:show of an absent UUID exits 4 with "Dashboard not found"', async () => {
		const result = await runOcc(
			'launchpad:dashboard:show',
			'00000000-0000-0000-0000-000000000000',
		)
		expect(result.code, describeRun(result)).toBe(4)
		expect(result.stderr, describeRun(result)).toContain('Dashboard not found')
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-007 JSON output schema
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-007 JSON output schema', () => {
	// @e2e cli-commands::successful-json-response
	test('dashboard:list --json answers {success, exitCode 0, data.dashboards[], errors: []}', async () => {
		const seeded = [
			await dashboard(admin, 'json one'),
			await dashboard(admin, 'json two'),
			await dashboard(admin, 'json three'),
		]
		const result = await runOcc('launchpad:dashboard:list', '--json')
		expect(result.code, describeRun(result)).toBe(0)
		const parsed = envelope(result)
		expect(parsed.success).toBe(true)
		expect(parsed.exitCode).toBe(0)
		expect(parsed.errors).toEqual([])
		expect(Array.isArray(parsed.data.dashboards)).toBe(true)
		const uuids = (parsed.data.dashboards as Array<{ uuid: string }>).map(
			(d) => d.uuid,
		)
		expect(uuids).toEqual(expect.arrayContaining(seeded.map((d) => d.uuid)))
	})

	// @e2e cli-commands::stdout-clean-of-non-json-bytes
	test('every --json run, passing or failing, prints only the envelope on stdout', async () => {
		const row = await dashboard(admin, 'clean stdout')
		const runs: string[][] = [
			['launchpad:dashboard:list', '--json'],
			['launchpad:dashboard:list', '--json', '--status=bogus'],
			['launchpad:dashboard:show', row.uuid, '--json'],
			['launchpad:dashboard:show', 'not-a-uuid', '--json'],
			[
				'launchpad:dashboard:show',
				'00000000-0000-0000-0000-000000000000',
				'--json',
			],
			['launchpad:dashboard:debug-share', row.uuid, '--json'],
			[
				'launchpad:dashboard:delete',
				'00000000-0000-0000-0000-000000000000',
				'--json',
				'--no-interaction',
			],
			['launchpad:i18n:copy-navigation', '--from=xx', '--to=en', '--json'],
			['launchpad:i18n:copy-navigation', '--json'],
			[
				'launchpad:i18n:migrate-language-structure',
				'--json',
				'--no-interaction',
			],
		]
		for (const argv of runs) {
			const result = await runOcc(...argv)
			const parsed = envelope(result)
			expect(
				parsed.exitCode,
				`${argv.join(' ')}: the envelope's exitCode is the process exit code`,
			).toBe(result.code)
			expect(parsed.success, argv.join(' ')).toBe(result.code === 0)
		}
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-008 Registration
// ---------------------------------------------------------------------------

/** The command names appinfo/info.xml registers, read through each class's setName. */
function registeredNames(): Map<string, string> {
	const xml = readFileSync(path.join(CHECKOUT, 'appinfo', 'info.xml'), 'utf8')
	const block = /<commands>([\s\S]*?)<\/commands>/.exec(xml)
	expect(block, 'appinfo/info.xml has a <commands> block').not.toBeNull()
	const names = new Map<string, string>()
	for (const match of block![1].matchAll(
		/<command>\s*OCA\\LaunchPad\\Command\\(\w+)\s*<\/command>/g,
	)) {
		const source = readFileSync(
			path.join(CHECKOUT, 'lib', 'Command', `${match[1]}.php`),
			'utf8',
		)
		const name = /setName\(\s*(?:name:\s*)?'([^']+)'/.exec(source)
		expect(name, `${match[1]} sets a name`).not.toBeNull()
		names.set(match[1], name![1])
	}
	return names
}

test.describe('REQ-CLI-008 registration', () => {
	// @e2e cli-commands::commands-are-registered-at-install
	test('occ list launchpad lists every class in the <commands> block, and help works', async () => {
		const registered = registeredNames()
		expect(registered.size).toBeGreaterThanOrEqual(8)
		const listed = (await listedCommands()).map((c) => c.name)
		for (const [cls, name] of registered) {
			expect(listed, `${cls} (${name})`).toContain(name)
		}
		const help = await runOcc('launchpad:dashboard:list', '--help')
		expect(help.code, describeRun(help)).toBe(0)
		expect(helpSection(help.stdout).trim(), 'the help text is shown').not.toBe(
			'',
		)
	})

	// @e2e cli-commands::new-sibling-spec-command-added-to-infoxml
	test('ExportCommand in the <commands> block is all it takes for launchpad:export to be listed', async () => {
		const registered = registeredNames()
		expect(registered.get('ExportCommand')).toBe('launchpad:export')
		expect((await listedCommands()).map((c) => c.name)).toContain(
			'launchpad:export',
		)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-009 Help text
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-009 help text', () => {
	// @e2e cli-commands::one-line-description-in-occ-list
	test("every command's description in occ list is one line of at most 60 characters", async () => {
		const tooLong: string[] = []
		for (const command of await listedCommands()) {
			expect(
				command.description.trim(),
				`${command.name} has a description`,
			).not.toBe('')
			expect(command.description, `${command.name}: one line`).not.toContain(
				'\n',
			)
			if (command.description.length > 60) {
				tooLong.push(
					`${command.name} (${command.description.length}): ${command.description}`,
				)
			}
		}
		expect(tooLong, 'descriptions over 60 characters').toEqual([])
	})

	// @e2e cli-commands::detailed-help-via-help
	test('dashboard:list --help explains the purpose, the options and gives two examples', async () => {
		const result = await runOcc('launchpad:dashboard:list', '--help')
		expect(result.code, describeRun(result)).toBe(0)
		const help = helpSection(result.stdout)
		const purpose = help.trim().split(/\n\s*\n/)[0] ?? ''
		const sentences = purpose
			.split(/(?<=[.!?])\s+/)
			.filter((s) => s.trim() !== '')
		expect(
			sentences.length,
			`purpose of two or three sentences:\n${purpose}`,
		).toBeGreaterThanOrEqual(2)
		expect(
			sentences.length,
			`purpose of two or three sentences:\n${purpose}`,
		).toBeLessThanOrEqual(3)
		for (const option of ['--user', '--group', '--status']) {
			expect(result.stdout, option).toContain(option)
		}
		const examples = help
			.split('\n')
			.filter((line) =>
				/^\s*(\$\s+)?php occ launchpad:dashboard:list\b/.test(line),
			)
		expect(examples.length, 'at least two examples').toBeGreaterThanOrEqual(2)
		expect(examples.join('\n')).toContain(
			'php occ launchpad:dashboard:list --user=alice --status=published --json',
		)
	})

	// @e2e cli-commands::help-documents-global-flags
	test('dashboard:delete --help documents -q/--quiet, --json and -n/--no-interaction', async () => {
		const result = await runOcc('launchpad:dashboard:delete', '--help')
		expect(result.code, describeRun(result)).toBe(0)
		expect(result.stdout).toMatch(/-q, --quiet\b/)
		expect(result.stdout).toMatch(/--json\b/)
		expect(result.stdout).toMatch(/-n, --no-interaction\b/)
	})

	// @e2e cli-commands::no-undocumented-or-hidden-commands
	test('no launchpad command is hidden and every argument and option shows in its --help', async () => {
		const commands = await listedCommands()
		expect(
			commands.filter((c) => c.hidden === true).map((c) => c.name),
			'hidden commands',
		).toEqual([])
		for (const command of commands) {
			const help = await runOcc(command.name, '--help')
			expect(help.code, describeRun(help)).toBe(0)
			for (const argument of Object.values(
				command.definition.arguments ?? {},
			)) {
				expect(
					help.stdout,
					`${command.name} --help names the argument ${argument.name}`,
				).toContain(argument.name)
			}
			for (const option of Object.values(command.definition.options ?? {})) {
				expect(
					help.stdout,
					`${command.name} --help names ${option.name}`,
				).toContain(option.name)
			}
		}
	})

	// @e2e cli-commands::examples-are-realistic
	test('copy-navigation --help gives the nl-to-en example and a piped --json one', async () => {
		const result = await runOcc('launchpad:i18n:copy-navigation', '--help')
		expect(result.code, describeRun(result)).toBe(0)
		const lines = helpSection(result.stdout).split('\n')
		expect(
			lines.some((l) =>
				/^\s*(\$\s+)?php occ launchpad:i18n:copy-navigation --from=nl --to=en\s*$/.test(
					l,
				),
			),
			result.stdout,
		).toBe(true)
		expect(
			lines.some((l) =>
				/php occ launchpad:i18n:copy-navigation\b.*--json.*\|/.test(l),
			),
			`a piped --json example\n${result.stdout}`,
		).toBe(true)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-010 Audit logging
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-010 audit logging', () => {
	// @e2e cli-commands::audit-log-on-success
	test('a successful run writes one audit line in the contract format', async () => {
		let result: RunResult | null = null
		const messages = await logDuring(async () => {
			result = await runOcc(
				'launchpad:dashboard:list',
				`--user=${alice!.username}`,
			)
		})
		expect(result!.code, describeRun(result!)).toBe(0)
		const lines = auditLines(messages, 'dashboard:list')
		expect(lines, messages.join('\n')).toHaveLength(1)
		const pattern = new RegExp(
			`^\\[launchpad\\] cli dashboard:list --user=${alice!.username} exitCode=0 durationMs=\\d+ byUser=\\S+$`,
		)
		expect(lines[0]).toMatch(pattern)
	})

	// @e2e cli-commands::audit-log-truncates-long-args
	test('arguments over 100 characters are cut to 100 with a trailing ...', async () => {
		const long = `e2e-cli-${'x'.repeat(130)}`
		const messages = await logDuring(async () => {
			await runOcc('launchpad:dashboard:list', `--group=${long}`)
		})
		const lines = auditLines(messages, 'dashboard:list')
		expect(lines, messages.join('\n')).toHaveLength(1)
		const args =
			/^\[launchpad\] cli dashboard:list (.*) exitCode=\d+ durationMs=\d+ byUser=\S+$/.exec(
				lines[0],
			)
		expect(args, lines[0]).not.toBeNull()
		expect(args![1].length, args![1]).toBeLessThanOrEqual(100)
		expect(args![1].endsWith('...'), args![1]).toBe(true)
		expect(lines[0]).not.toContain(long)
	})

	// @e2e cli-commands::byuser-is-cli-for-cron-invoked-commands
	test('launchpad:cleanup:scan, run with no session, is audited as byUser=cli', async () => {
		let result: RunResult | null = null
		const messages = await logDuring(async () => {
			result = await runOcc('launchpad:cleanup:scan')
		})
		expect([0, 1], describeRun(result!)).toContain(result!.code)
		const lines = auditLines(messages, 'cleanup:scan')
		expect(
			lines,
			`no audit line for cleanup:scan among:\n${messages.join('\n')}`,
		).toHaveLength(1)
		expect(lines[0]).toMatch(/ byUser=cli$/)
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-011 Discoverability
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-011 discoverability', () => {
	// @e2e cli-commands::full-command-listing-is-alphabetical
	test('occ list launchpad is sorted by name with the descriptions in one column', async () => {
		const rows = await listedRows('launchpad')
		expect(rows.length).toBeGreaterThanOrEqual(8)
		const names = rows.map((r) => r.name)
		expect(names).toEqual([...names].sort())
		const columns = new Set(rows.map((r) => r.column))
		expect(columns.size, rows.map((r) => r.line).join('\n')).toBe(1)
		expect(
			[...columns][0],
			'the description column starts inside 80 characters',
		).toBeLessThan(80)
	})

	// @e2e cli-commands::filtering-the-listing-by-sub-namespace
	test('occ list launchpad:dashboard shows only launchpad:dashboard: commands', async () => {
		const rows = await listedRows('launchpad:dashboard')
		const names = rows.map((r) => r.name)
		expect(names).toEqual(
			expect.arrayContaining([
				'launchpad:dashboard:debug-share',
				'launchpad:dashboard:delete',
				'launchpad:dashboard:list',
				'launchpad:dashboard:show',
			]),
		)
		for (const name of names) {
			expect(name).toMatch(/^launchpad:dashboard:/)
		}
	})

	// @e2e cli-commands::occ-list-links-to-help-for-each-command
	test('launchpad:i18n:export-strings, seen in the listing, has detailed --help', async () => {
		expect((await listedRows('launchpad')).map((r) => r.name)).toContain(
			'launchpad:i18n:export-strings',
		)
		const result = await runOcc('launchpad:i18n:export-strings', '--help')
		expect(result.code, describeRun(result)).toBe(0)
		expect(result.stdout).toContain('Usage:')
		expect(result.stdout).toContain('--output')
		expect(helpSection(result.stdout)).toContain(
			'php occ launchpad:i18n:export-strings',
		)
	})

	// @e2e cli-commands::pre-existing-commands-are-not-broken
	test('launchpad:export and launchpad:import keep their names and options, and round-trip a dashboard', async () => {
		// The pre-existing pair extends Symfony Command, not CommandBase, and
		// keeps its own option names instead of the global flags.
		const listed = await listedCommands()
		const optionsOf = (name: string): string[] => {
			const command = listed.find((c) => c.name === name)
			expect(
				command,
				`${name} is still registered under its own name`,
			).toBeDefined()
			return Object.keys(command!.definition.options)
		}
		expect(optionsOf('launchpad:export')).toEqual(
			expect.arrayContaining(['scope', 'dashboard-uuid', 'output']),
		)
		expect(optionsOf('launchpad:import')).toEqual(
			expect.arrayContaining(['file', 'preserve-uuids', 'user']),
		)

		const row = await dashboard(admin, 'pre-existing round trip')
		const added = await admin.post(`${API}/dashboard/${row.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 1,
				gridY: 2,
				gridWidth: 3,
				gridHeight: 2,
				content: { text: `cli round trip ${RUN}`, contentMode: 'markdown' },
			},
		})
		expect(added.status(), await added.text()).toBeLessThan(300)

		const archive = `/tmp/launchpad-e2e-cli-roundtrip-${RUN}.zip`
		try {
			const exported = await runOcc(
				'launchpad:export',
				'--scope=dashboard',
				`--dashboard-uuid=${row.uuid}`,
				`--output=${archive}`,
			)
			expect(exported.code, describeRun(exported)).toBe(0)
			expect(exported.stdout.trim(), describeRun(exported)).toBe(
				`Exported 1 dashboard to ${archive}`,
			)
			expect(await existsOnInstance(archive), 'the archive was written').toBe(
				true,
			)

			const imported = await runOcc(
				'launchpad:import',
				`--file=${archive}`,
				`--user=${ADMIN_USER}`,
			)
			expect(imported.code, describeRun(imported)).toBe(0)
			expect(imported.stdout.trim(), describeRun(imported)).toBe(
				'Imported 1 dashboards, skipped 0, errors: 0',
			)

			const named = await selectRows('launchpad_dashboards', {
				name: row.name,
			})
			const copies = named.filter((r) => Number(r.id) !== Number(row.id))
			dashboards.push(...copies.map((r) => Number(r.id)))
			expect(
				copies,
				'exactly one imported copy beside the original',
			).toHaveLength(1)
			const copy = copies[0]
			expect(
				copy.uuid,
				'without --preserve-uuids the copy gets a new UUID',
			).not.toBe(row.uuid)
			expect(copy.user_id, 'owned by the --user the import ran as').toBe(
				ADMIN_USER,
			)

			const shown = await runOcc(
				'launchpad:dashboard:show',
				String(copy.uuid),
				'--json',
			)
			expect(shown.code, describeRun(shown)).toBe(0)
			const widgets = envelope(shown).data.widgets as Array<
				Record<string, unknown>
			>
			expect(widgets, 'the widget travelled with the dashboard').toHaveLength(
				1,
			)
			expect(widgets[0]).toMatchObject({
				widgetId: 'text',
				gridX: 1,
				gridY: 2,
				gridWidth: 3,
				gridHeight: 2,
			})
			expect(JSON.stringify(widgets[0]), 'with its content').toContain(
				`cli round trip ${RUN}`,
			)
		} finally {
			await removeOnInstance(archive)
		}
	})
})

// ---------------------------------------------------------------------------
// REQ-CLI-012 Install a shipped template
// ---------------------------------------------------------------------------

test.describe('REQ-CLI-012 install a shipped template', () => {
	/** The admin templates, by uuid. */
	async function templatesByUuid(): Promise<
		Map<string, { id: number; targetGroups: string[]; updatedAt: string }>
	> {
		const rows = await listTemplates(admin)
		return new Map(
			rows.map((r) => [
				r.uuid,
				{ id: r.id, targetGroups: r.targetGroups, updatedAt: r.updatedAt },
			]),
		)
	}

	// @e2e cli-commands::a-deploy-script-installs-the-template-for-a-group
	test('template:install mijn-werkdag --group adds it for that group, exit 0, and prints its UUID', async () => {
		const group = await createGroup(admin, `e2e-cli-medewerkers-${RUN}`)
		groups.push(group)
		for (const key of SHIPPED_KEYS) {
			await writeAppConfig(admin, key, '').catch(() => undefined)
		}

		const result = await runOcc(
			'launchpad:template:install',
			'mijn-werkdag',
			`--group=${group}`,
		)
		expect(result.code, describeRun(result)).toBe(0)
		const uuid = /\(UUID: ([0-9a-f-]{36})\)/i.exec(result.stdout)
		expect(uuid, describeRun(result)).not.toBeNull()
		expect(result.stdout).toContain(group)

		const installed = (await templatesByUuid()).get(uuid![1])
		expect(installed, 'the printed UUID is an admin template').toBeDefined()
		templates.push(installed!.id)
		expect(installed!.targetGroups).toEqual([group])
	})

	// @e2e cli-commands::an-unknown-group-stops-the-install
	test('an unknown --group exits 1, names the group and adds no template', async () => {
		const missing = `e2e-cli-medewerkerz-${RUN}`
		const before = await templatesByUuid()
		const result = await runOcc(
			'launchpad:template:install',
			'mijn-werkdag',
			`--group=${missing}`,
			'--force',
		)
		expect(result.code, describeRun(result)).toBe(1)
		expect(`${result.stdout}${result.stderr}`, describeRun(result)).toContain(
			missing,
		)
		const after = await templatesByUuid()
		expect([...after.keys()].sort(), 'no template was added').toEqual(
			[...before.keys()].sort(),
		)
	})

	// @e2e cli-commands::an-update-refuses-the-options-that-change-who-gets-the-template
	test('--update with --group exits 1, names the conflicting options and writes nothing', async () => {
		const before = await templatesByUuid()
		const result = await runOcc(
			'launchpad:template:install',
			'mijn-werkdag',
			'--update',
			'--group=bestuur',
		)
		expect(result.code, describeRun(result)).toBe(1)
		const output = `${result.stdout}${result.stderr}`
		for (const option of ['--update', '--group', '--default', '--force']) {
			expect(output, describeRun(result)).toContain(option)
		}
		const after = await templatesByUuid()
		expect([...after.entries()].sort(), 'nothing was written').toEqual(
			[...before.entries()].sort(),
		)
	})

	// @e2e cli-commands::an-operator-updates-the-installed-template-and-reads-what-changed
	test('--update --dry-run prints the widget changes and writes nothing; without it the same changes are applied', async () => {
		// Stage an older install of the template the first test installed: the
		// installed version one below the shipped one, the header widget
		// replaced by another (one added, one removed on update) and the
		// calendar moved (one changed).
		const uuid = await appValue('shipped_template_mijn-werkdag')
		expect(uuid, 'mijn-werkdag is installed (by the --group test)').toMatch(/^[0-9a-f-]{36}$/i)
		const template = (await templatesByUuid()).get(uuid!)
		expect(template, 'the installed template is an admin template').toBeDefined()
		const shipped = Number(await appValue('shipped_template_version_mijn-werkdag'))
		expect(shipped, 'the shipped version is recorded').toBeGreaterThanOrEqual(1)
		const older = shipped - 1
		await setIntAppValue('shipped_template_version_mijn-werkdag', older)

		const where = { dashboard_id: template!.id }
		const header = await updateRows(
			'launchpad_widget_placements',
			{ widget_id: 'clock', custom_title: `E2E extra ${RUN}` },
			{ ...where, widget_id: 'header' },
		)
		expect(header, 'the template has one header widget to replace').toBe(1)
		const calendar = await updateRows(
			'launchpad_widget_placements',
			{ grid_x: 0 },
			{ ...where, widget_id: 'calendar' },
		)
		expect(calendar, 'the template has one calendar widget to move').toBe(1)

		const placements = async () =>
			(await selectRows('launchpad_widget_placements', where))
				.map((r) => JSON.stringify(Object.entries(r).sort()))
				.sort()
		const staged = await placements()

		const changeLines = (out: string): string[] =>
			out.split('\n').filter((l) => /^ {2}[+~-] (added|removed|changed):/.test(l))
		const expectChanges = (lines: string[], out: string): void => {
			expect(
				lines.filter((l) => l.startsWith('  + added:')).some((l) => l.includes('header')),
				`one line for the header that comes back\n${out}`,
			).toBe(true)
			expect(
				lines.filter((l) => l.startsWith('  - removed:')).some((l) => l.includes(`E2E extra ${RUN} (clock)`)),
				`one line for the widget that goes\n${out}`,
			).toBe(true)
			expect(
				lines.filter((l) => l.startsWith('  ~ changed:')).some((l) =>
					l.includes('Agenda vandaag (calendar) (position)'),
				),
				`one line for the moved calendar, naming what changed\n${out}`,
			).toBe(true)
			expect(lines, `exactly those three\n${out}`).toHaveLength(3)
		}

		const dry = await runOcc('launchpad:template:install', 'mijn-werkdag', '--update', '--dry-run')
		expect(dry.code, describeRun(dry)).toBe(0)
		expect(dry.stdout, describeRun(dry)).toContain('Dry run, nothing written.')
		expect(dry.stdout).toContain(`from version ${older} to version ${shipped}`)
		const dryLines = changeLines(dry.stdout)
		expectChanges(dryLines, dry.stdout)
		expect(await placements(), 'a dry run writes no widget').toEqual(staged)
		expect(
			Number(await appValue('shipped_template_version_mijn-werkdag')),
			'a dry run leaves the installed version',
		).toBe(older)

		const run = await runOcc('launchpad:template:install', 'mijn-werkdag', '--update')
		expect(run.code, describeRun(run)).toBe(0)
		expect(run.stdout, describeRun(run)).not.toContain('Dry run')
		expect(run.stdout).toContain(`Updated template mijn-werkdag from version ${older} to version ${shipped}`)
		expect(changeLines(run.stdout), 'the same lines as the dry run').toEqual(dryLines)
		expect(run.stdout, 'how many members\' copies changed').toMatch(
			/Members with a copy of this template: \d+\. (Copies changed: \d+|Their copies are updated in the background)\./,
		)

		expect(
			Number(await appValue('shipped_template_version_mijn-werkdag')),
			'the installed version is now the shipped one',
		).toBe(shipped)
		const after = await selectRows('launchpad_widget_placements', where)
		expect(after.filter((r) => r.widget_id === 'clock'), 'the extra widget is gone').toHaveLength(0)
		expect(after.filter((r) => r.widget_id === 'header'), 'the header is back').toHaveLength(1)
		expect(
			Number(after.find((r) => r.widget_id === 'calendar')?.grid_x),
			'the calendar is back where the template puts it',
		).toBe(8)
	})

	// @e2e cli-commands::an-unknown-template-id-lists-the-shipped-ones
	test('an unknown template id exits 1 and lists the shipped ids', async () => {
		const result = await runOcc('launchpad:template:install', 'does-not-exist')
		expect(result.code, describeRun(result)).toBe(1)
		expect(`${result.stdout}${result.stderr}`, describeRun(result)).toContain(
			'mijn-werkdag',
		)
	})
})
