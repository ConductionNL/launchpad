<?php

/**
 * AcknowledgementController
 *
 * Controller for the mandatory-read acknowledgement endpoints —
 * REQ-ACK-002..006. Owns:
 *   - POST /api/acknowledgements                       (idempotent, own-user)
 *   - GET  /api/acknowledgements/pending               (current user)
 *   - GET  /api/acknowledgements/report/{announcementKey}      (admin/owner)
 *   - GET  /api/acknowledgements/report/{announcementKey}/csv  (admin/owner)
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Mandatory-read acknowledgement endpoints.
 *
 * The write + pending endpoints are `#[NoAdminRequired]` — any authenticated
 * user acts only on their own receipts (own-user enforced in the body). The
 * report endpoints additionally gate on admin / template-owner in the method
 * body (REQ-ACK-004, ADR-005).
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
class AcknowledgementController extends Controller {
	/**
	 * Constructor
	 *
	 * @param IRequest             $request The request.
	 * @param AcknowledgementTools $tools The acknowledgement and role services and the logger.
	 * @param IGroupManager        $groupManager NC admin check.
	 * @param string|null          $userId Acting user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly AcknowledgementTools $tools,
		private readonly IGroupManager $groupManager,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * POST /api/acknowledgements — record the calling user's receipt.
	 * Idempotent (REQ-ACK-003). A body `userId` that names another user is
	 * rejected with 403 (no IDOR, ADR-005 / REQ-ACK-003).
	 *
	 * @return JSONResponse The stored receipt.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	#[NoAdminRequired]
	public function acknowledge(): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		$announcementKey = (string)$this->request->getParam(key: 'announcementKey', default: '');
		if ($announcementKey === '') {
			return ResponseHelper::failure(message: 'announcementKey is required', statusCode: Http::STATUS_BAD_REQUEST);
		}

		// Reject any attempt to acknowledge on behalf of another user
		// (REQ-ACK-003 scenario "A user cannot acknowledge on behalf of
		// another user").
		$bodyUserId = $this->request->getParam(key: 'userId');
		if ($bodyUserId !== null && (string)$bodyUserId !== $this->userId) {
			return ResponseHelper::forbidden(
				message: 'Cannot acknowledge on behalf of another user'
			);
		}

		$denied = $this->denyOutsideAudience(announcementKey: $announcementKey, userId: $this->userId);
		if ($denied !== null) {
			return $denied;
		}

		$contentVersion = (int)$this->request->getParam(key: 'contentVersion', default: 1);
		if ($contentVersion < 1) {
			$contentVersion = 1;
		}

		$invalid = $this->rejectUnpublishedVersion(announcementKey: $announcementKey, contentVersion: $contentVersion);
		if ($invalid !== null) {
			return $invalid;
		}

		try {
			$receipt = $this->tools->acknowledgements->acknowledge(
				announcementKey: $announcementKey,
				userId: $this->userId,
				contentVersion: $contentVersion
			);
		} catch (Throwable $e) {
			$this->tools->logger->error(
				message: 'acknowledge failed: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
			return ResponseHelper::failure(message: 'Operation failed', statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return ResponseHelper::success(data: $receipt->jsonSerialize());
	}//end acknowledge()

	/**
	 * Refuse a user outside the announcement's live audience (REQ-ACK-003,
	 * ADR-005). An unknown announcement has no audience.
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId          The session user.
	 *
	 * @return JSONResponse|null The 403, or null when the user may acknowledge.
	 */
	private function denyOutsideAudience(string $announcementKey, string $userId): ?JSONResponse {
		if ($this->tools->acknowledgements->isAllowedToAcknowledge(announcementKey: $announcementKey, userId: $userId) === false) {
			return ResponseHelper::forbidden(
				message: 'This announcement is not addressed to you'
			);
		}

		return null;
	}//end denyOutsideAudience()

	/**
	 * Refuse a content version newer than the announcement's current one, so
	 * no receipt can exist for content that was not published yet
	 * (REQ-ACK-005). An older version stays accepted: a copy that has not
	 * been re-synced yet still shows it.
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param int    $contentVersion  The version from the request.
	 *
	 * @return JSONResponse|null The 400, or null when the version is published.
	 */
	private function rejectUnpublishedVersion(string $announcementKey, int $contentVersion): ?JSONResponse {
		$current = $this->tools->acknowledgements->currentContentVersion(announcementKey: $announcementKey);
		if ($current === null || $contentVersion > $current) {
			return ResponseHelper::invalidArgument(
				message: 'contentVersion is not a published version of this announcement'
			);
		}

		return null;
	}//end rejectUnpublishedVersion()

	/**
	 * GET /api/acknowledgements/pending — the current user's outstanding
	 * mandatory items and count. REQ-ACK-002.
	 *
	 * @return JSONResponse The `{count, items}` payload.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	#[NoAdminRequired]
	public function pending(): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		try {
			$result = $this->tools->acknowledgements->getPending(userId: $this->userId);
		} catch (Throwable $e) {
			$this->tools->logger->error(
				message: 'pending failed: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
			return ResponseHelper::failure(message: 'Operation failed', statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return ResponseHelper::success(data: $result);
	}//end pending()

	/**
	 * GET /api/acknowledgements/report/{announcementKey} — the
	 * audience-scoped read-receipt report. Admin / template owner only
	 * (REQ-ACK-004).
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return JSONResponse The report payload or 403 / 404.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	#[NoAdminRequired]
	public function report(string $announcementKey): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		if ($this->isManager(announcementKey: $announcementKey) === false) {
			return ResponseHelper::forbidden(
				message: 'Only an admin or the template owner may read this report'
			);
		}

		try {
			$report = $this->tools->acknowledgements->report(
				announcementKey: $announcementKey
			);
		} catch (DoesNotExistException) {
			return ResponseHelper::failure(message: 'Unknown announcement', statusCode: Http::STATUS_NOT_FOUND);
		} catch (Throwable $e) {
			$this->tools->logger->error(
				message: 'report failed: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
			return ResponseHelper::failure(message: 'Operation failed', statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return ResponseHelper::success(data: $report);
	}//end report()

	/**
	 * GET /api/acknowledgements/report/{announcementKey}/csv — the report
	 * as a downloadable CSV compliance file. Admin / template owner only
	 * (REQ-ACK-004 / REQ-ACK-006).
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return DataDownloadResponse|JSONResponse The CSV download or 403 / 404.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function reportCsv(string $announcementKey) {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		if ($this->isManager(announcementKey: $announcementKey) === false) {
			return ResponseHelper::forbidden(
				message: 'Only an admin or the template owner may export this report'
			);
		}

		try {
			$report = $this->tools->acknowledgements->report(
				announcementKey: $announcementKey
			);
		} catch (DoesNotExistException) {
			return ResponseHelper::failure(message: 'Unknown announcement', statusCode: Http::STATUS_NOT_FOUND);
		} catch (Throwable $e) {
			$this->tools->logger->error(
				message: 'reportCsv failed: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
			return ResponseHelper::failure(message: 'Operation failed', statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$csv = $this->buildCsv(report: $report);

		return new DataDownloadResponse(
			data: $csv,
			filename: 'acknowledgement-report-' . $announcementKey . '.csv',
			contentType: 'text/csv'
		);
	}//end reportCsv()

	/**
	 * Build the CSV body from a report payload — one row per audience
	 * member with status and (for acknowledged rows) timestamp.
	 * REQ-ACK-006.
	 *
	 * @param array $report The report payload from the service.
	 *
	 * @return string The CSV text.
	 */
	private function buildCsv(array $report): string {
		$lines = [];
		$lines[] = 'user_id,status,acknowledged_at';
		foreach (($report['rows'] ?? []) as $row) {
			$lines[] = implode(
				separator: ',',
				array: [
					$this->csvField(value: (string)($row['userId'] ?? '')),
					$this->csvField(value: (string)($row['status'] ?? '')),
					$this->csvField(value: (string)($row['acknowledgedAt'] ?? '')),
				]
			);
		}

		return implode(separator: "\r\n", array: $lines) . "\r\n";
	}//end buildCsv()

	/**
	 * Quote and escape a single CSV field per RFC 4180.
	 *
	 * @param string $value The raw field value.
	 *
	 * @return string The quoted field.
	 */
	private function csvField(string $value): string {
		return '"' . str_replace(search: '"', replace: '""', subject: $value) . '"';
	}//end csvField()

	/**
	 * Whether the current user may manage (report on) the announcement —
	 * a Nextcloud admin, a LaunchPad admin, or the announcement's template
	 * owner (REQ-ACK-004, design "Authorization").
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return bool True when authorized.
	 */
	private function isManager(string $announcementKey): bool {
		if ($this->userId === null) {
			return false;
		}

		if ($this->groupManager->isAdmin(userId: $this->userId) === true) {
			return true;
		}

		if ($this->tools->roleService->isAdmin(userId: $this->userId) === true) {
			return true;
		}

		$owner = $this->tools->acknowledgements->resolveOwnerUserId(
			announcementKey: $announcementKey
		);

		return $owner !== null && $owner === $this->userId;
	}//end isManager()
}//end class
