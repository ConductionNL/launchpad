<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="roles-permissions-tab" data-test="roles-permissions-tab" data-testid="admin-roles-section">
		<RolePermissionsSection />
		<div class="roles-permissions-tab__spacer" />
		<RoleLayoutDefaultsSection data-testid="admin-layout-defaults-section" />
		<div class="roles-permissions-tab__spacer" />
		<ActionAuthMatrix />
	</div>
</template>

<script>
import RolePermissionsSection from '../RolePermissionsSection.vue'
import RoleLayoutDefaultsSection from '../RoleLayoutDefaultsSection.vue'
import ActionAuthMatrix from '../ActionAuthMatrix.vue'

/**
 * RolesPermissionsTab — Beheer ▸ Roles & Permissions tab (permissions spec).
 * Hosts the relocated `RolePermissionsSection`, the role-based layout
 * defaults, and the ADR-023 action-authorization matrix. These were three
 * of the eleven flat sections inside the old monolithic `AdminSettings.vue`.
 */
export default {
	name: 'RolesPermissionsTab',

	components: {
		RolePermissionsSection,
		RoleLayoutDefaultsSection,
		ActionAuthMatrix,
	},
}
</script>

<style scoped>
.roles-permissions-tab__spacer {
	height: 32px;
}
</style>
