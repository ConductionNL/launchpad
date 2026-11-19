/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed token manager lists tokens with their status, issues one and
 * shows its JWT once, revokes with a reason and restores, re-issues, and
 * shows the server's refusal (embedded-analytics tasks 15, 27 and 40).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EmbedTokenManager from '../EmbedTokenManager.vue'

const embed = vi.hoisted(() => ({
	listTokens: vi.fn(),
	listThemes: vi.fn(),
	createToken: vi.fn(),
	updateToken: vi.fn(),
	revokeToken: vi.fn(),
	unrevokeToken: vi.fn(),
	reissueToken: vi.fn(),
}))
const dashboards = vi.hoisted(() => ({ getVisibleDashboards: vi.fn() }))
const toasts = vi.hoisted(() => ({ showSuccess: vi.fn() }))
vi.mock('../../../../services/embedApi.js', () => ({ embedApi: embed }))
vi.mock('../../../../services/api.js', () => ({ api: dashboards }))
vi.mock('@nextcloud/dialogs', () => toasts)
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', count),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const Button = { name: 'NcButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' }
const FormStub = { name: 'EmbedTokenForm', props: ['token', 'dashboards', 'themes', 'defaults', 'saving'], emits: ['submit', 'cancel'], template: '<div class="form-stub" />' }
const DialogStub = { name: 'RevokeEmbedTokenDialog', props: ['open', 'name'], emits: ['update:open', 'confirm'], template: '<div class="revoke-stub" />' }

const ACTIVE = { id: 't-1', name: 'Zeist WOO', subject: { type: 'dashboard', id: 'dash-uuid' }, scope: { mode: 'read' }, hostOrigins: ['https://a.nl'], createdAt: '2026-05-22T14:30:00Z' }
const REVOKED = { ...ACTIVE, id: 't-2', name: 'Oud', revokedAt: '2026-05-23T08:00:00Z', revocationReason: 'no_longer_needed' }

function mountManager (props = {}) {
  return mount(EmbedTokenManager, {
	props,
	global: {
		stubs: {
			NcButton: Button,
			NcNoteCard: { props: ['type'], template: '<div :class="\'note-\' + type"><slot /></div>' },
			NcLoadingIcon: true,
			EmbedTokenForm: FormStub,
			EmbedUsageReport: { props: ['tokenId', 'tokenName'], template: '<div class="usage-stub" />' },
			RevokeEmbedTokenDialog: DialogStub,
		},
	},
})
}

describe('EmbedTokenManager', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		embed.listTokens.mockResolvedValue([ACTIVE, REVOKED])
		embed.listThemes.mockResolvedValue([{ id: 'th-1', name: 'Zeist', cssVariables: {} }])
		dashboards.getVisibleDashboards.mockResolvedValue({ data: { items: [{ id: 7, uuid: 'dash-uuid', name: 'Mijn week', extra: 1 }] } })
	})

	it('lists every token with its status and subject', async () => {
		const wrapper = mountManager()
		await flushPromises()

		const rows = wrapper.findAll('tbody tr')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('Zeist WOO')
		expect(rows[0].text()).toContain('Active')
		expect(rows[0].text()).toContain('Dashboard Mijn week')
		expect(rows[1].text()).toContain('Revoked')
	})

	it('says so when there are no tokens, and when they cannot be loaded', async () => {
		embed.listTokens.mockResolvedValueOnce([])
		const empty = mountManager()
		await flushPromises()
		expect(empty.find('[data-test="embed-token-empty"]').exists()).toBe(true)

		embed.listTokens.mockRejectedValueOnce(new Error('503'))
		const failed = mountManager()
		await flushPromises()
		expect(failed.find('[data-test="embed-token-error"]').text()).toBe('Embed tokens could not be loaded.')
	})

	it('issues a token and shows its JWT once, with the keep-safe warning and a copy button', async () => {
		embed.createToken.mockResolvedValue({ id: 't-3', name: 'Nieuw', jwt: 'aaa.bbb.ccc' })
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="embed-token-new"]').trigger('click')
		const form = wrapper.findComponent(FormStub)
		expect(form.props()).toMatchObject({ token: null, dashboards: [{ id: 7, uuid: 'dash-uuid', name: 'Mijn week' }], themes: [{ id: 'th-1', name: 'Zeist' }] })
		form.vm.$emit('submit', { name: 'Nieuw' })
		await flushPromises()

		expect(embed.createToken).toHaveBeenCalledWith({ name: 'Nieuw' })
		expect(wrapper.find('[data-test="embed-token-jwt"]').text()).toBe('aaa.bbb.ccc')
		expect(wrapper.find('[data-test="embed-token-issued"]').text()).toContain('It is shown only once')
		expect(wrapper.findComponent(FormStub).exists()).toBe(false)

		await wrapper.find('[data-test="embed-token-copy"]').trigger('click')
		await flushPromises()
		expect(writeText).toHaveBeenCalledWith('aaa.bbb.ccc')
		expect(wrapper.find('[data-test="embed-token-copy"]').text()).toBe('Copied')

		await wrapper.find('[data-test="embed-token-issued-done"]').trigger('click')
		expect(wrapper.find('[data-test="embed-token-jwt"]').exists()).toBe(false)
	})

	it('shows the server refusal of a create and keeps the form', async () => {
		embed.createToken.mockRejectedValue({ response: { data: { message: 'Wildcard origin \'*\' is not permitted.' } } })
		const wrapper = mountManager()
		await flushPromises()
		await wrapper.find('[data-test="embed-token-new"]').trigger('click')
		wrapper.findComponent(FormStub).vm.$emit('submit', { name: 'x' })
		await flushPromises()

		expect(wrapper.find('[data-test="embed-token-error"]').text()).toBe('Wildcard origin \'*\' is not permitted.')
		expect(wrapper.findComponent(FormStub).exists()).toBe(true)
	})

	it('revokes with the reason and notes from the dialog and shows the new status', async () => {
		embed.revokeToken.mockResolvedValue({ ...ACTIVE, revokedAt: '2026-05-24T10:00:00Z', revocationReason: 'compromised' })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="embed-token-open-t-1"]').trigger('click')
		expect(wrapper.find('[data-test="embed-token-status-active"]').exists()).toBe(true)
		await wrapper.find('[data-test="embed-token-revoke"]').trigger('click')
		const dialog = wrapper.findComponent(DialogStub)
		expect(dialog.props('name')).toBe('Zeist WOO')
		dialog.vm.$emit('confirm', { reason: 'compromised', notes: 'gelekt' })
		await flushPromises()

		expect(embed.revokeToken).toHaveBeenCalledWith('t-1', 'compromised', 'gelekt')
		expect(wrapper.findComponent(DialogStub).exists()).toBe(false)
		expect(wrapper.find('[data-test="embed-token-status-revoked"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="embed-token-unrevoke"]').exists()).toBe(true)
		expect(toasts.showSuccess).toHaveBeenCalledWith('Token revoked.')
	})

	it('restores a revoked token', async () => {
		embed.unrevokeToken.mockResolvedValue({ ...REVOKED, revokedAt: null })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="embed-token-open-t-2"]').trigger('click')
		await wrapper.find('[data-test="embed-token-unrevoke"]').trigger('click')
		await flushPromises()

		expect(embed.unrevokeToken).toHaveBeenCalledWith('t-2')
		expect(wrapper.find('[data-test="embed-token-status-active"]').exists()).toBe(true)
		expect(toasts.showSuccess).toHaveBeenCalledWith('Token restored.')
	})

	it('re-issues: shows the new JWT and reloads the list', async () => {
		embed.reissueToken.mockResolvedValue({ id: 't-9', jwt: 'new.jwt.value' })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="embed-token-open-t-1"]').trigger('click')
		await wrapper.find('[data-test="embed-token-reissue"]').trigger('click')
		await flushPromises()

		expect(embed.reissueToken).toHaveBeenCalledWith('t-1')
		expect(embed.listTokens).toHaveBeenCalledTimes(2)
		expect(wrapper.find('[data-test="embed-token-jwt"]').text()).toBe('new.jwt.value')
		expect(wrapper.find('[data-test="embed-token-table"]').exists()).toBe(true)
	})

	it('edits only through the update call and returns to the detail view', async () => {
		embed.updateToken.mockResolvedValue({ ...ACTIVE, name: 'Zeist WOO 2' })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="embed-token-open-t-1"]').trigger('click')
		await wrapper.find('[data-test="embed-token-edit"]').trigger('click')
		expect(wrapper.findComponent(FormStub).props('token')).toEqual(ACTIVE)
		wrapper.findComponent(FormStub).vm.$emit('submit', { name: 'Zeist WOO 2' })
		await flushPromises()

		expect(embed.updateToken).toHaveBeenCalledWith('t-1', { name: 'Zeist WOO 2' })
		expect(embed.createToken).not.toHaveBeenCalled()
		expect(wrapper.find('[data-test="embed-token-detail"] h4').text()).toBe('Zeist WOO 2')
	})

	it('opens on the create form for a subject handed in from a dashboard', async () => {
		const defaults = { subjectType: 'dashboard', subjectId: 'dash-uuid', dashboardId: 7 }
		const wrapper = mountManager({ defaults })
		await flushPromises()

		expect(wrapper.findComponent(FormStub).props('defaults')).toEqual(defaults)
		expect(wrapper.find('[data-test="embed-token-table"]').exists()).toBe(false)
	})
})
