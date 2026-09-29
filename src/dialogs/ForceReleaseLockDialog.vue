<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Take over editing?')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'{name} can no longer save changes to this dashboard. Take over only when you know they have stopped.',
						{ name: holderName || t('launchpad', 'A colleague') },
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
				data-testid="edit-lock-take-over-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Take over') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * ForceReleaseLockDialog: the confirmation before an administrator
 * force-releases a colleague's editing lock (ADR-004 modal isolation).
 *
 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
 */
export default {
	name: 'ForceReleaseLockDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		holderName: {
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
