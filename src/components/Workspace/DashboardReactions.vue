<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="summary && summary.enabled"
		class="dashboard-reactions"
		role="group"
		:aria-label="t('launchpad', 'Reactions')"
		data-testid="dashboard-reactions">
		<button
			v-for="emoji in shown"
			:key="emoji"
			type="button"
			class="dashboard-reactions__chip"
			:class="{ 'dashboard-reactions__chip--mine': isMine(emoji) }"
			:aria-pressed="isMine(emoji) ? 'true' : 'false'"
			:aria-label="
				t('launchpad', '{emoji}: {count} reactions', {
					emoji,
					count: countOf(emoji),
				})
			"
			:title="reactorNames[emoji] || ''"
			:data-testid="`reaction-${emoji}`"
			@click="toggle(emoji)"
			@mouseenter="loadReactors(emoji)"
			@focus="loadReactors(emoji)">
			<span aria-hidden="true">{{ emoji }}</span>
			<span class="dashboard-reactions__count">{{ countOf(emoji) }}</span>
		</button>
		<button
			v-for="emoji in unused"
			:key="`add-${emoji}`"
			type="button"
			class="dashboard-reactions__chip dashboard-reactions__chip--add"
			:aria-label="t('launchpad', 'React with {emoji}', { emoji })"
			:data-testid="`reaction-add-${emoji}`"
			@click="toggle(emoji)">
			<span aria-hidden="true">{{ emoji }}</span>
		</button>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { api } from '../../services/api.js'
import { useDashboardStore } from '../../stores/dashboard.js'

/**
 * DashboardReactions: the reactions bar under the dashboard title. Each
 * emoji with a count, the viewer's own marked (aria-pressed); a click
 * toggles the viewer's reaction; the allowed emoji nobody used yet are
 * offered too. Hovering or focusing a count names who reacted. Absent when
 * reactions are off globally or for the dashboard (the summary says
 * `enabled: false`).
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */
export default {
	name: 'DashboardReactions',

	props: {
		dashboardUuid: {
			type: String,
			default: '',
		},
	},

	/** @spec openspec/specs/dashboard-reactions/spec.md */
	data() {
		return { reactorNames: {} }
	},

	computed: {
		...mapState(useDashboardStore, ['reactionsSummary']),

		/**
		 * @return {object|null} This dashboard's summary `{counts, mine, enabled, allowed}`.
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		summary() {
			return this.reactionsSummary?.[this.dashboardUuid] ?? null
		},

		/**
		 * @return {Array<string>} Emoji with at least one reaction.
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		shown() {
			return Object.keys(this.summary?.counts ?? {}).filter(
				(e) => this.countOf(e) > 0,
			)
		},

		/**
		 * @return {Array<string>} Allowed emoji nobody used yet.
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		unused() {
			const allowed = Array.isArray(this.summary?.allowed)
				? this.summary.allowed
				: []
			return allowed.filter((e) => !this.shown.includes(e))
		},
	},

	watch: {
		dashboardUuid: {
			immediate: true,
			/**
			 * Read the summary of the dashboard that opened.
			 *
			 * @param {string} uuid Dashboard UUID.
			 * @spec openspec/specs/dashboard-reactions/spec.md
			 */
			handler(uuid) {
				this.reactorNames = {}
				if (uuid) {
					this.fetchReactionsSummary(uuid)
				}
			},
		},
	},

	methods: {
		t,

		...mapActions(useDashboardStore, [
			'fetchReactionsSummary',
			'addReaction',
			'removeReaction',
		]),

		/**
		 * @param {string} emoji An emoji.
		 * @return {number} Its count.
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		countOf(emoji) {
			return Number(this.summary?.counts?.[emoji] ?? 0)
		},

		/**
		 * @param {string} emoji An emoji.
		 * @return {boolean} Whether the viewer reacted with it.
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		isMine(emoji) {
			return (
				Array.isArray(this.summary?.mine)
				&& this.summary.mine.includes(emoji)
			)
		},

		/**
		 * Add or take back the viewer's reaction.
		 *
		 * @param {string} emoji An emoji.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		async toggle(emoji) {
			if (this.isMine(emoji)) {
				await this.removeReaction(this.dashboardUuid, emoji)
			} else {
				await this.addReaction(this.dashboardUuid, emoji)
			}
			this.reactorNames = { ...this.reactorNames, [emoji]: '' }
		},

		/**
		 * Name who reacted with an emoji, once, on hover or focus.
		 *
		 * @param {string} emoji An emoji.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-reactions/spec.md
		 */
		async loadReactors(emoji) {
			if (this.reactorNames[emoji]) {
				return
			}
			try {
				const { data } = await api.getDashboardReactors(
					this.dashboardUuid,
					emoji,
				)
				const names = (data?.items ?? []).map(
					(r) => r.displayName || r.userId,
				)
				const more = Number(data?.total ?? names.length) - names.length
				this.reactorNames = {
					...this.reactorNames,
					[emoji]:
						more > 0
							? t('launchpad', '{names} and {count} more', {
									names: names.join(', '),
									count: more,
								})
							: names.join(', '),
				}
			} catch {
				// The names are a courtesy; the count stays.
			}
		},
	},
}
</script>

<style scoped>
.dashboard-reactions {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0 8px 8px;
}

.dashboard-reactions__chip {
	display: inline-flex;
	gap: 4px;
	align-items: center;
	padding: 2px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.dashboard-reactions__chip--mine {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.dashboard-reactions__chip--add {
	opacity: 0.7;
}
</style>
