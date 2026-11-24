/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the LaunchPad setup wizard
 * (openspec/specs/setup-wizard), written for the e2e-coverage-programme.
 *
 * HOW THE COMPLETED FLAG BEHAVES, AND WHY THIS FILE IS ORDERED
 * ===========================================================
 * The flag is the `setup_wizard_complete` row in `oc_launchpad_admin_settings`
 * (AdminSettingKey::SETUP_WIZARD_COMPLETE), not an IAppConfig key. Exactly one
 * writer touches it, `SetupWizardService::markWizardComplete()`, and it only
 * ever writes `true` — reached from `POST /api/admin/setup-wizard/complete` and
 * `occ launchpad:setup`. `PUT /api/admin/settings` does not bind the key, the
 * footer and import endpoints do not either, and nothing deletes the row. So
 * no API can put the flag back to `false` and nothing here can restore it.
 *
 * The wizard is still reproducible, because the CI fixture is a fresh
 * instance on which the flag starts `false`. The file is therefore split in
 * two, in this order:
 *
 *   1. "fresh instance": every test whose GIVEN needs `complete = false`.
 *      Each one skips itself, saying why, when the flag is already `true`
 *      (a warm developer rig). The last of them drives Finish through the
 *      wizard and is the one write that flips the flag.
 *   2. "completed instance": everything that holds once the flag is `true`,
 *      which is also every re-run of the wizard. Its beforeAll makes sure the
 *      flag is `true` (idempotent POST), so it runs on any instance.
 *
 * Everything else these tests write is restored: the group order is read in
 * beforeAll and written back in afterAll, the seeded groups and the probe
 * account are deleted.
 *
 * The admin page is `/apps/launchpad/admin` (the banner lives in the
 * General tab of AdminSettings.vue). The spec still names
 * `/apps/launchpad/admin/dashboards`, which is a deep-link slug in the SPA, not
 * the admin section. The step counter reads "Step n / 5": five steps, Welcome,
 * Groups, Examples, Roles and Done (REQ-WIZ-012). The storage step was retired
 * by decision 131 and the footer step by setup-five-steps-and-admin-tour; the
 * footer is set on the admin tab Organisatiemenu.
 *
 * The five-step walk (REQ-WIZ-012, "No footer step") is written from the
 * scenarios of the change setup-five-steps-and-admin-tour and runs in the
 * nightly against a live instance.
 *
 * Ten tests are EXPECTED RED until the product is fixed: they were
 * `test.fixme`, which gate-19 does not count, and now run. The comment on each
 * names the code that disagrees with its scenario. This file is not serial, so
 * a red test does not skip the ones after it; the fresh-instance ones sit
 * before Finish, because only a fresh instance can show them.
 *   - Step 1 on open, and the fresh state: SetupWizardModal.vue loadState()
 *     jumps to `currentRecommendedStep`, and SetupWizardService
 *     computeStepStatuses() reports steps 3 and 4 `skipped` from the start,
 *     so the recommendation is 2 and "Run setup wizard" opens at Step 2.
 *   - Step 3 demo checkboxes (four scenarios): AdminDemoData.vue renders each
 *     showcase as a card with its own Install/Uninstall button; nothing is
 *     checked and nothing installs on the wizard's Next.
 *   - Step 4 group selector (three scenarios): SetupWizardModal.vue renders a
 *     "not yet available" note (data-test="admin-roles-stub") and no selector,
 *     although POST /api/admin/roles exists.
 * The demo tests take real showcases from GET /api/admin/demo-showcases: the
 * scenarios' "Engineering Demo" and "Sales Demo" are examples, and no shipped
 * showcase carries those names.
 *
 * Gate-19 @e2e traceability:
 *   @e2e setup-wizard::fresh-instance-shows-banner
 *   @e2e setup-wizard::completed-instance-hides-banner
 *   @e2e setup-wizard::banner-includes-descriptive-text
 *   @e2e setup-wizard::banner-has-explicit-run-setup-wizard-call-to-action
 *   @e2e setup-wizard::admin-can-dismiss-and-re-access-banner
 *   @e2e setup-wizard::wizard-starts-at-step-1
 *   @e2e setup-wizard::next-button-advances-to-next-step
 *   @e2e setup-wizard::back-button-returns-to-previous-step
 *   @e2e setup-wizard::skip-button-jumps-to-next-step-without-commitment
 *   @e2e setup-wizard::step-1-has-no-form-only-explanatory-text
 *   @e2e setup-wizard::step-5-shows-summary-and-finish-button
 *   @e2e setup-wizard::no-footer-step
 *   @e2e setup-wizard::finish-button-completes-wizard
 *   @e2e setup-wizard::wizard-progress-persists-across-back-navigation
 *   @e2e setup-wizard::step-2-embeds-existing-group-priority-order-admin-component
 *   @e2e setup-wizard::group-order-choice-persists-immediately
 *   @e2e setup-wizard::step-2-allows-drag-and-drop-reordering-existing-ui-behavior
 *   @e2e setup-wizard::step-3-embeds-demo-data-showcases-admin-component
 *   @e2e setup-wizard::demo-data-installation-is-optional
 *   @e2e setup-wizard::checked-demos-are-installed-on-next
 *   @e2e setup-wizard::demo-selection-is-persisted
 *   @e2e setup-wizard::step-4-shows-group-selector-for-admin-role
 *   @e2e setup-wizard::admin-can-select-one-group-for-dashboard-admin-role
 *   @e2e setup-wizard::step-4-is-skippable
 *   @e2e setup-wizard::no-group-selected-is-equivalent-to-skip
 *   @e2e setup-wizard::only-one-group-can-have-admin-role-per-wizard-run
 *   @e2e setup-wizard::endpoint-returns-complete-state-on-fresh-instance
 *   @e2e setup-wizard::endpoint-returns-recommended-step-for-re-runs
 *   @e2e setup-wizard::endpoint-returns-completetrue-when-wizard-finished
 *   @e2e setup-wizard::non-admin-requests-receive-403
 *   @e2e setup-wizard::endpoint-includes-skipped-step-status
 *   @e2e setup-wizard::endpoint-sets-complete-flag
 *   @e2e setup-wizard::endpoint-is-idempotent
 *   @e2e setup-wizard::completion-returns-full-state
 *   @e2e setup-wizard::admin-can-run-wizard-again-after-completion
 *   @e2e setup-wizard::re-running-does-not-re-install-demo-packages
 *   @e2e setup-wizard::re-running-preserves-skipped-steps
 *   @e2e setup-wizard::step-1-is-always-done-on-re-run
 *   @e2e setup-wizard::api-endpoint-reports-re-runnable-state
 *
 * @spec openspec/specs/setup-wizard/spec.md
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import {
	adminContext,
	API,
	contextFor,
	createGroup,
	removeGroup,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const ADMIN_PAGE = '/index.php/apps/launchpad/admin'
const WIZARD_API = `${API}/admin/setup-wizard`
const GROUPS_API = `${API}/admin/groups`
const SHOWCASES_API = `${API}/admin/demo-showcases`
const ROLES_API = `${API}/admin/roles`
const FOOTER_API = `${API}/admin/footer-settings`
const RUN = Date.now()
const GROUP_A = `e2e-wizard-a-${RUN}`
const GROUP_B = `e2e-wizard-b-${RUN}`
const TOTAL = 5
const STEP_STATUSES = ['done', 'pending', 'skipped']

const NOT_FRESH =
	'setup_wizard_complete is already true here and no API resets it '
	+ '(SetupWizardService::markWizardComplete only writes true); this test '
	+ 'runs on a fresh instance such as the CI fixture'

const STEP_HEADINGS: Record<number, string> = {
	1: 'Welcome',
	2: 'Group priority order',
	3: 'Demo data',
	4: 'Admin roles',
	5: 'All set',
}

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
let fresh = false

/**
 * Read the wizard state as the administrator, asserting the 200.
 *
 * @return the state payload.
 */
async function readState(): Promise<WizardState> {
	const res = await admin.get(`${WIZARD_API}/state`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Write the group priority order as the administrator.
 *
 * @param groups group ids in priority order.
 * @return nothing.
 */
async function setGroupOrder(groups: string[]): Promise<void> {
	const res = await admin.post(GROUPS_API, { data: { groups } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The stored group priority order, re-read from the API.
 *
 * @return the active group ids in order.
 */
async function readGroupOrder(): Promise<string[]> {
	const res = await admin.get(GROUPS_API)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).active
}

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
 * The wizard modal.
 *
 * @param page the admin page.
 * @return its root.
 */
function wizard(page: Page): Locator {
	return page.locator('[data-test="setup-wizard"]')
}

/**
 * Open the admin section on its General tab and wait for the wizard gate.
 *
 * @param page the admin page.
 * @return nothing.
 */
async function openAdminPage(page: Page): Promise<void> {
	await page.goto(ADMIN_PAGE)
	const general = adminTab(page, 'general')
	await expect(general).toBeVisible({ timeout: 30_000 })
	await general.click()
	await expect(
		page
			.locator(
				'[data-test="setup-wizard-banner"], [data-test="setup-wizard-rerun"]',
			)
			.first(),
	).toBeVisible({ timeout: 30_000 })
}

/**
 * Open the wizard from the admin section through the button a person clicks.
 *
 * @param page the admin page.
 * @param via `banner` for "Run setup wizard", `rerun` for "Run setup wizard again".
 * @return the wizard root.
 */
async function openWizard(page: Page, via: 'banner' | 'rerun'): Promise<Locator> {
	await openAdminPage(page)
	const button =
		via === 'banner'
			? page.locator('[data-test="setup-wizard-open"]')
			: page.locator('[data-test="setup-wizard-rerun-open"]')
	// The modal reads the wizard state on mount and may move to another step
	// once it answers; wait for that read so the step is settled.
	const stateRead = page.waitForResponse(
		(r) =>
			r.request().method() === 'GET'
			&& new URL(r.url()).pathname.endsWith('/api/admin/setup-wizard/state'),
	)
	await button.click()
	await stateRead
	await page.waitForTimeout(300)
	const root = wizard(page)
	await expect(root).toBeVisible({ timeout: 20_000 })
	await expect(root.locator('[data-test="setup-wizard-counter"]')).toHaveText(
		/Step \d \/ 5/,
	)
	return root
}

/**
 * The step number the counter currently shows.
 *
 * @param root the wizard root.
 * @return the step.
 */
async function currentStep(root: Locator): Promise<number> {
	const text = await root.locator('[data-test="setup-wizard-counter"]').innerText()
	const match = /Step (\d) \/ (\d)/.exec(text)
	expect(match, `counter text "${text}"`).not.toBeNull()
	return Number(match![1])
}

/**
 * Assert the wizard shows the given step: counter and step heading.
 *
 * @param root the wizard root.
 * @param step the step number.
 * @return nothing.
 */
async function expectStep(root: Locator, step: number): Promise<void> {
	await expect(root.locator('[data-test="setup-wizard-counter"]')).toHaveText(
		`Step ${step} / ${TOTAL}`,
	)
	await expect(root.locator('.setup-wizard__step > h3')).toHaveText(
		STEP_HEADINGS[step],
	)
}

/**
 * Click Next until the wizard shows `step`.
 *
 * @param root the wizard root.
 * @param step the target step.
 * @return nothing.
 */
async function goToStep(root: Locator, step: number): Promise<void> {
	let at = await currentStep(root)
	expect(at, 'the wizard is already past the target step').toBeLessThanOrEqual(
		step,
	)
	while (at < step) {
		await root.locator('[data-test="setup-wizard-next"]').click()
		at += 1
		await expectStep(root, at)
	}
}

/**
 * Record every non-GET request to the LaunchPad API while `action` runs.
 *
 * @param page the page.
 * @param action what to do.
 * @return the writes seen, as "METHOD path".
 */
async function writesDuring(
	page: Page,
	action: () => Promise<void>,
): Promise<string[]> {
	const seen: string[] = []
	const onRequest = (req: Request) => {
		if (req.method() !== 'GET' && req.url().includes('/apps/launchpad/api/')) {
			seen.push(`${req.method()} ${new URL(req.url()).pathname}`)
		}
	}
	page.on('request', onRequest)
	try {
		await action()
		// The group list debounces its save by 300ms; give any stray write time to leave.
		await page.waitForTimeout(800)
	} finally {
		page.off('request', onRequest)
	}
	return seen
}

/**
 * The ids in a group-priority column, in rendered order.
 *
 * @param root the wizard root.
 * @param column `active` or `inactive`.
 * @return the group ids.
 */
async function columnIds(
	root: Locator,
	column: 'active' | 'inactive',
): Promise<string[]> {
	return root
		.locator(`[data-test="group-priority-${column}"] li[data-test-id]`)
		.evaluateAll((items) =>
			items.map((li) => li.getAttribute('data-test-id') ?? ''),
		)
}

/**
 * Wait for the debounced group-order save the component sends.
 *
 * @param page the page.
 * @return the promise of the 200 response.
 */
function groupSave(page: Page) {
	return page.waitForResponse(
		(r) =>
			r.request().method() === 'POST'
			&& new URL(r.url()).pathname.endsWith('/api/admin/groups'),
	)
}

/**
 * Wait for the completion call the Finish button sends.
 *
 * @param page the page.
 * @return the promise of the response.
 */
function completeCall(page: Page) {
	return page.waitForResponse(
		(r) =>
			r.request().method() === 'POST'
			&& new URL(r.url()).pathname.endsWith(
				'/api/admin/setup-wizard/complete',
			),
	)
}

/**
 * Assert a payload has exactly the wizard-state shape (REQ-WIZ-008).
 *
 * @param state the payload.
 * @return nothing.
 */
function expectStateShape(state: WizardState): void {
	expect(Object.keys(state).sort()).toEqual([
		'complete',
		'currentRecommendedStep',
		'stepStatuses',
	])
	expect(typeof state.complete).toBe('boolean')
	expect(Number.isInteger(state.currentRecommendedStep)).toBe(true)
	expect(state.currentRecommendedStep).toBeGreaterThanOrEqual(1)
	expect(state.currentRecommendedStep).toBeLessThanOrEqual(TOTAL)
	expect(Object.keys(state.stepStatuses).sort()).toEqual([
		'1',
		'2',
		'3',
		'4',
		'5',
	])
	for (const status of Object.values(state.stepStatuses)) {
		expect(STEP_STATUSES).toContain(status)
	}
}

/**
 * Uninstall every showcase that was not installed in `before`.
 *
 * @param before the showcase list taken before the test.
 * @return nothing.
 */
async function uninstallNewShowcases(before: Showcase[]): Promise<void> {
	const wasInstalled = new Set(
		before.filter((s) => s.isInstalled).map((s) => s.id),
	)
	for (const showcase of await readJson<Showcase[]>(SHOWCASES_API)) {
		if (showcase.isInstalled && !wasInstalled.has(showcase.id)) {
			await admin.delete(`${SHOWCASES_API}/${encodeURIComponent(showcase.id)}`)
		}
	}
}

/**
 * Delete every role row not present in `before`.
 *
 * @param before the role rows taken before the test.
 * @return nothing.
 */
async function removeNewRoles(before: RoleRow[]): Promise<void> {
	const known = new Set(before.map((r) => r.id))
	for (const row of await readJson<RoleRow[]>(ROLES_API)) {
		if (!known.has(row.id)) {
			await admin.delete(`${ROLES_API}/${row.id}`)
		}
	}
}

test.beforeAll(async () => {
	admin = await adminContext()
	fresh = (await readState()).complete === false
})

test.afterAll(async () => {
	await admin?.dispose()
})

test.describe('setup-wizard — fresh instance (complete = false)', () => {
	test('fresh instance shows the banner until Finish', async ({ page }) => {
		// @e2e setup-wizard::fresh-instance-shows-banner
		test.skip(!fresh, NOT_FRESH)

		await openAdminPage(page)
		const banner = page.locator('[data-test="setup-wizard-banner"]')
		await expect(banner).toBeVisible()
		await expect(banner.locator('strong')).toHaveText('Run setup wizard')
		const open = banner.locator('[data-test="setup-wizard-open"]')
		await expect(open).toHaveText('Run setup wizard')
		await expect(page.locator('[data-test="setup-wizard-rerun"]')).toHaveCount(0)

		// The button leads to the wizard flow.
		await open.click()
		await expect(wizard(page)).toBeVisible()

		// Leaving the wizard without Finish keeps the banner, also after a reload.
		await page.keyboard.press('Escape')
		await expect(wizard(page)).toBeHidden()
		await expect(banner).toBeVisible()
		await openAdminPage(page)
		await expect(page.locator('[data-test="setup-wizard-banner"]')).toBeVisible()
		expect((await readState()).complete).toBe(false)
	})

	test('the banner explains what the wizard does', async ({ page }) => {
		// @e2e setup-wizard::banner-includes-descriptive-text
		test.skip(!fresh, NOT_FRESH)

		await openAdminPage(page)
		await expect(page.locator('[data-test="setup-wizard-banner"] p')).toHaveText(
			'Get your intranet started: configure groups, install demo data, and set up admin roles.',
		)
	})

	// EXPECTED RED: src/modals/SetupWizardModal.vue loadState() jumps to
	// `currentRecommendedStep` whenever `complete` is false, and on a fresh
	// instance that is never 1 (SetupWizardService::resolveRecommendedStep()
	// returns the first non-done step, and step 2 is pending). So "Run setup
	// wizard" opens at Step 2, not at Step 1 (Welcome).
	test('the banner call-to-action opens the wizard at Step 1', async ({
		page,
	}) => {
		// @e2e setup-wizard::banner-has-explicit-run-setup-wizard-call-to-action
		test.skip(!fresh, NOT_FRESH)

		const root = await openWizard(page, 'banner')
		await expectStep(root, 1)
	})

	// EXPECTED RED: same cause as above, SetupWizardModal.vue loadState().
	test('the wizard starts at Step 1 with counter 1 / 5', async ({
		page,
	}) => {
		// @e2e setup-wizard::wizard-starts-at-step-1
		test.skip(!fresh, NOT_FRESH)

		const root = await openWizard(page, 'banner')
		await expect(root.locator('[data-test="setup-wizard-counter"]')).toHaveText(
			'Step 1 / 5',
		)
		await expect(root.locator('.setup-wizard__step > h3')).toHaveText('Welcome')
	})

	test('a dismissed banner comes back while the wizard is not finished', async ({
		page,
	}) => {
		// @e2e setup-wizard::admin-can-dismiss-and-re-access-banner
		test.skip(!fresh, NOT_FRESH)

		await openWizard(page, 'banner')
		await page.keyboard.press('Escape')
		await expect(wizard(page)).toBeHidden()

		// Navigate away, then come back.
		await page.goto('/index.php/apps/launchpad')
		await expect(page.locator('#header, header.header').first()).toBeVisible()
		await openAdminPage(page)
		await expect(page.locator('[data-test="setup-wizard-banner"]')).toBeVisible()
		expect((await readState()).complete).toBe(false)
	})

	// EXPECTED RED: SetupWizardService::computeStepStatuses() reports steps
	// 3 and 4 as "skipped" from the start (not "pending"), and
	// resolveRecommendedStep() therefore answers 2, not 1. The scenario's
	// example still lists a sixth step; the wizard has five (REQ-WIZ-012), so
	// this asserts five, all but Step 1 pending.
	test('the state endpoint on a fresh instance', async () => {
		// @e2e setup-wizard::endpoint-returns-complete-state-on-fresh-instance
		test.skip(!fresh, NOT_FRESH)

		const res = await admin.get(`${WIZARD_API}/state`)
		expect(res.status()).toBe(200)
		expect(await res.json()).toEqual({
			complete: false,
			currentRecommendedStep: 1,
			stepStatuses: {
				1: 'done',
				2: 'pending',
				3: 'pending',
				4: 'pending',
				5: 'pending',
			},
		})
	})

	test('Finish completes the wizard, and a non-admin cannot', async ({ page }) => {
		// @e2e setup-wizard::finish-button-completes-wizard
		// @e2e setup-wizard::endpoint-sets-complete-flag
		// @e2e setup-wizard::non-admin-requests-receive-403
		test.skip(!fresh, NOT_FRESH)

		const probe = await provisionThrowawayUser('e2e-wizard-probe')
		const probeApi = await contextFor(probe.username, probe.password)
		try {
			const root = await openWizard(page, 'banner')
			await goToStep(root, TOTAL)
			const finish = root.locator('[data-test="setup-wizard-next"]')
			await expect(finish).toHaveText('Finish')
			// The banner is still there behind the modal until Finish.
			await expect(
				page.locator('[data-test="setup-wizard-banner"]'),
			).toBeAttached()

			// The refusal, while the flag is still false: 403 and nothing written.
			const refused = await probeApi.post(`${WIZARD_API}/complete`)
			expect(refused.status()).toBe(403)
			expect((await readState()).complete).toBe(false)

			// The admin, through the product's own button.
			const response = completeCall(page)
			await finish.click()
			const done = await response
			expect(done.status()).toBe(200)
			const body: WizardState = await done.json()
			expectStateShape(body)
			expect(body.complete).toBe(true)
			expect(body.stepStatuses['5']).toBe('done')

			// Re-read: the flag is stored, and the GET agrees with the POST body.
			const reread = await readState()
			expect(reread.complete).toBe(true)
			expect(reread).toEqual(body)

			// The modal closes and the admin section reloads without the banner.
			await expect(wizard(page)).toBeHidden()
			await expect(
				page.locator('[data-test="setup-wizard-banner"]'),
			).toHaveCount(0)
			await expect(
				page.locator('[data-test="setup-wizard-rerun"]'),
			).toBeVisible()
			await openAdminPage(page)
			await expect(
				page.locator('[data-test="setup-wizard-banner"]'),
			).toHaveCount(0)
		} finally {
			await probeApi.dispose()
			await deprovisionUser(probe.username)
		}
	})
})

test.describe('setup-wizard — completed instance and re-runs', () => {
	let originalOrder: string[] = []

	test.beforeAll(async () => {
		// The terminal state; on a fresh run the Finish test above already got here.
		const res = await admin.post(`${WIZARD_API}/complete`)
		expect(res.status(), await res.text()).toBe(200)
		originalOrder = await readGroupOrder()
		await createGroup(admin, GROUP_A)
		await createGroup(admin, GROUP_B)
	})

	test.afterAll(async () => {
		await setGroupOrder(originalOrder)
		await removeGroup(admin, GROUP_A)
		await removeGroup(admin, GROUP_B)
	})

	test('a completed instance hides the banner and loads normally', async ({
		page,
	}) => {
		// @e2e setup-wizard::completed-instance-hides-banner
		expect((await readState()).complete).toBe(true)

		await openAdminPage(page)
		await expect(page.locator('[data-test="setup-wizard-banner"]')).toHaveCount(
			0,
		)
		await expect(adminTab(page, 'general')).toHaveAttribute(
			'aria-selected',
			'true',
		)
		await expect(adminTab(page, 'templates')).toBeVisible()
		await expect(adminTab(page, 'operations')).toBeVisible()
	})

	test('the state endpoint reports complete=true', async () => {
		// @e2e setup-wizard::endpoint-returns-completetrue-when-wizard-finished
		const state = await readState()
		expectStateShape(state)
		expect(state.complete).toBe(true)
	})

	test('a completed instance offers "Run setup wizard again"', async ({
		page,
	}) => {
		// @e2e setup-wizard::api-endpoint-reports-re-runnable-state
		expect((await readState()).complete).toBe(true)

		await openAdminPage(page)
		await expect(page.locator('[data-test="setup-wizard-banner"]')).toHaveCount(
			0,
		)
		const rerun = page.locator('[data-test="setup-wizard-rerun-open"]')
		await expect(rerun).toHaveText('Run setup wizard again')
		await expect(page.locator('[data-test="setup-wizard-open"]')).toHaveCount(0)
	})

	test('the complete endpoint is idempotent', async () => {
		// @e2e setup-wizard::endpoint-is-idempotent
		const first = await admin.post(`${WIZARD_API}/complete`)
		expect(first.status()).toBe(200)
		const second = await admin.post(`${WIZARD_API}/complete`)
		expect(second.status()).toBe(200)
		const a: WizardState = await first.json()
		const b: WizardState = await second.json()
		expect(a.complete).toBe(true)
		expect(b.complete).toBe(true)
		expect(b).toEqual(a)
		expect(await readState()).toEqual(b)
	})

	test('the complete endpoint returns the full state', async () => {
		// @e2e setup-wizard::completion-returns-full-state
		const res = await admin.post(`${WIZARD_API}/complete`)
		expect(res.status()).toBe(200)
		const body: WizardState = await res.json()
		expectStateShape(body)
		expect(body).toEqual(await readState())
	})

	test('a non-admin gets 403 from both wizard endpoints', async () => {
		// @e2e setup-wizard::non-admin-requests-receive-403
		const probe = await provisionThrowawayUser('e2e-wizard-probe')
		const probeApi = await contextFor(probe.username, probe.password)
		try {
			const before = await readState()

			const get = await probeApi.get(`${WIZARD_API}/state`)
			expect(get.status()).toBe(403)
			const post = await probeApi.post(`${WIZARD_API}/complete`)
			expect(post.status()).toBe(403)

			// Nothing changed, and the administrator is allowed both.
			expect(await readState()).toEqual(before)
			const allowed = await admin.post(`${WIZARD_API}/complete`)
			expect(allowed.status()).toBe(200)
			expect((await allowed.json()).complete).toBe(true)
		} finally {
			await probeApi.dispose()
			await deprovisionUser(probe.username)
		}
	})

	test('the recommended step is 2 while no group order is set', async () => {
		// @e2e setup-wizard::endpoint-returns-recommended-step-for-re-runs
		await setGroupOrder([])
		expect(await readGroupOrder()).toEqual([])

		const state = await readState()
		expect(state.stepStatuses['2']).toBe('pending')
		expect(state.currentRecommendedStep).toBe(2)

		// And it moves on once the order is set.
		await setGroupOrder([GROUP_A])
		expect((await readState()).stepStatuses['2']).toBe('done')
	})

	test('the wizard re-runs after completion and shows current settings', async ({
		page,
	}) => {
		// @e2e setup-wizard::admin-can-run-wizard-again-after-completion
		await setGroupOrder([GROUP_A])
		expect((await readState()).complete).toBe(true)

		const root = await openWizard(page, 'rerun')
		await expectStep(root, 1)

		// Step 2 shows the stored order.
		await goToStep(root, 2)
		await expect(
			root.locator(
				`[data-test="group-priority-active"] li[data-test-id="${GROUP_A}"]`,
			),
		).toBeVisible()
		await expect(
			root.locator(
				`[data-test="group-priority-inactive"] li[data-test-id="${GROUP_B}"]`,
			),
		).toBeVisible()
		expect(await columnIds(root, 'active')).toEqual([GROUP_A])

		// The admin can change it.
		const saved = groupSave(page)
		await root
			.locator(
				`[data-test="group-priority-inactive"] li[data-test-id="${GROUP_B}"]`,
			)
			.getByRole('button', { name: 'Move to active' })
			.click()
		expect((await saved).status()).toBe(200)
		expect(await readGroupOrder()).toEqual([GROUP_A, GROUP_B])

		// Step 3 shows the stored demo-data state.
		await goToStep(root, 3)
		for (const showcase of await readJson<Showcase[]>(SHOWCASES_API)) {
			const card = root.locator(`[data-test="showcase-card-${showcase.id}"]`)
			await expect(card).toBeVisible()
			const action = showcase.isInstalled ? 'uninstall' : 'install'
			await expect(
				card.locator(`[data-test="showcase-${action}-${showcase.id}"]`),
			).toBeVisible()
		}
	})

	test('Step 1 is done on a re-run and Next moves straight on', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-1-is-always-done-on-re-run
		expect((await readState()).stepStatuses['1']).toBe('done')

		const root = await openWizard(page, 'rerun')
		await expectStep(root, 1)
		await root.locator('[data-test="setup-wizard-next"]').click()
		await expectStep(root, 2)
	})

	test('Step 1 is explanatory text only and Next persists nothing', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-1-has-no-form-only-explanatory-text
		const root = await openWizard(page, 'rerun')
		await expectStep(root, 1)
		await expect(root.locator('.setup-wizard__step > p')).toHaveText(
			'This setup sets the groups, the example dashboards and the roles, in five steps. Existing dashboards stay as they are.',
		)
		await expect(
			root.locator(
				'.setup-wizard__body input, .setup-wizard__body select, '
					+ '.setup-wizard__body textarea, .setup-wizard__body [contenteditable="true"]',
			),
		).toHaveCount(0)

		const settingsBefore = await readJson<unknown>(`${API}/admin/settings`)
		const stateBefore = await readState()
		const orderBefore = await readGroupOrder()
		const writes = await writesDuring(page, async () => {
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 2)
		})
		expect(writes).toEqual([])
		expect(await readJson<unknown>(`${API}/admin/settings`)).toEqual(
			settingsBefore,
		)
		expect(await readState()).toEqual(stateBefore)
		expect(await readGroupOrder()).toEqual(orderBefore)
	})

	test('Next advances from Step 1 to Step 2 and enables Back', async ({
		page,
	}) => {
		// @e2e setup-wizard::next-button-advances-to-next-step
		const root = await openWizard(page, 'rerun')
		await expectStep(root, 1)
		const back = root.locator('[data-test="setup-wizard-back"]')
		await expect(back).toBeDisabled()

		await root.locator('[data-test="setup-wizard-next"]').click()
		await expectStep(root, 2)
		await expect(back).toBeEnabled()
	})

	test('Step 2 embeds the group priority order component, working', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-2-embeds-existing-group-priority-order-admin-component
		await setGroupOrder([GROUP_A])

		const root = await openWizard(page, 'rerun')
		await goToStep(root, 2)
		const component = root.locator('.group-priority')
		await expect(component).toBeVisible()
		await expect(component.locator('.group-priority__header h3')).toHaveText(
			'Group priority order',
		)
		await expect(
			root.locator('[data-test="group-priority-active"]'),
		).toBeVisible()
		await expect(
			root.locator('[data-test="group-priority-inactive"]'),
		).toBeVisible()

		const saved = groupSave(page)
		await root
			.locator(
				`[data-test="group-priority-inactive"] li[data-test-id="${GROUP_B}"]`,
			)
			.getByRole('button', { name: 'Move to active' })
			.click()
		expect((await saved).status()).toBe(200)
		expect(await columnIds(root, 'active')).toEqual([GROUP_A, GROUP_B])
		expect(await readGroupOrder()).toEqual([GROUP_A, GROUP_B])
	})

	test('a reordered group order is stored and survives navigating away', async ({
		page,
	}) => {
		// @e2e setup-wizard::group-order-choice-persists-immediately
		await setGroupOrder([GROUP_A, GROUP_B])

		const root = await openWizard(page, 'rerun')
		await goToStep(root, 2)
		const active = root.locator('[data-test="group-priority-active"]')
		expect(await columnIds(root, 'active')).toEqual([GROUP_A, GROUP_B])

		// Move B above A by dragging it onto A.
		const saved = groupSave(page)
		await active
			.locator(`li[data-test-id="${GROUP_B}"]`)
			.dragTo(active.locator(`li[data-test-id="${GROUP_A}"]`))
		expect((await saved).status()).toBe(200)
		await root.locator('[data-test="setup-wizard-next"]').click()
		await expectStep(root, 3)
		expect(await readGroupOrder()).toEqual([GROUP_B, GROUP_A])

		// Navigate away and back: still there.
		await page.goto('/index.php/apps/launchpad')
		const again = await openWizard(page, 'rerun')
		await goToStep(again, 2)
		await expect(
			again
				.locator('[data-test="group-priority-active"] li[data-test-id]')
				.first(),
		).toHaveAttribute('data-test-id', GROUP_B)
		expect(await columnIds(again, 'active')).toEqual([GROUP_B, GROUP_A])
		expect(await readGroupOrder()).toEqual([GROUP_B, GROUP_A])
	})

	test('a group dragged into the active column shows there', async ({ page }) => {
		// @e2e setup-wizard::step-2-allows-drag-and-drop-reordering-existing-ui-behavior
		await setGroupOrder([GROUP_A])

		const root = await openWizard(page, 'rerun')
		await goToStep(root, 2)
		const inactiveB = root.locator(
			`[data-test="group-priority-inactive"] li[data-test-id="${GROUP_B}"]`,
		)
		await expect(inactiveB).toBeVisible()
		// Narrow the inactive column to B, as a person looking for one group
		// among many does: with every leftover group listed, B sits below
		// the fold and the drag starts after the dialog scrolled away from it.
		await root.getByLabel('Filter inactive groups').fill(GROUP_B)
		await expect(
			root.locator('[data-test="group-priority-inactive"] li[data-test-id]'),
		).toHaveCount(1)

		const saved = groupSave(page)
		await inactiveB.dragTo(root.locator('[data-test="group-priority-active"]'))
		expect((await saved).status()).toBe(200)
		await expect(
			root.locator(
				`[data-test="group-priority-active"] li[data-test-id="${GROUP_B}"]`,
			),
		).toBeVisible()
		await expect(inactiveB).toHaveCount(0)
		expect(await columnIds(root, 'active')).toContain(GROUP_B)
	})

	test('the Step 2 choice is still there after Back from Step 3', async ({
		page,
	}) => {
		// @e2e setup-wizard::wizard-progress-persists-across-back-navigation
		await setGroupOrder([GROUP_A])

		const root = await openWizard(page, 'rerun')
		await goToStep(root, 2)
		const saved = groupSave(page)
		await root
			.locator(
				`[data-test="group-priority-inactive"] li[data-test-id="${GROUP_B}"]`,
			)
			.getByRole('button', { name: 'Move to active' })
			.click()
		expect((await saved).status()).toBe(200)

		await root.locator('[data-test="setup-wizard-next"]').click()
		await expectStep(root, 3)
		await root.locator('[data-test="setup-wizard-back"]').click()
		await expectStep(root, 2)
		await expect(
			root.locator(
				`[data-test="group-priority-active"] li[data-test-id="${GROUP_B}"]`,
			),
		).toBeVisible()
		expect(await columnIds(root, 'active')).toEqual([GROUP_A, GROUP_B])
	})

	test('Back from Step 3 returns to Step 2 and loses nothing', async ({
		page,
	}) => {
		// @e2e setup-wizard::back-button-returns-to-previous-step
		const showcasesBefore = await readJson<Showcase[]>(SHOWCASES_API)
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 3)
		const panel = root.locator('[data-test="demo-data-panel"]')
		await expect(panel).toBeVisible()
		const cardsBefore = await panel
			.locator('[data-test^="showcase-card-"]')
			.evaluateAll((cards) => cards.map((c) => c.textContent?.trim() ?? ''))

		const writes = await writesDuring(page, async () => {
			await root.locator('[data-test="setup-wizard-back"]').click()
			await expectStep(root, 2)
		})
		expect(writes).toEqual([])

		await root.locator('[data-test="setup-wizard-next"]').click()
		await expectStep(root, 3)
		await expect(panel.locator('[data-test^="showcase-card-"]')).toHaveCount(
			cardsBefore.length,
		)
		expect(
			await panel
				.locator('[data-test^="showcase-card-"]')
				.evaluateAll((cards) =>
					cards.map((c) => c.textContent?.trim() ?? ''),
				),
		).toEqual(cardsBefore)
		expect(await readJson<Showcase[]>(SHOWCASES_API)).toEqual(showcasesBefore)
	})

	// EXPECTED RED (spec vs product): src/components/admin/AdminDemoData.vue
	// renders each showcase as a card with an Install/Uninstall button; there is
	// no checkbox per package.
	test('Step 3 embeds the demo showcases with a checkbox each', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-3-embeds-demo-data-showcases-admin-component
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 3)
		await expect(root.locator('[data-test="demo-data-panel"]')).toBeVisible()
		const showcases = await readJson<Showcase[]>(SHOWCASES_API)
		expect(showcases.length).toBeGreaterThan(0)
		for (const showcase of showcases) {
			const card = root.locator(`[data-test="showcase-card-${showcase.id}"]`)
			await expect(card).toBeVisible()
			await expect(card.getByRole('checkbox')).toHaveCount(1)
		}
	})

	test('Step 3 can be passed with nothing installed', async ({ page }) => {
		// @e2e setup-wizard::demo-data-installation-is-optional
		const before = await readJson<Showcase[]>(SHOWCASES_API)
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 3)

		const writes = await writesDuring(page, async () => {
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 4)
		})
		expect(writes.filter((w) => w.includes('/demo-showcases'))).toEqual([])
		expect(await readJson<Showcase[]>(SHOWCASES_API)).toEqual(before)
	})

	/**
	 * Two showcases not installed yet, for the demo-checkbox scenarios. The
	 * scenarios' "Engineering Demo" and "Sales Demo" are examples; any two
	 * shipped showcases stand in for them.
	 *
	 * @param showcases the current showcase list.
	 * @return the first two that are not installed.
	 */
	function twoUninstalled(showcases: Showcase[]): Showcase[] {
		const free = showcases.filter((s) => !s.isInstalled)
		expect(
			free.length,
			'two showcases that are not installed yet',
		).toBeGreaterThanOrEqual(2)
		return free.slice(0, 2)
	}

	/**
	 * The checkbox on a showcase's card in Step 3.
	 *
	 * @param root the wizard root.
	 * @param showcase the showcase.
	 * @return its checkbox.
	 */
	function demoCheckbox(root: Locator, showcase: Showcase): Locator {
		return root
			.locator(`[data-test="showcase-card-${showcase.id}"]`)
			.getByRole('checkbox')
	}

	// EXPECTED RED (spec vs product): AdminDemoData.vue has no checkbox per
	// showcase; it installs one showcase immediately from its own button, not
	// on the wizard's Next.
	test('checked demos are installed on Next', async ({ page }) => {
		// @e2e setup-wizard::checked-demos-are-installed-on-next
		const before = await readJson<Showcase[]>(SHOWCASES_API)
		const picked = twoUninstalled(before)
		try {
			const root = await openWizard(page, 'rerun')
			await goToStep(root, 3)
			for (const showcase of picked) {
				await demoCheckbox(root, showcase).check()
			}
			// Checking alone installs nothing; Next does.
			expect(
				(await readJson<Showcase[]>(SHOWCASES_API))
					.filter((s) => s.isInstalled)
					.map((s) => s.id),
			).toEqual(before.filter((s) => s.isInstalled).map((s) => s.id))

			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 4)
			await expect
				.poll(
					async () =>
						(await readJson<Showcase[]>(SHOWCASES_API))
							.filter((s) => picked.some((p) => p.id === s.id))
							.every((s) => s.isInstalled),
					{ timeout: 60_000 },
				)
				.toBe(true)
		} finally {
			await uninstallNewShowcases(before)
		}
	})

	// EXPECTED RED (spec vs product): as above, no demo checkboxes to keep.
	test('the demo selection survives Back without a second install', async ({
		page,
	}) => {
		// @e2e setup-wizard::demo-selection-is-persisted
		const before = await readJson<Showcase[]>(SHOWCASES_API)
		const [first] = twoUninstalled(before)
		try {
			const root = await openWizard(page, 'rerun')
			await goToStep(root, 3)
			await demoCheckbox(root, first).check()
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 4)
			const writes = await writesDuring(page, async () => {
				await root.locator('[data-test="setup-wizard-back"]').click()
				await expectStep(root, 3)
			})
			await expect(demoCheckbox(root, first)).toBeChecked()
			expect(writes.filter((w) => w.endsWith('/install'))).toEqual([])
		} finally {
			await uninstallNewShowcases(before)
		}
	})

	// EXPECTED RED (spec vs product): no checkboxes in Step 3
	// (AdminDemoData.vue), so there is nothing to uncheck.
	test('a re-run does not re-install an installed demo', async ({ page }) => {
		// @e2e setup-wizard::re-running-does-not-re-install-demo-packages
		const before = await readJson<Showcase[]>(SHOWCASES_API)
		const installed =
			before.find((s) => s.isInstalled) ?? twoUninstalled(before)[0]
		try {
			if (!installed.isInstalled) {
				const install = await admin.post(
					`${SHOWCASES_API}/${encodeURIComponent(installed.id)}/install`,
				)
				expect(install.status(), await install.text()).toBe(200)
			}

			const root = await openWizard(page, 'rerun')
			await goToStep(root, 3)
			await demoCheckbox(root, installed).uncheck()
			const writes = await writesDuring(page, async () => {
				await root.locator('[data-test="setup-wizard-next"]').click()
				await expectStep(root, 4)
			})
			expect(writes.filter((w) => w.includes('/demo-showcases'))).toEqual([])
			const after = await readJson<Showcase[]>(SHOWCASES_API)
			expect(after.find((s) => s.id === installed.id)?.isInstalled).toBe(true)
		} finally {
			await uninstallNewShowcases(before)
		}
	})

	// EXPECTED RED (spec vs product): Step 4 is a stub. SetupWizardModal.vue
	// renders a "not yet available" note (data-test="admin-roles-stub") and no
	// group selector, although the admin-roles endpoints exist (/api/admin/roles).
	test('Step 4 lists every group for the Dashboard Admin role', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-4-shows-group-selector-for-admin-role
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 4)
		const step = root.locator('.setup-wizard__step')
		await expect(step).toContainText(
			"Assign 'Dashboard Admin' role to a group to delegate LaunchPad administration to group members.",
		)
		const selector = step.getByRole('combobox')
		await expect(selector).toBeVisible()
		await selector.click()
		const groups = await admin.get('/ocs/v1.php/cloud/groups?format=json')
		const ids: string[] = (await groups.json()).ocs.data.groups
		for (const id of ids) {
			await expect(page.getByRole('option', { name: id })).toBeVisible()
		}
	})

	// EXPECTED RED (spec vs product): Step 4 stub, SetupWizardModal.vue.
	test('selecting one group on Step 4 gives it the admin role', async ({
		page,
	}) => {
		// @e2e setup-wizard::admin-can-select-one-group-for-dashboard-admin-role
		const before = await readJson<RoleRow[]>(ROLES_API)
		try {
			const root = await openWizard(page, 'rerun')
			await goToStep(root, 4)
			await root.locator('.setup-wizard__step').getByRole('combobox').click()
			await page.getByRole('option', { name: GROUP_A }).click()
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 5)
			const added = (await readJson<RoleRow[]>(ROLES_API)).filter(
				(r) => !before.some((b) => b.id === r.id),
			)
			expect(added).toHaveLength(1)
			expect(added[0]).toMatchObject({
				groupId: GROUP_A,
				role: 'admin',
				assignedBy: ADMIN_USER,
			})
		} finally {
			await removeNewRoles(before)
		}
	})

	// EXPECTED RED (spec vs product): Step 4 stub, SetupWizardModal.vue.
	test('one wizard run gives the admin role to one group only', async ({
		page,
	}) => {
		// @e2e setup-wizard::only-one-group-can-have-admin-role-per-wizard-run
		const before = await readJson<RoleRow[]>(ROLES_API)
		try {
			const root = await openWizard(page, 'rerun')
			await goToStep(root, 4)
			await root.locator('.setup-wizard__step').getByRole('combobox').click()
			await page.getByRole('option', { name: GROUP_A }).click()
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 5)
			const added = (await readJson<RoleRow[]>(ROLES_API)).filter(
				(r) => !before.some((b) => b.id === r.id),
			)
			expect(added.map((r) => r.groupId)).toEqual([GROUP_A])
		} finally {
			await removeNewRoles(before)
		}
	})

	test('Skip on Step 4 moves to Step 5, grants nothing, and reports skipped', async ({
		page,
	}) => {
		// @e2e setup-wizard::skip-button-jumps-to-next-step-without-commitment
		// @e2e setup-wizard::step-4-is-skippable
		// @e2e setup-wizard::endpoint-includes-skipped-step-status
		const rolesBefore = await readJson<RoleRow[]>(ROLES_API)
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 4)

		const writes = await writesDuring(page, async () => {
			await root.locator('[data-test="setup-wizard-skip"]').click()
			await expectStep(root, 5)
		})
		expect(writes).toEqual([])
		expect(await readJson<RoleRow[]>(ROLES_API)).toEqual(rolesBefore)
		expect((await readState()).stepStatuses['4']).toBe('skipped')
	})

	test('Next on Step 4 with no group selected is the same as Skip', async ({
		page,
	}) => {
		// @e2e setup-wizard::no-group-selected-is-equivalent-to-skip
		const rolesBefore = await readJson<RoleRow[]>(ROLES_API)
		const root = await openWizard(page, 'rerun')
		await goToStep(root, 4)

		const writes = await writesDuring(page, async () => {
			await root.locator('[data-test="setup-wizard-next"]').click()
			await expectStep(root, 5)
		})
		expect(writes.filter((w) => w.includes('/admin/roles'))).toEqual([])
		expect(await readJson<RoleRow[]>(ROLES_API)).toEqual(rolesBefore)
	})

	test('a re-run after skipping Step 4 offers and honours Skip again', async ({
		page,
	}) => {
		// @e2e setup-wizard::re-running-preserves-skipped-steps
		const rolesBefore = await readJson<RoleRow[]>(ROLES_API)

		// First run: skip Step 4, then Finish.
		let root = await openWizard(page, 'rerun')
		await goToStep(root, 4)
		await root.locator('[data-test="setup-wizard-skip"]').click()
		await expectStep(root, 5)
		await goToStep(root, TOTAL)
		const finished = completeCall(page)
		await root.locator('[data-test="setup-wizard-next"]').click()
		expect((await finished).status()).toBe(200)
		await expect(wizard(page)).toBeHidden()
		const firstRun = await readState()
		expect(firstRun.stepStatuses['4']).toBe('skipped')

		// Re-run: Step 4 still offers Skip, and Skip behaves the same.
		root = await openWizard(page, 'rerun')
		await goToStep(root, 4)
		const skip = root.locator('[data-test="setup-wizard-skip"]')
		await expect(skip).toBeVisible()
		await expect(skip).toHaveText('Skip')
		await skip.click()
		await expectStep(root, 5)
		expect((await readState()).stepStatuses['4']).toBe(
			firstRun.stepStatuses['4'],
		)
		expect(await readJson<RoleRow[]>(ROLES_API)).toEqual(rolesBefore)
	})

	test('the wizard walks five steps without a footer step and finishes', async ({
		page,
	}) => {
		// @e2e setup-wizard::no-footer-step
		const footerBefore = await readJson<unknown>(FOOTER_API)
		const state = await readState()
		expect(Object.keys(state.stepStatuses)).toHaveLength(5)

		const root = await openWizard(page, 'rerun')
		await expectStep(root, 1)
		const writes = await writesDuring(page, async () => {
			for (let step = 1; step <= TOTAL; step++) {
				await expectStep(root, step)
				await expect(root).not.toContainText('Footer configuration')
				await expect(root.locator('[data-test="footer-stub"]')).toHaveCount(0)
				if (step < TOTAL) {
					await expect(
						root.locator('[data-test="setup-wizard-next"]'),
					).toHaveText('Next')
					await root.locator('[data-test="setup-wizard-next"]').click()
				}
			}
		})
		expect(writes.filter((w) => w.includes('/footer'))).toEqual([])
		await expect(root.locator('[data-test="setup-wizard-next"]')).toHaveText(
			'Finish',
		)

		const finished = completeCall(page)
		await root.locator('[data-test="setup-wizard-next"]').click()
		const response = await finished
		expect(response.status()).toBe(200)
		const body: WizardState = await response.json()
		expectStateShape(body)
		expect(body.complete).toBe(true)
		await expect(wizard(page)).toBeHidden()
		expect(await readJson<unknown>(FOOTER_API)).toEqual(footerBefore)
	})

	test('Step 5 shows the summary and a Finish button that completes', async ({
		page,
	}) => {
		// @e2e setup-wizard::step-5-shows-summary-and-finish-button
		const root = await openWizard(page, 'rerun')
		await goToStep(root, TOTAL)
		await expect(root.locator('.setup-wizard__step > p')).toHaveText(
			'Your setup is complete. Click Finish to save changes and dismiss the setup banner.',
		)
		const primary = root.locator('[data-test="setup-wizard-next"]')
		await expect(primary).toHaveText('Finish')
		await expect(
			root
				.locator('.setup-wizard__footer')
				.getByRole('button', { name: 'Next', exact: true }),
		).toHaveCount(0)
		await expect(root.locator('[data-test="setup-wizard-skip"]')).toHaveCount(0)

		const finished = completeCall(page)
		await primary.click()
		const response = await finished
		expect(response.status()).toBe(200)
		expect((await response.json()).complete).toBe(true)
		await expect(wizard(page)).toBeHidden()
		expect((await readState()).complete).toBe(true)
	})
})
