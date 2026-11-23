/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * The content a placement renders with: its stored content as a plain
 * object, with the files widget's upload off unless its author switched it
 * on (the shared renderer would otherwise default it on).
 *
 * @param {{widgetId?: string, content?: object|null}|null|undefined} placement The placement.
 * @return {object} The content to render with.
 * @spec openspec/specs/files-widget/spec.md
 */
export function placementContent(placement) {
	const content = placement?.content || {}
	if (placement?.widgetId === 'files' && content.allowUpload === undefined) {
		return { ...content, allowUpload: false }
	}
	return content
}
