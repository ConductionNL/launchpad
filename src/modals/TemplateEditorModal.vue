<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	TemplateEditorModal — admin create/edit form for a dashboard template
	(admin-templates spec).

	Extracted from `TemplatesPage.vue`, where the same markup was written
	inline. The modal owns the whole editing transaction: it seeds its own
	working copy from the `template` prop each time it opens, validates and
	persists it, and reports back with `saved` so the list can reload. The
	parent keeps nothing but the open/close state, so the form can never be
	left half-edited in the page's own data.
-->

<template>
	<NcModal
		v-if="open"
		:name="
			isEdit
				? t('launchpad', 'Edit template')
				: t('launchpad', 'Create template')
		"
		size="large"
		@close="$emit('close')">
		<div class="launchpad-admin__modal" data-testid="admin-template-editor">
			<h2>
				{{
					isEdit
						? t('launchpad', 'Edit template')
						: t('launchpad', 'Create template')
				}}
			</h2>

			<!--
				The visible text moves from a bare `<label>` onto
				NcTextField's own `label` prop.

				A `<label>` with no `for` (and not wrapping the field) is
				not associated with anything — it renders as text, and a
				screen reader announces the field with no name at all.
				NcTextField renders the label against the `<input>` it
				owns, which is the association the markup was reaching
				for. The placeholder stays as the example value it always
				was; it is not a substitute for a name, because it
				disappears as soon as the field has content.
			-->
			<div class="launchpad-admin__field">
				<NcTextField
					v-model="form.name"
					:label="t('launchpad', 'Template name')"
					:placeholder="t('launchpad', 'My template')" />
			</div>

			<div class="launchpad-admin__field">
				<NcTextField
					v-model="form.description"
					:label="t('launchpad', 'Description')"
					:placeholder="t('launchpad', 'Optional description')" />
			</div>

			<div class="launchpad-admin__field">
				<label>{{ t('launchpad', 'Target groups') }}</label>
				<NcSelectTags
					v-model="form.targetGroups"
					:options="availableGroups"
					:multiple="true"
					:aria-label-combobox="t('launchpad', 'Target groups')"
					:placeholder="
						t('launchpad', 'Select groups (leave empty for all users)')
					" />
			</div>

			<div class="launchpad-admin__field">
				<NcSelect
					v-model="form.permissionLevel"
					:inputLabel="t('launchpad', 'Permission level')"
					:options="permissionOptions"
					label="label"
					trackBy="id"
					:clearable="false" />
			</div>

			<NcCheckboxRadioSwitch v-model="form.isDefault">
				{{ t('launchpad', 'Set as default template') }}
			</NcCheckboxRadioSwitch>

			<div class="launchpad-admin__modal-actions">
				<NcButton variant="secondary" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary" @click="saveTemplate">
					{{ t('launchpad', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcModal,
	NcSelect,
	NcSelectTags,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../services/api.js'
import { logger } from '../utils/logger.js'

/**
 * A blank template form — the shape the create path starts from.
 *
 * @return {object} An empty working copy; `permissionLevel` is filled in by
 *   the caller because it is one of the localised option objects.
 */
function blankForm() {
	return {
		id: null,
		name: '',
		description: '',
		targetGroups: [],
		permissionLevel: null,
		isDefault: false,
	}
}

export default {
	name: 'TemplateEditorModal',

	components: {
		NcButton,
		NcSelect,
		NcSelectTags,
		NcTextField,
		NcCheckboxRadioSwitch,
		NcModal,
	},

	props: {
		/** Visibility toggle owned by the parent page. */
		open: {
			type: Boolean,
			default: false,
		},

		/**
		 * The template to edit, or `null` to open a blank create form.
		 */
		template: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			form: blankForm(),
			availableGroups: [],
			permissionOptions: [
				{ id: 'view_only', label: t('launchpad', 'View only') },
				{ id: 'add_only', label: t('launchpad', 'Add only') },
				{ id: 'full', label: t('launchpad', 'Full customization') },
			],
		}
	},

	computed: {
		/**
		 * Whether the modal is editing an existing template rather than
		 * creating one.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		isEdit() {
			return Boolean(this.form.id)
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Re-seed the working copy every time the modal opens, so a
			 * cancelled edit never leaks into the next one.
			 *
			 * @param {boolean} isOpen Whether the modal is now open.
			 * @spec openspec/specs/admin-templates/spec.md
			 */
			handler(isOpen) {
				if (isOpen) {
					this.seedForm()
				}
			},
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		template() {
			if (this.open) {
				this.seedForm()
			}
		},
	},

	methods: {
		t,

		/**
		 * Build the working copy from the `template` prop.
		 *
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		seedForm() {
			const defaultPermission = this.permissionOptions[1]

			if (this.template === null) {
				this.form = { ...blankForm(), permissionLevel: defaultPermission }
				return
			}

			this.form = {
				...this.template,
				permissionLevel:
					this.permissionOptions.find(
						(p) => p.id === this.template.permissionLevel,
					) || defaultPermission,
			}
		},

		/**
		 * Persist the working copy — update when it carries an id, create
		 * otherwise — and tell the parent to reload its list.
		 *
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		async saveTemplate() {
			try {
				const data = {
					name: this.form.name,
					description: this.form.description,
					targetGroups: this.form.targetGroups,
					permissionLevel: this.form.permissionLevel?.id,
					isDefault: this.form.isDefault,
				}

				if (this.form.id) {
					await api.updateAdminTemplate(this.form.id, data)
				} else {
					await api.createAdminTemplate(data)
				}

				this.$emit('saved')
			} catch (error) {
				logger.error('Failed to save template:', error)
			}
		},
	},
}
</script>

<style scoped>
.launchpad-admin__field {
	margin-bottom: 16px;
}

.launchpad-admin__field label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}

.launchpad-admin__modal {
	padding: 24px;
}

.launchpad-admin__modal h2 {
	margin: 0 0 24px;
}

.launchpad-admin__modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 24px;
}
</style>
