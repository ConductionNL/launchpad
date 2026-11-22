/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the Talk discussion on shared dashboards
 * (openspec change `openregister-leaf-integrations`, tasks 3 and 11 to 14).
 *
 * Task 3 reads the Dashboard schema OpenRegister actually imported, through
 * OpenRegister's own API, and requires `configuration.linkedTypes` to be
 * exactly `["talk"]`: the file in `register.d/` says nothing about what the
 * importer kept.
 *
 * Tasks 11 to 13 open the real workspace page as real accounts. Talk is not
 * installed on the stack and must not be, so the page is made to BELIEVE it
 * is: an init script adds `spreed` to `_oc_appswebroots` as Nextcloud's
 * inline config declares it, which is what `OC.appswebroots` and therefore
 * `discussionAvailable()` read. Requests to Talk's own routes
 * (`/apps/spreed/`, `/ocs/v2.php/apps/spreed/`) would fail without the app
 * and are stubbed with empty answers; nothing else is. LaunchPad's mount
 * decision, its shares call and the OpenRegister integration call
 * (`/apps/openregister/api/objects/launchpad/dashboard/{uuid}/integrations/talk`,
 * against the dashboard's real mirror object) all run unstubbed. Every test
 * first checks that the page did see `spreed`, so an absent surface cannot
 * be explained by a page that never thought Talk was there.
 *
 * Task 14 probes as a third account with no access, and compares the
 * refusal of the dashboard (LaunchPad's API) with the refusal of its
 * discussion's access path (the dashboard's OpenRegister object and its talk
 * integration), with the owner's successful read of the same object as the
 * control.
 *
 * Accounts, groups and dashboards are throwaway, removed in afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-talk-leaf::overlay-declares-the-leaf
 *   @e2e dashboard-talk-leaf::group-dashboard-shows-discussion
 *   @e2e dashboard-talk-leaf::shared-personal-dashboard-shows-discussion
 *   @e2e dashboard-talk-leaf::unshared-personal-dashboard-shows-nothing
 *   @e2e dashboard-talk-leaf::non-viewer-cannot-reach-the-discussion
 *
 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'
import type { CreatedGroupDashboard, DashboardRow } from './support/dashboardsSpec.ts'
import type { OwnDashboard } from './support/ownDashboard.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	createGroupDashboard,
	removeGroupDashboards,
	setActivePreference,
} from './support/dashboardsSpec.ts'
import { setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'
import { share } from './support/shareApi.ts'

const RUN = Date.now()
const OR_OBJECTS = '/index.php/apps/openregister/api/objects/launchpad/dashboard'
const DISCUSSION = '[data-testid="dashboard-discussion"]'
const REGION = '[data-testid="launchpad-dashboard-region"]'

let admin: APIRequestContext
const principals: Principal[] = []
const groups: string[] = []
const groupDashboards: CreatedGroupDashboard[] = []
const ownDashboards: OwnDashboard[] = []

// Each test logs in one to three fresh accounts; a first login provisions a home.
test.describe.configure({ timeout: 180_000 })

test.beforeAll(async () => {
	admin = await adminContext()
})

test.afterAll(async () => {
	await removeGroupDashboards(admin, groupDashboards)
	// Newest first: each fixture puts back the admin state it found.
	for (const own of [...ownDashboards].reverse()) {
		await tearDownOwnDashboard(own)
	}
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const group of groups) {
		await removeGroup(admin, group)
	}
	await admin.dispose()
})

/**
 * Make the page see Talk as installed, and answer Talk's own routes.
 *
 * @param context the browser context, before the page navigates.
 * @return the Talk requests that were stubbed.
 */
async function pretendTalkIsInstalled(context: BrowserContext): Promise<string[]> {
	await context.addInitScript(() => {
		let roots: unknown
		Object.defineProperty(window, '_oc_appswebroots', {
			configurable: true,
			get: () => roots,
			set: (value: unknown) => {
				roots = value !== null && typeof value === 'object'
					? { ...(value as Record<string, string>), spreed: '/apps/spreed' }
					: value
			},
		})
	})
	const stubbed: string[] = []
	await context.route(/\/(ocs\/v[12]\.php\/)?apps\/spreed\//, async (route) => {
		const url = route.request().url()
		stubbed.push(url)
		if (/\.(m?js)(\?|$)/.test(url)) {
			await route.fulfill({ status: 200, contentType: 'application/javascript', body: '' })
		} else if (/\.css(\?|$)/.test(url)) {
			await route.fulfill({ status: 200, contentType: 'text/css', body: '' })
		} else {
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ ocs: { meta: { status: 'ok', statuscode: 200 }, data: [] } }),
			})
		}
	})
	return stubbed
}

/**
 * Log a principal in with Talk made to look installed, and open the
 * workspace on a dashboard.
 *
 * @param browser the browser.
 * @param principal the account.
 * @param uuid the dashboard that must be the one on screen.
 * @return the session and the talk integration responses the page received.
 */
async function openWithTalk(
	browser: Browser,
	principal: Principal,
	uuid: string,
): Promise<{ context: BrowserContext; page: Page; integrationStatuses: number[] }> {
	await setActivePreference(principal.api, uuid)
	const { context, page } = await loginAs(browser, principal.username, principal.password)
	await pretendTalkIsInstalled(context)
	const integrationStatuses: number[] = []
	page.on('response', (res) => {
		if (res.url().includes(`/objects/launchpad/dashboard/${uuid}/integrations/talk`)) {
			integrationStatuses.push(res.status())
		}
	})
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(REGION)).toHaveAttribute('data-dashboard-uuid', uuid, {
		timeout: 30_000,
	})
	const seen = await page.evaluate(() => {
		const roots = (window as unknown as { OC?: { appswebroots?: Record<string, string> } }).OC?.appswebroots
		return Boolean(roots && Object.hasOwn(roots, 'spreed') && Object.hasOwn(roots, 'openregister'))
	})
	expect(seen, 'the page must see both openregister and spreed in OC.appswebroots').toBe(true)
	return { context, page, integrationStatuses }
}

/**
 * Require the discussion surface on screen, with the integration read
 * answered by OpenRegister for the dashboard's object.
 *
 * @param page the page.
 * @param integrationStatuses the talk integration responses so far.
 * @return nothing.
 */
async function expectDiscussion(page: Page, integrationStatuses: number[]): Promise<void> {
	await expect(page.locator(DISCUSSION)).toBeVisible({ timeout: 20_000 })
	await expect(page.locator(DISCUSSION)).toContainText('Discussion')
	await expect.poll(() => integrationStatuses.length, { timeout: 15_000 }).toBeGreaterThan(0)
	expect(integrationStatuses.every((status) => status === 200), JSON.stringify(integrationStatuses)).toBe(true)
}

/**
 * A fresh principal, checked to really act as itself.
 *
 * @param prefix the account prefix.
 * @return the principal.
 */
async function principal(prefix: string): Promise<Principal> {
	const p = await createPrincipal(admin, `${prefix}-${RUN}`)
	principals.push(p)
	await assertActingAs(p.api, p.username)
	return p
}

test('Task 3: the imported Dashboard schema carries linkedTypes ["talk"]', async () => {
	const registers = await admin.get('/index.php/apps/openregister/api/registers?_limit=200')
	expect(registers.status(), await registers.text()).toBe(200)
	const body = await registers.json()
	const register = (body.results ?? body).find((r: { slug?: string }) => r.slug === 'launchpad')
	expect(register, 'the launchpad register must be imported').toBeTruthy()

	let dashboard: Record<string, any> | undefined
	for (const id of register.schemas as Array<number | string>) {
		const res = await admin.get(`/index.php/apps/openregister/api/schemas/${id}`)
		expect(res.status(), await res.text()).toBe(200)
		const schema = await res.json()
		if (schema.slug === 'dashboard') {
			dashboard = schema
		}
	}
	expect(dashboard, 'the launchpad register must hold a dashboard schema').toBeTruthy()
	expect(dashboard!.configuration?.linkedTypes).toEqual(['talk'])
})

test('Task 11: a member of a group dashboard\'s group sees its discussion', async ({ browser }) => {
	const group = await createGroup(admin, `e2e-leaf-${RUN}`)
	groups.push(group)
	const member = await principal('e2e-leaf-member')
	await addToGroup(admin, member.username, group)
	const dashboard: DashboardRow = await createGroupDashboard(admin, group, `E2E Leaf Group ${RUN}`)
	groupDashboards.push({ groupId: group, uuid: dashboard.uuid })

	const session = await openWithTalk(browser, member, dashboard.uuid)
	try {
		await expectDiscussion(session.page, session.integrationStatuses)
	} finally {
		await session.context.close()
	}
})

test('Task 12: a shared personal dashboard shows the discussion to owner and recipient', async ({ browser }) => {
	const shared = await setUpOwnDashboard(`e2e-leaf-owner-${RUN}`, `E2E Leaf Shared ${RUN}`)
	ownDashboards.push(shared)
	const recipient = await principal('e2e-leaf-recipient')
	await publishForRecipients(shared.owner.api, shared.id)
	await share(shared.owner.api, shared.id, 'user', recipient.username, 'view_only')

	const owner = await openWithTalk(browser, shared.owner, shared.uuid)
	try {
		await expectDiscussion(owner.page, owner.integrationStatuses)
	} finally {
		await owner.context.close()
	}

	const other = await openWithTalk(browser, recipient, shared.uuid)
	try {
		await expectDiscussion(other.page, other.integrationStatuses)
	} finally {
		await other.context.close()
	}

})

test('Task 13: an unshared personal dashboard has no discussion affordance', async ({ browser }) => {
	const unshared = await setUpOwnDashboard(`e2e-leaf-solo-${RUN}`, `E2E Leaf Solo ${RUN}`)
	ownDashboards.push(unshared)
	const solo = await openWithTalk(browser, unshared.owner, unshared.uuid)
	try {
		// The shares read answers before the surface could appear.
		await solo.page.waitForResponse(
			(res) => res.url().includes(`/api/dashboard/${unshared.id}/shares`),
			{ timeout: 15_000 },
		).catch(() => null)
		await solo.page.waitForTimeout(1_000)
		await expect(solo.page.locator(DISCUSSION)).toHaveCount(0)
		await expect(solo.page.locator('.dashboard-discussion')).toHaveCount(0)
		expect(solo.integrationStatuses, 'no talk integration request for an unshared dashboard').toEqual([])
	} finally {
		await solo.context.close()
	}
})

test.describe('Task 14: a non-viewer is refused the dashboard and its discussion alike', () => {
	let own: OwnDashboard
	let stranger: Principal

	test.beforeAll(async () => {
		// Three first logins; a hook does not inherit the file's test timeout.
		test.setTimeout(180_000)
		own = await setUpOwnDashboard(`e2e-leaf-guard-${RUN}`, `E2E Leaf Guarded ${RUN}`)
		ownDashboards.push(own)
		const recipient = await principal('e2e-leaf-guard-recipient')
		await share(own.owner.api, own.id, 'user', recipient.username, 'view_only')
		stranger = await principal('e2e-leaf-stranger')
	})

	test('the dashboard itself is refused, and the owner reads its discussion object', async () => {
		// Control: the mirror object exists and its owner may read it.
		const ownerRead = await own.owner.api.get(`${OR_OBJECTS}/${own.uuid}`)
		expect(ownerRead.status(), await ownerRead.text()).toBe(200)

		const dashboard = await stranger.api.get(`${API}/dashboard/${own.id}`)
		expect([403, 404], `dashboard: ${dashboard.status()} ${await dashboard.text()}`).toContain(dashboard.status())
	})

	test('the discussion\'s access path refuses the same way', async () => {
		const dashboard = await stranger.api.get(`${API}/dashboard/${own.id}`)
		const object = await stranger.api.get(`${OR_OBJECTS}/${own.uuid}`)
		const talk = await stranger.api.get(`${OR_OBJECTS}/${own.uuid}/integrations/talk`)
		const denied = (status: number) => status === 403 || status === 404

		expect(denied(dashboard.status()), `dashboard: ${dashboard.status()}`).toBe(true)
		expect(denied(object.status()), `object: ${object.status()} ${await object.text()}`).toBe(true)
		expect(denied(talk.status()), `talk integration: ${talk.status()} ${await talk.text()}`).toBe(true)
	})
})
