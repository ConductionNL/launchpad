/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the cross-widget filter bus
 * (drill-down-cross-widget-filter): the URL restore of Task 22, the reset of
 * Task 25 and the active-filters panel of Task 26.
 *
 * The filter state is set through the `f` URL parameter, the way a bookmark
 * restores it. The widget-click halves of these tasks wait for a chart to
 * emit its selection (Task 7, blocked on @conduction/nextcloud-vue) and are
 * not covered here.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::url-restores-in-a-fresh-browser
 *   @e2e cross-widget-filter::reset-keeps-only-the-pinned-clause
 *   @e2e cross-widget-filter::reset-on-empty-bus-changes-nothing
 *   @e2e cross-widget-filter::panel-shows-sources-and-removes-one
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md
 */

import type { Clause, SeededUser } from './support/filterBus.ts'

import { expect, test } from '@playwright/test'
import {
	chip,
	currentFilterParam,
	decodeFilterParam,
	filterParam,
	openFiltered,
	provisionUserWithDashboard,
	removeUser,
} from './support/filterBus.ts'

const PINNED: Clause = {
	dimension: 'provincieCode',
	operator: 'eq',
	value: 'PV26',
	source: 'pinned',
	pinned: true,
}

const FIVE: Clause[] = [
	PINNED,
	{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
	{ dimension: 'status', operator: 'in', value: ['open', 'in behandeling'] },
	{ dimension: 'jaar', operator: 'eq', value: 2026 },
	{ dimension: 'categorie', operator: 'notNull' },
]

let user: SeededUser | null = null

test.beforeAll(async () => {
	user = await provisionUserWithDashboard('e2e-cwf')
})

test.afterAll(async () => {
	await removeUser(user)
})

test.describe('cross-widget filter (REQ-CWF-003, REQ-CWF-006, REQ-CWF-007)', () => {
	// @e2e cross-widget-filter::url-restores-in-a-fresh-browser
	test('a filtered URL restores the same filters in a fresh browser context', async ({
		browser,
	}) => {
		const clauses: Clause[] = [
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
			{ dimension: 'status', operator: 'in', value: ['open'] },
		]
		const first = await openFiltered(browser, user!, clauses)
		let copied: string
		try {
			await expect(chip(first.page, 'gemeenteCode')).toContainText('GM0355')
			await expect(chip(first.page, 'status')).toContainText('open')
			copied = first.page.url()
			expect(currentFilterParam(first.page)).not.toBeNull()
		} finally {
			await first.context.close()
		}

		// A second, separate login, the way an incognito window opens a bookmark.
		const second = await openFiltered(browser, user!)
		try {
			await second.page.goto(copied)
			await expect(chip(second.page, 'gemeenteCode')).toContainText('GM0355')
			await expect(chip(second.page, 'status')).toContainText('open')
		} finally {
			await second.context.close()
		}
	})

	// @e2e cross-widget-filter::reset-keeps-only-the-pinned-clause
	test('Reset filters clears every non-pinned clause, keeps the pinned one and drops f', async ({
		browser,
	}) => {
		const { context, page } = await openFiltered(browser, user!, FIVE)
		try {
			const chips = page.locator('[data-testid="active-filters"] [data-dimension]')
			await expect(chips).toHaveCount(5)

			await page.getByRole('button', { name: 'Reset filters' }).click()

			await expect(chips).toHaveCount(1)
			await expect(chip(page, 'provincieCode')).toContainText('PV26')
			await expect(
				chip(page, 'provincieCode').locator('[data-testid="active-filter-lock"]'),
			).toBeVisible()
			// The URL sync rewrites `f` after its 500 ms debounce: only the pinned clause is left.
			await expect
				.poll(() => {
					const value = currentFilterParam(page)
					return value === null ? null : decodeFilterParam(value).map((c) => c.d)
				}, { timeout: 5_000 })
				.toEqual(['provincieCode'])
			// Nothing left to clear: the button is gone.
			await expect(page.getByRole('button', { name: 'Reset filters' })).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e cross-widget-filter::reset-on-empty-bus-changes-nothing
	test('an unfiltered dashboard shows no panel, no reset and no f parameter', async ({
		browser,
	}) => {
		const { context, page } = await openFiltered(browser, user!)
		try {
			await expect(page.locator('[data-testid="active-filters"]')).toHaveCount(0)
			await expect(page.getByRole('button', { name: 'Reset filters' })).toHaveCount(0)
			await page.waitForTimeout(1_000)
			expect(currentFilterParam(page)).toBeNull()
		} finally {
			await context.close()
		}
	})

	// @e2e cross-widget-filter::panel-shows-sources-and-removes-one
	test('the panel marks each source, has no remove button on a pinned clause and removes one clause', async ({
		browser,
	}) => {
		const clauses: Clause[] = [
			{
				dimension: 'gemeenteCode',
				operator: 'eq',
				value: 'GM0355',
				source: 'widgetClick',
				sourceWidgetId: 'e2e-gemeente-chart',
			},
			{ dimension: 'status', operator: 'eq', value: 'open' },
			PINNED,
		]
		const { context, page } = await openFiltered(browser, user!, clauses)
		try {
			await expect(page.locator('[data-testid="active-filters"]')).toContainText(
				'Active filters',
			)
			// A restored clause carries no source note; the pinned one a lock and no remove button.
			await expect(chip(page, 'status').locator('[data-testid="active-filter-source"]')).toHaveCount(0)
			await expect(chip(page, 'status').locator('[data-testid="active-filter-remove"]')).toHaveCount(1)
			await expect(chip(page, 'provincieCode').locator('[data-testid="active-filter-lock"]')).toBeVisible()
			await expect(chip(page, 'provincieCode').locator('[data-testid="active-filter-remove"]')).toHaveCount(0)

			await chip(page, 'status').locator('[data-testid="active-filter-remove"]').click()

			await expect(chip(page, 'status')).toHaveCount(0)
			await expect(chip(page, 'provincieCode')).toHaveCount(1)
			await expect
				.poll(() => {
					const value = currentFilterParam(page)
					return value === null ? [] : decodeFilterParam(value).map((c) => c.d).sort()
				}, { timeout: 5_000 })
				.not.toContain('status')
		} finally {
			await context.close()
		}
	})

	test('filterParam encodes clauses the way the dashboard reads them', () => {
		// Guard for the helper itself: short keys, base64url without padding.
		const value = filterParam([PINNED])
		expect(value).not.toMatch(/[+/=]/)
		expect(decodeFilterParam(value)).toEqual([
			{ d: 'provincieCode', o: 'eq', v: 'PV26', s: 'pinned', p: true },
		])
	})
})
