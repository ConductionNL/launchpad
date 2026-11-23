/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard settings modal fits a phone screen (390x844), in light and
 * dark: every tab's content stays inside the modal, the modal stays inside
 * the viewport, and the Drill-down levels picker is fully on screen.
 *
 * An element counts as overflowing when its box leaves the modal's box, or
 * when the modal body itself scrolls sideways. Content inside an element that
 * clips or scrolls on its own (an ellipsised option, the tab strip at phone
 * width) is judged by that element's box instead.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { installBundleOverride } from './support/bundleOverride.ts'
import { openDashboardSettings } from './support/dashboardSwitcher.ts'
import { adminApi, openWorkspace, Seeds } from './support/widgetPlacements.ts'

const PHONE = { width: 390, height: 844 }

/**
 * Describe every element inside the open settings modal that sticks out.
 *
 * @param page the page with the modal open.
 * @return one line per offending element; empty when everything fits.
 */
async function overflowingElements(page: Page): Promise<string[]> {
	return page.evaluate(() => {
		const config = document.querySelector('.dashboard-config') as HTMLElement
		const frame = config.closest('.modal-container') as HTMLElement
		const body = config.closest('.modal-container__content') as HTMLElement
		const box = frame.getBoundingClientRect()
		const found: string[] = []
		const name = (el: Element) => `${el.tagName.toLowerCase()}.${String((el as HTMLElement).className?.baseVal ?? (el as HTMLElement).className).trim().split(/\s+/).join('.')}`

		if (box.left < -1 || box.right > window.innerWidth + 1) {
			found.push(`modal frame spans ${Math.round(box.left)}..${Math.round(box.right)} in a ${window.innerWidth}px viewport`)
		}
		if (body.scrollWidth > body.clientWidth + 1) {
			found.push(`modal body scrolls sideways: ${body.scrollWidth}px of content in ${body.clientWidth}px`)
		}

		const clips = (el: Element) => ['auto', 'scroll', 'hidden', 'clip'].includes(getComputedStyle(el).overflowX)
		const insideClipper = (el: Element) => {
			for (let up = el.parentElement; up && up !== config; up = up.parentElement) {
				if (clips(up)) {
					return true
				}
			}
			return false
		}

		for (const el of Array.from(config.querySelectorAll('*'))) {
			const rect = el.getBoundingClientRect()
			if (rect.width === 0 || rect.height === 0 || insideClipper(el)) {
				continue
			}
			if (rect.left < box.left - 1 || rect.right > box.right + 1) {
				found.push(`${name(el)} spans ${Math.round(rect.left)}..${Math.round(rect.right)}, modal ${Math.round(box.left)}..${Math.round(box.right)}`)
			}
		}
		return found
	})
}

for (const colorScheme of ['light', 'dark'] as const) {
	test.describe(`The dashboard settings modal on a phone (${colorScheme})`, () => {
		test.use({ viewport: PHONE, colorScheme })

		test('every tab fits the screen and the Drill-down levels picker is fully visible', async ({ page }) => {
			await installBundleOverride(page.context())
			const seeds = new Seeds(await adminApi())
			try {
				await seeds.dashboard(`E2E config modal phone ${colorScheme} ${Date.now()}`)
				await openWorkspace(page)
				await openDashboardSettings(page)
				await expect(page.locator('.dashboard-config')).toBeVisible({ timeout: 10_000 })

				const picker = page.getByTestId('dashboard-hierarchies-select')
				await expect(picker).toBeVisible({ timeout: 10_000 })
				await picker.scrollIntoViewIfNeeded()
				const rect = await picker.boundingBox()
				expect(rect, 'the picker has a box').not.toBeNull()
				expect(rect!.x, 'the picker starts on screen').toBeGreaterThanOrEqual(0)
				expect(rect!.x + rect!.width, 'the picker ends on screen').toBeLessThanOrEqual(PHONE.width)

				const tabs = page.locator('.dashboard-config__tab')
				const count = await tabs.count()
				expect(count, 'the owner sees the tab strip').toBeGreaterThan(1)
				for (let i = 0; i < count; i++) {
					const tab = tabs.nth(i)
					const label = (await tab.innerText()).trim()
					await tab.click()
					await expect(tab).toHaveAttribute('aria-selected', 'true')
					await expect.poll(() => overflowingElements(page), { message: `tab "${label}" overflows`, timeout: 5_000 }).toEqual([])
				}
			} finally {
				await seeds.cleanup()
			}
		})
	})
}
