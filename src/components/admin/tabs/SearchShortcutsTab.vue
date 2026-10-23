<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="search-shortcuts-tab" data-testid="search-shortcuts-tab">
		<h3>{{ t('launchpad', 'Search shortcuts') }}</h3>
		<p class="search-shortcuts-tab__intro">
			{{
				t(
					'launchpad',
					'A search that starts with a prefix, such as !t printer, goes straight to that site in a new tab. Typing ? in a search box lists the shortcuts.',
				)
			}}
		</p>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<form v-else class="search-shortcuts-tab__form" @submit.prevent="save">
			<table v-if="shortcuts.length > 0" class="search-shortcuts-tab__table">
				<thead>
					<tr>
						<th scope="col">
							{{ t('launchpad', 'Prefix') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Name') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Address with {query}') }}
						</th>
						<th scope="col">
							<span class="hidden-visually">{{
								t('launchpad', 'Actions')
							}}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(shortcut, index) in shortcuts" :key="index">
						<td>
							<input
								v-model="shortcut.prefix"
								type="text"
								maxlength="11"
								pattern="![A-Za-z0-9]{1,10}"
								placeholder="!t"
								:aria-label="t('launchpad', 'Prefix')"
								required />
						</td>
						<td>
							<input
								v-model="shortcut.name"
								type="text"
								maxlength="64"
								placeholder="TOPdesk"
								:aria-label="t('launchpad', 'Name')"
								required />
						</td>
						<td>
							<input
								v-model="shortcut.urlTemplate"
								type="url"
								placeholder="https://example.org/search?q={query}"
								:aria-label="t('launchpad', 'Address with {query}')"
								required />
						</td>
						<td>
							<NcButton variant="tertiary" @click="remove(index)">
								{{ t('launchpad', 'Remove') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
			<p v-else>
				{{ t('launchpad', 'No search shortcuts yet.') }}
			</p>

			<p v-if="error" class="search-shortcuts-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ t('launchpad', 'Search shortcuts saved.') }}
			</p>

			<div class="search-shortcuts-tab__actions">
				<NcButton :disabled="shortcuts.length >= maxShortcuts" @click="add">
					{{ t('launchpad', 'Add shortcut') }}
				</NcButton>
				<NcButton type="submit" variant="primary" :disabled="saving">
					{{ t('launchpad', 'Save search shortcuts') }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcButton } from '@nextcloud/vue'

const MAX_SHORTCUTS = 30
const URL = '/apps/launchpad/api/admin/search-shortcuts'

/**
 * SearchShortcutsTab: the administrator's search shortcuts (REQ-SPX-001).
 * The server validates every entry; its refusal is shown as is.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */
export default {
	name: 'SearchShortcutsTab',

	components: { NcButton },

	data() {
		return {
			shortcuts: [],
			loading: true,
			saving: false,
			error: '',
			saved: false,
			maxShortcuts: MAX_SHORTCUTS,
		}
	},

	/** @spec openspec/specs/tile-quick-search/spec.md */
	async mounted() {
		try {
			const response = await axios.get(generateUrl(URL))
			this.shortcuts = response?.data?.shortcuts || []
		} catch {
			this.error = t('launchpad', 'The search shortcuts could not be loaded.')
		} finally {
			this.loading = false
		}
	},

	methods: {
		/** @spec openspec/specs/tile-quick-search/spec.md */
		add() {
			if (this.shortcuts.length < MAX_SHORTCUTS) {
				this.shortcuts.push({ prefix: '', name: '', urlTemplate: '' })
			}
		},

		/**
		 * Remove the shortcut at a position.
		 *
		 * @param {number} index Position in the list.
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		remove(index) {
			this.shortcuts.splice(index, 1)
		},

		/** @spec openspec/specs/tile-quick-search/spec.md */
		async save() {
			this.saving = true
			this.error = ''
			this.saved = false
			try {
				const response = await axios.put(generateUrl(URL), {
					shortcuts: this.shortcuts,
				})
				this.shortcuts = response?.data?.shortcuts || []
				this.saved = true
			} catch (err) {
				this.error =
					err?.response?.data?.error
					|| t('launchpad', 'The search shortcuts could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.search-shortcuts-tab__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.search-shortcuts-tab__table {
	width: 100%;
	border-collapse: collapse;
}

.search-shortcuts-tab__table th {
	text-align: start;
	padding: 4px;
}

.search-shortcuts-tab__table td {
	padding: 4px;
}

.search-shortcuts-tab__table input {
	width: 100%;
}

.search-shortcuts-tab__actions {
	display: flex;
	gap: 8px;
}

.search-shortcuts-tab__error {
	color: var(--color-error-text);
}
</style>
