/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A bare icon name on an org-nav node (openspec/specs/navigation-editor-org,
 * REQ-ONAV-006, scenario icon-name-renders-inline).
 *
 * EXPECTED RED until the product renders a bare icon name as inline text, or
 * the scenario is changed. The rail maps a bare name to its MDI path
 * (`normaliseIconValue`, src/services/iconCatalogue.js) and draws it as an
 * `<svg>` through CnDashboardIcon (src/components/OrgNavigationItem.vue, the
 * `CnDashboardIcon :name="iconName"` in the row), so the name never appears as
 * text. That follows dashboard-icons REQ-ICON-009, which maps legacy names to
 * glyphs; REQ-ONAV-006 still says "A bare name MUST render as an inline
 * label". The two specs disagree; this test holds the product to the
 * navigation-editor-org scenario as written.
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
test('a bare icon name appears as inline text left of the label', async ({
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
	await expect(icon).toHaveText('briefcase')
	// Not merely the glyph the name resolves to.
	await expect(row.locator(`path[d="${mdiBriefcase}"]`)).toHaveCount(0)
	const iconBox = await icon.boundingBox()
	const labelBox = await row.locator('.org-nav-item__label').boundingBox()
	expect(iconBox).not.toBeNull()
	expect(labelBox).not.toBeNull()
	expect(iconBox!.x + iconBox!.width).toBeLessThanOrEqual(labelBox!.x)
})
