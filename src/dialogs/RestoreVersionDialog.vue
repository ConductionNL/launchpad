<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Restore this version?')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'The dashboard goes back to how it was on {date}. How it looks now is saved as a version first, so you can undo this.',
						{ date },
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
				data-testid="version-restore-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Restore') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * RestoreVersionDialog: the confirmation before a dashboard version is
 * restored (ADR-004 modal isolation).
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */
export default {
	name: 'RestoreVersionDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		/** The version's date as shown in the list. */
		date: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],

	methods: {
		t,
	},
}
</script>
