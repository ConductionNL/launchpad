/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The board grid sits on the page ground, not on the white content panel
 * (runtime-shell REQ-SHELL-012). The content area paints the page ground
 * token; a widget keeps its own surface.
 *
 * @e2e openspec/specs/runtime-shell/spec.md#req-shell-012
 */
import { expect, test } from '@playwright/test'

// @e2e runtime-shell::the-grid-on-the-page-ground
test('the content area carries the page ground and a widget keeps its surface', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/', { waitUntil: 'domcontentloaded' })
	const root = page.locator('[data-testid="cn-app-root"]')
	await expect(root).toHaveClass(/cn-app-root--page-ground/, { timeout: 60_000 })

	const colours = await page.evaluate(() => {
		const content = document.querySelector('#app-content-vue')
		const probe = document.createElement('div')
		probe.style.backgroundColor = 'var(--nldesign-component-content-surface-background-color, var(--color-background-hover))'
		content?.appendChild(probe)
		const ground = getComputedStyle(probe).backgroundColor
		probe.remove()
		const widget = document.querySelector('.grid-stack-item-content')
		return {
			content: content ? getComputedStyle(content).backgroundColor : null,
			ground,
			widget: widget ? getComputedStyle(widget).backgroundColor : null,
		}
	})
	expect(colours.content).toBe(colours.ground)
	if (colours.widget !== null) {
		expect(colours.widget).not.toBe(colours.content)
	}
})
