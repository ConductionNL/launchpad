<!--
  - SPDX-FileCopyrightText: 2024 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcActions
		:aria-label="t('mydash', 'Dashboard menu')"
		:force-menu="true"
		placement="bottom-end"
		type="secondary">
		<template #icon>
			<Cog :size="20" />
		</template>

		<!-- REQ-ASET-003 (extended): personal-dashboard creation is gated by
		     the admin `allow_user_dashboards` flag. The trigger is hidden
		     when the flag is off so the UI stays in sync with the 403 the
		     backend would return. The flag itself comes from the typed
		     initial-state contract via the root `provide` (REQ-INIT-004).

		     The `runtime-shell-trim` change removed the inline list of
		     dashboards above this entry — the left sidebar (`dashboard-
		     switcher` capability) owns dashboard navigation now. -->
		<NcActionButton
			v-if="allowUserDashboards"
			:close-after-click="true"
			@click="$emit('create-dashboard')">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('mydash', 'Create dashboard…') }}
		</NcActionButton>

		<NcActionSeparator />

		<NcActionButton
			v-if="canEdit"
			:close-after-click="true"
			@click="$emit('toggle-edit')">
			<template #icon>
				<ContentSave v-if="isEditMode" :size="20" />
				<Pencil v-else :size="20" />
			</template>
			{{ isEditMode ? t('mydash', 'Save dashboard') : t('mydash', 'Edit dashboard') }}
		</NcActionButton>
		<NcActionButton
			v-if="isActiveOwner && activeDashboardId"
			:close-after-click="true"
			@click="$emit('open-config')">
			<template #icon>
				<Tune :size="20" />
			</template>
			{{ t('mydash', 'Dashboard configuration…') }}
		</NcActionButton>
		<!--
			REQ-WDG-022 / unified-add-widget-flow: the standalone "Add tile…"
			and "Add widget…" entries were removed in favour of the single
			unified picker below. Tile is now a registry-driven widget type
			alongside label/text/image/link/etc. Custom widget types come
			from widgetRegistry.js — REQ-WDG-014. Only shown when the
			registry has at least one type with a usable form, so the menu
			never offers an option that opens an empty modal.
		-->
		<NcActionButton
			v-if="canEdit && hasCustomWidgetTypes"
			:close-after-click="true"
			@click="$emit('add-custom-widget')">
			<template #icon>
				<ShapePolygonPlus :size="20" />
			</template>
			{{ t('mydash', 'Add custom widget…') }}
		</NcActionButton>

		<NcActionSeparator />

		<!-- The "Powered by Sendent / Conduction" footer was removed by
		     the `runtime-shell-trim` change; it now lives in the left
		     sidebar's footer per `dashboard-switcher-extensions`. -->
		<NcActionLink
			href="https://mydash.app"
			target="_blank"
			rel="noopener noreferrer">
			<template #icon>
				<BookOpenVariantOutline :size="20" />
			</template>
			{{ t('mydash', 'Documentation') }}
		</NcActionLink>
	</NcActions>
</template>

<script>
import {
	NcActions,
	NcActionButton,
	NcActionLink,
	NcActionSeparator,
} from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'

import Cog from 'vue-material-design-icons/Cog.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import ShapePolygonPlus from 'vue-material-design-icons/ShapePolygonPlus.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'

import { listWidgetTypes } from '../constants/widgetRegistry.js'

export default {
	name: 'DashboardConfigMenu',

	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		NcActionSeparator,
		Cog,
		Plus,
		Pencil,
		ContentSave,
		Tune,
		ShapePolygonPlus,
		BookOpenVariantOutline,
	},

	// REQ-INIT-004: read the typed initial-state snapshot via root
	// `provide` (set in `src/main.js`). Default `false` keeps the gating
	// safe even if the value is missing — REQ-ASET-003 says the secure
	// default is "block creation".
	inject: {
		allowUserDashboards: {
			from: 'allowUserDashboards',
			default: false,
		},
	},

	props: {
		activeDashboardId: {
			type: [Number, String],
			default: null,
		},
		isEditMode: {
			type: Boolean,
			default: false,
		},
		canEdit: {
			type: Boolean,
			default: true,
		},
		isActiveOwner: {
			type: Boolean,
			default: true,
		},
	},

	emits: [
		'create-dashboard',
		'toggle-edit',
		'open-config',
		'add-custom-widget',
	],

	computed: {
		/**
		 * Whether the registry has at least one custom widget type with a
		 * usable form. Hides the "Add custom widget…" entry when no
		 * per-type sub-form is registered yet (REQ-WDG-014 — the menu is
		 * registry-driven and never offers an option that would open an
		 * empty modal).
		 *
		 * @return {boolean}
		 */
		hasCustomWidgetTypes() {
			return listWidgetTypes().length > 0
		},
	},

	methods: {
		t,
	},
}
</script>
