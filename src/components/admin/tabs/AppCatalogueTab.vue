<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="app-catalogue-tab" data-testid="app-catalogue-tab">
		<h3>{{ t('launchpad', 'App catalogue') }}</h3>
		<p class="app-catalogue-tab__intro">
			{{
				t(
					'launchpad',
					'The approved apps people can add as a tile from Add. An app without groups is visible to everyone.',
				)
			}}
		</p>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<template v-else>
			<p
				v-if="error"
				class="app-catalogue-tab__error"
				role="alert"
				data-testid="app-catalogue-error">
				{{ error }}
			</p>
			<p v-if="notice" role="status" data-testid="app-catalogue-notice">
				{{ notice }}
			</p>

			<section class="app-catalogue-tab__section" data-testid="app-catalogue-requests">
				<h4>{{ t('launchpad', 'Open requests') }}</h4>
				<p v-if="requests.length === 0" class="app-catalogue-tab__empty">
					{{ t('launchpad', 'There are no open requests.') }}
				</p>
				<ul v-else class="app-catalogue-tab__list">
					<li
						v-for="request in requests"
						:key="request.uuid"
						class="app-catalogue-tab__item"
						data-testid="app-catalogue-request">
						<div class="app-catalogue-tab__item-text">
							<strong>{{ request.appName }}</strong>
							<span>{{ askedBy(request) }}</span>
							<span v-if="request.purpose">{{ request.purpose }}</span>
						</div>
						<form
							v-if="declining === request.uuid"
							class="app-catalogue-tab__decline"
							@submit.prevent="decline(request)">
							<NcTextField
								v-model="declineNote"
								:label="t('launchpad', 'Why is it declined?')"
								:required="true" />
							<div class="app-catalogue-tab__actions">
								<NcButton
									type="submit"
									variant="error"
									:disabled="busy || declineNote.trim() === ''">
									{{ t('launchpad', 'Decline request') }}
								</NcButton>
								<NcButton variant="tertiary" @click="cancelDecline">
									{{ t('launchpad', 'Cancel') }}
								</NcButton>
							</div>
						</form>
						<div v-else class="app-catalogue-tab__actions">
							<NcButton
								variant="primary"
								:disabled="busy"
								@click="startApprove(request)">
								{{ t('launchpad', 'Approve') }}
							</NcButton>
							<NcButton :disabled="busy" @click="startDecline(request)">
								{{ t('launchpad', 'Decline') }}
							</NcButton>
						</div>
					</li>
				</ul>
			</section>

			<section class="app-catalogue-tab__section" data-testid="app-catalogue-entries">
				<h4>{{ t('launchpad', 'Approved apps') }}</h4>
				<p v-if="apps.length === 0" class="app-catalogue-tab__empty">
					{{ t('launchpad', 'The catalogue is empty.') }}
				</p>
				<ul v-else class="app-catalogue-tab__list">
					<li
						v-for="app in apps"
						:key="app.uuid"
						class="app-catalogue-tab__item"
						data-testid="app-catalogue-entry">
						<div class="app-catalogue-tab__item-text">
							<strong>{{ app.name }}</strong>
							<span>{{ app.linkValue }}</span>
							<span data-testid="app-catalogue-entry-groups">
								{{ groupNames(app.targetGroups) }}
							</span>
						</div>
						<div class="app-catalogue-tab__actions">
							<NcButton :disabled="busy" @click="startEdit(app)">
								{{ t('launchpad', 'Edit') }}
							</NcButton>
							<NcButton
								variant="tertiary"
								:disabled="busy"
								@click="remove(app)">
								{{ t('launchpad', 'Remove') }}
							</NcButton>
						</div>
					</li>
				</ul>
				<NcButton
					v-if="!form"
					data-testid="app-catalogue-add"
					@click="startCreate">
					{{ t('launchpad', 'Add app') }}
				</NcButton>
			</section>

			<form
				v-if="form"
				class="app-catalogue-tab__form"
				data-testid="app-catalogue-form"
				@submit.prevent="save">
				<h4>{{ formTitle }}</h4>
				<NcTextField
					v-model="form.name"
					:label="t('launchpad', 'Name')"
					:required="true" />
				<NcTextField
					v-model="form.description"
					:label="t('launchpad', 'Short description')" />
				<NcSelect
					v-model="form.linkType"
					:inputLabel="t('launchpad', 'Address type')"
					:options="linkTypeOptions"
					:reduce="(option) => option.id"
					label="label"
					:clearable="false" />
				<NcTextField
					v-model="form.linkValue"
					:label="
						form.linkType === 'app'
							? t('launchpad', 'Nextcloud app id')
							: t('launchpad', 'Web address')
					"
					:required="true" />
				<NcTextField
					v-model="form.icon"
					:label="t('launchpad', 'Icon (icon class or image address)')" />
				<NcSelect
					v-model="form.targetGroups"
					:inputLabel="t('launchpad', 'Visible to groups')"
					:placeholder="t('launchpad', 'Everyone')"
					:options="groupOptions"
					:reduce="(option) => option.id"
					label="displayName"
					:multiple="true" />
				<div class="app-catalogue-tab__actions">
					<NcButton
						type="submit"
						variant="primary"
						:disabled="busy || form.name.trim() === '' || form.linkValue.trim() === ''">
						{{ t('launchpad', 'Save app') }}
					</NcButton>
					<NcButton variant="tertiary" @click="closeForm">
						{{ t('launchpad', 'Cancel') }}
					</NcButton>
				</div>
			</form>

			<section class="app-catalogue-tab__section" data-testid="app-catalogue-manager">
				<h4>{{ t('launchpad', 'Who decides on requests') }}</h4>
				<p class="app-catalogue-tab__empty">
					{{
						t(
							'launchpad',
							'Administrators always hear about new requests. Members of this group do too.',
						)
					}}
				</p>
				<NcSelect
					v-model="managerGroup"
					:inputLabel="t('launchpad', 'Catalogue manager group')"
					:placeholder="t('launchpad', 'None')"
					:options="groupOptions"
					:reduce="(option) => option.id"
					label="displayName"
					@update:modelValue="saveManagerGroup" />
			</section>
		</template>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcSelect, NcTextField } from '@nextcloud/vue'

const BASE = '/apps/launchpad/api/admin/catalogue'

/**
 * An empty catalogue form, optionally prefilled with a name.
 *
 * @param {string} name The name to start with.
 * @return {object} The form fields.
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
 */
function emptyForm(name = '') {
	return {
		name,
		description: '',
		icon: '',
		linkType: 'url',
		linkValue: '',
		targetGroups: [],
	}
}

/**
 * AppCatalogueTab: the administrator keeps the catalogue of approved apps
 * (REQ-ACAT-001) and decides on the requests for missing ones
 * (REQ-ACAT-004). Approving opens the form prefilled with the requested
 * name; saving it creates the entry and links the request to it.
 * Declining asks for a note first.
 *
 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md
 */
export default {
	name: 'AppCatalogueTab',

	components: { NcButton, NcSelect, NcTextField },

	props: {
		/** The server's groups, as `{ id, displayName }`. */
		groups: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			apps: [],
			requests: [],
			managerGroup: null,
			loading: true,
			busy: false,
			error: '',
			notice: '',
			// The open form: null, or the fields plus how it saves.
			form: null,
			// 'create', 'edit' (with formUuid) or 'approve' (with formUuid).
			formMode: 'create',
			formUuid: '',
			declining: '',
			declineNote: '',
		}
	},

	computed: {
		/**
		 * Group picker options; plain group ids are accepted too.
		 *
		 * @return {Array<{id: string, displayName: string}>}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		groupOptions() {
			return this.groups.map((group) =>
				typeof group === 'string'
					? { id: group, displayName: group }
					: { id: group.id, displayName: group.displayName || group.id },
			)
		},

		/**
		 * The two kinds of address an entry can have.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		linkTypeOptions() {
			return [
				{ id: 'url', label: t('launchpad', 'Web address') },
				{ id: 'app', label: t('launchpad', 'Nextcloud app') },
			]
		},

		/**
		 * The heading of the open form.
		 *
		 * @return {string}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		formTitle() {
			if (this.formMode === 'approve') {
				return t('launchpad', 'Approve request')
			}
			return this.formMode === 'edit'
				? t('launchpad', 'Edit app')
				: t('launchpad', 'Add app')
		},
	},

	/** @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8 */
	async mounted() {
		await this.load()
	},

	methods: {
		/** @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8 */
		async load() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl(BASE))
				this.apps = response?.data?.apps || []
				this.requests = response?.data?.requests || []
				this.managerGroup = response?.data?.managerGroup || null
			} catch {
				this.error = t('launchpad', 'The app catalogue could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * The names of an entry's groups, or "Everyone" without any.
		 *
		 * @param {Array<string>} ids Group ids.
		 * @return {string}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		groupNames(ids) {
			if (!Array.isArray(ids) || ids.length === 0) {
				return t('launchpad', 'Everyone')
			}
			return ids
				.map((id) => this.groupOptions.find((group) => group.id === id)?.displayName || id)
				.join(', ')
		},

		/**
		 * Who asked for an app.
		 *
		 * @param {object} request The open request.
		 * @return {string}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		askedBy(request) {
			return t('launchpad', 'Asked by {user}', { user: request.requestedBy })
		},

		/** @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8 */
		startCreate() {
			this.openForm('create', '', emptyForm())
		},

		/**
		 * Open the form on an existing entry.
		 *
		 * @param {object} app The entry.
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		startEdit(app) {
			this.openForm('edit', app.uuid, {
				name: app.name || '',
				description: app.description || '',
				icon: app.icon || '',
				linkType: app.linkType === 'app' ? 'app' : 'url',
				linkValue: app.linkValue || '',
				targetGroups: [...(app.targetGroups || [])],
			})
		},

		/**
		 * Approving opens the form prefilled with the requested name.
		 *
		 * @param {object} request The open request.
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
		 */
		startApprove(request) {
			this.cancelDecline()
			this.openForm('approve', request.uuid, emptyForm(request.appName || ''))
		},

		/**
		 * @param {string} mode create, edit or approve.
		 * @param {string} uuid The entry or request it saves to.
		 * @param {object} fields The starting fields.
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		openForm(mode, uuid, fields) {
			this.formMode = mode
			this.formUuid = uuid
			this.form = fields
			this.error = ''
			this.notice = ''
		},

		/** @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8 */
		closeForm() {
			this.form = null
			this.formUuid = ''
			this.formMode = 'create'
		},

		/** @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001 */
		async save() {
			if (!this.form) {
				return
			}
			const body = {
				name: this.form.name.trim(),
				description: this.form.description.trim(),
				icon: this.form.icon.trim(),
				iconType: /^https?:\/\//i.test(this.form.icon.trim()) ? 'url' : 'class',
				linkType: this.form.linkType,
				linkValue: this.form.linkValue.trim(),
				targetGroups: this.form.targetGroups,
			}
			this.busy = true
			this.error = ''
			this.notice = ''
			try {
				if (this.formMode === 'approve') {
					const response = await axios.post(
						generateUrl(`${BASE}/requests/${this.formUuid}/approve`),
						body,
					)
					this.requests = this.requests.filter((request) => request.uuid !== this.formUuid)
					if (response?.data?.app) {
						this.apps.push(response.data.app)
					}
					this.notice = t('launchpad', '{name} is now in the app catalogue', { name: body.name })
				} else if (this.formMode === 'edit') {
					const response = await axios.put(generateUrl(`${BASE}/${this.formUuid}`), body)
					const saved = response?.data?.app
					this.apps = this.apps.map((app) =>
						app.uuid === this.formUuid && saved ? saved : app,
					)
					this.notice = t('launchpad', 'App saved.')
				} else {
					const response = await axios.post(generateUrl(BASE), body)
					if (response?.data?.app) {
						this.apps.push(response.data.app)
					}
					this.notice = t('launchpad', 'App saved.')
				}
				this.closeForm()
			} catch (err) {
				this.error = this.failure(err, t('launchpad', 'The app could not be saved.'))
			} finally {
				this.busy = false
			}
		},

		/**
		 * @param {object} app The entry to remove.
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
		 */
		async remove(app) {
			this.busy = true
			this.error = ''
			this.notice = ''
			try {
				await axios.delete(generateUrl(`${BASE}/${app.uuid}`))
				this.apps = this.apps.filter((entry) => entry.uuid !== app.uuid)
				if (this.formMode === 'edit' && this.formUuid === app.uuid) {
					this.closeForm()
				}
			} catch (err) {
				this.error = this.failure(err, t('launchpad', 'The app could not be removed.'))
			} finally {
				this.busy = false
			}
		},

		/**
		 * @param {object} request The request to decline.
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		startDecline(request) {
			if (this.formMode === 'approve') {
				this.closeForm()
			}
			this.declining = request.uuid
			this.declineNote = ''
		},

		/** @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8 */
		cancelDecline() {
			this.declining = ''
			this.declineNote = ''
		},

		/**
		 * Decline a request; the note is required and goes to the requester.
		 *
		 * @param {object} request The open request.
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
		 */
		async decline(request) {
			const note = this.declineNote.trim()
			if (note === '') {
				return
			}
			this.busy = true
			this.error = ''
			this.notice = ''
			try {
				await axios.post(generateUrl(`${BASE}/requests/${request.uuid}/decline`), { note })
				this.requests = this.requests.filter((entry) => entry.uuid !== request.uuid)
				this.cancelDecline()
				this.notice = t('launchpad', 'The request was declined.')
			} catch (err) {
				this.error = this.failure(err, t('launchpad', 'The request could not be declined.'))
			} finally {
				this.busy = false
			}
		},

		/**
		 * Store the optional manager group; clearing it stores none.
		 *
		 * @param {string|null} groupId The group, or null.
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		async saveManagerGroup(groupId) {
			this.error = ''
			try {
				const response = await axios.put(generateUrl(`${BASE}/settings`), {
					managerGroup: groupId || '',
				})
				this.managerGroup = response?.data?.managerGroup || null
			} catch (err) {
				this.error = this.failure(err, t('launchpad', 'The manager group could not be saved.'))
			}
		},

		/**
		 * The message for a failed call: a decided request has its own text.
		 *
		 * @param {object} err The axios error.
		 * @param {string} fallback The translated fallback.
		 * @return {string}
		 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-8
		 */
		failure(err, fallback) {
			if (err?.response?.status === 409) {
				return t('launchpad', 'Someone already decided on this request.')
			}
			return fallback
		},
	},
}
</script>

<style scoped>
.app-catalogue-tab {
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-width: 760px;
}

.app-catalogue-tab__section,
.app-catalogue-tab__form,
.app-catalogue-tab__decline {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.app-catalogue-tab__form {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.app-catalogue-tab__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.app-catalogue-tab__item {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 8px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.app-catalogue-tab__item-text {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
	overflow-wrap: anywhere;
}

.app-catalogue-tab__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.app-catalogue-tab__intro,
.app-catalogue-tab__empty {
	color: var(--color-text-maxcontrast);
}

.app-catalogue-tab__error {
	color: var(--color-error-text);
}
</style>
