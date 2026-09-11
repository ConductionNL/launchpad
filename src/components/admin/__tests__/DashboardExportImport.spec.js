/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `DashboardExportImport.vue`: what the admin is told when an
 * import is refused (REQ-EXIM-008, REQ-EXIM-009).
 *
 * The server names the reason an archive was rejected: `manifest.json not found
 * in archive`, `Uploaded file is not a valid ZIP archive`, or the schema-version
 * message that tells the admin to upgrade LaunchPad. The component used to
 * discard all of it and render "Import failed. Please try again.", so the one
 * instruction REQ-EXIM-009 asks for could never reach the person who needed it.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const importDashboards = vi.fn()
vi.mock('../../../services/api.js', () => ({
	api: {
		exportDashboards: vi.fn(),
		importDashboards: (...args) => importDashboards(...args),
	},
}))

import DashboardExportImport from '../DashboardExportImport.vue'

/** Mount the component with a file already chosen. */
function mountWithFile() {
	const wrapper = mount(DashboardExportImport, {
		global: {
			// The suite-wide `t` stub returns the key and drops the variables,
			// which would hide whether the reason is interpolated at all.
			mocks: {
				t: (_app, source, vars) =>
					source.replace(
						/\{(\w+)\}/g,
						(_m, key) => vars?.[key] ?? `{${key}}`,
					),
			},
			stubs: {
				NcButton: { template: '<button><slot /></button>' },
				NcCheckboxRadioSwitch: { template: '<label><slot /></label>' },
				Download: true,
				Upload: true,
			},
		},
	})
	wrapper.vm.selectedFile = new File(['zip'], 'archive.zip')
	return wrapper
}

/** An axios-shaped rejection carrying a response body. */
function rejectionWith(data) {
	return Object.assign(new Error('Request failed'), { response: { data } })
}

describe('DashboardExportImport — a refused import', () => {
	beforeEach(() => {
		importDashboards.mockReset()
	})

	it("shows the server's reason, so the admin learns what to fix", async () => {
		importDashboards.mockRejectedValue(
			rejectionWith({ error: 'manifest.json not found in archive' }),
		)

		const wrapper = mountWithFile()
		await wrapper.vm.runImport()

		expect(wrapper.vm.importError).toBe(
			'Import failed: manifest.json not found in archive',
		)
		expect(wrapper.text()).toContain('manifest.json not found in archive')
	})

	it('passes the upgrade instruction through for a newer archive', async () => {
		const reason =
			'Unsupported manifest schema version: 2. Only version 1 is supported. '
			+ 'Upgrade LaunchPad to import archives of version 2.'
		importDashboards.mockRejectedValue(rejectionWith({ error: reason }))

		const wrapper = mountWithFile()
		await wrapper.vm.runImport()

		expect(wrapper.text()).toContain('Upgrade LaunchPad')
	})

	it('falls back to the generic message when the server names no reason', async () => {
		importDashboards.mockRejectedValue(rejectionWith(undefined))

		const wrapper = mountWithFile()
		await wrapper.vm.runImport()

		expect(wrapper.vm.importError).toBe('Import failed. Please try again.')
	})

	it('still renders a per-dashboard error list rather than the banner', async () => {
		// A 409 collision carries `errors`, which belongs in the result list.
		importDashboards.mockRejectedValue(
			rejectionWith({
				importedDashboardCount: 0,
				skippedDashboardCount: 0,
				errors: [{ type: 'uuidCollision', message: 'Dashboard exists' }],
			}),
		)

		const wrapper = mountWithFile()
		await wrapper.vm.runImport()

		expect(wrapper.vm.importError).toBe('')
		expect(wrapper.vm.importResult.errors).toHaveLength(1)
	})
})
