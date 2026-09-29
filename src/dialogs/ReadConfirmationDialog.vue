<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Read confirmation')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<div class="read-confirmation">
			<NcCheckboxRadioSwitch
				:modelValue="ask"
				data-testid="read-confirmation-ask"
				@update:modelValue="ask = $event">
				{{ t('launchpad', 'Ask readers to confirm they have read this') }}
			</NcCheckboxRadioSwitch>
			<template v-if="ask">
				<NcTextField
					:modelValue="prompt"
					:label="t('launchpad', 'What readers confirm')"
					:placeholder="t('launchpad', 'I have read this')"
					@update:modelValue="prompt = $event" />
				<label class="read-confirmation__date">
					{{ t('launchpad', 'Confirm before (optional)') }}
					<input v-model="deadline" type="date" />
				</label>
			</template>
			<p class="read-confirmation__hint">
				{{
					t(
						'launchpad',
						'Read receipts at the top of the dashboard show who has confirmed.',
					)
				}}
			</p>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				data-testid="read-confirmation-save"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * ReadConfirmationDialog: an editor asks readers to confirm they have read
 * a widget, with the sentence they confirm and an optional deadline.
 * Emits `save` with `{requiresAcknowledgement, acknowledgementPrompt,
 * acknowledgementDeadline}`, the fields PlacementUpdater applies.
 *
 * @spec openspec/changes/engagement-acknowledgement-toggle/specs/dashboard-acknowledgements/spec.md
 */
export default {
	name: 'ReadConfirmationDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcTextField,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		/** The widget placement being set up. */
		placement: {
			type: Object,
			default: null,
		},
	},

	emits: ['update:open', 'save'],

	/** @spec openspec/changes/engagement-acknowledgement-toggle/specs/dashboard-acknowledgements/spec.md */
	data() {
		return {
			ask: Number(this.placement?.requiresAcknowledgement) === 1,
			prompt: this.placement?.acknowledgementPrompt ?? '',
			deadline: this.placement?.acknowledgementDeadline ?? '',
		}
	},

	methods: {
		t,

		/**
		 * Emit the placement update.
		 *
		 * @spec openspec/changes/engagement-acknowledgement-toggle/specs/dashboard-acknowledgements/spec.md
		 */
		save() {
			this.$emit('save', {
				requiresAcknowledgement: this.ask ? 1 : 0,
				acknowledgementPrompt:
					this.prompt.trim() === '' ? null : this.prompt.trim(),
				acknowledgementDeadline: this.deadline
					? String(this.deadline)
					: null,
			})
		},
	},
}
</script>

<style scoped>
.read-confirmation {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.read-confirmation__date {
	display: flex;
	flex-direction: column;
}

.read-confirmation__hint {
	color: var(--color-text-maxcontrast);
}
</style>
