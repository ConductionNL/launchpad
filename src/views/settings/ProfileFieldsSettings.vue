<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcSettingsSection
		:name="t('launchpad', 'LaunchPad profile')"
		:description="
			t(
				'launchpad',
				'Colleagues see these fields on your card in the people widget and can find you by them.',
			)
		">
		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<NcNoteCard v-else-if="loadError" type="error">
			{{ t('launchpad', 'Your profile fields could not be loaded.') }}
		</NcNoteCard>
		<form v-else class="profile-fields" @submit.prevent="save">
			<div
				v-for="field in fields"
				:key="field.key"
				class="profile-fields__row">
				<template v-if="field.readOnly">
					<span class="profile-fields__label">{{ field.label }}</span>
					<span class="profile-fields__value">
						{{
							field.values.length
								? field.values.join(', ')
								: t('launchpad', 'Not filled in')
						}}
					</span>
					<span class="profile-fields__hint">
						{{
							t(
								'launchpad',
								'Filled from your organisation’s directory. You cannot change it here.',
							)
						}}
					</span>
				</template>
				<NcSelect
					v-else-if="field.type === 'tags'"
					v-model="draft[field.key]"
					:inputLabel="field.label"
					:options="draft[field.key]"
					:placeholder="t('launchpad', 'Type a subject and press Enter')"
					multiple
					taggable
					:pushTags="true" />
				<NcTextField
					v-else
					v-model="draft[field.key]"
					:label="field.label"
					:maxlength="255" />
			</div>
			<NcNoteCard v-if="saveError" type="error">
				{{ saveError }}
			</NcNoteCard>
			<NcNoteCard v-if="saved" type="success">
				{{ t('launchpad', 'Your profile fields are saved.') }}
			</NcNoteCard>
			<NcButton
				v-if="hasEditable"
				type="submit"
				variant="primary"
				:disabled="saving">
				{{ saving ? t('launchpad', 'Saving…') : t('launchpad', 'Save') }}
			</NcButton>
		</form>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcNoteCard,
	NcSelect,
	NcSettingsSection,
	NcTextField,
} from '@nextcloud/vue'

/**
 * ProfileFieldsSettings: the person's own LaunchPad profile fields on the
 * personal settings page (REQ-PEX-002). Text fields are a text box, tags a
 * taggable select; fields read from LDAP show read-only.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
export default {
	name: 'ProfileFieldsSettings',

	components: { NcButton, NcNoteCard, NcSelect, NcSettingsSection, NcTextField },

	data() {
		return {
			fields: [],
			draft: {},
			loading: true,
			loadError: false,
			saving: false,
			saveError: '',
			saved: false,
		}
	},

	computed: {
		/** @spec openspec/specs/people-widget/spec.md */
		hasEditable() {
			return this.fields.some((field) => !field.readOnly)
		},
	},

	/** @spec openspec/specs/people-widget/spec.md */
	async mounted() {
		try {
			const response = await axios.get(
				generateUrl('/apps/launchpad/api/profile-fields/me'),
			)
			this.apply(response?.data?.fields || [])
		} catch {
			this.loadError = true
		} finally {
			this.loading = false
		}
	},

	methods: {
		/**
		 * Take the server's fields and build the editable draft.
		 *
		 * @param {Array<object>} fields Fields with `values` and `readOnly`.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		apply(fields) {
			this.fields = fields
			const draft = {}
			fields.forEach((field) => {
				draft[field.key] =
					field.type === 'tags' ? [...field.values] : field.values[0] || ''
			})
			this.draft = draft
		},

		/**
		 * Save the editable fields.
		 *
		 * @spec openspec/specs/people-widget/spec.md
		 */
		async save() {
			this.saving = true
			this.saveError = ''
			this.saved = false
			const values = {}
			this.fields
				.filter((field) => !field.readOnly)
				.forEach((field) => {
					values[field.key] = this.draft[field.key]
				})
			try {
				const response = await axios.put(
					generateUrl('/apps/launchpad/api/profile-fields/me'),
					{ values },
				)
				this.apply(response?.data?.fields || [])
				this.saved = true
			} catch (err) {
				this.saveError =
					err?.response?.data?.error
					|| t('launchpad', 'Your profile fields could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.profile-fields {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 480px;
}

.profile-fields__row {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.profile-fields__label {
	font-weight: 600;
}

.profile-fields__hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
