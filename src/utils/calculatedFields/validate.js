// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Validation of a widget's calculated fields before it is saved (REQ-CALC-001,
 * REQ-CALC-002, REQ-CALC-006): every expression is parsed against the allowed
 * functions, the dashboard is checked for cycles, and each field's type is
 * inferred in dependency order so a field reading another one knows its type.
 * The first refusal is thrown with the field it concerns.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

import { allowedFunctions } from './config.js'
import { assertAcyclic } from './cycleDetector.js'
import { CalculatedFieldError, parse } from './parser.js'
import { inferType } from './typeChecker.js'

/**
 * A refusal with the field it concerns added to its detail.
 *
 * @param {CalculatedFieldError} error The refusal.
 * @param {string} field The field name.
 * @return {CalculatedFieldError} The same error.
 */
function forField(error, field) {
	if (error instanceof CalculatedFieldError && error.detail.field === undefined) {
		error.detail = { ...error.detail, field }
	}
	return error
}

/**
 * Refuse a widget that declares a calculated field name twice.
 *
 * @param {string[]} names The declared names, in order.
 * @return {void}
 * @throws {CalculatedFieldError} `duplicateField`.
 */
function assertUniqueNames(names) {
	const duplicate = names.find((name, i) => names.indexOf(name) !== i)
	if (duplicate !== undefined) {
		throw new CalculatedFieldError({ error: 'duplicateField', field: duplicate, message: `The calculated field '${duplicate}' is declared twice` })
	}
}

/**
 * One declared field with its compiled expression.
 *
 * @param {{name: string, expression: string}} field The declared field.
 * @param {string[]} names The widget's calculated field names.
 * @param {Set<string>|null} allowed The allowed functions.
 * @return {object} The field with `compiledExpression`.
 * @throws {CalculatedFieldError} The parse error, naming the field.
 */
function compileField(field, names, allowed) {
	try {
		return { ...field, compiledExpression: parse(field.expression, { calculatedNames: names, allowedFunctions: allowed }) }
	} catch (e) {
		throw forField(e, field.name)
	}
}

/**
 * Type-check this widget's fields in evaluation order, setting each field's
 * `returnType` so later fields see the types of the ones they read.
 *
 * @param {string} id The widget id.
 * @param {Array<object>} fields The compiled fields.
 * @param {string[]} order The dashboard's evaluation order.
 * @param {object} options As for {@link parseAndValidate}.
 * @return {void}
 * @throws {CalculatedFieldError} The type error, naming the field.
 */
function typeFields(id, fields, order, options) {
	const calculatedTypes = {}
	const byName = new Map(fields.map((field) => [field.name, field]))
	for (const node of order.filter((entry) => entry.startsWith(`${id}.`))) {
		const field = byName.get(node.slice(id.length + 1))
		try {
			field.returnType = inferType(field.compiledExpression, options.fieldSchema ?? null, {
				calculatedTypes,
				widgetSchemas: options.widgetSchemas ?? {},
			})
		} catch (e) {
			throw forField(e, field.name)
		}
		calculatedTypes[field.name] = field.returnType
	}
}

/**
 * Parse, cycle-check and type-check a widget's calculated fields.
 *
 * @param {{id: string|number, calculatedFields?: Array<{name: string, expression: string}>}} widget The widget being saved.
 * @param {{fieldSchema?: object, otherWidgets?: Array<object>, widgetSchemas?: object, setting?: object}} [options] The widget's source fields, the dashboard's other widgets (with compiled fields), their field schemas by id, and the instance's function setting.
 * @return {{fields: Array<object>, order: string[]}} The fields with `compiledExpression` and `returnType`, and the dashboard's evaluation order.
 * @throws {CalculatedFieldError} parseError, unknownFunction, arityMismatch, typeError, functionSignature or cycleDetected.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
export function parseAndValidate(widget, options = {}) {
	const id = String(widget.id)
	const declared = Array.isArray(widget.calculatedFields) ? widget.calculatedFields : []
	const names = declared.map((field) => field.name)
	assertUniqueNames(names)
	const allowed = allowedFunctions(options.setting)
	const fields = declared.map((field) => compileField(field, names, allowed))

	const others = (options.otherWidgets || []).filter((other) => String(other.id) !== id)
	const order = assertAcyclic([{ id, calculatedFields: fields }, ...others])
	typeFields(id, fields, order, options)
	return { fields, order }
}
