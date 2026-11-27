// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Sending a request for a missing app to IT and saying how it went
 * (launcher-app-catalogue REQ-ACAT-003), for the dialogs that offer it.
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */

import { t } from '@nextcloud/l10n'

/**
 * Send the request through the host's function.
 *
 * @param {function({appName: string, purpose: string}): Promise} requestAppFn The host's request function.
 * @param {string} appName The app asked for.
 * @param {string} purpose What it is for.
 * @return {Promise<{failed: boolean, message: string}>} how it went.
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export async function sendAppRequest(requestAppFn, appName, purpose) {
	try {
		await requestAppFn({ appName: appName.trim(), purpose: purpose.trim() })
		return { failed: false, message: t('launchpad', 'Your request was sent to IT') }
	} catch (error) {
		const limit = error?.response?.data?.code === 'request_limit'
		return {
			failed: true,
			message: limit
				? t('launchpad', 'You have 10 open requests. Wait for an answer first.')
				: t('launchpad', 'Your request could not be sent. Try again later.'),
		}
	}
}
