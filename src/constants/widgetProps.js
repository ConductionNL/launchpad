/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Prop definitions shared by the widget renderers and their forms.
 *
 * `WidgetRenderer.vue` mounts every renderer with the same `content` and
 * `placement` bindings, and the add/edit modal mounts every form with the
 * same `editingWidget` and `value` bindings, so each family declares the
 * same props. Vue copies a prop definition when it normalises a component's
 * props, so one definition object can serve many components.
 */

/**
 * An object prop that defaults to a fresh shallow copy of `defaults`.
 *
 * @param {object} defaults The default's keys and values.
 * @return {{type: ObjectConstructor, default: function(): object}} The prop definition.
 * @spec openspec/specs/widgets/spec.md
 */
export function objectProp(defaults) {
	return {
		type: Object,
		default: () => ({ ...defaults }),
	}
}

/**
 * An object prop that defaults to a fresh empty object.
 *
 * @type {{type: ObjectConstructor, default: function(): object}}
 */
export const OBJECT_PROP = objectProp({})

/**
 * An object prop that defaults to null, such as the placement being edited
 * (null in create mode).
 *
 * @type {{type: ObjectConstructor, default: null}}
 */
export const NULLABLE_OBJECT_PROP = {
	type: Object,
	default: null,
}
