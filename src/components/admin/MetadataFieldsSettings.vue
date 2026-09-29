<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="metadata-fields" data-testid="metadata-fields-settings">
		<h3>{{ t('launchpad', 'Detail fields') }}</h3>
		<p class="metadata-fields__intro">
			{{
				t(
					'launchpad',
					'Fields every dashboard can fill in on its Details tab, such as the owning department. Select fields can be used to filter the dashboard list.',
				)
			}}
		</p>

		<table v-if="fields.length" class="metadata-fields__table">
			<thead>
				<tr>
					<th scope="col">
						{{ t('launchpad', 'Label') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Key') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Type') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Options') }}
					</th>
					<th scope="col">
						<span class="hidden-visually">{{
							t('launchpad', 'Actions')
						}}</span>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="field in fields"
					:key="field.id"
					data-testid="metadata-field-row">
					<td>{{ field.label }}</td>
					<td>
						<code>{{ field.key }}</code>
					</td>
					<td>{{ typeLabel(field.type) }}</td>
					<td>{{ (field.options || []).join(', ') }}</td>
					<td>
						<NcButton
							variant="tertiary"
							data-testid="metadata-field-delete"
							@click="remove(field)">
							{{ t('launchpad', 'Delete') }}
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="metadata-fields__empty">
			{{ t('launchpad', 'No detail fields yet.') }}
		</p>

		<fieldset class="metadata-fields__form">
			<legend>{{ t('launchpad', 'New detail field') }}</legend>
			<NcTextField
				v-model="form.label"
				:label="t('launchpad', 'Label')"
				data-testid="metadata-field-label" />
			<NcTextField
				v-model="form.key"
				:label="t('launchpad', 'Key')"
				data-testid="metadata-field-key" />
			<NcSelect
				v-model="form.type"
				:inputLabel="t('launchpad', 'Type')"
				:options="typeOptions"
				label="name"
				trackBy="id"
				:clearable="false" />
			<NcTextField
				v-if="needsOptions"
				v-model="form.options"
				:label="t('launchpad', 'Options, separated by commas')"
				data-testid="metadata-field-options" />
			<p
				v-if="error"
				class="metadata-fields__error"
				role="alert"
				data-testid="metadata-field-error">
				{{ error }}
			</p>
			<NcButton
				variant="primary"
				data-testid="metadata-field-add"
				@click="add">
				{{ t('launchpad', 'Add field') }}
			</NcButton>
		</fieldset>
	</section>
</template>

<script>
import { NcButton, NcSelect, NcTextField } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'

/**
 * MetadataFieldsSettings: administrators define the detail fields
 * (text, number, date, select, multi-select, yes or no) through the
 * existing admin endpoints. The service's refusal, for example a select
 * field without options, is shown as it answers.
 *
 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
 */
export default {
	name: 'MetadataFieldsSettings',

	components: {
		NcButton,
		NcSelect,
		NcTextField,
	},

	/** @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md */
	data() {
		return {
			fields: [],
			error: '',
			form: { label: '', key: '', type: null, options: '' },
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, name: string}>} The field types.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		typeOptions() {
			return [
				'text',
				'number',
				'date',
				'select',
				'multi-select',
				'boolean',
			].map((id) => ({ id, name: this.typeLabel(id) }))
		},

		/**
		 * @return {boolean} Whether the chosen type takes options.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		needsOptions() {
			return (
				this.form.type?.id === 'select'
				|| this.form.type?.id === 'multi-select'
			)
		},
	},

	/** @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md */
	created() {
		this.form.type = this.typeOptions[0]
		this.load()
	},

	methods: {
		t,

		/**
		 * @param {string} type Field type.
		 * @return {string} Its name.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		typeLabel(type) {
			return (
				{
					text: t('launchpad', 'Text'),
					number: t('launchpad', 'Number'),
					date: t('launchpad', 'Date'),
					select: t('launchpad', 'One choice'),
					'multi-select': t('launchpad', 'Several choices'),
					boolean: t('launchpad', 'Yes or no'),
				}[type] ?? type
			)
		},

		/**
		 * Read the fields.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		async load() {
			try {
				const { data } = await api.getMetadataFields()
				this.fields = Array.isArray(data?.fields) ? data.fields : []
			} catch {
				this.error = t('launchpad', 'The detail fields could not be loaded.')
			}
		},

		/**
		 * Create the field; show the service's refusal when it answers 400.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		async add() {
			this.error = ''
			const body = {
				key: this.form.key.trim(),
				label: this.form.label.trim(),
				type: this.form.type?.id ?? 'text',
			}
			if (this.needsOptions) {
				body.options = this.form.options
					.split(',')
					.map((o) => o.trim())
					.filter((o) => o !== '')
			}
			try {
				await api.createMetadataField(body)
				this.form = {
					label: '',
					key: '',
					type: this.typeOptions[0],
					options: '',
				}
				await this.load()
			} catch (e) {
				this.error =
					e?.response?.data?.message
					|| t('launchpad', 'The field could not be saved.')
			}
		},

		/**
		 * Delete a field.
		 *
		 * @param {object} field The field.
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		async remove(field) {
			try {
				await api.deleteMetadataField(field.id)
				await this.load()
			} catch {
				this.error = t('launchpad', 'The field could not be deleted.')
			}
		},
	},
}
</script>

<style scoped>
.metadata-fields__table {
	width: 100%;
	border-collapse: collapse;
	margin: 12px 0;
}

.metadata-fields__table th,
.metadata-fields__table td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.metadata-fields__form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 480px;
	border: none;
	padding: 0;
}

.metadata-fields__error {
	color: var(--color-error-text);
}
</style>
