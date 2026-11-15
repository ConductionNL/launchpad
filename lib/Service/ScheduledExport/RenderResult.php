<?php

/**
 * RenderResult
 *
 * One rendered artefact (REQ-SCH-003): its format, size, sha256, where it
 * was stored, and its bytes for channels that attach or upload it.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

/**
 * A rendered artefact.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
 */
final class RenderResult {

	private const MIME = [
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'csv' => 'text/csv',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	];

	/**
	 * Constructor.
	 *
	 * @param string      $format      `pdf`, `png`, `csv` or `xlsx`.
	 * @param string      $filename    The file name from the target's pattern.
	 * @param string      $content     The bytes.
	 * @param string|null $storagePath Where it was written in Files, or null for a preview.
	 */
	public function __construct(
		public readonly string $format,
		public readonly string $filename,
		public readonly string $content,
		public readonly ?string $storagePath = null,
	) {
	}//end __construct()

	/**
	 * The hex sha256 of the bytes.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function sha256(): string {
		return hash('sha256', $this->content);
	}//end sha256()

	/**
	 * The media type of the format.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function mimeType(): string {
		return self::MIME[$this->format] ?? 'application/octet-stream';
	}//end mimeType()

	/**
	 * The artefact as the run-row records it.
	 *
	 * @return array{format: string, sizeBytes: int, sha256: string, storagePath: string|null}
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function toAudit(): array {
		return [
			'format' => $this->format,
			'sizeBytes' => strlen($this->content),
			'sha256' => $this->sha256(),
			'storagePath' => $this->storagePath,
		];
	}//end toAudit()
}//end class
