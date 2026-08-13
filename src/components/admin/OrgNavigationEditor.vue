<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="org-nav-editor">
		<div class="org-nav-editor__header">
			<h3>{{ t('launchpad', 'Organization navigation') }}</h3>
			<p class="org-nav-editor__hint">
				{{
					t(
						'launchpad',
						'Build an organisation-wide navigation tree shown next to the dashboard surface. Drag nodes to reorder, click Edit to change properties, and use the group visibility selector to restrict sections to specific Nextcloud groups.',
					)
				}}
			</p>
		</div>

		<div class="org-nav-editor__controls">
			<label class="org-nav-editor__field">
				<span>{{ t('launchpad', 'Language') }}</span>
				<select v-model="selectedLanguage" @change="onLanguageChange">
					<option value="nl">{{ t('launchpad', 'Dutch') }}</option>
					<option value="en">{{ t('launchpad', 'English') }}</option>
				</select>
			</label>
			<label class="org-nav-editor__field">
				<span>{{ t('launchpad', 'Position') }}</span>
				<select v-model="selectedPosition" @change="onPositionChange">
					<option value="hidden">{{ t('launchpad', 'Hidden') }}</option>
					<option value="left">{{ t('launchpad', 'Left') }}</option>
					<option value="right">{{ t('launchpad', 'Right') }}</option>
					<option value="top">{{ t('launchpad', 'Top') }}</option>
				</select>
			</label>
		</div>

		<p
			v-if="errorMessage"
			class="org-nav-editor__error"
			role="alert"
			data-test="org-nav-editor-error">
			{{ errorMessage }}
		</p>

		<div class="org-nav-editor__toolbar">
			<button
				type="button"
				class="org-nav-editor__add"
				data-test="org-nav-add-section"
				@click="addRoot('section')">
				{{ t('launchpad', 'Add section') }}
			</button>
			<button
				type="button"
				class="org-nav-editor__add"
				data-test="org-nav-add-link"
				@click="addRoot('link')">
				{{ t('launchpad', 'Add link') }}
			</button>
			<button
				type="button"
				class="org-nav-editor__save"
				:disabled="saving"
				data-test="org-nav-save"
				@click="save">
				{{ saving ? t('launchpad', 'Saving…') : t('launchpad', 'Save') }}
			</button>
		</div>

		<!-- vuedraggable v4 (Vue 3) requires rows to come from the `#item`
		     scoped slot; a v-for in the default slot throws "draggable element
		     must have an item slot" at render. `item-key` replaces the manual
		     :key binding. -->
		<draggable
			:list="workingTree"
			tag="ul"
			item-key="id"
			class="org-nav-editor__tree"
			handle=".org-nav-row__handle"
			ghost-class="org-nav-row__ghost"
			:animation="150">
			<template #item="{ element: node, index: idx }">
				<OrgNavigationEditorRow
					:node="node"
					:level="1"
					:index="idx"
					:siblings="workingTree"
					:max-depth="maxDepth"
					:groups="groups"
					@update="onUpdate"
					@delete="onDelete"
					@move-up="moveUp"
					@move-down="moveDown"
					@add-child="addChild" />
			</template>
		</draggable>

		<p v-if="workingTree.length === 0" class="org-nav-editor__empty">
			{{
				t(
					'launchpad',
					'No navigation configured. Add a section or link to start building the tree.',
				)
			}}
		</p>

		<p v-if="successFlag" class="org-nav-editor__success" role="status">
			{{ t('launchpad', 'Navigation saved successfully.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import draggable from 'vuedraggable'

import OrgNavigationEditorRow from './OrgNavigationEditorRow.vue'
import {
	useOrgNavigationStore,
	ORG_NAV_POSITIONS,
} from '../../stores/orgNavigation.js'

/**
 * OrgNavigationEditor — admin-only editor for the org-wide navigation
 * tree (REQ-ONAV-007).
 *
 * Drives a wholesale-replace workflow:
 *   - on mount, fetch the current tree + position from the store
 *   - keep a deep clone in `workingTree` so unsaved edits never mutate
 *     the canonical store state
 *   - on Save, validate basic shape locally and POST to the store
 *
 * Reordering supports two equivalent paths (REQ-ONAV-007):
 * drag-and-drop via each row's ⋮⋮ handle (vuedraggable / SortableJS,
 * one Sortable per sibling list — see the draggable wrappers here and
 * in OrgNavigationEditorRow) and explicit "move up" / "move down"
 * buttons as an accessible fallback. Both reorder within a sibling
 * list only (no reparenting) and mutate workingTree in place via
 * vuedraggable's `:list`, leaving the persistence path untouched.
 */
export default {
	name: 'OrgNavigationEditor',

	components: {
		OrgNavigationEditorRow,
		draggable,
	},

	props: {
		/**
		 * Optional initial group list — the admin selector inside each
		 * row uses it to populate the multi-select. When omitted the
		 * editor still functions, but the visibility selector falls
		 * back to a free-text input.
		 */
		groups: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			workingTree: [],
			selectedLanguage: 'nl',
			selectedPosition: 'hidden',
			saving: false,
			successFlag: false,
			maxDepth: 3,
		}
	},

	computed: {
		/** @spec openspec/specs/navigation-editor-org/spec.md */
		store() {
			return useOrgNavigationStore()
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		errorMessage() {
			return this.store.error
		},
	},

	mounted() {
		this.bootstrap()
	},

	methods: {
		t,

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		async bootstrap() {
			await this.store.fetchPosition()
			this.selectedPosition = this.store.position
			await this.store.fetchTree(this.selectedLanguage)
			this.workingTree = this.cloneTree(this.store.visibleTree)
		},

		/**
		 * Deep-copy the store's tree so edits stay local until saved.
		 *
		 * @param {Array<object>} tree Navigation nodes from the store.
		 * @return {Array<object>} An independent copy; `[]` for non-arrays.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		cloneTree(tree) {
			return JSON.parse(JSON.stringify(Array.isArray(tree) ? tree : []))
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		generateUuid() {
			// RFC 4122 v4 — uses crypto.getRandomValues when available
			// so the UUIDs pass the backend validator's regex.
			if (
				typeof globalThis !== 'undefined'
				&& globalThis.crypto
				&& typeof globalThis.crypto.randomUUID === 'function'
			) {
				return globalThis.crypto.randomUUID()
			}
			// Fallback — Math.random based (acceptable for admin UI).
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
				const r = (Math.random() * 16) | 0
				const v = c === 'x' ? r : (r & 0x3) | 0x8
				return v.toString(16)
			})
		},

		/**
		 * Build a blank navigation node of the requested kind.
		 *
		 * @param {string} kind `'section'` for a heading, anything else for
		 *   a link (which gets a default `/` url).
		 * @return {object} The new node, with a fresh id and no children.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		makeNode(kind) {
			return {
				id: this.generateUuid(),
				label:
					kind === 'section'
						? t('launchpad', 'New section')
						: t('launchpad', 'New link'),
				icon: null,
				url: kind === 'section' ? null : '/',
				openInNewTab: false,
				groupVisibility: null,
				children: [],
			}
		},

		/**
		 * Append a new node at the top level of the tree.
		 *
		 * @param {string} kind Node kind to create — see `makeNode`.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		addRoot(kind) {
			this.workingTree.push(this.makeNode(kind))
		},

		/**
		 * Append a new node beneath an existing one.
		 *
		 * @param {object} payload Emitted by the row component.
		 * @param {object} payload.parent Node to nest the new child under.
		 * @param {string} payload.kind Node kind to create — see `makeNode`.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		addChild({ parent, kind }) {
			if (!parent.children) {
				parent.children = []
			}
			parent.children.push(this.makeNode(kind))
		},

		/**
		 * Apply a row's field edit to its node in the working tree.
		 *
		 * @param {object} payload Emitted by the row component.
		 * @param {object} payload.node Node being edited.
		 * @param {object} payload.patch Changed fields to merge in.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		onUpdate({ node, patch }) {
			Object.assign(node, patch)
		},

		/**
		 * Remove a node (and its subtree) from the working tree.
		 *
		 * @param {object} payload Emitted by the row component.
		 * @param {Array<object>} payload.siblings Array the node lives in.
		 * @param {number} payload.index Position of the node to remove.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		onDelete({ siblings, index }) {
			siblings.splice(index, 1)
		},

		/**
		 * Swap a node with the sibling above it. No-op at the top.
		 *
		 * @param {object} payload Emitted by the row component.
		 * @param {Array<object>} payload.siblings Array the node lives in.
		 * @param {number} payload.index Position of the node to move.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		moveUp({ siblings, index }) {
			if (index <= 0) {
				return
			}
			const item = siblings[index]
			siblings.splice(index, 1)
			siblings.splice(index - 1, 0, item)
		},

		/**
		 * Swap a node with the sibling below it. No-op at the bottom.
		 *
		 * @param {object} payload Emitted by the row component.
		 * @param {Array<object>} payload.siblings Array the node lives in.
		 * @param {number} payload.index Position of the node to move.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		moveDown({ siblings, index }) {
			if (index >= siblings.length - 1) {
				return
			}
			const item = siblings[index]
			siblings.splice(index, 1)
			siblings.splice(index + 1, 0, item)
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		async onLanguageChange() {
			await this.store.fetchTree(this.selectedLanguage)
			this.workingTree = this.cloneTree(this.store.visibleTree)
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		async onPositionChange() {
			if (!ORG_NAV_POSITIONS.includes(this.selectedPosition)) {
				return
			}
			await this.store.updatePosition(this.selectedPosition)
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		async save() {
			this.saving = true
			this.successFlag = false
			const ok = await this.store.updateTree(
				this.workingTree,
				this.selectedLanguage,
			)
			this.saving = false
			if (ok) {
				this.successFlag = true
				// Hide the toast after a short delay.
				setTimeout(() => {
					this.successFlag = false
				}, 3000)
			}
		},
	},
}
</script>

<style scoped>
.org-nav-editor {
	padding: 12px 0;
}

.org-nav-editor__hint {
	color: var(--color-text-maxcontrast, #555);
	margin: 4px 0 12px;
}

.org-nav-editor__controls {
	display: flex;
	gap: 16px;
	margin-bottom: 12px;
	flex-wrap: wrap;
}

.org-nav-editor__field {
	display: inline-flex;
	flex-direction: column;
	gap: 4px;
}

.org-nav-editor__error {
	background: var(--color-error, #d32f2f);
	color: #fff;
	padding: 8px 12px;
	border-radius: 4px;
	margin: 8px 0;
}

.org-nav-editor__success {
	background: var(--color-success, #2e7d32);
	color: #fff;
	padding: 8px 12px;
	border-radius: 4px;
	margin: 8px 0;
}

.org-nav-editor__toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 12px;
	flex-wrap: wrap;
}

.org-nav-editor__add,
.org-nav-editor__save {
	background: var(--color-primary-element, #1976d2);
	color: var(--color-primary-element-text, #fff);
	border: none;
	border-radius: 4px;
	padding: 6px 12px;
	cursor: pointer;
	font: inherit;
}

.org-nav-editor__save[disabled] {
	opacity: 0.6;
	cursor: not-allowed;
}

.org-nav-editor__tree {
	list-style: none;
	margin: 0;
	padding: 0;
}

.org-nav-editor__empty {
	color: var(--color-text-maxcontrast, #555);
	font-style: italic;
}
</style>
