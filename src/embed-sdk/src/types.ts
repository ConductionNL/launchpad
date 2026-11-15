/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Type definitions of @launchpad/embed-sdk (embedded-analytics task 19).
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

export type SubjectType = 'widget' | 'dashboard'

export interface EmbedConfig {
	/** Where LaunchPad lives, e.g. `https://cloud.example.nl/index.php/apps/launchpad`. */
	baseUrl: string
	/** The embed token (JWT) issued for this subject. */
	token: string
	subjectType: SubjectType
	subjectId: string
	/** The iframe's accessible title. */
	title?: string
	/** `small`, `medium`, `large` or a width, for usage statistics. */
	viewport?: string
	/** Milliseconds to wait for the embed to answer (default 10000). */
	timeout?: number
}

export type EmbedEventType = 'filterApplied' | 'drillDown' | 'export' | 'error' | 'resized'

export interface EmbedError {
	error: string
	message: string
}

export type EmbedEventHandler = (payload: unknown) => void

export interface EmbedInstance {
	readonly iframe: HTMLIFrameElement
	on(eventType: EmbedEventType | string, handler: EmbedEventHandler): EmbedInstance
	off(eventType: EmbedEventType | string, handler?: EmbedEventHandler): EmbedInstance
	resize(): Promise<{ width: number, height: number }>
	destroy(): void
}

export interface LaunchPadEmbedApi {
	render(container: HTMLElement, config: EmbedConfig): Promise<EmbedInstance>
}
