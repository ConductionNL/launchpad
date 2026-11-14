<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnSettingsSection
		:name="t('launchpad', 'Search shortcuts')"
		data-testid="admin-search-shortcuts">
		<p class="search-shortcuts__hint">
			{{
				t(
					'launchpad',
					'A query that starts with a prefix such as !t goes straight to that site in a new tab. Typing ! or ? in a search box lists the shortcuts.',
				)
			}}
		</p>

		<p
			v-if="rows.length === 0"
			class="search-shortcuts__hint"
			data-test="search-shortcuts-empty">
			{{ t('launchpad', 'No search shortcuts yet.') }}
		</p>

		<ul v-else class="search-shortcuts__list">
			<li
				v-for="(row, index) in rows"
				:key="row.key"
				class="search-shortcuts__row"
				data-test="search-shortcut-row">
				<NcTextField
					v-model="row.prefix"
					class="search-shortcuts__prefix"
					:label="t('launchpad', 'Prefix')"
					placeholder="!t"
					data-test="search-shortcut-prefix" />
				<NcTextField
					v-model="row.name"
					class="search-shortcuts__name"
					:label="t('launchpad', 'Name')"
					data-test="search-shortcut-name" />
				<NcTextField
					v-model="row.urlTemplate"
					class="search-shortcuts__template"
					:label="t('launchpad', 'Address with {query}')"
					placeholder="https://example.org/search?q={query}"
					data-test="search-shortcut-template" />
				<NcButton
					variant="tertiary"
					:aria-label="t('launchpad', 'Remove shortcut {prefix}', { prefix: row.prefix || '' })"
					data-test="search-shortcut-remove"
					@click="removeRow(index)">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>

		<p
			v-if="error"
			class="search-shortcuts__error"
			role="alert"
			data-test="search-shortcuts-error">
			{{ error }}
		</p>

		<div class="search-shortcuts__actions">
			<NcButton
				variant="secondary"
				:disabled="rows.length >= maxShortcuts"
				data-test="search-shortcut-add"
				@click="addRow">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('launchpad', 'Add shortcut') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving"
				data-test="search-shortcuts-save"
				@click="save">
				{{ t('launchpad', 'Save shortcuts') }}
			</NcButton>
		</div>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection, NcButton, NcTextField } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { isValidFallbackTemplate } from '../../composables/useTileSearch.js'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/** The most shortcuts the server accepts (search-ai-prefix-shortcuts D1). */
export const MAX_SEARCH_SHORTCUTS = 30

/** `!` and 1 to 10 letters or digits, as the server checks it. */
const PREFIX_PATTERN = /^![A-Za-z0-9]{1,10}$/

/** The longest name the server accepts. */
const MAX_NAME_LENGTH = 60

let rowCounter = 0

/**
 * An editable row for a shortcut.
 *
 * @param {{prefix?: string, name?: string, urlTemplate?: string}} [shortcut] the stored shortcut.
 * @return {{key: number, prefix: string, name: string, urlTemplate: string}} the row.
 */
function toRow(shortcut = {}) {
	rowCounter += 1
	return {
		key: rowCounter,
		prefix: shortcut.prefix || '',
		name: shortcut.name || '',
		urlTemplate: shortcut.urlTemplate || '',
	}
}

/**
 * The first problem in a list of shortcuts, in the admin's language, or
 * null when the server will accept it. Mirrors `SearchShortcuts::normalise()`
 * so a mistake is named before the round trip; the server stays the
 * authority.
 *
 * @param {Array<{prefix: string, name: string, urlTemplate: string}>} shortcuts the trimmed list.
 * @return {string|null} the problem.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
 */
export function validateSearchShortcuts(shortcuts) {
	if (shortcuts.length > MAX_SEARCH_SHORTCUTS) {
		return t('launchpad', 'At most {count} search shortcuts are allowed.', { count: MAX_SEARCH_SHORTCUTS })
	}
	const seen = new Set()
	for (const shortcut of shortcuts) {
		if (!PREFIX_PATTERN.test(shortcut.prefix)) {
			return t('launchpad', 'A prefix is ! followed by 1 to 10 letters or digits.')
		}
		const key = shortcut.prefix.toLowerCase()
		if (seen.has(key)) {
			return t('launchpad', 'The prefix {prefix} is used twice.', { prefix: shortcut.prefix })
		}
		seen.add(key)
		if (shortcut.name === '' || shortcut.name.length > MAX_NAME_LENGTH) {
			return t('launchpad', 'The shortcut {prefix} needs a name of at most {count} characters.', { prefix: shortcut.prefix, count: MAX_NAME_LENGTH })
		}
		if (!isValidFallbackTemplate(shortcut.urlTemplate)) {
			return t('launchpad', 'Use an https address that contains {query}', { query: '{query}' })
		}
	}
	return null
}

/**
 * SearchShortcutsSettings — the admin section that edits the quick-search
 * prefix shortcuts (search-ai-prefix-shortcuts REQ-SPX-001). Starts from the
 * `searchShortcuts` initial state and saves the whole list through the
 * admin settings endpoint, which validates it again.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
 */
export default {
	name: 'SearchShortcutsSettings',

	components: {
		CnSettingsSection,
		Delete,
		NcButton,
		NcTextField,
		Plus,
	},

	inject: {
		injectedSearchShortcuts: {
			from: 'searchShortcuts',
			default: () => [],
		},
	},

	data() {
		const stored = Array.isArray(this.injectedSearchShortcuts) ? this.injectedSearchShortcuts : []
		return {
			rows: stored.map((shortcut) => toRow(shortcut)),
			error: '',
			saving: false,
			maxShortcuts: MAX_SEARCH_SHORTCUTS,
		}
	},

	methods: {
		t,

		/**
		 * Add an empty row, up to the limit.
		 *
		 * @return {void}
		 */
		addRow() {
			if (this.rows.length < MAX_SEARCH_SHORTCUTS) {
				this.rows.push(toRow())
			}
		},

		/**
		 * Remove a row; the change is stored on save.
		 *
		 * @param {number} index the row's index.
		 * @return {void}
		 */
		removeRow(index) {
			this.rows.splice(index, 1)
		},

		/**
		 * The rows as the list to store, trimmed.
		 *
		 * @return {Array<{prefix: string, name: string, urlTemplate: string}>} the list.
		 */
		shortcuts() {
			return this.rows.map((row) => ({
				prefix: row.prefix.trim(),
				name: row.name.trim(),
				urlTemplate: row.urlTemplate.trim(),
			}))
		},

		/**
		 * Validate and store the list; a refusal names the problem.
		 *
		 * @return {Promise<boolean>} whether the list was stored.
		 *
		 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
		 */
		async save() {
			const shortcuts = this.shortcuts()
			const problem = validateSearchShortcuts(shortcuts)
			if (problem !== null) {
				this.error = problem
				return false
			}
			this.error = ''
			this.saving = true
			try {
				await api.updateAdminSettings({ searchShortcuts: shortcuts })
				showSuccess(t('launchpad', 'Search shortcuts saved.'))
				return true
			} catch (err) {
				logger.error('Failed to save search shortcuts:', err)
				this.error = err?.response?.data?.error
					|| t('launchpad', 'Failed to save the search shortcuts.')
				return false
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.search-shortcuts__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}

.search-shortcuts__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 8px;
}

.search-shortcuts__row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.search-shortcuts__prefix {
	flex: 0 0 8em;
}

.search-shortcuts__name {
	flex: 1 1 10em;
}

.search-shortcuts__template {
	flex: 3 1 18em;
}

.search-shortcuts__error {
	color: var(--color-error-text);
	margin-bottom: 8px;
}

.search-shortcuts__actions {
	display: flex;
	gap: 8px;
}
</style>
