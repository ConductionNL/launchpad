<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="sharing-conversation" data-test="sharing-conversation">
		<h3 class="sharing-conversation__title">
			{{ t('launchpad', 'Conversation beside this dashboard') }}
		</h3>
		<NcCheckboxRadioSwitch
			v-if="shared"
			type="switch"
			:modelValue="enabled"
			:loading="saving"
			:disabled="saving"
			data-test="sharing-conversation-switch"
			@update:modelValue="toggle">
			{{ t('launchpad', 'Show the conversation beside the dashboard') }}
		</NcCheckboxRadioSwitch>
		<p v-else class="sharing-conversation__hint" data-test="sharing-conversation-unshared">
			{{ t('launchpad', 'This dashboard is yours alone. Share it with people or a group and the conversation appears here too.') }}
		</p>
		<p v-if="error !== ''" class="sharing-conversation__error" role="alert">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { FLAG_PROP, NULLABLE_OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { api } from '../../../services/api.js'
import { conversationEnabled } from '../../../utils/dashboardDiscussion.js'
import { logger } from '../../../utils/logger.js'

/**
 * SharingConversation — the "Conversation beside this dashboard" part of a
 * dashboard's Sharing tab (REQ-LEAF-006). On a shared dashboard a switch
 * shows or hides the Talk conversation beside it and is stored at once; an
 * unshared dashboard says the conversation appears once it is shared.
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */
export default {
	name: 'SharingConversation',

	components: { NcCheckboxRadioSwitch },

	props: {
		/** The dashboard: {id, conversationEnabled}. */
		dashboard: NULLABLE_OBJECT_PROP,

		/** Whether the dashboard is shared with people or a group. */
		shared: FLAG_PROP,
	},

	emits: ['update:conversationEnabled'],

	data() {
		return {
			enabled: conversationEnabled(this.dashboard),
			saving: false,
			error: '',
		}
	},

	watch: {
		/**
		 * Follow a switch stored elsewhere.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		'dashboard.conversationEnabled': function() {
			this.enabled = conversationEnabled(this.dashboard)
		},
	},

	methods: {
		t,

		/**
		 * Store the switch; on a failure it returns to where it was.
		 *
		 * @param {boolean} value Whether the conversation shows.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		async toggle(value) {
			const before = this.enabled
			this.enabled = value
			this.saving = true
			this.error = ''
			try {
				await api.updateDashboard(this.dashboard.id, { conversationEnabled: value })
				this.$emit('update:conversationEnabled', value ? 1 : 0)
			} catch (error) {
				this.enabled = before
				this.error = t('launchpad', 'The conversation setting could not be saved.')
				logger.error('Failed to save the conversation switch:', error)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.sharing-conversation {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 16px;
}

.sharing-conversation__title {
	margin: 0;
	font-size: 1em;
	font-weight: bold;
}

.sharing-conversation__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.sharing-conversation__error {
	margin: 0;
	color: var(--color-error-text);
}
</style>
