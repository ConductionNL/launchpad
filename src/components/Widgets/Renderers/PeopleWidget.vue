<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="people-widget" :class="layoutClass">
		<header class="people-widget__header">
			<input
				v-model="search"
				type="search"
				class="people-widget__search"
				:aria-label="t('launchpad', 'Search people')"
				:placeholder="
					t('launchpad', 'Search by name, email or expertise…')
				" />
			<button
				type="button"
				class="people-widget__refresh"
				:title="t('launchpad', 'Refresh')"
				:aria-label="t('launchpad', 'Refresh')"
				@click="forceRefresh">
				&#x21bb;
			</button>
		</header>

		<div
			v-if="loading && filteredUsers.length === 0"
			class="people-widget__state">
			{{ t('launchpad', 'Loading…') }}
		</div>

		<div
			v-else-if="error"
			class="people-widget__state people-widget__state--error">
			<p>{{ t('launchpad', 'Failed to load users') }}</p>
			<button type="button" class="people-widget__retry" @click="forceRefresh">
				{{ t('launchpad', 'Retry') }}
			</button>
		</div>

		<div v-else-if="filteredUsers.length === 0" class="people-widget__state">
			{{
				search
					? t('launchpad', 'No users match your search')
					: t('launchpad', 'No matching users.')
			}}
		</div>

		<div v-else class="people-widget__items" :style="gridStyle" role="list">
			<div
				v-for="user in filteredUsers"
				:key="user.uid"
				class="people-widget__item"
				role="listitem">
				<a :href="profileUrl(user.uid)" class="people-widget__link">
					<img
						:src="user.avatarUrl"
						:width="avatarSize"
						:height="avatarSize"
						:alt="
							t('launchpad', 'Avatar of {name}', {
								name: user.displayName,
							})
						"
						class="people-widget__avatar" />

					<div class="people-widget__meta">
						<strong class="people-widget__name">{{
							user.displayName
						}}</strong>
						<span
							v-if="layout !== 'grid' && user.role"
							class="people-widget__role">
							{{ user.role }}
						</span>
						<span
							v-if="
								layout !== 'grid'
								&& (user.organisation || user.department)
							"
							class="people-widget__org">
							{{ user.organisation || user.department }}
						</span>
						<span
							v-if="layout !== 'grid' && user.email"
							class="people-widget__email">
							{{ user.email }}
						</span>
						<span
							v-if="showBirthdayBadge(user)"
							class="people-widget__birthday">
							{{ formatBirthdayBadge(user) }}
						</span>
					</div>
				</a>
				<div
					v-if="layout !== 'grid' && hasCustomFields(user)"
					class="people-widget__fields">
					<template v-for="field in user.customFields" :key="field.key">
						<span
							v-if="field.type !== 'tags'"
							class="people-widget__field">
							{{ field.label }}: {{ field.values.join(', ') }}
						</span>
						<ul
							v-else
							class="people-widget__tags"
							:aria-label="field.label">
							<li v-for="tag in field.values" :key="tag">
								<button
									type="button"
									class="people-widget__tag"
									:class="{
										'people-widget__tag--match': tagMatches(tag),
									}"
									:aria-label="
										t(
											'launchpad',
											'Find everyone tagged {tag}',
											{ tag },
										)
									"
									@click="searchTag(tag)">
									{{ tag }}
								</button>
							</li>
						</ul>
					</template>
				</div>
			</div>
		</div>

		<footer v-if="canLoadMore && !error" class="people-widget__footer">
			<button
				type="button"
				class="people-widget__load-more"
				:disabled="loading"
				@click="loadMore">
				{{
					loading
						? t('launchpad', 'Loading…')
						: t('launchpad', 'Load more')
				}}
			</button>
		</footer>
	</div>
</template>

<script>
const CACHE_TTL_MS = 60 * 1000
const PAGE_SIZE = 50
const MIN_SERVER_QUERY = 2
const SEARCH_DEBOUNCE_MS = 300

const DEFAULT_CONTENT = Object.freeze({
	layout: 'grid',
	filters: [],
	excludeDisabled: true,
	showBirthdays: true,
	birthdayWindowDays: 7,
	sortBy: 'displayName',
	columns: 3,
})

/**
 * PeopleWidget — renders a paginated, group-filterable user directory
 * (capability `people-widget`, REQ-PPL-001..012).
 *
 * Three layout modes (`card`, `grid`, `list`) backed by the same item
 * markup; CSS modifiers tune avatar size and column count. A query of 2 or
 * more characters asks the server, which searches the whole directory by
 * name, email and profile fields (REQ-PEX-003); a shorter one filters the
 * current page (REQ-PPL-011). Search results are never cached (REQ-PEX-004).
 * Tags show as buttons; pressing one searches for it.
 *
 * Pagination is offset-based and matches the backend service contract
 * (REQ-PPL-003): each "Load more" tap appends one page of size
 * `PAGE_SIZE` starting at the current `users.length`.
 *
 * Caching: results are kept in an in-memory map keyed on the JSON-encoded
 * filter shape for {@link CACHE_TTL_MS}; the toolbar refresh button
 * clears the cache and refetches (REQ-PPL-012).
 *
 * Click-through (REQ-PPL-010): each item is rendered as an `<a>` linking
 * to `/u/{uid}` so the browser does the navigation natively (same tab,
 * keyboard accessible by default).
 */
export default {
	name: 'PeopleWidget',

	props: {
		/**
		 * Persisted widget content (see `DEFAULT_CONTENT` for shape).
		 */
		content: {
			type: Object,
			default: () => ({ ...DEFAULT_CONTENT }),
		},
	},

	data() {
		return {
			users: [],
			total: 0,
			hasMore: false,
			loading: false,
			error: null,
			search: '',
			searchResults: [],
			searchHasMore: false,
			searchTimer: null,
			searchSeq: 0,
			cacheKey: '',
			cacheStoredAt: 0,
		}
	},

	computed: {
		/** @spec openspec/specs/people-widget/spec.md */
		layout() {
			const value = this.content?.layout
			return ['card', 'grid', 'list'].includes(value)
				? value
				: DEFAULT_CONTENT.layout
		},

		/** @spec openspec/specs/people-widget/spec.md */
		layoutClass() {
			return `people-widget--${this.layout}`
		},

		/** @spec openspec/specs/people-widget/spec.md */
		columns() {
			const raw = this.content?.columns
			if (typeof raw === 'number' && [2, 3, 4].includes(raw)) {
				return raw
			}
			return this.layout === 'grid' ? 4 : 3
		},

		/** @spec openspec/specs/people-widget/spec.md */
		gridStyle() {
			if (this.layout === 'list') {
				return {}
			}
			return {
				'grid-template-columns': `repeat(${this.columns}, minmax(0, 1fr))`,
			}
		},

		/** @spec openspec/specs/people-widget/spec.md */
		avatarSize() {
			switch (this.layout) {
				case 'card':
					return 80
				case 'list':
					return 44
				case 'grid':
				default:
					return 64
			}
		},

		/** @spec openspec/specs/people-widget/spec.md */
		showBirthdays() {
			return this.content?.showBirthdays !== false
		},

		/** @spec openspec/specs/people-widget/spec.md */
		birthdayWindowDays() {
			const value = this.content?.birthdayWindowDays
			if (typeof value === 'number' && value >= 0 && value <= 30) {
				return value
			}
			return DEFAULT_CONTENT.birthdayWindowDays
		},

		/**
		 * The trimmed query when it is long enough to ask the server, else ''.
		 *
		 * @spec openspec/specs/people-widget/spec.md
		 */
		serverQuery() {
			const query = (this.search || '').trim()
			return query.length >= MIN_SERVER_QUERY ? query : ''
		},

		/** @spec openspec/specs/people-widget/spec.md */
		canLoadMore() {
			return this.serverQuery ? this.searchHasMore : this.hasMore
		},

		/** @spec openspec/specs/people-widget/spec.md */
		filteredUsers() {
			if (this.serverQuery) {
				return this.searchResults
			}
			if (!this.search) {
				return this.users
			}
			const needle = this.search.toLowerCase()
			return this.users.filter((user) => {
				const name = (user.displayName || '').toLowerCase()
				const email = (user.email || '').toLowerCase()
				return name.includes(needle) || email.includes(needle)
			})
		},

		/** @spec openspec/specs/people-widget/spec.md */
		queryParams() {
			const filters = Array.isArray(this.content?.filters)
				? this.content.filters
				: []
			return {
				filters: JSON.stringify(filters),
				excludeDisabled: this.content?.excludeDisabled === false ? 0 : 1,
				showBirthdays: this.content?.showBirthdays === false ? 0 : 1,
				sortBy: this.content?.sortBy || DEFAULT_CONTENT.sortBy,
			}
		},
	},

	watch: {
		/**
		 * Debounce a server search for the new query; a short query drops the results.
		 *
		 * @param {string} query The new server query, or ''.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		serverQuery(query) {
			clearTimeout(this.searchTimer)
			this.searchResults = []
			this.searchHasMore = false
			if (!query) {
				return
			}
			this.searchTimer = setTimeout(
				() => this.fetchSearch(0),
				SEARCH_DEBOUNCE_MS,
			)
		},

		queryParams: {
			/** @spec openspec/specs/people-widget/spec.md */
			handler() {
				this.cacheKey = ''
				this.cacheStoredAt = 0
				this.users = []
				this.total = 0
				this.hasMore = false
				this.fetchPage(0)
			},

			deep: true,
		},
	},

	mounted() {
		this.fetchPage(0)
	},

	beforeUnmount() {
		clearTimeout(this.searchTimer)
	},

	methods: {
		/**
		 * Nextcloud profile URL for a user.
		 *
		 * @param {string} uid The user id.
		 * @return {string} Path to that user's profile page.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		profileUrl(uid) {
			return `/u/${encodeURIComponent(uid)}`
		},

		/**
		 * Whether a user's card should carry a birthday badge — requires the
		 * widget setting, a known birthdate, and an upcoming date.
		 *
		 * @param {object} user The user record.
		 * @return {boolean} True when the badge should render.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		showBirthdayBadge(user) {
			if (!this.showBirthdays || !user.birthdate) {
				return false
			}
			const days = this.daysToBirthday(user.birthdate)
			if (days === null) {
				return false
			}
			return days >= 0 && days <= this.birthdayWindowDays
		},

		/**
		 * Badge text for an upcoming birthday.
		 *
		 * @param {object} user The user record.
		 * @return {string} Localised "today" or "in {n} days" label.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		formatBirthdayBadge(user) {
			const days = this.daysToBirthday(user.birthdate)
			if (days === 0) {
				return t('launchpad', '🎂 today')
			}
			return t('launchpad', '🎂 in {n} days', { n: days })
		},

		/**
		 * Days until a user's next birthday, ignoring the birth year.
		 *
		 * @param {string} iso Birthdate as `YYYY-MM-DD`.
		 * @return {number|null} Whole days until the next occurrence, or
		 *   null when the input is missing or unparseable.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		daysToBirthday(iso) {
			if (typeof iso !== 'string' || !iso) {
				return null
			}
			const parts = iso.split('-')
			if (parts.length !== 3) {
				return null
			}
			const [, monthStr, dayStr] = parts
			const month = Number(monthStr)
			const day = Number(dayStr)
			if (!Number.isFinite(month) || !Number.isFinite(day)) {
				return null
			}
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			let candidate = new Date(today.getFullYear(), month - 1, day)
			if (candidate < today) {
				candidate = new Date(today.getFullYear() + 1, month - 1, day)
			}
			const diffMs = candidate.getTime() - today.getTime()
			return Math.round(diffMs / (24 * 60 * 60 * 1000))
		},

		/** @spec openspec/specs/people-widget/spec.md */
		async loadMore() {
			if (this.loading || !this.canLoadMore) {
				return
			}
			if (this.serverQuery) {
				await this.fetchSearch(this.searchResults.length)
				return
			}
			await this.fetchPage(this.users.length)
		},

		/**
		 * Whether a profile carries custom fields to show.
		 *
		 * @param {object} user The user record.
		 * @return {boolean} True when there is at least one field.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		hasCustomFields(user) {
			return Array.isArray(user.customFields) && user.customFields.length > 0
		},

		/**
		 * Whether a tag contains the current server query, for highlighting.
		 *
		 * @param {string} tag The tag.
		 * @return {boolean} True when it matches.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		tagMatches(tag) {
			return (
				this.serverQuery !== ''
				&& String(tag).toLowerCase().includes(this.serverQuery.toLowerCase())
			)
		},

		/**
		 * Search for everyone with this tag (REQ-PEX-003).
		 *
		 * @param {string} tag The tag.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		searchTag(tag) {
			this.search = tag
		},

		/**
		 * One page of server search results. Never cached (REQ-PEX-004); a
		 * late answer to an older query is dropped.
		 *
		 * @param {number} offset Zero-based index of the first match.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		async fetchSearch(offset) {
			const query = this.serverQuery
			if (!query) {
				return
			}
			const seq = ++this.searchSeq
			this.loading = true
			this.error = null
			try {
				const data = await this.requestPage({ q: query, offset })
				if (seq !== this.searchSeq) {
					return
				}
				const incoming = Array.isArray(data.users) ? data.users : []
				this.searchResults =
					offset === 0 ? incoming : this.searchResults.concat(incoming)
				this.searchHasMore = data.hasMore === true
			} catch (err) {
				if (seq === this.searchSeq) {
					this.error = err
				}
			} finally {
				if (seq === this.searchSeq) {
					this.loading = false
				}
			}
		},

		/**
		 * GET one page of `/api/people` with the widget's parameters.
		 *
		 * @param {object} extra Extra parameters (`offset`, optional `q`).
		 * @return {Promise<object>} The response body.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		async requestPage(extra) {
			const params = new URLSearchParams()
			Object.entries({
				...this.queryParams,
				limit: PAGE_SIZE,
				...extra,
			}).forEach(([key, value]) => {
				params.append(key, String(value))
			})

			// Lazy import @nextcloud/* helpers — keeps the renderer
			// out of the vitest css-no-op transform path for the
			// majority of tests that don't exercise the network call.
			const [{ default: axios }, { generateUrl }] = await Promise.all([
				import('@nextcloud/axios'),
				import('@nextcloud/router'),
			])

			const url = `${generateUrl('/apps/launchpad/api/people')}?${params.toString()}`
			const response = await axios.get(url)
			return response?.data || {}
		},

		/** @spec openspec/specs/people-widget/spec.md */
		forceRefresh() {
			this.cacheKey = ''
			this.cacheStoredAt = 0
			this.users = []
			this.total = 0
			this.hasMore = false
			this.fetchPage(0)
		},

		/**
		 * Fetch one page of users, serving from the in-memory cache when a
		 * matching entry is younger than `CACHE_TTL_MS` (REQ-PPL-003).
		 *
		 * @param {number} offset Zero-based index of the first user to
		 *   fetch; pages are `PAGE_SIZE` long.
		 * @spec openspec/specs/people-widget/spec.md
		 */
		async fetchPage(offset) {
			const cacheKey = JSON.stringify({ params: this.queryParams, offset })
			const fresh =
				this.cacheKey === cacheKey
				&& Date.now() - this.cacheStoredAt < CACHE_TTL_MS
			if (fresh && this.users.length > 0) {
				return
			}

			this.loading = true
			this.error = null
			try {
				const data = await this.requestPage({ offset })

				const incoming = Array.isArray(data.users) ? data.users : []
				if (offset === 0) {
					this.users = incoming
				} else {
					this.users = this.users.concat(incoming)
				}
				this.total =
					typeof data.total === 'number' ? data.total : this.users.length
				this.hasMore = data.hasMore === true

				this.cacheKey = cacheKey
				this.cacheStoredAt = Date.now()
			} catch (err) {
				this.error = err
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.people-widget {
	display: flex;
	flex-direction: column;
	height: 100%;
	gap: 8px;
	padding: 8px;
	overflow: hidden;
}

.people-widget__header {
	display: flex;
	gap: 6px;
	align-items: center;
	flex: 0 0 auto;
}

.people-widget__search {
	flex: 1 1 auto;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.people-widget__refresh {
	border: 1px solid var(--color-border);
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	padding: 2px 8px;
	cursor: pointer;
	font-size: 14px;
}

.people-widget__items {
	flex: 1 1 auto;
	overflow-y: auto;
	display: grid;
	gap: 8px;
}

.people-widget--list .people-widget__items {
	display: flex;
	flex-direction: column;
}

.people-widget__item {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 6px;
	border-radius: var(--border-radius);
	border: 1px solid transparent;
}

.people-widget__link {
	display: flex;
	gap: 8px;
	align-items: center;
	color: var(--color-main-text);
	text-decoration: none;
	min-width: 0;
}

.people-widget--card .people-widget__item {
	text-align: center;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	min-height: 200px;
}

.people-widget--card .people-widget__link,
.people-widget--grid .people-widget__link {
	flex-direction: column;
	text-align: center;
}

.people-widget__fields {
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.people-widget__tags {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.people-widget--card .people-widget__tags {
	justify-content: center;
}

.people-widget__tag {
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill, 12px);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 12px;
	cursor: pointer;
}

.people-widget__tag--match {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-weight: 600;
}

.people-widget__item:hover {
	background: var(--color-background-hover);
	border-color: var(--color-border);
}

.people-widget__avatar {
	border-radius: 50%;
	object-fit: cover;
	background: var(--color-background-darker);
}

.people-widget__meta {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.people-widget__name {
	font-weight: 600;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 100%;
}

.people-widget__role,
.people-widget__org,
.people-widget__email {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.people-widget__birthday {
	font-size: 12px;
	color: var(--color-warning, #c4a000);
}

.people-widget__state {
	flex: 1 1 auto;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	padding: 16px;
	text-align: center;
}

.people-widget__state--error {
	flex-direction: column;
	gap: 8px;
}

.people-widget__retry,
.people-widget__load-more {
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	cursor: pointer;
	font-size: 13px;
}

.people-widget__footer {
	flex: 0 0 auto;
	display: flex;
	justify-content: center;
	padding-top: 4px;
}
</style>
