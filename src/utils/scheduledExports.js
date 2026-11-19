/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared presentation helpers for the scheduled-exports screens: labels for
 * the enums the API answers with, dates and sizes in the viewer's locale,
 * the message of a refused request and the Files address of an artefact.
 */

import { t } from '@nextcloud/l10n'
import { generateRemoteUrl } from '@nextcloud/router'

/** The render formats the API accepts, in menu order. */
export const EXPORT_FORMATS = ['pdf', 'png', 'csv', 'xlsx']

/** The delivery channels the API accepts, in menu order. */
export const EXPORT_CHANNELS = ['email', 'nextcloudFiles', 'webhook', 'sftp']

/**
 * The label of a render format.
 *
 * @param {string} format pdf, png, csv or xlsx.
 * @return {string} The label.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
 */
export function formatLabel(format) {
	return (
		{
			pdf: t('launchpad', 'PDF document'),
			png: t('launchpad', 'PNG image'),
			csv: t('launchpad', 'CSV table'),
			xlsx: t('launchpad', 'Excel workbook (XLSX)'),
		}[format] ?? String(format ?? '')
	)
}

/**
 * The label of a delivery channel.
 *
 * @param {string} channel email, nextcloudFiles, webhook or sftp.
 * @return {string} The label.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
 */
export function channelLabel(channel) {
	return (
		{
			email: t('launchpad', 'Email'),
			nextcloudFiles: t('launchpad', 'Nextcloud Files'),
			webhook: t('launchpad', 'Webhook'),
			sftp: t('launchpad', 'SFTP'),
		}[channel] ?? String(channel ?? '')
	)
}

/**
 * The label of a run status or a delivery status.
 *
 * @param {string} status running, success, partial, failed, pending, retrying or failed-permanent.
 * @return {string} The label.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
 */
export function statusLabel(status) {
	return (
		{
			running: t('launchpad', 'Running'),
			success: t('launchpad', 'Succeeded'),
			partial: t('launchpad', 'Partly failed'),
			failed: t('launchpad', 'Failed'),
			pending: t('launchpad', 'Waiting'),
			retrying: t('launchpad', 'Waiting for a retry'),
			'failed-permanent': t('launchpad', 'Failed for good'),
		}[status] ?? t('launchpad', 'Not run yet')
	)
}

/**
 * A short label for a recipient: its channel and where it delivers to.
 *
 * @param {object} recipient The recipient.
 * @param {number} index Its position, used when it has no address.
 * @return {string} The label.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
 */
export function recipientLabel(recipient, index) {
	const config = recipient?.channelConfig ?? {}
	const target = {
		email: Array.isArray(config.addresses)
			? config.addresses.join(', ')
			: config.to,
		nextcloudFiles: config.targetPath,
		webhook: config.url,
		sftp: config.host,
	}[recipient?.channel]
	const where =
		target || t('launchpad', 'Recipient {number}', { number: index + 1 })
	return `${channelLabel(recipient?.channel)}: ${where}`
}

/**
 * The id the API uses for a recipient: its `id`, or its position.
 *
 * @param {object} recipient The recipient.
 * @param {number} index Its position.
 * @return {string} The id.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
 */
export function recipientId(recipient, index) {
	return String(recipient?.id ?? index)
}

/**
 * A stored timestamp in the viewer's locale.
 *
 * @param {string|null} value An ISO 8601 timestamp.
 * @return {string} The date and time, '' for none, or the raw value when unreadable.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
 */
export function formatDateTime(value) {
	if (!value) {
		return ''
	}
	const parsed = new Date(value)
	if (Number.isNaN(parsed.getTime())) {
		return String(value)
	}
	return parsed.toLocaleString(undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	})
}

/**
 * A byte count as B, KB or MB.
 *
 * @param {number} bytes The size.
 * @return {string} The size with its unit.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
 */
export function formatBytes(bytes) {
	const size = Number(bytes) || 0
	if (size < 1024) {
		return `${size} B`
	}
	if (size < 1024 * 1024) {
		return `${(size / 1024).toFixed(1)} KB`
	}
	return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * The WebDAV address of a file the export wrote, from its node path
 * (`/{user}/files/Apps/LaunchPad/Exports/...`).
 *
 * @param {string|null} storagePath The node path; null once retention removed the file.
 * @return {string} The download address, or '' when there is no file.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
 */
export function artefactDownloadUrl(storagePath) {
	const match = /^\/([^/]+)\/files\/(.+)$/.exec(String(storagePath ?? ''))
	if (!match) {
		return ''
	}
	const path = match[2].split('/').map(encodeURIComponent).join('/')
	return `${generateRemoteUrl('dav')}/files/${encodeURIComponent(match[1])}/${path}`
}

/**
 * The reason the server gave for refusing a request, or a fallback. A
 * binary request (the preview) carries its JSON error in a Blob.
 *
 * @param {Error} error The failed request.
 * @param {string} fallback What to say when the server gave no reason.
 * @return {Promise<string>} The message.
 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
 */
export async function serverMessage(error, fallback) {
	let data = error?.response?.data
	if (data && typeof data.text === 'function') {
		try {
			data = JSON.parse(await data.text())
		} catch {
			data = null
		}
	}
	if (data && typeof data === 'object') {
		return data.message || data.error || fallback
	}
	return fallback
}
