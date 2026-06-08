<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
