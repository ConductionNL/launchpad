<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		v-if="open"
		size="small"
		:name="t('launchpad', 'Move widget')"
		@close="onCancel">
		<div
			ref="panel"
			class="widget-move-panel"
			data-test="widget-move-panel"
			tabindex="0"
			role="group"
			:aria-label="t('launchpad', 'Move and resize widget with the keyboard')"
			@keydown="onKeydown">
			<h2 class="widget-move-panel__title">
				{{ t('launchpad', 'Move widget') }}
			</h2>
			<p class="widget-move-panel__hint">
				{{ t('launchpad', 'Use the arrow keys to move one cell. Hold Shift and use the arrow keys to resize. Press Enter to confirm or Escape to cancel.') }}
			</p>

			<!-- Live readout of the pending position/size for screen readers. -->
			<p
				class="widget-move-panel__readout"
				data-test="widget-move-readout"
				aria-live="polite">
				{{ readoutText }}
			</p>

			<div class="widget-move-panel__controls">
				<div class="widget-move-panel__group" role="group" :aria-label="t('launchpad', 'Position')">
					<span class="widget-move-panel__group-label">{{ t('launchpad', 'Position') }}</span>
					<div class="widget-move-panel__buttons">
						<NcButton
							data-test="move-up"
							:aria-label="t('launchpad', 'Move up')"
							@click="apply('move-up')">
							{{ t('launchpad', 'Up') }}
						</NcButton>
						<NcButton
							data-test="move-down"
							:aria-label="t('launchpad', 'Move down')"
							@click="apply('move-down')">
							{{ t('launchpad', 'Down') }}
						</NcButton>
						<NcButton
							data-test="move-left"
							:aria-label="t('launchpad', 'Move left')"
							@click="apply('move-left')">
							{{ t('launchpad', 'Left') }}
						</NcButton>
						<NcButton
							data-test="move-right"
							:aria-label="t('launchpad', 'Move right')"
							@click="apply('move-right')">
							{{ t('launchpad', 'Right') }}
						</NcButton>
					</div>
				</div>

				<div class="widget-move-panel__group" role="group" :aria-label="t('launchpad', 'Size')">
					<span class="widget-move-panel__group-label">{{ t('launchpad', 'Size') }}</span>
					<div class="widget-move-panel__buttons">
						<NcButton
							data-test="grow-width"
							:aria-label="t('launchpad', 'Increase width')"
							@click="apply('grow-width')">
							{{ t('launchpad', 'Wider') }}
						</NcButton>
						<NcButton
							data-test="shrink-width"
							:aria-label="t('launchpad', 'Decrease width')"
							@click="apply('shrink-width')">
							{{ t('launchpad', 'Narrower') }}
						</NcButton>
						<NcButton
							data-test="grow-height"
							:aria-label="t('launchpad', 'Increase height')"
							@click="apply('grow-height')">
							{{ t('launchpad', 'Taller') }}
						</NcButton>
						<NcButton
							data-test="shrink-height"
							:aria-label="t('launchpad', 'Decrease height')"
							@click="apply('shrink-height')">
							{{ t('launchpad', 'Shorter') }}
						</NcButton>
					</div>
				</div>
			</div>

			<div class="widget-move-panel__actions">
				<NcButton
					data-test="move-cancel"
					@click="onCancel">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					type="primary"
					data-test="move-save"
					@click="onSave">
					{{ t('launchpad', 'Confirm') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { nudgePlacement } from '../../composables/useGridManager.js'

/**
 * WidgetMovePanel — keyboard-operable move/resize panel for a dashboard
 * grid item. Provides the WCAG 2.1 SC 2.1.1 keyboard-equivalent to
 * pointer-only GridStack drag (grid-layout capability).
 *
 * The panel edits a local working copy of the placement rectangle
 * (`gridX/gridY/gridWidth/gridHeight`) so nothing is persisted until the
 * user confirms:
 *  - `ArrowUp/Down/Left/Right` nudge position by one cell.
 *  - `Shift+Arrow` grows/shrinks width/height by one cell (respecting the
 *    `MIN_CELLS` floor).
 *  - `Enter` confirms (emits `save` with the new rect).
 *  - `Escape` cancels (emits `close`, no `save`).
 *
 * All geometry runs through the pure `nudgePlacement()` helper so keyboard
 * moves share the drag path's collision model.
 *
 * Props:
 *  - `open` (boolean): visibility toggle owned by the parent.
 *  - `placement` (object): the placement to move (LaunchPad field-name form).
 *  - `allPlacements` (array): every placement on the dashboard (for collision).
 *  - `gridColumns` (number): the dashboard column count.
 *
 * Emits:
 *  - `save` (rect): confirmed `{gridX, gridY, gridWidth, gridHeight, pushed}`.
 *  - `close`: cancelled or dismissed — no persistence.
 */
export default {
	name: 'WidgetMovePanel',

	components: {
		NcModal,
		NcButton,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		placement: {
			type: Object,
			default: null,
		},
		allPlacements: {
			type: Array,
			default: () => [],
		},
		gridColumns: {
			type: Number,
			default: 12,
		},
	},

	emits: ['save', 'close'],

	data() {
		return {
			// Working copy of the placement rectangle + accumulated pushes.
			working: this.initialRect(),
		}
	},

	computed: {
		/**
		 * Screen-reader / visible readout of the pending rectangle.
		 *
		 * @return {string}
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		readoutText() {
			return t(
				'launchpad',
				'Column {x}, row {y}, {w} wide by {h} tall',
				{
					x: this.working.gridX + 1,
					y: this.working.gridY + 1,
					w: this.working.gridWidth,
					h: this.working.gridHeight,
				},
			)
		},
	},

	watch: {
		/**
		 * Reset the working rectangle and move focus into the panel each
		 * time it opens, so arrow keys act immediately (SC 2.1.1).
		 *
		 * @param {boolean} isOpen Whether the panel is now open.
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		open(isOpen) {
			if (isOpen) {
				this.working = this.initialRect()
				this.$nextTick(() => {
					if (this.$refs.panel && typeof this.$refs.panel.focus === 'function') {
						this.$refs.panel.focus()
					}
				})
			}
		},

		/** @spec openspec/specs/grid-layout/spec.md */
		placement() {
			this.working = this.initialRect()
		},
	},

	methods: {
		t,

		/**
		 * Snapshot the incoming placement into a working rectangle.
		 *
		 * @return {{gridX: number, gridY: number, gridWidth: number, gridHeight: number, pushed: Array}}
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		initialRect() {
			const p = this.placement || {}
			return {
				gridX: Number.isFinite(p.gridX) ? p.gridX : 0,
				gridY: Number.isFinite(p.gridY) ? p.gridY : 0,
				gridWidth: Number.isFinite(p.gridWidth) ? p.gridWidth : 2,
				gridHeight: Number.isFinite(p.gridHeight) ? p.gridHeight : 2,
				pushed: [],
			}
		},

		/**
		 * Apply a single move/resize action to the working rectangle via the
		 * shared collision-aware helper.
		 *
		 * @param {string} action one of the `nudgePlacement` action strings
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		apply(action) {
			const base = {
				id: this.placement ? this.placement.id : undefined,
				gridX: this.working.gridX,
				gridY: this.working.gridY,
				gridWidth: this.working.gridWidth,
				gridHeight: this.working.gridHeight,
			}
			this.working = nudgePlacement(base, action, this.allPlacements, {
				gridColumns: this.gridColumns,
			})
		},

		/**
		 * Map a keydown event to a move/resize action or confirm/cancel.
		 *
		 * @param {KeyboardEvent} event the keydown event
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		onKeydown(event) {
			const key = event.key
			const shift = event.shiftKey

			const moveMap = {
				ArrowUp: 'move-up',
				ArrowDown: 'move-down',
				ArrowLeft: 'move-left',
				ArrowRight: 'move-right',
			}
			const resizeMap = {
				ArrowUp: 'shrink-height',
				ArrowDown: 'grow-height',
				ArrowLeft: 'shrink-width',
				ArrowRight: 'grow-width',
			}

			if (key === 'Enter') {
				event.preventDefault()
				this.onSave()
				return
			}
			if (key === 'Escape') {
				event.preventDefault()
				this.onCancel()
				return
			}

			const action = shift ? resizeMap[key] : moveMap[key]
			if (action) {
				event.preventDefault()
				this.apply(action)
			}
		},

		/** @spec openspec/specs/grid-layout/spec.md */
		onSave() {
			this.$emit('save', { ...this.working })
			this.$emit('close')
		},

		/** @spec openspec/specs/grid-layout/spec.md */
		onCancel() {
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.widget-move-panel {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	outline: none;
}

.widget-move-panel:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	border-radius: var(--border-radius-large);
}

.widget-move-panel__title {
	margin: 0;
	font-size: 1.2em;
	font-weight: 600;
}

.widget-move-panel__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.widget-move-panel__readout {
	margin: 0;
	font-weight: 600;
	color: var(--color-main-text);
}

.widget-move-panel__controls {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
}

.widget-move-panel__group {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.widget-move-panel__group-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.widget-move-panel__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.widget-move-panel__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 8px;
}
</style>
