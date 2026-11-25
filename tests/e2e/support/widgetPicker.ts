/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Placing a card from the widget picker, as LpWidgetKiezen draws it
 * (widget-picker-board-sync, REQ-WDG-040): a click on a card only chooses
 * it (the card shows a check), and the picker's primary "Add widget"
 * button places the chosen card. A Launchpad type then opens the Add
 * Widget form on that type; a Nextcloud widget, an app tile or the office
 * pack is placed and the picker closes.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
 */

import type { Locator } from '@playwright/test'

import { expect } from '@playwright/test'

/** The picker's primary button that places the chosen card. */
export const WIDGET_PICKER_ADD = '[data-testid="widget-picker-add"]'

/**
 * Choose a card in the open widget picker and place it with "Add widget".
 *
 * @param card the card to place, e.g. `page.locator('[data-testid="widget-picker-type-label"]')`.
 * @return nothing; the card's own action has run (form opened or widget placed).
 */
export async function chooseAndAdd(card: Locator): Promise<void> {
	await card.click()
	await expect(card).toHaveAttribute('aria-pressed', 'true', { timeout: 5_000 })
	const add = card.page().locator(WIDGET_PICKER_ADD)
	await expect(add).toBeEnabled({ timeout: 5_000 })
	await add.click()
}
