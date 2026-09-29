<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Revoke this playlist?')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'Screens showing {name} stop at their next check, and the link stops working for good.',
						{ name },
					)
				}}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				data-testid="kiosk-revoke-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Revoke') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * RevokeKioskPlaylistDialog: the confirmation before a kiosk link is
 * revoked (ADR-004 modal isolation).
 *
 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'RevokeKioskPlaylistDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		name: {
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
