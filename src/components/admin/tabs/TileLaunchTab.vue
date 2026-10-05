<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="tile-launch-tab" data-testid="tile-launch-tab">
		<h3>{{ t('launchpad', 'Programs and sign-on') }}</h3>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<form v-else class="tile-launch-tab__form" @submit.prevent="save">
			<p class="tile-launch-tab__intro">
				{{
					t(
						'launchpad',
						'A program tile starts a program on the computer, through an address such as ms-word: or vscode:. List the address types you allow, one per line. Until you list one, program tiles do nothing.',
					)
				}}
			</p>
			<label>
				{{ t('launchpad', 'Allowed program address types') }}
				<textarea
					v-model="schemesText"
					rows="4"
					spellcheck="false"
					placeholder="ms-word" />
			</label>

			<p class="tile-launch-tab__intro">
				{{
					t(
						'launchpad',
						'A sign-on tile opens a company app through your identity provider. Add a launch address per identity provider, with {appId} where the app goes.',
						{ appId: '{appId}' },
					)
				}}
			</p>
			<fieldset
				v-for="(template, index) in templates"
				:key="index"
				class="tile-launch-tab__template"
				data-testid="tile-launch-template">
				<legend>
					{{ template.name || t('launchpad', 'New launch template') }}
				</legend>
				<NcTextField
					v-model="template.name"
					:label="t('launchpad', 'Identity provider name')" />
				<NcTextField
					v-model="template.urlTemplate"
					:label="t('launchpad', 'Launch address')"
					placeholder="https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=..." />
				<NcButton variant="tertiary" @click="removeTemplate(index)">
					{{ t('launchpad', 'Remove launch template') }}
				</NcButton>
			</fieldset>
			<NcButton data-testid="tile-launch-add-template" @click="addTemplate">
				{{ t('launchpad', 'Add launch template') }}
			</NcButton>

			<p v-if="error" class="tile-launch-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ t('launchpad', 'Program and sign-on settings saved.') }}
			</p>
			<NcButton type="submit" variant="primary" :disabled="saving">
				{{ t('launchpad', 'Save program and sign-on settings') }}
			</NcButton>
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcTextField } from '@nextcloud/vue'

const URL = '/apps/launchpad/api/admin/tile-launch'

/**
 * TileLaunchTab: the administrator lists the program address types tiles
 * may use and the single sign-on launch templates tiles pick from
 * (launcher-tile-launch-types, REQ-TLT-001, REQ-TLT-003).
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
export default {
	name: 'TileLaunchTab',

	components: { NcButton, NcTextField },

	data() {
		return {
			schemesText: '',
			templates: [],
			loading: true,
			saving: false,
			error: '',
			saved: false,
		}
	},

	/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
	async mounted() {
		try {
			const response = await axios.get(generateUrl(URL))
			this.apply(response?.data || {})
		} catch {
			this.error = t(
				'launchpad',
				'The program and sign-on settings could not be loaded.',
			)
		} finally {
			this.loading = false
		}
	},

	methods: {
		t,

		/**
		 * Take the server's answer.
		 *
		 * @param {{allowedSchemes: string[], ssoTemplates: object[]}} data The answer.
		 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
		 */
		apply(data) {
			this.schemesText = (data.allowedSchemes || []).join('\n')
			this.templates = (data.ssoTemplates || []).map((template) => ({
				key: template.key,
				name: template.name,
				urlTemplate: template.urlTemplate,
			}))
		},

		/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
		addTemplate() {
			this.templates.push({ name: '', urlTemplate: '' })
		},

		/**
		 * @param {number} index The template's position.
		 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
		 */
		removeTemplate(index) {
			this.templates.splice(index, 1)
		},

		/** @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md */
		async save() {
			this.saving = true
			this.error = ''
			this.saved = false
			const allowedSchemes = this.schemesText
				.split(/\s+/)
				.map((line) => line.trim())
				.filter(Boolean)
			const ssoTemplates = this.templates
				.filter((template) => template.name || template.urlTemplate)
				.map((template) => ({
					...(template.key ? { key: template.key } : {}),
					name: template.name.trim(),
					urlTemplate: template.urlTemplate.trim(),
				}))
			try {
				const response = await axios.put(generateUrl(URL), {
					allowedSchemes,
					ssoTemplates,
				})
				this.apply(response?.data || {})
				this.saved = true
			} catch (err) {
				this.error =
					err?.response?.data?.error
					|| t(
						'launchpad',
						'The program and sign-on settings could not be saved.',
					)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.tile-launch-tab__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 560px;
}

.tile-launch-tab__form label {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.tile-launch-tab__form textarea {
	font-family: monospace;
}

.tile-launch-tab__template {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.tile-launch-tab__error {
	color: var(--color-error-text);
}
</style>
