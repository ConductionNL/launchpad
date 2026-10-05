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

				<!-- launcher-tile-launch-types REQ-TLT-001 to REQ-TLT-003. -->
				<NcSelect
					v-model="selectedLinkType"
					:options="linkTypeOptions"
					:clearable="false"
					:inputLabel="t('launchpad', 'This tile opens')"
					data-testid="tile-link-type" />

				<template v-if="isWebLink">
					<NcTextField
						v-model="form.linkValue"
						:label="t('launchpad', 'URL')"
						:placeholder="
							t('launchpad', 'https://example.com or /apps/files')
						"
						type="text" />

					<!-- launcher-tile-internal-address REQ-TIA-002/003. -->
					<NcTextField
						v-model="form.internalUrl"
						:label="t('launchpad', 'Address on the office network')"
						:placeholder="
							t('launchpad', 'Optional, for example http://zaken.intern')
						"
						type="text" />
					<p
						v-if="form.internalUrl"
						class="tile-editor__address-in-effect"
						data-testid="tile-address-in-effect">
						{{ addressInEffect }}
					</p>
				</template>

				<template v-else-if="form.linkType === 'program'">
					<NcTextField
						v-model="form.linkValue"
						:label="t('launchpad', 'Program address')"
						placeholder="ms-word:ofe|u|https://"
						type="text"
						data-testid="tile-program-address" />
					<p class="tile-editor__hint" data-testid="tile-program-schemes">
						{{ programSchemesLine }}
					</p>
				</template>

				<template v-else-if="form.linkType === 'remote-desktop'">
					<div
						class="tile-editor__remote-mode"
						role="radiogroup"
						:aria-label="t('launchpad', 'How the remote desktop starts')">
						<NcCheckboxRadioSwitch
							v-model="form.remote.mode"
							value="gateway"
							name="tile-remote-mode"
							type="radio">
							{{ t('launchpad', 'Through a web gateway') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-model="form.remote.mode"
							value="rdp"
							name="tile-remote-mode"
							type="radio">
							{{ t('launchpad', 'As a remote desktop file') }}
						</NcCheckboxRadioSwitch>
					</div>
					<NcTextField
						v-if="form.remote.mode === 'gateway'"
						v-model="form.remote.url"
						:label="t('launchpad', 'Gateway address')"
						placeholder="https://desktop.example.nl/guacamole/"
						type="text"
						data-testid="tile-remote-url" />
					<template v-else>
						<NcTextField
							v-model="form.remote.host"
							:label="t('launchpad', 'Computer name')"
							placeholder="rds01.example.local"
							type="text"
							data-testid="tile-remote-host" />
						<NcTextField
							v-model="form.remote.port"
							:label="t('launchpad', 'Port')"
							placeholder="3389"
							type="number" />
						<NcTextField
							v-model="form.remote.remoteApp"
							:label="t('launchpad', 'Published program (optional)')"
							placeholder="||Belastingen"
							type="text" />
						<NcTextField
							v-model="form.remote.gateway"
							:label="t('launchpad', 'Remote desktop gateway (optional)')"
							placeholder="rdgw.example.nl"
							type="text" />
					</template>
				</template>

				<template v-else-if="form.linkType === 'sso'">
					<NcSelect
						v-model="selectedSsoTemplate"
						:options="ssoTemplateOptions"
						:inputLabel="t('launchpad', 'Identity provider')"
						data-testid="tile-sso-template" />
					<p
						v-if="ssoTemplateOptions.length === 0"
						class="tile-editor__hint"
						data-testid="tile-sso-none">
						{{
							t(
								'launchpad',
								'An administrator has not added an identity provider yet.',
							)
						}}
					</p>
					<NcTextField
						v-model="form.sso.appId"
						:label="t('launchpad', 'App id at the identity provider')"
						type="text"
						data-testid="tile-sso-app-id" />
				</template>

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
import { t } from '@nextcloud/l10n'
import { validateHealthPingConfig } from '../services/healthPingClient.js'
import { ICON_CATALOGUE, normaliseIconValue } from '../services/iconCatalogue.js'

const MIN_PING_INTERVAL = 15

/**
 * A remote desktop tile's settings before the author fills them in.
 *
 * @return {object} The empty settings.
 */
function emptyRemote() {
	return {
		mode: 'gateway',
		url: '',
		host: '',
		port: '',
		remoteApp: '',
		gateway: '',
	}
}

/**
 * A single sign-on tile's settings before the author fills them in.
 *
 * @return {object} The empty settings.
 */
function emptySso() {
	return { template: '', appId: '' }
}
const DEFAULT_PING_INTERVAL = 60
const DEFAULT_EXPECTED_STATUS = 200

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

	inject: {
		/** Whether this request comes from an office network (REQ-TIA-003). */
		injectedOnOfficeNetwork: {
			from: 'onOfficeNetwork',
			default: false,
		},

		/** The program schemes an administrator allowed (REQ-TLT-001). */
		injectedAllowedSchemes: {
			from: 'tileAllowedSchemes',
			default: () => [],
		},

		/** The single sign-on launch templates (REQ-TLT-003). */
		injectedSsoLaunchTemplates: {
			from: 'ssoLaunchTemplates',
			default: () => [],
		},
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		tile: {
			type: Object,
			default: null,
		},
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
				remote: emptyRemote(),
				sso: emptySso(),
				healthPingEnabled: false,
				healthUrl: '',
				expectedStatus: DEFAULT_EXPECTED_STATUS,
				pingInterval: DEFAULT_PING_INTERVAL,
			},

			healthUrlError: '',
			MIN_PING_INTERVAL,
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
		 * The kinds of thing a tile opens (REQ-TLT-001 to REQ-TLT-003). A
		 * Nextcloud app is listed only for tiles that already open one.
		 *
		 * @return {Array<{id: string, label: string}>} The options.
		 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
		 */
		linkTypeOptions() {
			const options = [
				{ id: 'url', label: t('launchpad', 'Web address') },
				{ id: 'program', label: t('launchpad', 'Program on this computer') },
				{ id: 'remote-desktop', label: t('launchpad', 'Remote desktop') },
				{ id: 'sso', label: t('launchpad', 'Single sign-on app') },
			]
			if (this.form.linkType === 'app') {
				options.unshift({ id: 'app', label: t('launchpad', 'Nextcloud app') })
			}
			return options
		},

		selectedLinkType: {
			/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
			get() {
				return (
					this.linkTypeOptions.find(
						(option) => option.id === this.form.linkType,
					) || null
				)
			},

			/**
			 * @param {{id: string}|null} option The chosen type.
			 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
			 */
			set(option) {
				this.form.linkType = option?.id || 'url'
			},
		},

		/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
		isWebLink() {
			return this.form.linkType === 'url' || this.form.linkType === 'app'
		},

		/**
		 * Which program address types the administrator allows.
		 *
		 * @return {string} The line under the program address.
		 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
		 */
		programSchemesLine() {
			const schemes = this.injectedAllowedSchemes || []
			if (schemes.length === 0) {
				return t(
					'launchpad',
					'An administrator has not allowed any program address yet, so this tile does nothing.',
				)
			}
			return t('launchpad', 'Allowed program addresses: {schemes}', {
				schemes: schemes.map((scheme) => scheme + ':').join(', '),
			})
		},

		/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
		ssoTemplateOptions() {
			return (this.injectedSsoLaunchTemplates || []).map((template) => ({
				id: template.key,
				label: template.name,
			}))
		},

		selectedSsoTemplate: {
			/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
			get() {
				return (
					this.ssoTemplateOptions.find(
						(option) => option.id === this.form.sso.template,
					) || null
				)
			},

			/**
			 * @param {{id: string}|null} option The chosen template.
			 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
			 */
			set(option) {
				this.form.sso.template = option?.id || ''
			},
		},

		/**
		 * Which address the author would get right now (REQ-TIA-003).
		 *
		 * @spec openspec/specs/tiles/spec.md
		 */
		addressInEffect() {
			return this.injectedOnOfficeNetwork === true
				? t(
						'launchpad',
						'You are on the office network: this tile opens the internal address',
					)
				: t(
						'launchpad',
						'You are not on the office network: this tile opens the main address',
					)
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
						...newTile,
						iconType: newTile.iconType || 'class',
						remote: { ...emptyRemote(), ...(newTile.remote || {}) },
						sso: { ...emptySso(), ...(newTile.sso || {}) },
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
				remote: emptyRemote(),
				sso: emptySso(),
				healthPingEnabled: false,
				healthUrl: '',
				expectedStatus: DEFAULT_EXPECTED_STATUS,
				pingInterval: DEFAULT_PING_INTERVAL,
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
				...this.launchSettings(),
				expectedStatus:
					Number(this.form.expectedStatus) || DEFAULT_EXPECTED_STATUS,
				pingInterval: this.clampPingInterval(this.form.pingInterval),
			})
		},

		/**
		 * The settings the chosen type keeps, trimmed; the others are null so
		 * a tile that changes type drops them (REQ-TLT-002, REQ-TLT-003).
		 * Remote desktop and sign-on tiles carry no address of their own.
		 *
		 * @return {{remote: object|null, sso: object|null, linkValue?: string}} The settings.
		 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
		 */
		launchSettings() {
			const type = this.form.linkType
			if (type === 'remote-desktop') {
				const remote = this.form.remote
				const settings =
					remote.mode === 'gateway'
						? { mode: 'gateway', url: String(remote.url || '').trim() }
						: {
								mode: 'rdp',
								host: String(remote.host || '').trim(),
								port: remote.port === '' ? null : Number(remote.port),
								remoteApp: String(remote.remoteApp || '').trim(),
								gateway: String(remote.gateway || '').trim(),
							}
				return { remote: settings, sso: null, linkValue: '' }
			}
			if (type === 'sso') {
				return {
					remote: null,
					sso: {
						template: this.form.sso.template,
						appId: String(this.form.sso.appId || '').trim(),
					},
					linkValue: '',
				}
			}
			return { remote: null, sso: null }
		},
	},
}
</script>

<style scoped>
.tile-editor {
	padding: 20px;
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
