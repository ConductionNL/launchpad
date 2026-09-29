<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="languages-tab" data-testid="languages-tab">
		<p class="languages-tab__intro">
			{{
				t(
					'launchpad',
					'Readers see the version in their own Nextcloud language, or the primary one when there is none.',
				)
			}}
		</p>
		<p
			v-if="error"
			class="languages-tab__error"
			role="alert"
			data-testid="languages-error">
			{{ error }}
		</p>

		<ul class="languages-tab__list">
			<li
				v-for="variant in variants"
				:key="variant.languageCode"
				data-testid="language-row">
				<div class="languages-tab__head">
					<strong>{{ languageName(variant.languageCode) }}</strong>
					<span v-if="isPrimary(variant)" class="languages-tab__badge">{{
						t('launchpad', 'Primary')
					}}</span>
				</div>
				<NcTextField
					:modelValue="drafts[variant.languageCode]?.name ?? ''"
					:label="t('launchpad', 'Name')"
					@update:modelValue="
						setDraft(variant.languageCode, 'name', $event)
					" />
				<NcTextField
					:modelValue="drafts[variant.languageCode]?.description ?? ''"
					:label="t('launchpad', 'Description')"
					@update:modelValue="
						setDraft(variant.languageCode, 'description', $event)
					" />
				<div class="languages-tab__actions">
					<NcButton
						variant="secondary"
						data-testid="language-save"
						@click="save(variant)">
						{{ t('launchpad', 'Save') }}
					</NcButton>
					<NcButton
						v-if="!isPrimary(variant)"
						variant="tertiary"
						data-testid="language-make-primary"
						@click="makePrimary(variant)">
						{{ t('launchpad', 'Make primary') }}
					</NcButton>
					<NcButton
						v-if="!isPrimary(variant)"
						variant="tertiary"
						data-testid="language-delete"
						@click="remove(variant)">
						{{ t('launchpad', 'Delete') }}
					</NcButton>
				</div>
			</li>
		</ul>

		<fieldset class="languages-tab__add">
			<legend>{{ t('launchpad', 'Add language') }}</legend>
			<NcSelect
				v-model="newLanguage"
				:inputLabel="t('launchpad', 'Language')"
				:options="languageOptions"
				label="name"
				trackBy="code" />
			<NcSelect
				v-model="copyFrom"
				:inputLabel="t('launchpad', 'Start from')"
				:options="copyOptions"
				label="name"
				trackBy="code"
				:clearable="false" />
			<NcButton
				variant="primary"
				:disabled="!newLanguage"
				data-testid="language-add"
				@click="add">
				{{ t('launchpad', 'Add language') }}
			</NcButton>
		</fieldset>
	</div>
</template>

<script>
import { NcButton, NcSelect, NcTextField } from '@conduction/nextcloud-vue'
import { getLanguage, t } from '@nextcloud/l10n'
import { api } from '../../../services/api.js'

/** The languages LaunchPad ships, offered for new versions. */
export const LANGUAGE_CODES = [
	'en',
	'nl',
	'de',
	'fr',
	'es',
	'it',
	'bg',
	'hr',
	'cs',
	'da',
	'et',
	'fi',
	'el',
	'hu',
	'ga',
	'lv',
	'lt',
	'mt',
	'pl',
	'pt',
	'ro',
	'sk',
	'sl',
	'sv',
	'sq',
	'is',
	'nb',
	'sr',
	'bs',
	'mk',
	'uk',
	'be',
	'ru',
	'tr',
	'ca',
	'lb',
	'rm',
]

/**
 * LanguagesTab: the dashboard's language versions in the settings dialog.
 * Lists each with its language and marks the primary; adds one blank or
 * copied from another language; edits name and description; deletes a
 * non-primary one; makes another one primary. All through the existing
 * translation endpoints (DashboardTranslationApiController).
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */
export default {
	name: 'LanguagesTab',

	components: {
		NcButton,
		NcSelect,
		NcTextField,
	},

	props: {
		dashboardUuid: {
			type: String,
			required: true,
		},
	},

	/** @spec openspec/specs/dashboard-language-content/spec.md */
	data() {
		return {
			variants: [],
			drafts: {},
			newLanguage: null,
			copyFrom: null,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {Array<{code: string, name: string}>} Languages not yet used.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		languageOptions() {
			const used = new Set(this.variants.map((v) => v.languageCode))
			return LANGUAGE_CODES.filter((code) => !used.has(code)).map((code) => ({
				code,
				name: this.languageName(code),
			}))
		},

		/**
		 * @return {Array<{code: string, name: string}>} "Blank" plus each existing version.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		copyOptions() {
			return [
				{ code: '', name: t('launchpad', 'Blank') },
				...this.variants.map((v) => ({
					code: v.languageCode,
					name: t('launchpad', 'Copy from {language}', {
						language: this.languageName(v.languageCode),
					}),
				})),
			]
		},
	},

	watch: {
		dashboardUuid: {
			immediate: true,
			/**
			 * Load the versions of the dashboard being edited.
			 *
			 * @spec openspec/specs/dashboard-language-content/spec.md
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * @param {string} code Language code.
		 * @return {string} The language's name in the viewer's language.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		languageName(code) {
			try {
				return (
					new Intl.DisplayNames([getLanguage() || 'en'], {
						type: 'language',
					}).of(code) || code
				)
			} catch {
				return code
			}
		},

		/**
		 * @param {object} variant A version.
		 * @return {boolean} Whether it is the primary one.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		isPrimary(variant) {
			return Number(variant.isPrimary) === 1 || variant.isPrimary === true
		},

		/**
		 * Read the versions.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async load() {
			this.error = ''
			try {
				const { data } = await api.listTranslations(this.dashboardUuid)
				this.variants = Array.isArray(data?.translations)
					? data.translations
					: []
				const drafts = {}
				for (const v of this.variants) {
					drafts[v.languageCode] = {
						name: v.name ?? '',
						description: v.description ?? '',
					}
				}
				this.drafts = drafts
				this.copyFrom = this.copyOptions[0]
			} catch {
				this.error = t(
					'launchpad',
					'The language versions could not be loaded.',
				)
			}
		},

		/**
		 * @param {string} code Language code.
		 * @param {string} key `name` or `description`.
		 * @param {string} value New text.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		setDraft(code, key, value) {
			this.drafts = {
				...this.drafts,
				[code]: { ...this.drafts[code], [key]: value },
			}
		},

		/**
		 * Explain a refusal from the translation endpoints.
		 *
		 * @param {Error} e The failed request.
		 * @return {string} A translated message.
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		refusal(e) {
			switch (e?.response?.data?.error) {
				case 'language_exists':
					return t(
						'launchpad',
						'This dashboard already has that language.',
					)
				case 'last_variant':
					return t('launchpad', 'A dashboard keeps at least one language.')
				case 'primary_variant':
					return t(
						'launchpad',
						'Make another language primary before you delete this one.',
					)
				default:
					return t('launchpad', 'The change could not be saved.')
			}
		},

		/**
		 * Add the chosen language, blank or copied.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async add() {
			if (!this.newLanguage) {
				return
			}
			const body = { languageCode: this.newLanguage.code }
			if (this.copyFrom?.code) {
				body.copyFrom = this.copyFrom.code
			}
			try {
				await api.createTranslation(this.dashboardUuid, body)
				this.newLanguage = null
				await this.load()
			} catch (e) {
				this.error = this.refusal(e)
			}
		},

		/**
		 * Save a version's name and description.
		 *
		 * @param {object} variant The version.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async save(variant) {
			try {
				await api.updateTranslation(
					this.dashboardUuid,
					variant.languageCode,
					this.drafts[variant.languageCode],
				)
				await this.load()
			} catch (e) {
				this.error = this.refusal(e)
			}
		},

		/**
		 * Make a version the primary one.
		 *
		 * @param {object} variant The version.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async makePrimary(variant) {
			try {
				await api.setPrimaryTranslation(
					this.dashboardUuid,
					variant.languageCode,
				)
				await this.load()
			} catch (e) {
				this.error = this.refusal(e)
			}
		},

		/**
		 * Delete a version.
		 *
		 * @param {object} variant The version.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async remove(variant) {
			try {
				await api.deleteTranslation(this.dashboardUuid, variant.languageCode)
				await this.load()
			} catch (e) {
				this.error = this.refusal(e)
			}
		},
	},
}
</script>

<style scoped>
.languages-tab__list {
	display: flex;
	flex-direction: column;
	gap: 16px;
	margin: 12px 0;
	padding: 0;
	list-style: none;
}

.languages-tab__head {
	display: flex;
	gap: 8px;
	align-items: center;
}

.languages-tab__badge {
	padding: 0 6px;
	border-radius: var(--border-radius-pill);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.languages-tab__actions {
	display: flex;
	gap: 8px;
	margin-top: 4px;
}

.languages-tab__add {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: none;
	padding: 0;
}

.languages-tab__error {
	color: var(--color-error-text);
}
</style>
