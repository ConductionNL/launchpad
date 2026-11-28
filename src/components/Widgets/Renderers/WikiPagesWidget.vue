<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="wiki-pages-widget" data-testid="wiki-pages-widget">
		<p
			v-if="state === 'not-connected' || state === 'unavailable'"
			class="wiki-pages-widget__state"
			data-testid="wiki-pages-not-connected">
			{{ state === 'not-connected'
				? t('launchpad', 'The wiki is not connected yet. Ask your administrator to connect XWiki.')
				: t('launchpad', 'The wiki needs OpenRegister. Ask your administrator to enable it.') }}
		</p>

		<template v-else>
			<NcTextField
				:modelValue="query"
				:label="t('launchpad', 'Search the wiki')"
				type="search"
				:showTrailingButton="query !== ''"
				trailingButtonIcon="close"
				data-testid="wiki-pages-search"
				@update:modelValue="onQuery"
				@trailingButtonClick="onQuery('')" />

			<section
				v-for="section in sections"
				:key="section.key"
				class="wiki-pages-widget__section"
				:data-testid="`wiki-pages-${section.key}`">
				<h3 class="wiki-pages-widget__heading">
					{{ section.title }}
				</h3>
				<CnStatusList
					:items="items(section.pages)"
					:loading="section.loading"
					:emptyLabel="section.emptyLabel">
					<template #actions="{ item }">
						<NcButton
							variant="tertiary"
							:aria-label="favouriteLabel(item)"
							:pressed="isFavourite(item.id)"
							@click="toggleFavourite(item.id)">
							<template #icon>
								<Star v-if="isFavourite(item.id)" :size="18" />
								<StarOutline v-else :size="18" />
							</template>
						</NcButton>
					</template>
				</CnStatusList>
			</section>
		</template>
	</div>
</template>

<script>
import { CnStatusList } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { readWikiFavourites, saveWikiFavourites, searchWikiPages } from '../../../services/wikiPages.js'
import { logger } from '../../../utils/logger.js'

/** How long the search waits after the last key before it asks the wiki. */
const SEARCH_DELAY_MS = 300

/**
 * WikiPagesWidget: the `wiki-pages` widget (board LpWerkplek "Wiki"). A
 * search field over the wiki, the person's favourite pages and the most
 * recent pages, each a link into the wiki. The pages come from
 * OpenRegister's XWiki integration, so the wiki's address and credentials
 * stay on the server; the favourites are the person's own.
 *
 * `content`: `{ space, limit }`. `space` keeps only the pages of one
 * wiki space ("Vergunningen"); `limit` is how many favourites and recent
 * pages show (default 3).
 *
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md
 */
export default {
	name: 'WikiPagesWidget',

	components: { CnStatusList, NcButton, NcTextField, Star, StarOutline },

	props: {
		/**
		 * The widget is a miniature in Add widget (REQ-WDG-048): it draws
		 * its sample content and sends no request.
		 */
		preview: { type: Boolean, default: false },

		/** Persisted widget content: `{ space, limit }`. */
		content: OBJECT_PROP,

		/** The placement row; declared so it does not fall through as an attribute. */
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,
	},

	data() {
		return {
			state: 'loading',
			recent: [],
			favourites: [],
			query: '',
			results: [],
			searching: false,
			searchTimer: null,
		}
	},

	computed: {
		/**
		 * The lists the widget shows: the search results while a search runs,
		 * else the favourites and the recent pages.
		 *
		 * @return {Array<{key: string, title: string, pages: Array<object>, loading: boolean, emptyLabel: string}>}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
		 */
		sections() {
			if (this.query.trim() !== '') {
				return [{ key: 'results', title: t('launchpad', 'Search results'), pages: this.results, loading: this.searching, emptyLabel: t('launchpad', 'No page matches your search') }]
			}
			return [
				{ key: 'favourites', title: t('launchpad', 'Favourites'), pages: this.shownFavourites, loading: false, emptyLabel: t('launchpad', 'Star a page to keep it here') },
				{ key: 'recent', title: t('launchpad', 'Recent'), pages: this.recent, loading: this.state === 'loading', emptyLabel: t('launchpad', 'No pages yet') },
			]
		},

		/**
		 * @return {number} How many favourites and recent pages show.
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
		 */
		limit() {
			const limit = Number(this.content?.limit)
			return Number.isInteger(limit) && limit > 0 ? Math.min(limit, 20) : 3
		},

		/**
		 * @return {string} The one space to show, '' for every space.
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
		 */
		space() {
			return String(this.content?.space || '').trim()
		},

		/**
		 * @return {Array<object>} The favourites in this widget's space, at most `limit`.
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-person-keeps-favourite-pages-req-wiki-002
		 */
		shownFavourites() {
			return this.inSpace(this.favourites).slice(0, this.limit)
		},
	},

	/**
	 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
	 * @spec openspec/changes/archive/2026-11-27-add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
	 */
	async created() {
		if (this.preview) {
			return
		}
		const [recent, favourites] = await Promise.all([
			searchWikiPages('', this.space ? 50 : this.limit),
			readWikiFavourites(),
		])
		this.favourites = favourites
		this.recent = this.inSpace(recent.pages).slice(0, this.limit)
		this.state = recent.state
	},

	beforeUnmount() {
		clearTimeout(this.searchTimer)
	},

	methods: {
		t,

		/**
		 * The pages of this widget's space; every page without one.
		 *
		 * @param {Array<object>} pages The pages.
		 * @return {Array<object>}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
		 */
		inSpace(pages) {
			return this.space === '' ? pages : pages.filter((page) => page.space === this.space)
		},

		/**
		 * Pages as list items: a link into the wiki, opening in a new tab, with the space as detail.
		 *
		 * @param {Array<object>} pages The pages.
		 * @return {Array<object>}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
		 */
		items(pages) {
			return pages.map((page) => ({
				id: page.reference,
				title: page.title,
				href: page.url,
				external: true,
				meta: page.space,
			}))
		},

		/**
		 * Search the wiki once typing pauses.
		 *
		 * @param {string} value The search text.
		 * @return {void}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
		 */
		onQuery(value) {
			this.query = String(value ?? '')
			clearTimeout(this.searchTimer)
			if (this.query.trim() === '') {
				this.results = []
				this.searching = false
				return
			}
			this.searching = true
			this.searchTimer = setTimeout(() => this.search(this.query.trim()), SEARCH_DELAY_MS)
		},

		/**
		 * Ask the wiki for pages matching a query; a later query wins.
		 *
		 * @param {string} query The search text.
		 * @return {Promise<void>}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
		 */
		async search(query) {
			const found = await searchWikiPages(query, 10)
			if (this.query.trim() !== query) {
				return
			}
			this.results = this.inSpace(found.pages)
			this.searching = false
		},

		/**
		 * @param {string} reference The page reference.
		 * @return {boolean} Whether the page is a favourite.
		 */
		isFavourite(reference) {
			return this.favourites.some((page) => page.reference === reference)
		},

		/**
		 * The star button's name for a page.
		 *
		 * @param {{id: string, title: string}} item The list item.
		 * @return {string}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-person-keeps-favourite-pages-req-wiki-002
		 */
		favouriteLabel(item) {
			return this.isFavourite(item.id)
				? t('launchpad', 'Remove {title} from favourites', { title: item.title })
				: t('launchpad', 'Add {title} to favourites', { title: item.title })
		},

		/**
		 * Star or unstar a page; a new favourite comes first.
		 *
		 * @param {string} reference The page reference.
		 * @return {Promise<void>}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-person-keeps-favourite-pages-req-wiki-002
		 */
		async toggleFavourite(reference) {
			const before = this.favourites
			if (this.isFavourite(reference)) {
				this.favourites = before.filter((page) => page.reference !== reference)
			} else {
				const page = [...this.results, ...this.recent].find((entry) => entry.reference === reference)
				if (!page) {
					return
				}
				this.favourites = [page, ...before]
			}
			try {
				this.favourites = await saveWikiFavourites(this.favourites)
			} catch (error) {
				logger.error('The wiki favourites could not be saved', { error })
				this.favourites = before
			}
		},
	},
}
</script>

<style scoped>
.wiki-pages-widget {
	display: flex;
	flex-direction: column;
	gap: 12px;
	height: 100%;
	min-height: 0;
	overflow-y: auto;
}

.wiki-pages-widget__section {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.wiki-pages-widget__heading {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.wiki-pages-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
