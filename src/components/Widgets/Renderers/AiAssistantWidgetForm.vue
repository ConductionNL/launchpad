<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="ai-assistant-form">
		<label class="ai-assistant-form__field">
			{{ t('launchpad', 'Hermiq agent (optional)') }}
			<input
				v-model="agentUuid"
				type="text"
				data-testid="ai-assistant-agent"
				@input="emit" />
		</label>
		<p class="ai-assistant-form__hint">
			{{
				t(
					'launchpad',
					"Leave empty to use Hermiq's default agent. The answers can use everything that agent may read for the person asking.",
				)
			}}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

/**
 * AiAssistantWidgetForm: the assistant widget's settings, the Hermiq agent
 * to ask (optional). Emits `update:content` like the other widget forms.
 *
 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
 */
export default {
	name: 'AiAssistantWidgetForm',

	props: {
		editingWidget: {
			type: Object,
			default: null,
		},

		value: {
			type: Object,
			default: () => ({ agentUuid: '' }),
		},
	},

	emits: ['update:content'],

	/** @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md */
	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return { agentUuid: initial.agentUuid ?? '' }
	},

	methods: {
		t,

		/**
		 * Tell the host the content.
		 *
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		emit() {
			this.$emit('update:content', { agentUuid: this.agentUuid.trim() })
		},

		/**
		 * Nothing is required.
		 *
		 * @return {Array<string>} No errors.
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.ai-assistant-form__field {
	display: flex;
	flex-direction: column;
}

.ai-assistant-form__hint {
	color: var(--color-text-maxcontrast);
}
</style>
