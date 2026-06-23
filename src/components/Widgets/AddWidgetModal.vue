<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		v-if="show"
		size="normal"
		:name="modalTitle"
		@close="onCancel">
		<div
			class="add-widget-modal"
			role="dialog"
			:aria-labelledby="titleId"
			aria-modal="true">
			<h2 :id="titleId" class="add-widget-modal__title">
				{{ modalTitle }}
			</h2>

			<!-- Type selector: shown only in pure-create mode (no preselected
			     type, no editing widget). REQ-WDG-010. -->
			<div v-if="showTypeSelect" class="add-widget-modal__type">
				<label class="add-widget-modal__type-label" :for="typeSelectId">
					{{ t('launchpad', 'Widget type') }}
				</label>
				<select
					:id="typeSelectId"
					v-model="state.type"
					class="add-widget-modal__type-select"
					data-testid="widget-type-select"
					@change="onTypeSwitch">
					<option
						v-for="type in availableTypes"
						:key="type"
						:value="type"
						:data-testid="`widget-type-option-${type}`">
						{{ typeDisplayName(type) }}
					</option>
				</select>
			</div>

			<!-- Active per-type sub-form. Driven by `<component :is>` from the
			     widget registry; sub-forms expose `validate()` and either an
			     `assembledContent` getter or `@update:content` events. -->
			<div v-if="activeSubFormComponent" class="add-widget-modal__form">
				<component
					:is="activeSubFormComponent"
					ref="activeSubForm"
					:key="state.type"
					:editing-widget="state.editingWidget"
					:value="state.content"
					:upload-fn="iconUploadFn"
					@update:content="onContentUpdate" />
			</div>
			<div v-else class="add-widget-modal__empty">
				{{ t('launchpad', 'No widget types available') }}
			</div>

			<!-- Widget appearance (chrome): title, background, icon. These are
			     the controls the standalone style editor used to own; merging
			     them here makes the add and edit modals one complete modal. -->
			<div v-if="activeSubFormComponent" class="add-widget-modal__chrome">
				<h3 class="add-widget-modal__chrome-heading">
					{{ t('launchpad', 'Appearance') }}
				</h3>
				<NcCheckboxRadioSwitch
					:checked="chrome.showTitle"
					@update:checked="chrome.showTitle = $event">
					{{ t('launchpad', 'Show title') }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					v-if="chrome.showTitle"
					:value="chrome.customTitle"
					:label="t('launchpad', 'Custom title')"
					@update:value="chrome.customTitle = $event" />

				<div class="add-widget-modal__chrome-row">
					<span class="add-widget-modal__chrome-label">{{ t('launchpad', 'Background') }}</span>
					<NcColorPicker v-model="chrome.backgroundColor">
						<NcButton type="tertiary">
							<template #icon>
								<span
									class="add-widget-modal__color-preview"
									:style="{ backgroundColor: chrome.backgroundColor || 'transparent' }" />
							</template>
							{{ chrome.backgroundColor || t('launchpad', 'Default') }}
						</NcButton>
					</NcColorPicker>
				</div>

				<div class="add-widget-modal__chrome-row">
					<span class="add-widget-modal__chrome-label">{{ t('launchpad', 'Icon') }}</span>
					<CnIconPicker
						:value="chrome.customIcon"
						:upload-fn="iconUploadFn"
						@input="chrome.customIcon = $event" />
				</div>
			</div>

			<!-- Action buttons. REQ-WDG-013 close discipline: cancel emits
			     close, never submit. Submit button is disabled while the
			     active sub-form's validate() returns errors (REQ-WDG-012). -->
			<div class="add-widget-modal__actions">
				<NcButton type="tertiary" @click="onCancel">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					type="primary"
					:disabled="!isValid"
					:title="firstError || ''"
					data-testid="add-widget-save"
					@click="onSubmit">
					{{ submitLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, CnIconPicker } from '@conduction/nextcloud-vue'
import { NcTextField, NcColorPicker, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'

import {
	listWidgetTypes,
	getWidgetTypeEntry,
} from '../../constants/widgetRegistry.js'
import { useWidgetForm } from '../../composables/useWidgetForm.js'
import { uploadDataUrl } from '../../services/resourceService.js'

let titleIdCounter = 0
let selectIdCounter = 0

/**
 * AddWidgetModal — unified host for both "add a custom widget" and "edit a
 * custom widget" flows. The modal does NO API work itself; it emits
 * `submit({type, content})` for the parent to persist. Per-type fields live
 * in sub-form components owned by their respective widget capabilities and
 * registered in `widgetRegistry.js`.
 *
 * Props:
 *  - `show` (bool): toggles visibility. Going `false → true` triggers
 *    `resetForm()` (or `loadEditingWidget()` when `editingWidget` is set).
 *  - `preselectedType` (string|null): when set, the type `<select>` is
 *    hidden and the form opens directly on this type (toolbar deep-links).
 *  - `editingWidget` (object|null): when set, the modal opens in edit mode;
 *    the type select is hidden (placement type is immutable) and the
 *    sub-form is pre-filled from `editingWidget.content`. The action button
 *    reads `t('Save')` instead of `t('Add')` and the title reads
 *    `t('Edit Widget')` instead of `t('Add Widget')`.
 *
 * Emits:
 *  - `close`: cancel button, backdrop click, or Esc key.
 *  - `submit`: `{type, content}` payload for the parent to send to the API.
 */
export default {
	name: 'AddWidgetModal',

	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcColorPicker,
		NcCheckboxRadioSwitch,
		CnIconPicker,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		preselectedType: {
			type: String,
			default: null,
		},
		editingWidget: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'submit'],

	/** @spec openspec/specs/widgets/spec.md */
	setup() {
		// One composable instance per modal mount. The composable owns the
		// type/content/editingWidget reactive state shared with sub-forms.
		const form = useWidgetForm()
		return { form }
	},

	data() {
		return {
			// Re-validation tick: the modal needs `isValid` to recompute
			// every time the active sub-form's input changes. Sub-forms
			// emit `update:content` on every keystroke; we bump this
			// counter in the handler so the computed re-runs.
			validationTick: 0,
			titleId: `add-widget-modal-title-${++titleIdCounter}`,
			typeSelectId: `add-widget-modal-type-${++selectIdCounter}`,
			// Widget chrome (title / background / icon) edited in the same
			// modal as the content; synced from the placement on open and
			// emitted back on submit. Maps to the placement fields
			// showTitle / customTitle / styleConfig.backgroundColor / customIcon.
			chrome: { showTitle: true, customTitle: '', backgroundColor: '', customIcon: '' },
		}
	},

	computed: {
		/** @spec openspec/specs/widgets/spec.md */
		state() {
			return this.form.state
		},

		/**
		 * Type keys the picker should offer. Filters out registry entries
		 * with no `form` component (i.e. types whose owning per-widget
		 * proposal hasn't shipped its sub-form yet). REQ-WDG-014.
		 *
		 * @return {string[]}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		availableTypes() {
			return listWidgetTypes()
		},

		/**
		 * Hide the type select in edit mode (placement type is immutable)
		 * or when the caller pre-selected a type.
		 *
		 * @return {boolean}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		showTypeSelect() {
			return !this.editingWidget && !this.preselectedType
		},

		/**
		 * The Vue component reference to mount via `<component :is>`.
		 * Returns `null` when the active type is unknown OR has no form
		 * registered yet (defensive — the user shouldn't be able to pick
		 * such a type via `availableTypes`, but a stale `preselectedType`
		 * could still drive us here).
		 *
		 * @return {object|null}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		activeSubFormComponent() {
			const entry = getWidgetTypeEntry(this.state.type)
			return entry?.form || null
		},

		/**
		 * Modal heading text — flips between Add/Edit based on whether
		 * an existing placement is being edited.
		 *
		 * @return {string}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		modalTitle() {
			return this.editingWidget
				? t('launchpad', 'Edit Widget')
				: t('launchpad', 'Add Widget')
		},

		/**
		 * Action button label — flips between Add/Save based on edit mode.
		 *
		 * @return {string}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		submitLabel() {
			return this.editingWidget ? t('launchpad', 'Save') : t('launchpad', 'Add')
		},

		/**
		 * Validation gate. `validationTick` keeps Vue's dependency tracker
		 * aware that this computed should re-run on every form input.
		 *
		 * @return {string[]}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		validationErrors() {
			// touch the tick so Vue tracks it as a dependency
			// eslint-disable-next-line no-unused-expressions
			this.validationTick
			return this.form.validate(this.$refs.activeSubForm)
		},

		isValid() {
			return this.validationErrors.length === 0
		},

		/** @spec openspec/specs/widgets/spec.md */
		firstError() {
			const err = this.validationErrors[0]
			// Hide the internal "no active form" sentinel from the user UI.
			return err && err !== '__no-active-form__' ? err : ''
		},
	},

	watch: {
		/** @spec openspec/specs/widgets/spec.md */
		show(isOpen) {
			if (isOpen) {
				this.openLifecycle()
			}
		},
		editingWidget: {
			immediate: false,
			/** @spec openspec/specs/widgets/spec.md */
			handler(widget) {
				if (this.show && widget) {
					this.form.loadEditingWidget(widget)
				}
			},
		},
		/** @spec openspec/specs/widgets/spec.md */
		preselectedType(type) {
			if (this.show && type && !this.editingWidget) {
				this.form.resetForm(type)
			}
		},
	},

	/** @spec openspec/specs/widgets/spec.md */
	created() {
		// Seed state synchronously before the first render so the
		// `v-if="activeSubFormComponent"` path resolves to the right
		// sub-form on initial mount (otherwise the modal flashes the
		// "No widget types available" empty state for one tick).
		if (this.show) {
			this.openLifecycle()
		}
	},

	mounted() {
		document.addEventListener('keydown', this.onKeydown)
	},

	beforeDestroy() {
		document.removeEventListener('keydown', this.onKeydown)
	},

	methods: {
		t,

		/**
		 * Initialise form state when the modal opens. Edit mode pre-fills
		 * from `editingWidget`; create mode resets to the preselected type
		 * (toolbar invocation) or to the first available registered type.
		 */
		/** @spec openspec/specs/widgets/spec.md */
		openLifecycle() {
			if (this.editingWidget) {
				this.form.loadEditingWidget(this.editingWidget)
				this.syncChromeFromPlacement(this.editingWidget)
				return
			}
			const initialType = this.preselectedType
				|| this.availableTypes[0]
				|| ''
			this.form.resetForm(initialType)
			this.syncChromeFromPlacement(null)
			this.validationTick++
		},

		/**
		 * Seed the chrome controls (title / background / icon) from the
		 * placement being edited, or to defaults for a new widget. Mirrors
		 * the fields the legacy style editor persisted.
		 *
		 * @param {object|null} placement the placement being edited, or null on add.
		 * @return {void}
		 */
		syncChromeFromPlacement(placement) {
			this.chrome = {
				// New widgets default to NO title: most custom widgets (text,
				// image, divider, header which has its own title…) don't want a
				// generic "Widget" chrome header. The user can switch it on.
				// Existing placements keep whatever they were saved with.
				showTitle: placement ? placement.showTitle !== false : false,
				customTitle: (placement && placement.customTitle) || '',
				backgroundColor: (placement && placement.styleConfig && placement.styleConfig.backgroundColor) || '',
				customIcon: (placement && placement.customIcon) || '',
			}
		},

		/**
		 * Handle a `<select>` change: swap the active sub-form and reset
		 * its state to defaults. REQ-WDG-010 — switching type discards
		 * any in-progress field input (explicit trade-off, see proposal).
		 *
		 * The sub-form is keyed on `state.type`, so changing the type
		 * tears down the old `<component :is>` and remounts a new one;
		 * `$refs.activeSubForm` is briefly null between teardown and
		 * mount. The first `validationTick++` fires while the ref is
		 * stale, so `validate()` returns `__no-active-form__` and
		 * `isValid` flips to false — the visible symptom is an Add
		 * button that stays disabled until the user touches a field.
		 * Bumping the tick again on the NEXT tick (after Vue has
		 * remounted the sub-form and rebound the ref) lets the
		 * computed re-run against the fresh sub-form's `validate()`.
		 */
		/** @spec openspec/specs/widgets/spec.md */
		onTypeSwitch() {
			this.form.resetForm(this.state.type)
			this.validationTick++
			this.$nextTick(() => {
				this.validationTick++
			})
		},

		/**
		 * Sub-forms emit `update:content` on every keystroke. We mirror
		 * the payload into the composable so `assembleContent()` can fall
		 * back to it for sub-forms without an `assembledContent` getter,
		 * AND bump the validation tick so the action button enables/
		 * disables reactively on input. REQ-WDG-012.
		 *
		 * @param {object} content the sub-form's current content payload
		 */
		/** @spec openspec/specs/widgets/spec.md */
		onContentUpdate(content) {
			this.state.content = { ...content }
			this.validationTick++
		},

		/**
		 * Cancel button / backdrop / NcModal `close` event. REQ-WDG-013 —
		 * close is non-destructive; it does not emit submit.
		 */
		/** @spec openspec/specs/widgets/spec.md */
		onCancel() {
			this.$emit('close')
		},

		/**
		 * Esc-key listener. NcModal handles its own Esc dismissal in
		 * normal usage, but we register this fallback so the modal works
		 * even when NcModal's internal handler is suppressed by a parent
		 * (e.g. inside a focus-trap). REQ-WDG-013.
		 *
		 * @param {KeyboardEvent} event the keydown event
		 */
		/** @spec openspec/specs/widgets/spec.md */
		onKeydown(event) {
			if (this.show && event.key === 'Escape') {
				this.$emit('close')
			}
		},

		/**
		 * Build the `{type, content}` payload via the composable's
		 * `assembleContent()` and emit it. The modal performs no API
		 * calls AND no GridStack operations — the parent (Views.vue)
		 * persists via the dashboard store, which routes the placement
		 * through `placeNewWidget(spec)` from `useGridManager.js`
		 * (REQ-GRID-014: single placement authority). The modal MUST
		 * NOT call the GridStack add-widget API directly. REQ-WDG-010.
		 */
		/** @spec openspec/specs/widgets/spec.md */
		onSubmit() {
			if (!this.isValid) {
				return
			}
			const payload = this.form.assembleContent(this.$refs.activeSubForm)
			// Carry the chrome (title/background/icon) alongside the content so
			// the parent can persist both from this single modal.
			payload.chrome = {
				showTitle: this.chrome.showTitle,
				customTitle: this.chrome.customTitle,
				customIcon: this.chrome.customIcon,
				backgroundColor: this.chrome.backgroundColor,
			}
			this.$emit('submit', payload)
		},

		/**
		 * Data-URL upload transport for CnIconPicker (custom icon uploads),
		 * exposed as a method so the template can reference the imported helper.
		 *
		 * @param {File} file the file to upload.
		 * @return {Promise<string>} the resulting data URL.
		 */
		iconUploadFn(file) {
			return uploadDataUrl(file)
		},

		/**
		 * Look up the human-readable name for a registry type. Falls back
		 * to the type key itself when the registry entry is missing.
		 *
		 * @param {string} type the registry type key
		 * @return {string}
		 */
		/** @spec openspec/specs/widgets/spec.md */
		typeDisplayName(type) {
			const entry = getWidgetTypeEntry(type)
			return entry?.displayName || type
		},
	},
}
</script>

<style scoped>
.add-widget-modal {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 320px;
	/* No own height cap / scroll: NcModal's .modal-container__content is the
	   single scroll container (a second one here caused a double scrollbar).
	   Sections size to content; the sub-form is flex:0 0 auto so it isn't
	   squished by the Appearance section below it. */
}

.add-widget-modal__title {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}

.add-widget-modal__type {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.add-widget-modal__type-label {
	font-size: 14px;
	font-weight: 500;
}

.add-widget-modal__type-select {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
}

.add-widget-modal__form {
	flex: 0 0 auto;
}

.add-widget-modal__empty {
	padding: 16px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.add-widget-modal__chrome {
	display: flex;
	flex-direction: column;
	gap: 12px;
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}

.add-widget-modal__chrome-heading {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.add-widget-modal__chrome-row {
	display: flex;
	align-items: center;
	gap: 12px;
}

.add-widget-modal__chrome-label {
	font-size: 14px;
	min-width: 96px;
}

/* Let the icon-grid picker fill the row width so it shows several columns
   instead of being squeezed to content width. */
.add-widget-modal__chrome-row :deep(.cn-icon-picker) {
	flex: 1;
	min-width: 0;
}

.add-widget-modal__color-preview {
	display: inline-block;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	border: 1px solid var(--color-border);
}

.add-widget-modal__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}
</style>
