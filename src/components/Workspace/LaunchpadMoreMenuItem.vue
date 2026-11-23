<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - LaunchpadMoreMenuItem: one item of the More menu (LaunchpadMoreMenu), as a
  - link (`href`), a router link (`to`) or a button. An item with `checked`
  - is a radio item; a destructive one is drawn in the error colour.
-->
<template>
	<component
		:is="tag"
		v-bind="attributes"
		class="lp-more-menu__item"
		:class="{
			'lp-more-menu__item--checked': item.checked === true,
			'lp-more-menu__item--destructive': item.destructive === true,
		}"
		:role="item.checked === undefined ? 'menuitem' : 'menuitemradio'"
		:aria-checked="item.checked === undefined ? undefined : String(item.checked === true)"
		tabindex="-1"
		:data-testid="`launchpad-more-menu-item-${item.id}`">
		<span class="lp-more-menu__icon" aria-hidden="true">
			<component :is="item.icon" v-if="item.icon" :size="18" />
		</span>
		<span class="lp-more-menu__label">{{ item.label }}</span>
		<span v-if="item.meta" class="lp-more-menu__meta">{{ item.meta }}</span>
	</component>
</template>

<script>
import { REQUIRED_OBJECT_PROP } from '../../constants/propDefinitions.js'

export default {
	name: 'LaunchpadMoreMenuItem',

	props: {
		/**
		 * The item: `{ id, label, icon?, meta?, checked?, destructive?, href?,
		 * target?, to? }`.
		 */
		item: REQUIRED_OBJECT_PROP,
	},

	computed: {
		/**
		 * The element the item renders as.
		 *
		 * @return {string}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		tag() {
			if (this.item.href) {
				return 'a'
			}
			return this.item.to ? 'router-link' : 'button'
		},

		/**
		 * The attributes of the item's element.
		 *
		 * @return {object}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		attributes() {
			if (this.tag === 'a') {
				const target = this.item.target || undefined
				return { href: this.item.href, target, rel: target === '_blank' ? 'noopener noreferrer' : undefined }
			}
			return this.tag === 'router-link' ? { to: this.item.to } : { type: 'button' }
		},
	},
}
</script>
