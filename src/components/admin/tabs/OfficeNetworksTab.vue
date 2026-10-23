<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="office-networks-tab" data-testid="office-networks-tab">
		<h3>{{ t('launchpad', 'Office networks') }}</h3>
		<p class="office-networks-tab__intro">
			{{
				t(
					'launchpad',
					"Requests from these networks open a tile's address on the office network, when the tile has one. Enter one IPv4 or IPv6 range per line, such as 10.20.0.0/16.",
				)
			}}
		</p>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<form v-else class="office-networks-tab__form" @submit.prevent="save">
			<label>
				{{ t('launchpad', 'Network ranges') }}
				<textarea
					v-model="text"
					rows="6"
					spellcheck="false"
					placeholder="10.20.0.0/16" />
			</label>
			<p data-testid="office-current-address" role="status">
				{{ currentLine }}
			</p>
			<p v-if="error" class="office-networks-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ t('launchpad', 'Office networks saved.') }}
			</p>
			<NcButton type="submit" variant="primary" :disabled="saving">
				{{ t('launchpad', 'Save office networks') }}
			</NcButton>
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton } from '@nextcloud/vue'

const URL = '/apps/launchpad/api/admin/office-networks'

/**
 * OfficeNetworksTab: the administrator lists the office networks and sees
 * whether their own address is inside them (REQ-TIA-001).
 *
 * @spec openspec/specs/tiles/spec.md
 */
export default {
	name: 'OfficeNetworksTab',

	components: { NcButton },

	data() {
		return {
			text: '',
			currentAddress: '',
			onOfficeNetwork: false,
			loading: true,
			saving: false,
			error: '',
			saved: false,
		}
	},

	computed: {
		/** @spec openspec/specs/tiles/spec.md */
		currentLine() {
			return this.onOfficeNetwork
				? t(
						'launchpad',
						'Your current address {address} is on the office network.',
						{ address: this.currentAddress },
					)
				: t(
						'launchpad',
						'Your current address {address} is not on the office network.',
						{ address: this.currentAddress },
					)
		},
	},

	/** @spec openspec/specs/tiles/spec.md */
	async mounted() {
		try {
			const response = await axios.get(generateUrl(URL))
			this.apply(response?.data || {})
		} catch {
			this.error = t('launchpad', 'The office networks could not be loaded.')
		} finally {
			this.loading = false
		}
	},

	methods: {
		t,

		/**
		 * Take the server's answer.
		 *
		 * @param {{ranges: string[], currentAddress: string, onOfficeNetwork: boolean}} data The answer.
		 * @spec openspec/specs/tiles/spec.md
		 */
		apply(data) {
			this.text = (data.ranges || []).join('\n')
			this.currentAddress = data.currentAddress || ''
			this.onOfficeNetwork = data.onOfficeNetwork === true
		},

		/** @spec openspec/specs/tiles/spec.md */
		async save() {
			this.saving = true
			this.error = ''
			this.saved = false
			const ranges = this.text
				.split(/\s+/)
				.map((line) => line.trim())
				.filter(Boolean)
			try {
				const response = await axios.put(generateUrl(URL), { ranges })
				this.apply(response?.data || {})
				this.saved = true
			} catch (err) {
				this.error =
					err?.response?.data?.error
					|| t('launchpad', 'The office networks could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.office-networks-tab__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 480px;
}

.office-networks-tab__form label {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.office-networks-tab__form textarea {
	font-family: monospace;
}

.office-networks-tab__error {
	color: var(--color-error-text);
}
</style>
