/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget picker's assistant line (REQ-WDG-044): whether the server can
 * answer, read once per page, and one proposal for a described need, always
 * one of the cards the picker offered.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */

import { api } from './api.js'

let availability = null

/**
 * Whether the assistant line can answer; read from the server once per page.
 * A failed read counts as unavailable and is asked again next time.
 *
 * @return {Promise<boolean>}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
export function assistantAvailable() {
	if (availability === null) {
		availability = api.widgetAssistant()
			.then((response) => response?.data?.available === true)
			.catch(() => {
				availability = null
				return false
			})
	}
	return availability
}

/**
 * Forget the cached availability (tests, and after a sign-in change).
 *
 * @return {void}
 */
export function resetAssistantAvailability() {
	availability = null
}

/**
 * The cards sent to the assistant: the ones the reader may add, as
 * `{key, name, description}`.
 *
 * @param {Array<object>} cards The picker's cards (`{key, title, line, disabled}`).
 * @return {Array<{key: string, name: string, description: string}>}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
export function offeredCards(cards) {
	return (cards || [])
		.filter((card) => card && !card.disabled && typeof card.key === 'string' && card.key !== '')
		.map((card) => ({ key: card.key, name: String(card.title ?? ''), description: String(card.line ?? '') }))
}

/**
 * Ask for a proposal. Resolves to `{status: 'proposed', card, reason}` when
 * the answer names an offered card, `{status: 'none'}` when it names none,
 * and `{status: 'failed'}` when the request failed.
 *
 * @param {string} need What the reader needs, in their words.
 * @param {Array<object>} cards The picker's cards.
 * @return {Promise<{status: string, card?: object, reason?: string}>}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
export async function proposeWidget(need, cards) {
	const offered = offeredCards(cards)
	let proposal
	try {
		const response = await api.proposeWidget({ need: need.trim(), cards: offered })
		proposal = response?.data?.proposal ?? null
	} catch {
		return { status: 'failed' }
	}
	const card = proposal ? (cards || []).find((candidate) => candidate.key === proposal.key && !candidate.disabled) : null
	if (!card) {
		return { status: 'none' }
	}
	return { status: 'proposed', card, reason: typeof proposal.reason === 'string' ? proposal.reason : '' }
}
