<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section
		v-if="summary && summary.enabled"
		class="dashboard-reactions"
		:aria-label="t('launchpad', 'Reactions')"
		data-testid="dashboard-reactions">
		<p class="dashboard-reactions__question" data-testid="dashboard-reactions-question">
			{{ t('launchpad', 'What do you think of this dashboard?') }}
		</p>
		<div class="dashboard-reactions__chips" role="group" :aria-label="t('launchpad', 'Reactions')">
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
		<p
			v-if="lastChangeLine"
			class="dashboard-reactions__last-change"
			data-testid="dashboard-reactions-last-change">
			{{ lastChangeLine }}
		</p>
	</section>
</template>

<script>
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { TEXT_PROP } from '../../constants/propDefinitions.js'
import { api } from '../../services/api.js'
import { useDashboardStore } from '../../stores/dashboard.js'

/** Milliseconds in a day. */
const DAY_MS = 86400000

/**
 * The moment of a change as the boards word it: "today 08:30" for today,
 * the weekday within the last week, the date before that.
 *
 * @param {Date} changed The moment of the change.
 * @param {Date} now The current moment.
 * @param {string} locale BCP 47 locale.
 * @return {string} The moment.
 * @spec openspec/changes/reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
 */
function whenText(changed, now, locale) {
	const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
	if (changed.getTime() >= startOfToday) {
		return t('launchpad', 'today {time}', {
			time: changed.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
		})
	}
	if (changed.getTime() >= startOfToday - 6 * DAY_MS) {
		return changed.toLocaleDateString(locale, { weekday: 'long' })
	}
	return changed.toLocaleDateString(locale, { day: 'numeric', month: 'long' })
}

/**
 * The last-change line of the reactions bar: "Last updated by <name>,
 * <when>", or "Last updated <when>" without a name.
 *
 * @param {{name?: string, at?: string|null}|null|undefined} lastChange The summary's last change.
 * @param {Date} now The current moment.
 * @param {string} locale BCP 47 locale.
 * @return {string} The line, or '' without a usable time.
 * @spec openspec/changes/reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
 */
export function lastChangeText(lastChange, now, locale) {
	const changed = new Date(lastChange?.at ?? '')
	if (Number.isNaN(changed.getTime())) {
		return ''
	}
	const when = whenText(changed, now, locale)
	const name = typeof lastChange.name === 'string' ? lastChange.name.trim() : ''
	if (name === '') {
		return t('launchpad', 'Last updated {when}', { when }, undefined, { escape: false })
	}
	return t('launchpad', 'Last updated by {name}, {when}', { name, when }, undefined, { escape: false })
}

/**
 * DashboardReactions: the reactions bar at the foot of the dashboard, a
 * card over the full width that asks what the viewer thinks of it. Each
 * emoji with a count, the viewer's own marked (aria-pressed); a click
 * toggles the viewer's reaction; the allowed emoji nobody used yet are
 * offered too. Hovering or focusing a count names who reacted. Absent when
 * reactions are off globally or for the dashboard (the summary says
 * `enabled: false`). After the reactions it names who last changed the
 * dashboard, and when.
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 * @spec openspec/changes/reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
 */
export default {
	name: 'DashboardReactions',

	props: {
		dashboardUuid: TEXT_PROP,
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

		/**
		 * "Last updated by <name>, <when>" from the summary's last change.
		 *
		 * @return {string} The line, or '' when the change is unknown.
		 * @spec openspec/changes/reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
		 */
		lastChangeLine() {
			return lastChangeText(this.summary?.lastChange, new Date(), getCanonicalLocale())
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
	gap: 8px 16px;
	align-items: center;
	margin: 8px;
	padding: 12px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.dashboard-reactions__question,
.dashboard-reactions__last-change {
	margin: 0;
}

.dashboard-reactions__question {
	font-weight: bold;
}

.dashboard-reactions__chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.dashboard-reactions__last-change {
	margin-inline-start: auto;
	color: var(--color-text-maxcontrast);
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
