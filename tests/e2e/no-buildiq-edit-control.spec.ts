/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Launchpad's page header holds only launchpad's own actions: no Buildiq
 * in-app edit button, also where Buildiq is enabled (REQ-SHELL-NOBQ).
 *
 * The GIVEN is established, not assumed. CnAppRoot offers the button when
 * `useBuildiqEditAvailability()` is true, which reads `OC.appswebroots` for
 * `buildiq` (or the legacy `openbuild`), and the manifest does not set
 * `openbuildEditable: false`. An e2e instance without Buildiq installed never
 * offers the button, whatever the manifest says, so a bare "no button" check
 * passes there on any build. An init script therefore adds `buildiq` to
 * `_oc_appswebroots`, as Nextcloud's inline config declares an enabled app
 * (the same technique as dashboard-talk-leaf.spec.ts uses for Talk), and the
 * test requires the page to see it before it looks for the button. With the
 * GIVEN in place, only the manifest's opt-out keeps the button away.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::buildiq-is-enabled
 *
 * @spec openspec/changes/no-buildiq-edit-control/specs/runtime-shell/spec.md
 */

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

/** The web root the stub gives Buildiq, as Nextcloud would for an app in apps-extra. */
const BUILDIQ_ROOT = '/apps-extra/buildiq'

// @e2e runtime-shell::buildiq-is-enabled
test('a launchpad dashboard shows no Buildiq edit button where Buildiq is enabled', async ({ page }) => {
	await page.addInitScript((root: string) => {
		let roots: unknown
		Object.defineProperty(window, '_oc_appswebroots', {
			configurable: true,
			get: () => roots,
			set: (value: unknown) => {
				roots = value !== null && typeof value === 'object'
					? { ...(value as Record<string, string>), buildiq: root }
					: value
			},
		})
	}, BUILDIQ_ROOT)

	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })

	// GIVEN: the page sees Buildiq as an enabled app, alongside launchpad itself.
	const seen = await page.evaluate(() => {
		const roots = (window as unknown as { OC?: { appswebroots?: Record<string, string> } }).OC?.appswebroots
		return { buildiq: roots?.buildiq ?? null, launchpad: Boolean(roots && Object.hasOwn(roots, 'launchpad')) }
	})
	expect(seen, 'the page must see buildiq and launchpad in OC.appswebroots').toEqual({
		buildiq: BUILDIQ_ROOT,
		launchpad: true,
	})

	// THEN: no Buildiq edit button in either of its two renderings.
	await expect(page.locator('[data-testid="cn-buildiq-edit-link"]')).toHaveCount(0)
	await expect(page.locator('.cn-buildiq-edit')).toHaveCount(0)
	await expect(page.getByRole('button', { name: /buildiq/i })).toHaveCount(0)
	await expect(page.getByRole('link', { name: /buildiq/i })).toHaveCount(0)
})
