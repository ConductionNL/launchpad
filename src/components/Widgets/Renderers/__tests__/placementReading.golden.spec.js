/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for WeatherWidget and LiveTileWidget: what `load()` leaves in
 * the component (and shows while the request runs) for each placement and
 * answer, and the final style each class gets from the component's style
 * blocks, compared with fixtures/placementReading.golden.json case by case.
 * Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import LiveTileWidget from '../LiveTileWidget.vue'
import WeatherWidget from '../WeatherWidget.vue'
import { fetchLiveTileValue } from '../../../../services/liveTileClient.js'
import { fetchWeatherReading } from '../../../../services/weatherClient.js'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => `t:${text}`,
	t: (_app, text) => `t:${text}`,
}))
vi.mock('../../../../services/weatherClient.js', () => ({
	fetchWeatherReading: vi.fn(),
}))
vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchLiveTileValue: vi.fn(),
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/placementReading.golden.json')
const RENDERERS = path.resolve(__dirname, '..')

const WIDGETS = {
	weather: {
		component: WeatherWidget,
		file: 'WeatherWidget.vue',
		client: fetchWeatherReading,
	},
	'live tile': {
		component: LiveTileWidget,
		file: 'LiveTileWidget.vue',
		client: fetchLiveTileValue,
	},
}

const PLACEMENTS = {
	null: null,
	'no id': {},
	'id null': { id: null },
	'id zero': { id: 0 },
	'id 7': { id: 7 },
}

const ANSWERS = {
	reading: { value: { value: 21, stale: false } },
	'error answer': { value: { error: 'upstream' } },
	'empty error': { value: { error: '' } },
	null: { value: null },
	undefined: { value: undefined },
	rejects: { error: new Error('offline') },
}

/**
 * Run `load()` on a stand-in for the component and record what it did.
 *
 * @param {object} widget The widget under test.
 * @param {{placement: object|null, answer: object}} given The placement and the client answer.
 * @return {Promise<object>} The outcome.
 */
async function runLoad(widget, given) {
	const host = {
		placement: given.placement,
		loading: true,
		errorMessage: 'stale message',
		reading: { old: true },
	}
	let during = null
	widget.client.mockReset()
	widget.client.mockImplementation(async (id) => {
		during = { id, loading: host.loading, errorMessage: host.errorMessage }
		if (given.answer.error) {
			throw given.answer.error
		}
		return given.answer.value
	})
	await widget.component.methods.load.call(host)
	return JSON.parse(
		JSON.stringify({
			during,
			after: {
				loading: host.loading,
				errorMessage: host.errorMessage,
				reading: host.reading === undefined ? '(undefined)' : host.reading,
			},
			calls: widget.client.mock.calls.length,
		}),
	)
}

/**
 * The final declarations per class selector from a component's style blocks,
 * applying the blocks and rules in order (a `src` block read from its file).
 * Only selectors of the component's own block are kept.
 *
 * @param {string} file The component file name.
 * @param {string} block The component's class block, e.g. `weather-widget`.
 * @return {object} Declarations by selector, each with its scoped flag.
 */
function finalStyles(file, block) {
	const source = fs.readFileSync(path.join(RENDERERS, file), 'utf8')
	const out = {}
	const own = new RegExp(`^\\.${block}(?:$|__|--|[\\s:.[>])`)
	for (const match of source.matchAll(/<style([^>]*)>([\s\S]*?)<\/style>/g)) {
		const src = /src="([^"]+)"/.exec(match[1])
		const css = (
			src ? fs.readFileSync(path.join(RENDERERS, src[1]), 'utf8') : match[2]
		).replace(/\/\*[\s\S]*?\*\//g, '')
		const scoped = /\bscoped\b/.test(match[1])
		for (const rule of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
			for (const selector of rule[1]
				.split(',')
				.map((s) => s.trim().replace(/\s+/g, ' '))) {
				if (!own.test(selector)) {
					continue
				}
				const entry = (out[selector] ??= { scoped, props: {} })
				entry.scoped = entry.scoped && scoped
				for (const declaration of rule[2].split(';')) {
					const [prop, ...value] = declaration.split(':')
					if (prop.trim()) {
						entry.props[prop.trim()] = value
							.join(':')
							.trim()
							.replace(/\s+/g, ' ')
					}
				}
			}
		}
	}
	return Object.fromEntries(Object.entries(out).sort())
}

describe('placement reading widgets (golden)', () => {
	it('matches the recorded outcomes case by case', async () => {
		const actual = {}
		for (const [name, widget] of Object.entries(WIDGETS)) {
			for (const [placementName, placement] of Object.entries(PLACEMENTS)) {
				for (const [answerName, answer] of Object.entries(ANSWERS)) {
					actual[`${name} | load | ${placementName} | ${answerName}`] =
						await runLoad(widget, {
							placement,
							answer,
						})
				}
			}
			actual[`${name} | styles`] = finalStyles(
				widget.file,
				name === 'weather' ? 'weather-widget' : 'live-tile-widget',
			)
		}
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const [name, value] of Object.entries(expected)) {
			expect(actual[name], name).toEqual(value)
		}
	})
})
