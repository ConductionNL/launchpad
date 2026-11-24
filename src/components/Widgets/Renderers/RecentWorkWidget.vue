<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="recent-work" data-testid="recent-work-widget">
		<p v-if="loading" class="recent-work__state" data-testid="recent-work-loading">
			{{ t('launchpad', 'Loading your recent work…') }}
		</p>
		<p v-else-if="unavailable" class="recent-work__state" data-testid="recent-work-unavailable">
			{{ t('launchpad', 'Recent work needs the Activity app.') }}
		</p>
		<p v-else-if="rows.length === 0" class="recent-work__state" data-testid="recent-work-empty">
			{{ t('launchpad', 'Nothing you worked on recently.') }}
		</p>
		<ul v-else class="recent-work__list" data-testid="recent-work-list">
			<li v-for="row in rows" :key="row.key">
				<a :href="row.link" class="recent-work__row" data-testid="recent-work-row">
					<span class="recent-work__text">
						<span class="recent-work__title">{{ row.title }}</span>
						<span class="recent-work__meta">{{ row.meta }}</span>
					</span>
					<ChevronRight class="recent-work__chevron" :size="20" aria-hidden="true" />
				</a>
			</li>
		</ul>
	</div>
</template>

<script>
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'

/** Milliseconds in a day. */
const DAY_MS = 86400000

/** A name that is only an id (a record's uuid) says nothing to the reader. */
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

/** App ids the Activity app reports under another name than the reader knows. */
const APP_LABELS = { dav: 'calendar', spreed: 'talk' }

/**
 * When something was done, as the boards word it in recent work: this
 * morning, afternoon or evening for today, the weekday within the last week,
 * the day and month before that.
 *
 * @param {string} value ISO date.
 * @param {Date} now The current moment.
 * @param {string} locale BCP 47 locale.
 * @return {string}
 * @spec openspec/specs/widgets/spec.md
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
export function recentWhen(value, now, locale) {
	const when = new Date(value ?? '')
	if (Number.isNaN(when.getTime())) {
		return ''
	}
	const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
	if (when >= startOfToday) {
		if (when.getHours() < 12) {
			return t('launchpad', 'this morning')
		}
		return when.getHours() < 18 ? t('launchpad', 'this afternoon') : t('launchpad', 'this evening')
	}
	if (startOfToday - when < 6 * DAY_MS) {
		return when.toLocaleDateString(locale, { weekday: 'long' })
	}
	return when.toLocaleDateString(locale, { day: 'numeric', month: 'short' })
}

/**
 * Whether an activity is the reader removing something.
 *
 * @param {object} activity An Activity API entry.
 * @return {boolean} True for a delete.
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
function isDeletion(activity) {
	return /deleted|_delete/.test(String(activity.type ?? '')) || /deleted/.test(String(activity.subject ?? ''))
}

/**
 * The two keys one thing is known by: its object, and its name within its
 * app (the same file saved again gets a new id).
 *
 * @param {object} activity An Activity API entry.
 * @param {string} name The thing's name.
 * @return {Array<string>} The object key and the name key.
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
function thingKeys(activity, name) {
	return [`${activity.object_type ?? ''}:${activity.object_id ?? name}`, `${activity.app ?? ''}:${name}`]
}

/**
 * Whether an activity names a thing a row can show: a name that is not
 * only an id, a link, and an app the author chose.
 *
 * @param {object} activity An Activity API entry.
 * @param {string} name The thing's name.
 * @param {Array<string>} apps Only these apps' activity, all when empty.
 * @return {boolean} True when it can be a row.
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
function isShowable(activity, name, apps) {
	if (name === '' || UUID.test(name) || !activity.link) {
		return false
	}
	return apps.length === 0 || apps.includes(activity.app)
}

/**
 * The rows recent work shows for the reader's own activity: one row per
 * thing (its newest activity), named by the thing and not by the action,
 * with the app and when below it. Something the reader removed is not work
 * to pick up again, and neither is its older activity: the feed is newest
 * first, so the delete marks the thing as seen before that comes.
 *
 * @param {Array<object>} activities The Activity API's own-activity entries, newest first.
 * @param {number} limit The most rows to show.
 * @param {Date} now The current moment.
 * @param {string} locale BCP 47 locale.
 * @param {Array<string>} [apps] Only these apps' activity, all when empty.
 * @return {Array<{key: string, title: string, meta: string, link: string}>}
 * @spec openspec/specs/widgets/spec.md
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
export function recentWorkRows(activities, limit, now, locale, apps = []) {
	const rows = []
	const seen = new Set()
	for (const activity of Array.isArray(activities) ? activities : []) {
		const name = String(activity?.object_name ?? '').split('/').filter(Boolean).pop() || ''
		const keys = thingKeys(activity, name)
		if (isDeletion(activity)) {
			if (name !== '') {
				keys.forEach((k) => seen.add(k))
			}
			continue
		}
		if (!isShowable(activity, name, apps) || keys.some((k) => seen.has(k))) {
			continue
		}
		keys.forEach((k) => seen.add(k))
		const app = APP_LABELS[activity.app] ?? activity.app ?? ''
		rows.push({
			key: keys[0],
			title: name,
			meta: [app, recentWhen(activity.datetime, now, locale)].filter(Boolean).join(' · '),
			link: activity.link,
		})
		if (rows.length >= limit) {
			break
		}
	}
	return rows
}

/**
 * RecentWorkWidget: "Verder waar u was" on LpVandaag. The reader's own
 * recent work from the Activity app (its `self` filter), so what colleagues
 * or other apps did does not show here.
 *
 * @spec openspec/specs/widgets/spec.md
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
export default {
	name: 'RecentWorkWidget',

	components: { ChevronRight },

	props: {
		/** `{limit, apps}`: how many rows to show (default 6), and only these apps' work. */
		content: OBJECT_PROP,
	},

	data() {
		return {
			loading: true,
			unavailable: false,
			activities: [],
		}
	},

	computed: {
		/**
		 * @return {Array<object>} The rows to show.
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
		 */
		rows() {
			const limit = Number(this.content?.limit) > 0 ? Number(this.content.limit) : 6
			const apps = Array.isArray(this.content?.apps) ? this.content.apps : []
			return recentWorkRows(this.activities, limit, new Date(), getCanonicalLocale(), apps)
		},
	},

	/**
	 * Ask the Activity app for the reader's own recent activity.
	 *
	 * @return {Promise<void>}
	 * @spec openspec/specs/widgets/spec.md
	 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
	 */
	async mounted() {
		const [{ default: axios }, { generateOcsUrl }] = await Promise.all([
			import('@nextcloud/axios'),
			import('@nextcloud/router'),
		])
		try {
			const { data } = await axios.get(generateOcsUrl('/apps/activity/api/v2/activity/self'), {
				params: { format: 'json', limit: 200 },
			})
			this.activities = Array.isArray(data?.ocs?.data) ? data.ocs.data : []
		} catch (error) {
			// 304 means no activity yet; anything else means the app cannot answer.
			this.unavailable = error?.response?.status !== 304
		} finally {
			this.loading = false
		}
	},

	methods: { t },
}
</script>

<style scoped>
.recent-work__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

/* The theme colours and underlines every link with id weight; a row reads
   as a list row in the main text colour. */
.recent-work__row:not(#recent-work-a):not(#recent-work-b) {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 0;
	border-top: 1px solid var(--color-border);
	color: var(--color-main-text) !important;
	text-decoration: none !important;
}

.recent-work__text {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-width: 0;
}

.recent-work__title {
	overflow: hidden;
	font-size: 15px;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.recent-work__meta {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.recent-work__chevron {
	flex: none;
	color: var(--color-primary-element);
}

.recent-work__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
