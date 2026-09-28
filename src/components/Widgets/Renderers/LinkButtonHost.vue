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
			@internalAction="onInternalAction"
			@createFile="onCreateFile" />

		<!--
			`tabindex="-1"` + `@keydown.esc` is what makes this dialog
			dismissable without a mouse. Escape is the expected way out of any
			`aria-modal` dialog; before this, closing it required clicking
			either the backdrop or the Cancel button. The listener sits on the
			backdrop rather than on `document` because the dialog focuses
			itself on open (see `focusModal`), so every keystroke while it is
			up is already inside this subtree — and scoping it here means it
			cannot swallow Escape from anything else on the page.
		-->
		<div
			v-if="modalOpen"
			class="link-button-host__modal-backdrop"
			role="dialog"
			aria-modal="true"
			tabindex="-1"
			:aria-labelledby="modalTitleId"
			@click.self="closeModal"
			@keydown.esc="closeModal">
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
						@keyup.enter="onCreateConfirm" />
				</label>
				<p class="link-button-host__modal-extension">
					.{{ pendingExtension }}
				</p>
				<p
					v-if="existingFileWarning"
					class="link-button-host__modal-warning"
					role="alert">
					{{
						t(
							'launchpad',
							'A file named {name} already exists. Choose Replace to overwrite it with an empty file, or change the name.',
							{ name: existingFileWarning },
						)
					}}
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
						{{ createButtonLabel }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { CnLinkButtonWidget } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
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
			// Full filename the server reported as existing; while it
			// matches the draft, Create becomes Replace (issue #712).
			existingFileWarning: '',
		}
	},

	computed: {
		/** Whether the Create button is enabled. */
		canCreate() {
			return this.filenameDraft.trim() !== ''
		},

		/** The filename that Create would write, extension included. */
		pendingFilename() {
			const name = this.filenameDraft.trim()
			return this.pendingExtension === ''
				? name
				: `${name}.${this.pendingExtension}`
		},

		/** Whether the user has seen the warning for exactly this name. */
		confirmsOverwrite() {
			return (
				this.existingFileWarning !== ''
				&& this.existingFileWarning === this.pendingFilename
			)
		},

		/** Create, Replace after the exists warning, or the busy label. */
		createButtonLabel() {
			if (this.isExecuting) {
				return t('launchpad', 'Creating…')
			}
			return this.confirmsOverwrite
				? t('launchpad', 'Replace')
				: t('launchpad', 'Create')
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
		 * @spec openspec/specs/link-button-widget/spec.md#requirement-req-lbn-003-createfile-flow
		 */
		onCreateFile(token) {
			this.pendingExtension = String(token || '')
				.trim()
				.replace(/^\./, '')
				.toLowerCase()
			// REQ-LBN-003: prefill `document_<timestamp>` so an empty
			// submit never lands on a name the user did not choose.
			this.filenameDraft = `document_${Date.now()}`
			this.existingFileWarning = ''
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
		 * The first attempt sends `overwrite: false`. When the server answers
		 * 409 `file_exists`, the modal warns and Create becomes Replace; only
		 * that second, explicit click sends `overwrite: true` (issue #712,
		 * REQ-LBN-004 "UI must warn the user when overwriting").
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/link-button-widget/spec.md#requirement-req-lbn-004-server-side-file-creation-endpoint
		 */
		async onCreateConfirm() {
			if (!this.canCreate || this.isExecuting) {
				return
			}
			const filename = this.pendingFilename
			const overwrite = this.confirmsOverwrite

			this.isExecuting = true
			try {
				const [{ default: axios }, { generateUrl }, { showError }] =
					await Promise.all([
						import('@nextcloud/axios'),
						import('@nextcloud/router'),
						import('@nextcloud/dialogs'),
					])
				try {
					const response = await axios.post(
						generateUrl('/apps/launchpad/api/files/create'),
						{ filename, dir: '/', content: '', overwrite },
					)
					const data = response?.data
					if (
						data
						&& data.status === 'success'
						&& typeof data.url === 'string'
					) {
						window.open(data.url, '_blank')
						this.modalOpen = false
					} else {
						showError(t('launchpad', 'Failed to create document'))
					}
				} catch (error) {
					if (
						error?.response?.status === 409
						&& error.response.data?.error === 'file_exists'
					) {
						this.existingFileWarning = filename
						return
					}
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

.link-button-host__modal-warning {
	margin: 0 0 12px 0;
	color: var(--color-error-text, var(--color-error));
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
