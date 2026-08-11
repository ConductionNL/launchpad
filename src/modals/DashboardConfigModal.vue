<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		size="normal"
		:name="modalTitle"
		@close="$emit('close')">
		<div class="dashboard-config">
			<h2 class="dashboard-config__title">
				{{ modalTitle }}
			</h2>

			<!-- Tab strip (dashboard-sharing spec). Sharing is no longer a
			     free-floating field set in the modal body — it lives in its
			     own Sharing tab. Tabs are hidden in create mode (nothing to
			     share / pin until the dashboard exists). Markup + styling
			     follow the fleet tablist pattern (nc-vue CnTabbedFormDialog /
			     NC sidebar tabs): justified tabs, icon + label, active
			     primary underline. -->
			<ul v-if="!isCreate"
				class="dashboard-config__tabs"
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
						:class="{ 'dashboard-config__tab--active': currentTab === tab.id }"
						:data-test="`config-tab-${tab.id}`"
						@click="currentTab = tab.id">
						<component :is="tab.icon" v-if="tab.icon" :size="16" />
						<span>{{ tab.label }}</span>
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
						:model-value="form.name"
						:label="t('launchpad', 'Title')"
						:placeholder="t('launchpad', 'My dashboard')"
						data-testid="dashboard-name-input"
						@update:modelValue="form.name = $event" />
				</div>

				<div class="dashboard-config__field">
					<label class="dashboard-config__label" for="dashboard-config-description">
						{{ t('launchpad', 'Description') }}
					</label>
					<textarea
						id="dashboard-config-description"
						v-model="form.description"
						class="dashboard-config__textarea"
						rows="3"
						data-testid="dashboard-description-input"
						:placeholder="t('launchpad', 'What is this dashboard for?')" />
				</div>

				<!-- Icon browser — searchable grid over the full MDI set plus a
			     Custom tab that accepts an upload (capability
			     `custom-icon-upload-pattern`). The same v-model holds whichever
			     it emits: an SVG path string or a /apps/launchpad/resource/...
			     URL (REQ-ICON-003 + REQ-ICON-008..009). -->
				<div class="dashboard-config__field">
					<CnIconBrowser
						inline
						:label="t('launchpad', 'Icon')"
						:value="form.icon"
						:icons="iconCatalogue"
						:upload-fn="iconUploadFn"
						@input="form.icon = $event" />
				</div>
			</div>
			<!-- /general panel -->

			<!--
				Wave3.8 — explicit "default dashboard" pin. Lives in its own
				Default tab now (dashboard-sharing spec tab split). Wrapped in
				`v-if="!isCreate"` because pinning a not-yet-saved dashboard is
				meaningless.
			-->
			<div
				v-show="!isCreate && currentTab === 'default'"
				id="config-panel-default"
				role="tabpanel"
				data-test="config-panel-default"
				class="dashboard-config__panel">
				<div v-if="!isCreate" class="dashboard-config__field dashboard-config__field--toggle">
					<NcCheckboxRadioSwitch
						:model-value="form.isDefault"
						type="switch"
						@update:modelValue="form.isDefault = $event">
						<strong>{{ t('launchpad', 'Default dashboard') }}</strong>
						<span class="dashboard-config__hint">
							{{ t('launchpad', 'Open this dashboard automatically when visiting LaunchPad.') }}
						</span>
					</NcCheckboxRadioSwitch>
				</div>
			</div>
			<!-- /default panel -->

			<!-- Sharing panel (dashboard-sharing spec). The sharee picker and
			     per-share permission rows are reachable ONLY from this tab. -->
			<div
				v-show="!isCreate && currentTab === 'sharing'"
				id="config-panel-sharing"
				role="tabpanel"
				data-test="config-panel-sharing"
				class="dashboard-config__panel">
				<div v-if="!isCreate && canManageShares" class="dashboard-config__field">
					<label class="dashboard-config__label">
						{{ t('launchpad', 'Share with users and groups') }}
					</label>

					<NcSelect
						:model-value="null"
						:options="shareeOptions"
						:filterable="false"
						:loading="shareeLoading"
						:aria-label-combobox="t('launchpad', 'Share with users and groups')"
						:placeholder="t('launchpad', 'Search users and groups…')"
						label="displayName"
						track-by="key"
						:clearable="false"
						@search="onShareeSearch"
						@update:modelValue="onShareeSelected">
						<template #option="option">
							<span class="sharee-option">
								<AccountGroup v-if="option.shareType === 'group'" :size="18" />
								<Account v-else :size="18" />
								{{ option.displayName }}
							</span>
						</template>
					</NcSelect>

					<ul v-if="localShares.length > 0" class="dashboard-config__shares">
						<li
							v-for="(share, idx) in localShares"
							:key="`${share.shareType}:${share.shareWith}`"
							class="dashboard-config__share">
							<span class="dashboard-config__share-name">
								<AccountGroup v-if="share.shareType === 'group'" :size="18" />
								<Account v-else :size="18" />
								{{ share.displayName || share.shareWith }}
							</span>
							<NcSelect
								:model-value="permissionOptionFor(share.permissionLevel)"
								:options="permissionOptions"
								:input-label="t('launchpad', 'Permission level')"
								label="label"
								track-by="value"
								:clearable="false"
								class="dashboard-config__share-level"
								@update:modelValue="onShareLevelChange(idx, $event)" />
							<NcButton
								type="tertiary"
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
					<p v-if="sharesDirty" class="dashboard-config__hint dashboard-config__hint--dirty">
						{{ t('launchpad', 'Unsaved changes — click Save to apply.') }}
					</p>
				</div>

				<!-- Public link (anonymous read-only share). Applies immediately;
				     independent of the Save button. -->
				<div v-if="!isCreate && canManageShares" class="dashboard-config__field dashboard-config__public">
					<label class="dashboard-config__label">
						{{ t('launchpad', 'Public link') }}
					</label>
					<p class="dashboard-config__hint">
						{{ t('launchpad', 'Anyone with the link can view this dashboard read-only, without a Nextcloud account.') }}
					</p>

					<ul v-if="publicShares.length > 0" class="dashboard-config__public-list">
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
								@focus="$event.target.select()">
							<NcButton
								type="tertiary"
								:aria-label="t('launchpad', 'Copy link')"
								:title="copiedToken === share.token ? t('launchpad', 'Copied') : t('launchpad', 'Copy link')"
								@click="copyPublicShareUrl(share)">
								<template #icon>
									<Check v-if="copiedToken === share.token" :size="18" />
									<ContentCopy v-else :size="18" />
								</template>
							</NcButton>
							<span v-if="share.passwordRequired" class="dashboard-config__public-badge" :title="t('launchpad', 'Password protected')">
								<Lock :size="16" />
							</span>
							<span v-if="share.expiresAt" class="dashboard-config__public-expiry">
								{{ t('launchpad', 'until {date}', { date: share.expiresAt }) }}
							</span>
							<NcButton
								type="tertiary"
								:aria-label="t('launchpad', 'Revoke link')"
								:title="t('launchpad', 'Revoke link')"
								@click="onRevokePublicShare(share.id)">
								<template #icon>
									<Close :size="18" />
								</template>
							</NcButton>
						</li>
					</ul>
					<p v-else-if="!publicSharesLoading" class="dashboard-config__hint">
						{{ t('launchpad', 'No public links yet.') }}
					</p>

					<div class="dashboard-config__public-create">
						<NcTextField
							v-model="newSharePassword"
							type="password"
							:label="t('launchpad', 'Password (optional)')"
							autocomplete="new-password" />
						<input
							v-model="newShareExpiry"
							class="dashboard-config__public-date"
							type="date"
							:aria-label="t('launchpad', 'Expiry date (optional)')">
						<NcButton
							type="secondary"
							:disabled="creatingPublicShare"
							@click="onCreatePublicShare">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('launchpad', 'Create public link') }}
						</NcButton>
					</div>
				</div>
			</div>
			<!-- /sharing panel -->

			<div class="dashboard-config__actions">
				<NcButton
					v-if="canDelete && !isCreate"
					type="error"
					:disabled="saving"
					data-testid="dashboard-delete-button"
					@click="onDelete">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('launchpad', 'Delete dashboard') }}
				</NcButton>
				<div class="dashboard-config__actions-right">
					<NcButton type="tertiary" :disabled="saving" @click="$emit('close')">
						{{ t('launchpad', 'Cancel') }}
					</NcButton>
					<NcButton
						type="primary"
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
import { NcModal, NcButton, NcTextField, NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'

import Delete from 'vue-material-design-icons/Delete.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Account from 'vue-material-design-icons/Account.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Lock from 'vue-material-design-icons/Lock.vue'

import { generateUrl } from '@nextcloud/router'
import { CnIconBrowser, DEFAULT_ICON } from '@conduction/nextcloud-vue'
import { ICON_CATALOGUE } from '../services/iconCatalogue.js'
import { uploadDataUrl } from '../services/resourceService.js'
import { api } from '../services/api.js'
import { usePublicShareStore } from '../stores/publicShares.js'

const PERMISSION_OPTIONS = [
	{ value: 'view_only', label: 'View only' },
	{ value: 'add_only', label: 'Add only' },
	{ value: 'full', label: 'Full access' },
]

export default {
	name: 'DashboardConfigModal',

	components: {
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
		Tune,
		ShareVariant,
		StarOutline,
		ContentCopy,
		Check,
		Lock,
		CnIconBrowser,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		dashboard: {
			type: Object,
			default: null,
		},
		canDelete: {
			type: Boolean,
			default: false,
		},
		mode: {
			type: String,
			default: 'edit',
			validator: v => ['edit', 'create'].includes(v),
		},

		/*
		 * Wave3.8 — UUID currently pinned as the user's default
		 * dashboard, or '' when none. Drives the initial state of
		 * the "Default dashboard" switch. The host (Views.vue)
		 * fetches it once via `GET /api/dashboards/default` and
		 * passes it down both here and to `DashboardSwitcherSidebar`.
		 */
		defaultUuid: {
			type: String,
			default: '',
		},

		/**
		 * Tab to land on when the modal opens (dashboard-sharing spec). The
		 * top-bar share action passes `'sharing'` so the share button lands
		 * directly on the Sharing tab.
		 */
		initialTab: {
			type: String,
			default: 'general',
			validator: v => ['general', 'sharing', 'default'].includes(v),
		},
	},

	emits: ['close', 'save', 'delete', 'set-default'],

	data() {
		return {
			currentTab: this.initialTab || 'general',
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
			},
			saving: false,
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
			return ICON_CATALOGUE
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
		 * @return {Function} the data-URL upload function.
		 */
		iconUploadFn() {
			return uploadDataUrl
		},
		/**
		 * Config-drawer tab descriptors. The Sharing tab is only offered when
		 * the user can manage shares — REQ-SHARE-001 is owner-only, so a
		 * recipient must not be shown a share-management surface at all.
		 *
		 * @spec openspec/specs/dashboard-sharing/spec.md#req-share-001
		 * @return {Array<{id: string, label: string}>}
		 */
		tabs() {
			const list = [
				{ id: 'general', label: t('launchpad', 'General'), icon: Tune },
			]
			if (this.canManageShares) {
				list.push({ id: 'sharing', label: t('launchpad', 'Sharing'), icon: ShareVariant })
			}
			list.push({ id: 'default', label: t('launchpad', 'Default'), icon: StarOutline })
			return list
		},
		/** @spec openspec/specs/dashboards/spec.md */
		canManageShares() {
			// Only the owner can see / manage shares.
			return this.dashboard?.isOwner !== false && (this.dashboard?.id ?? null) !== null
		},
		/** @spec openspec/specs/dashboards/spec.md */
		modalTitle() {
			return this.isCreate
				? t('launchpad', 'Create dashboard')
				: t('launchpad', 'Dashboard configuration')
		},
		/** @spec openspec/specs/dashboards/spec.md */
		primaryButtonLabel() {
			if (this.saving) {
				return this.isCreate ? t('launchpad', 'Creating…') : t('launchpad', 'Saving…')
			}
			return this.isCreate ? t('launchpad', 'Create') : t('launchpad', 'Save')
		},
		/** @spec openspec/specs/dashboards/spec.md */
		permissionOptions() {
			return PERMISSION_OPTIONS.map(o => ({
				value: o.value,
				label: t('launchpad', o.label),
			}))
		},
		selectedPermission: {
			/** @spec openspec/specs/dashboards/spec.md */
			get() {
				const level = this.dashboard?.permissionLevel || 'full'
				return this.permissionOptions.find(o => o.value === level) || this.permissionOptions[2]
			},
			/** @spec openspec/specs/dashboards/spec.md */
			set() {
				// Read-only — admin-managed.
			},
		},
		/** @spec openspec/specs/dashboards/spec.md */
		canSave() {
			return this.form.name.trim().length > 0
		},
		/** @spec openspec/specs/dashboards/spec.md */
		sharesDirty() {
			if (this.localShares.length !== this.serverShares.length) return true
			const key = s => `${s.shareType}:${s.shareWith}:${s.permissionLevel}`
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
				const wanted = this.initialTab || 'general'
				this.currentTab = (wanted === 'sharing' && !this.canManageShares)
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
					// Persisted icon may be NULL/empty, a registry key, a custom
					// URL, or an SVG path string (CnIconBrowser). Any non-empty
					// value is kept verbatim and rendered by CnDashboardIcon;
					// only null/empty falls back to DEFAULT_ICON
					// (REQ-ICON-002 + REQ-ICON-009).
					this.form.icon = this.dashboard.icon || DEFAULT_ICON
					// Wave3.8 — initial toggle state mirrors whether
					// THIS dashboard's UUID matches the user's pinned
					// default. Snapshot for the dirty-check in onSave.
					this.form.isDefault = !!this.dashboard.uuid && this.dashboard.uuid === this.defaultUuid
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
		t,
		/**
		 * Resolve a permission level to its select option, falling back to
		 * the first option for unknown levels.
		 *
		 * @param {string} level Stored permission level, e.g. `read`/`full`.
		 * @return {object} Matching option from `permissionOptions`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		permissionOptionFor(level) {
			return this.permissionOptions.find(o => o.value === level) || this.permissionOptions[0]
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async loadShares() {
			try {
				const response = await api.listShares(this.dashboard.id)
				const fresh = response.data || []
				this.serverShares = fresh.map(s => ({ ...s }))
				this.localShares = fresh.map(s => ({ ...s }))
			} catch (error) {
				console.error('Failed to load shares:', error)
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
				this.publicShares = await usePublicShareStore().fetchShares(this.dashboard.uuid)
			} catch (error) {
				console.error('Failed to load public shares:', error)
				this.publicShares = []
			} finally {
				this.publicSharesLoading = false
			}
		},
		/**
		 * Mint a new public link, optionally password-protected and/or expiring.
		 *
		 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
		 */
		async onCreatePublicShare() {
			if (!this.dashboard?.uuid) {
				return
			}
			this.creatingPublicShare = true
			try {
				// The date input yields `YYYY-MM-DD`; the backend expects an
				// ISO 8601 instant, so pin expiry to end-of-day UTC.
				const expiresAt = this.newShareExpiry
					? `${this.newShareExpiry}T23:59:59Z`
					: null
				await usePublicShareStore().createShare(this.dashboard.uuid, {
					password: this.newSharePassword || null,
					expiresAt,
				})
				this.newSharePassword = ''
				this.newShareExpiry = ''
				await this.loadPublicShares()
			} catch (error) {
				console.error('Failed to create public share:', error)
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
				console.error('Failed to revoke public share:', error)
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
			return window.location.origin + generateUrl('/apps/launchpad/s/{token}', { token })
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
				console.error('Clipboard write failed:', error)
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
				console.error('Sharee suggestion preload failed:', error)
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
			const users = (response.data?.users || []).map(u => ({
				key: `user:${u.id}`,
				shareType: 'user',
				id: u.id,
				displayName: u.displayName,
			}))
			const groups = (response.data?.groups || []).map(g => ({
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
				console.error('Sharee search failed:', error)
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
				s => s.shareType === option.shareType && s.shareWith === option.id,
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
							this.localShares.map(s => ({
								shareType: s.shareType,
								shareWith: s.shareWith,
								permissionLevel: s.permissionLevel,
							})),
						)
					} catch (error) {
						console.error('Failed to replace shares:', error)
					}
				}
				await this.$emit('save', {
					id: this.dashboard?.id ?? null,
					name: this.form.name.trim(),
					description: this.form.description.trim(),
					icon: this.form.icon || null,
				})

				// Wave3.8 — propagate the default-pin toggle when its
				// state changed since the modal opened. Emitted after
				// save so the host can issue the API call without
				// blocking the rest of the save flow. Skip on create
				// (no UUID yet — pin via the per-row cog after the
				// row appears in the list).
				if (!this.isCreate
					&& this.dashboard
					&& this.form.isDefault !== this._initialIsDefault
				) {
					this.$emit('set-default', {
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

/* Fleet tablist design (nc-vue CnTabbedFormDialog / NC sidebar tabs):
   justified full-width tabs, icon + label, primary underline on the
   active tab, border underline on hover. */
.dashboard-config__tabs {
	display: flex;
	justify-content: space-between;
	list-style: none;
	margin: 8px 0 16px;
	padding: 0;
	border-bottom: 1px solid var(--color-border);
}

.dashboard-config__tab-item {
	display: flex;
	flex: 1;
}

.dashboard-config__tab-item:hover {
	background-color: var(--color-background-hover);
}

.dashboard-config__tab {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	background: transparent;
	border: 0;
	border-bottom: 2px solid transparent;
	padding: 10px;
	cursor: pointer;
	font: inherit;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.dashboard-config__tab:hover {
	border-bottom-color: var(--color-border);
}

.dashboard-config__tab--active,
.dashboard-config__tab--active:hover {
	color: var(--color-main-text);
	border-bottom-color: var(--color-primary-element);
	font-weight: 600;
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
</style>
