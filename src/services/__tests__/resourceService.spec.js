/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `src/services/resourceService.js`. Confirms the wrapper:
 *
 *  - posts `{base64: <dataUrl>}` to `/apps/launchpad/api/resources`
 *  - returns the success envelope's `{url, name, size}` shape
 *  - rethrows the server's `error` enum + `message` as a `ResourceUploadError`
 *  - falls back to a `network_error` code when the network call fails
 *  - returns `unknown_error` when the body is malformed
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `http://localhost${path}`,
}))

const postMock = vi.fn()
vi.mock('@nextcloud/axios', () => ({
	default: { post: (...args) => postMock(...args) },
}))

let uploadDataUrl
let uploadFile
let ResourceUploadError

beforeEach(async () => {
	postMock.mockReset()
	const mod = await import('../resourceService.js')
	uploadDataUrl = mod.uploadDataUrl
	uploadFile = mod.uploadFile
	ResourceUploadError = mod.ResourceUploadError
})

describe('resourceService.uploadDataUrl', () => {
	it('posts the data URL and returns {url, name, size} on success', async () => {
		postMock.mockResolvedValueOnce({
			status: 200,
			data: {
				status: 'success',
				url: '/apps/launchpad/resource/resource_abc.png',
				name: 'resource_abc.png',
				size: 1234,
			},
		})

		const result = await uploadDataUrl('data:image/png;base64,xxx')

		expect(postMock).toHaveBeenCalledWith(
			'http://localhost/apps/launchpad/api/resources',
			{ base64: 'data:image/png;base64,xxx' },
		)
		expect(result).toEqual({
			url: '/apps/launchpad/resource/resource_abc.png',
			name: 'resource_abc.png',
			size: 1234,
		})
	})

	it('throws ResourceUploadError carrying the stable code on server error envelope', async () => {
		const err = new Error('Request failed')
		err.response = {
			status: 400,
			data: {
				status: 'error',
				error: 'file_too_large',
				message: 'Maximum size is 5MB',
			},
		}
		postMock.mockRejectedValueOnce(err)

		await expect(uploadDataUrl('data:image/png;base64,xxx')).rejects
			.toMatchObject({
				name: 'ResourceUploadError',
				code: 'file_too_large',
				message: 'Maximum size is 5MB',
				httpStatus: 400,
			})
	})

	it('throws network_error when the transport itself fails', async () => {
		postMock.mockRejectedValueOnce(new Error('boom'))

		try {
			await uploadDataUrl('data:image/png;base64,xxx')
			throw new Error('should have thrown')
		} catch (e) {
			expect(e).toBeInstanceOf(ResourceUploadError)
			expect(e.code).toBe('network_error')
		}
	})

	it('throws unknown_error when server returns 200 with malformed body', async () => {
		postMock.mockResolvedValueOnce({ status: 200, data: { status: 'success' } })

		await expect(uploadDataUrl('data:image/png;base64,xxx')).rejects
			.toMatchObject({ code: 'unknown_error' })
	})
})

describe('resourceService.uploadFile', () => {
	it('posts multipart FormData (no base64) and returns {url, name, size}', async () => {
		postMock.mockResolvedValueOnce({
			status: 200,
			data: {
				status: 'success',
				url: '/apps/launchpad/resource/resource_abc.png',
				name: 'resource_abc.png',
				size: 4321,
			},
		})

		const file = new File(['bytes'], 'photo.png', { type: 'image/png' })
		const result = await uploadFile(file)

		expect(postMock).toHaveBeenCalledTimes(1)
		const [calledUrl, body] = postMock.mock.calls[0]
		expect(calledUrl).toBe('http://localhost/apps/launchpad/api/resources/upload')
		expect(body).toBeInstanceOf(FormData)
		expect(body.get('file')).toBe(file)
		// The transport returns the server's logical path unchanged; the renderer
		// resolves it via generateUrl() at display time.
		expect(result).toEqual({
			url: '/apps/launchpad/resource/resource_abc.png',
			name: 'resource_abc.png',
			size: 4321,
		})
	})

	it('rethrows the server error envelope as a ResourceUploadError', async () => {
		const err = new Error('Request failed')
		err.response = {
			status: 400,
			data: { status: 'error', error: 'invalid_image_format', message: 'Bad type' },
		}
		postMock.mockRejectedValueOnce(err)

		await expect(uploadFile(new File(['x'], 'a.bmp'))).rejects
			.toMatchObject({ name: 'ResourceUploadError', code: 'invalid_image_format', httpStatus: 400 })
	})

	it('throws network_error when the transport itself fails', async () => {
		postMock.mockRejectedValueOnce(new Error('boom'))

		await expect(uploadFile(new File(['x'], 'a.png'))).rejects
			.toMatchObject({ code: 'network_error' })
	})
})
