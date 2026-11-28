<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		class="choice-cards"
		role="radiogroup"
		:aria-labelledby="labelId">
		<span :id="labelId" class="choice-cards__legend">{{ label }}</span>
		<div class="choice-cards__grid" :class="`choice-cards__grid--${columns}`">
			<label
				v-for="option in options"
				:key="option.value"
				class="choice-cards__card"
				:class="{
					'choice-cards__card--chosen': modelValue === option.value,
					'choice-cards__card--disabled': option.disabled,
				}"
				:data-value="option.value">
				<input
					class="choice-cards__input"
					type="radio"
					:name="name"
					:value="option.value"
					:checked="modelValue === option.value"
					:disabled="option.disabled"
					@change="$emit('update:modelValue', option.value)">
				<span class="choice-cards__title">{{ option.label }}</span>
				<span v-if="option.hint" class="choice-cards__hint">{{ option.hint }}</span>
			</label>
		</div>
	</div>
</template>

<script>
import { LIST_PROP, TEXT_PROP } from '../constants/propDefinitions.js'

let choiceCardsCounter = 0

/**
 * ChoiceCards — one choice out of a few, drawn as cards with a title and one
 * line of explanation (the boards' "keuzekaarten"). Native radio inputs keep
 * the keyboard and the accessible names: each card is a radio named by its
 * title and line, the group a radiogroup named by its label.
 *
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
 */
export default {
	name: 'ChoiceCards',

	props: {
		/** The group's label. */
		label: TEXT_PROP,

		/** The radio group's name. */
		name: TEXT_PROP,

		/** The chosen value; '' when none is chosen. */
		modelValue: TEXT_PROP,

		/** `{value, label, hint?, disabled?}` per card. */
		options: LIST_PROP,

		/** Cards per row. */
		columns: { type: Number, default: 2 },
	},

	emits: ['update:modelValue'],

	data() {
		choiceCardsCounter += 1
		return { labelId: `choice-cards-${choiceCardsCounter}` }
	},
}
</script>

<style scoped>
.choice-cards {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.choice-cards__legend {
	font-weight: 600;
	font-size: 14px;
}

.choice-cards__grid {
	display: grid;
	gap: 8px;
}

.choice-cards__grid--2 {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.choice-cards__grid--3 {
	grid-template-columns: repeat(3, minmax(0, 1fr));
}

.choice-cards__grid--4 {
	grid-template-columns: repeat(4, minmax(0, 1fr));
}

@media (max-width: 600px) {
	.choice-cards__grid--3,
	.choice-cards__grid--4 {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

.choice-cards__card {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 10px 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.choice-cards__card--chosen {
	border-color: var(--color-primary-element);
}

.choice-cards__card--disabled {
	cursor: not-allowed;
	opacity: 0.5;
}

.choice-cards__card:focus-within {
	outline: 2px solid var(--color-main-text);
	outline-offset: 2px;
}

.choice-cards__input {
	position: absolute;
	opacity: 0;
	pointer-events: none;
}

.choice-cards__title {
	font-weight: 600;
}

.choice-cards__hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
