<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- launcher-tile-launch-types task 6: the two admin lists behind program
	     and single sign-on tiles. The server checks both (TileLaunchSettings)
	     and refuses a list whole, naming the entry; the message is shown here. -->
	<CnSettingsSection
		:name="t('launchpad', 'Program and sign-on tiles')"
		data-testid="admin-tile-launch">
		<p class="tile-launch-settings__hint">
			{{ t('launchpad', 'Program tiles only work with the schemes you allow here. Single sign-on tiles open one of your sign-on templates; authors pick a template and enter the app id.') }}
		</p>

		<h3 class="tile-launch-settings__heading">
			{{ t('launchpad', 'Allowed program schemes') }}
		</h3>
		<ul v-if="schemes.length > 0" class="tile-launch-settings__list" data-testid="admin-tile-schemes">
			<li v-for="scheme in schemes" :key="scheme" class="tile-launch-settings__item">
				<code>{{ scheme }}</code>
				<NcButton
					variant="tertiary"
					:aria-label="t('launchpad', 'Remove {scheme}', { scheme })"
					:disabled="saving"
					@click="removeScheme(scheme)">
					{{ t('launchpad', 'Remove') }}
				</NcButton>
			</li>
		</ul>
		<p v-else class="tile-launch-settings__hint">
			{{ t('launchpad', 'No schemes allowed yet, so program tiles do nothing.') }}
		</p>
		<form class="tile-launch-settings__add" @submit.prevent="addScheme">
			<NcTextField
				v-model="newScheme"
				:label="t('launchpad', 'Scheme')"
				:helperText="t('launchpad', 'Lower-case, such as ms-word or vscode. javascript, data, file, vbscript, about and blob are never allowed.')"
				data-testid="admin-tile-scheme-input" />
			<NcButton
				type="submit"
				variant="secondary"
				:disabled="saving || newScheme.trim() === ''">
				{{ t('launchpad', 'Allow scheme') }}
			</NcButton>
		</form>

		<h3 class="tile-launch-settings__heading">
			{{ t('launchpad', 'Sign-on templates') }}
		</h3>
		<ul v-if="templates.length > 0" class="tile-launch-settings__list" data-testid="admin-sso-templates">
			<li v-for="template in templates" :key="template.key" class="tile-launch-settings__item">
				<span>
					<strong>{{ template.name }}</strong>
					<code class="tile-launch-settings__address">{{ template.urlTemplate }}</code>
				</span>
				<NcButton
					variant="tertiary"
					:aria-label="t('launchpad', 'Remove {name}', { name: template.name })"
					:disabled="saving"
					@click="removeTemplate(template.key)">
					{{ t('launchpad', 'Remove') }}
				</NcButton>
			</li>
		</ul>
		<p v-else class="tile-launch-settings__hint">
			{{ t('launchpad', 'No sign-on templates yet.') }}
		</p>
		<form class="tile-launch-settings__add" @submit.prevent="addTemplate">
			<NcTextField
				v-model="newTemplate.name"
				:label="t('launchpad', 'Template name')"
				data-testid="admin-sso-name-input" />
			<NcTextField
				v-model="newTemplate.key"
				:label="t('launchpad', 'Key')"
				:helperText="t('launchpad', 'Lower-case letters, digits and hyphens.')"
				data-testid="admin-sso-key-input" />
			<NcTextField
				v-model="newTemplate.urlTemplate"
				:label="t('launchpad', 'Sign-on address')"
				:helperText="t('launchpad', 'An https address with {appId} where the app id goes.', { appId: '{appId}' })"
				data-testid="admin-sso-url-input" />
			<NcButton
				type="submit"
				variant="secondary"
				:disabled="saving || !templateComplete">
				{{ t('launchpad', 'Add template') }}
			</NcButton>
		</form>

		<p
			v-if="error"
			class="tile-launch-settings__error"
			role="alert"
			data-testid="admin-tile-launch-error">
			{{ error }}
		</p>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection, NcButton, NcTextField } from '@conduction/nextcloud-vue'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

export default {
	name: 'TileLaunchSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcTextField,
	},

	data() {
		return {
			schemes: [],
			templates: [],
			newScheme: '',
			newTemplate: { name: '', key: '', urlTemplate: '' },
			saving: false,
			error: '',
		}
	},

	computed: {
		/**
		 * Whether the new template has every field filled in.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6
		 */
		templateComplete() {
			return ['name', 'key', 'urlTemplate'].every((field) => this.newTemplate[field].trim() !== '')
		},
	},

	/** @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read both lists from the admin settings.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6
		 */
		async load() {
			try {
				const { data } = await api.getAdminSettings()
				const settings = data?.data ?? data ?? {}
				this.schemes = Array.isArray(settings.tileAllowedSchemes) ? settings.tileAllowedSchemes : []
				this.templates = Array.isArray(settings.ssoLaunchTemplates) ? settings.ssoLaunchTemplates : []
			} catch (error) {
				logger.error('Failed to load the tile launch settings:', error)
			}
		},

		/**
		 * Save one list. The server refuses a list whole, so on a refusal
		 * nothing changes here either and its message is shown.
		 *
		 * @param {string} key `tileAllowedSchemes` or `ssoLaunchTemplates`.
		 * @param {Array} value The whole new list.
		 * @return {Promise<boolean>} Whether it was saved.
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6
		 */
		async save(key, value) {
			this.saving = true
			this.error = ''
			try {
				await api.updateAdminSettings({ [key]: value })
				await this.load()
				return true
			} catch (error) {
				this.error = error?.response?.data?.error || t('launchpad', 'The setting could not be saved.')
				logger.error('Failed to save the tile launch settings:', error)
				return false
			} finally {
				this.saving = false
			}
		},

		/** @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6 */
		async addScheme() {
			if (await this.save('tileAllowedSchemes', [...this.schemes, this.newScheme.trim()])) {
				this.newScheme = ''
			}
		},

		/**
		 * @param {string} scheme The scheme to drop.
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6
		 */
		async removeScheme(scheme) {
			await this.save('tileAllowedSchemes', this.schemes.filter((entry) => entry !== scheme))
		},

		/** @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6 */
		async addTemplate() {
			const template = {
				key: this.newTemplate.key.trim(),
				name: this.newTemplate.name.trim(),
				urlTemplate: this.newTemplate.urlTemplate.trim(),
			}
			if (await this.save('ssoLaunchTemplates', [...this.templates, template])) {
				this.newTemplate = { name: '', key: '', urlTemplate: '' }
			}
		},

		/**
		 * @param {string} key The key of the template to drop.
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-6
		 */
		async removeTemplate(key) {
			await this.save('ssoLaunchTemplates', this.templates.filter((entry) => entry.key !== key))
		},
	},
}
</script>

<style scoped>
.tile-launch-settings__hint {
	color: var(--color-text-maxcontrast);
	margin: 8px 0;
}

.tile-launch-settings__heading {
	margin: 16px 0 4px;
	font-size: var(--default-font-size);
	font-weight: bold;
}

.tile-launch-settings__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.tile-launch-settings__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	padding: 4px 0;
	border-bottom: 1px solid var(--color-border);
}

.tile-launch-settings__address {
	display: block;
	color: var(--color-text-maxcontrast);
	overflow-wrap: anywhere;
}

.tile-launch-settings__add {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
	margin-top: 8px;
}

.tile-launch-settings__error {
	color: var(--color-error-text, var(--color-error));
	margin-top: 8px;
}
</style>
