<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- Vue 3 removed the `.sync` modifier, and @nextcloud/vue@9 renamed the
	     two-way props of its form controls to `modelValue`/`update:modelValue`.
	     Both halves are silent failures: the compiler drops the unknown `.sync`
	     modifier and Vue never warns about the missing prop, so the field
	     renders empty and never writes back. Use `v-model` throughout. -->
	<NcModal
		v-model:show="isOpen"
		class="launchpad-modal"
		:name="heading"
		size="normal"
		@close="$emit('close')">
		<!-- tile-form-board-sync REQ-TILE-040: one 640 px form, in the board's order. -->
		<div class="tile-editor">
			<h2 class="tile-editor__heading">
				{{ heading }}
			</h2>
			<p class="tile-editor__where" data-testid="tile-editor-where">
				{{ whereLine }}
			</p>

			<div class="tile-editor__form">
				<div class="form-row">
					<div class="form-row__item">
						<NcTextField
							v-model="form.title"
							:label="t('launchpad', 'Name')"
							:placeholder="t('launchpad', 'Enter tile name')"
							required />
					</div>
					<div class="form-row__item">
						<NcTextField
							:modelValue="groupLabel"
							:label="t('launchpad', 'Group')"
							:helperText="groupName ? '' : t('launchpad', 'Tiles in a group share its sort order.')"
							disabled
							data-testid="tile-group" />
					</div>
				</div>

				<!-- launcher-tile-launch-types: what the tile opens. The server
				     checks every type (TileLinkValidator); an sso tile's address
				     is resolved there from the template, so it has no address field. -->
				<ChoiceCards
					:modelValue="form.linkType"
					:options="linkTypeOptions"
					:label="t('launchpad', 'Tile type')"
					:columns="2"
					name="tile-link-type"
					data-testid="tile-link-type"
					@update:modelValue="onLinkType" />

				<template v-if="form.linkType === 'program'">
					<NcTextField
						v-model="form.linkValue"
						:label="t('launchpad', 'Program address')"
						:helperText="programHelp"
						type="text"
						data-testid="tile-program-address" />
				</template>

				<template v-else-if="form.linkType === 'remote-desktop'">
					<fieldset class="tile-editor__fieldset">
						<legend>{{ t('launchpad', 'Connection') }}</legend>
						<NcCheckboxRadioSwitch
							:modelValue="form.remoteMode === 'gateway'"
							type="radio"
							name="tile-remote-mode"
							data-testid="tile-remote-gateway"
							@update:modelValue="form.remoteMode = 'gateway'">
							{{ t('launchpad', 'Through a web gateway') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							:modelValue="form.remoteMode === 'rdp'"
							type="radio"
							name="tile-remote-mode"
							data-testid="tile-remote-rdp"
							@update:modelValue="form.remoteMode = 'rdp'">
							{{ t('launchpad', 'Download an RDP file') }}
						</NcCheckboxRadioSwitch>
					</fieldset>

					<NcTextField
						v-if="form.remoteMode === 'gateway'"
						v-model="form.remoteUrl"
						:label="t('launchpad', 'Gateway address')"
						:helperText="t('launchpad', 'An https address, such as your Guacamole, Citrix or Azure Virtual Desktop web page.')"
						type="text"
						data-testid="tile-remote-url" />
					<template v-else>
						<div class="form-row">
							<div class="form-row__item">
								<NcTextField
									v-model="form.rdpHost"
									:label="t('launchpad', 'Computer or server')"
									type="text"
									data-testid="tile-rdp-host" />
							</div>
							<div class="form-row__item">
								<NcTextField
									v-model="form.rdpPort"
									:label="t('launchpad', 'Port')"
									:helperText="t('launchpad', 'Leave empty for {port}.', { port: DEFAULT_RDP_PORT })"
									type="number"
									data-testid="tile-rdp-port" />
							</div>
						</div>
						<NcTextField
							v-model="form.rdpRemoteApp"
							:label="t('launchpad', 'Published program (optional)')"
							:helperText="t('launchpad', 'The RemoteApp name, such as ||Belastingen. Leave empty for the whole desktop.')"
							type="text"
							data-testid="tile-rdp-program" />
						<NcTextField
							v-model="form.rdpGateway"
							:label="t('launchpad', 'Remote desktop gateway (optional)')"
							type="text"
							data-testid="tile-rdp-gateway" />
					</template>
				</template>

				<template v-else-if="form.linkType === 'sso'">
					<NcSelect
						:modelValue="form.ssoTemplate || null"
						:options="ssoTemplateOptions"
						:inputLabel="t('launchpad', 'Sign-on template')"
						:reduce="(option) => option.value"
						:clearable="false"
						label="label"
						data-testid="tile-sso-template"
						@update:modelValue="form.ssoTemplate = $event" />
					<p v-if="ssoTemplateOptions.length === 0" class="tile-editor__hint-small">
						{{ t('launchpad', 'No sign-on templates yet. An administrator adds them on the LaunchPad admin page.') }}
					</p>
					<NcTextField
						v-model="form.ssoAppId"
						:label="t('launchpad', 'App id')"
						:helperText="t('launchpad', 'The id the identity provider gives this app.')"
						type="text"
						data-testid="tile-sso-app-id" />
				</template>

				<!-- Web address and internal address side by side (REQ-TILE-040). -->
				<div class="form-row">
					<div v-if="hasWebAddress" class="form-row__item">
						<NcTextField
							v-model="form.linkValue"
							:label="t('launchpad', 'Web address')"
							:placeholder="t('launchpad', 'https://example.com or /apps/files')"
							type="text" />
					</div>
					<div class="form-row__item">
						<NcTextField
							v-model="form.internalUrl"
							:label="t('launchpad', 'Internal address (optional)')"
							:placeholder="t('launchpad', 'e.g. http://intranet.local')"
							type="text" />
					</div>
				</div>
				<p class="tile-editor__hint-small tile-editor__address-in-effect">
					{{ addressInEffect }}
				</p>

				<!-- Launcher-tile-open-behaviour: how the tile opens, as three cards. -->
				<div class="tile-editor__opens-in" data-test="tile-editor-opens-in">
					<ChoiceCards
						:modelValue="form.linkTarget"
						:options="linkTargetOptions"
						:label="t('launchpad', 'Open in')"
						:columns="3"
						name="tile-link-target"
						@update:modelValue="form.linkTarget = $event" />
					<p class="tile-editor__hint" data-test="tile-editor-opens-in-default">
						<template v-if="form.linkTarget === ''">
							{{ t('launchpad', 'None chosen: the tile opens as the dashboard decides.') }}
						</template>
						<NcButton
							v-else
							variant="tertiary"
							data-test="tile-editor-follow-dashboard"
							@click="form.linkTarget = ''">
							{{ t('launchpad', 'Open as the dashboard decides') }}
						</NcButton>
					</p>
					<p
						v-if="panelRefusal"
						class="tile-editor__hint"
						data-test="tile-editor-panel-refusal">
						{{ panelRefusal }}
					</p>
				</div>

				<!-- The icon with its preview, a picker on demand and an upload. -->
				<div class="tile-editor__field tile-editor__icon">
					<span class="tile-editor__label">{{ t('launchpad', 'Icon') }}</span>
					<div class="tile-editor__icon-row">
						<div
							class="tile-preview"
							:style="{
								backgroundColor: form.backgroundColor,
								color: form.textColor,
							}">
							<img
								v-if="isUrlIcon"
								class="tile-preview__icon"
								:src="displayIcon"
								alt="" />
							<svg
								v-else
								class="tile-preview__icon"
								:style="{ fill: form.textColor }"
								viewBox="0 0 24 24">
								<path :d="displayIcon" />
							</svg>
							<span class="tile-preview__title">{{ form.title }}</span>
						</div>
						<div class="tile-editor__icon-actions">
							<NcButton
								data-testid="tile-icon-choose"
								:aria-expanded="iconBrowserOpen ? 'true' : 'false'"
								@click="iconBrowserOpen = !iconBrowserOpen">
								{{ iconBrowserOpen ? t('launchpad', 'Close the icon list') : t('launchpad', 'Choose an icon') }}
							</NcButton>
							<NcButton
								data-testid="tile-icon-upload"
								:disabled="iconUploading"
								@click="pickIconFile">
								{{ t('launchpad', 'Upload another icon') }}
							</NcButton>
							<input
								ref="iconFile"
								class="tile-editor__file"
								type="file"
								accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
								tabindex="-1"
								aria-hidden="true"
								@change="onIconFile">
							<p v-if="iconUploadError" class="tile-editor__warning" role="alert">
								{{ iconUploadError }}
							</p>
						</div>
					</div>
					<!-- No `url-icon-groups`: CnIconBrowser ships the NL Design sets
					     (Gemeente / Den Haag / RVO) by default and fetches RVO's
					     1.9 MB only when its tab is opened. Passing them explicitly,
					     as this used to, forced the whole pack into the eager bundle. -->
					<CnIconBrowser
						v-if="iconBrowserOpen"
						inline
						:label="t('launchpad', 'Icon')"
						:value="displayIcon"
						:icons="iconCatalogue"
						@input="onIcon" />
				</div>

				<div class="tile-editor__field">
					<span class="tile-editor__label">{{ t('launchpad', 'Colour') }}</span>
					<div class="form-row">
						<div class="form-row__item">
							<NcColorPicker v-model="form.backgroundColor">
								<NcButton
									variant="tertiary"
									:aria-label="t('launchpad', 'Pick background color')">
									<template #icon>
										<div
											class="color-preview"
											:style="{
												backgroundColor: form.backgroundColor,
											}" />
									</template>
									{{ t('launchpad', 'Background') }} {{ form.backgroundColor }}
								</NcButton>
							</NcColorPicker>
						</div>

						<div class="form-row__item">
							<NcColorPicker v-model="form.textColor">
								<NcButton
									variant="tertiary"
									:aria-label="t('launchpad', 'Pick text color')">
									<template #icon>
										<div
											class="color-preview"
											:style="{
												backgroundColor: form.textColor,
											}" />
									</template>
									{{ t('launchpad', 'Icon and text') }} {{ form.textColor }}
								</NcButton>
							</NcColorPicker>
						</div>
					</div>
				</div>

				<!-- REQ-TILE-041: the sort order of the tile's group. -->
				<NcSelect
					:modelValue="groupSortBy"
					:options="groupSortOptions"
					:inputLabel="t('launchpad', 'Sort order in this group')"
					:reduce="(option) => option.value"
					:clearable="false"
					:disabled="!groupName"
					label="label"
					data-testid="tile-group-sort"
					@update:modelValue="groupSortBy = $event" />

				<div class="tile-editor__health-ping">
					<NcCheckboxRadioSwitch
						:modelValue="form.healthPingEnabled"
						type="switch"
						@update:modelValue="onHealthPingToggle">
						{{ t('launchpad', 'Check availability') }}
					</NcCheckboxRadioSwitch>

					<template v-if="form.healthPingEnabled">
						<!-- REQ-TILE-042: the last check's result with its time. -->
						<p
							v-if="tile && tile.id"
							class="tile-editor__hint-small tile-editor__last-check"
							data-testid="tile-last-check">
							{{ lastCheck }}
						</p>
						<NcTextField
							v-model="form.healthUrl"
							:label="t('launchpad', 'Health check URL')"
							:placeholder="t('launchpad', 'https://…')"
							type="text"
							@update:modelValue="healthUrlError = ''"
							@blur="checkHealthUrlAllowed" />
						<p v-if="healthUrlError" class="tile-editor__warning">
							{{ healthUrlError }}
						</p>

						<div class="form-row">
							<div class="form-row__item">
								<NcTextField
									v-model="form.expectedStatus"
									type="number"
									:label="
										t('launchpad', 'Expected HTTP status')
									" />
							</div>
							<div class="form-row__item">
								<NcTextField
									v-model="form.pingInterval"
									type="number"
									:label="
										t('launchpad', 'Check interval (seconds)')
									" />
							</div>
						</div>
						<p class="tile-editor__hint-small">
							{{
								t('launchpad', 'Minimum {min} seconds.', {
									min: MIN_PING_INTERVAL,
								})
							}}
						</p>
					</template>
				</div>

				<div class="tile-editor__actions">
					<NcButton v-if="tile" variant="error" @click="$emit('delete')">
						{{ t('launchpad', 'Delete tile') }}
					</NcButton>
					<span v-else />
					<div class="tile-editor__actions-right">
						<NcButton @click="$emit('close')">
							{{ t('launchpad', 'Cancel') }}
						</NcButton>
						<NcButton variant="primary" @click="saveTile">
							{{ t('launchpad', 'Save') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	CnIconBrowser,
	isCustomIconUrl,
	NcButton,
	NcCheckboxRadioSwitch,
	NcColorPicker,
	NcModal,
	NcSelect,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { mdiLink } from '@mdi/js'
import { translate as t } from '@nextcloud/l10n'
import ChoiceCards from '../components/ChoiceCards.vue'
import { FLAG_PROP, NULLABLE_OBJECT_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { fetchHealthPingBadge, validateHealthPingConfig } from '../services/healthPingClient.js'
import { ICON_CATALOGUE, loadIconCatalogue, normaliseIconValue } from '../services/iconCatalogue.js'
import { validateIframeUrl } from '../services/iframeClient.js'
import { uploadFile } from '../services/resourceService.js'
import { isOnOfficeNetwork, loadInitialState } from '../utils/loadInitialState.js'
import { lastCheckLine } from '../utils/tileAvailability.js'
import { DEFAULT_RDP_PORT, launchFields, LINK_PROGRAM, LINK_REMOTE, LINK_SSO } from '../utils/tileLaunch.js'
import { NEW_TAB, SAME_TAB, SIDE_PANEL } from '../utils/tileLinkTarget.js'
import { normaliseSortMode } from '../utils/tileSort.js'

const MIN_PING_INTERVAL = 15
const DEFAULT_PING_INTERVAL = 60
const DEFAULT_EXPECTED_STATUS = 200

/**
 * The admin's allowed program schemes and the sign-on template names.
 *
 * @return {{schemes: string[], templates: Array<{key: string, name: string}>}}
 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
 */
function tileLaunchOptions() {
	const state = loadInitialState('workspace')
	return {
		schemes: Array.isArray(state.tileAllowedSchemes) ? state.tileAllowedSchemes : [],
		templates: Array.isArray(state.ssoLaunchTemplates) ? state.ssoLaunchTemplates : [],
	}
}

export default {
	name: 'TileEditor',

	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcColorPicker,
		NcCheckboxRadioSwitch,
		NcSelect,
		CnIconBrowser,
		ChoiceCards,
	},

	props: {
		open: FLAG_PROP,

		tile: NULLABLE_OBJECT_PROP,

		/** The dashboard the tile is on, for the line under the heading. */
		dashboardName: TEXT_PROP,

		/** The title of the group (container) the tile is in; '' for none. */
		groupName: TEXT_PROP,

		/** The group's stored sort mode (`content.sortBy`). */
		groupSort: TEXT_PROP,
	},

	emits: ['close', 'save', 'delete'],

	data() {
		return {
			form: {
				title: '',
				icon: mdiLink,
				iconType: 'svg',
				backgroundColor: '#0082c9',
				textColor: '#ffffff',
				linkType: 'url',
				linkValue: '',
				internalUrl: '',
				linkTarget: '',
				healthPingEnabled: false,
				healthUrl: '',
				expectedStatus: DEFAULT_EXPECTED_STATUS,
				pingInterval: DEFAULT_PING_INTERVAL,
				...launchFields({}),
			},

			healthUrlError: '',
			iconBrowserOpen: false,
			iconUploading: false,
			iconUploadError: '',
			// REQ-TILE-042: the last health ping reading of the tile.
			lastReading: null,
			groupSortBy: normaliseSortMode(this.groupSort),
			onOfficeNetwork: isOnOfficeNetwork(),
			panelRefusal: '',
			MIN_PING_INTERVAL,
			DEFAULT_RDP_PORT,
			// launcher-tile-launch-types: the admin's allowed schemes and the
			// names of the sign-on templates (never their addresses).
			launchOptions: tileLaunchOptions(),
		}
	},

	computed: {
		/**
		 * The "Opens in" choices. The panel is unavailable for an outside
		 * address whose host is not on the embed allow-list.
		 *
		 * Three cards (REQ-TILE-040); none chosen ('') follows the dashboard.
		 *
		 * @return {Array<{value: string, label: string, hint: string, disabled: boolean}>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		linkTargetOptions() {
			return [
				{ value: SAME_TAB, label: t('launchpad', 'Same tab'), hint: t('launchpad', 'Replaces Launchpad in this tab.'), disabled: false },
				{ value: NEW_TAB, label: t('launchpad', 'New tab'), hint: t('launchpad', 'Launchpad stays open.'), disabled: false },
				{
					value: SIDE_PANEL,
					label: t('launchpad', 'In Launchpad, next to the tiles'),
					hint: t('launchpad', 'Opens in a panel beside the tiles.'),
					disabled: this.panelRefusal !== '',
				},
			]
		},

		/**
		 * The form's heading: "Edit tile: <name>", or "New tile".
		 *
		 * @return {string}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		heading() {
			if (!this.tile) {
				return t('launchpad', 'New tile')
			}
			return t('launchpad', 'Edit tile: {name}', { name: this.tile.title || '' })
		},

		/**
		 * The line under the heading: "<group> · <dashboard>".
		 *
		 * @return {string}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		whereLine() {
			if (!this.dashboardName) {
				return this.groupLabel
			}
			return t('launchpad', '{group} · {dashboard}', { group: this.groupLabel, dashboard: this.dashboardName })
		},

		/**
		 * The tile's group as the Group field shows it.
		 *
		 * @return {string}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		groupLabel() {
			return this.groupName || t('launchpad', 'No group')
		},

		/**
		 * The group's sort orders, by the names the form gives them; the
		 * values are the container's stored modes (src/utils/tileSort.js).
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		groupSortOptions() {
			const options = [
				{ value: 'last-used', label: t('launchpad', 'Last used first') },
				{ value: 'alphabetical', label: t('launchpad', 'Alphabetical') },
				{ value: 'random', label: t('launchpad', 'Random') },
				{ value: 'manual', label: t('launchpad', 'As placed') },
			]
			if (!options.some((option) => option.value === this.groupSortBy)) {
				options.push({ value: this.groupSortBy, label: t('launchpad', 'Most used first') })
			}
			return options
		},

		/**
		 * Whether the tile type has a web address field of its own: a program,
		 * remote desktop or sign-on tile has its own fields instead.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		hasWebAddress() {
			return ![LINK_PROGRAM, LINK_REMOTE, LINK_SSO].includes(this.form.linkType)
		},

		/**
		 * The result of the last availability check with its time.
		 *
		 * @return {string}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
		 */
		lastCheck() {
			return lastCheckLine(this.lastReading, new Date(), t)
		},

		isOpen: {
			/** @spec openspec/specs/tiles/spec.md */
			get() {
				return this.open
			},

			/**
			 * Closing the modal is owned by the parent, so a `false` write
			 * is forwarded as a `close` event rather than mutating the prop.
			 *
			 * @param {boolean} value New open state.
			 * @spec openspec/specs/tiles/spec.md
			 */
			set(value) {
				if (!value) {
					this.$emit('close')
				}
			},
		},

		/** @spec openspec/specs/tiles/spec.md */
		isUrlIcon() {
			return isCustomIconUrl(this.form.icon)
		},

		/**
		 * The shared MDI icon catalogue passed to CnIconBrowser — the single
		 * picker source every icon surface reads, so the picker cannot drift
		 * from the registry (REQ-ICON-003).
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-003
		 * @return {object} the frozen icon catalogue.
		 */
		iconCatalogue() {
			return ICON_CATALOGUE
		},

		/**
		 * Icon value for the picker and preview. Legacy tiles store an MDI
		 * shortname (`link`) or key (`AlertCircle`) rather than the SVG path the
		 * catalogue is indexed by, so the picker can't match them and the
		 * preview can't draw them. Map those to their path for display; the
		 * stored `form.icon` is left untouched until the user picks a new icon
		 * — the single `icon` column keeps holding whatever it held, with no
		 * migration (REQ-ICON-009).
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
		 * @return {string} the SVG path / URL to display.
		 */
		displayIcon() {
			return normaliseIconValue(this.form.icon)
		},

		/**
		 * What a tile can open. A type this editor does not offer (an older
		 * `app` or `internal` tile) stays selectable so editing keeps it.
		 *
		 * Four choice cards with one line of explanation each (REQ-TILE-040).
		 *
		 * @return {Array<{value: string, label: string, hint: string}>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		linkTypeOptions() {
			const options = [
				{ value: 'url', label: t('launchpad', 'Web address'), hint: t('launchpad', 'A website or a page on this Nextcloud.') },
				{ value: LINK_SSO, label: t('launchpad', 'App with single sign-on'), hint: t('launchpad', 'Signs in through your identity provider.') },
				{ value: LINK_REMOTE, label: t('launchpad', 'Legacy Windows program'), hint: t('launchpad', 'Through remote desktop or a web gateway.') },
				{ value: LINK_PROGRAM, label: t('launchpad', 'Program on this computer'), hint: t('launchpad', 'Starts a program installed on the device.') },
			]
			const current = this.form.linkType
			if (current && !options.some((option) => option.value === current)) {
				options.unshift({
					value: current,
					label: current === 'app' ? t('launchpad', 'Nextcloud app') : t('launchpad', 'Link'),
					hint: t('launchpad', 'An older tile type, kept while you edit.'),
				})
			}
			return options
		},

		/**
		 * The sign-on templates an author picks from.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 */
		ssoTemplateOptions() {
			return this.launchOptions.templates.map((template) => ({ value: template.key, label: template.name }))
		},

		/**
		 * The helper line under a program address: which schemes work.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 */
		programHelp() {
			if (this.launchOptions.schemes.length === 0) {
				return t('launchpad', 'No program schemes are allowed yet. An administrator allows them on the LaunchPad admin page.')
			}
			return t('launchpad', 'Allowed schemes: {schemes}', { schemes: this.launchOptions.schemes.join(', ') })
		},

		/**
		 * Which address this tile opens for the author right now, from the
		 * same server answer every viewer gets (launcher-tile-internal-address D3).
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
		 */
		addressInEffect() {
			if (this.onOfficeNetwork) {
				return t('launchpad', 'You are on the office network: this tile opens the internal address')
			}
			return t('launchpad', 'You are not on the office network: this tile opens the main address')
		},
	},

	watch: {
		/**
		 * Re-check whether the address may open in the panel whenever it changes.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		'form.linkValue': function() {
			this.checkPanelAllowed()
		},

		/**
		 * Follow the group's stored order when the parent changes it.
		 *
		 * @param {string} value The stored sort mode.
		 * @return {void}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		groupSort(value) {
			this.groupSortBy = normaliseSortMode(value)
		},

		tile: {
			immediate: true,
			/**
			 * Seed the form from the tile being edited.
			 *
			 * @param {object|null} newTile Tile to edit; null opens a blank
			 *   form for a new tile.
			 * @spec openspec/specs/tiles/spec.md
			 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
			 */
			handler(newTile) {
				if (newTile) {
					this.form = {
						...launchFields({}),
						...newTile,
						internalUrl: newTile.internalUrl || '',
						iconType: newTile.iconType || 'class',
						linkTarget: newTile.linkTarget || '',
					}
				} else {
					this.resetForm()
				}
				this.lastReading = null
				this.loadLastCheck()
			},
		},
	},

	created() {
		loadIconCatalogue().catch(() => {})
	},

	methods: {
		/**
		 * Store the picked icon. CnIconBrowser emits an SVG path (MDI) or a URL
		 * (Thematiq/upload); derive iconType from the value so TileWidget renders
		 * the right element. `isCustomIconUrl` is the REQ-ICON-005 URL/name
		 * discriminator — `iconType` is derived from it rather than guessed,
		 * so a picked value and its stored type can never disagree.
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-005
		 * @param {string} value the chosen SVG path or icon URL.
		 * @return {void}
		 */
		onIcon(value) {
			this.form.icon = value
			this.form.iconType = isCustomIconUrl(value) ? 'url' : 'svg'
		},

		/**
		 * Switch what the tile opens. Leaving a type whose address the server
		 * resolves clears the stale address.
		 *
		 * @param {string} value The new link type.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 */
		onLinkType(value) {
			if (value === this.form.linkType) {
				return
			}
			if ([LINK_REMOTE, LINK_SSO].includes(this.form.linkType) || [LINK_REMOTE, LINK_SSO].includes(value)) {
				this.form.linkValue = ''
			}
			this.form.linkType = value
		},

		/** @spec openspec/specs/tiles/spec.md */
		resetForm() {
			this.form = {
				title: '',
				icon: mdiLink,
				iconType: 'svg',
				backgroundColor: '#0082c9',
				textColor: '#ffffff',
				linkType: 'url',
				linkValue: '',
				internalUrl: '',
				linkTarget: '',
				healthPingEnabled: false,
				healthUrl: '',
				expectedStatus: DEFAULT_EXPECTED_STATUS,
				pingInterval: DEFAULT_PING_INTERVAL,
				...launchFields({}),
			}
			this.healthUrlError = ''
		},

		/**
		 * Toggle the health-ping block. Clears any stale validation error
		 * when disabling so a re-enable starts clean.
		 *
		 * @param {boolean} value the new toggle state.
		 * @return {void}
		 * @spec openspec/specs/service-health-ping/spec.md
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
		 */
		onHealthPingToggle(value) {
			this.form.healthPingEnabled = value
			if (!value) {
				this.healthUrlError = ''
			} else {
				this.loadLastCheck()
			}
		},

		/**
		 * Read the tile's last health ping, for the line under the switch.
		 * Only a saved tile with the check on has one; a failed read shows
		 * "Not checked yet".
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
		 */
		async loadLastCheck() {
			const id = this.tile?.id
			if (!id || !this.form.healthPingEnabled || this.lastReading) {
				return
			}
			try {
				const reading = await fetchHealthPingBadge(id)
				if (this.tile?.id === id) {
					this.lastReading = reading
				}
			} catch {
				this.lastReading = null
			}
		},

		/**
		 * Open the file chooser for "Upload another icon".
		 *
		 * @return {void}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		pickIconFile() {
			this.$refs.iconFile?.click()
		},

		/**
		 * Upload the chosen file as the tile's icon; the stored icon becomes
		 * the resource's URL, as an upload in the icon browser makes it.
		 *
		 * @param {Event} event The file input's change event.
		 * @return {Promise<void>}
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 * @spec openspec/specs/resource-uploads/spec.md
		 */
		async onIconFile(event) {
			const file = event?.target?.files?.[0]
			if (!file) {
				return
			}
			this.iconUploading = true
			this.iconUploadError = ''
			try {
				const resource = await uploadFile(file)
				this.form.icon = resource.url
				this.form.iconType = 'url'
			} catch {
				this.iconUploadError = t('launchpad', 'The icon could not be uploaded.')
			} finally {
				this.iconUploading = false
				if (event.target) {
					event.target.value = ''
				}
			}
		},

		/**
		 * Clamp a configured ping interval client-side: values `<= 0`
		 * default to {@link DEFAULT_PING_INTERVAL}; any positive value
		 * below {@link MIN_PING_INTERVAL} is raised to that minimum
		 * (REQ-HPING-001 "Interval bounds"). The server clamps
		 * authoritatively regardless — this only keeps the saved payload
		 * consistent with what the badge will actually use.
		 *
		 * @param {number|string} raw the raw entered value.
		 * @return {number} the clamped interval in seconds.
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		clampPingInterval(raw) {
			const num = Number(raw)
			if (!Number.isFinite(num) || num <= 0) {
				return DEFAULT_PING_INTERVAL
			}
			return Math.max(num, MIN_PING_INTERVAL)
		},

		/**
		 * Best-effort async save-time check (REQ-HPING-001 "rejected at
		 * save time") — the authoritative fail-closed enforcement always
		 * happens server-side regardless; this only gives the author fast
		 * feedback before they hit Save.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		async checkHealthUrlAllowed() {
			if (
				!this.form.healthPingEnabled
				|| String(this.form.healthUrl || '').trim() === ''
			) {
				this.healthUrlError = ''
				return
			}
			const result = await validateHealthPingConfig({
				healthPingEnabled: true,
				healthUrl: this.form.healthUrl,
			})
			if (
				result.valid === false
				&& result.errors.includes('host_not_allowed')
			) {
				this.healthUrlError = t(
					'launchpad',
					'This host is not on the allow-list.',
				)
			} else if (
				result.valid === false
				&& result.errors.includes('invalid_url')
			) {
				this.healthUrlError = t('launchpad', 'Enter a valid http(s) URL.')
			} else {
				this.healthUrlError = ''
			}
		},

		/**
		 * Whether the address may open in the panel. An address on this
		 * Nextcloud (an app, an internal path) always may; an outside one only
		 * when its host is on the embed allow-list. A refused address cannot
		 * keep the panel as its choice.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		async checkPanelAllowed() {
			const value = String(this.form.linkValue || '').trim()
			if (this.form.linkType !== 'url' || !/^https?:\/\//i.test(value)) {
				this.panelRefusal = ''
				return
			}
			const result = await validateIframeUrl({ url: value })
			if (String(this.form.linkValue || '').trim() !== value) {
				return
			}
			if (result.valid === false) {
				this.panelRefusal = t('launchpad', 'This address cannot open in the panel.')
				if (this.form.linkTarget === SIDE_PANEL) {
					this.form.linkTarget = ''
				}
			} else {
				this.panelRefusal = ''
			}
		},

		/**
		 * Emit the tile, with the group's sort order when the tile is in a group.
		 *
		 * @return {void}
		 * @spec openspec/specs/tiles/spec.md
		 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		saveTile() {
			const tile = {
				...this.form,
				expectedStatus:
					Number(this.form.expectedStatus) || DEFAULT_EXPECTED_STATUS,

				pingInterval: this.clampPingInterval(this.form.pingInterval),
			}
			// REQ-TILE-041: the group's order travels with the save, for the group.
			if (this.groupName) {
				tile.groupSortBy = this.groupSortBy
			}
			this.$emit('save', tile)
		},
	},
}
</script>

<style scoped>
.tile-editor {
	padding: 20px;
	max-width: 640px;
	box-sizing: border-box;
}

.tile-editor__heading {
	margin-bottom: 2px !important;
}

.tile-editor__where {
	margin: 0 0 16px;
	color: var(--color-text-maxcontrast);
}

.tile-editor__label {
	font-weight: 600;
	font-size: 14px;
}

.tile-editor__icon-row {
	display: flex;
	align-items: center;
	gap: 16px;
}

.tile-editor__icon-actions {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
}

.tile-editor__file {
	display: none;
}

.tile-editor__last-check {
	margin: 0;
}

.tile-editor__opens-in {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.tile-editor__fieldset {
	border: none;
	margin: 0;
	padding: 0;
}

.tile-editor__fieldset legend {
	font-weight: bold;
}

.tile-editor h2 {
	margin-top: 0;
	margin-bottom: 20px;
}

.tile-preview {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: 96px;
	height: 96px;
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	padding: 12px;
	gap: 8px;
}

.tile-preview__icon {
	width: 48px;
	height: 48px;
	display: block;
}

.tile-preview__icon img {
	width: 100%;
	height: 100%;
	object-fit: contain;
}

.tile-preview__title {
	font-size: 14px;
	font-weight: 600;
	text-align: center;
	overflow-wrap: break-word;
	line-height: 1.2;
}

.tile-editor__form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.form-row {
	display: flex;
	gap: 12px;
}

.form-row__item {
	flex: 1;
}

.form-row__item label {
	display: block;
	margin-bottom: 4px;
	font-weight: 600;
	font-size: 14px;
}

.color-preview {
	width: 20px;
	height: 20px;
	border-radius: 4px;
	border: 1px solid var(--color-border);
}

.tile-editor__actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	margin-top: 20px;
	padding-top: 20px;
}

.tile-editor__actions-right {
	display: flex;
	gap: 8px;
}

.tile-editor__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.tile-editor__health-ping {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding-top: 8px;
	border-top: 1px solid var(--color-border);
}

.tile-editor__warning {
	margin: 0;
	font-size: 13px;
	color: var(--color-warning-text, var(--color-text-maxcontrast));
}

.tile-editor__hint {
	margin: 4px 0 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.tile-editor__hint-small {
	margin: -4px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
