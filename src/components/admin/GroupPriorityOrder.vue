<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="group-priority">
		<div class="group-priority__header">
			<h3>{{ t('launchpad', 'Group priority order') }}</h3>
			<p class="group-priority__hint">
				{{
					t(
						'launchpad',
						"Drag groups between the columns to control which Nextcloud groups LaunchPad uses, and in what order. The first active group becomes the user's primary workspace.",
					)
				}}
			</p>
		</div>

		<div class="group-priority__columns">
			<!-- ACTIVE column -->
			<div class="group-priority__column" data-column="active">
				<div class="group-priority__column-header">
					<h4>{{ t('launchpad', 'Active groups') }}</h4>
					<span class="group-priority__count">{{ active.length }}</span>
				</div>
				<NcTextField
					v-model="activeFilter"
					:label="t('launchpad', 'Filter active groups')"
					:placeholder="t('launchpad', 'Filter')"
					class="group-priority__filter" />

				<ul
					class="group-priority__list group-priority__list--active"
					data-test="group-priority-active"
					@dragover.prevent="onDragOver($event, 'active')"
					@drop.prevent="onDrop($event, 'active')">
					<li
						v-for="(id, index) in filteredActive"
						:key="`active-${id}`"
						:draggable="!loading"
						class="group-priority__item"
						:class="{ 'group-priority__item--stale': isStale(id) }"
						:data-test-id="id"
						@dragstart="onDragStart($event, id, 'active', index)"
						@dragover.prevent="onItemDragOver($event, index, 'active')"
						@drop.prevent.stop="onItemDrop($event, index, 'active')">
						<span class="group-priority__handle" aria-hidden="true"
							>⋮⋮</span
						>
						<span class="group-priority__label"
							>{{ displayName(id)
							}}<span
								v-if="isStale(id)"
								class="group-priority__stale-affix">
								{{ t('launchpad', '(removed)') }}</span
							></span
						>
						<NcButton
							type="tertiary"
							:aria-label="t('launchpad', 'Move to inactive')"
							class="group-priority__move"
							@click="moveToInactive(id)">
							→
						</NcButton>
					</li>
					<li
						v-if="filteredActive.length === 0"
						class="group-priority__empty">
						{{
							activeFilter
								? t('launchpad', 'No matches.')
								: t(
										'launchpad',
										'No active groups. Drag groups here from the inactive column.',
									)
						}}
					</li>
				</ul>
			</div>

			<!-- INACTIVE column -->
			<div class="group-priority__column" data-column="inactive">
				<div class="group-priority__column-header">
					<h4>{{ t('launchpad', 'Inactive groups') }}</h4>
					<span class="group-priority__count">{{ inactive.length }}</span>
				</div>
				<NcTextField
					v-model="inactiveFilter"
					:label="t('launchpad', 'Filter inactive groups')"
					:placeholder="t('launchpad', 'Filter')"
					class="group-priority__filter" />

				<ul
					class="group-priority__list group-priority__list--inactive"
					data-test="group-priority-inactive"
					@dragover.prevent="onDragOver($event, 'inactive')"
					@drop.prevent="onDrop($event, 'inactive')">
					<li
						v-for="id in filteredInactive"
						:key="`inactive-${id}`"
						:draggable="!loading"
						class="group-priority__item"
						:data-test-id="id"
						@dragstart="onDragStart($event, id, 'inactive', null)">
						<span class="group-priority__handle" aria-hidden="true"
							>⋮⋮</span
						>
						<span class="group-priority__label">{{
							displayName(id)
						}}</span>
						<NcButton
							type="tertiary"
							:aria-label="t('launchpad', 'Move to active')"
							class="group-priority__move"
							@click="moveToActive(id)">
							←
						</NcButton>
					</li>
					<li
						v-if="filteredInactive.length === 0"
						class="group-priority__empty">
						{{
							inactiveFilter
								? t('launchpad', 'No matches.')
								: t('launchpad', 'No inactive groups.')
						}}
					</li>
				</ul>
			</div>
		</div>

		<p v-if="loading" class="group-priority__status">
			{{ t('launchpad', 'Loading group list…') }}
		</p>
		<p v-else-if="saving" class="group-priority__status">
			{{ t('launchpad', 'Saving…') }}
		</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { api } from '../../services/api.js'

/**
 * Two-list drag-and-drop component for the admin group priority order
 * (REQ-ASET-012, REQ-ASET-013, REQ-ASET-014).
 *
 * Auto-saves on every drag with a 300ms debounce so admins don't have
 * to click a Save button. Native HTML5 drag-and-drop is used to keep
 * the dependency footprint flat (no new third-party libs).
 */
export default {
	name: 'GroupPriorityOrder',

	components: {
		NcButton,
		NcTextField,
	},

	props: {
		// Optional initial seed for the active list (admin initial-state).
		// `loadGroups()` always overwrites with the API-truth at mount.
		initialActive: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			active: [...this.initialActive],
			inactive: [],
			allKnown: [],
			activeFilter: '',
			inactiveFilter: '',
			loading: true,
			saving: false,
			saveTimer: null,
			// Tracks the in-flight drag so item drops can know origin.
			dragState: null,
		}
	},

	computed: {
		// Map id → displayName for fast O(1) label lookups.
		/** @spec openspec/specs/admin-roles/spec.md */
		displayNameMap() {
			const map = {}
			for (const row of this.allKnown) {
				map[row.id] = row.displayName
			}
			return map
		},

		// Set of every known group id; anything in `active` not in here
		// renders as a stale "(removed)" entry per REQ-ASET-013.
		/** @spec openspec/specs/admin-roles/spec.md */
		knownIdSet() {
			return new Set(this.allKnown.map((row) => row.id))
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		filteredActive() {
			return this.applyFilter(this.active, this.activeFilter)
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		filteredInactive() {
			return this.applyFilter(this.inactive, this.inactiveFilter)
		},
	},

	async created() {
		await this.loadGroups()
	},

	/** @spec openspec/specs/admin-roles/spec.md */
	beforeUnmount() {
		if (this.saveTimer) {
			clearTimeout(this.saveTimer)
		}
	},

	methods: {
		/** @spec openspec/specs/admin-roles/spec.md */
		async loadGroups() {
			this.loading = true
			try {
				const res = await api.getAdminGroups()
				const data = res?.data || {}
				this.active = Array.isArray(data.active) ? data.active : []
				this.inactive = Array.isArray(data.inactive) ? data.inactive : []
				this.allKnown = Array.isArray(data.allKnown) ? data.allKnown : []
			} catch (error) {
				console.error('Failed to load admin groups:', error)
				showError(this.t('launchpad', 'Failed to load group list.'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Narrow a list of group ids to those whose id or display name
		 * contains the filter text (case-insensitive).
		 *
		 * @param {string[]} list Group ids to filter.
		 * @param {string} filter Raw filter text from the search box.
		 * @return {string[]} The matching group ids; the full list when the
		 *   filter is blank.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		applyFilter(list, filter) {
			const f = (filter || '').trim().toLowerCase()
			if (f === '') return list
			return list.filter((id) => {
				const name = (this.displayNameMap[id] || id).toLowerCase()
				return name.includes(f) || id.toLowerCase().includes(f)
			})
		},

		/**
		 * Human-readable name for a group id, falling back to the raw id
		 * when the group is no longer known to Nextcloud.
		 *
		 * @param {string} id Nextcloud group id.
		 * @return {string} Display name, or the id itself.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		displayName(id) {
			return this.displayNameMap[id] || id
		},

		/**
		 * Whether a configured group id no longer exists in Nextcloud — such
		 * rows render with a "(removed)" affix (REQ-ASET-013).
		 *
		 * @param {string} id Nextcloud group id.
		 * @return {boolean} True when the id is stale.
		 */
		isStale(id) {
			return this.knownIdSet.has(id) === false
		},

		// --- Drag-and-drop handlers (native HTML5) ---

		/**
		 * Begin a drag, recording which row left which column.
		 *
		 * @param {DragEvent} event The dragstart event.
		 * @param {string} id Group id being dragged.
		 * @param {string} column Column the drag started in.
		 * @param {number} index Row index within that column.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		onDragStart(event, id, column, index) {
			this.dragState = { id, fromColumn: column, fromIndex: index }
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move'
				event.dataTransfer.setData('text/plain', id)
			}
		},

		/**
		 * Mark a column as a valid drop target while dragging over it.
		 *
		 * @param {DragEvent} event The dragover event.
		 * @param {string} _column Column under the cursor; unused — every
		 *   column accepts a drop.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		onDragOver(event, _column) {
			if (event.dataTransfer) {
				event.dataTransfer.dropEffect = 'move'
			}
		},

		/**
		 * Mark an individual row as a valid drop target.
		 *
		 * @param {DragEvent} event The dragover event.
		 * @param {number} _index Row index under the cursor; unused — the
		 *   insertion point is computed on drop.
		 * @param {string} _column Column under the cursor; unused for the
		 *   same reason.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		onItemDragOver(event, _index, _column) {
			if (event.dataTransfer) {
				event.dataTransfer.dropEffect = 'move'
			}
		},

		/**
		 * Handle a drop on empty space inside a column — appends the dragged
		 * group at the end of that column.
		 *
		 * @param {DragEvent} event The drop event.
		 * @param {string} toColumn Column the group was dropped into.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		onDrop(event, toColumn) {
			if (!this.dragState) return
			const { id, fromColumn } = this.dragState
			this.dragState = null
			if (fromColumn === toColumn && toColumn === 'inactive') {
				// Inactive is server-sorted; intra-list reorder is a no-op.
				return
			}
			this.moveBetweenColumns(id, fromColumn, toColumn, null)
		},

		/**
		 * Handle a drop directly onto another row — inserts the dragged
		 * group before that row.
		 *
		 * @param {DragEvent} event The drop event.
		 * @param {number} targetIndex Index of the row dropped onto.
		 * @param {string} toColumn Column the group was dropped into.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		onItemDrop(event, targetIndex, toColumn) {
			if (!this.dragState) return
			const { id, fromColumn, fromIndex } = this.dragState
			this.dragState = null

			if (fromColumn === toColumn && toColumn === 'active') {
				// Reorder within active.
				if (fromIndex === targetIndex) return
				const next = [...this.active]
				next.splice(fromIndex, 1)
				const adjustedTarget =
					fromIndex < targetIndex ? targetIndex - 1 : targetIndex
				next.splice(adjustedTarget, 0, id)
				this.active = next
				this.queueSave()
				return
			}

			this.moveBetweenColumns(id, fromColumn, toColumn, targetIndex)
		},

		/**
		 * Move a group from one column to the other, then queue a save.
		 * A no-op when both columns are the same.
		 *
		 * @param {string} id Group id to move.
		 * @param {string} fromColumn Column the group is leaving.
		 * @param {string} toColumn Column the group is joining.
		 * @param {number|null} insertIndex Position within the target column;
		 *   null (or out of range) appends at the end.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		moveBetweenColumns(id, fromColumn, toColumn, insertIndex) {
			if (fromColumn === toColumn) return
			if (fromColumn === 'active') {
				this.active = this.active.filter((x) => x !== id)
			} else {
				this.inactive = this.inactive.filter((x) => x !== id)
			}

			if (toColumn === 'active') {
				const next = [...this.active]
				if (
					insertIndex === null
					|| insertIndex < 0
					|| insertIndex >= next.length
				) {
					next.push(id)
				} else {
					next.splice(insertIndex, 0, id)
				}
				this.active = next
			} else {
				// Inactive list keeps server-side sort order; resort by name.
				const next = [...this.inactive, id]
				next.sort((a, b) => {
					const an = (this.displayNameMap[a] || a).toLowerCase()
					const bn = (this.displayNameMap[b] || b).toLowerCase()
					return an.localeCompare(bn)
				})
				this.inactive = next
			}
			this.queueSave()
		},

		/**
		 * Click-to-move shortcut promoting a group to the active column.
		 * Provided because drag-and-drop is not screen-reader operable.
		 *
		 * @param {string} id Group id to activate.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		moveToActive(id) {
			this.moveBetweenColumns(id, 'inactive', 'active', null)
		},

		/**
		 * Click-to-move shortcut demoting a group to the inactive column.
		 *
		 * @param {string} id Group id to deactivate.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		moveToInactive(id) {
			this.moveBetweenColumns(id, 'active', 'inactive', null)
		},

		// Debounced auto-save (300ms) — REQ-ASET-012 / tasks.md 3.3.
		/** @spec openspec/specs/admin-roles/spec.md */
		queueSave() {
			if (this.saveTimer) {
				clearTimeout(this.saveTimer)
			}
			this.saveTimer = setTimeout(() => {
				this.saveTimer = null
				this.persist()
			}, 300)
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		async persist() {
			this.saving = true
			try {
				await api.updateAdminGroupOrder(this.active)
				showSuccess(this.t('launchpad', 'Group order saved.'))
			} catch (error) {
				console.error('Failed to save group order:', error)
				showError(this.t('launchpad', 'Failed to save group order.'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.group-priority {
	margin-bottom: 32px;
}

.group-priority__header h3 {
	margin: 0 0 8px;
}

.group-priority__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.group-priority__columns {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.group-priority__column {
	background: var(--color-background-hover);
	border-radius: var(--border-radius-large, 8px);
	padding: 12px;
	min-height: 240px;
	display: flex;
	flex-direction: column;
}

.group-priority__column-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
}

.group-priority__column-header h4 {
	margin: 0;
}

.group-priority__count {
	background: var(--color-primary-element-light, var(--color-background-dark));
	color: var(--color-primary-text, var(--color-main-text));
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	font-size: 12px;
}

.group-priority__filter {
	margin-bottom: 8px;
}

.group-priority__list {
	flex: 1;
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-height: 60px;
}

.group-priority__item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: var(--color-main-background);
	border-radius: var(--border-radius);
	cursor: grab;
	user-select: none;
}

.group-priority__item:active {
	cursor: grabbing;
}

.group-priority__item--stale {
	opacity: 0.7;
	border-left: 3px solid var(--color-warning, #c93);
}

.group-priority__handle {
	color: var(--color-text-maxcontrast);
	font-weight: bold;
}

.group-priority__label {
	flex: 1;
}

.group-priority__stale-affix {
	color: var(--color-warning, #c93);
	font-style: italic;
	font-size: 12px;
	margin-left: 4px;
}

.group-priority__move {
	min-width: 32px;
}

.group-priority__empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 12px;
	text-align: center;
}

.group-priority__status {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	margin-top: 8px;
}
</style>
