/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for the build's accessibility check
 * (`npm run check:a11y`, see src/axe-core-check.js): the unit-test setup,
 * restricted to the axe render spec under tests/a11y/.
 */

const base = require('./vitest.config.js')

module.exports = {
	...base,
	test: {
		...base.test,
		include: ['tests/a11y/**/*.a11y.spec.js'],
	},
}
