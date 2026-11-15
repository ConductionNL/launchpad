/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `DashboardConfigModal.vue` behaviour beyond the tab
 * split: seeding the form on open, the buffered user/group share list and
 * when Save replaces it on the server, sharee search, public links, the save
 * payload with its default-pin event, and delete.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import DashboardConfigModal from '../DashboardConfigModal.vue'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (p, params = {}) =>
		'/index.php' + p.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))

vi.mock('../../services/api.js', () => ({
	api: {
		listShares: vi.fn(),
		searchSharees: vi.fn(),
		replaceShares: vi.fn(),
	},
}))
vi.mock('../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const store = vi.hoisted(() => ({
	fetchShares: vi.fn(),
	createShare: vi.fn(),
	revokeShare: vi.fn(),
}))
vi.mock('../../stores/publicShares.js', () => ({
	usePublicShareStore: () => store,
}))

vi.mock('@nextcloud/vue', () => {
	const passthrough = (tag, cls) => ({
		render() {
			return h(tag, { class: cls }, this.$slots.default?.())
		},
	})
	return {
		NcModal: passthrough('div', 'nc-modal-stub'),
		NcButton: passthrough('button', 'nc-button-stub'),
		NcTextField: { render: () => h('input') },
		NcSelect: passthrough('div', 'nc-select-stub'),
		NcCheckboxRadioSwitch: passthrough('label', 'nc-switch-stub'),
	}
})

const SHARES = [
	{ shareType: 'user', shareWith: 'alice', permissionLevel: 'view_only', displayName: 'Alice' },
	{ shareType: 'group', shareWith: 'sales', permissionLevel: 'full', displayName: 'Sales' },
]

const SHAREES = {
	data: {
		users: [{ id: 'bob', displayName: 'Bob' }],
		groups: [{ id: 'ops', displayName: 'Ops' }],
	},
}

function dashboard (extra = {}) {
  return {
	id: 3,
	uuid: 'uuid-3',
	name: '  Marketing ',
	description: ' Team board ',
	slug: 'marketing',
	parentUuid: 'uuid-1',
	icon: 'ViewDashboard',
	isOwner: true,
	permissionLevel: 'add_only',
	...extra,
}
}

function mountModal(props = {}) {
	return mount(DashboardConfigModal, {
		propsData: {
			open: true,
			dashboard: dashboard(),
			mode: 'edit',
			parentOptions: [{ uuid: 'uuid-1', name: 'Root' }],
			...props,
		},
		stubs: {
			DetailsTab: true,
			LanguagesTab: true,
			CnIconBrowser: true,
		},
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
	api.listShares.mockResolvedValue({ data: SHARES.map((s) => ({ ...s })) })
	api.searchSharees.mockResolvedValue(SHAREES)
	api.replaceShares.mockResolvedValue({ data: {} })
	store.fetchShares.mockResolvedValue([{ id: 1, token: 'tok1' }])
	store.createShare.mockResolvedValue({})
	store.revokeShare.mockResolvedValue({})
})

describe('DashboardConfigModal: opening', () => {
	it('seeds the form from the dashboard and loads shares, suggestions and links', async () => {
		const wrapper = mountModal({ defaultUuid: 'uuid-3' })
		await flushPromises()

		expect(wrapper.vm.form.name).toBe('  Marketing ')
		expect(wrapper.vm.form.slug).toBe('marketing')
		expect(wrapper.vm.form.parent).toEqual({ uuid: 'uuid-1', name: 'Root' })
		expect(wrapper.vm.form.isDefault).toBe(true)
		expect(api.listShares).toHaveBeenCalledWith(3)
		expect(api.searchSharees).toHaveBeenCalledWith('')
		expect(store.fetchShares).toHaveBeenCalledWith('uuid-3')
		expect(wrapper.vm.localShares).toEqual(SHARES)
		expect(wrapper.vm.shareeOptions.map((o) => o.key)).toEqual(['user:bob', 'group:ops'])
		expect(wrapper.vm.publicShares).toEqual([{ id: 1, token: 'tok1' }])
		expect(wrapper.vm.sharesDirty).toBe(false)
	})

	it('falls back to "No parent" for an unknown parent', () => {
		const wrapper = mountModal({ dashboard: dashboard({ parentUuid: 'gone' }) })
		expect(wrapper.vm.form.parent).toEqual({ uuid: '', name: 'No parent' })
	})

	it('does not load shares for a dashboard the user does not own', async () => {
		const wrapper = mountModal({ dashboard: dashboard({ isOwner: false }) })
		await flushPromises()

		expect(wrapper.vm.canManageShares).toBe(false)
		expect(api.listShares).not.toHaveBeenCalled()
		expect(store.fetchShares).not.toHaveBeenCalled()
		expect(wrapper.vm.tabs.map((tab) => tab.id)).toEqual(['general'])
	})

	it('starts empty on create, titled and labelled for creating', () => {
		const wrapper = mountModal({ mode: 'create', dashboard: null })

		expect(wrapper.vm.form.name).toBe('')
		expect(wrapper.vm.form.isDefault).toBe(false)
		expect(wrapper.vm.modalTitle).toBe('Create dashboard')
		expect(wrapper.vm.primaryButtonLabel).toBe('Create')
		expect(api.listShares).not.toHaveBeenCalled()
	})

	it('labels the primary button while saving', async () => {
		const wrapper = mountModal()
		expect(wrapper.vm.modalTitle).toBe('Dashboard configuration')
		expect(wrapper.vm.primaryButtonLabel).toBe('Save')
		wrapper.vm.saving = true
		expect(wrapper.vm.primaryButtonLabel).toBe('Saving…')
		await wrapper.setProps({ mode: 'create' })
		expect(wrapper.vm.primaryButtonLabel).toBe('Creating…')
	})

	it('shows the dashboard permission level, defaulting to full access', () => {
		expect(mountModal().vm.selectedPermission.value).toBe('add_only')
		expect(mountModal({ dashboard: dashboard({ permissionLevel: undefined }) }).vm.selectedPermission.value).toBe('full')
		expect(mountModal({ dashboard: dashboard({ permissionLevel: 'odd' }) }).vm.selectedPermission.value).toBe('full')
	})

	it('maps a share level to its option, unknown levels to view only', () => {
		const wrapper = mountModal()
		expect(wrapper.vm.permissionOptionFor('full')).toEqual({ value: 'full', label: 'Full access' })
		expect(wrapper.vm.permissionOptionFor('nope')).toEqual({ value: 'view_only', label: 'View only' })
	})

	it('clears the share state when closed', async () => {
		const wrapper = mountModal()
		await flushPromises()
		wrapper.vm.newSharePassword = 'secret'

		await wrapper.setProps({ open: false })

		expect(wrapper.vm.localShares).toEqual([])
		expect(wrapper.vm.serverShares).toEqual([])
		expect(wrapper.vm.publicShares).toEqual([])
		expect(wrapper.vm.newSharePassword).toBe('')
	})

	it('logs and empties the lists when loading fails', async () => {
		api.listShares.mockRejectedValue(new Error('a'))
		api.searchSharees.mockRejectedValue(new Error('b'))
		store.fetchShares.mockRejectedValue(new Error('c'))
		const wrapper = mountModal()
		await flushPromises()

		expect(wrapper.vm.localShares).toEqual([])
		expect(wrapper.vm.shareeSuggestions).toEqual([])
		expect(wrapper.vm.publicShares).toEqual([])
		expect(wrapper.vm.publicSharesLoading).toBe(false)
		expect(logger.error.mock.calls.map((c) => c[0])).toEqual([
			'Failed to load shares:',
			'Sharee suggestion preload failed:',
			'Failed to load public shares:',
		])
	})
})

describe('DashboardConfigModal: user and group shares', () => {
	it('buffers a picked sharee once, as view only', async () => {
		const wrapper = mountModal()
		await flushPromises()
		const bob = { shareType: 'user', id: 'bob', displayName: 'Bob' }

		wrapper.vm.onShareeSelected(bob)
		wrapper.vm.onShareeSelected(bob)
		wrapper.vm.onShareeSelected(null)

		expect(wrapper.vm.localShares).toHaveLength(3)
		expect(wrapper.vm.localShares[2]).toEqual({
			shareType: 'user',
			shareWith: 'bob',
			permissionLevel: 'view_only',
			displayName: 'Bob',
		})
		expect(wrapper.vm.sharesDirty).toBe(true)
		expect(api.replaceShares).not.toHaveBeenCalled()
	})

	it('changes and removes buffered shares', async () => {
		const wrapper = mountModal()
		await flushPromises()

		wrapper.vm.onShareLevelChange(0, null)
		wrapper.vm.onShareLevelChange(5, { value: 'full' })
		wrapper.vm.onShareLevelChange(0, { value: 'view_only' })
		expect(wrapper.vm.sharesDirty).toBe(false)

		wrapper.vm.onShareLevelChange(0, { value: 'full' })
		expect(wrapper.vm.localShares[0].permissionLevel).toBe('full')
		expect(wrapper.vm.serverShares[0].permissionLevel).toBe('view_only')
		expect(wrapper.vm.sharesDirty).toBe(true)

		wrapper.vm.onShareRemove(1)
		expect(wrapper.vm.localShares.map((s) => s.shareWith)).toEqual(['alice'])
	})

	it('searches sharees for two or more characters only', async () => {
		const wrapper = mountModal()
		await flushPromises()
		api.searchSharees.mockClear()
		api.searchSharees.mockResolvedValue({ data: { users: [{ id: 'carol', displayName: 'Carol' }] } })

		await wrapper.vm.onShareeSearch('c')
		expect(api.searchSharees).not.toHaveBeenCalled()

		await wrapper.vm.onShareeSearch('  ca ')
		expect(api.searchSharees).toHaveBeenCalledWith('ca')
		expect(wrapper.vm.shareeOptions).toEqual([
			{ key: 'user:carol', shareType: 'user', id: 'carol', displayName: 'Carol' },
		])
		expect(wrapper.vm.shareeLoading).toBe(false)

		await wrapper.vm.onShareeSearch('')
		expect(wrapper.vm.shareeOptions.map((o) => o.key)).toEqual(['user:bob', 'group:ops'])
	})

	it('drops a stale search result', async () => {
		const wrapper = mountModal()
		await flushPromises()
		let resolveFirst
		api.searchSharees
			.mockReturnValueOnce(new Promise((resolve) => { resolveFirst = resolve }))
			.mockResolvedValueOnce({ data: { users: [{ id: 'dave', displayName: 'Dave' }] } })

		const first = wrapper.vm.onShareeSearch('al')
		await wrapper.vm.onShareeSearch('da')
		resolveFirst({ data: { users: [{ id: 'alice', displayName: 'Alice' }] } })
		await first

		expect(wrapper.vm.shareeOptions.map((o) => o.id)).toEqual(['dave'])
	})

	it('empties the options when a search fails', async () => {
		const wrapper = mountModal()
		await flushPromises()
		api.searchSharees.mockRejectedValue(new Error('x'))

		await wrapper.vm.onShareeSearch('zz')

		expect(wrapper.vm.shareeOptions).toEqual([])
		expect(logger.error).toHaveBeenCalledWith('Sharee search failed:', expect.any(Error))
	})
})

describe('DashboardConfigModal: public links', () => {
	it('creates a link with password and end-of-day expiry, then reloads', async () => {
		const wrapper = mountModal()
		await flushPromises()
		store.fetchShares.mockClear()
		wrapper.vm.newSharePassword = 'pw'
		wrapper.vm.newShareExpiry = '2026-12-31'

		await wrapper.vm.onCreatePublicShare()

		expect(store.createShare).toHaveBeenCalledWith('uuid-3', {
			password: 'pw',
			expiresAt: '2026-12-31T23:59:59Z',
		})
		expect(wrapper.vm.newSharePassword).toBe('')
		expect(wrapper.vm.newShareExpiry).toBe('')
		expect(store.fetchShares).toHaveBeenCalledWith('uuid-3')
		expect(wrapper.vm.creatingPublicShare).toBe(false)
	})

	it('creates an open-ended link without password', async () => {
		const wrapper = mountModal()
		await flushPromises()
		await wrapper.vm.onCreatePublicShare()
		expect(store.createShare).toHaveBeenCalledWith('uuid-3', { password: null, expiresAt: null })
	})

	it('keeps the inputs when creating a link fails', async () => {
		store.createShare.mockRejectedValue(new Error('x'))
		const wrapper = mountModal()
		await flushPromises()
		wrapper.vm.newSharePassword = 'pw'

		await wrapper.vm.onCreatePublicShare()

		expect(wrapper.vm.newSharePassword).toBe('pw')
		expect(wrapper.vm.creatingPublicShare).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('Failed to create public share:', expect.any(Error))
	})

	it('revokes a link and reloads, logging a failure', async () => {
		const wrapper = mountModal()
		await flushPromises()
		store.fetchShares.mockClear()

		await wrapper.vm.onRevokePublicShare(1)
		expect(store.revokeShare).toHaveBeenCalledWith('uuid-3', 1)
		expect(store.fetchShares).toHaveBeenCalledTimes(1)

		store.revokeShare.mockRejectedValue(new Error('x'))
		await wrapper.vm.onRevokePublicShare(2)
		expect(logger.error).toHaveBeenCalledWith('Failed to revoke public share:', expect.any(Error))
	})

	it('does nothing with links for a dashboard without uuid', async () => {
		const wrapper = mountModal({ dashboard: dashboard({ uuid: undefined }) })
		await flushPromises()

		await wrapper.vm.onCreatePublicShare()
		await wrapper.vm.onRevokePublicShare(1)

		expect(store.createShare).not.toHaveBeenCalled()
		expect(store.revokeShare).not.toHaveBeenCalled()
		expect(store.fetchShares).not.toHaveBeenCalled()
		expect(wrapper.vm.publicShares).toEqual([])
	})

	it('builds the public URL and copies it', async () => {
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const wrapper = mountModal()

		expect(wrapper.vm.publicShareUrl('abc')).toBe(`${window.location.origin}/index.php/apps/launchpad/s/abc`)
		await wrapper.vm.copyPublicShareUrl({ token: 'abc' })

		expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/index.php/apps/launchpad/s/abc`)
		expect(wrapper.vm.copiedToken).toBe('abc')

		writeText.mockRejectedValue(new Error('denied'))
		await wrapper.vm.copyPublicShareUrl({ token: 'def' })
		expect(wrapper.vm.copiedToken).toBe('abc')
		expect(logger.error).toHaveBeenCalledWith('Clipboard write failed:', expect.any(Error))
	})
})

describe('DashboardConfigModal: save and delete', () => {
	it('emits the trimmed edit payload without touching unchanged shares', async () => {
		const wrapper = mountModal()
		await flushPromises()

		await wrapper.vm.onSave()

		expect(api.replaceShares).not.toHaveBeenCalled()
		expect(wrapper.emitted('save')).toEqual([[{
			id: 3,
			name: 'Marketing',
			description: 'Team board',
			icon: 'ViewDashboard',
			parentUuid: 'uuid-1',
			slug: 'marketing',
		}]])
		expect(wrapper.emitted('setDefault')).toBeUndefined()
		expect(wrapper.vm.saving).toBe(false)
	})

	it('replaces changed shares before emitting save', async () => {
		const wrapper = mountModal()
		await flushPromises()
		wrapper.vm.onShareRemove(0)

		await wrapper.vm.onSave()

		expect(api.replaceShares).toHaveBeenCalledWith(3, [
			{ shareType: 'group', shareWith: 'sales', permissionLevel: 'full' },
		])
		expect(wrapper.emitted('save')).toHaveLength(1)
	})

	it('still emits save when replacing shares fails', async () => {
		api.replaceShares.mockRejectedValue(new Error('x'))
		const wrapper = mountModal()
		await flushPromises()
		wrapper.vm.onShareRemove(0)

		await wrapper.vm.onSave()

		expect(logger.error).toHaveBeenCalledWith('Failed to replace shares:', expect.any(Error))
		expect(wrapper.emitted('save')).toHaveLength(1)
	})

	it('emits setDefault only when the default switch changed', async () => {
		const wrapper = mountModal({ defaultUuid: 'other' })
		await flushPromises()
		wrapper.vm.form.isDefault = true

		await wrapper.vm.onSave()

		expect(wrapper.emitted('setDefault')).toEqual([[{ uuid: 'uuid-3', isDefault: true }]])
	})

	it('emits a create payload without parent or slug', async () => {
		const wrapper = mountModal({ mode: 'create', dashboard: null })
		wrapper.vm.form.name = ' New '
		wrapper.vm.form.isDefault = true

		await wrapper.vm.onSave()

		const [[payload]] = wrapper.emitted('save')
		expect(payload).toEqual({ id: null, name: 'New', description: '', icon: payload.icon })
		expect(payload.icon).toBeTruthy()
		expect(wrapper.emitted('setDefault')).toBeUndefined()
		expect(api.replaceShares).not.toHaveBeenCalled()
	})

	it('does not save a blank name', async () => {
		const wrapper = mountModal()
		wrapper.vm.form.name = '   '
		expect(wrapper.vm.canSave).toBe(false)

		await wrapper.vm.onSave()

		expect(wrapper.emitted('save')).toBeUndefined()
	})

	it('emits delete with the dashboard only when allowed', () => {
		const denied = mountModal()
		denied.vm.onDelete()
		expect(denied.emitted('delete')).toBeUndefined()

		const allowed = mountModal({ canDelete: true })
		allowed.vm.onDelete()
		expect(allowed.emitted('delete')).toEqual([[allowed.props('dashboard')]])
	})
})
