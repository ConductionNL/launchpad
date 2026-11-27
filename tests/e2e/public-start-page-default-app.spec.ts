/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LaunchPad as Nextcloud's default app (REQ-PSP-003 in
 * openspec/specs/dashboard-public-share/spec.md).
 *
 * WHAT DECIDES IT. `DefaultAppSetting::claim()` sets Nextcloud's `defaultapp`
 * system value to `launchpad` only when none is set, and the post-migration
 * repair step `SetDefaultApp` calls it on install and upgrade. The admin
 * switch "Open LaunchPad after signing in" (`defaultAppEnabled`) releases the
 * value when it is LaunchPad's and claims it again when nothing is set.
 *
 * HOW IT IS STAGED. No HTTP route writes `defaultapp`, so this file sets and
 * reads it with `occ config:system:set|get|delete` (`support/occ.ts`), and
 * stages an upgrade with `occ maintenance:repair`, which runs every enabled
 * app's post-migration steps, as `allowed-websites-list.spec.ts` does. The
 * administrator's switch is driven through the admin page.
 *
 * WHY IT IS SAFE ON THE SHARED INSTANCE. Where a later login lands depends on
 * `defaultapp` (public-start-page.spec.ts reads it), so both tests snapshot
 * `defaultapp` and `defaultAppEnabled` first and put them back in `finally`:
 * the switch first, because switching it has its own effect on `defaultapp`,
 * then `defaultapp` itself, exactly as it was.
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { readSettings, writeSettings } from './support/adminSettings.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { describeRun, runOcc } from './support/occ.ts'
import {
	adminContext,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

test.describe.configure({ mode: 'serial' })
test.setTimeout(240_000)

// The administration is a Launchpad page since admin-full-width-page.
const SETTINGS_PAGE = `${BASE_URL}/index.php/settings/admin/launchpad#general`
const SWITCH_LABEL = 'Open LaunchPad after signing in'

let admin: APIRequestContext
const principals: Principal[] = []

/**
 * Nextcloud's `defaultapp` system value, or null when it is absent.
 * occ answers an unset key with exit code 1 and nothing on stdout.
 */
async function defaultApp(): Promise<string | null> {
	const result = await runOcc('config:system:get', 'defaultapp')
	if (result.code === 1 && result.stdout.trim() === '') {
		return null
	}
	expect(result.code, describeRun(result)).toBe(0)
	return result.stdout.replace(/\r?\n$/, '')
}

/** Write `defaultapp`, or delete it for null, and read it back. */
async function setDefaultApp(value: string | null): Promise<void> {
	const result = value === null
		? await runOcc('config:system:delete', 'defaultapp')
		: await runOcc('config:system:set', 'defaultapp', `--value=${value}`)
	expect(result.code, describeRun(result)).toBe(0)
	expect(await defaultApp(), 'defaultapp after the write').toBe(value)
}

/** Stage an upgrade: run the post-migration repair steps and insist they finished. */
async function upgrade(): Promise<void> {
	const repair = await runOcc('maintenance:repair')
	expect(repair.code, describeRun(repair)).toBe(0)
}

/**
 * Snapshot `defaultapp` and the switch, run the body, and put both back,
 * the switch first.
 */
async function withDefaultAppRestored(body: () => Promise<void>): Promise<void> {
	const priorValue = await defaultApp()
	const priorEnabled = (await readSettings(admin)).defaultAppEnabled !== false
	try {
		await body()
	} finally {
		await writeSettings(admin, { defaultAppEnabled: priorEnabled }).catch(() => undefined)
		await setDefaultApp(priorValue).catch(() => undefined)
	}
}

/**
 * Sign a fresh ordinary account in through the login form, in a context
 * with an empty cookie jar, and return the page where the login landed.
 */
async function signInFresh(browser: Browser): Promise<Page> {
	const p = await createPrincipal(admin, 'e2e-psp-default-app')
	principals.push(p)
	await assertActingAs(p.api, p.username)
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	const page = await context.newPage()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(p.username)
	await page.locator('input[name="password"]').fill(p.password)
	await page.locator('button[type="submit"]').click({ noWaitAfter: true })
	await expect(page).not.toHaveURL(/\/login/, { timeout: 60_000 })
	return page
}

/** Open the admin page on the General tab and return the switch's label. */
async function openSwitch(page: Page) {
	await page.goto(SETTINGS_PAGE)
	await adminTab(page, 'general').click()
	const form = page.getByTestId('admin-default-settings')
	await expect(form).toBeVisible({ timeout: 30_000 })
	return form
}

/** Flip the switch through the page and require the 200 of the PUT it sends. */
async function flipSwitch(page: Page, to: boolean): Promise<void> {
	const form = page.getByTestId('admin-default-settings')
	const box = form.getByLabel(SWITCH_LABEL, { exact: true })
	if (to) {
		await expect(box).not.toBeChecked()
	} else {
		await expect(box).toBeChecked()
	}
	const put = page.waitForResponse(
		(res) => res.url().includes('/api/admin/settings') && res.request().method() === 'PUT',
	)
	await form.getByText(SWITCH_LABEL, { exact: true }).click()
	const res = await put
	expect(res.status()).toBe(200)
	expect(res.request().postDataJSON()).toMatchObject({ defaultAppEnabled: to })
	expect((await readSettings(admin)).defaultAppEnabled).toBe(to)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	for (const p of principals) {
		await removePrincipal(admin, p).catch(() => undefined)
	}
	await admin.dispose()
})

// @e2e dashboard-public-share::launchpad-is-the-default-app-after-install
test('an install or upgrade makes LaunchPad the default app only when none is set', async ({ browser }) => {
	await withDefaultAppRestored(async () => {
		await writeSettings(admin, { defaultAppEnabled: true })

		// GIVEN: no default app is configured.
		await setDefaultApp(null)

		// WHEN: LaunchPad is installed or upgraded.
		await upgrade()

		// THEN: defaultapp is launchpad, so signing in lands on the Workspace page.
		expect(await defaultApp()).toBe('launchpad')
		const page = await signInFresh(browser)
		try {
			await expect(page).toHaveURL(/\/apps\/launchpad/, { timeout: 60_000 })
		} finally {
			await page.context().close()
		}

		// AND: an upgrade never overwrites a default app the administrator
		// chose after the install.
		await setDefaultApp('files')
		await upgrade()
		expect(await defaultApp(), 'the administrator chose files after the install').toBe('files')

		// AND: an empty value, which Nextcloud's own "reset to default" writes, is kept too.
		await setDefaultApp('')
		await upgrade()
		expect(await defaultApp(), 'an empty defaultapp is a configured one').toBe('')
	})
})

// @e2e dashboard-public-share::the-administrator-switches-it-off
test('switching "Open LaunchPad after signing in" off gives the default app back, and keeps a later choice', async ({
	page,
}) => {
	await withDefaultAppRestored(async () => {
		// GIVEN: LaunchPad set itself as the default app.
		await writeSettings(admin, { defaultAppEnabled: true })
		await setDefaultApp(null)
		await upgrade()
		expect(await defaultApp()).toBe('launchpad')

		// WHEN: the administrator switches it off on the admin page.
		await openSwitch(page)
		await flipSwitch(page, false)

		// THEN: LaunchPad unsets defaultapp, so Nextcloud's own default applies.
		expect(await defaultApp(), 'switching off unsets the value LaunchPad set').toBeNull()

		// AND: switching it back on sets LaunchPad again when no default app is set.
		await flipSwitch(page, true)
		expect(await defaultApp()).toBe('launchpad')

		// AND: when the administrator chose another default app in the
		// meantime, switching off keeps that choice.
		await setDefaultApp('files')
		await openSwitch(page)
		await flipSwitch(page, false)
		expect(await defaultApp(), 'a default app chosen later is not LaunchPad\'s to unset').toBe('files')

		// AND: switching back on does not take over a default app that is set.
		await flipSwitch(page, true)
		expect(await defaultApp(), 'switching on claims only an unset default app').toBe('files')
	})
})
