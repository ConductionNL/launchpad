<?php

/**
 * OfficeNetworksController
 *
 * Administrator endpoints for the office network ranges
 * (launcher-tile-internal-address, REQ-TIA-001). The answer also names the
 * administrator's own address and whether it is on the office network, so
 * the setting can be checked from where it is made.
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

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\OfficeNetworkService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Office network administration.
 *
 * @spec openspec/specs/tiles/spec.md
 */
class OfficeNetworksController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param OfficeNetworkService $officeNetworks Office network service.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param IGroupManager $groupManager Admin check.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly OfficeNetworkService $officeNetworks,
		private readonly ActionAuthService $actionAuth,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * The ranges and the caller's own address (administrators).
	 *
	 * @return JSONResponse `{ranges, currentAddress, onOfficeNetwork}`, 401 or 403.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$this->actionAuth->requireAction($user, 'office-network.list');
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(data: $this->state(ranges: $this->officeNetworks->getRanges()));
	}//end index()

	/**
	 * Replace the ranges (administrators).
	 *
	 * @param array|null $ranges IPv4 or IPv6 ranges in CIDR notation.
	 *
	 * @return JSONResponse `{ranges, currentAddress, onOfficeNetwork}`, 400, 401 or 403.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function save(?array $ranges = null): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$this->actionAuth->requireAction($user, 'office-network.save');
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$saved = $this->officeNetworks->saveRanges(raw: ($ranges ?? []));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: $this->state(ranges: $saved));
	}//end save()

	/**
	 * The response body.
	 *
	 * @param string[] $ranges The ranges.
	 *
	 * @return array{ranges: string[], currentAddress: string, onOfficeNetwork: bool}
	 */
	private function state(array $ranges): array {
		return [
			'ranges' => $ranges,
			'currentAddress' => $this->officeNetworks->currentAddress(),
			'onOfficeNetwork' => $this->officeNetworks->isOfficeRequest(),
		];
	}//end state()
}//end class
