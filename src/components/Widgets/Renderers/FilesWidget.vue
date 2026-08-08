<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="files-widget">
		<div class="files-widget__toolbar">
			<nav
				class="files-widget__breadcrumb"
				:aria-label="t('launchpad', 'Folder breadcrumb')">
				<button
					type="button"
					class="files-widget__crumb files-widget__crumb--root"
					:aria-current="currentSubPath === '/' ? 'page' : null"
					@click="navigateTo('/')">
					{{ t('launchpad', 'Root') }}
				</button>
				<!-- Vue 3 keys the whole `<template v-for>` fragment, so the key
				     belongs on the `<template>`; per-child keys (the Vue 2 form)
				     are ignored and leave the breadcrumb list unkeyed. -->
				<template v-for="(segment, index) in pathSegments" :key="index">
					<span
						aria-hidden="true"
						class="files-widget__separator">/</span>
					<button
						type="button"
						class="files-widget__crumb"
						:aria-current="index === pathSegments.length - 1 ? 'page' : null"
						@click="navigateTo(segmentPathTo(index))">
						{{ segment }}
					</button>
				</template>
			</nav>

			<input
				v-model="searchQuery"
				type="search"
				class="files-widget__search"
				:aria-label="t('launchpad', 'Search this folder')"
				:placeholder="t('launchpad', 'Search this folder…')">

			<button
				v-if="canShowUpload"
				type="button"
				class="files-widget__upload"
				@click="triggerUpload">
				{{ t('launchpad', 'Upload File') }}
			</button>
			<input
				ref="fileInput"
				type="file"
				multiple
				class="files-widget__file-input"
				:aria-hidden="true"
				tabindex="-1"
				@change="onFileInputChange">
		</div>

		<div v-if="loading" class="files-widget__state">
			{{ t('launchpad', 'Loading folder…') }}
		</div>

		<div
			v-else-if="errorCode === 'no_access'"
			class="files-widget__state files-widget__state--no-access">
			{{ t('launchpad', 'You don\'t have access to this folder.') }}
		</div>

		<div
			v-else-if="errorCode === 'folder_not_found'"
			class="files-widget__state files-widget__state--not-found">
			{{ t('launchpad', 'Folder no longer exists.') }}
		</div>

		<div
			v-else-if="errorCode"
			class="files-widget__state files-widget__state--error">
			{{ t('launchpad', 'Failed to load folder contents.') }}
			<button
				type="button"
				class="files-widget__retry"
				@click="fetchContents">
				{{ t('launchpad', 'Retry') }}
			</button>
		</div>

		<div
			v-else-if="filteredItems.length === 0 && !searchQuery"
			class="files-widget__state files-widget__state--empty">
			{{ t('launchpad', 'This folder is empty.') }}
		</div>

		<div
			v-else-if="filteredItems.length === 0 && searchQuery"
			class="files-widget__state files-widget__state--empty">
			{{ noSearchResultsLabel }}
		</div>

		<ul v-else class="files-widget__list">
			<li
				v-for="item in filteredItems"
				:key="item.fileId"
				class="files-widget__row"
				:class="{ 'files-widget__row--folder': item.isFolder }">
				<button
					type="button"
					class="files-widget__row-name"
					@click="onItemClick(item)">
					<span aria-hidden="true" class="files-widget__row-icon">
						{{ item.isFolder ? '📁' : '📄' }}
					</span>
					<span class="files-widget__row-label">{{ item.name }}</span>
				</button>
				<span class="files-widget__row-modified">{{ item.modifiedAt }}</span>
				<span class="files-widget__row-size">{{ formatSize(item.size, item.isFolder) }}</span>
				<button
					v-if="canDeleteItem(item)"
					type="button"
					class="files-widget__row-delete"
					:aria-label="t('launchpad', 'Delete {name}', { name: item.name })"
					@click="confirmDelete(item)">
					{{ t('launchpad', 'Delete') }}
				</button>
			</li>
		</ul>

		<div v-if="nextCursor" class="files-widget__pagination">
			<button
				type="button"
				class="files-widget__more"
				@click="loadMore">
				{{ t('launchpad', 'Load more') }}
			</button>
		</div>

		<div
			v-if="confirmTarget"
			class="files-widget__modal-backdrop"
			role="dialog"
			aria-modal="true"
			@click.self="cancelDelete">
			<div class="files-widget__modal">
				<p>
					{{ t('launchpad', 'Are you sure you want to delete {name}?', { name: confirmTarget.name }) }}
				</p>
				<div class="files-widget__modal-actions">
					<button type="button" @click="cancelDelete">
						{{ t('launchpad', 'Cancel') }}
					</button>
					<button
						type="button"
						class="files-widget__modal-confirm"
						@click="performDelete">
						{{ t('launchpad', 'Delete') }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * FilesWidget — inline Nextcloud Files browser as a LaunchPad widget.
 *
 * Implements REQ-FLS-003 (folder listing), REQ-FLS-005 (breadcrumb +
 * folder navigation), REQ-FLS-006 (deep-link click-through to the
 * Files app), REQ-FLS-007 (upload button gated by allowUpload + write
 * permission), REQ-FLS-008 (delete button + confirm modal),
 * REQ-FLS-009 (folder-not-found / access-denied empty states), and
 * REQ-FLS-011 (in-widget client-side search).
 *
 * The widget keeps its current sub-path in component state — every
 * placement instance has its own independent navigation stack
 * (REQ-FLS-005 scenario "Navigation state isolated per widget
 * instance"). Sub-path is RESET to `/` whenever the placement prop
 * changes so re-configuring the widget root never leaves the user on
 * a stale path.
 */
export default {
	name: 'FilesWidget',

	props: {
		/** Persisted content blob: see FilesForm for shape. */
		content: {
			type: Object,
			default: () => ({}),
		},
		/** Placement entity (provides `id` for endpoint scoping). */
		placement: {
			type: Object,
			default: () => ({}),
		},
		/** Whether the dashboard shell is in admin mode. */
		isAdmin: {
			type: Boolean,
			default: false,
		},
		/** Whether the dashboard shell is in edit mode. */
		canEdit: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			items: [],
			currentSubPath: '/',
			loading: false,
			errorCode: null,
			nextCursor: null,
			searchQuery: '',
			confirmTarget: null,
		}
	},

	computed: {
		/** @spec openspec/specs/files-widget/spec.md */
		placementId() {
			return Number(this.placement?.id || 0)
		},

		/** @spec openspec/specs/files-widget/spec.md */
		allowUpload() {
			return this.content?.allowUpload === true
		},

		/** @spec openspec/specs/files-widget/spec.md */
		allowDelete() {
			return this.content?.allowDelete === true
		},

		/** @spec openspec/specs/files-widget/spec.md */
		viewerCanWrite() {
			// REQ-FLS-007: when at least one item is editable we treat
			// the viewer as having write permission on the folder. The
			// folder itself is not surfaced as a node, so we inspect
			// children.
			if (this.items.length === 0) {
				// Empty folder + allowUpload still surfaces the upload
				// button — the backend re-validates write permission
				// on the underlying folder so this is a UX convenience
				// only.
				return true
			}
			return this.items.some((item) => item.canEdit === true)
		},

		/** @spec openspec/specs/files-widget/spec.md */
		canShowUpload() {
			return this.allowUpload && this.viewerCanWrite && !this.errorCode
		},

		/** @spec openspec/specs/files-widget/spec.md */
		pathSegments() {
			if (!this.currentSubPath || this.currentSubPath === '/') {
				return []
			}
			return this.currentSubPath
				.split('/')
				.map((segment) => segment.trim())
				.filter((segment) => segment !== '')
		},

		/** @spec openspec/specs/files-widget/spec.md */
		filteredItems() {
			const query = this.searchQuery.trim().toLowerCase()
			if (query === '') {
				return this.items
			}
			return this.items.filter((item) => {
				const name = String(item.name || '').toLowerCase()
				return name.includes(query)
			})
		},

		/** @spec openspec/specs/files-widget/spec.md */
		noSearchResultsLabel() {
			return t(
				'launchpad',
				'No files matching \'{query}\'',
				{ query: this.searchQuery },
			)
		},
	},

	watch: {
		placement: {
			immediate: true,
			/** @spec openspec/specs/files-widget/spec.md */
			handler() {
				this.currentSubPath = '/'
				this.items = []
				this.nextCursor = null
				this.errorCode = null
				this.fetchContents()
			},
		},
	},

	methods: {
		/**
		 * Load the current folder's contents.
		 *
		 * @param {boolean} [append] True to append the next cursor page to
		 *   the existing list (infinite scroll); false replaces it.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		async fetchContents(append = false) {
			if (this.placementId === 0) {
				return
			}

			this.loading = !append
			this.errorCode = null

			try {
				const [{ default: axios }, { generateUrl }] = await Promise.all([
					import('@nextcloud/axios'),
					import('@nextcloud/router'),
				])

				const url = generateUrl(
					'/apps/launchpad/api/widgets/files/{placementId}/contents',
					{ placementId: this.placementId },
				)
				const params = {
					currentPath: this.currentSubPath,
					limit: 50,
				}
				if (append && this.nextCursor) {
					params.cursor = this.nextCursor
				}

				const response = await axios.get(url, { params })
				const data = response?.data || {}
				const nextItems = Array.isArray(data.items) ? data.items : []
				this.items = append ? this.items.concat(nextItems) : nextItems
				this.nextCursor = data.nextCursor || null
			} catch (err) {
				const status = err?.response?.status
				const code = err?.response?.data?.error
				if (status === 404 || code === 'folder_not_found') {
					this.errorCode = 'folder_not_found'
				} else if (status === 403 || code === 'no_access') {
					this.errorCode = 'no_access'
				} else {
					this.errorCode = 'unknown_error'
				}
				if (!append) {
					this.items = []
					this.nextCursor = null
				}
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/files-widget/spec.md */
		loadMore() {
			if (this.nextCursor) {
				this.fetchContents(true)
			}
		},

		/**
		 * Handle a row click — descend into folders, open files.
		 *
		 * @param {object} item The clicked directory entry.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		onItemClick(item) {
			if (item.isFolder) {
				const next = this.joinPath(this.currentSubPath, item.name)
				this.navigateTo(next)
			} else {
				this.openFileInFilesApp(item.fileId)
			}
		},

		/**
		 * Move to another folder, clearing the search box and paging cursor.
		 *
		 * @param {string} path Folder path to show; falsy resets to root.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		navigateTo(path) {
			this.currentSubPath = path || '/'
			this.searchQuery = ''
			this.nextCursor = null
			this.fetchContents()
		},

		/**
		 * Build the path for a breadcrumb segment.
		 *
		 * @param {number} index Zero-based index of the segment clicked.
		 * @return {string} Absolute path up to and including that segment.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		segmentPathTo(index) {
			const segments = this.pathSegments.slice(0, index + 1)
			return '/' + segments.join('/')
		},

		/**
		 * Join a folder path and an entry name into one path, tolerating
		 * stray leading/trailing slashes on either side.
		 *
		 * @param {string} base Folder path.
		 * @param {string} name Entry name to append.
		 * @return {string} The combined absolute path.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		joinPath(base, name) {
			const trimmedBase = String(base || '/').replace(/\/+$/, '')
			const trimmedName = String(name || '').replace(/^\/+/, '')
			if (trimmedBase === '' || trimmedBase === '/') {
				return '/' + trimmedName
			}
			return `${trimmedBase}/${trimmedName}`
		},

		/**
		 * Open a file in the Nextcloud Files app in a new tab.
		 *
		 * @param {number|string} fileId Nextcloud file id; falsy is ignored.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		openFileInFilesApp(fileId) {
			if (!fileId) {
				return
			}
			const url = `/apps/files/?fileid=${encodeURIComponent(fileId)}`
			window.open(url, '_blank', 'noopener,noreferrer')
		},

		/**
		 * Whether the delete affordance should show for an entry — requires
		 * both the widget setting and the server-reported permission.
		 *
		 * @param {object} item The directory entry.
		 * @return {boolean} True when the entry may be deleted.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		canDeleteItem(item) {
			return this.allowDelete === true && item.canDelete === true
		},

		/**
		 * Stage an entry for deletion, opening the confirmation prompt.
		 *
		 * @param {object} item The entry to delete.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		confirmDelete(item) {
			this.confirmTarget = item
		},

		/** @spec openspec/specs/files-widget/spec.md */
		cancelDelete() {
			this.confirmTarget = null
		},

		/** @spec openspec/specs/files-widget/spec.md */
		async performDelete() {
			const target = this.confirmTarget
			if (!target) {
				return
			}
			try {
				const [{ default: axios }, { generateUrl }] = await Promise.all([
					import('@nextcloud/axios'),
					import('@nextcloud/router'),
				])

				const url = generateUrl(
					'/apps/launchpad/api/widgets/files/{placementId}/files/{fileId}',
					{ placementId: this.placementId, fileId: target.fileId },
				)
				await axios.delete(url)
				this.items = this.items.filter((item) => item.fileId !== target.fileId)
			} catch (err) {
				// Swallow — refresh the listing so the UI stays
				// truthful even if the optimistic remove failed.
				this.fetchContents()
			} finally {
				this.confirmTarget = null
			}
		},

		/** @spec openspec/specs/files-widget/spec.md */
		triggerUpload() {
			if (this.$refs.fileInput) {
				this.$refs.fileInput.click()
			}
		},

		/**
		 * Upload the files chosen in the hidden file input.
		 *
		 * @param {Event} event The input's change event.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		async onFileInputChange(event) {
			const fileList = event?.target?.files
			if (!fileList || fileList.length === 0) {
				return
			}

			const formData = new FormData()
			for (let i = 0; i < fileList.length; i++) {
				formData.append('files[]', fileList[i])
			}

			try {
				const [{ default: axios }, { generateUrl }] = await Promise.all([
					import('@nextcloud/axios'),
					import('@nextcloud/router'),
				])

				const url = generateUrl(
					'/apps/launchpad/api/widgets/files/{placementId}/upload',
					{ placementId: this.placementId },
				)
				await axios.post(url, formData, {
					params: { currentPath: this.currentSubPath },
				})
				this.fetchContents()
			} catch (err) {
				// Surface failure by re-fetching; the user can retry.
				this.fetchContents()
			} finally {
				if (this.$refs.fileInput) {
					this.$refs.fileInput.value = ''
				}
			}
		},

		/**
		 * Render a byte count as a human-readable size.
		 *
		 * @param {number} bytes Size in bytes.
		 * @param {boolean} isFolder True for folders, which show no size.
		 * @return {string} The formatted size, or `''` for folders.
		 * @spec openspec/specs/files-widget/spec.md
		 */
		formatSize(bytes, isFolder) {
			if (isFolder) {
				return ''
			}
			const value = Number(bytes || 0)
			if (value === 0) {
				return '0 B'
			}
			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			let unitIndex = 0
			let display = value
			while (display >= 1024 && unitIndex < units.length - 1) {
				display = display / 1024
				unitIndex++
			}
			return `${display.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`
		},
	},
}
</script>

<style scoped>
.files-widget {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	overflow: hidden;
}

.files-widget__toolbar {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.files-widget__breadcrumb {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	flex: 1 1 auto;
	min-width: 0;
}

.files-widget__crumb {
	background: transparent;
	border: none;
	padding: 4px 6px;
	cursor: pointer;
	color: var(--color-primary);
	font: inherit;
}

.files-widget__crumb[aria-current='page'] {
	color: var(--color-main-text);
	cursor: default;
}

.files-widget__separator {
	color: var(--color-text-maxcontrast);
}

.files-widget__search {
	flex: 0 1 200px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.files-widget__upload {
	padding: 4px 12px;
	border: 1px solid var(--color-primary);
	background: var(--color-primary);
	color: var(--color-primary-text);
	border-radius: var(--border-radius);
	cursor: pointer;
}

.files-widget__file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	border: 0;
}

.files-widget__state {
	padding: 16px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.files-widget__retry {
	display: block;
	margin: 8px auto 0;
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	cursor: pointer;
}

.files-widget__list {
	list-style: none;
	margin: 0;
	padding: 0;
	overflow: auto;
	flex: 1 1 auto;
}

.files-widget__row {
	display: grid;
	grid-template-columns: 1fr 140px 80px auto;
	align-items: center;
	gap: 8px;
	padding: 4px 6px;
	border-bottom: 1px solid var(--color-border);
}

.files-widget__row-name {
	display: flex;
	align-items: center;
	gap: 8px;
	background: transparent;
	border: none;
	padding: 0;
	cursor: pointer;
	text-align: left;
	color: var(--color-main-text);
	font: inherit;
	min-width: 0;
}

.files-widget__row-label {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.files-widget__row-modified,
.files-widget__row-size {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.files-widget__row-delete {
	background: transparent;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 2px 8px;
	cursor: pointer;
	font-size: 12px;
	color: var(--color-error, #e9322d);
}

.files-widget__pagination {
	text-align: center;
	padding: 8px 0;
}

.files-widget__more {
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	cursor: pointer;
}

.files-widget__modal-backdrop {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10000;
}

.files-widget__modal {
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 16px;
	border-radius: var(--border-radius-large, 8px);
	max-width: 90vw;
	min-width: 280px;
}

.files-widget__modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 12px;
}

.files-widget__modal-actions button {
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	cursor: pointer;
}

.files-widget__modal-confirm {
	background: var(--color-error, #e9322d);
	color: #fff;
	border-color: var(--color-error, #e9322d);
}
</style>
