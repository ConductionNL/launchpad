/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Builds the two distributions of @conduction/launchpad-embed into dist/:
 * an ES module and a UMD bundle that exposes the global `LaunchPadEmbed`.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-19
 */

const path = require('path')

const entry = path.join(__dirname, 'src', 'LaunchPadEmbed.js')
const dist = path.join(__dirname, 'dist')

const shared = {
	mode: 'production',
	entry,
	devtool: 'source-map',
	target: ['web', 'es2020'],
}

module.exports = [
	{
		...shared,
		name: 'esm',
		experiments: { outputModule: true },
		output: {
			path: dist,
			filename: 'embed-sdk.esm.js',
			library: { type: 'module' },
		},
	},
	{
		...shared,
		name: 'umd',
		output: {
			path: dist,
			filename: 'embed-sdk.umd.js',
			library: { name: 'LaunchPadEmbed', type: 'umd', export: 'default' },
			globalObject: 'this',
		},
	},
]
