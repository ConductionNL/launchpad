<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin">
		<!-- The board look: the shell draws the h1, the path line and the
		     Documentatie button, and lays its sections out as cards in two
		     columns (board LpBeheerAlgemeen). The shell's own `look` switches
		     the board look on. The page sits in Nextcloud's settings
		     framework (`/settings/admin/launchpad`, REQ-ADM-032); the open
		     tab is the URL fragment, which AdminRoot keeps, not `?tab=`.
		     The version card comes first on General (Restart setup, Tour). -->
		<CnAdminSettingsShell
			ref="shell"
			appId="launchpad"
			appName="Launchpad"
			look="board"
			:title="pageTitle"
			docUrl="https://launchpad.conduction.nl/docs/intro"
			:breadcrumbs="breadcrumbs"
			:breadcrumbNote="breadcrumbNote"
			:showVersionCard="showsVersionCard"
			:appVersion="appVersion"
			:showReimport="false"
			:showUpdateButton="false"
			:tabs="beheerTabs"
			tabsVariant="underline"
			tabQueryKey=""
			lazyTabs
			@tabChange="onTabChange">
			<!-- REQ-ADM-032: the version card's own actions. -->
			<template #actions>
				<NcButton
					variant="secondary"
					data-testid="admin-version-restart-setup"
					@click="openWizard">
					{{ t('launchpad', 'Restart setup') }}
				</NcButton>
				<NcButton
					v-if="adminTour"
					variant="secondary"
					data-testid="admin-version-tour"
					@click="startAdminTour">
					{{ t('launchpad', 'Tour') }}
				</NcButton>
			</template>
			<!-- REQ-ADM-034: the Announcements tab, the one a delegated
			     announcements manager holds. -->
			<template #tab-announcements>
				<AnnouncementsTab />
			</template>
			<!-- Each tab draws its own content in the shell's `tab-<id>` slot.
			     With `lazyTabs` the shell mounts only the open tab's slot: it
			     fetches fresh on each visit, and the page does not load all
			     tabs at once. Algemeen's panel
			     holds the setup wizard banner (REQ-WIZ-001); its cards are the
			     shell's default slot, which shows on the first tab only. -->
			<template #tab-general>
				<div
					v-if="wizardState && !wizardState.complete"
					class="launchpad-admin__wizard-banner"
					data-test="setup-wizard-banner">
					<div>
						<strong>{{ t('launchpad', 'Run setup wizard') }}</strong>
						<p>
							{{
								t(
									'launchpad',
									'Get your intranet started: configure groups, install demo data, and set up admin roles.',
								)
							}}
						</p>
					</div>
					<NcButton
						variant="secondary"
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
						variant="tertiary"
						data-test="setup-wizard-rerun-open"
						@click="openWizard">
						{{ t('launchpad', 'Run setup wizard again') }}
					</NcButton>
				</div>
			</template>
			<template #tab-templates>
				<TemplatesPage />
			</template>
			<template #tab-operations>
				<OperationsTab />
				<!-- REQ-ADM-032: the app catalogue is a section of Bediening. -->
				<div class="launchpad-admin__folded" data-admin-section="app-catalogue">
					<AppCatalogueTab :groups="injectedAllGroups" />
				</div>
			</template>
			<template #tab-roles-permissions>
				<RolesPermissionsTab />
			</template>
			<template #tab-versioning-audit>
				<VersioningAuditTab />
			</template>
			<template #tab-sharing>
				<SharingTab
					:groups="injectedAllGroups"
					:configuredGroups="configuredGroups" />
			</template>
			<template #tab-kiosk>
				<KioskTab />
			</template>
			<template #tab-org-navigation>
				<OrgNavigationTab :groups="injectedAllGroups" />
			</template>
			<template #tab-demo-data>
				<DemoDataTab />
			</template>
			<template #tab-group-dashboards>
				<GroupDashboardsTab />
				<!-- REQ-ADM-032: pinned communities are a section of Groepsdashboards. -->
				<div class="launchpad-admin__folded" data-admin-section="communities">
					<CommunitiesTab />
				</div>
			</template>
			<template #tab-detail-fields>
				<MetadataFieldsSettings />
			</template>
			<template #tab-profile-fields>
				<ProfileFieldsTab />
			</template>

			<!-- Algemeen (board LpBeheerAlgemeen): the organisation-wide
			     defaults, group-shared dashboards and the default-app note,
			     each a card of the shell's grid. -->
			<template v-if="activeTab === 'general'">
				<CnSettingsSection
					:name="t('launchpad', 'Default settings')"
					data-testid="admin-default-settings">

				<div class="launchpad-admin__field">
					<NcSelect
						v-model="settings.defaultPermissionLevel"
						:inputLabel="t('launchpad', 'Default permission level')"
						:options="permissionOptions"
						label="label"
						trackBy="id"
						:clearable="false"
						@update:modelValue="saveSettings" />
				</div>

				<NcCheckboxRadioSwitch
					type="switch"
					:modelValue="settings.allowUserDashboards"
					data-testid="admin-allow-user-dashboards"
					@update:modelValue="
						updateSetting('allowUserDashboards', $event)
					">
					{{ t('launchpad', 'Allow users to create custom dashboards') }}
				</NcCheckboxRadioSwitch>
				<p class="launchpad-admin__hint launchpad-admin__hint--inline">
					{{
						t(
							'launchpad',
							'Disabling this only blocks creating new personal dashboards. Existing personal dashboards remain visible and editable.',
						)
					}}
				</p>

				<NcCheckboxRadioSwitch
					type="switch"
					:modelValue="settings.allowMultipleDashboards"
					@update:modelValue="
						updateSetting('allowMultipleDashboards', $event)
					">
					{{ t('launchpad', 'Allow users to have multiple dashboards') }}
				</NcCheckboxRadioSwitch>

				<!-- REQ-PSP-003: on by default; switching it off gives the
				     default app back. -->
				<NcCheckboxRadioSwitch
					type="switch"
					:modelValue="settings.defaultAppEnabled"
					data-testid="admin-default-app-enabled"
					@update:modelValue="
						updateSetting('defaultAppEnabled', $event)
					">
					{{ t('launchpad', 'Open LaunchPad after signing in') }}
				</NcCheckboxRadioSwitch>
				<p class="launchpad-admin__hint launchpad-admin__hint--inline">
					{{
						t(
							'launchpad',
							'When on, LaunchPad is the default app, so people land on their Workspace page after signing in. A default app chosen by the administrator is kept. Switching this off restores Nextcloud\'s own default.',
						)
					}}
				</p>

				<!-- drill-down-cross-widget-filter REQ-CWF-010: off by default;
				     only later filter changes are recorded. -->
				<NcCheckboxRadioSwitch
					type="switch"
					:modelValue="settings.filterTelemetryEnabled"
					data-testid="admin-filter-telemetry-enabled"
					@update:modelValue="
						updateSetting('filterTelemetryEnabled', $event)
					">
					{{ t('launchpad', 'Record how dashboard filters are used') }}
				</NcCheckboxRadioSwitch>
				<p class="launchpad-admin__hint launchpad-admin__hint--inline">
					{{
						t(
							'launchpad',
							'When on, applying, resetting or bookmarking dashboard filters records which dimensions were filtered, never the values. Earlier filter changes are not recorded.',
						)
					}}
				</p>

				<div class="launchpad-admin__field">
					<NcSelect
						v-model="settings.defaultGridColumns"
						:inputLabel="t('launchpad', 'Default grid columns')"
						:options="gridColumnOptions"
						:clearable="false"
						@update:modelValue="saveSettings" />
				</div>

				<!-- dashboard-quota-limits REQ-QUOTA-001: numeric governance
				     quotas. `0` = unlimited (no enforcement). -->
				<div
					class="launchpad-admin__field"
					data-testid="admin-quota-dashboards">
					<NcTextField
						v-model="settings.maxDashboardsPerUser"
						type="number"
						min="0"
						max="10000"
						:label="t('launchpad', 'Maximum dashboards per user')"
						@update:modelValue="
							onQuotaInput('maxDashboardsPerUser', $event)
						" />
					<p class="launchpad-admin__hint launchpad-admin__hint--inline">
						{{
							t(
								'launchpad',
								'0 = unlimited. Lowering a limit never deletes existing dashboards; it only blocks new ones until users are back under the limit.',
							)
						}}
					</p>
				</div>

				<div
					class="launchpad-admin__field"
					data-testid="admin-quota-widgets">
					<NcTextField
						v-model="settings.maxWidgetsPerDashboard"
						type="number"
						min="0"
						max="10000"
						:label="t('launchpad', 'Maximum widgets per dashboard')"
						@update:modelValue="
							onQuotaInput('maxWidgetsPerDashboard', $event)
						" />
					<p class="launchpad-admin__hint launchpad-admin__hint--inline">
						{{
							t(
								'launchpad',
								'0 = unlimited. Counts placements on a single dashboard. Admin template rollout and compulsory widgets are exempt.',
							)
						}}
					</p>
				</div>
				</CnSettingsSection>

				<!-- REQ-ASET-021: the outside websites widgets may embed or fetch from. -->
				<AllowedSitesSettings />

				<!-- REQ-TILE-044: the services on the server Add tiles may list. -->
				<DiscoverableServicesSettings />

				<!-- launcher-tile-launch-types: program schemes and sign-on templates. -->
				<TileLaunchSettings />

				<!-- launcher-tile-internal-address REQ-TIA-001: the office networks a
				     tile's office-network address is used on. Saved on its own, so a
				     malformed range never blocks the other settings. -->
				<CnSettingsSection
					:name="t('launchpad', 'Office networks')"
					data-testid="admin-office-networks">
					<p class="launchpad-admin__hint">
						{{
							t(
								'launchpad',
								'On these networks a tile with an address on the office network opens that address. One IPv4 or IPv6 range per line, such as 10.20.0.0/16.',
							)
						}}
					</p>
					<label class="launchpad-admin__label" for="launchpad-office-networks">
						{{ t('launchpad', 'Office network ranges') }}
					</label>
					<textarea
						id="launchpad-office-networks"
						v-model="officeNetworksText"
						class="launchpad-admin__textarea"
						rows="4"
						data-testid="admin-office-networks-input" />
					<p
						v-if="officeNetworksError"
						class="launchpad-admin__error"
						role="alert"
						data-testid="admin-office-networks-error">
						{{ officeNetworksError }}
					</p>
					<p class="launchpad-admin__hint" data-testid="admin-office-networks-current">
						{{ currentAddressLine }}
					</p>
					<NcButton
						data-testid="admin-office-networks-save"
						@click="saveOfficeNetworks">
						{{ t('launchpad', 'Save office networks') }}
					</NcButton>
				</CnSettingsSection>

				<!-- Group-shared dashboards (REQ-DASH-015..017). Owns the
				     `set-group-default` / `group-default-badge` data-test hooks. -->
				<CnSettingsSection :name="t('launchpad', 'Group-shared dashboards')">

				<p class="launchpad-admin__hint">
					{{
						t(
							'launchpad',
							'Promote a single dashboard per group as the default. Members of the group will land on it when they have no personal preference yet.',
						)
					}}
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
						{{
							t(
								'launchpad',
								'No group-shared dashboards in this group yet.',
							)
						}}
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
									variant="secondary"
									data-test="set-group-default"
									:disabled="settingGroupDefault === dash.uuid"
									@click="setGroupDefault(groupId, dash.uuid)">
									{{ t('launchpad', 'Set as default') }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>
				</CnSettingsSection>

				<CnSettingsSection :name="t('launchpad', 'Setting as default app')">
				<p>
					{{
						t(
							'launchpad',
							'To make LaunchPad the default app for users, go to Settings > Administration > Theming and select LaunchPad as the default app.',
						)
					}}
				</p>
				</CnSettingsSection>

				<!-- Administrator CSS, cleaned and scoped on save (REQ-THM-003). -->
				<CnSettingsSection :name="t('launchpad', 'Custom CSS')">
					<AdminCustomCss />
				</CnSettingsSection>

				<!-- REQ-ADM-032: embeds and search shortcuts are cards of
				     Algemeen, not tabs of their own (boards LpBeheer*). -->
				<CnSettingsSection
					:name="t('launchpad', 'Embeds')"
					data-admin-section="embeds">
					<EmbedsTab />
				</CnSettingsSection>
				<CnSettingsSection
					:name="t('launchpad', 'Search shortcuts')"
					data-admin-section="search-shortcuts">
					<SearchShortcutsTab />
				</CnSettingsSection>
			</template>
		</CnAdminSettingsShell>

		<!-- Setup wizard modal (REQ-WIZ-002). -->
		<SetupWizardModal
			v-if="showWizard"
			data-test="setup-wizard-modal"
			@close="closeWizard"
			@completed="onWizardCompleted" />

		<!-- runtime-shell REQ-SHELL-011: the admin page's own first-visit
		     tour. Mounted once the General tab has been open, so it starts on
		     the general settings and keeps running when the tab changes. -->
		<CnWalkthrough
			v-if="adminTourMounted"
			:appId="adminTourWalkthroughKey"
			:manifest="adminTour"
			:seenVersion="adminTourSeen"
			:translate="translateTour"
			look="board"
			@complete="onAdminTourComplete" />
	</div>
</template>

<script>
import {
	CnAdminSettingsShell,
	CnDashboardIcon,
	CnSettingsSection,
	CnWalkthrough,
	NcButton,
	NcCheckboxRadioSwitch,
	NcSelect,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { generateUrl } from '@nextcloud/router'
import SetupWizardModal from '../../modals/SetupWizardModal.vue'
import AdminCustomCss from './AdminCustomCss.vue'
import AllowedSitesSettings from './AllowedSitesSettings.vue'
import DiscoverableServicesSettings from './DiscoverableServicesSettings.vue'
import MetadataFieldsSettings from './MetadataFieldsSettings.vue'
import AnnouncementsTab from './tabs/AnnouncementsTab.vue'
import AppCatalogueTab from './tabs/AppCatalogueTab.vue'
import CommunitiesTab from './tabs/CommunitiesTab.vue'
import DemoDataTab from './tabs/DemoDataTab.vue'
import EmbedsTab from './tabs/EmbedsTab.vue'
import GroupDashboardsTab from './tabs/GroupDashboardsTab.vue'
import KioskTab from './tabs/KioskTab.vue'
import OperationsTab from './tabs/OperationsTab.vue'
import OrgNavigationTab from './tabs/OrgNavigationTab.vue'
import ProfileFieldsTab from './tabs/ProfileFieldsTab.vue'
import RolesPermissionsTab from './tabs/RolesPermissionsTab.vue'
import SearchShortcutsTab from './tabs/SearchShortcutsTab.vue'
import SharingTab from './tabs/SharingTab.vue'
import TemplatesPage from './tabs/TemplatesPage.vue'
import VersioningAuditTab from './tabs/VersioningAuditTab.vue'
import TileLaunchSettings from './TileLaunchSettings.vue'
import manifest from '../../manifest.json'
import { api } from '../../services/api.js'
import { ADMIN_TABS, adminTab } from '../../utils/adminPage.js'
import { ADMIN_TOUR_WALKTHROUGH_KEY, adminTourManifest, loadAdminTourSeen, persistAdminTourSeen } from '../../utils/adminWalkthrough.js'
import { logger } from '../../utils/logger.js'

export default {
	name: 'AdminSettings',

	components: {
		AdminCustomCss,
		AllowedSitesSettings,
		AnnouncementsTab,
		CnAdminSettingsShell,
		CnSettingsSection,
		CnWalkthrough,
		DiscoverableServicesSettings,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcTextField,
		CnDashboardIcon,
		SetupWizardModal,
		TemplatesPage,
		OperationsTab,
		EmbedsTab,
		RolesPermissionsTab,
		VersioningAuditTab,
		MetadataFieldsSettings,
		TileLaunchSettings,
		KioskTab,
		CommunitiesTab,
		ProfileFieldsTab,
		SearchShortcutsTab,
		SharingTab,
		OrgNavigationTab,
		DemoDataTab,
		GroupDashboardsTab,
		AppCatalogueTab,
	},

	// REQ-INIT-004: read the initial-state snapshot the administration page
	// pushes via `AdminPageController::show()`. Treated as a hint for the
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

		// REQ-ADM-032: the running version for the version card.
		appVersion: { from: 'version', default: '' },
	},

	props: {
		/**
		 * The tab the page's address names (REQ-ADM-032). An unknown or
		 * empty tab opens General.
		 */
		tab: {
			type: String,
			default: '',
		},

		/**
		 * A former tab the address points at (REQ-ADM-032), e.g. `embeds`:
		 * the page opens its host tab scrolled to that section.
		 */
		section: {
			type: String,
			default: '',
		},

		/**
		 * The tabs the viewer holds (REQ-ADM-034), in the page's order; the
		 * page shows only these.
		 */
		heldTabs: {
			type: Array,
			default: () => [...ADMIN_TABS],
		},
	},

	emits: ['tabChange'],

	/** @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032 */
	data() {
		return {
			loading: true,
			settings: {
				defaultPermissionLevel: {
					id: 'add_only',
					label: this.t('launchpad', 'Add only'),
				},

				allowUserDashboards: this.allowUserDashboards ?? false,
				allowMultipleDashboards: true,
				// drill-down-cross-widget-filter REQ-CWF-010: off by default.
				filterTelemetryEnabled: false,
				// REQ-PSP-003: on by default.
				defaultAppEnabled: true,
				defaultGridColumns: 12,
				// dashboard-quota-limits REQ-QUOTA-001 — `0` = unlimited.
				maxDashboardsPerUser: 0,
				maxWidgetsPerDashboard: 0,
			},

			// launcher-tile-internal-address REQ-TIA-001.
			officeNetworksText: '',
			officeNetworksError: '',

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
			activeTab: adminTab(this.tab),
			// runtime-shell REQ-SHELL-011: the admin tour, its seen version
			// (null until loaded) and whether it has been mounted.
			adminTour: adminTourManifest(manifest),
			adminTourWalkthroughKey: ADMIN_TOUR_WALKTHROUGH_KEY,
			adminTourSeen: null,
			adminTourMounted: false,
		}
	},

	computed: {
		/**
		 * Whether the admin tour may be mounted now: there is one, its seen
		 * version is loaded, and the General tab is open (REQ-SHELL-011).
		 *
		 * @return {boolean}
		 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
		 */
		adminTourReady() {
			// The tour waits while the setup wizard is open (REQ-ADM-032, D6).
			return this.adminTour !== null && this.adminTourSeen !== null && this.activeTab === 'general' && !this.showWizard
		},

		/**
		 * The administrator's own address and whether it is inside the
		 * office networks, so a wrong proxy setup shows at once.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
		 */
		currentAddressLine() {
			const address = this.settings.currentAddress || ''
			if (address === '') {
				return ''
			}
			if (this.settings.currentAddressInOfficeNetworks === true) {
				return this.t('launchpad', 'Your current address is {address}, which is inside the office networks.', { address })
			}
			return this.t('launchpad', 'Your current address is {address}, which is outside the office networks.', { address })
		},

		/**
		 * The page title names the open tab, as the boards do
		 * ("Launchpad-beheer: algemeen").
		 *
		 * @spec openspec/specs/admin-settings/spec.md
		 * @return {string}
		 */
		pageTitle() {
			const tab = this.beheerTabs.find((entry) => entry.id === this.activeTab)
			const label = tab ? tab.label.toLocaleLowerCase() : ''
			// The tab name goes into text interpolation, which escapes it again.
			return this.t('launchpad', 'Launchpad administration: {tab}', { tab: label }, undefined, { escape: false })
		},

		/**
		 * The path line under the title: the Nextcloud administration
		 * settings, the app, then the open tab.
		 *
		 * @spec openspec/specs/admin-settings/spec.md
		 * @return {Array<{label: string, href?: string}>}
		 */
		breadcrumbs() {
			const tab = this.beheerTabs.find((entry) => entry.id === this.activeTab)
			const crumbs = [
				{
					label: this.t('launchpad', 'Administration settings'),
					href: generateUrl('/settings/admin'),
				},
				{ label: 'Launchpad' },
			]
			if (tab) {
				crumbs.push({ label: tab.label })
			}
			return crumbs
		},

		/**
		 * The note after the path. Only Algemeen has one: its settings hold
		 * for the whole organisation.
		 *
		 * @spec openspec/specs/admin-settings/spec.md
		 * @return {string}
		 */
		breadcrumbNote() {
			return this.activeTab === 'general'
				? this.t('launchpad', 'applies to the whole organisation')
				: ''
		},

		/**
		 * Ordered Beheer tab descriptors: the thirteen tabs the Beheer boards
		 * draw, those the viewer holds (REQ-ADM-032, REQ-ADM-034). Labels live here so they stay translatable in
		 * one place; each `id` after the first names the shell slot
		 * `tab-<id>`; the first tab is the default slot.
		 *
		 * @spec openspec/changes/admin-group-management/tasks.md#task-1
		 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 * @return {Array<{id: string, label: string}>}
		 */
		beheerTabs() {
			const labels = {
				general: this.t('launchpad', 'General'),
				announcements: this.t('launchpad', 'Announcements'),
				templates: this.t('launchpad', 'Templates'),
				'roles-permissions': this.t('launchpad', 'Roles and permissions'),
				sharing: this.t('launchpad', 'Sharing'),
				'org-navigation': this.t('launchpad', 'Organisation menu'),
				kiosk: this.t('launchpad', 'Kiosk'),
				'versioning-audit': this.t('launchpad', 'Versions and analytics'),
				operations: this.t('launchpad', 'Controls'),
				'demo-data': this.t('launchpad', 'Demo data'),
				'group-dashboards': this.t('launchpad', 'Group dashboards'),
				'detail-fields': this.t('launchpad', 'Detail fields'),
				'profile-fields': this.t('launchpad', 'Profile fields'),
			}
			const held = Array.isArray(this.heldTabs) ? this.heldTabs : ADMIN_TABS
			return ADMIN_TABS
				.filter((id) => held.includes(id))
				.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * The version card shows on General, so only to a viewer who holds
		 * the whole page; an announcements manager sees none (REQ-ADM-034).
		 *
		 * @return {boolean}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
		 */
		showsVersionCard() {
			return !Array.isArray(this.heldTabs) || this.heldTabs.includes('general')
		},
	},

	/** @spec openspec/specs/admin-settings/spec.md */
	watch: {
		/**
		 * Open the tab the address names when it changes (Back, Forward).
		 *
		 * @param {string} tab The tab the address names.
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		tab(tab) {
			this.showTab(adminTab(tab))
		},

		/**
		 * Scroll to a former tab's section when the fragment names one.
		 *
		 * @param {string} section The section.
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		section(section) {
			this.revealSection(section)
		},

		/**
		 * Mount the admin tour once it is ready, and keep it mounted when the
		 * tab changes so a running tour continues (REQ-SHELL-011).
		 *
		 * @param {boolean} ready Whether the tour may be mounted.
		 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
		 */
		adminTourReady: {
			immediate: true,
			handler(ready) {
				if (ready) {
					this.adminTourMounted = true
				}
			},
		},
	},

	async created() {
		this.loadAdminTour()
		await this.loadData()
		await this.loadGroupSharedDashboards()
		await this.loadWizardState()
	},

	/**
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
	 */
	mounted() {
		this.showTab(this.activeTab)
		this.revealSection(this.section)
	},

	methods: {
		/**
		 * Switch the active admin tab, and tell the page so its address
		 * names the tab.
		 *
		 * @param {string} id The id of the newly opened tab.
		 * @spec openspec/specs/admin-settings/spec.md
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		onTabChange(id) {
			this.activeTab = id
			this.$emit('tabChange', id)
		},

		/**
		 * Scroll a folded section into view: the former tab an old address
		 * or link names (REQ-ADM-032).
		 *
		 * @param {string} section A key of FOLDED_ADMIN_TABS, a `section-*` element id, or ''.
		 * @return {void}
		 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
		 */
		revealSection(section) {
			if (!section) {
				return
			}
			this.$nextTick(() => {
				// A former tab's section, or an element id a settings link names.
				const selector = section.startsWith('section-') ? `#${section}` : `[data-admin-section="${section}"]`
				const target = this.$el?.querySelector?.(selector)
				if (target && typeof target.scrollIntoView === 'function') {
					target.scrollIntoView({ block: 'start' })
				}
			})
		},

		/**
		 * Open a tab without a click: the one the address names.
		 *
		 * @param {string} id The tab's id.
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		showTab(id) {
			this.activeTab = id
			const shell = this.$refs.shell
			if (shell && shell.chosenTabId !== id) {
				shell.chosenTabId = id
			}
		},

		/**
		 * Read whether this administrator finished the admin tour (REQ-SHELL-011).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
		 */
		async loadAdminTour() {
			if (this.adminTour) {
				this.adminTourSeen = await loadAdminTourSeen('launchpad')
			}
		},

		/**
		 * Start the admin tour again from the version card's Tour button: on
		 * General, as if it had not been seen (REQ-ADM-032, REQ-SHELL-011).
		 *
		 * @return {void}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		startAdminTour() {
			this.adminTourMounted = false
			this.adminTourSeen = ''
			if (this.activeTab !== 'general') {
				this.onTabChange('general')
				this.showTab('general')
			}
			this.$nextTick(() => {
				this.adminTourMounted = true
			})
		},

		/**
		 * Record the admin tour as finished, so it does not start again (REQ-SHELL-011).
		 *
		 * @return {void}
		 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
		 */
		onAdminTourComplete() {
			this.adminTourSeen = String(this.adminTour.version || '')
			persistAdminTourSeen('launchpad', this.adminTourSeen)
		},

		/**
		 * Translate a tour title, body or task through LaunchPad's catalogue.
		 *
		 * @param {string} text The source string.
		 * @return {string}
		 */
		translateTour(text) {
			return this.t('launchpad', text)
		},

		/** @spec openspec/specs/admin-settings/spec.md */
		async loadData() {
			this.loading = true
			try {
				const settingsRes = await api.getAdminSettings()
				const data = settingsRes.data?.data ?? settingsRes.data
				if (data) {
					this.officeNetworksText = (Array.isArray(data.officeNetworks) ? data.officeNetworks : []).join('\n')
					this.settings = {
						...this.settings,
						...data,
						defaultPermissionLevel:
							this.permissionOptions.find(
								(p) => p.id === data.defaultPermissionLevel,
							) || this.permissionOptions[1],
					}
				}
			} catch (error) {
				logger.error('Failed to load admin data:', error)
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
					filterTelemetryEnabled:
						this.settings.filterTelemetryEnabled === true,
					defaultAppEnabled: this.settings.defaultAppEnabled !== false,
					defaultGridCols: this.settings.defaultGridColumns,
					// dashboard-quota-limits REQ-QUOTA-001 — sent as integers;
					// the server clamps into [0, 10000].
					maxDashboardsPerUser: this.clampQuota(
						this.settings.maxDashboardsPerUser,
					),
					maxWidgetsPerDashboard: this.clampQuota(
						this.settings.maxWidgetsPerDashboard,
					),
				})
			} catch (error) {
				logger.error('Failed to save settings:', error)
			}
		},

		/**
		 * Save the office networks, one range per line, and show the server's
		 * verdict on the administrator's own address. A malformed range is
		 * refused by the server and nothing is stored.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
		 */
		async saveOfficeNetworks() {
			this.officeNetworksError = ''
			const ranges = this.officeNetworksText
				.split(/[\n,]+/)
				.map((range) => range.trim())
				.filter((range) => range !== '')
			try {
				await api.updateAdminSettings({ officeNetworks: ranges })
			} catch (error) {
				this.officeNetworksError = this.t('launchpad', 'This is not a valid network range')
				// The server names the range it refused after a colon; keep the
				// translated sentence and add that range to it.
				const detail = error?.response?.data?.error || error?.response?.data?.message
				const named = typeof detail === 'string' ? detail.match(/^This is not a valid network range: (.+)$/) : null
				if (named) {
					this.officeNetworksError += ': ' + named[1]
				}
				return
			}
			await this.loadData()
		},

		/**
		 * Write one setting and persist the whole set.
		 *
		 * @param {string} key Setting key to write.
		 * @param {string|number|boolean|object} value New value for that key.
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
		 * @param {string|number} value the raw input value
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
		 * @param {string|number} value the raw input value
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
						next[groupId] = Array.isArray(response.data)
							? response.data
							: []
					} catch (e) {
						logger.warn(
							`Failed to load group dashboards for ${groupId}:`,
							e,
						)
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
				this.offerWizardOnce()
			} catch (e) {
				logger.warn('Failed to load setup wizard state:', e)
				this.wizardState = null
			}
		},

		/**
		 * Open the setup wizard by itself on the first visit of the settings
		 * page while setup is not done (ADR-042): once per browser session,
		 * and only for a viewer who holds General (REQ-ADM-032, D6).
		 *
		 * @return {void}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		offerWizardOnce() {
			if (!this.wizardState || this.wizardState.complete || !this.showsVersionCard) {
				return
			}
			const key = 'launchpad:setup-wizard-offered'
			try {
				if (globalThis.sessionStorage?.getItem(key)) {
					return
				}
				globalThis.sessionStorage?.setItem(key, '1')
			} catch {
				// Without storage the banner still offers the wizard.
				return
			}
			this.showWizard = true
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
			const snapshot = rows.map((d) => ({
				uuid: d.uuid,
				isDefault: d.isDefault,
			}))
			this.groupSharedDashboards = {
				...this.groupSharedDashboards,
				[groupId]: rows.map((d) => ({
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
						const prev = snapshot.find((s) => s.uuid === d.uuid)
						return prev ? { ...d, isDefault: prev.isDefault } : d
					}),
				}
				logger.error(
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
	background: var(--color-primary-element-light);
	border-radius: 12px;
}

.launchpad-admin__wizard-banner p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.launchpad-admin__wizard-rerun {
	display: flex;
	justify-content: flex-end;
}

.launchpad-admin__label {
	display: block;
	margin-top: 8px;
	font-weight: bold;
}

.launchpad-admin__textarea {
	width: 100%;
	max-width: 480px;
	font-family: monospace;
}

.launchpad-admin__error {
	color: var(--color-error-text);
}
</style>
