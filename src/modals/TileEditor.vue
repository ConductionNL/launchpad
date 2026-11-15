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
		:name="tile ? t('launchpad', 'Edit Tile') : t('launchpad', 'Create Tile')"
		size="normal"
		@close="$emit('close')">
		<div class="tile-editor">
			<h2>
				{{
					tile
						? t('launchpad', 'Edit Tile')
						: t('launchpad', 'Create Tile')
				}}
			</h2>

			<div class="tile-editor__preview">
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
			</div>

			<div class="tile-editor__form">
				<NcTextField
					v-model="form.title"
					:label="t('launchpad', 'Title')"
					:placeholder="t('launchpad', 'Enter tile title')"
					required />

				<div class="tile-editor__field">
					<!-- No `url-icon-groups`: CnIconBrowser ships the NL Design sets
					     (Gemeente / Den Haag / RVO) by default and fetches RVO's
					     1.9 MB only when its tab is opened. Passing them explicitly,
					     as this used to, forced the whole pack into the eager bundle. -->
					<CnIconBrowser
						inline
						:label="t('launchpad', 'Icon')"
						:value="displayIcon"
						:icons="iconCatalogue"
						@input="onIcon" />
				</div>

				<div class="form-row">
					<div class="form-row__item">
						<label>{{ t('launchpad', 'Background Color') }}</label>
						<NcColorPicker v-model="form.backgroundColor">
							<NcButton
								variant="tertiary"
								:aria-label="
									t('launchpad', 'Pick background color')
								">
								<template #icon>
									<div
										class="color-preview"
										:style="{
											backgroundColor: form.backgroundColor,
										}" />
								</template>
								{{ form.backgroundColor }}
							</NcButton>
						</NcColorPicker>
					</div>

					<div class="form-row__item">
						<label>{{ t('launchpad', 'Text Color') }}</label>
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
								{{ form.textColor }}
							</NcButton>
						</NcColorPicker>
					</div>
				</div>

				<!-- launcher-tile-launch-types: what the tile opens. The server
				     checks every type (TileLinkValidator); an sso tile's address
				     is resolved there from the template, so it has no address field. -->
				<NcSelect
					:modelValue="form.linkType"
					:options="linkTypeOptions"
					:inputLabel="t('launchpad', 'Opens')"
					:reduce="(option) => option.value"
					:clearable="false"
					label="label"
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

				<NcTextField
					v-else
					v-model="form.linkValue"
					:label="t('launchpad', 'URL')"
					:placeholder="
						t('launchpad', 'https://example.com or /apps/files')
					"
					type="text" />

				<div class="tile-editor__health-ping">
					<NcCheckboxRadioSwitch
						:modelValue="form.healthPingEnabled"
						type="switch"
						@update:modelValue="onHealthPingToggle">
						{{
							t('launchpad', 'Show a live status badge (health ping)')
						}}
					</NcCheckboxRadioSwitch>

					<template v-if="form.healthPingEnabled">
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
						{{ t('launchpad', 'Delete') }}
					</NcButton>
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
import { FLAG_PROP, NULLABLE_OBJECT_PROP } from '../constants/propDefinitions.js'
import { validateHealthPingConfig } from '../services/healthPingClient.js'
import { ICON_CATALOGUE, normaliseIconValue } from '../services/iconCatalogue.js'
import { loadInitialState } from '../utils/loadInitialState.js'
import { DEFAULT_RDP_PORT, launchFields, LINK_PROGRAM, LINK_REMOTE, LINK_SSO } from '../utils/tileLaunch.js'

const MIN_PING_INTERVAL = 15
const DEFAULT_PING_INTERVAL = 60
const DEFAULT_EXPECTED_STATUS = 200

/**
 * The admin's allowed program schemes and the sign-on template names.
 *
 * @return {{schemes: string[], templates: Array<{key: string, name: string}>}}
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
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
	},

	props: {
		open: FLAG_PROP,

		tile: NULLABLE_OBJECT_PROP,
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
				healthPingEnabled: false,
				healthUrl: '',
				expectedStatus: DEFAULT_EXPECTED_STATUS,
				pingInterval: DEFAULT_PING_INTERVAL,
				...launchFields({}),
			},

			healthUrlError: '',
			MIN_PING_INTERVAL,
			DEFAULT_RDP_PORT,
			// launcher-tile-launch-types: the admin's allowed schemes and the
			// names of the sign-on templates (never their addresses).
			launchOptions: tileLaunchOptions(),
		}
	},

	computed: {
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
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
		 */
		linkTypeOptions() {
			const options = [
				{ value: 'url', label: t('launchpad', 'Web address') },
				{ value: LINK_PROGRAM, label: t('launchpad', 'Program on this computer') },
				{ value: LINK_REMOTE, label: t('launchpad', 'Remote desktop') },
				{ value: LINK_SSO, label: t('launchpad', 'Single sign-on app') },
			]
			const current = this.form.linkType
			if (current && !options.some((option) => option.value === current)) {
				options.unshift({
					value: current,
					label: current === 'app' ? t('launchpad', 'Nextcloud app') : t('launchpad', 'Link'),
				})
			}
			return options
		},

		/**
		 * The sign-on templates an author picks from.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
		 */
		ssoTemplateOptions() {
			return this.launchOptions.templates.map((template) => ({ value: template.key, label: template.name }))
		},

		/**
		 * The helper line under a program address: which schemes work.
		 *
		 * @return {string}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
		 */
		programHelp() {
			if (this.launchOptions.schemes.length === 0) {
				return t('launchpad', 'No program schemes are allowed yet. An administrator allows them on the LaunchPad admin page.')
			}
			return t('launchpad', 'Allowed schemes: {schemes}', { schemes: this.launchOptions.schemes.join(', ') })
		},
	},

	watch: {
		tile: {
			immediate: true,
			/**
			 * Seed the form from the tile being edited.
			 *
			 * @param {object|null} newTile Tile to edit; null opens a blank
			 *   form for a new tile.
			 * @spec openspec/specs/tiles/spec.md
			 */
			handler(newTile) {
				if (newTile) {
					this.form = {
						...launchFields({}),
						...newTile,
						iconType: newTile.iconType || 'class',
					}
				} else {
					this.resetForm()
				}
			},
		},
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
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
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
		 */
		onHealthPingToggle(value) {
			this.form.healthPingEnabled = value
			if (!value) {
				this.healthUrlError = ''
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

		/** @spec openspec/specs/tiles/spec.md */
		saveTile() {
			this.$emit('save', {
				...this.form,
				expectedStatus:
					Number(this.form.expectedStatus) || DEFAULT_EXPECTED_STATUS,
				pingInterval: this.clampPingInterval(this.form.pingInterval),
			})
		},
	},
}
</script>

<style scoped>
.tile-editor {
	padding: 20px;
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

.tile-editor__preview {
	display: flex;
	justify-content: center;
	margin-bottom: 30px;
}

.tile-preview {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 120px;
	height: 120px;
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

.tile-editor__hint-small {
	margin: -4px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
