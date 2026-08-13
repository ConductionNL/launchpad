<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<li class="org-nav-row" :data-test-id="node.id">
		<div class="org-nav-row__row" :style="{ paddingLeft: indentPx }">
			<span
				class="org-nav-row__handle"
				:title="t('launchpad', 'Drag to reorder within this level')"
				:aria-label="t('launchpad', 'Drag to reorder')"
				>⋮⋮</span
			>
			<input
				v-model="localLabel"
				type="text"
				class="org-nav-row__label"
				:aria-label="t('launchpad', 'Label')"
				@input="emitPatch({ label: localLabel })" />
			<input
				v-model="localUrl"
				type="text"
				class="org-nav-row__url"
				:placeholder="t('launchpad', 'URL (leave empty for section)')"
				:aria-label="t('launchpad', 'URL')"
				@input="emitPatch({ url: localUrl || null })" />
			<CnIconBrowser
				class="org-nav-row__icon"
				:label="t('launchpad', 'Icon')"
				:value="localIcon"
				:icons="iconCatalogue"
				@input="onIconInput" />
			<label class="org-nav-row__checkbox">
				<input
					v-model="localOpenInNewTab"
					type="checkbox"
					@change="emitPatch({ openInNewTab: localOpenInNewTab })" />
				{{ t('launchpad', 'New tab') }}
			</label>
			<button
				type="button"
				class="org-nav-row__btn"
				:disabled="!canMoveUp"
				:aria-label="t('launchpad', 'Move up')"
				@click="$emit('move-up', { siblings, index })">
				↑
			</button>
			<button
				type="button"
				class="org-nav-row__btn"
				:disabled="!canMoveDown"
				:aria-label="t('launchpad', 'Move down')"
				@click="$emit('move-down', { siblings, index })">
				↓
			</button>
			<button
				type="button"
				class="org-nav-row__btn"
				:disabled="!canAddChild"
				:title="
					canAddChild
						? ''
						: t('launchpad', 'Tree depth cannot exceed 3 levels')
				"
				@click="$emit('add-child', { parent: node, kind: 'link' })">
				{{ t('launchpad', 'Add child') }}
			</button>
			<button
				type="button"
				class="org-nav-row__btn org-nav-row__btn--danger"
				@click="$emit('delete', { siblings, index })">
				{{ t('launchpad', 'Delete') }}
			</button>
		</div>
		<div class="org-nav-row__visibility" :style="{ paddingLeft: indentPx }">
			<label class="org-nav-row__checkbox">
				<input
					type="checkbox"
					:checked="localVisibility === null"
					@change="onToggleVisibilityAll($event.target.checked)" />
				{{ t('launchpad', 'Visible to everyone') }}
			</label>
			<select
				v-if="localVisibility !== null && groups.length > 0"
				v-model="localVisibility"
				multiple
				class="org-nav-row__groups"
				@change="emitPatch({ groupVisibility: localVisibility })">
				<option v-for="group in groups" :key="group.id" :value="group.id">
					{{ group.displayName || group.id }}
				</option>
			</select>
			<input
				v-else-if="localVisibility !== null"
				v-model="freeTextGroups"
				type="text"
				class="org-nav-row__groups-text"
				:aria-label="t('launchpad', 'Group visibility')"
				:placeholder="t('launchpad', 'Group ids, comma separated')"
				@input="onFreeTextGroupsInput" />
		</div>
		<!-- vuedraggable v4 (Vue 3): rows come from the `#item` scoped slot, and
		     `item-key` replaces the manual :key binding. See OrgNavigationEditor. -->
		<draggable
			v-if="hasChildren"
			:list="node.children"
			tag="ul"
			item-key="id"
			class="org-nav-row__children"
			handle=".org-nav-row__handle"
			ghost-class="org-nav-row__ghost"
			:animation="150">
			<template #item="{ element: child, index: idx }">
				<OrgNavigationEditorRow
					:node="child"
					:level="level + 1"
					:index="idx"
					:siblings="node.children"
					:max-depth="maxDepth"
					:groups="groups"
					@update="(payload) => $emit('update', payload)"
					@delete="(payload) => $emit('delete', payload)"
					@move-up="(payload) => $emit('move-up', payload)"
					@move-down="(payload) => $emit('move-down', payload)"
					@add-child="(payload) => $emit('add-child', payload)" />
			</template>
		</draggable>
	</li>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import draggable from 'vuedraggable'
import { CnIconBrowser } from '@conduction/nextcloud-vue'
import { ICON_CATALOGUE } from '../../services/iconCatalogue.js'

/**
 * OrgNavigationEditorRow — single row inside the admin tree editor.
 *
 * Supports inline editing of label / url / icon / openInNewTab and a
 * group-visibility selector. Child nodes are wrapped in a vuedraggable
 * list so they can be reordered by dragging the ⋮⋮ handle; the up/down
 * buttons remain as an accessible fallback. Each list is its own
 * Sortable with no shared group, so nodes reorder within their level
 * only (no reparenting — keeps the depth limit intact).
 *
 * Depth enforcement (REQ-ONAV-007): the "Add child" button is
 * disabled when adding would exceed `maxDepth`, with a tooltip
 * explaining why.
 */
export default {
	name: 'OrgNavigationEditorRow',

	components: {
		draggable,
		CnIconBrowser,
	},

	props: {
		node: {
			type: Object,
			required: true,
		},
		level: {
			type: Number,
			default: 1,
		},
		index: {
			type: Number,
			required: true,
		},
		siblings: {
			type: Array,
			required: true,
		},
		maxDepth: {
			type: Number,
			default: 3,
		},
		groups: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update', 'delete', 'move-up', 'move-down', 'add-child'],

	data() {
		return {
			localLabel: this.node.label || '',
			localUrl: this.node.url || '',
			localIcon: this.node.icon || '',
			localOpenInNewTab: !!this.node.openInNewTab,
			localVisibility: Array.isArray(this.node.groupVisibility)
				? [...this.node.groupVisibility]
				: null,
			freeTextGroups: Array.isArray(this.node.groupVisibility)
				? this.node.groupVisibility.join(', ')
				: '',
		}
	},

	computed: {
		/**
		 * The shared MDI icon catalogue passed to CnIconBrowser — the single
		 * picker source every admin surface reads, so the picker cannot drift
		 * from the registry (REQ-ICON-003).
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-003
		 * @return {object} the frozen icon catalogue.
		 */
		iconCatalogue() {
			return ICON_CATALOGUE
		},

		hasChildren() {
			return Array.isArray(this.node.children) && this.node.children.length > 0
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		canAddChild() {
			return this.level < this.maxDepth
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		canMoveUp() {
			return this.index > 0
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		canMoveDown() {
			return this.index < this.siblings.length - 1
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		indentPx() {
			return (this.level - 1) * 16 + 'px'
		},
	},

	methods: {
		t,

		/**
		 * Ask the editor to merge changed fields into this row's node.
		 *
		 * @param {object} patch Changed fields for the node.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		emitPatch(patch) {
			this.$emit('update', { node: this.node, patch })
		},

		/**
		 * Store the icon picked from CnIconBrowser — either a built-in SVG
		 * path or a custom URL, the two inputs REQ-ICON-008 requires the
		 * picker to switch between without losing the previous value.
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-008
		 * @param {string|null} value the chosen icon path/URL.
		 * @return {void}
		 */
		onIconInput(value) {
			this.localIcon = value || ''
			this.emitPatch({ icon: value || null })
		},

		/**
		 * Switch between "visible to everyone" and a per-group list.
		 *
		 * @param {boolean} allVisible True to clear the group restriction.
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		onToggleVisibilityAll(allVisible) {
			if (allVisible) {
				this.localVisibility = null
				this.emitPatch({ groupVisibility: null })
			} else {
				this.localVisibility = []
				this.emitPatch({ groupVisibility: [] })
			}
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		onFreeTextGroupsInput() {
			const ids = (this.freeTextGroups || '')
				.split(',')
				.map((s) => s.trim())
				.filter((s) => s.length > 0)
			this.localVisibility = ids
			this.emitPatch({ groupVisibility: ids.length > 0 ? ids : null })
		},
	},
}
</script>

<style scoped>
.org-nav-row {
	list-style: none;
	border-bottom: 1px solid var(--color-border, #eee);
	padding: 8px 0;
	/* Solid background so a row being dragged doesn't visually bleed
	   into the rows underneath it. */
	background-color: var(--color-main-background);
}

.org-nav-row__row {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}

.org-nav-row__handle {
	color: var(--color-text-maxcontrast, #888);
	cursor: grab;
	font-size: 1.2em;
	user-select: none;
}

.org-nav-row__handle:active {
	cursor: grabbing;
}

/* Placeholder shown for the dragged row while sorting. */
.org-nav-row__ghost > .org-nav-row__row {
	opacity: 0.5;
	background: var(
		--color-primary-element-light,
		var(--color-background-hover, #e3f2fd)
	);
	border-radius: 4px;
}

.org-nav-row__label {
	flex: 1;
	min-width: 120px;
}

.org-nav-row__url {
	flex: 1;
	min-width: 100px;
}

.org-nav-row__icon {
	flex: 0 0 auto;
}

.org-nav-row__btn {
	background: transparent;
	border: 1px solid var(--color-border-dark, #ccc);
	border-radius: 4px;
	padding: 4px 8px;
	cursor: pointer;
	font: inherit;
}

.org-nav-row__btn[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.org-nav-row__btn--danger {
	color: var(--color-error, #d32f2f);
}

.org-nav-row__visibility {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 4px;
	flex-wrap: wrap;
}

.org-nav-row__groups,
.org-nav-row__groups-text {
	min-width: 200px;
}

.org-nav-row__children {
	list-style: none;
	margin: 8px 0 0;
	padding: 0;
}

.org-nav-row__checkbox {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
</style>
