<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		:show.sync="isOpen"
		:name="tile ? t('launchpad', 'Edit Tile') : t('launchpad', 'Create Tile')"
		size="normal"
		@close="$emit('close')">
		<div class="tile-editor">
			<h2>{{ tile ? t('launchpad', 'Edit Tile') : t('launchpad', 'Create Tile') }}</h2>

			<div class="tile-editor__preview">
				<div
					class="tile-preview"
					:style="{
						backgroundColor: form.backgroundColor,
						color: form.textColor
					}">
					<img
						v-if="isUrlIcon"
						class="tile-preview__icon"
						:src="form.icon"
						alt="">
					<svg
						v-else
						class="tile-preview__icon"
						:style="{ fill: form.textColor }"
						viewBox="0 0 24 24">
						<path :d="form.icon" />
					</svg>
					<span class="tile-preview__title">{{ form.title }}</span>
				</div>
			</div>

			<div class="tile-editor__form">
				<NcTextField
					:value.sync="form.title"
					:label="t('launchpad', 'Title')"
					:placeholder="t('launchpad', 'Enter tile title')"
					required />

				<div class="tile-editor__field">
					<label class="tile-editor__label">{{ t('launchpad', 'Icon') }}</label>
					<CnIconBrowser
						inline
						:value="form.icon"
						:icons="iconCatalogue"
						:url-icons="nlDesignIcons"
						@input="onIcon" />
				</div>

				<div class="form-row">
					<div class="form-row__item">
						<label>{{ t('launchpad', 'Background Color') }}</label>
						<NcColorPicker
							:value.sync="form.backgroundColor"
							@input="form.backgroundColor = $event">
							<NcButton
								type="tertiary"
								:aria-label="t('launchpad', 'Pick background color')">
								<template #icon>
									<div
										class="color-preview"
										:style="{ backgroundColor: form.backgroundColor }" />
								</template>
								{{ form.backgroundColor }}
							</NcButton>
						</NcColorPicker>
					</div>

					<div class="form-row__item">
						<label>{{ t('launchpad', 'Text Color') }}</label>
						<NcColorPicker
							:value.sync="form.textColor"
							@input="form.textColor = $event">
							<NcButton
								type="tertiary"
								:aria-label="t('launchpad', 'Pick text color')">
								<template #icon>
									<div
										class="color-preview"
										:style="{ backgroundColor: form.textColor }" />
								</template>
								{{ form.textColor }}
							</NcButton>
						</NcColorPicker>
					</div>
				</div>

				<NcTextField
					:value.sync="form.linkValue"
					:label="t('launchpad', 'URL')"
					:placeholder="t('launchpad', 'https://example.com or /apps/files')"
					type="text" />

				<div class="tile-editor__actions">
					<NcButton
						v-if="tile"
						type="error"
						@click="$emit('delete')">
						{{ t('launchpad', 'Delete') }}
					</NcButton>
					<div class="tile-editor__actions-right">
						<NcButton @click="$emit('close')">
							{{ t('launchpad', 'Cancel') }}
						</NcButton>
						<NcButton type="primary" @click="saveTile">
							{{ t('launchpad', 'Save') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcTextField, NcColorPicker, CnIconBrowser, isCustomIconUrl } from '@conduction/nextcloud-vue'
import { mdiLink } from '@mdi/js'
import { ICON_CATALOGUE } from '../services/iconCatalogue.js'

export default {
	name: 'TileEditor',

	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcColorPicker,
		CnIconBrowser,
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
			},
			// Curated NlDesign icons offered on the picker's Custom tab. Each
			// renders from its public app URL and is stored as that URL.
			nlDesignIcons: [
				'Airplane', 'Bell', 'Bike', 'Building', 'Bus', 'Cake', 'Calendar',
				'Camera', 'Car', 'Certificate', 'Clock', 'Cogwheel', 'Document',
				'Earth', 'Euro', 'Flower', 'Folder', 'Heart', 'House', 'Image',
				'LightBulb', 'Lightning', 'Mail', 'Map', 'Megaphone', 'Monument',
				'Park', 'Parking', 'Person', 'Phone', 'Search', 'Star', 'Tree',
				'Wallet',
			].map((name) => ({ label: name, url: this.getNlDesignIconUrl(name) })),
		}
	},

	computed: {
		isOpen: {
			/** @spec openspec/specs/tiles/spec.md */
			get() {
				return this.open
			},
			/** @spec openspec/specs/tiles/spec.md */
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
		/** The shared MDI icon catalogue passed to CnIconBrowser. */
		iconCatalogue() {
			return ICON_CATALOGUE
		},
	},

	watch: {
		tile: {
			immediate: true,
			/** @spec openspec/specs/tiles/spec.md */
			handler(newTile) {
				if (newTile) {
					this.form = {
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
		/** @spec openspec/specs/tiles/spec.md */
		getNlDesignIconUrl(iconName) {
			// Generate URL for NlDesign icons
			return `${window.location.origin}/apps/nldesign/img/icons/${iconName}.svg`
		},

		/**
		 * Store the picked icon. CnIconBrowser emits an SVG path (MDI) or a URL
		 * (NlDesign/upload); derive iconType from the value so TileWidget renders
		 * the right element.
		 *
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
			}
		},

		/** @spec openspec/specs/tiles/spec.md */
		saveTile() {
			this.$emit('save', { ...this.form })
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
	word-break: break-word;
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

.tile-editor__label {
	font-weight: 600;
	font-size: 14px;
}
</style>
