/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Prop definitions that many components declare the same way.
 *
 * Every widget renderer takes `content` and `placement`, every widget form
 * `editingWidget` and `value`, and most dialogs an `open` flag, so the same
 * definitions recur across the app. Vue copies a prop definition when it
 * normalises a component's props, so one definition object can serve many
 * components.
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

/**
 * A required object prop.
 *
 * @type {{type: ObjectConstructor, required: true}}
 */
export const REQUIRED_OBJECT_PROP = {
	type: Object,
	required: true,
}

/**
 * A list prop that defaults to a fresh empty array.
 *
 * @type {{type: ArrayConstructor, default: function(): Array}}
 */
export const LIST_PROP = {
	type: Array,
	default: () => [],
}

/**
 * A text prop that defaults to the empty string.
 *
 * @type {{type: StringConstructor, default: string}}
 */
export const TEXT_PROP = {
	type: String,
	default: '',
}

/**
 * A required text prop.
 *
 * @type {{type: StringConstructor, required: true}}
 */
export const REQUIRED_TEXT_PROP = {
	type: String,
	required: true,
}

/**
 * A flag prop that defaults to false.
 *
 * @type {{type: BooleanConstructor, default: boolean}}
 */
export const FLAG_PROP = {
	type: Boolean,
	default: false,
}

/**
 * A required flag prop, such as a dialog's `open`.
 *
 * @type {{type: BooleanConstructor, required: true}}
 */
export const REQUIRED_FLAG_PROP = {
	type: Boolean,
	required: true,
}
