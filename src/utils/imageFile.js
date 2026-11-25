/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Checks and labels for the file an Image widget uploads (REQ-IMG-020).
 */

/** The largest file the Image form accepts: 5 MB. */
export const MAX_IMAGE_BYTES = 5 * 1024 * 1024

/**
 * Why a file cannot be uploaded as the image, or null when it can.
 *
 * @param {{type?: string, size?: number}|null} file The picked file.
 * @return {'type'|'size'|null} the problem.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
 */
export function isUploadableImage(file) {
	if (!file || typeof file.type !== 'string' || !file.type.startsWith('image/')) {
		return 'type'
	}
	if (typeof file.size !== 'number' || file.size > MAX_IMAGE_BYTES) {
		return 'size'
	}
	return null
}

/**
 * A byte count as "820 KB" or "1.4 MB".
 *
 * @param {number} bytes The size.
 * @return {string} the size.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
 */
export function formatBytes(bytes) {
	if (bytes < 1024) {
		return `${bytes} B`
	}
	if (bytes < 1024 * 1024) {
		return `${Math.round(bytes / 1024)} KB`
	}
	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * The size and dimensions line under a chosen file: "1.4 MB · 1600 × 900",
 * with what is not known left out.
 *
 * @param {{size: (number|null), width: (number|null), height: (number|null)}|null} chosen The chosen file.
 * @return {string} the line.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
 */
export function describeImageFile(chosen) {
	const parts = []
	if (typeof chosen?.size === 'number') {
		parts.push(formatBytes(chosen.size))
	}
	if (chosen?.width && chosen?.height) {
		parts.push(`${chosen.width} × ${chosen.height}`)
	}
	return parts.join(' · ')
}
