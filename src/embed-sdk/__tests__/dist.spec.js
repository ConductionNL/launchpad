/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed SDK builds into an ES module and a UMD bundle under the app's
 * js/embed-sdk/, the names EmbedSdkController serves, and both expose render()
 * (embedded-analytics tasks 19 and 24).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-19
 * @spec openspec/changes/embedded-analytics/tasks.md#task-24
 */

import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join, resolve } from 'node:path'
import { afterAll, beforeAll, describe, expect, it } from 'vitest'

const require = createRequire(__filename)
const webpack = require('webpack')
const configs = require('../webpack.config.cjs')

let out

/**
 * Run the SDK build into a temporary directory.
 *
 * @return {Promise<void>}
 */
function build() {
	const local = configs.map((config) => ({ ...config, output: { ...config.output, path: out } }))
	return new Promise((resolve, reject) => {
		webpack(local, (error, stats) => {
			if (error || stats.hasErrors()) {
				reject(error ?? new Error(stats.toString('errors-only')))
				return
			}
			resolve()
		})
	})
}

describe('embed-sdk distribution', () => {
	beforeAll(async () => {
		out = mkdtempSync(join(__dirname, '.dist-'))
		await build()
	}, 60000)

	afterAll(() => {
		rmSync(out, { recursive: true, force: true })
	})

	it('builds one ESM and one UMD bundle into the app\'s js/embed-sdk/, the names the app serves', () => {
		expect(configs.map((config) => config.output.filename)).toEqual(['embed-sdk.esm.js', 'embed-sdk.umd.js'])
		for (const config of configs) {
			expect(config.output.path).toBe(resolve(__dirname, '../../../js/embed-sdk'))
		}
		const controller = readFileSync(resolve(__dirname, '../../../lib/Controller/EmbedSdkController.php'), 'utf8')
		expect(controller).toContain("'embed-sdk.esm.js'")
		expect(controller).toContain("'embed-sdk.umd.js'")
	})

	it('is not an npm package: private, and the app build builds it', () => {
		const pkg = JSON.parse(readFileSync(resolve(__dirname, '../package.json'), 'utf8'))
		const app = JSON.parse(readFileSync(resolve(__dirname, '../../../package.json'), 'utf8'))
		expect(pkg.private).toBe(true)
		expect(pkg.publishConfig).toBeUndefined()
		expect(app.scripts.build).toContain('npm run build:embed-sdk')
	})

	it('the ES module exports render and LaunchPadEmbed', async () => {
		const sdk = await import(/* @vite-ignore */ join(out, 'embed-sdk.esm.js'))
		expect(typeof sdk.render).toBe('function')
		expect(typeof sdk.default.render).toBe('function')
	})

	it('the UMD bundle sets the LaunchPadEmbed global', () => {
		const scope = {}
		new Function(readFileSync(join(out, 'embed-sdk.umd.js'), 'utf8')).call(scope)
		expect(typeof scope.LaunchPadEmbed.render).toBe('function')
	})
})
