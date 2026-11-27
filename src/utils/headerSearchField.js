/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Finds the text field the top bar's results list serves (header-search-dropdown,
 * REQ-SRCH-013).
 *
 * Older Nextcloud versions put a text field in the top bar. Since Nextcloud 31
 * the desktop top bar holds a `<search class="unified-search-input">` landmark
 * with only a button that opens the search dialog, so there is nothing to type
 * in under which a list could open. On those versions LaunchPad places its own
 * search field inside that landmark, in the button's place, as the board
 * (LpWerkplek) draws it. The button stays in the DOM, hidden, so "Show all
 * results" and Nextcloud's own shortcuts still reach the dialog. The field
 * takes its words from the button's label, which is where thematiq writes them.
 * The small-screen top bar keeps Nextcloud's button.
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

/** The class of the field LaunchPad places in the top bar. */
export const INLINE_FIELD_CLASS = 'lp-header-search-field'

/** The class the landmark gets while it holds LaunchPad's field. */
export const INLINE_HOST_CLASS = 'lp-header-search-host'

const CORE_BUTTON_SELECTOR = '.unified-search-input__button'
const CORE_LABEL_SELECTOR = '.unified-search-input__label'
const CORE_MOBILE_CLASS = 'unified-search-input--mobile'

/**
 * Whether an element takes typed text.
 *
 * @param {Element} element The element.
 * @return {boolean} True for an input or a textarea.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
function isTextField(element) {
	return element.tagName === 'INPUT' || element.tagName === 'TEXTAREA'
}

/**
 * The words the field shows: the core button's label (thematiq's words when
 * thematiq runs), else the button's accessible name, else the fallback.
 *
 * @param {Element} button The core search button.
 * @param {string} fallback The words when the button has none.
 * @return {string} The placeholder.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function fieldWords(button, fallback) {
	const label = button?.querySelector(CORE_LABEL_SELECTOR)?.textContent?.trim()
	return label || button?.getAttribute('aria-label')?.trim() || fallback
}

/**
 * Resolve the element the selector found to the field to type in.
 *
 * - a text field is used as it is;
 * - a wrapper holding a text field gives that field;
 * - Nextcloud 31+'s desktop landmark (a button, no field) gets LaunchPad's own
 *   field in the button's place; the button is hidden by CSS, not removed;
 * - anything else (the small-screen button, an unknown shape) gives null.
 *
 * @param {Element|null} match The element the field selector found.
 * @param {string} fallbackWords The placeholder when the button has no words.
 * @return {{field: HTMLElement, created: boolean, label: Element|null}|null} The field, whether LaunchPad made it, and the core label to follow.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function resolveSearchField(match, fallbackWords) {
	if (!match) {
		return null
	}
	if (isTextField(match)) {
		return { field: match, created: false, label: null }
	}
	const inner = match.querySelector('input, textarea')
	if (inner) {
		return { field: inner, created: false, label: null }
	}
	const button = match.querySelector(CORE_BUTTON_SELECTOR)
	if (!button || match.classList.contains(CORE_MOBILE_CLASS)) {
		return null
	}
	const words = fieldWords(button, fallbackWords)
	const field = document.createElement('input')
	field.type = 'search'
	field.className = INLINE_FIELD_CLASS
	field.placeholder = words
	field.setAttribute('aria-label', words)
	field.setAttribute('data-testid', 'header-search-field')
	match.classList.add(INLINE_HOST_CLASS)
	match.insertBefore(field, button)
	return { field, created: true, label: button.querySelector(CORE_LABEL_SELECTOR) }
}

/**
 * Open Nextcloud's own search dialog by pressing core's top-bar search
 * button (Nextcloud 31+): core opens its dialog only from that button, and
 * listens to no event for it. The button next to `field` is preferred, then
 * the first one on the page.
 *
 * @param {HTMLElement|null} field The search field LaunchPad is bound to.
 * @return {boolean} Whether a button was found and pressed.
 * @spec openspec/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function openCoreSearchDialog(field) {
	const button = field?.parentElement?.querySelector(CORE_BUTTON_SELECTOR)
		?? document.querySelector(CORE_BUTTON_SELECTOR)
	if (!button) {
		return false
	}
	button.click()
	return true
}

/**
 * Take LaunchPad's own field out of the top bar again and show the core
 * button. Does nothing for a field LaunchPad did not make.
 *
 * @param {HTMLElement|null} field The field resolveSearchField() made.
 * @return {void}
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function removeInlineField(field) {
	if (!field?.classList?.contains(INLINE_FIELD_CLASS)) {
		return
	}
	field.parentElement?.classList.remove(INLINE_HOST_CLASS)
	field.remove()
}
