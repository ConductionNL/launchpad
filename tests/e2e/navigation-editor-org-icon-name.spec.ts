/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A bare icon name on an org-nav node (openspec/specs/navigation-editor-org,
 * REQ-ONAV-006, scenario icon-name-renders-inline).
 *
 * A bare name renders as the glyph it resolves to (decision 25 Nov 2026,
 * launchpad PR "navigation-editor-org: a bare icon name is its glyph"): the
 * rail maps it to its MDI path (`normaliseIconValue`,
 * src/services/iconCatalogue.js) and draws it through CnDashboardIcon, as
 * dashboard-icons REQ-ICON-009 and REQ-ONAV-005's own "with the folder icon"
 * scenario expect. REQ-ONAV-006 used to say the name shows as text; it now
 * says the glyph.
 *
 * This test used to be `test.fixme` in navigation-editor-org-panel.spec.ts,
 * which gate-19 does not count. It sits in its own file, without serial mode,
 * so its red cannot skip the rail tests there.
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::icon-name-renders-inline
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Snapshot } from './support/orgNavigation.ts'

import { mdiBriefcase } from '@mdi/js'
import { expect, test } from '@playwright/test'
import {
	APP_PATH,
	grantReadsToEveryone,
	navNode,
	navRow,
	openWorkspace,
	restore,
	seedTree,
	setPosition,
	snapshot,
} from './support/orgNavigation.ts'
import { adminContext } from './support/principals.ts'

const RUN = Date.now()

let admin: APIRequestContext
let snap: Snapshot | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	snap = await snapshot(admin)
	await grantReadsToEveryone(admin)
})

test.afterAll(async () => {
	await restore(admin, snap)
	await admin.dispose()
})

// @e2e navigation-editor-org::icon-name-renders-inline
test('a bare icon name appears as its glyph, inline left of the label', async ({
	page,
}) => {
	const label = `E2E Business ${RUN}`
	await seedTree(admin, [
		navNode(label, {
			icon: 'briefcase',
			url: `${APP_PATH}/dashboards/onav-business`,
		}),
	])
	await setPosition(admin, 'left')
	await openWorkspace(page)

	const row = navRow(page, label)
	await expect(row).toBeVisible({ timeout: 30_000 })
	const icon = row.locator('.org-nav-item__icon')
	await expect(icon.locator(`path[d="${mdiBriefcase}"]`)).toHaveCount(1)
	// The glyph, not the name spelled out.
	await expect(row).not.toContainText('briefcase')
	const iconBox = await icon.boundingBox()
	const labelBox = await row.locator('.org-nav-item__label').boundingBox()
	expect(iconBox).not.toBeNull()
	expect(labelBox).not.toBeNull()
	expect(iconBox!.x + iconBox!.width).toBeLessThanOrEqual(labelBox!.x)
})
