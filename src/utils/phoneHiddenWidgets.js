/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Widgets that do not work on a phone (board LpMobiel). A widget type whose
 * registry entry says `mobileReady: false` is hidden on a phone, and the
 * workspace notice names the hidden widgets: three by title, the rest
 * counted. There is no forced desktop layout on a phone.
 */

import { t } from '@nextcloud/l10n'
import { PHONE_MAX_WIDTH } from './installPrompt.js'

/** How many hidden widgets the notice names before it counts the rest. */
export const NAMED_MAX = 3

/**
 * Whether a viewport width is a phone's. An unknown width is not.
 *
 * @param {number} width The viewport width in pixels.
 * @return {boolean} True below the phone breakpoint.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function isPhoneWidth(width) {
	return typeof width === 'number' && width > 0 && width < PHONE_MAX_WIDTH
}

/**
 * Whether a widget type works on a phone. Only an explicit
 * `mobileReady: false` hides a widget; an entry without the flag stays shown.
 *
 * @param {object|null|undefined} entry The widget type's registry entry.
 * @return {boolean} False when the type declares it is not ready for a phone.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function isPhoneReady(entry) {
	return !(entry && entry.mobileReady === false)
}

/**
 * The placements that a phone hides.
 *
 * @param {Array<object>} placements The placements that would show.
 * @param {(type: string) => object|null} entryOf Looks up a type's registry entry.
 * @return {Array<object>} The placements whose type is not ready for a phone.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function phoneHiddenPlacements(placements, entryOf) {
	return (placements || []).filter((p) => !isPhoneReady(entryOf(p?.widgetId)))
}

/**
 * The title the notice uses for a hidden widget: its own title, else the
 * title in its content, else the type's display name, else the type.
 *
 * @param {object} placement The placement.
 * @param {object|null} entry The type's registry entry.
 * @return {string} The title.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function hiddenWidgetTitle(placement, entry) {
	const candidates = [
		placement?.customTitle,
		placement?.title,
		placement?.content?.title,
		entry?.displayName,
		placement?.widgetId,
	]
	const found = candidates.find((c) => typeof c === 'string' && c.trim() !== '')
	return found ? found.trim() : ''
}

/**
 * The names in the notice: up to three titles joined with "and", or three
 * titles and "and N more".
 *
 * @param {Array<string>} titles The titles of the hidden widgets.
 * @return {string} The names, or an empty string without titles.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function namedList(titles) {
	const list = (titles || []).filter((x) => typeof x === 'string' && x !== '')
	if (list.length === 0) {
		return ''
	}
	if (list.length === 1) {
		return list[0]
	}
	if (list.length <= NAMED_MAX) {
		return t('launchpad', '{list} and {last}', {
			list: list.slice(0, -1).join(', '),
			last: list[list.length - 1],
		}, undefined, { escape: false })
	}
	return t('launchpad', '{list} and {count} more', {
		list: list.slice(0, NAMED_MAX).join(', '),
		count: list.length - NAMED_MAX,
	}, undefined, { escape: false })
}

/**
 * The notice sentence: how many widgets a phone hides, and their names.
 *
 * @param {Array<string>} titles The titles of the hidden widgets.
 * @return {string} The sentence, or an empty string when nothing is hidden.
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export function phoneHiddenSentence(titles) {
	const count = (titles || []).length
	if (count === 0) {
		return ''
	}
	const names = namedList(titles)
	if (count === 1) {
		return t('launchpad', 'One widget does not work on a phone and is hidden: {names}.', { names }, undefined, { escape: false })
	}
	return t('launchpad', '{count} widgets do not work on a phone and are hidden: {names}.', { count, names }, undefined, { escape: false })
}
