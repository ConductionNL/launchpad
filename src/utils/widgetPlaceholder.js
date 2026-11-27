/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { resolveWidgetPlaceholder } from '@conduction/nextcloud-vue'
import kinds from '../../lib/Service/first-paint-kinds.json'
import registry from '../../lib/Service/widget-placeholders.json'

/**
 * The registry entry the page has for a widget type, as far as its
 * placeholder goes: nextcloud-vue's declaration (the committed copy of
 * `widget-placeholders.json`, the file the server reads too), else the shape
 * of a type launchpad registers itself (`first-paint-kinds.json`).
 *
 * @param {string|undefined} type The widget type (a placement's `widgetId`).
 * @return {{placeholder: object|string}|undefined} An entry with `placeholder`, or undefined.
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009
 */
export function placeholderRegistryEntry(type) {
	if (typeof type !== 'string' || type === '') {
		return undefined
	}
	const declared = Object.hasOwn(registry, type) ? registry[type] : (Object.hasOwn(kinds.kinds, type) ? kinds.kinds[type] : undefined)
	return declared === undefined ? undefined : { placeholder: declared }
}

/**
 * The shape a placement waits in: the manifest's declaration on the
 * placement, the registry's for its type, the Nextcloud widget's from the
 * catalogue, else rows. The same order as FirstPaintShell on the server, so
 * the shell and the mounted card draw the same shape.
 *
 * @param {object|null|undefined} placement The placement (its `placeholder` and `widgetId`).
 * @param {object|null|undefined} catalogueWidget The catalogue descriptor of a Nextcloud widget.
 * @return {{shape: string, count?: number}} The shape to draw.
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009
 */
export function placementPlaceholder(placement, catalogueWidget) {
	return resolveWidgetPlaceholder(placement ?? {}, placeholderRegistryEntry(placement?.widgetId), catalogueWidget ?? undefined)
}
