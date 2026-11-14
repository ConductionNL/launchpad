/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Thin wrapper around `POST /api/resources` (REQ-RES-001..005). Posts a
 * `data:image/<type>;base64,...` data URL as JSON, returns the server's
 * standard `{status, url, name, size}` envelope on success, and rethrows
 * a normalised `ResourceUploadError` on the standardised
 * `{status: 'error', error, message}` envelope so callers can branch on
 * the stable `error` enum without parsing the human message.
 *
 * Used by:
 * - `image-widget` form
 * - `link-button-widget` icon picker
 * - `IconPicker` (custom-icon-upload pattern, dashboard-icons)
 *
 * Keep this module dependency-light — only `@nextcloud/axios` and
 * `@nextcloud/router`. No store, no Pinia, no app-level state. Two
 * exported helpers: `uploadDataUrl` (server round-trip) and
 * `readFileAsDataUrl` (client-side FileReader convenience).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Resource upload error thrown when the server returns a `status: 'error'`
 * envelope or the network call fails. Carries the stable `error` enum
 * from the server (or `network_error` / `unknown_error` for transport
 * failures) so callers can branch on a machine-readable code without
 * parsing the human message.
 */
export class ResourceUploadError extends Error {
	/**
	 * Create a new resource upload error.
	 *
	 * @param {string} code Stable error enum from the server envelope.
	 * @param {string} message Human-readable display message.
	 * @param {number} [httpStatus] HTTP status code (when known).
	 * @spec openspec/specs/resource-uploads/spec.md
	 */
	constructor(code, message, httpStatus) {
		super(message)
		this.name = 'ResourceUploadError'
		this.code = code
		this.httpStatus = httpStatus
	}
}

/**
 * Upload a base64 data URL via `POST /apps/launchpad/api/resources`.
 *
 * @param {string} dataUrl A `data:image/<type>;base64,<payload>` string.
 * @return {Promise<{url: string, name: string, size: number}>} The
 *   persisted resource info (URL is the public path served by the
 *   `resource-serving` capability).
 * @throws {ResourceUploadError} On any server-side rejection (forbidden,
 *   invalid_image_format, file_too_large, mime_mismatch, etc.) or transport
 *   failure (`code === 'network_error'`).
 * @spec openspec/specs/resource-uploads/spec.md
 */
export async function uploadDataUrl(dataUrl) {
	return postResource(generateUrl('/apps/launchpad/api/resources'), {
		base64: dataUrl,
	})
}

/**
 * Upload a raw `File` via `POST /apps/launchpad/api/resources/upload`
 * (multipart). Unlike {@link uploadDataUrl}, the file is streamed as-is —
 * no base64 conversion — so large images and GIFs never become a huge
 * in-memory string that can freeze the browser tab. Prefer this for
 * user-picked files; the endpoint returns the same envelope.
 *
 * @param {File|Blob} file A `File` (typically from an `<input type="file">`).
 * @return {Promise<{url: string, name: string, size: number}>} The persisted
 *   resource info. `url` is the server's logical `/apps/launchpad/resource/<name>`
 *   path; the renderer resolves it through `generateUrl()` at display time so it
 *   works on index.php-routed instances without persisting routing into content.
 * @throws {ResourceUploadError} On any server-side rejection (forbidden,
 *   no_file, invalid_image_format, file_too_large, invalid_svg, etc.) or
 *   transport failure (`code === 'network_error'`).
 * @spec openspec/specs/resource-uploads/spec.md
 */
export async function uploadFile(file) {
	const url = generateUrl('/apps/launchpad/api/resources/upload')

	const formData = new FormData()
	formData.append('file', file)

	// Let the browser/axios set the multipart boundary and CSRF token.
	return postResource(url, formData)
}

/**
 * Post an upload and read the resource envelope the server answers with.
 *
 * @param {string} url The upload endpoint.
 * @param {object|FormData} payload The request body.
 * @return {Promise<{url: string, name: string, size: number}>} The persisted resource info.
 * @throws {ResourceUploadError} On a server-side rejection or a transport failure.
 */
async function postResource(url, payload) {
	let response
	try {
		response = await axios.post(url, payload)
	} catch (err) {
		throw rejectionError(err)
	}
	return resourceOf(response)
}

/**
 * The error for a failed upload request: the server's own error code and
 * message when it answered with an error envelope, `network_error` otherwise.
 *
 * @param {unknown} err The caught request error.
 * @return {ResourceUploadError} The error to throw.
 */
function rejectionError(err) {
	const data = err?.response?.data
	if (data && data.status === 'error' && typeof data.error === 'string') {
		return new ResourceUploadError(
			data.error,
			typeof data.message === 'string' ? data.message : data.error,
			err?.response?.status,
		)
	}
	return new ResourceUploadError(
		'network_error',
		err?.message || 'Network error',
		err?.response?.status,
	)
}

/**
 * The resource info of a successful upload response.
 *
 * @param {object} response The axios response.
 * @return {{url: string, name: string, size: number}} The persisted resource info.
 * @throws {ResourceUploadError} When the response is not a success envelope with a URL.
 */
function resourceOf(response) {
	const body = response?.data
	if (!body || body.status !== 'success' || typeof body.url !== 'string') {
		throw new ResourceUploadError(
			body?.error || 'unknown_error',
			body?.message || 'Unexpected server response',
			response?.status,
		)
	}

	return {
		url: body.url,
		name: typeof body.name === 'string' ? body.name : '',
		size: typeof body.size === 'number' ? body.size : 0,
	}
}

/**
 * Read a `File` object as a base64 data URL — convenience wrapper around
 * `FileReader` that returns a promise so callers can `await` it. Used by
 * the image-widget form and link-button widget icon picker.
 *
 * @param {File|Blob} file File or Blob (typically from an `<input type="file">`).
 * @return {Promise<string>} Resolves to a `data:<mime>;base64,<payload>` URL.
 * @spec openspec/specs/resource-uploads/spec.md
 */
export function readFileAsDataUrl(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.onload = () =>
			resolve(typeof reader.result === 'string' ? reader.result : '')
		reader.onerror = () => reject(reader.error || new Error('FileReader failed'))
		reader.readAsDataURL(file)
	})
}

export default { uploadDataUrl, uploadFile, ResourceUploadError, readFileAsDataUrl }
