<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal launchpad-modal--wide"
		size="normal"
		:name="modalTitle"
		@close="$emit('close')">
		<div class="dashboard-config">
			<header class="dashboard-config__head">
				<p
					v-if="!isCreate && dashboard?.name"
					class="launchpad-modal__kicker"
					data-testid="dashboard-config-kicker">
					{{ dashboard.name }}
				</p>
				<h2 class="dashboard-config__title launchpad-modal__title">
					{{ modalTitle }}
				</h2>
			</header>

			<!-- Tab strip (dashboard-sharing spec). Sharing is no longer a
			     free-floating field set in the modal body — it lives in its
			     own Sharing tab. Tabs are hidden in create mode (nothing to
			     share / pin until the dashboard exists). Markup + styling
			     follow the fleet tablist pattern (nc-vue CnTabbedFormDialog /
			     NC sidebar tabs): justified tabs, icon + label, active
			     primary underline. -->
			<!-- cn-look-board keeps the theme's button rules off the tabs,
			     which are drawn as words on an underline, not as buttons. -->
			<ul
				v-if="!isCreate"
				class="dashboard-config__tabs cn-look-board"
				role="tablist"
				data-test="config-tabs">
				<li
					v-for="tab in tabs"
					:key="tab.id"
					role="presentation"
					class="dashboard-config__tab-item">
					<button
						type="button"
						role="tab"
						:aria-selected="currentTab === tab.id ? 'true' : 'false'"
						:aria-controls="`config-panel-${tab.id}`"
						class="dashboard-config__tab"
						:class="{
							'dashboard-config__tab--active': currentTab === tab.id,
						}"
						:data-test="`config-tab-${tab.id}`"
						@click="currentTab = tab.id">
						<!-- LpDashboardInstellingen draws the tabs as words alone, so the
						     strip fits the dialog without scrolling sideways. -->
						<span>{{ tab.label }}</span>
						<span
							v-if="tab.count"
							class="dashboard-config__tab-count"
							:data-test="`config-tab-count-${tab.id}`">
							{{ tab.count }}
						</span>
					</button>
				</li>
			</ul>

			<div
				v-show="isCreate || currentTab === 'general'"
				id="config-panel-general"
				role="tabpanel"
				data-test="config-panel-general"
				class="dashboard-config__panel">
				<div class="dashboard-config__field">
					<NcTextField
						:modelValue="form.name"
						:label="t('launchpad', 'Title')"
						:placeholder="t('launchpad', 'My dashboard')"
						data-testid="dashboard-name-input"
						@update:modelValue="form.name = $event" />
				</div>

				<div class="dashboard-config__field">
					<label
						class="dashboard-config__label"
						for="dashboard-config-description">
						{{ t('launchpad', 'Description') }}
					</label>
					<textarea
						id="dashboard-config-description"
						v-model="form.description"
						class="dashboard-config__textarea"
						rows="3"
						data-testid="dashboard-description-input"
						:placeholder="
							t('launchpad', 'What is this dashboard for?')
						" />
				</div>

				<!-- Icon browser — searchable grid over the full MDI set plus a
			     Custom tab that accepts an upload (capability
			     `custom-icon-upload-pattern`). The same v-model holds whichever
			     it emits: an SVG path string or a /apps/launchpad/resource/...
			     URL (REQ-ICON-003 + REQ-ICON-008..009). -->
				<!-- dashboard-tree-navigation REQ-TREEUI-003: where this
				     dashboard sits and its web address name. Edit only. -->
				<template v-if="!isCreate">
					<div class="dashboard-config__field">
						<NcSelect
							v-model="form.parent"
							:inputLabel="t('launchpad', 'Parent dashboard')"
							:options="parentChoices"
							label="name"
							trackBy="uuid"
							:clearable="false"
							data-testid="dashboard-parent-select" />
						<span class="dashboard-config__hint" data-testid="dashboard-parent-hint">
							{{ t('launchpad', 'This builds a tree of dashboards under Dashboards in the More menu') }}
						</span>
					</div>
					<div class="dashboard-config__field">
						<NcTextField
							:modelValue="form.slug"
							:label="t('launchpad', 'Web address name')"
							:helperText="
								t(
									'launchpad',
									'Lowercase letters, digits and dashes. Used in the address of a dashboard under another one.',
								)
							"
							data-testid="dashboard-slug-input"
							@update:modelValue="form.slug = $event" />
					</div>
					<!-- launcher-tile-open-behaviour D2: how a tile without its
					     own choice opens on this dashboard. -->
					<div class="dashboard-config__field">
						<NcSelect
							v-model="form.defaultLinkTarget"
							:inputLabel="t('launchpad', 'Opens in')"
							:options="linkTargetChoices"
							label="label"
							trackBy="id"
							:clearable="false"
							data-testid="dashboard-link-target-select" />
					</div>
					<!-- REQ-CWF-011: the hierarchies the filter bus joins widgets on. -->
					<div class="dashboard-config__field">
						<NcSelect
							v-model="form.hierarchies"
							:inputLabel="t('launchpad', 'Drill-down levels')"
							:options="hierarchyChoices"
							label="name"
							trackBy="id"
							multiple
							keepOpen
							:placeholder="t('launchpad', 'None')"
							data-testid="dashboard-hierarchies-select" />
						<span class="dashboard-config__hint">
							{{ t('launchpad', 'A click on an area in one widget filters the other widgets, and a click on a smaller area also selects the area it belongs to.') }}
						</span>
					</div>
				</template>
				<p
					v-if="saveError"
					class="dashboard-config__error"
					role="alert"
					data-testid="dashboard-config-error">
					{{ saveError }}
				</p>

				<div class="dashboard-config__field">
					<CnIconBrowser
						inline
						:label="t('launchpad', 'Icon')"
						:value="form.icon"
						:icons="iconCatalogue"
						:uploadFn="iconUploadFn"
						@input="form.icon = $event" />
				</div>
				<!-- The default switch and the detail fields live under Details,
				     as board LpDashboardInstellingen draws them (three tabs:
				     Details, Talen, Delen). -->
				<div
					v-if="!isCreate"
					class="dashboard-config__field dashboard-config__field--toggle">
					<NcCheckboxRadioSwitch
						:modelValue="form.isDefault"
						type="switch"
						@update:modelValue="form.isDefault = $event">
						<strong>{{ t('launchpad', 'Default dashboard') }}</strong>
						<span class="dashboard-config__hint">
							{{
								t(
									'launchpad',
									'Open this dashboard automatically when visiting LaunchPad.',
								)
							}}
						</span>
					</NcCheckboxRadioSwitch>
				</div>
				<DetailsTab
					v-if="!isCreate && canManageShares && dashboard?.uuid"
					:dashboardUuid="dashboard.uuid" />
				<!-- Display (REQ-THM-004): the instance theme, read-only; a
				     dashboard picks no theme and no layout preset. -->
				<section
					v-if="!isCreate && canManageShares"
					class="dashboard-config__display"
					data-test="config-display">
					<h3 class="dashboard-config__section-heading">
						{{ t('launchpad', 'Display') }}
					</h3>
					<div class="dashboard-config__field">
						<NcTextField
							:modelValue="themeName"
							:label="t('launchpad', 'Theme')"
							readonly
							data-testid="display-theme" />
						<p class="dashboard-config__hint" data-testid="display-theme-hint">
							{{ t('launchpad', 'The theme is the same for every dashboard. Set this dashboard\'s own colours under Own look.') }}
						</p>
					</div>
				</section>
				<!-- Own look (REQ-THM-001/002, board LpDashboardInstellingen):
				     accent, background colour and background image of THIS
				     dashboard, a block on Details with the same contrast check
				     the server applies; there is no separate Look tab. -->
				<section
					v-if="!isCreate && canManageShares"
					class="dashboard-config__look"
					data-test="config-look">
					<h3 class="dashboard-config__section-heading">
						{{ t('launchpad', 'Own look') }}
					</h3>
					<p class="dashboard-config__hint">
						{{ t('launchpad', 'Give this dashboard its own colors. Leave a field empty to follow the theme.') }}
					</p>
					<div class="dashboard-config__field dashboard-config__look-row">
						<NcTextField
							:modelValue="look.accent"
							:label="t('launchpad', 'Accent color')"
							placeholder="#1f5c3a"
							data-testid="look-accent"
							@update:modelValue="look.accent = $event" />
						<input
							type="color"
							class="dashboard-config__swatch"
							:value="lookAccentHex || '#000000'"
							:aria-label="t('launchpad', 'Accent color')"
							@input="look.accent = $event.target.value">
					</div>
					<div class="dashboard-config__field dashboard-config__look-row">
						<NcTextField
							:modelValue="look.background"
							:label="t('launchpad', 'Background color')"
							placeholder="#ffffff"
							data-testid="look-background"
							@update:modelValue="look.background = $event" />
						<input
							type="color"
							class="dashboard-config__swatch"
							:value="lookBackgroundHex || '#ffffff'"
							:aria-label="t('launchpad', 'Background color')"
							@input="look.background = $event.target.value">
					</div>
					<p
						v-if="lookFormatError"
						class="dashboard-config__error"
						role="alert"
						data-testid="look-format-error">
						{{ lookFormatError }}
					</p>
					<p
						v-else-if="!lookAccentCheck.readable"
						class="dashboard-config__error"
						role="alert"
						data-testid="look-contrast-error">
						{{ t('launchpad', 'Buttons in this colour are hard to see (contrast {ratio} to 1, needs 3 to 1)', { ratio: lookAccentCheck.ratio.toFixed(1) }) }}
					</p>
					<!-- The dashboard's background image (review 27 Nov item 17):
					     a styled upload button, a small preview and a remove
					     action instead of the bare browser file input. -->
					<div class="dashboard-config__field" data-testid="look-image">
						<span id="look-image-label" class="dashboard-config__label">
							{{ t('launchpad', 'Background image') }}
						</span>
						<p class="dashboard-config__hint">
							{{ t('launchpad', 'Shown behind this dashboard; leave empty to follow the theme.') }}
						</p>
						<div class="dashboard-config__image-row">
							<img
								v-if="look.resource"
								class="dashboard-config__image-preview"
								:src="lookImageUrl"
								alt=""
								data-testid="look-image-preview">
							<span v-if="look.resource" class="dashboard-config__image-name" data-testid="look-image-name">
								{{ look.resource }}
							</span>
							<NcButton
								aria-describedby="look-image-label"
								data-testid="look-image-upload"
								:disabled="lookUploading"
								@click="pickLookImage">
								{{ look.resource ? t('launchpad', 'Choose another image') : t('launchpad', 'Upload image') }}
							</NcButton>
							<NcButton
								v-if="look.resource"
								variant="tertiary"
								data-testid="look-image-clear"
								@click="look.resource = ''">
								{{ t('launchpad', 'Remove') }}
							</NcButton>
							<input
								id="look-image-input"
								ref="lookImageFile"
								class="dashboard-config__file"
								type="file"
								accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
								tabindex="-1"
								aria-hidden="true"
								data-testid="look-image-input"
								:disabled="lookUploading"
								@change="onLookImage">
						</div>
						<p v-if="lookUploadError" class="dashboard-config__error" role="alert">
							{{ lookUploadError }}
						</p>
					</div>
				</section>
			</div>
			<!-- /general panel -->


			<div
				v-if="!isCreate && currentTab === 'languages' && dashboard?.uuid"
				id="config-panel-languages"
				role="tabpanel"
				data-test="config-panel-languages"
				class="dashboard-config__panel">
				<LanguagesTab :dashboardUuid="dashboard.uuid" />
			</div>


			<!-- Sharing panel (dashboard-sharing spec). The sharee picker and
			     per-share permission rows are reachable ONLY from this tab. -->
			<div
				v-show="!isCreate && currentTab === 'sharing'"
				id="config-panel-sharing"
				role="tabpanel"
				data-test="config-panel-sharing"
				class="dashboard-config__panel">
				<div
					v-if="!isCreate && canManageShares"
					class="dashboard-config__field dashboard-config__field--select">
					<label class="dashboard-config__label">
						{{ t('launchpad', 'Share with users and groups') }}
					</label>

					<!-- REQ-SHARE-015: under Nobody only an administrator adds shares. -->
					<template v-if="sharingPolicy.mayShare">
						<NcSelect
							:modelValue="null"
							:options="shareeOptions"
							:filterable="false"
							:loading="shareeLoading"
							:aria-label-combobox="
								t('launchpad', 'Share with users and groups')
							"
							:placeholder="t('launchpad', 'Search users and groups…')"
							label="displayName"
							trackBy="key"
							:clearable="false"
							@search="onShareeSearch"
							@update:modelValue="onShareeSelected">
							<template #option="option">
								<span class="sharee-option">
									<AccountGroup
										v-if="option.shareType === 'group'"
										:size="18" />
									<Account v-else :size="18" />
									{{ option.displayName }}
								</span>
							</template>
						</NcSelect>
					</template>
					<p
						v-else
						class="dashboard-config__hint"
						data-test="share-not-allowed-hint">
						{{ t('launchpad', 'Your administrator does not allow you to share dashboards.') }}
					</p>

					<ul
						v-if="localShares.length > 0"
						class="dashboard-config__shares">
						<li
							v-for="(share, idx) in localShares"
							:key="`${share.shareType}:${share.shareWith}`"
							class="dashboard-config__share">
							<span class="dashboard-config__share-name">
								<AccountGroup
									v-if="share.shareType === 'group'"
									:size="18" />
								<Account v-else :size="18" />
								{{ share.displayName || share.shareWith }}
							</span>
							<NcSelect
								:modelValue="
									permissionOptionFor(share.permissionLevel)
								"
								:options="permissionOptions"
								:inputLabel="t('launchpad', 'Permission level')"
								label="label"
								trackBy="value"
								:clearable="false"
								class="dashboard-config__share-level"
								@update:modelValue="
									onShareLevelChange(idx, $event)
								" />
							<NcButton
								variant="tertiary"
								:aria-label="t('launchpad', 'Remove share')"
								@click="onShareRemove(idx)">
								<template #icon>
									<Close :size="18" />
								</template>
							</NcButton>
						</li>
					</ul>
					<p v-else class="dashboard-config__hint">
						{{ t('launchpad', 'Not shared with anyone yet.') }}
					</p>
					<p
						v-if="localShares.length > 0 && isUnpublished"
						class="dashboard-config__hint"
						data-test="share-unpublished-hint">
						{{
							t('launchpad', 'Colleagues see this dashboard once you publish it')
						}}
					</p>
					<p
						v-if="sharesDirty"
						class="dashboard-config__hint dashboard-config__hint--dirty">
						{{
							t('launchpad', 'Unsaved changes — click Save to apply.')
						}}
					</p>
				</div>

				<!-- Public link (anonymous read-only share). Applies immediately;
				     independent of the Save button. -->
				<div
					v-if="!isCreate && canManageShares"
					class="dashboard-config__field dashboard-config__public">
					<label class="dashboard-config__label">
						{{ t('launchpad', 'Public link') }}
					</label>
					<p class="dashboard-config__hint">
						{{
							t(
								'launchpad',
								'Anyone with the link can view this dashboard read-only, without a Nextcloud account.',
							)
						}}
					</p>

					<ul
						v-if="publicShares.length > 0"
						class="dashboard-config__public-list">
						<li
							v-for="share in publicShares"
							:key="share.id"
							class="dashboard-config__public-row">
							<!-- Read-only link field. It is in the tab order (so
							     the link can be selected and copied by keyboard),
							     which means it needs a name: without one a screen
							     reader announces only the URL string with no
							     indication of what it is. -->
							<input
								class="dashboard-config__public-url"
								type="text"
								readonly
								:aria-label="t('launchpad', 'Public share link')"
								:value="publicShareUrl(share.token)"
								@focus="$event.target.select()" />
							<NcButton
								variant="tertiary"
								:aria-label="t('launchpad', 'Copy link')"
								:title="
									copiedToken === share.token
										? t('launchpad', 'Copied')
										: t('launchpad', 'Copy link')
								"
								@click="copyPublicShareUrl(share)">
								<template #icon>
									<Check
										v-if="copiedToken === share.token"
										:size="18" />
									<ContentCopy v-else :size="18" />
								</template>
							</NcButton>
							<span
								v-if="share.passwordRequired"
								class="dashboard-config__public-badge"
								:title="t('launchpad', 'Password protected')">
								<Lock :size="16" />
							</span>
							<span
								v-if="share.expiresAt"
								class="dashboard-config__public-expiry">
								{{
									t('launchpad', 'until {date}', {
										date: share.expiresAt,
									})
								}}
							</span>
							<NcButton
								variant="tertiary"
								:aria-label="t('launchpad', 'Revoke link')"
								:title="t('launchpad', 'Revoke link')"
								@click="onRevokePublicShare(share.id)">
								<template #icon>
									<Close :size="18" />
								</template>
							</NcButton>
						</li>
					</ul>
					<p
						v-else-if="!publicSharesLoading"
						class="dashboard-config__hint">
						{{ t('launchpad', 'No public links yet.') }}
					</p>

					<p
						v-if="!canCreatePublicLink"
						class="dashboard-config__hint"
						data-test="public-share-off-hint">
						{{ t('launchpad', 'Public links are switched off.') }}
					</p>
					<div v-else class="dashboard-config__public-create">
						<NcTextField
							v-model="newSharePassword"
							type="password"
							:label="t('launchpad', 'Password (optional)')"
							autocomplete="new-password" />
						<!-- REQ-SHARE-015: every new public link has an expiry date. -->
						<input
							v-model="newShareExpiry"
							class="dashboard-config__public-date"
							type="date"
							required
							aria-required="true"
							data-test="public-share-expiry"
							:aria-label="t('launchpad', 'Expiry date (required)')" />
						<NcButton
							variant="secondary"
							data-test="public-share-create"
							:disabled="creatingPublicShare || !newShareExpiry"
							@click="onCreatePublicShare">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('launchpad', 'Create public link') }}
						</NcButton>
					</div>
				</div>

				<!-- REQ-LEAF-006: the Talk conversation beside a shared dashboard. -->
				<SharingConversation
					v-if="!isCreate && canManageShares"
					:dashboard="dashboard"
					:shared="isSharedDashboard"
					@update:conversationEnabled="onConversationChanged" />

				<!-- REQ-EMB-011: the dashboard's embed tokens. The embed token
				     API is admin-only, so the part is too. -->
				<SharingEmbedTokens
					v-if="!isCreate && canManageShares && isAdmin && currentTab === 'sharing'"
					:dashboard="dashboard" />
			</div>
			<!-- /sharing panel -->

			<!-- REQ-SCHEDUI-001: publish, unpublish and the schedule. -->
			<div
				v-if="!isCreate && currentTab === 'publication'"
				id="config-panel-publication"
				role="tabpanel"
				data-test="config-panel-publication"
				class="dashboard-config__panel">
				<PublicationTab :dashboard="dashboard" />
			</div>

			<!-- REQ-SCH-011: the scheduled exports of this dashboard. -->
			<div
				v-if="!isCreate && currentTab === 'exports'"
				id="config-panel-exports"
				role="tabpanel"
				data-test="config-panel-exports"
				class="dashboard-config__panel">
				<ExportsTab
					:subjects="exportSubjects"
					:defaultSubject="{ type: 'dashboard', id: dashboard.uuid }" />
			</div>

			<!-- Board LpVersies: the saved versions of this dashboard. -->
			<div
				v-if="!isCreate && currentTab === 'versions'"
				id="config-panel-versions"
				role="tabpanel"
				data-test="config-panel-versions"
				class="dashboard-config__panel">
				<VersionsTab
					:dashboardUuid="dashboard.uuid"
					@restored="$emit('versionRestored', $event)"
					@close="$emit('close')" />
			</div>

			<!-- The Versions tab ends in its own Close and Restore this version
			     (LpVersies); the dialog's Save does not apply there. -->
			<div v-if="currentTab !== 'versions' || isCreate" class="dashboard-config__actions">
				<NcButton
					v-if="canDelete && !isCreate"
					variant="error"
					:disabled="saving"
					data-testid="dashboard-delete-button"
					@click="onDelete">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('launchpad', 'Delete dashboard') }}
				</NcButton>
				<div class="dashboard-config__actions-right">
					<NcButton
						variant="tertiary"
						:disabled="saving"
						@click="$emit('close')">
						{{ t('launchpad', 'Cancel') }}
					</NcButton>
					<NcButton
						variant="primary"
						:disabled="!canSave || saving"
						data-testid="dashboard-save-button"
						@click="onSave">
						<template #icon>
							<Plus v-if="isCreate" :size="20" />
							<ContentSave v-else :size="20" />
						</template>
						{{ primaryButtonLabel }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { CnIconBrowser, DEFAULT_ICON } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcModal,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import { defineAsyncComponent } from 'vue'
import Account from 'vue-material-design-icons/Account.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CalendarExport from 'vue-material-design-icons/CalendarExport.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'
import History from 'vue-material-design-icons/History.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import Translate from 'vue-material-design-icons/Translate.vue'
import DetailsTab from '../components/Workspace/config/DetailsTab.vue'
import LanguagesTab from '../components/Workspace/config/LanguagesTab.vue'
import SharingConversation from '../components/Workspace/config/SharingConversation.vue'
import { FLAG_PROP, LIST_PROP, NULLABLE_OBJECT_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'
import { DASHBOARD_ICON_CATALOGUE } from '../services/iconCatalogue.js'
import { uploadDataUrl, uploadFile } from '../services/resourceService.js'
import { usePublicShareStore } from '../stores/publicShares.js'
import { isCollaborative } from '../utils/dashboardDiscussion.js'
import { accentCheck, normaliseHex } from '../utils/dashboardLook.js'
import { hierarchyPresets } from '../utils/hierarchyPresets.js'
import { instanceThemeName } from '../utils/instanceTheme.js'
import { readSharingPolicy } from '../utils/loadInitialState.js'
import { logger } from '../utils/logger.js'
import { NEW_TAB, SAME_TAB, SIDE_PANEL } from '../utils/tileLinkTarget.js'

/* Loaded when their tab opens: they bring the export editor and the store. */
const SharingEmbedTokens = defineAsyncComponent(() => import('../components/Workspace/config/SharingEmbedTokens.vue'))
const ExportsTab = defineAsyncComponent(() => import('../components/Workspace/config/ExportsTab.vue'))
const PublicationTab = defineAsyncComponent(() => import('../components/Workspace/config/PublicationTab.vue'))
const VersionsTab = defineAsyncComponent(() => import('../components/Workspace/config/VersionsTab.vue'))

const PERMISSION_OPTIONS = [
	{ value: 'view_only', label: 'View only' },
	{ value: 'add_only', label: 'Add only' },
	{ value: 'full', label: 'Full access' },
]

/**
 * The ids of picked hierarchy choices, as one comparable string.
 *
 * @param {Array<{id: string}>} choices The choices.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */
function hierarchyIds(choices) {
	return (choices || []).map((choice) => choice.id).join('\n')
}

export default {
	name: 'DashboardConfigModal',

	components: {
		DetailsTab,
		ExportsTab,
		LanguagesTab,
		SharingConversation,
		SharingEmbedTokens,
		PublicationTab,
		VersionsTab,
		NcModal,
		NcButton,
		NcTextField,
		NcSelect,
		NcCheckboxRadioSwitch,
		Delete,
		ContentSave,
		Plus,
		Close,
		Account,
		AccountGroup,
		ContentCopy,
		Check,
		Lock,
		CnIconBrowser,
	},

	inject: {
		// Server-computed admin flag (PageController initial state); only
		// decides whether the embed tokens show. The server checks again.
		isAdmin: {
			from: 'isAdmin',
			default: false,
		},
	},

	props: {
		open: FLAG_PROP,

		dashboard: NULLABLE_OBJECT_PROP,

		canDelete: FLAG_PROP,

		mode: {
			type: String,
			default: 'edit',
			validator: (v) => ['edit', 'create'].includes(v),
		},

		/*
		 * Wave3.8 — UUID currently pinned as the user's default
		 * dashboard, or '' when none. Drives the initial state of
		 * the "Default dashboard" switch. The host (Views.vue)
		 * fetches it once via `GET /api/dashboards/default` and
		 * passes it down both here and to `DashboardSwitcherSidebar`.
		 */
		defaultUuid: TEXT_PROP,

		/**
		 * Dashboards that may be chosen as parent (dashboard-tree-navigation).
		 * The server refuses a cycle; the host leaves the dashboard itself out.
		 */
		parentOptions: LIST_PROP,

		/** Why the last save was refused, shown in the General tab. */
		saveError: TEXT_PROP,

		/** Whether the Publication tab is offered: the owner or an administrator. */
		canManagePublication: FLAG_PROP,

		/** Whether the Exports tab is offered. */
		canScheduleExports: FLAG_PROP,

		/** Whether the Versions tab is offered: the owner or an administrator. */
		canViewVersions: FLAG_PROP,

		/** This dashboard and its widgets as export subjects: `{type, id, label}`. */
		exportSubjects: LIST_PROP,

		/**
		 * Tab to land on when the modal opens (dashboard-sharing spec). The
		 * top-bar share action passes `'sharing'` so the share button lands
		 * directly on the Sharing tab.
		 */
		initialTab: {
			type: String,
			default: 'general',
			validator: (v) => ['general', 'sharing', 'default', 'publication', 'exports'].includes(v),
		},
	},

	emits: ['close', 'save', 'delete', 'setDefault', 'versionRestored'],

	data() {
		return {
			currentTab:
				this.initialTab === 'default' ? 'general' : this.initialTab || 'general',

			form: {
				name: '',
				description: '',
				icon: DEFAULT_ICON,
				/*
				 * Wave3.8 — toggle state for the "Default dashboard"
				 * switch. Initialised from `defaultUuid === dashboard.uuid`
				 * in `syncFormFromDashboard`; emitted as a separate
				 * `set-default` event from `onSave` only when its value
				 * changed since the modal opened (so re-saving without
				 * touching the toggle never re-pins).
				 */
				isDefault: false,
				// dashboard-tree-navigation: `{uuid, name}` of the parent
				// (uuid '' for none) and the web address name.
				parent: null,
				slug: '',
				// launcher-tile-open-behaviour D2: `{id, label}`, id '' for
				// the link-type rule.
				defaultLinkTarget: null,
				// REQ-CWF-011: the picked hierarchy choices, `{id, name, descriptor}`.
				hierarchies: [],
			},

			saving: false,
			// REQ-THM-001: the look being edited, and the stored one it started from.
			look: { accent: '', background: '', resource: '' },
			initialLook: { accent: '', background: '', resource: '' },
			lookUploading: false,
			lookUploadError: '',
			// Server snapshot of shares as last loaded; used to compute dirty state.
			serverShares: [],
			// Local in-progress edit list; mutations buffer here until Save.
			localShares: [],
			shareeOptions: [],
			// Bounded suggestion list fetched once per modal-open with an
			// empty query — shown when the picker has no search text so the
			// dropdown is never blank on focus (parity with the core share
			// dialog).
			shareeSuggestions: [],
			shareeLoading: false,
			shareeSearchSeq: 0,
			// Public-share (anonymous read-only link) state.
			publicShares: [],
			publicSharesLoading: false,
			creatingPublicShare: false,
			newSharePassword: '',
			newShareExpiry: '',
			copiedToken: '',
		}
	},

	computed: {
		/**
		 * The instance theme's name, shown read-only on Details.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-dashboard-theme-readonly/specs/dashboards/spec.md#requirement-the-details-tab-shows-the-instance-theme-read-only-req-thm-004
		 */
		themeName() {
			return instanceThemeName()
		},

		/**
		 * The hierarchies an owner can pick: the presets, then any other
		 * hierarchy the dashboard stores, so saving does not drop it.
		 *
		 * @return {Array<{id: string, name: string, descriptor: object}>}
		 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
		 */
		hierarchyChoices() {
			const presets = hierarchyPresets()
			const stored = Array.isArray(this.dashboard?.hierarchies) ? this.dashboard.hierarchies : []
			const others = stored
				.filter((entry) => entry && typeof entry.id === 'string' && !presets.some((preset) => preset.id === entry.id))
				.map((entry) => ({ id: entry.id, name: entry.id, descriptor: entry }))
			return [...presets, ...others]
		},

		/**
		 * The dashboard default choices for how tiles open.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		linkTargetChoices() {
			return [
				{ id: '', label: t('launchpad', 'Web addresses in a new tab, apps in the same tab') },
				{ id: SAME_TAB, label: t('launchpad', 'Same tab') },
				{ id: NEW_TAB, label: t('launchpad', 'New tab') },
				{ id: SIDE_PANEL, label: t('launchpad', 'Panel beside the tiles') },
			]
		},

		/**
		 * Parent choices: "No parent" first, then the other dashboards.
		 *
		 * @return {Array<{uuid: string, name: string}>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		parentChoices() {
			const self = this.dashboard?.uuid
			return [
				{ uuid: '', name: t('launchpad', 'No parent') },
				...this.parentOptions
					.filter((d) => d?.uuid && d.uuid !== self)
					.map((d) => ({ uuid: d.uuid, name: d.name })),
			]
		},

		isCreate() {
			return this.mode === 'create'
		},

		/**
		 * The shared MDI icon catalogue passed to CnIconBrowser — the single
		 * picker source every admin surface reads, so the picker cannot drift
		 * from the registry (REQ-ICON-003).
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-003
		 * @return {object} the frozen icon catalogue.
		 */
		iconCatalogue() {
			return DASHBOARD_ICON_CATALOGUE
		},

		/**
		 * The icon-upload transport handed to CnIconBrowser. Exposed on the
		 * instance (computed) so the template can reference the module-imported
		 * `uploadDataUrl` — a bare module import isn't visible in template scope.
		 *
		 * This is the custom-URL half of the REQ-ICON-008 dual input: it is
		 * what turns an uploaded file into the URL the icon field stores.
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-008
		 * @return {(dataUrl: string) => Promise<{url: string, name: string, size: number}>} the data-URL upload function.
		 */
		iconUploadFn() {
			return uploadDataUrl
		},

		/**
		 * Config-drawer tab descriptors. The Sharing tab is only offered when
		 * the user can manage shares — REQ-SHARE-001 is owner-only, so a
		 * recipient must not be shown a share-management surface at all.
		 * Publication is for the owner or an administrator (REQ-SCHEDUI-001),
		 * and so are the Versions (board LpVersies).
		 *
		 * @spec openspec/specs/dashboard-sharing/spec.md#req-share-001
		 * @spec openspec/specs/dashboards/spec.md#requirement-req-dash-039-versions-are-a-tab-of-the-dashboard-settings
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 * @spec openspec/specs/scheduled-exports/spec.md#requirement-req-sch-011-scheduled-exports-live-in-the-dashboard-settings
		 * @spec openspec/changes/archive/2026-11-24-dashboard-theme-readonly/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 * @return {Array<{id: string, label: string}>}
		 */
		tabs() {
			const hasUuid = Boolean(this.dashboard?.uuid)
			return [
				{ id: 'general', label: t('launchpad', 'Details'), icon: FormatListBulleted, offered: true },
				// dashboard-language-and-details-tabs: language versions, for the
				// owner (a dashboard needs a uuid first, so the strip is hidden
				// while creating).
				{ id: 'languages', label: t('launchpad', 'Languages'), icon: Translate, offered: this.canManageShares && hasUuid },
				{ id: 'sharing', label: t('launchpad', 'Sharing'), icon: ShareVariant, count: this.localShares.length, offered: this.canManageShares },
				{ id: 'publication', label: t('launchpad', 'Publication'), icon: CalendarClock, offered: this.canManagePublication && hasUuid },
				{ id: 'exports', label: t('launchpad', 'Exports'), icon: CalendarExport, offered: this.canScheduleExports && hasUuid },
				{ id: 'versions', label: t('launchpad', 'Versions'), icon: History, offered: this.canViewVersions && hasUuid },
			]
				.filter((tab) => tab.offered)
				.map(({ offered, ...tab }) => tab)
		},

		/**
		 * Whether the dashboard is a draft or still scheduled, so share
		 * recipients do not see it yet (REQ-DASH-036).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboard-sharing/spec.md
		 */
		isUnpublished() {
			const status = this.dashboard?.publicationStatus || 'published'
			return status === 'draft' || status === 'scheduled'
		},

		/**
		 * Whether the dashboard is shared: a group dashboard or one with stored shares.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		isSharedDashboard() {
			return isCollaborative({ dashboard: this.dashboard, ownShares: this.serverShares })
		},

		/** @spec openspec/specs/dashboards/spec.md */
		/**
		 * What the Sharing tab may offer the viewer (REQ-SHARE-015).
		 *
		 * @return {{mayShare: boolean, publicLinksAllowed: boolean}}
		 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
		 */
		sharingPolicy() {
			return readSharingPolicy()
		},

		/**
		 * Whether the viewer may create a public link: they may share and
		 * public links are on (REQ-SHARE-015). Existing links stay listed.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
		 */
		canCreatePublicLink() {
			return this.sharingPolicy.mayShare && this.sharingPolicy.publicLinksAllowed
		},

		canManageShares() {
			// Only the owner can see / manage shares.
			return (
				this.dashboard?.isOwner !== false
				&& (this.dashboard?.id ?? null) !== null
			)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		modalTitle() {
			return this.isCreate
				? t('launchpad', 'Create dashboard')
				: t('launchpad', 'Dashboard settings')
		},

		/** @spec openspec/specs/dashboards/spec.md */
		primaryButtonLabel() {
			if (this.saving) {
				return this.isCreate
					? t('launchpad', 'Creating…')
					: t('launchpad', 'Saving…')
			}
			return this.isCreate ? t('launchpad', 'Create') : t('launchpad', 'Save')
		},

		/** @spec openspec/specs/dashboards/spec.md */
		permissionOptions() {
			return PERMISSION_OPTIONS.map((o) => ({
				value: o.value,
				label: t('launchpad', o.label),
			}))
		},

		selectedPermission: {
			/** @spec openspec/specs/dashboards/spec.md */
			get() {
				const level = this.dashboard?.permissionLevel || 'full'
				return (
					this.permissionOptions.find((o) => o.value === level)
					|| this.permissionOptions[2]
				)
			},

			/** @spec openspec/specs/dashboards/spec.md */
			set() {
				// Read-only — admin-managed.
			},
		},

		/** @spec openspec/specs/dashboards/spec.md */
		canSave() {
			return this.form.name.trim().length > 0 && this.lookValid
		},

		/** @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001 */
		lookAccentHex() {
			return normaliseHex(this.look.accent)
		},

		/** @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001 */
		lookImageUrl() {
			return this.look.resource
				? generateUrl('/apps/launchpad/resource/' + encodeURIComponent(this.look.resource))
				: ''
		},

		/** @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001 */
		lookBackgroundHex() {
			return normaliseHex(this.look.background)
		},

		/**
		 * A colour field that holds something other than a colour.
		 *
		 * @return {string} the message, or '' when both fields are empty or colours.
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 */
		lookFormatError() {
			const bad = [this.look.accent, this.look.background].some(
				(value) => String(value).trim() !== '' && normaliseHex(value) === null,
			)
			return bad ? t('launchpad', 'Enter colors like #1f5c3a.') : ''
		},

		/** @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002 */
		lookAccentCheck() {
			return accentCheck(this.lookAccentHex, this.lookBackgroundHex)
		},

		/** @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002 */
		lookValid() {
			return this.lookFormatError === '' && this.lookAccentCheck.readable
		},

		/**
		 * The look fields that changed, as the update route takes them ('' clears),
		 * or null when nothing changed, so a rename stays a metadata edit.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 */
		lookPayload() {
			const fields = {
				themeAccent: ['accent', this.lookAccentHex ?? ''],
				themeBackgroundColor: ['background', this.lookBackgroundHex ?? ''],
				themeBackgroundResource: ['resource', this.look.resource || ''],
			}
			const payload = {}
			for (const [field, [key, value]] of Object.entries(fields)) {
				if (value !== (this.initialLook[key] || '')) {
					payload[field] = value
				}
			}
			return Object.keys(payload).length > 0 ? payload : null
		},

		/** @spec openspec/specs/dashboards/spec.md */
		sharesDirty() {
			if (this.localShares.length !== this.serverShares.length) return true
			const key = (s) => `${s.shareType}:${s.shareWith}:${s.permissionLevel}`
			const a = [...this.localShares].map(key).sort()
			const b = [...this.serverShares].map(key).sort()
			return a.some((v, i) => v !== b[i])
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Reset the buffered share state whenever the modal closes, so
			 * the next open starts from the server's list rather than a
			 * stale local edit.
			 *
			 * @param {boolean} isOpen Whether the modal is now open.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(isOpen) {
				if (!isOpen) {
					this.serverShares = []
					this.localShares = []
					this.shareeOptions = []
					this.shareeSuggestions = []
					this.publicShares = []
					this.newSharePassword = ''
					this.newShareExpiry = ''
					this.copiedToken = ''
					return
				}
				// Land on the requested tab (dashboard-sharing spec: the
				// top-bar share action opens directly on Sharing). Fall back
				// to General if the requested tab is the share tab but the
				// user cannot manage shares.
				// `default` was its own tab; the switch now sits under Details.
				const wanted
					= this.initialTab === 'default' ? 'general' : this.initialTab || 'general'
				this.currentTab =
					wanted === 'sharing' && !this.canManageShares
						? 'general'
						: wanted
				if (this.isCreate) {
					this.form.name = ''
					this.form.description = ''
					this.form.icon = DEFAULT_ICON
					this.form.isDefault = false
				} else if (this.dashboard) {
					this.form.name = this.dashboard.name || ''
					this.form.description = this.dashboard.description || ''
					this.form.slug = this.dashboard.slug || ''
					this.form.defaultLinkTarget =
						this.linkTargetChoices.find(
							(c) => c.id === (this.dashboard.defaultLinkTarget || ''),
						) || this.linkTargetChoices[0]
					this._initialLinkTarget = this.form.defaultLinkTarget.id
					this.form.hierarchies = this.storedHierarchyChoices()
					this._initialHierarchies = hierarchyIds(this.form.hierarchies)
					this.form.parent =
						this.parentChoices.find(
							(p) => p.uuid === (this.dashboard.parentUuid || ''),
						) || this.parentChoices[0]
					// Persisted icon may be NULL/empty, a registry key, a custom
					// URL, or an SVG path string (CnIconBrowser). Any non-empty
					// value is kept verbatim and rendered by CnDashboardIcon;
					// only null/empty falls back to DEFAULT_ICON
					// (REQ-ICON-002 + REQ-ICON-009).
					this.form.icon = this.dashboard.icon || DEFAULT_ICON
					// REQ-THM-001: the stored look, and a snapshot to send only what changes.
					this.initialLook = {
						accent: this.dashboard.themeAccent || '',
						background: this.dashboard.themeBackgroundColor || '',
						resource: this.dashboard.themeBackgroundResource || '',
					}
					this.look = { ...this.initialLook }
					this.lookUploadError = ''
					// Wave3.8 — initial toggle state mirrors whether
					// THIS dashboard's UUID matches the user's pinned
					// default. Snapshot for the dirty-check in onSave.
					this.form.isDefault =
						!!this.dashboard.uuid
						&& this.dashboard.uuid === this.defaultUuid
					this._initialIsDefault = this.form.isDefault
					if (this.canManageShares) {
						this.loadShares()
						this.loadShareeSuggestions()
						this.loadPublicShares()
					}
				}
			},
		},
	},

	methods: {
		/**
		 * The conversation switch was stored: let the open page follow it.
		 *
		 * @param {number} value 1 shows the conversation, 0 hides it.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		async onConversationChanged(value) {
			// Loaded on use: the store module reads the user's language on import.
			const { useDashboardStore } = await import('../stores/dashboard.js')
			useDashboardStore().applyFieldsPatch(this.dashboard?.uuid, { conversationEnabled: value })
		},

		t,

		/**
		 * The choices of the hierarchies the dashboard stores, in stored order.
		 *
		 * @return {Array<{id: string, name: string, descriptor: object}>}
		 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
		 */
		storedHierarchyChoices() {
			const stored = Array.isArray(this.dashboard?.hierarchies) ? this.dashboard.hierarchies : []
			return stored
				.map((entry) => this.hierarchyChoices.find((choice) => choice.id === entry?.id))
				.filter(Boolean)
		},

		/**
		 * Upload a chosen background image through LaunchPad's resource
		 * uploads and keep its file name (REQ-THM-001).
		 *
		 * @param {Event} event The file input's change event.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 */
		/**
		 * Open the browser's file picker behind the styled upload button.
		 *
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 */
		pickLookImage() {
			this.$refs.lookImageFile?.click()
		},

		async onLookImage(event) {
			const file = event?.target?.files?.[0]
			if (!file) {
				return
			}
			this.lookUploading = true
			this.lookUploadError = ''
			try {
				const resource = await uploadFile(file)
				this.look.resource = resource.name
			} catch (error) {
				logger.error('Failed to upload the background image:', error)
				this.lookUploadError = t('launchpad', 'Failed to upload image')
			} finally {
				this.lookUploading = false
				if (event?.target) {
					event.target.value = ''
				}
			}
		},

		/**
		 * Resolve a permission level to its select option, falling back to
		 * the first option for unknown levels.
		 *
		 * @param {string} level Stored permission level, e.g. `read`/`full`.
		 * @return {object} Matching option from `permissionOptions`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		permissionOptionFor(level) {
			return (
				this.permissionOptions.find((o) => o.value === level)
				|| this.permissionOptions[0]
			)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		async loadShares() {
			try {
				const response = await api.listShares(this.dashboard.id)
				const fresh = response.data || []
				this.serverShares = fresh.map((s) => ({ ...s }))
				this.localShares = fresh.map((s) => ({ ...s }))
			} catch (error) {
				logger.error('Failed to load shares:', error)
				this.serverShares = []
				this.localShares = []
			}
		},

		/**
		 * Load the dashboard's active anonymous public-share links.
		 *
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 */
		async loadPublicShares() {
			if (!this.dashboard?.uuid) {
				this.publicShares = []
				return
			}
			this.publicSharesLoading = true
			try {
				this.publicShares = await usePublicShareStore().fetchShares(
					this.dashboard.uuid,
				)
			} catch (error) {
				logger.error('Failed to load public shares:', error)
				this.publicShares = []
			} finally {
				this.publicSharesLoading = false
			}
		},

		/**
		 * Mint a new public link, optionally password-protected and/or expiring.
		 *
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
		 */
		async onCreatePublicShare() {
			// REQ-SHARE-015: the server refuses a link without an expiry date.
			if (!this.dashboard?.uuid || !this.newShareExpiry) {
				return
			}
			this.creatingPublicShare = true
			try {
				// The date input yields `YYYY-MM-DD`; the backend expects an
				// ISO 8601 instant, so pin expiry to end-of-day UTC.
				const expiresAt = `${this.newShareExpiry}T23:59:59Z`
				await usePublicShareStore().createShare(this.dashboard.uuid, {
					password: this.newSharePassword || null,
					expiresAt,
				})
				this.newSharePassword = ''
				this.newShareExpiry = ''
				await this.loadPublicShares()
			} catch (error) {
				logger.error('Failed to create public share:', error)
			} finally {
				this.creatingPublicShare = false
			}
		},

		/**
		 * Soft-revoke a public link.
		 *
		 * @param {number} id The share id.
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 */
		async onRevokePublicShare(id) {
			if (!this.dashboard?.uuid) {
				return
			}
			try {
				await usePublicShareStore().revokeShare(this.dashboard.uuid, id)
				await this.loadPublicShares()
			} catch (error) {
				logger.error('Failed to revoke public share:', error)
			}
		},

		/**
		 * Absolute, shareable URL for a public-share token.
		 *
		 * @param {string} token The share token.
		 * @return {string} the full `…/apps/launchpad/s/<token>` URL.
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 */
		publicShareUrl(token) {
			return (
				window.location.origin
				+ generateUrl('/apps/launchpad/s/{token}', { token })
			)
		},

		/**
		 * Copy a link to the clipboard and flag the row as copied briefly.
		 *
		 * @param {object} share The share row.
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 */
		async copyPublicShareUrl(share) {
			try {
				await navigator.clipboard.writeText(this.publicShareUrl(share.token))
				this.copiedToken = share.token
			} catch (error) {
				logger.error('Clipboard write failed:', error)
			}
		},

		/**
		 * Fetch the bounded empty-query suggestion list once per modal-open.
		 * Mapped through the same shape as search results so options are
		 * interchangeable. Errors degrade to an empty list (the picker then
		 * behaves like the pre-suggestion version).
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async loadShareeSuggestions() {
			try {
				const response = await api.searchSharees('')
				this.shareeSuggestions = this.mapShareeResults(response)
				if (this.shareeOptions.length === 0) {
					this.shareeOptions = [...this.shareeSuggestions]
				}
			} catch (error) {
				logger.error('Sharee suggestion preload failed:', error)
				this.shareeSuggestions = []
			}
		},

		/**
		 * Map a sharee API response to flat picker options.
		 *
		 * @param {object} response Axios response from `api.searchSharees`.
		 * @return {Array<object>} Combined user + group options.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		mapShareeResults(response) {
			const users = (response.data?.users || []).map((u) => ({
				key: `user:${u.id}`,
				shareType: 'user',
				id: u.id,
				displayName: u.displayName,
			}))
			const groups = (response.data?.groups || []).map((g) => ({
				key: `group:${g.id}`,
				shareType: 'group',
				id: g.id,
				displayName: g.displayName,
			}))
			return [...users, ...groups]
		},

		/**
		 * Search sharees as the user types; an empty query restores the
		 * preloaded suggestions.
		 *
		 * @param {string} query Raw search text from the sharee picker.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onShareeSearch(query) {
			const trimmed = (query || '').trim()
			if (trimmed.length === 0) {
				// Cleared input — fall back to the preloaded suggestions.
				this.shareeOptions = [...this.shareeSuggestions]
				return
			}
			if (trimmed.length === 1) {
				// Backend blocks 1-char queries (enumeration guard) — keep
				// whatever is shown rather than flashing an empty list.
				return
			}
			const seq = ++this.shareeSearchSeq
			this.shareeLoading = true
			try {
				const response = await api.searchSharees(trimmed)
				if (seq !== this.shareeSearchSeq) return // stale result
				this.shareeOptions = this.mapShareeResults(response)
			} catch (error) {
				logger.error('Sharee search failed:', error)
				this.shareeOptions = []
			} finally {
				this.shareeLoading = false
			}
		},

		/**
		 * Buffer a picked sharee locally. Nothing is written to the server
		 * until the user saves.
		 *
		 * @param {object|null} option Sharee option chosen in the picker.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onShareeSelected(option) {
			if (!option) return
			// Buffer locally — do not write to server until Save.
			const exists = this.localShares.find(
				(s) => s.shareType === option.shareType && s.shareWith === option.id,
			)
			if (!exists) {
				this.localShares.push({
					shareType: option.shareType,
					shareWith: option.id,
					permissionLevel: 'view_only',
					displayName: option.displayName,
				})
			}
			// Reset to suggestions so the next open isn't stuck on the
			// previous search results.
			this.shareeOptions = [...this.shareeSuggestions]
		},

		/**
		 * Change one buffered share's permission level.
		 *
		 * @param {number} idx Index of the share in `localShares`.
		 * @param {object|null} option Newly selected permission option.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onShareLevelChange(idx, option) {
			if (!option) return
			const share = this.localShares[idx]
			if (!share || option.value === share.permissionLevel) return
			this.localShares[idx] = {
				...share,
				permissionLevel: option.value,
			}
		},

		/**
		 * Drop one buffered share. Removal reaches the server on save.
		 *
		 * @param {number} idx Index of the share in `localShares`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onShareRemove(idx) {
			this.localShares.splice(idx, 1)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		async onSave() {
			if (!this.canSave) return
			this.saving = true
			try {
				// Persist share changes first (REQ-SHARE-009 bulk replace) so
				// notifications fire before the modal closes. Skip on create
				// (no dashboard id yet) and when the user has no manage rights.
				if (!this.isCreate && this.canManageShares && this.sharesDirty) {
					try {
						await api.replaceShares(
							this.dashboard.id,
							this.localShares.map((s) => ({
								shareType: s.shareType,
								shareWith: s.shareWith,
								permissionLevel: s.permissionLevel,
							})),
						)
					} catch (error) {
						logger.error('Failed to replace shares:', error)
					}
				}
				await this.$emit('save', {
					id: this.dashboard?.id ?? null,
					name: this.form.name.trim(),
					description: this.form.description.trim(),
					icon: this.form.icon || null,
					...(this.isCreate
						? {}
						: {
								parentUuid: this.form.parent?.uuid ?? '',
								slug: this.form.slug.trim(),
								...(this.lookPayload ? { look: this.lookPayload } : {}),
								// Only when changed: it needs full edit rights, so a
								// metadata-only editor's save must not carry it.
								...((this.form.defaultLinkTarget?.id ?? '') !== this._initialLinkTarget
									? { defaultLinkTarget: this.form.defaultLinkTarget?.id ?? '' }
									: {}),
								// REQ-CWF-011: only when changed, for the same reason.
								...(hierarchyIds(this.form.hierarchies) !== this._initialHierarchies
									? { hierarchies: this.form.hierarchies.map((choice) => choice.descriptor) }
									: {}),
							}),
				})

				// Wave3.8 — propagate the default-pin toggle when its
				// state changed since the modal opened. Emitted after
				// save so the host can issue the API call without
				// blocking the rest of the save flow. Skip on create
				// (no UUID yet — pin via the per-row cog after the
				// row appears in the list).
				if (
					!this.isCreate
					&& this.dashboard
					&& this.form.isDefault !== this._initialIsDefault
				) {
					this.$emit('setDefault', {
						uuid: this.dashboard.uuid,
						isDefault: this.form.isDefault,
					})
				}
			} finally {
				this.saving = false
			}
		},

		/** @spec openspec/specs/dashboards/spec.md */
		onDelete() {
			if (!this.canDelete) return
			this.$emit('delete', this.dashboard)
		},
	},
}
</script>

<style scoped>
.dashboard-config {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

/* Public-share (anonymous read-only link) section. */
.dashboard-config__public {
	margin-top: 12px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.dashboard-config__public-list {
	list-style: none;
	margin: 8px 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.dashboard-config__public-row {
	display: flex;
	align-items: center;
	gap: 6px;
}

.dashboard-config__public-url {
	flex: 1 1 auto;
	min-width: 0;
	font-family: monospace;
	font-size: 0.85em;
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.dashboard-config__public-badge {
	display: inline-flex;
	color: var(--color-text-maxcontrast);
}

.dashboard-config__public-expiry {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.dashboard-config__public-create {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	margin-top: 8px;
	flex-wrap: wrap;
}

.dashboard-config__public-date {
	height: 44px;
	padding: 0 8px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.dashboard-config__title {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}

/* Tabs as boards LpDashboardInstellingen and LpDelen draw them: words on
   the strip's rule, 44px high, the active one bold over a 3px primary line. */
.dashboard-config__tabs {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-start;
	gap: 4px;
	list-style: none;
	/* Against the dialog's 20px gap: 12px under the title, 16px over the panel. */
	margin: -8px 0 -4px;
	padding: 0;
	border-bottom: 1px solid var(--color-border);
}

.dashboard-config__tab-item {
	display: flex;
	flex: none;
}

.dashboard-config__tabs .dashboard-config__tab {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	width: auto;
	height: 44px;
	min-height: 44px;
	margin: 0;
	padding: 0 14px;
	border: 0;
	/* Core's hover and focus rules for buttons colour the border with a
	   stronger selector; the underline is the tab's state, not the pointer's. */
	border-bottom: 3px solid transparent !important;
	border-radius: 0;
	background: transparent;
	cursor: pointer;
	font-family: var(--font-face);
	font-size: 15px;
	font-weight: 500;
	white-space: nowrap;
	color: color-mix(in srgb, var(--color-main-text) 85%, var(--color-main-background));
}

.dashboard-config__tabs .dashboard-config__tab:hover {
	background: transparent;
	border-bottom-color: var(--color-border) !important;
}

.dashboard-config__tabs .dashboard-config__tab--active,
.dashboard-config__tabs .dashboard-config__tab--active:hover {
	color: var(--color-main-text);
	border-bottom-color: var(--color-primary-element) !important;
	font-weight: 700;
}

/*
 * Phone width (NcModal's own breakpoint): four labelled tabs need more room
 * than the screen has, so the strip scrolls inside its own row instead of
 * widening the modal body.
 */
@media only screen and (max-width: 512px) {
	.dashboard-config__tabs {
		overflow-x: auto;
	}

	.dashboard-config__tab-item {
		flex: 1 0 auto;
	}

	.dashboard-config__tab {
		white-space: nowrap;
	}
}

.dashboard-config__panel {
	display: flex;
	flex-direction: column;
}

.dashboard-config__field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

/*
 * The sharee dropdown must paint ABOVE the fields that follow it.
 *
 * The modal body is a column of `__field` blocks. An NcSelect dropdown
 * opens downward out of its field and lands over the next ones, and
 * because those are LATER siblings with no stacking context of their own,
 * they win and swallow the click.
 *
 * @nextcloud/vue 9.10 made this reachable by giving NcSelect a floating
 * label (#8570): the control is taller, so the dropdown reaches further
 * down. The Playwright trace names the interceptors exactly:
 *
 *   - locator resolved to <span class="sharee-option">
 *   - attempting click action
 *     - element is visible, enabled and stable
 *     - <div class="dashboard-config__field dashboard-config__public"> ...
 *     - <p class="dashboard-config__hint">Not shared with anyone yet.</p> ...
 *
 * The option was never unstable; it was covered. Only the field holding a
 * select is raised, so nothing else changes.
 */
.dashboard-config__field--select {
	position: relative;
	z-index: 2;
}

/*
 * A field holding an NcSelect must paint ABOVE the fields that follow it.
 *
 * The modal is a column of fields. An NcSelect dropdown opens downward out
 * of its own field and lands over the next one; those are LATER siblings
 * with no stacking context, so they win and swallow the click.
 *
 * @nextcloud/vue 9.10 made this reachable by giving NcSelect a floating
 * label (#8570), which makes the control taller and pushes the dropdown
 * far enough down to reach them. The Playwright trace names the
 * interception rather than leaving it to be guessed:
 *
 *   - locator resolved to <span class="sharee-option">
 *   - attempting click action
 *     - element is visible, enabled and stable
 *     - <p class="dashboard-config__hint">Not shared with anyone yet.</p>
 *       ... intercepts pointer events
 *
 * The option was never unstable; it was covered.
 */
.dashboard-config__field--select {
	position: relative;
	z-index: 2;
}

.dashboard-config__label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
}

.dashboard-config__textarea {
	width: 100%;
	resize: vertical;
	min-height: 80px;
	padding: 8px 12px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
	font-size: 14px;
}

.dashboard-config__textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.dashboard-config__icon-picker {
	display: flex;
	align-items: center;
	gap: 12px;
}

.dashboard-config__select {
	flex: 1;
	padding: 6px 12px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
	font-size: 14px;
}

.dashboard-config__select:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.dashboard-config__icon-preview {
	display: inline-flex;
	width: 32px;
	height: 32px;
	align-items: center;
	justify-content: center;
	color: var(--color-main-text);
}

.dashboard-config__hint {
	margin: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.dashboard-config__hint--dirty {
	color: var(--color-warning, #c9a227);
	margin-top: 4px;
}

.dashboard-config__actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
	margin-top: 8px;
}

.dashboard-config__actions-right {
	display: flex;
	gap: 8px;
	margin-left: auto;
}

.dashboard-config__shares {
	list-style: none;
	margin: 4px 0 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.dashboard-config__share {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}

.dashboard-config__share-name {
	flex: 1;
	min-width: 0;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dashboard-config__share-level {
	min-width: 140px;
}

.sharee-option {
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.dashboard-config__error {
	color: var(--color-error-text);
}

/* The count on the Delen tab (board LpDashboardInstellingen). */
.dashboard-config__tab-count {
	min-width: 22px;
	box-sizing: border-box;
	padding: 0 6px;
	border-radius: 11px;
	background: var(--color-border);
	font-size: 12px;
	font-weight: 700;
	line-height: 22px;
	text-align: center;
}

.dashboard-config__look {
	margin-top: 16px;
}

.dashboard-config__section-heading {
	margin: 0 0 8px;
	font-size: 1.1em;
	font-weight: bold;
}

/* .dashboard-config__field stacks its children (flex-direction: column);
   a look row puts the colour picker NEXT TO its field, on one row. */
.dashboard-config__look-row {
	display: flex;
	flex-direction: row;
	align-items: flex-end;
	gap: 8px;
}

.dashboard-config__look-row > :first-child {
	flex: 1 1 auto;
	min-inline-size: 0;
}

.dashboard-config__swatch {
	flex: 0 0 auto;
}

.dashboard-config__image-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.dashboard-config__image-preview {
	inline-size: 64px;
	block-size: 40px;
	object-fit: cover;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
}

.dashboard-config__image-name {
	overflow: hidden;
	max-inline-size: 16em;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
}

/* The real file input stays in the DOM for the picker, out of sight. */
.dashboard-config__file {
	position: absolute;
	inline-size: 1px;
	block-size: 1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
}

.dashboard-config__swatch {
	inline-size: 44px;
	block-size: 44px;
	padding: 2px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	cursor: pointer;
}
</style>
