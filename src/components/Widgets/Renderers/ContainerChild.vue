<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<component
		:is="childRenderer"
		v-if="childRenderer"
		:content="childContent"
		:placement="placement"
		:edit-mode="editMode"
		v-bind="extraProps" />
	<div v-else class="container-child container-child--unknown">
		<span class="container-child__missing">{{ unknownLabel }}</span>
	</div>
</template>

<script>
import { getWidgetTypeEntry } from '../../../constants/widgetRegistry.js'
import { buildWidgetDataProvide, buildRendererExtraProps } from '../../../services/widgetDataAdapters.js'

/**
 * ContainerChild — registry-driven dispatcher for a single child placement
 * inside a ContainerWidget (REQ-CONT-003).
 *
 * Looks up the placement's `type` in the shared widget registry and
 * mounts the matching `renderer` component, forwarding the placement's
 * `content` blob. Because the `container` widget type is itself a
 * registry entry whose renderer is `ContainerWidget`, this component is
 * naturally recursive: nesting is bounded only by the server-side
 * REQ-CONT-006 max-depth=3 invariant enforced in
 * `WidgetPlacementService::validateContainerDepth`.
 *
 * The dispatcher deliberately stays tiny — no header chrome, no edit
 * affordances of its own — so child widgets render exactly as they
 * would on the top-level grid. It does, however, provide() the same
 * nc-vue data-source adapters as WidgetRenderer (scoped to this child's
 * placement id) so nested data widgets (people/calendar/spend/news/files)
 * work identically inside a container.
 */
export default {
	name: 'ContainerChild',

	/**
	 * Bridge the nc-vue data widgets' injected sources to launchpad's
	 * endpoints, scoped to this child's placement. Without this a nested
	 * data widget would inherit the container's placement id (or none) and
	 * fetch the wrong rows, so "child widgets render via the registry
	 * dispatcher" would hold structurally but not behaviourally.
	 *
	 * @spec openspec/specs/container-widget/spec.md#req-cont-003
	 * @return {object} the injected data-source adapters.
	 */
	provide() {
		return buildWidgetDataProvide(() => this.placement?.id)
	},

	props: {
		placement: {
			type: Object,
			required: true,
		},
		editMode: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		/**
		 * Per-type extra props for nc-vue renderers that take a prop (news
		 * `itemsEndpoint`, files `apiBase`). The container child placement
		 * carries the type in `placement.type`.
		 *
		 * @return {object} extra props to v-bind onto the child renderer.
		 */
		extraProps() {
			return buildRendererExtraProps(this.placement?.type)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		registryEntry() {
			const type = this.placement?.type
			if (typeof type !== 'string' || type === '') {
				return null
			}
			return getWidgetTypeEntry(type)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		childRenderer() {
			return this.registryEntry?.renderer || null
		},

		/** @spec openspec/specs/container-widget/spec.md */
		childContent() {
			return this.placement?.content || {}
		},

		/** @spec openspec/specs/container-widget/spec.md */
		unknownLabel() {
			const type = this.placement?.type || ''
			return t('launchpad', 'Unknown widget type: {type}', { type })
		},
	},
}
</script>

<style scoped>
.container-child {
	width: 100%;
	height: 100%;
}

.container-child--unknown {
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
