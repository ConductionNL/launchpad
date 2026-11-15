/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The client-side URL checks shared by the forms of widgets that load an
 * external URL (iframe embed, live data tile). They give the author early
 * feedback only: the server enforces the allow-list and the http(s) scheme
 * again when it saves and when it fetches, and refuses on failure.
 */

import { translate as t } from '@nextcloud/l10n'

/**
 * The message for an allow-list answer from the server: the host is not on
 * the allow-list, or the URL is not a valid http(s) URL, or none.
 *
 * @param {{valid: boolean, errors: string[]}} result The server's validation answer.
 * @return {string} The message, or `''` when the answer raises none.
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
export function allowListMessage(result) {
	if (result.valid !== false) {
		return ''
	}
	if (result.errors.includes('host_not_allowed')) {
		return t('launchpad', 'This host is not on the allow-list.')
	}
	if (result.errors.includes('invalid_url')) {
		return t('launchpad', 'Enter a valid http(s) URL.')
	}
	return ''
}

/**
 * The validation errors for a URL field: required, an http(s) URL with
 * something after the scheme, and the allow-list message the last check
 * left, if any. Any other scheme (`javascript:`, `data:`, `ftp:`) and a
 * URL without a scheme are refused.
 *
 * @param {unknown} url The field value.
 * @param {string} allowListError The message from the last allow-list check.
 * @return {string[]} The errors, empty when the URL may be saved.
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
export function urlFieldErrors(url, allowListError) {
	const errors = []
	if (typeof url !== 'string' || url.trim() === '') {
		errors.push(t('launchpad', 'URL is required'))
	} else if (!/^https?:\/\/.+/i.test(url.trim())) {
		errors.push(t('launchpad', 'Enter a valid http(s) URL.'))
	}
	if (allowListError) {
		errors.push(allowListError)
	}
	return errors
}
