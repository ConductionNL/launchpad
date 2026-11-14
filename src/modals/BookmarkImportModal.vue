<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-model:show="isOpen"
		class="launchpad-modal"
		:name="t('launchpad', 'Import bookmarks')"
		size="normal"
		@close="$emit('close')">
		<div class="bookmark-import" data-testid="bookmark-import">
			<h2>{{ t('launchpad', 'Import bookmarks') }}</h2>
			<p class="bookmark-import__hint">
				{{ t('launchpad', 'Choose the bookmarks file your browser exported, then tick what to add.') }}
			</p>

			<input
				type="file"
				accept=".html,.htm,text/html"
				:aria-label="t('launchpad', 'Import bookmarks')"
				data-testid="bookmark-import-file"
				@change="onFile($event.target.files && $event.target.files[0])">

			<p
				v-if="error"
				class="bookmark-import__error"
				role="alert"
				data-testid="bookmark-import-error">
				{{ error }}
			</p>

			<fieldset v-if="parsed && parsed.folders.length > 0" class="bookmark-import__group">
				<legend>{{ t('launchpad', 'Folders') }}</legend>
				<label
					v-for="(folder, index) in parsed.folders"
					:key="'f' + index"
					class="bookmark-import__option">
					<input
						type="checkbox"
						:checked="folderIndexes.has(index)"
						:disabled="folder.bookmarks.length === 0"
						data-testid="bookmark-import-folder"
						@change="toggle(folderIndexes, index, $event.target.checked)">
					{{ folder.name }} ({{ folder.bookmarks.length }})
				</label>
			</fieldset>

			<fieldset v-if="parsed && parsed.bookmarks.length > 0" class="bookmark-import__group">
				<legend>{{ t('launchpad', 'Loose bookmarks') }}</legend>
				<label
					v-for="(bookmark, index) in parsed.bookmarks"
					:key="'b' + index"
					class="bookmark-import__option">
					<input
						type="checkbox"
						:checked="bookmarkIndexes.has(index)"
						data-testid="bookmark-import-bookmark"
						@change="toggle(bookmarkIndexes, index, $event.target.checked)">
					{{ bookmark.title }}
				</label>
			</fieldset>

			<p
				v-if="skipped.length > 0"
				class="bookmark-import__note"
				role="status"
				data-testid="bookmark-import-skipped">
				{{ t('launchpad', '{count} skipped: only web addresses can become tiles', { count: skipped.length }) }}
			</p>

			<div class="bookmark-import__actions">
				<NcButton @click="close">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="!canImport"
					data-testid="bookmark-import-confirm"
					@click="submit">
					{{ t('launchpad', 'Import bookmarks') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcModal } from '@nextcloud/vue'
import { chosenBookmarks, readBookmarkFile } from '../utils/bookmarkFile.js'

/**
 * BookmarkImportModal — import a browser bookmarks file as tiles
 * (launcher-bookmark-import REQ-BMI-001..004).
 *
 * The file is read in the browser; the user ticks folders and loose
 * bookmarks; only those are sent. The server skips addresses that are not
 * web addresses and lists them back, and refuses with 409 an import the
 * dashboard has no room for.
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */
export default {
	name: 'BookmarkImportModal',

	components: { NcButton, NcModal },

	props: {
		/** Whether the dialog is open. */
		open: { type: Boolean, default: false },
		/**
		 * Sends the chosen bookmarks; resolves to `{placements, skipped}` and
		 * rejects with the axios error (409 carries `limit` and `current`).
		 */
		importBookmarks: { type: Function, required: true },
	},

	emits: ['close', 'imported'],

	data() {
		return {
			parsed: null,
			folderIndexes: new Set(),
			bookmarkIndexes: new Set(),
			error: '',
			skipped: [],
			busy: false,
		}
	},

	computed: {
		/**
		 * Bound to the modal's visibility.
		 *
		 * @return {boolean}
		 */
		isOpen: {
			get() {
				return this.open
			},

			set(value) {
				if (!value) {
					this.close()
				}
			},
		},

		/**
		 * Whether something is chosen and nothing is in flight.
		 *
		 * @return {boolean}
		 */
		canImport() {
			return !this.busy && this.parsed !== null && (this.folderIndexes.size + this.bookmarkIndexes.size) > 0
		},
	},

	methods: {
		/**
		 * Read the chosen file, refusing a too large one before anything is sent
		 * (REQ-BMI-004), and tick every folder and bookmark in it.
		 *
		 * @param {File|undefined} file The chosen file.
		 * @return {Promise<void>}
		 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-large-files-are-refused-in-the-browser-req-bmi-004
		 */
		async onFile(file) {
			this.reset()
			if (!file) {
				return
			}
			try {
				const parsed = await readBookmarkFile(file)
				if (parsed.total === 0) {
					this.error = t('launchpad', 'This file holds no bookmarks.')
					return
				}
				this.parsed = parsed
				this.folderIndexes = new Set(parsed.folders.map((_f, i) => i).filter((i) => parsed.folders[i].bookmarks.length > 0))
				this.bookmarkIndexes = new Set(parsed.bookmarks.map((_b, i) => i))
			} catch (e) {
				this.error = this.refusal(e)
			}
		},

		/**
		 * The message for a refused file.
		 *
		 * @param {Error & {code?: string}} e The refusal.
		 * @return {string}
		 */
		refusal(e) {
			if (e && e.code === 'too-large') {
				return t('launchpad', 'This file is larger than 5 MB. Export one folder at a time.')
			}
			if (e && e.code === 'too-many') {
				return t('launchpad', 'This file has more than 2,000 bookmarks. Export one folder at a time.')
			}
			return t('launchpad', 'This file holds no bookmarks.')
		},

		/**
		 * Tick or untick one folder or bookmark.
		 *
		 * @param {Set<number>} set The chosen indexes.
		 * @param {number} index The item.
		 * @param {boolean} checked Whether it is ticked now.
		 */
		toggle(set, index, checked) {
			const next = new Set(set)
			if (checked) {
				next.add(index)
			} else {
				next.delete(index)
			}
			if (set === this.folderIndexes) {
				this.folderIndexes = next
			} else {
				this.bookmarkIndexes = next
			}
		},

		/**
		 * Send the chosen bookmarks. On success the dialog reports the skipped
		 * ones or closes; on 409 it says how much room is left (REQ-BMI-003).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-respects-the-widget-quota-as-one-operation-req-bmi-003
		 */
		async submit() {
			if (!this.canImport) {
				return
			}
			this.busy = true
			this.error = ''
			try {
				const result = await this.importBookmarks(chosenBookmarks(this.parsed, this.folderIndexes, this.bookmarkIndexes))
				this.skipped = Array.isArray(result?.skipped) ? result.skipped : []
				this.$emit('imported', result)
				if (this.skipped.length === 0) {
					this.close()
				} else {
					this.parsed = null
				}
			} catch (e) {
				const body = e?.response?.data
				if (e?.response?.status === 409 && Number.isFinite(body?.limit) && Number.isFinite(body?.current)) {
					this.error = t('launchpad', 'This dashboard can take {count} more. Choose fewer folders or bookmarks.', { count: Math.max(0, body.limit - body.current) })
				} else {
					this.error = t('launchpad', 'The bookmarks could not be imported.')
				}
			} finally {
				this.busy = false
			}
		},

		/** Clear what the dialog read and reported. */
		reset() {
			this.parsed = null
			this.folderIndexes = new Set()
			this.bookmarkIndexes = new Set()
			this.error = ''
			this.skipped = []
		},

		/** Close and forget. */
		close() {
			this.reset()
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.bookmark-import {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
}

.bookmark-import__hint,
.bookmark-import__note {
	color: var(--color-text-maxcontrast);
}

.bookmark-import__error {
	color: var(--color-error-text, var(--color-error));
}

.bookmark-import__group {
	display: flex;
	flex-direction: column;
	gap: 4px;
	max-height: 240px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 8px;
}

.bookmark-import__option {
	display: flex;
	gap: 8px;
	align-items: center;
}

.bookmark-import__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
