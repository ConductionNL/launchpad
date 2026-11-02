/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the URL checks of IframeWidgetForm and LiveTileWidgetForm:
 * `checkUrlAllowed()` against every allow-list answer, and `validate()` over
 * URLs (allowed, malformed, other schemes) with and without an allow-list
 * error, compared with fixtures/urlAllowList.golden.json case by case.
 * Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { validateIframeUrl } from '../../../../services/iframeClient.js'
import { validateLiveTileSource } from '../../../../services/liveTileClient.js'
import IframeWidgetForm from '../IframeWidgetForm.vue'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => `t:${text}`,
	t: (_app, text) => `t:${text}`,
}))
vi.mock('../../../../services/iframeClient.js', () => ({ validateIframeUrl: vi.fn() }))
vi.mock('../../../../services/liveTileClient.js', () => ({
	validateLiveTileSource: vi.fn(),
	fetchConnectorAvailability: vi.fn(),
	checkConnectorAvailable: vi.fn(),
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/urlAllowList.golden.json')

const URLS = {
	empty: '',
	blank: '   ',
	https: 'https://ok.example/page',
	'http upper case': 'HTTP://OK.EXAMPLE',
	padded: '  https://ok.example  ',
	'scheme only': 'https://',
	'no scheme': 'ok.example',
	javascript: 'javascript:alert(1)',
	data: 'data:text/html,<b>x</b>',
	ftp: 'ftp://files.example',
	'protocol relative': '//ok.example',
}

const ANSWERS = {
	allowed: { valid: true, errors: [] },
	'host not allowed': { valid: false, errors: ['host_not_allowed'] },
	'invalid url': { valid: false, errors: ['invalid_url'] },
	'both errors': { valid: false, errors: ['invalid_url', 'host_not_allowed'] },
	'other error': { valid: false, errors: ['too_long'] },
	'valid missing': { errors: ['host_not_allowed'] },
}

const FORMS = {
	iframe: {
		component: IframeWidgetForm,
		client: validateIframeUrl,
		host: (url) => ({ url, title: 'Frame', allowListChecked: 'unset' }),
	},
	'live tile url': {
		component: LiveTileWidgetForm,
		client: validateLiveTileSource,
		host: (url) => ({ url, sourceMode: 'url', valueExpr: 'value', sourceId: '' }),
	},
	'live tile connector': {
		component: LiveTileWidgetForm,
		client: validateLiveTileSource,
		host: (url) => ({
			url,
			sourceMode: 'connector',
			valueExpr: '',
			sourceId: 's1',
			connectorAvailable: true,
		}),
	},
}

/**
 * A stand-in for the form with its methods bound to it.
 *
 * @param {object} form The form under test.
 * @param {object} fields The form's data.
 * @return {object} The stand-in.
 */
function standIn(form, fields) {
	const host = { urlAllowListError: 'earlier error', ...fields }
	host.$emit = vi.fn()
	host.assembledContent = { url: fields.url }
	for (const [name, fn] of Object.entries(form.component.methods)) {
		host[name] = fn.bind(host)
	}
	return host
}

describe('URL allow-list checks (golden)', () => {
	it('matches the recorded outcomes case by case', async () => {
		const actual = {}
		for (const [formName, form] of Object.entries(FORMS)) {
			for (const [urlName, url] of Object.entries(URLS)) {
				for (const [answerName, answer] of Object.entries(ANSWERS)) {
					form.client.mockReset()
					form.client.mockResolvedValue(answer)
					const host = standIn(form, form.host(url))
					let outcome
					try {
						await host.checkUrlAllowed()
						outcome = {
							error: host.urlAllowListError,
							checked: host.allowListChecked ?? '(none)',
						}
					} catch (error) {
						outcome = { threw: error.constructor.name }
					}
					actual[`${formName} | check | ${urlName} | ${answerName}`] = {
						...outcome,
						calls: form.client.mock.calls.length,
						emitted: host.$emit.mock.calls.length,
					}
				}
				for (const allowListError of ['', 'Not allowed']) {
					const host = standIn(form, { ...form.host(url), urlAllowListError: allowListError })
					actual[`${formName} | validate | ${urlName} | ${allowListError || 'no allow-list error'}`] =
						host.validate()
				}
			}
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
