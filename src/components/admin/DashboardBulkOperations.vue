<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - Bulk dashboard operations widget (REQ-BULK-010).
  -
  - Renders a multi-select table over the visible dashboards plus an
  - "Actions" dropdown that wires into the four bulk endpoints exposed by
  - {@link OCA\\LaunchPad\\Controller\\AdminBulkController}. Every action
  - supports a "Dry run (preview only)" toggle, surfaces a summary of the
  - per-uuid result envelope, and clears the selection on a successful
  - non-dry-run run (REQ-BULK-008).
  -->

<template>
	<div class="launchpad-bulk-ops" data-test="bulk-ops">
		<h3>{{ t('launchpad', 'Bulk dashboard operations') }}</h3>
		<p class="launchpad-bulk-ops__hint">
			{{
				t(
					'launchpad',
					'Select multiple dashboards and apply an admin action to all of them at once. Every operation supports a dry-run preview.',
				)
			}}
		</p>

		<div v-if="loading" class="launchpad-bulk-ops__loading">
			{{ t('launchpad', 'Loading dashboards…') }}
		</div>

		<table v-else class="launchpad-bulk-ops__table" data-test="bulk-ops-table">
			<thead>
				<tr>
					<th scope="col" class="launchpad-bulk-ops__col-select">
						<input
							type="checkbox"
							:checked="allSelected"
							:indeterminate.prop="someSelected && !allSelected"
							:aria-label="t('launchpad', 'Select all dashboards')"
							data-test="bulk-ops-select-all"
							@change="toggleSelectAll" />
					</th>
					<th scope="col">{{ t('launchpad', 'Name') }}</th>
					<th scope="col">{{ t('launchpad', 'Status') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="dash in dashboards"
					:key="dash.uuid"
					:class="{
						'launchpad-bulk-ops__row--selected': selectedUuids.includes(
							dash.uuid,
						),
					}">
					<td>
						<input
							type="checkbox"
							:value="dash.uuid"
							:checked="selectedUuids.includes(dash.uuid)"
							:aria-label="
								t('launchpad', 'Select {name}', { name: dash.name })
							"
							data-test="bulk-ops-row-select"
							@change="toggleRow(dash.uuid)" />
					</td>
					<td>{{ dash.name }}</td>
					<td>{{ dash.publicationStatus || 'published' }}</td>
				</tr>
			</tbody>
		</table>

		<div class="launchpad-bulk-ops__actions">
			<select
				v-model="selectedAction"
				:disabled="selectedUuids.length === 0"
				data-test="bulk-ops-action-select">
				<option value="">
					{{ t('launchpad', 'Actions…') }}
				</option>
				<option value="delete">
					{{ t('launchpad', 'Delete') }}
				</option>
				<option value="move">
					{{ t('launchpad', 'Move to…') }}
				</option>
				<option value="status">
					{{ t('launchpad', 'Set status') }}
				</option>
				<option value="reindex">
					{{ t('launchpad', 'Reindex') }}
				</option>
			</select>
			<button
				:disabled="
					selectedUuids.length === 0 || selectedAction === '' || running
				"
				data-test="bulk-ops-run"
				@click="runAction">
				{{ running ? t('launchpad', 'Working…') : t('launchpad', 'Apply') }}
			</button>
		</div>

		<div
			v-if="modal"
			class="launchpad-bulk-ops__modal"
			data-test="bulk-ops-modal">
			<div class="launchpad-bulk-ops__modal-inner">
				<h4>{{ modal.title }}</h4>
				<p>{{ modal.message }}</p>

				<!--
					`for`/`id` pairs, rather than the bare `<label>`s that were
					here. A label with neither `for` nor the field nested
					inside it is associated with nothing: it renders as text
					and the field is announced with no name. `for`/`id` keeps
					the existing layout exactly as it was, which wrapping the
					inputs would not.

					The ids are static because this modal is a singleton — it
					renders once, for one pending bulk action.
				-->
				<div
					v-if="modal.action === 'move'"
					class="launchpad-bulk-ops__field">
					<label for="launchpad-bulk-ops-parent-uuid">
						{{
							t('launchpad', 'New parent UUID (leave empty for root)')
						}}
					</label>
					<input
						id="launchpad-bulk-ops-parent-uuid"
						v-model="parentUuidInput"
						type="text"
						data-test="bulk-ops-parent-uuid" />
				</div>

				<div
					v-if="modal.action === 'status'"
					class="launchpad-bulk-ops__field">
					<label for="launchpad-bulk-ops-status">
						{{ t('launchpad', 'Publication status') }}
					</label>
					<select
						id="launchpad-bulk-ops-status"
						v-model="statusInput"
						data-test="bulk-ops-status-select">
						<option value="draft">
							{{ t('launchpad', 'Draft') }}
						</option>
						<option value="published">
							{{ t('launchpad', 'Published') }}
						</option>
						<option value="scheduled">
							{{ t('launchpad', 'Scheduled') }}
						</option>
					</select>
					<label
						v-if="statusInput === 'scheduled'"
						for="launchpad-bulk-ops-publish-at">
						{{ t('launchpad', 'Publish at') }}
					</label>
					<input
						v-if="statusInput === 'scheduled'"
						id="launchpad-bulk-ops-publish-at"
						v-model="publishAtInput"
						type="datetime-local"
						data-test="bulk-ops-publish-at" />
				</div>

				<label class="launchpad-bulk-ops__dryrun">
					<input
						v-model="dryRun"
						type="checkbox"
						data-test="bulk-ops-dryrun" />
					{{ t('launchpad', 'Dry run (preview only)') }}
				</label>

				<div class="launchpad-bulk-ops__modal-actions">
					<button @click="cancelModal">
						{{ t('launchpad', 'Cancel') }}
					</button>
					<button
						:disabled="running"
						data-test="bulk-ops-confirm"
						@click="confirmAction">
						{{ t('launchpad', 'OK') }}
					</button>
				</div>
			</div>
		</div>

		<div
			v-if="resultSummary"
			class="launchpad-bulk-ops__result"
			data-test="bulk-ops-result">
			<p v-if="resultSummary.dryRun">
				{{
					t('launchpad', 'PREVIEW: would change {count} dashboards.', {
						count: resultSummary.changed,
					})
				}}
			</p>
			<p v-else>
				{{
					t(
						'launchpad',
						'Changed {count} dashboards. Skipped {skipped}.',
						{
							count: resultSummary.changed,
							skipped: resultSummary.skipped,
						},
					)
				}}
			</p>
			<ul v-if="resultSummary.errors.length > 0">
				<li v-for="(err, idx) in resultSummary.errors" :key="idx">
					{{ err.uuid }}: {{ err.reason }}
				</li>
			</ul>
		</div>

		<div
			v-if="errorMessage"
			class="launchpad-bulk-ops__error"
			data-test="bulk-ops-error">
			{{ errorMessage }}
		</div>
	</div>
</template>

<script>
import { api } from '../../services/api.js'

export default {
	name: 'DashboardBulkOperations',

	data() {
		return {
			dashboards: [],
			selectedUuids: [],
			selectedAction: '',
			loading: false,
			running: false,
			modal: null,
			parentUuidInput: '',
			statusInput: 'published',
			publishAtInput: '',
			dryRun: false,
			resultSummary: null,
			errorMessage: '',
		}
	},

	computed: {
		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		allSelected() {
			return (
				this.dashboards.length > 0
				&& this.selectedUuids.length === this.dashboards.length
			)
		},

		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		someSelected() {
			return this.selectedUuids.length > 0
		},
	},

	mounted() {
		this.loadDashboards()
	},

	methods: {
		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		async loadDashboards() {
			this.loading = true
			try {
				const { data } = await api.getVisibleDashboards()
				this.dashboards = Array.isArray(data) ? data : data.dashboards || []
			} catch {
				this.errorMessage = t('launchpad', 'Failed to load dashboards')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Select or clear every dashboard row from the header checkbox.
		 *
		 * @param {Event} event The checkbox's change event.
		 * @spec openspec/specs/dashboard-bulk-operations/spec.md
		 */
		toggleSelectAll(event) {
			if (event.target.checked) {
				this.selectedUuids = this.dashboards.map((d) => d.uuid)
			} else {
				this.selectedUuids = []
			}
		},

		/**
		 * Add or remove one dashboard from the bulk selection.
		 *
		 * @param {string} uuid UUID of the dashboard row toggled.
		 * @spec openspec/specs/dashboard-bulk-operations/spec.md
		 */
		toggleRow(uuid) {
			const idx = this.selectedUuids.indexOf(uuid)
			if (idx === -1) {
				this.selectedUuids.push(uuid)
			} else {
				this.selectedUuids.splice(idx, 1)
			}
		},

		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		runAction() {
			this.errorMessage = ''
			this.resultSummary = null
			const titles = {
				delete: t('launchpad', 'Delete dashboards?'),
				move: t('launchpad', 'Move dashboards?'),
				status: t('launchpad', 'Set publication status?'),
				reindex: t('launchpad', 'Reindex dashboards for search?'),
			}
			const messages = {
				delete: t(
					'launchpad',
					'About to delete {n} dashboards. This cannot be undone.',
					{ n: this.selectedUuids.length },
				),

				move: t('launchpad', 'About to re-parent {n} dashboards.', {
					n: this.selectedUuids.length,
				}),

				status: t(
					'launchpad',
					'About to update the publication status of {n} dashboards.',
					{ n: this.selectedUuids.length },
				),

				reindex: t(
					'launchpad',
					'About to reindex {n} dashboards for unified search.',
					{ n: this.selectedUuids.length },
				),
			}
			this.modal = {
				action: this.selectedAction,
				title: titles[this.selectedAction],
				message: messages[this.selectedAction],
			}
		},

		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		cancelModal() {
			this.modal = null
		},

		/** @spec openspec/specs/dashboard-bulk-operations/spec.md */
		async confirmAction() {
			this.running = true
			try {
				const opts = { dryRun: this.dryRun }
				let response
				if (this.modal.action === 'delete') {
					response = await api.bulkDeleteDashboards(
						this.selectedUuids,
						opts,
					)
				} else if (this.modal.action === 'move') {
					const parent =
						this.parentUuidInput.trim() === ''
							? null
							: this.parentUuidInput.trim()
					response = await api.bulkMoveDashboards(
						this.selectedUuids,
						parent,
						opts,
					)
				} else if (this.modal.action === 'status') {
					const extra = { ...opts }
					if (this.statusInput === 'scheduled') {
						extra.publishAt = this.publishAtInput
					}
					response = await api.bulkStatusDashboards(
						this.selectedUuids,
						this.statusInput,
						extra,
					)
				} else if (this.modal.action === 'reindex') {
					response = await api.bulkReindexDashboards(
						this.selectedUuids,
						opts,
					)
				}

				const data = response?.data || {}
				const changed =
					data.deletedCount
					?? data.movedCount
					?? data.updatedCount
					?? data.reindexedCount
					?? data.wouldDeleteCount
					?? data.wouldMoveCount
					?? data.wouldUpdateCount
					?? data.wouldReindexCount
					?? 0
				const skipped = data.skippedCount ?? data.wouldSkipCount ?? 0
				this.resultSummary = {
					dryRun: !!data.dryRun,
					changed,
					skipped,
					errors: Array.isArray(data.errors) ? data.errors : [],
				}

				if (!data.dryRun) {
					this.selectedUuids = []
					this.loadDashboards()
				}
			} catch (e) {
				this.errorMessage =
					e?.response?.data?.error
					|| t('launchpad', 'Bulk operation failed')
			} finally {
				this.running = false
				this.modal = null
			}
		},
	},
}
</script>

<style scoped>
.launchpad-bulk-ops {
	margin-top: 1rem;
}
.launchpad-bulk-ops__table {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 0.75rem;
}
.launchpad-bulk-ops__table th,
.launchpad-bulk-ops__table td {
	padding: 0.25rem 0.5rem;
	text-align: left;
}
.launchpad-bulk-ops__col-select {
	width: 2rem;
}
.launchpad-bulk-ops__row--selected {
	background-color: var(--color-background-hover, #eee);
}
.launchpad-bulk-ops__actions {
	display: flex;
	gap: 0.5rem;
	margin-bottom: 0.75rem;
}
.launchpad-bulk-ops__modal {
	position: fixed;
	inset: 0;
	background-color: rgba(0, 0, 0, 0.4);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 1000;
}
.launchpad-bulk-ops__modal-inner {
	background-color: var(--color-main-background, #fff);
	padding: 1rem;
	border-radius: 8px;
	min-width: 320px;
}
.launchpad-bulk-ops__field {
	margin: 0.5rem 0;
}
.launchpad-bulk-ops__dryrun {
	display: flex;
	gap: 0.5rem;
	align-items: center;
	margin: 0.5rem 0;
}
.launchpad-bulk-ops__modal-actions {
	display: flex;
	gap: 0.5rem;
	justify-content: flex-end;
}
.launchpad-bulk-ops__error {
	color: var(--color-error, #c00);
}
</style>
