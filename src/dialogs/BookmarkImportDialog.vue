<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Import bookmarks')"
		:open="open"
		size="normal"
		@update:open="onOpenChange">
		<template #default>
			<div class="bookmark-import">
				<p class="bookmark-import__intro">
					{{
						t(
							'launchpad',
							'Choose the bookmarks file your browser exports. Each folder you pick becomes a group of tiles at the bottom of this dashboard.',
						)
					}}
				</p>
				<label class="bookmark-import__file">
					{{ t('launchpad', 'Bookmarks file') }}
					<input
						type="file"
						accept=".html,.htm,text/html"
						data-testid="bookmark-file"
						@change="onFile" />
				</label>

				<p v-if="fileError" class="bookmark-import__error" role="alert">
					{{ fileError }}
				</p>

				<fieldset v-if="parsed" class="bookmark-import__choice">
					<legend>{{ t('launchpad', 'What to import') }}</legend>
					<label
						v-for="(folder, index) in parsed.folders"
						:key="'f' + index"
						class="bookmark-import__option">
						<input
							v-model="pickedFolders"
							type="checkbox"
							:value="index" />
						{{
							t('launchpad', '{name} ({count})', {
								name: folder.name,
								count: folder.bookmarks.length,
							})
						}}
					</label>
					<label
						v-for="(bookmark, index) in parsed.bookmarks"
						:key="'b' + index"
						class="bookmark-import__option">
						<input
							v-model="pickedBookmarks"
							type="checkbox"
							:value="index" />
						{{ bookmark.title || bookmark.url }}
					</label>
					<p v-if="parsed.total === 0">
						{{ t('launchpad', 'This file holds no bookmarks.') }}
					</p>
				</fieldset>

				<p v-if="result" role="status" class="bookmark-import__result">
					{{
						t('launchpad', 'Tiles added: {count}', {
							count: result.tiles,
						})
					}}
				</p>
				<div
					v-if="result && result.skipped.length > 0"
					class="bookmark-import__skipped">
					<p>
						{{
							n(
								'launchpad',
								'{count} bookmark skipped: only web addresses can become tiles',
								'{count} bookmarks skipped: only web addresses can become tiles',
								result.skipped.length,
								{ count: result.skipped.length },
							)
						}}
					</p>
					<ul>
						<li v-for="(item, index) in result.skipped" :key="index">
							{{ item.title || item.url }}
						</li>
					</ul>
				</div>
				<p v-if="submitError" class="bookmark-import__error" role="alert">
					{{ submitError }}
				</p>
			</div>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="onOpenChange(false)">
				{{ result ? t('launchpad', 'Close') : t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				v-if="!result"
				variant="primary"
				data-testid="bookmark-import-confirm"
				:disabled="!canImport || importing"
				@click="submit">
				{{ t('launchpad', 'Import') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { readBookmarksFile } from '../utils/parseBookmarks.js'

/**
 * BookmarkImportDialog: pick a browser bookmarks file, choose folders and
 * loose bookmarks, and import them as tiles (launcher-bookmark-import,
 * REQ-BMI-001..004). The file is read in the browser; only the chosen items
 * are sent. Emits `imported` with the created placements.
 *
 * @spec openspec/specs/tiles/spec.md
 */
export default {
	name: 'BookmarkImportDialog',

	components: { NcButton, NcDialog },

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		/** The dashboard to import onto. */
		dashboardId: {
			type: [Number, String],
			required: true,
		},
	},

	emits: ['update:open', 'imported'],

	data() {
		return {
			parsed: null,
			pickedFolders: [],
			pickedBookmarks: [],
			fileError: '',
			submitError: '',
			importing: false,
			result: null,
		}
	},

	computed: {
		/** @spec openspec/specs/tiles/spec.md */
		canImport() {
			return this.pickedFolders.length > 0 || this.pickedBookmarks.length > 0
		},
	},

	methods: {
		t,
		n,

		/**
		 * Close, and forget the file for the next time.
		 *
		 * @param {boolean} value The new open state.
		 * @spec openspec/specs/tiles/spec.md
		 */
		onOpenChange(value) {
			if (!value) {
				this.reset()
			}
			this.$emit('update:open', value)
		},

		/** @spec openspec/specs/tiles/spec.md */
		reset() {
			this.parsed = null
			this.pickedFolders = []
			this.pickedBookmarks = []
			this.fileError = ''
			this.submitError = ''
			this.result = null
		},

		/**
		 * Read the chosen file in the browser (REQ-BMI-004 limits first).
		 *
		 * @param {Event} event The file input change.
		 * @spec openspec/specs/tiles/spec.md
		 */
		async onFile(event) {
			const file = event?.target?.files?.[0]
			this.reset()
			if (!file) {
				return
			}
			try {
				this.parsed = await readBookmarksFile(file)
			} catch (error) {
				this.fileError = this.fileErrorMessage(error?.code)
			}
		},

		/**
		 * The message for a refused file.
		 *
		 * @param {string} code `too-large`, `too-many` or anything else.
		 * @return {string} The message.
		 * @spec openspec/specs/tiles/spec.md
		 */
		fileErrorMessage(code) {
			if (code === 'too-large') {
				return t(
					'launchpad',
					'This file is larger than 5 MB. Export one folder at a time.',
				)
			}
			if (code === 'too-many') {
				return t(
					'launchpad',
					'This file has more than 2,000 bookmarks. Export one folder at a time.',
				)
			}
			return t('launchpad', 'This file could not be read as a bookmarks file.')
		},

		/**
		 * Send the chosen folders and bookmarks (REQ-BMI-001).
		 *
		 * @spec openspec/specs/tiles/spec.md
		 */
		async submit() {
			this.importing = true
			this.submitError = ''
			const payload = {
				folders: this.pickedFolders.map(
					(index) => this.parsed.folders[index],
				),

				bookmarks: this.pickedBookmarks.map(
					(index) => this.parsed.bookmarks[index],
				),
			}
			try {
				const url = generateUrl(
					'/apps/launchpad/api/dashboard/{id}/tiles/import',
					{ id: this.dashboardId },
				)
				const response = await axios.post(url, payload)
				this.result = {
					tiles: 0,
					skipped: [],
					placements: [],
					...(response?.data || {}),
				}
				this.$emit('imported', this.result.placements)
			} catch (error) {
				this.submitError = this.submitErrorMessage(error?.response)
			} finally {
				this.importing = false
			}
		},

		/**
		 * The message for a refused import (REQ-BMI-003 names the room left).
		 *
		 * @param {object} response The error response.
		 * @return {string} The message.
		 * @spec openspec/specs/tiles/spec.md
		 */
		submitErrorMessage(response) {
			if (response?.status === 409) {
				const room = Math.max(
					0,
					Number(response.data?.limit) - Number(response.data?.current),
				)
				return t(
					'launchpad',
					'Room left on this dashboard: {count}. Pick fewer folders.',
					{ count: room },
				)
			}
			return (
				response?.data?.error
				|| t('launchpad', 'The import failed; nothing was added.')
			)
		},
	},
}
</script>

<style scoped>
.bookmark-import {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.bookmark-import__file {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.bookmark-import__choice {
	display: flex;
	flex-direction: column;
	gap: 4px;
	max-height: 320px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 8px 12px;
}

.bookmark-import__option {
	display: flex;
	gap: 8px;
	align-items: center;
}

.bookmark-import__error {
	color: var(--color-error-text);
}
</style>
