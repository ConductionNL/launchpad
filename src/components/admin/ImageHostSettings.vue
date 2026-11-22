<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="image-host-settings" data-test="image-host-settings">
		<h3>{{ t('launchpad', 'External image hosts') }}</h3>
		<p class="image-host-settings__hint">
			{{ t('launchpad', 'Image widgets can show pictures from these websites. Enter one host name per line, such as images.example.com. Pictures from any other website are blocked.') }}
		</p>
		<NcTextArea
			v-model="text"
			:label="t('launchpad', 'Allowed image hosts')"
			:disabled="!loaded || saving"
			resize="vertical"
			data-test="image-host-list" />
		<p
			v-if="error"
			class="image-host-settings__error"
			role="alert"
			data-test="image-host-error">
			{{ error }}
		</p>
		<div class="image-host-settings__actions">
			<NcButton
				variant="primary"
				:disabled="!loaded || saving"
				data-test="image-host-save"
				@click="save">
				{{ t('launchpad', 'Save image hosts') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcTextArea } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * ImageHostSettings — Beheer ▸ Operations section for the external image
 * host allow-list (REQ-IMG-006). The server checks every entry and answers
 * the first one that is not a host name; that message is shown under the list.
 *
 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
 */
export default {
	name: 'ImageHostSettings',

	components: {
		NcButton,
		NcTextArea,
	},

	data() {
		return {
			text: '',
			error: '',
			loaded: false,
			saving: false,
		}
	},

	/** @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read the stored list.
		 *
		 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
		 * @return {Promise<void>}
		 */
		async load() {
			try {
				const { data } = await api.getImageHosts()
				this.apply(data)
			} catch (error) {
				logger.error('Failed to load the image hosts:', error)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * Save the list, one host per line; the server's refusal is shown as it is.
		 *
		 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
		 * @return {Promise<void>}
		 */
		async save() {
			this.saving = true
			this.error = ''
			const hosts = this.text.split('\n').map((line) => line.trim()).filter((line) => line !== '')
			try {
				const { data } = await api.updateImageHosts(hosts)
				this.apply(data)
				showSuccess(t('launchpad', 'Image hosts saved.'))
			} catch (error) {
				logger.error('Failed to save the image hosts:', error)
				this.error = error?.response?.data?.message || t('launchpad', 'The image hosts could not be saved.')
			} finally {
				this.saving = false
			}
		},

		/**
		 * Fill the list from a server answer.
		 *
		 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
		 * @param {object} data The answer, `{ hosts }`.
		 */
		apply(data) {
			this.text = Array.isArray(data?.hosts) ? data.hosts.join('\n') : ''
		},
	},
}
</script>

<style scoped>
.image-host-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.image-host-settings__error {
	color: var(--color-error-text);
	margin-top: 8px;
}

.image-host-settings__actions {
	margin-top: 16px;
}
</style>
