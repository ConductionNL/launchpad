<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="org-navigation-tab" data-test="org-navigation-tab">
		<OrgNavigationEditor :groups="groups" />
	</div>
</template>

<script>
import OrgNavigationEditor from '../OrgNavigationEditor.vue'

/**
 * OrgNavigationTab — Beheer ▸ Org navigation tab. Hosts the relocated
 * org-wide navigation editor (REQ-ONAV-001..012), previously a flat section
 * inside `AdminSettings.vue`.
 */
export default {
	name: 'OrgNavigationTab',

	components: {
		OrgNavigationEditor,
	},

	props: {
		/** Groups passed through to the editor for its group picker. */
		groups: {
			type: Array,
			default: () => [],
		},
	},
}
</script>
