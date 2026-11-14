<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="communities-widget" data-testid="communities-widget">
		<h3 v-if="title" class="communities-widget__title">
			{{ title }}
		</h3>

		<p
			v-if="state === 'no-talk'"
			class="communities-widget__state"
			data-testid="communities-no-talk">
			{{
				t(
					'launchpad',
					'Communities need Nextcloud Talk. Ask your administrator to enable it.',
				)
			}}
		</p>

		<p
			v-else-if="state === 'loading'"
			class="communities-widget__state"
			data-testid="communities-loading">
			{{ t('launchpad', 'Loading communities…') }}
		</p>

		<p
			v-else-if="state === 'error'"
			class="communities-widget__state communities-widget__state--failed"
			role="alert"
			data-testid="communities-error">
			{{
				t(
					'launchpad',
					'The communities could not be loaded. Reload the page to try again.',
				)
			}}
		</p>

		<p
			v-else-if="state === 'empty'"
			class="communities-widget__state"
			data-testid="communities-empty">
			{{
				onlyPinned
					? t('launchpad', 'Your organisation has not pinned a community you can see yet.')
					: t('launchpad', 'There are no open communities to join yet.')
			}}
		</p>

		<ul v-else class="communities-widget__list" data-testid="communities-list">
			<li
				v-for="community in visibleCommunities"
				:key="community.token"
				class="communities-widget__item"
				:data-testid="`community-${community.token}`">
				<span class="communities-widget__text">
					<strong class="communities-widget__name">{{ community.name }}</strong>
					<span
						v-if="community.pinned"
						class="communities-widget__pinned"
						data-testid="community-pinned">
						{{ t('launchpad', 'Pinned by your organisation') }}
					</span>
					<span v-if="community.description" class="communities-widget__description">
						{{ community.description }}
					</span>
					<span
						v-if="community.members !== null"
						class="communities-widget__members">
						{{
							n(
								'launchpad',
								'%n member',
								'%n members',
								community.members,
							)
						}}
					</span>
				</span>

				<a
					v-if="community.joined"
					class="communities-widget__action button-vue"
					:href="openUrl(community.token)"
					data-testid="community-open">
					{{ t('launchpad', 'Open') }}
				</a>
				<NcButton
					v-else
					variant="primary"
					:disabled="joining === community.token"
					:aria-label="t('launchpad', 'Join {name}', { name: community.name })"
					data-testid="community-join"
					@click="join(community)">
					{{ t('launchpad', 'Join') }}
				</NcButton>
			</li>
		</ul>

		<p
			v-if="joinError"
			class="communities-widget__state communities-widget__state--failed"
			role="alert"
			data-testid="communities-join-error">
			{{ joinError }}
		</p>
	</div>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import {
	communityUrl,
	joinCommunity,
	loadCommunities,
} from '../../../services/talkCommunities.js'
import { logger } from '../../../utils/logger.js'

/**
 * CommunitiesWidget: the open Talk conversations the viewer may join, with
 * the organisation's pinned ones first (openspec/changes/engagement-communities).
 *
 * Talk is called from the browser as the viewer, so Talk decides what each
 * person sees and may join. Without Talk the widget says so and calls nothing.
 */
export default {
	name: 'CommunitiesWidget',

	components: { NcButton },

	inject: {
		/** Whether Talk is enabled for the viewer, from the initial state (REQ-COMM-004). */
		talkEnabled: { from: 'talkEnabled', default: false },
		/** The tokens the organisation pinned, in the order pinned (REQ-COMM-003). */
		pinnedCommunities: { from: 'pinnedCommunities', default: () => [] },
	},

	props: {
		/** Persisted widget content: `{ title, onlyPinned }`. */
		content: OBJECT_PROP,

		/**
		 * The placement row this widget renders as. Declared so the binding
		 * `WidgetRenderer` passes every renderer does not fall through as an
		 * attribute; the widget reads nothing from it.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,
	},

	data() {
		return {
			loading: true,
			loadFailed: false,
			communities: [],
			joining: '',
			joinError: '',
		}
	},

	computed: {
		/** @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003 */
		title() {
			return String(this.content?.title || '').trim()
		},

		/** @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003 */
		onlyPinned() {
			return this.content?.onlyPinned === true
		},

		/**
		 * The communities shown: every one, or only the pinned ones.
		 *
		 * @return {Array<object>} The communities.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
		 */
		visibleCommunities() {
			return this.onlyPinned
				? this.communities.filter((community) => community.pinned)
				: this.communities
		},

		/**
		 * Which state the widget is in.
		 *
		 * @return {string} `no-talk`, `loading`, `error`, `empty` or `list`.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-without-talk-the-widget-explains-itself-req-comm-004
		 */
		state() {
			if (this.talkEnabled !== true) {
				return 'no-talk'
			}
			if (this.loading) {
				return 'loading'
			}
			if (this.loadFailed) {
				return 'error'
			}
			return this.visibleCommunities.length === 0 ? 'empty' : 'list'
		},
	},

	/** @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-without-talk-the-widget-explains-itself-req-comm-004 */
	async created() {
		if (this.talkEnabled !== true) {
			this.loading = false
			return
		}
		await this.load()
	},

	methods: {
		t,
		n,

		/**
		 * Load the communities from Talk.
		 *
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-the-communities-widget-lists-open-talk-conversations-the-viewer-may-join-req-comm-001
		 */
		async load() {
			this.loading = true
			this.loadFailed = false
			try {
				this.communities = await loadCommunities(this.pinnedCommunities)
			} catch (error) {
				logger.error('Communities could not be loaded from Talk', { error })
				this.loadFailed = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * Join a community; on success its button becomes "Open".
		 *
		 * @param {object} community The community.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-one-click-joins-a-community-req-comm-002
		 */
		async join(community) {
			this.joining = community.token
			this.joinError = ''
			try {
				await joinCommunity(community.token)
				this.communities = this.communities.map((entry) =>
					entry.token === community.token ? { ...entry, joined: true } : entry,
				)
			} catch (error) {
				logger.error('Joining a Talk community failed', { error })
				this.joinError = t('launchpad', 'You could not join {name}. Try again later.', {
					name: community.name,
				})
			} finally {
				this.joining = ''
			}
		},

		/**
		 * The Talk page of a community.
		 *
		 * @param {string} token The conversation token.
		 * @return {string} The URL.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-one-click-joins-a-community-req-comm-002
		 */
		openUrl(token) {
			return communityUrl(token)
		},
	},
}
</script>

<style scoped>
.communities-widget {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	overflow: auto;
}

.communities-widget__title {
	margin: 0;
	font-size: 1.1em;
}

.communities-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.communities-widget__state--failed {
	color: var(--color-error-text, var(--color-error));
}

.communities-widget__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.communities-widget__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 8px;
	border-radius: var(--border-radius-large, var(--border-radius));
	background: var(--color-background-hover);
}

.communities-widget__text {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.communities-widget__pinned {
	font-size: 0.85em;
	color: var(--color-primary-element-text, var(--color-main-text));
}

.communities-widget__description,
.communities-widget__members {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.communities-widget__action {
	flex-shrink: 0;
	padding: 6px 14px;
	border-radius: var(--border-radius-element, var(--border-radius-large));
	border: 1px solid var(--color-border-dark);
	color: var(--color-main-text);
	text-decoration: none;
}
</style>
