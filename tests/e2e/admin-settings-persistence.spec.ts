/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * admin-settings REQ-ASET-007, Settings Persistence: the two scenarios that
 * were excluded as "needs restarting the Nextcloud server" and "needs
 * upgrading the installed app mid-test", written for e2e-coverage-programme
 * task 1.1.
 *
 * WHAT A RESTART IS HERE. Nextcloud keeps nothing of a request in the PHP
 * process: what survives a restart is what sits in the database, and what a
 * restart loses is whatever only lived in a process or its local memory
 * cache. So the restart is stood in for by a PHP process that never served
 * the write: `support/instanceDb.ts` boots Nextcloud in a fresh CLI process
 * (no APCu shared with the web server, no request state) and asks the app's
 * own `AdminSettingsService::getSettings()`. The stored row is read as well,
 * because `allowUserDashboards: false` is also the factory default (REQ-ASET-001)
 * and a setting that "reverted to its default" by losing its row would read
 * the same through the API. A non-default value (grid columns 8, level
 * view_only) is configured beside it, so a store that kept nothing fails.
 *
 * WHAT AN UPDATE IS HERE. Nextcloud decides an app needs updating when the
 * code's version is above the `installed_version` it recorded; `occ upgrade`
 * then runs the app's pending migrations and its pre- and post-migration
 * repair steps and records the new version, which is exactly what installing
 * a new release does. The test lowers `installed_version` to 1.0.0, so the
 * instance holds the code of a newer release than it last ran, and runs
 * `occ upgrade`. "A new setting introduced in the update" is a key the old
 * version never stored: its row is removed before the update (after proving
 * a non-default value was readable through it) and must read its default
 * afterwards.
 *
 * 🔴 THE INSTANCE IS PUT BACK. The whole settings table is snapshotted and
 * restored exactly, ids included; `installed_version` is restored and, if the
 * upgrade did not finish, run again; maintenance mode is switched off; and
 * `defaultapp` (which the `SetDefaultApp` repair step may claim) is put back
 * as it was.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-settings::settings-survive-server-restart
 *   @e2e admin-settings::settings-survive-app-update
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	dropSettingRows,
	putBackSettingRows,
	settingRows,
} from './support/adminSettingRows.ts'
import {
	readSettings,
	restoreSettings,
	writeSettings,
} from './support/adminSettings.ts'
import { instancePhp } from './support/instanceDb.ts'
import { appValue, describeRun, runOcc } from './support/occ.ts'
import { adminContext, assertActingAs } from './support/principals.ts'

/** The settings these tests configure, as GET answers them. */
const CONFIGURED = {
	allowUserDashboards: false,
	defaultGridColumns: 8,
	defaultPermissionLevel: 'view_only',
	linkCreateFileExtensions: ['txt', 'md'],
} as const

/** The same, under the names PUT takes. */
const CONFIGURED_BODY = {
	allowUserDash: CONFIGURED.allowUserDashboards,
	defaultGridCols: CONFIGURED.defaultGridColumns,
	defaultPermLevel: CONFIGURED.defaultPermissionLevel,
	linkCreateFileExts: [...CONFIGURED.linkCreateFileExtensions],
}

/** The setting standing in for one an update introduces, and its default. */
const NEW_SETTING = {
	readKey: 'maxWidgetsPerDashboard',
	writeKey: 'maxWidgetsPerDashboard',
	rowKey: 'max_widgets_per_dashboard',
	nonDefault: 7,
	defaultValue: 0,
} as const

/** An installed version below every release that carries this code. */
const OLD_VERSION = '1.0.0'

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(300_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
})

test.afterAll(async () => {
	await restoreSettings(admin, baseline)
	await admin.dispose()
})

/**
 * The settings as a PHP process that never served the write reads them,
 * through the app's own service.
 *
 * @return the settings object.
 */
async function settingsFromFreshProcess(): Promise<Record<string, unknown>> {
	return instancePhp<Record<string, unknown>>(
		`
\\OC_App::loadApp('launchpad');
$out = \\OCP\\Server::get(\\OCA\\LaunchPad\\Service\\AdminSettingsService::class)->getSettings();
`,
	)
}

/**
 * The JSON-decoded stored value of one setting row, or undefined when the
 * key has no row.
 *
 * @param rowKey the `setting_key`.
 * @return the decoded value.
 */
async function storedValue(rowKey: string): Promise<unknown> {
	const rows = await settingRows(rowKey)
	if (rows.length === 0) {
		return undefined
	}
	expect(rows, `one row for ${rowKey}`).toHaveLength(1)
	return JSON.parse(String(rows[0].setting_value))
}

/** Pick the configured keys out of a settings object. */
function configuredPart(settings: Record<string, unknown>): Record<string, unknown> {
	const out: Record<string, unknown> = {}
	for (const key of Object.keys(CONFIGURED)) {
		out[key] = settings[key]
	}
	return out
}

/** Nextcloud's `defaultapp` system value, or null when it is absent. */
async function defaultApp(): Promise<string | null> {
	const result = await runOcc('config:system:get', 'defaultapp')
	if (result.code === 1 && result.stdout.trim() === '') {
		return null
	}
	expect(result.code, describeRun(result)).toBe(0)
	return result.stdout.replace(/\r?\n$/, '')
}

// ── REQ-ASET-007 ────────────────────────────────────────────────────────────

// @e2e admin-settings::settings-survive-server-restart
test('settings configured over HTTP are read back unchanged by a process that never served the write', async () => {
	const snapshot = await settingRows()
	try {
		// Make the stored state differ from the configuration first, so the
		// write below is a change and a read that echoed a cache of it could
		// not be mistaken for the stored state.
		await writeSettings(admin, {
			allowUserDash: true,
			defaultGridCols: 12,
			defaultPermLevel: 'full',
		})
		expect((await readSettings(admin)).allowUserDashboards).toBe(true)

		await writeSettings(admin, CONFIGURED_BODY)

		// THEN, read by a process with none of the web server's state.
		const fresh = await settingsFromFreshProcess()
		expect(configuredPart(fresh)).toEqual(CONFIGURED)

		// AND it MUST NOT revert to its default: false is the default, so the
		// configured false must be stored, not merely absent.
		expect(
			await storedValue('allow_user_dashboards'),
			'allow_user_dashboards must be stored as false, not left to the default',
		).toBe(false)
		expect(await storedValue('default_grid_columns')).toBe(8)
		expect(await storedValue('default_permission_level')).toBe('view_only')

		// And GET /api/admin/settings, from a new HTTP request, answers the same.
		const after = await readSettings(admin)
		expect(after.allowUserDashboards).toBe(false)
		expect(configuredPart(after)).toEqual(CONFIGURED)
	} finally {
		await putBackSettingRows(snapshot)
		await restoreSettings(admin, baseline)
	}
})

// @e2e admin-settings::settings-survive-app-update
test('an app update keeps every configured setting and gives a setting the old version never stored its default', async () => {
	const snapshot = await settingRows()
	const installed = await appValue('installed_version')
	expect(installed, 'launchpad must record an installed_version').not.toBeNull()
	expect(installed).not.toBe(OLD_VERSION)
	const defaultAppBefore = await defaultApp()

	try {
		// GIVEN custom settings.
		await writeSettings(admin, CONFIGURED_BODY)
		expect(configuredPart(await readSettings(admin))).toEqual(CONFIGURED)

		// The "new" setting: prove its row is read (a non-default value comes
		// back), then remove the row, as a version that never knew the key
		// would have left it.
		await writeSettings(admin, { [NEW_SETTING.writeKey]: NEW_SETTING.nonDefault })
		expect((await readSettings(admin))[NEW_SETTING.readKey]).toBe(NEW_SETTING.nonDefault)
		await dropSettingRows(NEW_SETTING.rowKey)
		expect(await settingRows(NEW_SETTING.rowKey)).toEqual([])

		// WHEN the app is updated: the instance last ran an older version, and
		// occ upgrade runs this one's migrations and repair steps.
		const lowered = await runOcc(
			'config:app:set',
			'launchpad',
			'installed_version',
			`--value=${OLD_VERSION}`,
		)
		expect(lowered.code, describeRun(lowered)).toBe(0)
		const upgrade = await runOcc('upgrade')
		expect(upgrade.code, describeRun(upgrade)).toBe(0)
		expect(
			await appValue('installed_version'),
			`occ upgrade must have updated launchpad\n${describeRun(upgrade)}`,
		).toBe(installed)

		// THEN every configured setting is preserved, as stored and as answered.
		const after = await readSettings(admin)
		expect(configuredPart(after)).toEqual(CONFIGURED)
		expect(await storedValue('allow_user_dashboards')).toBe(false)
		expect(await storedValue('default_grid_columns')).toBe(8)
		expect(await storedValue('default_permission_level')).toBe('view_only')
		expect(await storedValue('link_create_file_extensions')).toEqual(['txt', 'md'])

		// AND the setting the old version never stored answers its default.
		expect(after[NEW_SETTING.readKey]).toBe(NEW_SETTING.defaultValue)
		const newStored = await storedValue(NEW_SETTING.rowKey)
		if (newStored !== undefined) {
			expect(newStored, 'a row the update wrote must hold the default').toBe(NEW_SETTING.defaultValue)
		}
	} finally {
		if ((await appValue('installed_version')) !== installed) {
			await runOcc('upgrade')
		}
		if ((await appValue('installed_version')) !== installed && installed !== null) {
			await runOcc('config:app:set', 'launchpad', 'installed_version', `--value=${installed}`)
		}
		await runOcc('maintenance:mode', '--off')
		await putBackSettingRows(snapshot)
		if (defaultAppBefore === null) {
			await runOcc('config:system:delete', 'defaultapp')
		} else {
			await runOcc('config:system:set', 'defaultapp', `--value=${defaultAppBefore}`)
		}
		await restoreSettings(admin, baseline)
	}
})
