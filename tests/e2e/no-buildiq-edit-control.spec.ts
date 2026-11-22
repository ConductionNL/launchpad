/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Launchpad's page header holds only launchpad's own actions: no Buildiq
 * in-app edit button, also where Buildiq is enabled (REQ-SHELL-NOBQ).
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::buildiq-is-enabled
 *
 * @spec openspec/changes/no-buildiq-edit-control/specs/runtime-shell/spec.md
 */

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

// @e2e runtime-shell::buildiq-is-enabled
test('a launchpad dashboard shows no Buildiq edit button', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await expect(page.locator('[data-testid="cn-buildiq-edit-link"]')).toHaveCount(0)
	await expect(page.locator('.cn-buildiq-edit')).toHaveCount(0)
})
