/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for uploadDataUrl() and uploadFile(): each against a set of
 * server answers and request failures, with the request sent, compared with
 * fixtures/resourceUploads.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import axios from '@nextcloud/axios'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { uploadDataUrl, uploadFile } from '../resourceService.js'

vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/resourceUploads.golden.json')

const ANSWERS = {
	success: {
		status: 200,
		data: { status: 'success', url: '/r/a.png', name: 'a.png', size: 12 },
	},
	'success without name and size': {
		status: 201,
		data: { status: 'success', url: '/r/b.png', name: 3, size: '12' },
	},
	'success without url': { status: 200, data: { status: 'success' } },
	'error envelope': {
		status: 200,
		data: { status: 'error', error: 'file_too_large', message: 'Too big' },
	},
	'unknown status': { status: 200, data: { status: 'pending', url: '/r/c' } },
	'no body': { status: 204, data: null },
	'no response': undefined,
}

const FAILURES = {
	'server error envelope': Object.assign(new Error('Request failed'), {
		response: {
			status: 413,
			data: { status: 'error', error: 'file_too_large', message: 'Too big' },
		},
	}),
	'envelope without message': Object.assign(new Error('Request failed'), {
		response: { status: 403, data: { status: 'error', error: 'forbidden' } },
	}),
	'envelope with numeric message': Object.assign(new Error('x'), {
		response: {
			status: 400,
			data: { status: 'error', error: 'mime_mismatch', message: 5 },
		},
	}),
	'envelope with numeric error': Object.assign(new Error('Bad'), {
		response: { status: 400, data: { status: 'error', error: 9 } },
	}),
	'envelope with another status': Object.assign(new Error('Unprocessable'), {
		response: {
			status: 422,
			data: { status: 'fail', error: 'invalid_svg', message: 'Bad SVG' },
		},
	}),
	'not an error envelope': Object.assign(new Error('Gateway'), {
		response: { status: 502, data: '<html>' },
	}),
	network: Object.assign(new Error('Network Error'), { code: 'ERR_NETWORK' }),
	'no message': new Error(''),
	null: null,
}

const CALLS = {
	uploadDataUrl: () => uploadDataUrl('data:image/png;base64,AAAA'),
	uploadFile: () =>
		uploadFile(new File(['abc'], 'pic.png', { type: 'image/png' })),
}

/**
 * The request axios was given, with a form written as its entries.
 *
 * @return {Array} The recorded calls.
 */
function sent() {
	return axios.post.mock.calls.map(([url, body, ...rest]) => [
		url,
		body instanceof FormData
			? [...body.entries()].map(([k, v]) => [k, v.name, v.size, v.type])
			: body,
		...rest,
	])
}

/**
 * Run one case and record what it resolved to, or the error it rejected
 * with, and the request it sent.
 *
 * @param {() => Promise<object>} call The upload to run.
 * @return {Promise<object>} The outcome.
 */
async function outcome(call) {
	try {
		return { result: await call(), sent: sent() }
	} catch (error) {
		return {
			rejected: {
				name: error.name,
				code: error.code,
				message: error.message,
				httpStatus: error.httpStatus,
			},
			sent: sent(),
		}
	}
}

describe('resource uploads (golden)', () => {
	it('matches the recorded results', async () => {
		const actual = {}
		for (const [c, call] of Object.entries(CALLS)) {
			for (const [a, answer] of Object.entries(ANSWERS)) {
				axios.post.mockReset()
				axios.post.mockResolvedValue(answer)
				actual[`${c} | ${a}`] = await outcome(call)
			}
			for (const [f, failure] of Object.entries(FAILURES)) {
				axios.post.mockReset()
				axios.post.mockRejectedValue(failure)
				actual[`${c} | ${f}`] = await outcome(call)
			}
		}
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		for (const key of Object.keys(expected)) {
			expect(actual[key], key).toEqual(expected[key])
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
	})
})
