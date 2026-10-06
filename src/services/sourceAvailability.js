/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Whether a register and schema exist on this instance, asked once per
 * page and remembered (admin-templates REQ-TMPL-022).
 *
 * A ready-made template lists objects of several apps. On an instance
 * without one of those apps, OpenRegister answers 404 ("Register not
 * found") and the list widget would show "Could not load these records"
 * to every employee. The page asks first, with the cheapest request there
 * is, and hides a list that asked to be hidden when its source is not
 * there. Any other failure (403, 500, no network) is NOT "not here": the
 * widget then renders and shows its own error line, because that is a
 * fault someone has to see.
 *
 * @type {Map<string, Promise<boolean|null>>}
 */
const probes = new Map()

/**
 * The key a placement's source is remembered under.
 *
 * @param {object} content The object-list widget's content.
 * @return {string} `register/schema`, or '' when either is missing.
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
 */
export function sourceKey(content) {
	const register = content && content.register
	const schema = content && content.schema
	if (!register || !schema) {
		return ''
	}
	return `${register}/${schema}`
}

/**
 * Whether a placement asks to be hidden when its source is not here.
 *
 * @param {object} placement A widget placement.
 * @return {boolean} True for an object-list with `hideWhenUnavailable`.
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
 */
export function hidesWhenUnavailable(placement) {
	return (
		!!placement
		&& placement.widgetId === 'object-list'
		&& !!placement.content
		&& placement.content.hideWhenUnavailable === true
		&& sourceKey(placement.content) !== ''
	)
}

/**
 * Ask OpenRegister whether the source exists. One request per source per
 * page; later callers share the answer.
 *
 * @param {string} register The register slug.
 * @param {string} schema The schema slug.
 * @return {Promise<boolean|null>} true: there; false: OpenRegister says
 *   404; null: could not tell (any other failure).
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
 */
export function isSourceAvailable(register, schema) {
	const key = `${register}/${schema}`
	if (!probes.has(key)) {
		probes.set(key, probe(register, schema))
	}
	return probes.get(key)
}

/**
 * Forget every answer (tests, and a page that re-reads after an install).
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
 */
export function resetSourceAvailability() {
	probes.clear()
}

/**
 * The request behind {@link isSourceAvailable}.
 *
 * @param {string} register The register slug.
 * @param {string} schema The schema slug.
 * @return {Promise<boolean|null>} See {@link isSourceAvailable}.
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
 */
async function probe(register, schema) {
	try {
		await axios.get(
			generateUrl('/apps/openregister/api/objects/{register}/{schema}', {
				register,
				schema,
			}),
			{ params: { _limit: 1 } },
		)
		return true
	} catch (error) {
		const status = error && error.response && error.response.status
		if (status === 404) {
			return false
		}
		return null
	}
}
