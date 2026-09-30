<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Reset your view?')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'Every widget you hid comes back, and the dashboard looks the way its owner set it up.',
					)
				}}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				data-testid="hidden-reset-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Reset my view') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * ResetPersonalViewDialog: the confirmation before a person's whole
 * personal layer is deleted (ADR-004 modal isolation).
 *
 * @spec openspec/specs/dashboards/spec.md
 */
export default {
	name: 'ResetPersonalViewDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:open', 'confirm'],

	methods: {
		t,
	},
}
</script>
