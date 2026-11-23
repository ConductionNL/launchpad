/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The grid size a widget added through the Add Widget dialog starts at, for
 * the types whose spec names one.
 */

/** Header height presets in pixels (header-widget REQ-HDR-009). */
export const HEADER_HEIGHT_PIXELS = Object.freeze({
	small: 120,
	medium: 200,
	large: 320,
	xlarge: 480,
})

/**
 * The starting size of a new custom widget, or null to use the default.
 *
 * A header spans the dashboard's full width and is as many rows tall as its
 * height preset needs.
 *
 * @param {{type: string, content?: object}} payload The Add Widget payload.
 * @param {number} gridColumns The dashboard's column count.
 * @param {number} rowHeight The grid row height in pixels.
 * @return {{w: number, h: number}|null} The size, or null.
 * @spec openspec/specs/header-widget/spec.md
 */
export function newWidgetSize(payload, gridColumns, rowHeight) {
	if (payload?.type !== 'header') {
		return null
	}
	const preset = payload.content?.height
	const pixels = HEADER_HEIGHT_PIXELS[preset] ?? HEADER_HEIGHT_PIXELS.medium
	return {
		w: Number.isFinite(gridColumns) && gridColumns > 0 ? gridColumns : 12,
		h: Math.ceil(pixels / rowHeight),
	}
}
