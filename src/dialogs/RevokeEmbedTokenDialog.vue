<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Revoke embed token')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{ t('launchpad', 'Revoke {name}? Sites embedding it stop showing it within a minute.', { name }) }}
			</p>
			<NcSelect
				v-model="reason"
				:inputLabel="t('launchpad', 'Reason')"
				:options="reasons"
				label="label"
				trackBy="id"
				:clearable="false"
				data-test="revoke-embed-token-reason" />
			<NcTextArea
				v-model="notes"
				:label="t('launchpad', 'Notes (optional)')"
				data-test="revoke-embed-token-notes" />
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				data-test="revoke-embed-token-confirm"
				@click="$emit('confirm', { reason: reason.id, notes: notes.trim() })">
				{{ t('launchpad', 'Revoke') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcSelect, NcTextArea } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { REQUIRED_FLAG_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { REVOKE_REASONS } from '../utils/embedToken.js'

/**
 * RevokeEmbedTokenDialog: the confirmation before an embed token is revoked,
 * with the reason and optional notes the audit record keeps
 * (embedded-analytics task 27, REQ-EMB-009).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
 */
export default {
	name: 'RevokeEmbedTokenDialog',

	components: {
		NcButton,
		NcDialog,
		NcSelect,
		NcTextArea,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The token's name. */
		name: TEXT_PROP,
	},

	emits: ['update:open', 'confirm'],

	data() {
		const reasons = this.reasonOptions()
		return {
			reasons,
			reason: reasons[0],
			notes: '',
		}
	},

	methods: {
		t,

		/**
		 * The revocation reasons, labelled.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		reasonOptions() {
			const labels = {
				compromised: t('launchpad', 'The token was exposed'),
				no_longer_needed: t('launchpad', 'No longer needed'),
				host_decommissioned: t('launchpad', 'The embedding site is gone'),
				other: t('launchpad', 'Other'),
			}
			return REVOKE_REASONS.map((id) => ({ id, label: labels[id] }))
		},
	},
}
</script>
