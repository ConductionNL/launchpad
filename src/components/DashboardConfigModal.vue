<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
						:value="form.name"
						:label="t('launchpad', 'Title')"
						:placeholder="t('launchpad', 'My dashboard')"
						data-testid="dashboard-name-input"
						@update:value="form.name = $event" />
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

				<!-- Icon picker — IconPicker enumerates registry names from
			     `DASHBOARD_ICONS` AND accepts a custom upload (capability
			     `custom-icon-upload-pattern`). Same v-model whether the
			     final value is a registry key or a /apps/launchpad/resource/...
			     URL (REQ-ICON-003 + REQ-ICON-008..009). -->
				<div class="dashboard-config__field">
					<label class="dashboard-config__label" for="dashboard-config-icon">
						{{ t('launchpad', 'Icon') }}
					</label>
					<CnIconPicker
						:value="form.icon"
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
						:checked="form.isDefault"
						type="switch"
						@update:checked="form.isDefault = $event">
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
						:value="null"
						:options="shareeOptions"
						:filterable="false"
						:loading="shareeLoading"
						:aria-label-combobox="t('launchpad', 'Share with users and groups')"
						:placeholder="t('launchpad', 'Search users and groups…')"
						label="displayName"
						track-by="key"
						:clearable="false"
						@search="onShareeSearch"
						@input="onShareeSelected">
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
								:value="permissionOptionFor(share.permissionLevel)"
								:options="permissionOptions"
								:input-label="t('launchpad', 'Permission level')"
								label="label"
								track-by="value"
								:clearable="false"
								class="dashboard-config__share-level"
								@input="onShareLevelChange(idx, $event)" />
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

import { CnIconPicker, DASHBOARD_ICONS, DEFAULT_ICON, isCustomIconUrl } from '@conduction/nextcloud-vue'
import { uploadDataUrl } from '../services/resourceService.js'
import { api } from '../services/api.js'

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
		CnIconPicker,
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
		}
	},

	computed: {
		isCreate() {
			return this.mode === 'create'
		},
		/**
		 * The icon-upload transport handed to CnIconPicker. Exposed on the
		 * instance (computed) so the template can reference the module-imported
		 * `uploadDataUrl` — a bare module import isn't visible in template scope.
		 *
		 * @return {Function} the data-URL upload function.
		 */
		iconUploadFn() {
			return uploadDataUrl
		},
		/**
		 * Config-drawer tab descriptors (dashboard-sharing spec). The
		 * Sharing tab is only offered when the user can manage shares.
		 *
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
		/**
		 * Picker option list — derived from the registry so the UI grows
		 * automatically when an icon is added or removed (REQ-ICON-003).
		 *
		 * @return {string[]} Registry keys, in insertion order.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		iconOptions() {
			return Object.keys(DASHBOARD_ICONS)
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
			 * @param isOpen
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(isOpen) {
				if (!isOpen) {
					this.serverShares = []
					this.localShares = []
					this.shareeOptions = []
					this.shareeSuggestions = []
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
					// Persisted icon may be NULL/empty/unknown OR a custom URL
					// (capability `custom-icon-upload-pattern`). URLs are kept
					// verbatim — only unknown registry names fall back to
					// DEFAULT_ICON (REQ-ICON-002 + REQ-ICON-009).
					if (isCustomIconUrl(this.dashboard.icon)) {
						this.form.icon = this.dashboard.icon
					} else if (this.dashboard.icon && DASHBOARD_ICONS[this.dashboard.icon]) {
						this.form.icon = this.dashboard.icon
					} else {
						this.form.icon = DEFAULT_ICON
					}
					// Wave3.8 — initial toggle state mirrors whether
					// THIS dashboard's UUID matches the user's pinned
					// default. Snapshot for the dirty-check in onSave.
					this.form.isDefault = !!this.dashboard.uuid && this.dashboard.uuid === this.defaultUuid
					this._initialIsDefault = this.form.isDefault
					if (this.canManageShares) {
						this.loadShares()
						this.loadShareeSuggestions()
					}
				}
			},
		},
	},

	methods: {
		t,
		/**
		 * @param level
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
		 * @param query
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
		 * @param option
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
		 * @param idx
		 * @param option
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onShareLevelChange(idx, option) {
			if (!option) return
			const share = this.localShares[idx]
			if (!share || option.value === share.permissionLevel) return
			this.$set(this.localShares, idx, {
				...share,
				permissionLevel: option.value,
			})
		},
		/**
		 * @param idx
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
