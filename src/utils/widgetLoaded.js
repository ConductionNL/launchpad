/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * When a widget's content counts as loaded (first-paint-skeleton REQ-FPS-004,
 * task 6): a renderer that declares the `loaded` event in its `emits` is
 * waited for (nextcloud-vue's fetching renderers emit it once their first
 * load has settled, error included); any other renderer counts as loaded
 * after its first render. Until then the card waits in its placeholder.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-each-widget-fills-its-own-frame-when-its-data-arrives-req-fps-004
 */

/** The event a renderer emits once its first content has settled. */
export const LOADED_EVENT = 'loaded'

/**
 * Whether a renderer component declares the `loaded` event, so the card
 * waits for it instead of counting the first render.
 *
 * @param {object|null|undefined} component The renderer component (an options object, or a constructor with `options`).
 * @return {boolean}
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-each-widget-fills-its-own-frame-when-its-data-arrives-req-fps-004
 */
export function rendererEmitsLoaded(component) {
	const emits = component?.emits ?? component?.options?.emits
	if (Array.isArray(emits)) {
		return emits.includes(LOADED_EVENT)
	}
	return emits !== null && typeof emits === 'object' && Object.hasOwn(emits, LOADED_EVENT)
}
