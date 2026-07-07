<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="link-button-host">
		<!-- Rendering (single button + list modes) is delegated to the shared
		     @conduction/nextcloud-vue CnLinkButtonWidget. The two app-specific
		     action paths it can't own are handled here: `internal` actions go
		     through launchpad's useInternalActions registry, and `createFile`
		     opens the document-creation modal below. -->
		<CnLinkButtonWidget
			:content="content"
			:placement="placement"
			@internal-action="onInternalAction"
			@create-file="onCreateFile" />

		<div
			v-if="modalOpen"
			class="link-button-host__modal-backdrop"
			role="dialog"
			aria-modal="true"
			:aria-labelledby="modalTitleId"
			@click.self="closeModal">
			<div class="link-button-host__modal">
				<h3 :id="modalTitleId" class="link-button-host__modal-title">
					{{ t('launchpad', 'Create Document') }}
				</h3>
				<label class="link-button-host__modal-label">
					{{ t('launchpad', 'File Name') }}
					<input
						ref="filenameInput"
						v-model="filenameDraft"
						type="text"
						class="link-button-host__modal-input"
						:placeholder="t('launchpad', 'Enter filename')"
						@keyup.enter="onCreateConfirm">
				</label>
				<p class="link-button-host__modal-extension">
					.{{ pendingExtension }}
				</p>
				<div class="link-button-host__modal-actions">
					<button
						type="button"
						class="link-button-host__modal-cancel"
						:disabled="isExecuting"
						@click="closeModal">
						{{ t('launchpad', 'Cancel') }}
					</button>
					<button
						type="button"
						class="link-button-host__modal-create"
						:disabled="!canCreate || isExecuting"
						@click="onCreateConfirm">
						{{ isExecuting ? t('launchpad', 'Creating…') : t('launchpad', 'Create') }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { CnLinkButtonWidget } from '@conduction/nextcloud-vue'
import { useInternalActions } from '../../../composables/useInternalActions.js'

// `@nextcloud/axios` / `@nextcloud/router` / `@nextcloud/dialogs` are loaded
// lazily inside `onCreateConfirm()` so the registry import graph doesn't drag
// in chunks vitest's css-no-op plugin can't transitively intercept.

let modalIdCounter = 0

/**
 * LinkButtonHost — the `link` widget renderer. Delegates all rendering to the
 * shared `CnLinkButtonWidget` and supplies launchpad's host-side behaviour for
 * the two action types the shared widget intentionally defers to the consumer:
 * `internal` (dispatched through the `useInternalActions` registry) and
 * `createFile` (a document-creation modal that POSTs to launchpad's endpoint).
 */
export default {
	name: 'LinkButtonHost',

	components: { CnLinkButtonWidget },

	props: {
		/** The placement content blob (`{label, url, actionType, …}`). */
		content: {
			type: Object,
			default: () => ({}),
		},
		/** The placement (carries `id`). */
		placement: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		modalIdCounter += 1
		return {
			modalOpen: false,
			filenameDraft: '',
			pendingExtension: '',
			isExecuting: false,
			modalTitleId: `link-button-host-modal-${modalIdCounter}`,
		}
	},

	computed: {
		/** Whether the Create button is enabled. */
		canCreate() {
			return this.filenameDraft.trim() !== ''
		},
	},

	methods: {
		t,

		/**
		 * Dispatch an `internal` action through launchpad's registry.
		 *
		 * @param {string} actionId the registered action id.
		 * @return {void}
		 */
		onInternalAction(actionId) {
			const { invoke } = useInternalActions()
			invoke(actionId)
		},

		/**
		 * Open the create-file modal for the given extension token.
		 *
		 * @param {string} token the extension token (e.g. `docx`).
		 * @return {void}
		 */
		onCreateFile(token) {
			this.pendingExtension = String(token || '').trim().replace(/^\./, '').toLowerCase()
			this.filenameDraft = ''
			this.modalOpen = true
			this.$nextTick(() => {
				if (this.$refs.filenameInput) {
					this.$refs.filenameInput.focus()
				}
			})
		},

		/** Close the create-file modal (no-op while a create is in flight). */
		closeModal() {
			if (this.isExecuting) {
				return
			}
			this.modalOpen = false
		},

		/**
		 * Create the document via launchpad's endpoint and open it.
		 *
		 * @return {Promise<void>}
		 */
		async onCreateConfirm() {
			if (!this.canCreate || this.isExecuting) {
				return
			}
			const ext = this.pendingExtension
			const safeName = this.filenameDraft.trim()
			const filename = ext === '' ? safeName : `${safeName}.${ext}`

			this.isExecuting = true
			try {
				const [{ default: axios }, { generateUrl }, { showError }] = await Promise.all([
					import('@nextcloud/axios'),
					import('@nextcloud/router'),
					import('@nextcloud/dialogs'),
				])
				try {
					const response = await axios.post(
						generateUrl('/apps/launchpad/api/files/create'),
						{ filename, dir: '/', content: '' },
					)
					const data = response?.data
					if (data && data.status === 'success' && typeof data.url === 'string') {
						window.open(data.url, '_blank')
						this.modalOpen = false
					} else {
						showError(t('launchpad', 'Failed to create document'))
					}
				} catch (err) {
					showError(t('launchpad', 'Failed to create document'))
				}
			} finally {
				this.isExecuting = false
			}
		},
	},
}
</script>

<style scoped>
.link-button-host {
	width: 100%;
	height: 100%;
}

.link-button-host__modal-backdrop {
	position: fixed;
	inset: 0;
	background-color: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10000;
}

.link-button-host__modal {
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 20px;
	border-radius: var(--border-radius-large, 8px);
	min-width: 320px;
	max-width: 90vw;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.link-button-host__modal-title {
	margin: 0 0 12px 0;
	font-size: 16px;
}

.link-button-host__modal-label {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 13px;
}

.link-button-host__modal-input {
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.link-button-host__modal-extension {
	margin: 8px 0 12px 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.link-button-host__modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}

.link-button-host__modal-cancel,
.link-button-host__modal-create {
	padding: 6px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 13px;
}

.link-button-host__modal-create {
	background: var(--color-primary);
	color: var(--color-primary-text);
	border-color: var(--color-primary);
}

.link-button-host__modal-create:disabled,
.link-button-host__modal-cancel:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}
</style>
