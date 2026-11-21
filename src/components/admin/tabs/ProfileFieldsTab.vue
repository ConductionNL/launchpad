<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="profile-fields-tab" data-testid="profile-fields-tab">
		<h3>{{ t('launchpad', 'Profile fields') }}</h3>
		<p class="profile-fields-tab__intro">
			{{
				t(
					'launchpad',
					'Extra fields on colleague cards in the people widget, such as office location or expertise. People fill them in on their personal settings, or they come from an LDAP attribute.',
				)
			}}
		</p>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<form v-else class="profile-fields-tab__form" @submit.prevent="save">
			<fieldset
				v-for="(field, index) in fields"
				:key="index"
				class="profile-fields-tab__field">
				<legend>{{ field.label || t('launchpad', 'New field') }}</legend>
				<label>
					{{ t('launchpad', 'Label') }}
					<input
						v-model="field.label"
						type="text"
						maxlength="64"
						required />
				</label>
				<label>
					{{ t('launchpad', 'Key') }}
					<input
						v-model="field.key"
						type="text"
						maxlength="32"
						pattern="[a-z][a-z0-9_]*"
						required />
				</label>
				<label>
					{{ t('launchpad', 'Type') }}
					<select v-model="field.type">
						<option value="text">{{ t('launchpad', 'Text') }}</option>
						<option value="tags">{{ t('launchpad', 'Tags') }}</option>
					</select>
				</label>
				<label>
					{{ t('launchpad', 'Filled by') }}
					<select v-model="field.source">
						<option value="self">
							{{ t('launchpad', 'The person') }}
						</option>
						<option value="ldap">
							{{ t('launchpad', 'LDAP attribute') }}
						</option>
					</select>
				</label>
				<label v-if="field.source === 'ldap'">
					{{ t('launchpad', 'LDAP attribute') }}
					<input
						v-model="field.ldapAttribute"
						type="text"
						placeholder="physicalDeliveryOfficeName"
						required />
				</label>
				<label>
					{{ t('launchpad', 'Who may see it') }}
					<select v-model="field.visibility">
						<option value="everyone">
							{{ t('launchpad', 'Everyone') }}
						</option>
						<option value="groups">
							{{ t('launchpad', 'People in the same group') }}
						</option>
					</select>
				</label>
				<label class="profile-fields-tab__check">
					<input v-model="field.searchable" type="checkbox" />
					{{ t('launchpad', 'Searchable in the people widget') }}
				</label>
				<label class="profile-fields-tab__check">
					<input v-model="field.shownInWidget" type="checkbox" />
					{{ t('launchpad', 'Shown on the card') }}
				</label>
				<NcButton variant="tertiary" @click="remove(index)">
					{{ t('launchpad', 'Remove field') }}
				</NcButton>
			</fieldset>

			<p v-if="error" class="profile-fields-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ t('launchpad', 'Profile fields saved.') }}
			</p>

			<div class="profile-fields-tab__actions">
				<NcButton :disabled="fields.length >= maxFields" @click="add">
					{{ t('launchpad', 'Add field') }}
				</NcButton>
				<NcButton type="submit" variant="primary" :disabled="saving">
					{{ t('launchpad', 'Save profile fields') }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcButton } from '@nextcloud/vue'

const MAX_FIELDS = 10
const URL = '/apps/launchpad/api/profile-fields/definitions'

/**
 * ProfileFieldsTab: the administrator defines up to ten custom profile
 * fields for the people widget (REQ-PEX-001).
 *
 * @spec openspec/specs/people-widget/spec.md
 */
export default {
	name: 'ProfileFieldsTab',

	components: { NcButton },

	data() {
		return {
			fields: [],
			loading: true,
			saving: false,
			error: '',
			saved: false,
			maxFields: MAX_FIELDS,
		}
	},

	/** @spec openspec/specs/people-widget/spec.md */
	async mounted() {
		try {
			const response = await axios.get(generateUrl(URL))
			this.fields = response?.data?.fields || []
		} catch {
			this.error = t('launchpad', 'The profile fields could not be loaded.')
		} finally {
			this.loading = false
		}
	},

	methods: {
		/** @spec openspec/specs/people-widget/spec.md */
		add() {
			if (this.fields.length >= MAX_FIELDS) {
				return
			}
			this.fields.push({
				key: '',
				label: '',
				type: 'text',
				source: 'self',
				ldapAttribute: '',
				searchable: true,
				shownInWidget: true,
				visibility: 'everyone',
			})
		},

		/**
		 * Remove the field at a position.
		 *
		 * @param {number} index Position in the list.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		remove(index) {
			this.fields.splice(index, 1)
		},

		/** @spec openspec/specs/people-widget/spec.md */
		async save() {
			this.saving = true
			this.error = ''
			this.saved = false
			try {
				const response = await axios.put(generateUrl(URL), {
					fields: this.fields,
				})
				this.fields = response?.data?.fields || []
				this.saved = true
			} catch (err) {
				this.error =
					err?.response?.data?.error
					|| t('launchpad', 'The profile fields could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.profile-fields-tab__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 640px;
}

.profile-fields-tab__field {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	gap: 8px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.profile-fields-tab__field label {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.profile-fields-tab__field .profile-fields-tab__check {
	flex-direction: row;
	align-items: center;
	gap: 6px;
}

.profile-fields-tab__actions {
	display: flex;
	gap: 8px;
}

.profile-fields-tab__error {
	color: var(--color-error-text);
}
</style>
