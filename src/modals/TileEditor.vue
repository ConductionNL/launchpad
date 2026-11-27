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
	<!-- add-widget-dialog-with-miniatures REQ-WDG-040: embedded, the form is
	     drawn inside the Add widget dialog instead of a dialog of its own. -->
	<component
		:is="embedded ? 'div' : 'NcModal'"
		v-bind="frameProps"
		@close="$emit('close')">
		<!-- tile-form-board-sync REQ-TILE-040: one 640 px form, in the board's order. -->
		<div class="tile-editor">
			<template v-if="!embedded">
				<h2 class="tile-editor__heading">
					{{ heading }}
				</h2>
				<p class="tile-editor__where" data-testid="tile-editor-where">
					{{ whereLine }}
				</p>
			</template>

			<div class="tile-editor__form">
				<!-- app-link-tile-widget REQ-APPTILE-004: first the choice. A
				     placed tile keeps its mode. -->
				<ChoiceCards
					:modelValue="form.mode"
					:options="modeOptions"
					:label="t('launchpad', 'What does the tile open?')"
					:columns="2"
					name="tile-mode"
					data-testid="tile-mode"
					@update:modelValue="chooseMode" />

				<!-- Board LpTegelToevoegen / LpTegelBewerken: the fields on the left,
				     the Appearance panel on the right. -->
				<div class="tile-editor__columns">
					<div class="tile-editor__main">
						<TileAppChooser
							v-if="isAppMode && !tile"
							:catalogueApps="catalogueApps"
							:discoveredApps="discoveredApps"
							:foundServices="foundServices"
							:officeTiles="officeTiles"
							:placements="placements"
							:requestAppFn="requestAppFn"
							@update:chosen="chosenApps = $event"
							@importBookmarks="$emit('importBookmarks', $event)" />
						<p v-else-if="isAppMode" class="tile-editor__hint" data-testid="tile-editor-app">
							{{ t('launchpad', 'Opens the app {app}.', { app: tile.title || tile.appId || '' }) }}
						</p>

						<div v-if="!isAppMode" class="form-row">
							<div class="form-row__item">
								<NcTextField
									v-model="form.title"
									:label="t('launchpad', 'Name')"
									:placeholder="t('launchpad', 'Enter tile name')"
									required />
							</div>
							<div class="form-row__item">
								<!-- REQ-TILE-041: a tile on the dashboard moves into one of
								     its groups from here; a tile in a group shows its group. -->
								<NcSelect
									v-if="canMoveIntoGroup"
									:modelValue="targetGroupId"
									:options="groupChoices"
									:inputLabel="t('launchpad', 'Group')"
									:reduce="(option) => option.value"
									:clearable="false"
									label="label"
									data-testid="tile-group"
									@update:modelValue="chooseGroup($event)" />
								<NcTextField
									v-else
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
						<!-- REQ-TILE-040: the former tile types are no choice cards any
						     more; a tile stored with one keeps its fields here. -->
						<h3
							v-if="!isAppMode && isLaunchTile"
							class="tile-editor__label tile-editor__how"
							data-testid="tile-how-it-opens">
							{{ t('launchpad', 'How it opens') }}
						</h3>

						<template v-if="isAppMode" />

						<template v-else-if="form.linkType === 'program'">
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
						<div v-if="!isAppMode" class="form-row">
							<div v-if="hasWebAddress" class="form-row__item">
								<NcTextField
									v-model="form.linkValue"
									:label="t('launchpad', 'Web address')"
									:placeholder="t('launchpad', 'https://example.com or /apps/files')"
									type="text" />
							</div>
							<div v-if="!inGroup" class="form-row__item">
								<NcTextField
									v-model="form.internalUrl"
									:label="t('launchpad', 'Internal address (optional)')"
									:placeholder="t('launchpad', 'e.g. http://intranet.local')"
									type="text" />
							</div>
						</div>
						<p v-if="brandLine" class="tile-editor__hint-small" data-testid="tile-editor-brand">
							{{ brandLine }}
						</p>
						<p v-if="!inGroup && !isAppMode" class="tile-editor__hint-small tile-editor__address-in-effect">
							{{ addressInEffect }}
						</p>
						<!-- REQ-TILE-041: a tile in a group is a child of the group, not a
						     placement of its own, so what needs its own placement on the
						     server (launching, the availability check, the office-network
						     address, where it opens) is not offered here. -->
						<p v-else-if="inGroup" class="tile-editor__hint-small" data-testid="tile-in-group-hint">
							{{ t('launchpad', 'A tile in a group opens its web address in a new tab.') }}
						</p>

						<!-- Launcher-tile-open-behaviour: how the tile opens, as three cards. -->
						<div v-if="!inGroup && !isAppMode" class="tile-editor__opens-in" data-test="tile-editor-opens-in">
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
						<div v-if="!isAppMode" class="tile-editor__field tile-editor__icon">
							<span class="tile-editor__label">{{ t('launchpad', 'Icon') }}</span>
							<div class="tile-editor__icon-row">
								<div
									class="tile-preview"
									:style="{
										background: previewBackground,
										color: textCss,
									}">
									<img
										v-if="isUrlIcon"
										class="tile-preview__icon"
										:src="displayIcon"
										alt="" />
									<svg
										v-else
										class="tile-preview__icon"
										:style="{ fill: textCss }"
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
									<p class="tile-editor__hint-small" data-testid="tile-icon-colour-note">
										{{ t('launchpad', 'An SVG icon is drawn in the text colour; an uploaded colour image is shown as it is.') }}
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


						<!-- REQ-TILE-041: the sort order of the tile's group. -->
						<NcSelect
							v-if="!isAppMode"
							:modelValue="groupSortBy"
							:options="groupSortOptions"
							:inputLabel="t('launchpad', 'Sort order in this group')"
							:reduce="(option) => option.value"
							:clearable="false"
							:disabled="!groupName && targetGroupId === null"
							label="label"
							data-testid="tile-group-sort"
							@update:modelValue="groupSortBy = $event" />

						<div v-if="!inGroup && !isAppMode" class="tile-editor__health-ping">
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
					</div>
					<aside class="tile-editor__appearance" data-testid="tile-editor-appearance">
						<h3 class="tile-editor__appearance-title">
							{{ t('launchpad', 'Appearance') }}
						</h3>
						<!-- The tile as it will look, with what the form explains about it. -->
						<div class="tile-editor__appearance-preview">
							<div
								class="tile-preview tile-preview--square"
								data-testid="tile-editor-preview"
								:style="{ background: previewBackground, color: textCss }">
								<span v-if="form.pinned" class="tile-preview__pin" aria-hidden="true">•</span>
								<span class="tile-preview__glyph" aria-hidden="true">{{ previewInitial }}</span>
								<span v-if="form.iconOnly !== true" class="tile-preview__title">{{ previewName }}</span>
							</div>
							<p class="tile-editor__hint-small">
								{{ previewLine }}
							</p>
						</div>
						<div class="tile-editor__field">
							<span :id="colourLabelId" class="tile-editor__label">{{ t('launchpad', 'Tile colour') }}</span>
							<!-- REQ-ICT-004 (board LpTegelBewerken): eight theme colours,
							     each previewing the white icon; "Own colour" keeps the
							     colour pickers for a hex colour. -->
							<div
								class="tile-editor__swatches"
								role="radiogroup"
								:aria-labelledby="colourLabelId"
								data-test="tile-editor-swatches">
								<label
									v-for="swatch in colourSwatches"
									:key="swatch.token"
									class="tile-editor__swatch"
									:class="{ 'tile-editor__swatch--on': form.colourToken === swatch.token }"
									:style="{ background: swatch.css }"
									:title="swatch.label">
									<input
										type="radio"
										class="tile-editor__swatch-input"
										name="tile-colour"
										:value="swatch.token"
										:checked="form.colourToken === swatch.token"
										:aria-label="swatch.label"
										:data-test="'tile-editor-swatch' + swatch.token"
										@change="form.colourToken = swatch.token">
									<span class="tile-editor__swatch-icon" :style="{ color: textCss }" aria-hidden="true">A</span>
								</label>
								<label v-if="!isAppMode" class="tile-editor__swatch-own">
									<input
										type="radio"
										name="tile-colour"
										value=""
										:checked="form.colourToken === ''"
										data-test="tile-editor-swatch-own"
										@change="form.colourToken = ''">
									{{ t('launchpad', 'Own colour') }}
								</label>
							</div>
							<div v-if="form.colourToken === '' && !isAppMode" class="form-row">
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
							</div>
						</div>

						<!-- REQ-APPTILE-005: the text colour, with its contrast on the tile colour. -->
						<div class="tile-editor__field">
							<span :id="textColourLabelId" class="tile-editor__label">{{ t('launchpad', 'Text colour') }}</span>
							<div
								class="tile-editor__swatches"
								role="radiogroup"
								:aria-labelledby="textColourLabelId"
								data-testid="tile-editor-text-colours">
								<label
									v-for="swatch in textColourSwatches"
									:key="swatch.name"
									class="tile-editor__swatch tile-editor__swatch--text"
									:class="{ 'tile-editor__swatch--on': form.textColour === swatch.name }"
									:style="{ background: previewBackground, color: swatch.css }"
									:title="swatch.label">
									<input
										type="radio"
										class="tile-editor__swatch-input"
										name="tile-text-colour"
										:value="swatch.name"
										:checked="form.textColour === swatch.name"
										:aria-label="swatch.label"
										:data-testid="'tile-editor-text-' + swatch.name"
										@change="form.textColour = swatch.name">
									<span class="tile-editor__swatch-icon" aria-hidden="true">A</span>
								</label>
							</div>
							<p
								class="tile-editor__contrast"
								:class="{ 'tile-editor__warning': !contrast.passes }"
								:role="contrast.passes ? null : 'alert'"
								data-testid="tile-editor-contrast">
								<AlertOutline v-if="!contrast.passes" :size="16" aria-hidden="true" />
								{{ contrastLine }}
							</p>
						</div>

						<!-- What is on the tile, and Pin (REQ-APPTILE-004). -->
						<fieldset class="tile-editor__fieldset" data-testid="tile-editor-on-tile">
							<legend>{{ t('launchpad', 'What is on the tile') }}</legend>
							<NcCheckboxRadioSwitch
								:modelValue="form.iconOnly !== true"
								type="switch"
								data-testid="tile-editor-show-name"
								@update:modelValue="form.iconOnly = !$event">
								{{ t('launchpad', 'Show the name') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								v-if="isAppMode"
								:modelValue="form.showCount !== false"
								type="switch"
								data-testid="tile-editor-show-count"
								@update:modelValue="form.showCount = $event">
								{{ t('launchpad', 'Show the count of new items') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								:modelValue="form.pinned === true"
								type="switch"
								data-testid="tile-editor-pin"
								@update:modelValue="form.pinned = $event">
								{{ t('launchpad', 'Pin') }}
							</NcCheckboxRadioSwitch>
						</fieldset>
						<p class="tile-editor__hint-small tile-editor__brand-note">
							{{ t('launchpad', 'The organisation\'s standard tiles (Conduction, Nextcloud, sendent) show the brand\'s white logo on its colour, without payoff.') }}
						</p>
					</aside>
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
						<NcButton
							variant="primary"
							data-testid="tile-editor-save"
							:disabled="isAppMode && !tile && chosenApps.length === 0"
							@click="saveTile">
							{{ isAppMode && !tile ? addAppsLabel : t('launchpad', 'Save') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</component>
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
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import ChoiceCards from '../components/ChoiceCards.vue'
import TileAppChooser from '../components/TileAppChooser.vue'
import { FLAG_PROP, LIST_PROP, NULLABLE_OBJECT_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { fetchHealthPingBadge, validateHealthPingConfig } from '../services/healthPingClient.js'
import { ICON_CATALOGUE, loadIconCatalogue, normaliseIconValue } from '../services/iconCatalogue.js'
import { validateIframeUrl } from '../services/iframeClient.js'
import { uploadFile } from '../services/resourceService.js'
import { isOnOfficeNetwork, loadInitialState } from '../utils/loadInitialState.js'
import { lastCheckLine } from '../utils/tileAvailability.js'
import { isColourToken, TILE_COLOUR_TOKENS, tileBackground } from '../utils/tileColour.js'
import { DEFAULT_RDP_PORT, launchFields, LINK_PROGRAM, LINK_REMOTE, LINK_SSO } from '../utils/tileLaunch.js'
import { NEW_TAB, SAME_TAB, SIDE_PANEL } from '../utils/tileLinkTarget.js'
import { brandDefaults } from '../utils/tileMode.js'
import { normaliseSortMode } from '../utils/tileSort.js'
import { DEFAULT_TEXT_COLOUR, isTextColour, TEXT_COLOURS, textColourValue, textContrast } from '../utils/tileTextColour.js'

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
		TileAppChooser,
		AlertOutline,
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

		/** Whether the tile is a child of a group rather than a placement of its own. */
		inGroup: FLAG_PROP,

		/**
		 * The groups on the dashboard a tile can move into:
		 * `{id, title, sortBy}` for each group (REQ-TILE-041).
		 */
		groups: LIST_PROP,

		// add-widget-dialog-with-miniatures REQ-WDG-040: drawn inside Add widget.
		embedded: FLAG_PROP,

		// app-link-tile-widget REQ-APPTILE-004: the sources of "Choose the app".
		catalogueApps: LIST_PROP,

		discoveredApps: LIST_PROP,

		foundServices: LIST_PROP,

		officeTiles: LIST_PROP,

		placements: LIST_PROP,

		requestAppFn: { type: Function, default: null },
	},

	emits: ['close', 'save', 'delete', 'addApps', 'importBookmarks'],

	data() {
		return {
			form: {
				title: '',
				icon: mdiLink,
				iconType: 'svg',
				backgroundColor: '#0082c9',
				textColor: '#ffffff',
				colourToken: '',
				mode: 'link',
				textColour: DEFAULT_TEXT_COLOUR,
				iconOnly: false,
				pinned: false,
				showCount: true,
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
			// REQ-APPTILE-004: the apps ticked in "Choose the app".
			chosenApps: [],
			// REQ-APPTILE-004: the brand whose defaults the form filled in.
			brand: '',
			iconBrowserOpen: false,
			iconUploading: false,
			iconUploadError: '',
			// REQ-TILE-042: the last health ping reading of the tile.
			lastReading: null,
			groupSortBy: normaliseSortMode(this.groupSort),
			// REQ-TILE-041: the group a dashboard tile moves into; null stays put.
			targetGroupId: null,
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
		 * The eight theme colours, each with its name for screen readers.
		 *
		 * @return {Array<{token: string, label: string, css: string}>}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
		 */
		colourSwatches() {
			const names = {
				'--sc-primary': t('launchpad', 'Primary'),
				'--sc-primary-deep': t('launchpad', 'Primary, dark'),
				'--sc-accent': t('launchpad', 'Accent'),
				'--sc-accent-deep': t('launchpad', 'Accent, dark'),
				'--sc-work-ink': t('launchpad', 'Ink'),
				'--sc-work-text': t('launchpad', 'Text grey'),
				'--sc-work-muted': t('launchpad', 'Muted grey'),
				'--sc-work-field': t('launchpad', 'Field grey'),
			}
			return TILE_COLOUR_TOKENS.map(({ token }) => ({
				token,
				label: names[token],
				css: tileBackground(null, { colourToken: token }),
			}))
		},

		/**
		 * @return {boolean} Whether the tile opens an app.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		isAppMode() {
			return this.form.mode === 'app'
		},

		/**
		 * The two choice cards; a placed tile keeps its mode.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		modeOptions() {
			const placed = Boolean(this.tile)
			return [
				{ value: 'app', label: t('launchpad', 'An app'), hint: t('launchpad', 'One of your apps, with its icon and new items.'), disabled: (placed && !this.isAppMode) || this.inGroup },
				{ value: 'link', label: t('launchpad', 'A link'), hint: t('launchpad', 'Any web address, with a title and an icon.'), disabled: placed && this.isAppMode },
			]
		},

		/**
		 * @return {boolean} Whether the tile was stored with a former tile type.
		 * @spec openspec/changes/app-link-tile-widget/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		isLaunchTile() {
			return [LINK_PROGRAM, LINK_REMOTE, LINK_SSO].includes(this.form.linkType)
		},

		/**
		 * @return {string} The text colour as CSS.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		textCss() {
			return textColourValue(this.form.textColour)
		},

		/**
		 * @return {string} The id of the Text colour label.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		textColourLabelId() {
			return 'tile-editor-text-colour-' + (this.tile?.id ?? 'new')
		},

		/**
		 * The text colours: the theme's tile colours, white and black.
		 *
		 * @return {Array<{name: string, label: string, css: string}>}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		textColourSwatches() {
			return TEXT_COLOURS.map((name) => ({ name, label: this.colourName(name), css: textColourValue(name) }))
		},

		/**
		 * @return {{ratio: number|null, passes: boolean}} The contrast in the current theme.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		contrast() {
			// An app tile without a chosen colour is checked on the primary colour,
			// as its preview draws it.
			const colourToken = this.form.colourToken || (this.isAppMode ? '--sc-primary' : '')
			return textContrast({
				textColour: this.form.textColour,
				colourToken,
				backgroundColor: colourToken ? '' : this.form.backgroundColor,
			})
		},

		/**
		 * The contrast line as drawn: "White on primary: contrast 5.6:1, meets
		 * WCAG AA (4.5:1)", or the warning below it.
		 *
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		contrastLine() {
			const vars = {
				text: this.colourName(this.form.textColour),
				tile: this.form.colourToken || this.isAppMode
					? this.colourName(this.form.colourToken || '--sc-primary').toLowerCase()
					: t('launchpad', 'the own colour'),
				ratio: this.contrast.ratio === null ? '?' : this.contrast.ratio.toFixed(1),
			}
			if (this.contrast.passes) {
				return t('launchpad', '{text} on {tile}: contrast {ratio}:1, meets WCAG AA (4.5:1)', vars)
			}
			return t('launchpad', '{text} on {tile}: contrast {ratio}:1, below WCAG AA (4.5:1). Choose white or a lighter tile colour.', vars)
		},

		/**
		 * @return {string} The line that says the brand's defaults were used.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		brandLine() {
			if (this.brand === '' || this.isAppMode) {
				return ''
			}
			return t('launchpad', 'The {brand} tile shows its white logo, without payoff, on the brand colour.', { brand: this.brand })
		},

		/**
		 * @return {string} The primary button in app mode.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		addAppsLabel() {
			const count = this.chosenApps.length
			if (count <= 1) {
				return t('launchpad', 'Add tile')
			}
			return t('launchpad', 'Add {count} tiles', { count })
		},

		/**
		 * The preview's background: the chosen token, else the hex colour.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
		 */
		/**
		 * The name the preview tile carries: the chosen app, else the title.
		 *
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		previewName() {
			if (this.isAppMode) {
				const first = this.chosenApps[0]
				const app = first ? this.discoveredApps.find((entry) => entry.appId === first.tile?.appId) : null
				return app?.name || first?.tile?.title || this.tile?.title || t('launchpad', 'App')
			}
			return this.form.title || t('launchpad', 'Title')
		},

		/**
		 * @return {string} The first letter of the preview's name, as its glyph.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		previewInitial() {
			return this.previewName.charAt(0).toUpperCase()
		},

		/**
		 * The line beside the preview: what the tile shows (boards
		 * LpTegelToevoegen, LpTegelBewerken).
		 *
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		previewLine() {
			if (this.isAppMode) {
				return t('launchpad', 'The app\'s icon in the text colour on the tile colour, with its count of new items.')
			}
			return t('launchpad', 'A link has no count. Its title and icon are on the tile as you choose below.')
		},

		previewBackground() {
			// An app tile without a chosen colour takes the primary colour here.
			if (this.isAppMode && !this.form.colourToken) {
				return tileBackground(null, { colourToken: '--sc-primary' })
			}
			return tileBackground({ backgroundColor: this.form.backgroundColor }, { colourToken: this.form.colourToken })
		},

		/**
		 * The id the swatch group is labelled by.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
		 */
		colourLabelId() {
			return 'tile-editor-colour-' + (this.tile?.id ?? 'new')
		},

		/**
		 * The "Opens in" choices. The panel is unavailable for an outside
		 * address whose host is not on the embed allow-list.
		 *
		 * Three cards (REQ-TILE-040); none chosen ('') follows the dashboard.
		 *
		 * @return {Array<{value: string, label: string, hint: string, disabled: boolean}>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		whereLine() {
			if (!this.dashboardName) {
				return this.groupLabel
			}
			return t('launchpad', '{group} · {dashboard}', { group: this.groupLabel, dashboard: this.dashboardName })
		},

		/**
		 * Whether the Group field lets the editor move this tile into a
		 * group: a saved tile on the dashboard, on a dashboard with groups.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		canMoveIntoGroup() {
			return !this.inGroup && this.tile?.id !== undefined && this.tile?.id !== null && this.groups.length > 0
		},

		/**
		 * The Group field's choices for a dashboard tile: no group, then each
		 * group on the dashboard by its name.
		 *
		 * @return {Array<{value: number|null, label: string}>}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		groupChoices() {
			return [
				{ value: null, label: t('launchpad', 'No group') },
				...this.groups.map((group) => ({ value: group.id, label: group.title || t('launchpad', 'Untitled group') })),
			]
		},

		/**
		 * The tile's group as the Group field shows it.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		groupLabel() {
			return this.groupName || t('launchpad', 'No group')
		},

		/**
		 * The group's sort orders, by the names the form gives them; the
		 * values are the container's stored modes (src/utils/tileSort.js).
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
		 */
		hasWebAddress() {
			return ![LINK_PROGRAM, LINK_REMOTE, LINK_SSO].includes(this.form.linkType)
		},

		/**
		 * The result of the last availability check with its time.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
		 */
		lastCheck() {
			return lastCheckLine(this.lastReading, new Date(), t)
		},

		/**
		 * The frame's attributes: the dialog's, or a plain block inside Add widget.
		 *
		 * @return {object}
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
		 */
		frameProps() {
			if (this.embedded) {
				return { class: 'tile-editor-embedded', 'data-testid': 'tile-editor-embedded' }
			}
			return {
				show: this.isOpen,
				class: 'launchpad-modal tile-editor-modal',
				name: this.heading,
				size: 'large',
				'onUpdate:show': (value) => {
					this.isOpen = value
				},
			}
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
		 * @param {string} value The new web address.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		'form.linkValue': function(value) {
			this.checkPanelAllowed()
			this.applyBrandDefaults(value)
		},

		/**
		 * Follow the group's stored order when the parent changes it.
		 *
		 * @param {string} value The stored sort mode.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
			 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
			 */
			handler(newTile) {
				if (newTile) {
					this.form = {
						...launchFields({}),
						...newTile,
						internalUrl: newTile.internalUrl || '',
						iconType: newTile.iconType || 'class',
						colourToken: isColourToken(newTile.colourToken) ? newTile.colourToken : '',
						linkTarget: newTile.linkTarget || '',
						mode: newTile.mode === 'app' ? 'app' : 'link',
						textColour: isTextColour(newTile.textColour) ? newTile.textColour : DEFAULT_TEXT_COLOUR,
						iconOnly: newTile.iconOnly === true,
						pinned: newTile.pinned === true,
						showCount: newTile.showCount !== false,
					}
				} else {
					this.resetForm()
				}
				this.lastReading = null
				this.targetGroupId = null
				this.chosenApps = []
				this.brand = ''
				this.loadLastCheck()
			},
		},
	},

	created() {
		loadIconCatalogue().catch(() => {})
	},

	methods: {
		/**
		 * Choose the group a dashboard tile moves into, and show that
		 * group's sort order; no group puts the order field back.
		 *
		 * @param {number|null} groupId The chosen group, or null.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		chooseGroup(groupId) {
			const group = this.groups.find((candidate) => candidate.id === groupId) || null
			this.targetGroupId = group ? group.id : null
			this.groupSortBy = normaliseSortMode(group ? group.sortBy : this.groupSort)
		},

		/**
		 * Choose An app or A link (a new tile only).
		 *
		 * @param {string} mode 'app' or 'link'.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		chooseMode(mode) {
			if (this.tile || (mode !== 'app' && mode !== 'link')) {
				return
			}
			this.form.mode = mode
		},

		/**
		 * The name of a tile or text colour.
		 *
		 * @param {string} name The token, white or black.
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		colourName(name) {
			if (name === 'white') {
				return t('launchpad', 'White')
			}
			if (name === 'black') {
				return t('launchpad', 'Black')
			}
			return this.colourSwatches.find((swatch) => swatch.token === name)?.label || name
		},

		/**
		 * A new link tile to the Conduction, Nextcloud or sendent site takes
		 * the brand's white logo, colour and white text (REQ-DWB-007).
		 *
		 * @param {string} address The web address.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		applyBrandDefaults(address) {
			if (this.tile || this.isAppMode) {
				return
			}
			const brand = brandDefaults(address)
			if (!brand) {
				this.brand = ''
				return
			}
			this.brand = brand.brand
			this.form.icon = brand.icon
			this.form.iconType = brand.iconType
			this.form.backgroundColor = brand.backgroundColor
			this.form.colourToken = brand.colourToken
			this.form.textColour = brand.textColour
			this.form.textColor = '#ffffff'
		},

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


		/** @spec openspec/specs/tiles/spec.md */
		resetForm() {
			this.form = {
				title: '',
				icon: mdiLink,
				iconType: 'svg',
				backgroundColor: '#0082c9',
				textColor: '#ffffff',
				colourToken: '',
				mode: 'link',
				textColour: DEFAULT_TEXT_COLOUR,
				iconOnly: false,
				pinned: false,
				showCount: true,
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
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
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		saveTile() {
			// REQ-APPTILE-004: a new app tile per ticked app, with the appearance.
			if (this.isAppMode && !this.tile) {
				if (this.chosenApps.length > 0) {
					this.$emit('addApps', {
						choices: this.chosenApps,
						appearance: {
							colourToken: this.form.colourToken,
							textColour: this.form.textColour,
							showCount: this.form.showCount !== false,
							pinned: this.form.pinned === true,
							iconOnly: this.form.iconOnly === true,
						},
					})
				}
				return
			}
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
			// REQ-TILE-041: a dashboard tile moving into a group names it.
			if (this.canMoveIntoGroup && this.targetGroupId !== null) {
				tile.groupId = this.targetGroupId
				tile.groupSortBy = this.groupSortBy
			}
			this.$emit('save', tile)
		},
	},
}
</script>

<style scoped>
.tile-editor__contrast {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	margin: 6px 0 0;
}

.tile-editor__contrast.tile-editor__warning {
	padding: 8px 10px;
	border: 1px solid var(--color-warning);
	border-radius: var(--border-radius);
	background: var(--color-warning-hover, var(--color-background-hover));
}

/* Boards LpTegelToevoegen / LpTegelBewerken: fields left, Appearance right. */
.tile-editor-modal :deep(.modal-container) {
	width: 1000px;
	max-width: calc(100vw - 32px);
}

.tile-editor__columns {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 400px);
	gap: 24px;
	align-items: start;
}

@media (max-width: 900px) {
	.tile-editor__columns {
		grid-template-columns: minmax(0, 1fr);
	}
}

.tile-editor__main {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 0;
}

.tile-editor__appearance {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.tile-editor__appearance-title {
	margin: 0;
	font-size: 16px;
}

.tile-editor__appearance-preview {
	display: flex;
	align-items: center;
	gap: 16px;
}

.tile-preview--square {
	position: relative;
	flex: 0 0 96px;
	width: 96px;
	height: 96px;
	border-radius: var(--border-radius-large);
}

.tile-preview__glyph {
	font-size: 36px;
	font-weight: 700;
}

.tile-preview__pin {
	position: absolute;
	top: 4px;
	inset-inline-start: 8px;
}

.tile-editor__brand-note {
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
}

.tile-editor {
	padding: 20px;
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

.tile-editor__swatches {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	margin-bottom: 8px;
}

.tile-editor__swatch {
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border: 2px solid transparent;
	border-radius: var(--border-radius-large);
	color: #ffffff;
	font-weight: 700;
	cursor: pointer;
}

.tile-editor__swatch--on {
	outline: 2px solid var(--color-main-text);
	outline-offset: 2px;
}

.tile-editor__swatch:focus-within {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.tile-editor__swatch-input {
	position: absolute;
	inset: 0;
	margin: 0;
	opacity: 0;
	cursor: pointer;
}

.tile-editor__swatch-own {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	margin-inline-start: 8px;
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
