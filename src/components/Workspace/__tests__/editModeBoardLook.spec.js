/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Edit mode as board LpBewerken draws it, found by the live check of
 * edit-mode-draft-and-save: the card title starts right of the drag grip, the
 * cards carry the field border and a light shadow, and the edit bar stays on
 * one row with the docked More menu showing its three dots only.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/tasks.md
 */

import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const workspaceCss = readFileSync(join(__dirname, '../../../styles/workspace.css'), 'utf8')
const editBar = readFileSync(join(__dirname, '../DashboardEditBar.vue'), 'utf8')

/**
 * The declarations of the first rule whose selector ends with `selectorTail`.
 *
 * @param {string} css The style sheet.
 * @param {string} selectorTail The end of the selector.
 * @return {string} The body of the rule, or '' when there is none.
 */
function ruleBody(css, selectorTail) {
	const at = css.indexOf(selectorTail + ' {')
	if (at === -1) {
		return ''
	}
	const open = css.indexOf('{', at)
	return css.slice(open + 1, css.indexOf('}', open))
}

describe('edit mode cards in the board look (LpBewerken)', () => {
	it('moves the title right of the grip with a rule that outranks the board header padding', () => {
		const base = '.cn-look-board .launchpad-container .cn-widget-wrapper--nc-dashboard .cn-widget-wrapper__header'
		const editing = '.cn-look-board .launchpad-container .launchpad-widget--editing .cn-widget-wrapper--nc-dashboard .cn-widget-wrapper__header'
		expect(ruleBody(workspaceCss, base)).toMatch(/padding: 16px 18px 8px;/)
		expect(ruleBody(workspaceCss, editing)).toMatch(/padding-inline-start: 46px;/)
		// One class more than the base rule, so it wins whatever the order.
		expect(editing.split('.').length).toBeGreaterThan(base.split('.').length)
	})

	it('does the same for an announcement, whose own header rule is more specific', () => {
		const editing = '.launchpad-widget--editing.launchpad-widget--announcement .cn-widget-wrapper--nc-dashboard .cn-widget-wrapper__header'
		expect(ruleBody(workspaceCss, editing)).toMatch(/padding-inline-start: 46px;/)
	})

	it('gives every card the field border and the light shadow', () => {
		const body = ruleBody(workspaceCss, '.launchpad-widget--editing .cn-widget-wrapper.cn-widget-wrapper--nc-dashboard')
		expect(body).toMatch(/border-color: var\(--sc-work-field, var\(--color-border-maxcontrast\)\);/)
		expect(body).toMatch(/box-shadow: 0 1px 2px rgba\(27, 28, 29, 0\.06\);/)
	})
})

describe('the edit bar on one row (LpBewerken)', () => {
	it('spaces its controls 12px apart as drawn', () => {
		expect(ruleBody(editBar, '.launchpad-edit-bar')).toMatch(/gap: 12px;/)
	})

	it('shows the docked More menu as its three dots, keeping the label for screen readers', () => {
		expect(ruleBody(editBar, '.launchpad-edit-bar__dock :deep(.lp-more-menu__trigger)')).toMatch(/padding-inline: 0;/)
		const label = ruleBody(editBar, '.launchpad-edit-bar__dock :deep(.lp-more-menu__trigger .button-vue__text)')
		expect(label).toMatch(/clip-path: inset\(50%\);/)
		expect(label).not.toMatch(/display: none/)
	})
})
