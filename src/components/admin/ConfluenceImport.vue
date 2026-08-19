<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-confluence-import">
		<h3>{{ t('launchpad', 'Import from Confluence') }}</h3>

		<p class="launchpad-confluence-import__hint">
			{{
				t(
					'launchpad',
					'Upload a Confluence HTML export ZIP archive. Each Confluence page becomes a LaunchPad dashboard with a single full-width text widget; the page hierarchy is mirrored using the dashboard tree.',
				)
			}}
		</p>

		<div
			class="launchpad-confluence-import__row launchpad-confluence-import__row--column">
			<label
				for="launchpad-cfli-file"
				class="launchpad-confluence-import__label">
				{{ t('launchpad', 'Confluence export archive (.zip)') }}
			</label>
			<input
				id="launchpad-cfli-file"
				type="file"
				accept=".zip,application/zip"
				data-test="confluence-import-file"
				@change="onFileSelected" />

			<label
				for="launchpad-cfli-parent"
				class="launchpad-confluence-import__label">
				{{
					t(
						'launchpad',
						'Optional parent dashboard UUID (root pages will be slotted under this dashboard)',
					)
				}}
			</label>
			<input
				id="launchpad-cfli-parent"
				v-model="parentUuid"
				type="text"
				class="launchpad-confluence-import__text-input"
				:placeholder="
					t('launchpad', 'Leave empty to import as root dashboards')
				" />

			<div class="launchpad-confluence-import__actions">
				<NcButton
					type="secondary"
					:disabled="!selectedFile || running"
					data-test="confluence-dry-run"
					@click="runDryRun">
					<template #icon>
						<Eye :size="20" />
					</template>
					{{
						running === 'dry-run'
							? t('launchpad', 'Inspecting…')
							: t('launchpad', 'Dry-run preview')
					}}
				</NcButton>

				<NcButton
					type="primary"
					:disabled="!selectedFile || running"
					data-test="confluence-import-submit"
					@click="runImport">
					<template #icon>
						<Upload :size="20" />
					</template>
					{{
						running === 'import'
							? t('launchpad', 'Importing…')
							: t('launchpad', 'Import dashboards')
					}}
				</NcButton>
			</div>

			<div v-if="dryRunResult" class="launchpad-confluence-import__result">
				<p>
					{{
						t(
							'launchpad',
							'{pages} pages, {attachments} attachments — would create {dashboards} dashboards.',
							{
								pages: dryRunResult.pageCount,
								attachments: dryRunResult.attachmentCount,
								dashboards: dryRunResult.estimatedDashboards,
							},
						)
					}}
				</p>
				<p
					v-if="dryRunResult.assetFolder"
					class="launchpad-confluence-import__sub">
					{{
						t('launchpad', 'Assets would be uploaded to {folder}', {
							folder: dryRunResult.assetFolder,
						})
					}}
				</p>
				<ul v-if="dryRunResult.warnings && dryRunResult.warnings.length > 0">
					<li v-for="(w, i) in dryRunResult.warnings" :key="i">
						{{ w }}
					</li>
				</ul>
			</div>

			<div v-if="importResult" class="launchpad-confluence-import__result">
				<p>
					{{
						t(
							'launchpad',
							'Imported {imported} dashboards, skipped {skipped}.',
							{
								imported: importResult.createdDashboardCount,
								skipped: importResult.skippedPageCount,
							},
						)
					}}
				</p>
				<p
					v-if="importResult.assetFolder"
					class="launchpad-confluence-import__sub">
					{{
						t('launchpad', 'Assets uploaded to {folder}', {
							folder: importResult.assetFolder,
						})
					}}
				</p>
				<ul v-if="importResult.errors && importResult.errors.length > 0">
					<li v-for="(err, i) in importResult.errors" :key="i">
						{{ err.pageId }}: {{ err.reason }}
					</li>
				</ul>
				<ul v-if="importResult.warnings && importResult.warnings.length > 0">
					<li v-for="(w, i) in importResult.warnings" :key="i">
						{{ w }}
					</li>
				</ul>
			</div>

			<span v-if="errorMessage" class="launchpad-confluence-import__error">
				{{ errorMessage }}
			</span>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@conduction/nextcloud-vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

export default {
	name: 'ConfluenceImport',

	components: {
		NcButton,
		Upload,
		Eye,
	},

	data() {
		return {
			selectedFile: null,
			parentUuid: '',
			running: '',
			errorMessage: '',
			dryRunResult: null,
			importResult: null,
		}
	},

	methods: {
		/**
		 * Capture the chosen Confluence export from the file input.
		 *
		 * @param {Event} event The input's change event.
		 * @spec openspec/specs/confluence-html-import/spec.md
		 */
		onFileSelected(event) {
			const files = event?.target?.files
			this.selectedFile = files && files.length > 0 ? files[0] : null
			this.dryRunResult = null
			this.importResult = null
			this.errorMessage = ''
		},

		/** @spec openspec/specs/confluence-html-import/spec.md */
		async runDryRun() {
			if (!this.selectedFile) return
			this.running = 'dry-run'
			this.errorMessage = ''
			this.importResult = null
			try {
				const response = await api.confluenceImportDryRun(this.selectedFile)
				this.dryRunResult = response.data
			} catch (err) {
				this.errorMessage =
					err?.response?.data?.error
					|| this.t(
						'launchpad',
						'Confluence dry-run failed. Please try again.',
					)
				logger.error('launchpad confluence dry-run failed', err)
			} finally {
				this.running = ''
			}
		},

		/** @spec openspec/specs/confluence-html-import/spec.md */
		async runImport() {
			if (!this.selectedFile) return
			this.running = 'import'
			this.errorMessage = ''
			this.dryRunResult = null
			try {
				const response = await api.confluenceImport(this.selectedFile, {
					parentUuid: this.parentUuid || null,
				})
				this.importResult = response.data
			} catch (err) {
				this.errorMessage =
					err?.response?.data?.error
					|| this.t(
						'launchpad',
						'Confluence import failed. Please try again.',
					)
				logger.error('launchpad confluence import failed', err)
			} finally {
				this.running = ''
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.launchpad-confluence-import {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
	}

	&__row {
		display: flex;
		align-items: center;
		gap: 0.75rem;

		&--column {
			flex-direction: column;
			align-items: flex-start;
		}
	}

	&__label {
		font-weight: 600;
	}

	&__text-input {
		width: 100%;
		max-width: 480px;
		padding: 0.5rem 0.75rem;
		border-radius: var(--border-radius);
		border: 1px solid var(--color-border);
		background: var(--color-main-background);
		color: var(--color-main-text);
	}

	&__actions {
		display: flex;
		gap: 0.5rem;
	}

	&__error {
		color: var(--color-error);
	}

	&__sub {
		color: var(--color-text-maxcontrast);
		font-size: 0.85rem;
		margin: 0.25rem 0 0;
	}

	&__result {
		background: var(--color-background-hover);
		padding: 0.5rem 0.75rem;
		border-radius: var(--border-radius);
		width: 100%;

		ul {
			margin: 0.25rem 0 0 1.25rem;
			padding: 0;
		}
	}
}
</style>
