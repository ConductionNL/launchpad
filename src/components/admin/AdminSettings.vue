<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin">
		<CnSettingsSection
			:name="t('launchpad', 'LaunchPad settings')"
			:description="t('launchpad', 'Configure dashboard permissions and defaults')"
			doc-url="https://launchpad.conduction.nl/docs/intro">
			<!-- Setup wizard banner (REQ-WIZ-001). Stays at the top of the
			     page, above the Beheer tabs, so the call-to-action is always
			     the first thing the admin sees regardless of active tab. -->
			<div
				v-if="wizardState && !wizardState.complete"
				class="launchpad-admin__wizard-banner"
				data-test="setup-wizard-banner">
				<div>
					<strong>{{ t('launchpad', 'Run setup wizard') }}</strong>
					<p>
						{{ t('launchpad', 'Get your intranet started: choose storage, configure groups, install demo data, and set up admin roles.') }}
					</p>
				</div>
				<NcButton
					type="primary"
					data-test="setup-wizard-open"
					@click="openWizard">
					{{ t('launchpad', 'Run setup wizard') }}
				</NcButton>
			</div>
			<div
				v-else-if="wizardState && wizardState.complete"
				class="launchpad-admin__wizard-rerun"
				data-test="setup-wizard-rerun">
				<NcButton
					type="tertiary"
					data-test="setup-wizard-rerun-open"
					@click="openWizard">
					{{ t('launchpad', 'Run setup wizard again') }}
				</NcButton>
			</div>

			<!-- Global Settings — always visible above the tab strip. -->
			<div class="launchpad-admin__section" data-testid="admin-default-settings">
				<h3>{{ t('launchpad', 'Default settings') }}</h3>

				<div class="launchpad-admin__field">
					<NcSelect
						v-model="settings.defaultPermissionLevel"
						:input-label="t('launchpad', 'Default permission level')"
						:options="permissionOptions"
						label="label"
						track-by="id"
						:clearable="false"
						@update:modelValue="saveSettings" />
				</div>

				<NcCheckboxRadioSwitch
					:model-value="settings.allowUserDashboards"
					data-testid="admin-allow-user-dashboards"
					@update:modelValue="updateSetting('allowUserDashboards', $event)">
					{{ t('launchpad', 'Allow users to create custom dashboards') }}
				</NcCheckboxRadioSwitch>
				<p class="launchpad-admin__hint launchpad-admin__hint--inline">
					{{ t('launchpad', 'Disabling this only blocks creating new personal dashboards. Existing personal dashboards remain visible and editable.') }}
				</p>

				<NcCheckboxRadioSwitch
					:model-value="settings.allowMultipleDashboards"
					@update:modelValue="updateSetting('allowMultipleDashboards', $event)">
					{{ t('launchpad', 'Allow users to have multiple dashboards') }}
				</NcCheckboxRadioSwitch>

				<div class="launchpad-admin__field">
					<NcSelect
						v-model="settings.defaultGridColumns"
						:input-label="t('launchpad', 'Default grid columns')"
						:options="gridColumnOptions"
						:clearable="false"
						@update:modelValue="saveSettings" />
				</div>

				<!-- dashboard-quota-limits REQ-QUOTA-001: numeric governance
				     quotas. `0` = unlimited (no enforcement). -->
				<div class="launchpad-admin__field" data-testid="admin-quota-dashboards">
					<NcTextField
						v-model="settings.maxDashboardsPerUser"
						type="number"
						min="0"
						max="10000"
						:label="t('launchpad', 'Maximum dashboards per user')"
						@update:modelValue="onQuotaInput('maxDashboardsPerUser', $event)" />
					<p class="launchpad-admin__hint launchpad-admin__hint--inline">
						{{ t('launchpad', '0 = unlimited. Lowering a limit never deletes existing dashboards; it only blocks new ones until users are back under the limit.') }}
					</p>
				</div>

				<div class="launchpad-admin__field" data-testid="admin-quota-widgets">
					<NcTextField
						v-model="settings.maxWidgetsPerDashboard"
						type="number"
						min="0"
						max="10000"
						:label="t('launchpad', 'Maximum widgets per dashboard')"
						@update:modelValue="onQuotaInput('maxWidgetsPerDashboard', $event)" />
					<p class="launchpad-admin__hint launchpad-admin__hint--inline">
						{{ t('launchpad', '0 = unlimited. Counts placements on a single dashboard. Admin template rollout and compulsory widgets are exempt.') }}
					</p>
				</div>
			</div>

			<!-- Group-shared dashboards (REQ-DASH-015..017). Kept above the
			     tabs because it owns the `set-group-default` /
			     `group-default-badge` data-test hooks (task 12.2). -->
			<div class="launchpad-admin__section">
				<div class="launchpad-admin__section-header">
					<h3>{{ t('launchpad', 'Group-shared dashboards') }}</h3>
				</div>

				<p class="launchpad-admin__hint">
					{{ t('launchpad', 'Promote a single dashboard per group as the default. Members of the group will land on it when they have no personal preference yet.') }}
				</p>

				<div v-if="loadingGroupDashboards" class="launchpad-admin__hint">
					{{ t('launchpad', 'Loading group dashboards…') }}
				</div>

				<div
					v-for="(rows, groupId) in groupSharedDashboards"
					:key="groupId"
					class="launchpad-admin__group">
					<h4 class="launchpad-admin__group-title">
						{{ groupId }}
					</h4>
					<div v-if="rows.length === 0" class="launchpad-admin__hint">
						{{ t('launchpad', 'No group-shared dashboards in this group yet.') }}
					</div>
					<div v-else class="launchpad-admin__templates">
						<div
							v-for="dash in rows"
							:key="dash.uuid"
							class="launchpad-admin__template">
							<div class="launchpad-admin__template-info">
								<CnDashboardIcon :name="dash.icon" :size="20" />
								<strong>{{ dash.name }}</strong>
								<span
									v-if="dash.isDefault === 1"
									class="launchpad-admin__badge"
									data-test="group-default-badge">
									{{ t('launchpad', 'Default') }}
								</span>
							</div>
							<div class="launchpad-admin__template-actions">
								<NcButton
									v-if="dash.isDefault !== 1"
									type="secondary"
									data-test="set-group-default"
									:disabled="settingGroupDefault === dash.uuid"
									@click="setGroupDefault(groupId, dash.uuid)">
									{{ t('launchpad', 'Set as default') }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Beheer tabs — the IA's discrete admin areas. Each tab's
			     content only renders when active (admin-settings spec). -->
			<BeheerTabs
				:tabs="beheerTabs"
				default-tab="templates"
				@change="onTabChange">
				<template #templates>
					<TemplatesPage />
				</template>
				<template #operations>
					<OperationsTab />
				</template>
				<template #roles-permissions>
					<RolesPermissionsTab />
				</template>
				<template #versioning-audit>
					<VersioningAuditTab />
				</template>
				<template #sharing>
					<SharingTab
						:groups="injectedAllGroups"
						:configured-groups="configuredGroups" />
				</template>
				<template #org-navigation>
					<OrgNavigationTab :groups="injectedAllGroups" />
				</template>
				<template #demo-data>
					<DemoDataTab />
				</template>
				<template #group-dashboards>
					<GroupDashboardsTab />
				</template>
			</BeheerTabs>

			<!-- Info -->
			<div class="launchpad-admin__section">
				<h3>{{ t('launchpad', 'Setting as default app') }}</h3>
				<p>
					{{ t('launchpad', 'To make LaunchPad the default app for users, go to Settings > Administration > Theming and select LaunchPad as the default app.') }}
				</p>
			</div>
		</CnSettingsSection>

		<!-- Setup wizard modal (REQ-WIZ-002). -->
		<SetupWizardModal
			v-if="showWizard"
			data-test="setup-wizard-modal"
			@close="closeWizard"
			@completed="onWizardCompleted" />
	</div>
</template>

<script>
import {
	CnSettingsSection,
	NcButton,
	NcSelect,
	NcCheckboxRadioSwitch,
	NcTextField,
	CnDashboardIcon,
} from '@conduction/nextcloud-vue'
import SetupWizardModal from '../../modals/SetupWizardModal.vue'
import BeheerTabs from './BeheerTabs.vue'
import TemplatesPage from './tabs/TemplatesPage.vue'
import OperationsTab from './tabs/OperationsTab.vue'
import RolesPermissionsTab from './tabs/RolesPermissionsTab.vue'
import VersioningAuditTab from './tabs/VersioningAuditTab.vue'
import SharingTab from './tabs/SharingTab.vue'
import OrgNavigationTab from './tabs/OrgNavigationTab.vue'
import DemoDataTab from './tabs/DemoDataTab.vue'
import GroupDashboardsTab from './tabs/GroupDashboardsTab.vue'
import { api } from '../../services/api.js'

export default {
	name: 'AdminSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcTextField,
		CnDashboardIcon,
		SetupWizardModal,
		BeheerTabs,
		TemplatesPage,
		OperationsTab,
		RolesPermissionsTab,
		VersioningAuditTab,
		SharingTab,
		OrgNavigationTab,
		DemoDataTab,
		GroupDashboardsTab,
	},

	// REQ-INIT-004: read the initial-state snapshot the PHP admin form
	// pushes via `LaunchPadAdmin::getForm()`. Treated as a hint for the
	// initial render only — `loadData()` overwrites with the API truth.
	inject: {
		injectedAllGroups: { from: 'allGroups', default: () => [] },
		injectedConfiguredGroups: { from: 'configuredGroups', default: () => [] },
		allowUserDashboards: {
			from: 'allowUserDashboards',
			default: false,
		},
		configuredGroups: {
			from: 'configuredGroups',
			default: () => [],
		},
	},

	data() {
		return {
			loading: true,
			settings: {
				defaultPermissionLevel: { id: 'add_only', label: this.t('launchpad', 'Add only') },
				allowUserDashboards: this.allowUserDashboards ?? false,
				allowMultipleDashboards: true,
				defaultGridColumns: 12,
				// dashboard-quota-limits REQ-QUOTA-001 — `0` = unlimited.
				maxDashboardsPerUser: 0,
				maxWidgetsPerDashboard: 0,
			},
			permissionOptions: [
				{ id: 'view_only', label: this.t('launchpad', 'View only') },
				{ id: 'add_only', label: this.t('launchpad', 'Add only') },
				{ id: 'full', label: this.t('launchpad', 'Full customization') },
			],
			gridColumnOptions: [6, 8, 12],
			// REQ-DASH-015..017 — group-shared dashboards listing.
			groupSharedDashboards: {},
			loadingGroupDashboards: false,
			settingGroupDefault: null,
			// Setup wizard banner state (REQ-WIZ-001).
			wizardState: null,
			showWizard: false,
			activeTab: 'templates',
		}
	},

	computed: {
		/**
		 * Ordered Beheer tab descriptors. Labels live here so they stay
		 * translatable in one place; the slugs match the named slots.
		 *
		 * @spec openspec/changes/admin-group-management/tasks.md#task-1
		 * @return {Array<{slug: string, label: string}>}
		 */
		beheerTabs() {
			return [
				{ slug: 'templates', label: this.t('launchpad', 'Templates') },
				{ slug: 'operations', label: this.t('launchpad', 'Operations') },
				{ slug: 'roles-permissions', label: this.t('launchpad', 'Roles & Permissions') },
				{ slug: 'versioning-audit', label: this.t('launchpad', 'Versioning & Audit') },
				{ slug: 'sharing', label: this.t('launchpad', 'Sharing') },
				{ slug: 'org-navigation', label: this.t('launchpad', 'Org navigation') },
				{ slug: 'demo-data', label: this.t('launchpad', 'Demo data') },
				{ slug: 'group-dashboards', label: this.t('launchpad', 'Group dashboards') },
			]
		},
	},

	/** @spec openspec/specs/admin-settings/spec.md */
	async created() {
		await this.loadData()
		await this.loadGroupSharedDashboards()
		await this.loadWizardState()
	},

	methods: {
		/**
		 * Switch the active admin tab.
		 *
		 * @param {string} slug Slug of the newly selected tab.
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		onTabChange(slug) {
			this.activeTab = slug
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		async loadData() {
			this.loading = true
			try {
				const settingsRes = await api.getAdminSettings()
				const data = settingsRes.data?.data ?? settingsRes.data
				if (data) {
					this.settings = {
						...this.settings,
						...data,
						defaultPermissionLevel: this.permissionOptions.find(
							p => p.id === data.defaultPermissionLevel,
						) || this.permissionOptions[1],
					}
				}
			} catch (error) {
				console.error('Failed to load admin data:', error)
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		async saveSettings() {
			try {
				await api.updateAdminSettings({
					defaultPermLevel: this.settings.defaultPermissionLevel?.id,
					allowUserDash: this.settings.allowUserDashboards,
					allowMultiDash: this.settings.allowMultipleDashboards,
					defaultGridCols: this.settings.defaultGridColumns,
					// dashboard-quota-limits REQ-QUOTA-001 — sent as integers;
					// the server clamps into [0, 10000].
					maxDashboardsPerUser: this.clampQuota(this.settings.maxDashboardsPerUser),
					maxWidgetsPerDashboard: this.clampQuota(this.settings.maxWidgetsPerDashboard),
				})
			} catch (error) {
				console.error('Failed to save settings:', error)
			}
		},

		/**
		 * Write one setting and persist the whole set.
		 *
		 * @param {string} key Setting key to write.
		 * @param {*} value New value for that key.
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		updateSetting(key, value) {
			this.settings[key] = value
			this.saveSettings()
		},

		/**
		 * Clamp an admin-entered quota into [0, 10000], coercing blank /
		 * non-numeric input to 0 (unlimited). Mirrors the server-side clamp
		 * so the UI never round-trips a value the backend would reject
		 * (dashboard-quota-limits REQ-QUOTA-001).
		 *
		 * @param {*} value the raw input value
		 * @return {number} the clamped non-negative integer
		 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-001-quota-admin-settings
		 */
		clampQuota(value) {
			const num = Number.parseInt(value, 10)
			if (Number.isNaN(num) || num < 0) {
				return 0
			}
			if (num > 10000) {
				return 10000
			}
			return num
		},

		/**
		 * Handle a numeric quota field edit: clamp into range, write back
		 * the normalised value (so the input reflects the clamp), and
		 * persist (dashboard-quota-limits REQ-QUOTA-001).
		 *
		 * @param {string} key the settings key (`maxDashboardsPerUser` | `maxWidgetsPerDashboard`)
		 * @param {*} value the raw input value
		 * @return {void}
		 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-001-quota-admin-settings
		 */
		onQuotaInput(key, value) {
			this.settings[key] = this.clampQuota(value)
			this.saveSettings()
		},

		/**
		 * Resolve the list of group ids the admin curates (REQ-DASH-015).
		 *
		 * @return {string[]} Group ids to render.
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		resolveAdminGroupIds() {
			const configured = Array.isArray(this.injectedConfiguredGroups)
				? this.injectedConfiguredGroups
				: []
			if (configured.length > 0) {
				return configured.includes('default')
					? configured
					: ['default', ...configured]
			}
			return ['default']
		},

		/**
		 * Fetch group-shared dashboards for every curated group (REQ-DASH-014).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		async loadGroupSharedDashboards() {
			this.loadingGroupDashboards = true
			const groupIds = this.resolveAdminGroupIds()
			const next = {}
			await Promise.all(
				groupIds.map(async (groupId) => {
					try {
						const response = await api.listGroupDashboards(groupId)
						next[groupId] = Array.isArray(response.data) ? response.data : []
					} catch (e) {
						console.warn(`Failed to load group dashboards for ${groupId}:`, e)
						next[groupId] = []
					}
				}),
			)
			this.groupSharedDashboards = next
			this.loadingGroupDashboards = false
		},

		/**
		 * Fetch the wizard state for the banner gate (REQ-WIZ-001).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		async loadWizardState() {
			try {
				const { data } = await api.getSetupWizardState()
				this.wizardState = data || null
			} catch (e) {
				console.warn('Failed to load setup wizard state:', e)
				this.wizardState = null
			}
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		openWizard() {
			this.showWizard = true
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		closeWizard() {
			this.showWizard = false
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		async onWizardCompleted() {
			this.showWizard = false
			await Promise.all([
				this.loadData(),
				this.loadGroupSharedDashboards(),
				this.loadWizardState(),
			])
		},

		/**
		 * Promote a group-shared dashboard to the group default
		 * (REQ-DASH-015). Optimistic with rollback on failure.
		 *
		 * @param {string} groupId The group id from the row context.
		 * @param {string} uuid The dashboard uuid to promote.
		 * @return {Promise<void>}
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		async setGroupDefault(groupId, uuid) {
			const rows = this.groupSharedDashboards[groupId] || []
			const snapshot = rows.map(d => ({ uuid: d.uuid, isDefault: d.isDefault }))
			this.groupSharedDashboards = {
				...this.groupSharedDashboards,
				[groupId]: rows.map(d => ({
					...d,
					isDefault: d.uuid === uuid ? 1 : 0,
				})),
			}
			this.settingGroupDefault = uuid
			try {
				await api.setGroupDashboardDefault(groupId, uuid)
			} catch (error) {
				this.groupSharedDashboards = {
					...this.groupSharedDashboards,
					[groupId]: rows.map((d) => {
						const prev = snapshot.find(s => s.uuid === d.uuid)
						return prev ? { ...d, isDefault: prev.isDefault } : d
					}),
				}
				console.error(
					this.t('launchpad', 'Failed to set the group default dashboard'),
					error,
				)
			} finally {
				this.settingGroupDefault = null
			}
		},
	},
}
</script>

<style scoped>
.launchpad-admin {
	max-width: 900px;
}

.launchpad-admin__section {
	margin-bottom: 32px;
	padding-bottom: 32px;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-admin__section h3 {
	margin: 0 0 16px;
}

.launchpad-admin__section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 16px;
}

.launchpad-admin__section-header h3 {
	margin: 0;
}

.launchpad-admin__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.launchpad-admin__field {
	margin-bottom: 16px;
}

.launchpad-admin__field label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}

.launchpad-admin__templates {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.launchpad-admin__template {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.launchpad-admin__template-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.launchpad-admin__badge {
	display: inline-block;
	padding: 2px 8px;
	background: var(--color-primary-element);
	color: var(--color-primary-text);
	border-radius: var(--border-radius-pill);
	font-size: 12px;
}

.launchpad-admin__template-actions {
	display: flex;
	gap: 8px;
}

.launchpad-admin__group {
	margin-bottom: 24px;
}

.launchpad-admin__group-title {
	margin: 16px 0 8px;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.launchpad-admin__wizard-banner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 16px 20px;
	margin-bottom: 24px;
	background: var(--color-primary-element-light);
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
}

.launchpad-admin__wizard-banner p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.launchpad-admin__wizard-rerun {
	margin-bottom: 16px;
	display: flex;
	justify-content: flex-end;
}
</style>
