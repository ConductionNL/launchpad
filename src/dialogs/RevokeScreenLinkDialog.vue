<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Revoke screen link')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{ t('launchpad', 'Revoke the screen link of {name}? A screen showing it stops showing this dashboard.', { name }) }}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				data-test="revoke-screen-link-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Revoke') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { REQUIRED_FLAG_PROP, TEXT_PROP } from '../constants/propDefinitions.js'

/**
 * RevokeScreenLinkDialog: the confirmation before a dashboard's screen link
 * is revoked, because the screen on the wall goes dark (REQ-KIOSK-006).
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-5
 */
export default {
	name: 'RevokeScreenLinkDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The dashboard's name. */
		name: TEXT_PROP,
	},

	emits: ['update:open', 'confirm'],

	methods: {
		t,
	},
}
</script>
