<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	LeaverHandoverSettings — the Clean up block of Beheer ▸ Operations
	(board LpBeheerOnderhoud, REQ-SHARE-016): where the dashboards of people
	who left go. Their manager, otherwise a named person; without a choice
	REQ-SHARE-012 applies (someone with full rights, or deleted).
-->

<template>
	<div class="leaver-handover" data-test="leaver-handover">
		<h3>{{ t('launchpad', 'Clean up') }}</h3>
		<p class="leaver-handover__label">
			{{ t('launchpad', 'Dashboards of people who left go to') }}
		</p>
		<NcCheckboxRadioSwitch
			:modelValue="manager"
			data-test="leaver-handover-manager"
			@update:modelValue="onManagerChange">
			{{ t('launchpad', 'Their manager') }}
		</NcCheckboxRadioSwitch>

		<div class="leaver-handover__field">
			<NcSelect
				:modelValue="fallback"
				:options="options"
				:filterable="false"
				:loading="searching"
				:inputLabel="manager ? t('launchpad', 'Otherwise') : t('launchpad', 'A person')"
				:placeholder="t('launchpad', 'Search for a person')"
				label="displayName"
				trackBy="id"
				:clearable="true"
				data-test="leaver-handover-fallback"
				@search="onSearch"
				@update:modelValue="onFallbackChange" />
		</div>

		<p class="leaver-handover__hint">
			{{
				t(
					'launchpad',
					'Without a choice, a dashboard goes to someone with full rights on it, or is deleted. Shares stay.',
				)
			}}
		</p>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcSelect } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * Where a leaver's dashboards go (REQ-SHARE-016).
 *
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
 */
export default {
	name: 'LeaverHandoverSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcSelect,
	},

	data() {
		return {
			manager: false,
			fallback: null,
			options: [],
			searching: false,
			searchSeq: 0,
		}
	},

	/**
	 * Read the stored setting.
	 *
	 * @return {Promise<void>}
	 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	async created() {
		try {
			const { data } = await api.getAdminSettings()
			const handover = (data?.data ?? data ?? {}).leaverHandover ?? {}
			this.manager = handover.manager === true
			const uid = typeof handover.fallbackUid === 'string' ? handover.fallbackUid : ''
			this.fallback = uid === '' ? null : { id: uid, displayName: uid }
		} catch (error) {
			logger.error('Failed to load the leaver handover:', error)
		}
	},

	methods: {
		t,

		/**
		 * Look people up for the "otherwise" person; a slower answer for an
		 * earlier query is dropped.
		 *
		 * @param {string} query The search text.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
		 */
		async onSearch(query) {
			const seq = ++this.searchSeq
			if ((query ?? '').trim().length < 2) {
				this.options = []
				return
			}
			this.searching = true
			try {
				const { data } = await api.searchSharees(query)
				if (seq !== this.searchSeq) {
					return
				}
				const users = (data?.data ?? data ?? {}).users ?? []
				this.options = users.map((user) => ({ id: user.id, displayName: user.displayName || user.id }))
			} catch (error) {
				logger.error('People search failed:', error)
				this.options = []
			} finally {
				if (seq === this.searchSeq) {
					this.searching = false
				}
			}
		},

		/**
		 * "Their manager" switched.
		 *
		 * @param {boolean} value Whether a leaver's manager comes first.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
		 */
		async onManagerChange(value) {
			this.manager = value === true
			await this.save()
		},

		/**
		 * The "otherwise" person chosen or cleared.
		 *
		 * @param {{id: string, displayName: string}|null} option The person, or null.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
		 */
		async onFallbackChange(option) {
			this.fallback = option ?? null
			await this.save()
		},

		/**
		 * Store the setting.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
		 */
		async save() {
			try {
				await api.updateSharingPolicy({
					leaverHandover: { manager: this.manager, fallbackUid: this.fallback?.id ?? '' },
				})
			} catch (error) {
				logger.error('Failed to save the leaver handover:', error)
			}
		},
	},
}
</script>

<style scoped>
.leaver-handover__label {
	font-weight: 500;
	margin-bottom: 4px;
}

.leaver-handover__field {
	margin: 12px 0;
	max-width: 400px;
}

.leaver-handover__hint {
	color: var(--color-text-maxcontrast);
}
</style>
