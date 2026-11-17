<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="dashboard-saved-views">
		<SavedViewsMenu
			:mine="grouped.mine"
			:shared="grouped.shared"
			:canSave="canEdit && clauses.length > 0"
			@apply="$emit('apply', $event)"
			@save="openSave"
			@edit="openEdit"
			@share="share"
			@delete="remove" />
		<SaveViewModal
			:open="modalOpen"
			:view="editing"
			:error="error"
			@close="closeModal"
			@submit="submit" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import SaveViewModal from '../../modals/SaveViewModal.vue'
import SavedViewsMenu from './SavedViewsMenu.vue'
import { FLAG_PROP, LIST_PROP, TEXT_PROP } from '../../constants/propDefinitions.js'
import {
	canManageView,
	createSavedView,
	deleteSavedView,
	groupViews,
	listSavedViews,
	updateSavedView,
} from '../../services/savedViews.js'
import { logger } from '../../utils/logger.js'

/**
 * DashboardSavedViews: the saved views of the open dashboard
 * (drill-down-cross-widget-filter REQ-CWF-009). It lists the views, saves
 * the current clauses as a new one, and lets the creator edit, share,
 * unshare or delete their own. Applying a view is the dashboard's job: it
 * gets an `apply` event with the view.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-009-saved-views-persist-named-filter-combinations
 */
export default {
	name: 'DashboardSavedViews',

	components: { SavedViewsMenu, SaveViewModal },

	props: {
		/** The open dashboard's uuid. */
		dashboardId: TEXT_PROP,
		/** The current user. */
		userId: TEXT_PROP,
		/** Whether the user may edit the dashboard, and so save views. */
		canEdit: FLAG_PROP,
		/** The bus's current clauses. */
		clauses: LIST_PROP,
		/** The service (injectable for tests). */
		api: {
			type: Object,
			default: () => ({ listSavedViews, createSavedView, updateSavedView, deleteSavedView }),
		},
	},

	emits: ['apply'],

	data() {
		return { views: [], modalOpen: false, editing: null, error: '' }
	},

	computed: {
		/**
		 * The views as the menu shows them.
		 *
		 * @return {{mine: Array<object>, shared: Array<object>}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		grouped() {
			return groupViews(this.views, this.userId)
		},
	},

	watch: {
		dashboardId: {
			immediate: true,
			/**
			 * Load the views of the dashboard that opened.
			 *
			 * @return {void}
			 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the dashboard's views; a failure leaves the list empty.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		async load() {
			const dashboardId = this.dashboardId
			if (!dashboardId) {
				this.views = []
				return
			}
			try {
				const views = await this.api.listSavedViews(dashboardId)
				if (dashboardId === this.dashboardId) {
					this.views = views
				}
			} catch (error) {
				logger.warn('[launchpad] the saved views could not be loaded', error)
				this.views = []
			}
		},

		/**
		 * Open the modal for a new view.
		 *
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		openSave() {
			this.editing = null
			this.error = ''
			this.modalOpen = true
		},

		/**
		 * Open the modal for one of the user's own views.
		 *
		 * @param {object} view The view.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		openEdit(view) {
			if (!canManageView(view, this.userId)) {
				return
			}
			this.editing = view
			this.error = ''
			this.modalOpen = true
		},

		/**
		 * Close the modal.
		 *
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		closeModal() {
			this.modalOpen = false
			this.editing = null
		},

		/**
		 * Save a new view from the current clauses, or the edited view's changes.
		 *
		 * @param {{name: string, description: string, isShared: boolean}} input The modal's input.
		 * @return {Promise<void>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
		 */
		async submit(input) {
			try {
				if (this.editing) {
					await this.api.updateSavedView(this.editing.id, input)
				} else {
					await this.api.createSavedView({
						...input,
						dashboardId: this.dashboardId,
						clauses: this.clauses,
						createdBy: this.userId,
					})
				}
			} catch (error) {
				logger.warn('[launchpad] the saved view was not saved', error)
				this.error = t('launchpad', 'The view could not be saved.')
				return
			}
			this.closeModal()
			await this.load()
		},

		/**
		 * Share or stop sharing one of the user's own views.
		 *
		 * @param {object} view The view.
		 * @param {boolean} isShared Whether it is shared from now on.
		 * @return {Promise<void>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-13
		 */
		async share(view, isShared) {
			if (!canManageView(view, this.userId)) {
				return
			}
			try {
				await this.api.updateSavedView(view.id, { isShared })
			} catch (error) {
				logger.warn('[launchpad] the saved view sharing was not changed', error)
				showError(t('launchpad', 'The view could not be changed.'))
				return
			}
			await this.load()
		},

		/**
		 * Delete one of the user's own views.
		 *
		 * @param {object} view The view.
		 * @return {Promise<void>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-13
		 */
		async remove(view) {
			if (!canManageView(view, this.userId)) {
				return
			}
			try {
				await this.api.deleteSavedView(view.id)
			} catch (error) {
				logger.warn('[launchpad] the saved view was not deleted', error)
				showError(t('launchpad', 'The view could not be deleted.'))
				return
			}
			await this.load()
		},
	},
}
</script>

<style scoped>
.dashboard-saved-views {
	display: flex;
	align-items: center;
}
</style>
