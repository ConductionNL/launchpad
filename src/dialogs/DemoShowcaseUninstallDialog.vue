<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Remove showcase dashboard')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'Remove the {name} showcase dashboard for all users? You can reinstall it later.',
						{ name: showcaseName },
					)
				}}
			</p>
		</template>
		<template #actions>
			<NcButton type="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton type="error" @click="$emit('confirm')">
				{{ t('launchpad', 'Remove') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'

/**
 * DemoShowcaseUninstallDialog — confirmation dialog extracted from
 * `AdminDemoData.vue` per ADR-004 modal-isolation.
 *
 * Replaces a `window.confirm()`. The native dialog is not themeable, is not
 * translatable beyond the string passed to it, renders outside the app's
 * stacking context, and — the reason it matters here — it blocks the whole
 * browser thread, so it cannot be driven by the e2e suite or read by a
 * screen reader as part of the page.
 *
 * The parent owns the showcase row and the uninstall action; this dialog
 * only renders the confirmation prompt.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
export default {
	name: 'DemoShowcaseUninstallDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
		showcaseName: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],
}
</script>
