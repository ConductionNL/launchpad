<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- REQ-TILE-044: the web services on the server an administrator allows
	     LaunchPad to look for. Only these addresses are ever asked whether
	     they answer; the ones that do appear under "Found on this server" in
	     Add tiles. -->
	<CnSettingsSection
		:name="t('launchpad', 'Services to discover')"
		data-testid="admin-discoverable-services">
		<p class="discoverable-services__hint">
			{{ t('launchpad', 'Add tiles lists these services when they answer. LaunchPad only asks the addresses on this list.') }}
		</p>

		<ul v-if="rows.length > 0" class="discoverable-services__list" data-testid="admin-discoverable-services-list">
			<li
				v-for="row in rows"
				:key="row.url"
				class="discoverable-services__item"
				data-testid="admin-discoverable-service">
				<span class="discoverable-services__name">{{ row.name }}</span>
				<code class="discoverable-services__url">{{ row.url }}</code>
				<NcButton
					variant="tertiary"
					:aria-label="t('launchpad', 'Remove {name}', { name: row.name })"
					:disabled="saving"
					@click="removeRow(row.url)">
					{{ t('launchpad', 'Remove') }}
				</NcButton>
			</li>
		</ul>
		<p v-else-if="loaded" class="discoverable-services__hint" data-testid="admin-discoverable-services-empty">
			{{ t('launchpad', 'No services on this list yet.') }}
		</p>

		<form class="discoverable-services__add" @submit.prevent="add">
			<NcTextField
				v-model="newName"
				:label="t('launchpad', 'Name')"
				data-testid="admin-discoverable-service-name" />
			<NcTextField
				v-model="newUrl"
				:label="t('launchpad', 'Web address')"
				:helperText="t('launchpad', 'For example http://localhost:3000')"
				data-testid="admin-discoverable-service-url" />
			<NcButton
				type="submit"
				:disabled="newName.trim() === '' || newUrl.trim() === '' || saving"
				data-testid="admin-discoverable-service-add">
				{{ t('launchpad', 'Add service') }}
			</NcButton>
		</form>

		<div class="discoverable-services__actions">
			<NcButton
				variant="primary"
				:disabled="!loaded || saving"
				data-testid="admin-discoverable-services-save"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
			<p
				v-if="error"
				class="discoverable-services__error"
				role="alert"
				data-testid="admin-discoverable-services-error">
				{{ error }}
			</p>
		</div>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection, NcButton, NcTextField } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * DiscoverableServicesSettings — the General tab's "Services to discover"
 * block (REQ-TILE-044): a name and a web address per service, Add service
 * and Save. The server checks every address and refuses one that is not a
 * web address; its message shows here.
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */
export default {
	name: 'DiscoverableServicesSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcTextField,
	},

	data() {
		return {
			rows: [],
			newName: '',
			newUrl: '',
			error: '',
			loaded: false,
			saving: false,
		}
	},

	/** @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read the stored list.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
		 */
		async load() {
			try {
				const { data } = await api.getDiscoverableServices()
				this.rows = Array.isArray(data?.services) ? data.services : []
			} catch (error) {
				logger.error('Failed to load the services to discover:', error)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * Add the entered service (saved with Save); an address already listed is replaced.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
		 */
		add() {
			const service = { name: this.newName.trim(), url: this.newUrl.trim() }
			this.rows = [...this.rows.filter((row) => row.url !== service.url), service]
			this.newName = ''
			this.newUrl = ''
		},

		/**
		 * @param {string} url The row's address.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
		 */
		removeRow(url) {
			this.rows = this.rows.filter((row) => row.url !== url)
		},

		/**
		 * Save the list; the server's refusal is shown as it is.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
		 */
		async save() {
			this.saving = true
			this.error = ''
			try {
				const { data } = await api.updateDiscoverableServices(this.rows.map((row) => ({ name: row.name, url: row.url })))
				this.rows = Array.isArray(data?.services) ? data.services : []
				showSuccess(t('launchpad', 'Services to discover saved.'))
			} catch (error) {
				logger.error('Failed to save the services to discover:', error)
				this.error = error?.response?.data?.message || t('launchpad', 'The services to discover could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.discoverable-services__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 12px;
}

.discoverable-services__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 16px;
}

.discoverable-services__item,
.discoverable-services__add {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.discoverable-services__name {
	flex: 0 1 160px;
	font-weight: bold;
}

.discoverable-services__url {
	flex: 1 1 200px;
	overflow-wrap: anywhere;
}

.discoverable-services__error {
	color: var(--color-error-text);
	margin-top: 8px;
}

.discoverable-services__actions {
	margin-top: 16px;
}
</style>
