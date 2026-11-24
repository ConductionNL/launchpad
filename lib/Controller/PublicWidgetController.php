<?php

/**
 * PublicWidgetController
 *
 * Anonymous reads of a live widget's data on a public link or a kiosk
 * screen (REQ-PSHR-011):
 *
 * - `GET /s/{token}/placements/{placementId}`: through a public share token,
 *   behind the share's password gate (the X-Share-Password header).
 * - `GET /kiosk/{token}/placements/{placementId}`: through a kiosk playlist token.
 *
 * Each answers for one news, weather or live data placement on a dashboard
 * the token shows, else 404.
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
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Closure;
use Exception;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\PublicWidgetReadService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Live widget data for anonymous visitors of a shared dashboard.
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
class PublicWidgetController extends Controller {

	/**
	 * The status a reading that carries an `error` answers with, per widget type.
	 *
	 * @var array<string, int>
	 */
	private const ERROR_STATUS = [
		PublicWidgetReadService::WIDGET_WEATHER => Http::STATUS_BAD_GATEWAY,
		PublicWidgetReadService::WIDGET_LIVE_DATA => Http::STATUS_NOT_FOUND,
	];

	/**
	 * Constructor.
	 *
	 * @param IRequest                $request      HTTP request.
	 * @param PublicWidgetReadService $reads        Reads a live widget's data.
	 * @param PublicShareService      $shares       Opens a public share token.
	 * @param KioskService            $kiosk        Opens a kiosk playlist token.
	 * @param PublicShareContext      $shareContext Marks the request as a read-only bearer.
	 * @param LoggerInterface         $logger       PSR-3 logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly PublicWidgetReadService $reads,
		private readonly PublicShareService $shares,
		private readonly KioskService $kiosk,
		private readonly PublicShareContext $shareContext,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /s/{token}/placements/{placementId}`: a live widget's data on a public link.
	 *
	 * @param string  $token       The share token.
	 * @param integer $placementId The placement.
	 *
	 * @return DataResponse 200 with `{widget, data}`, 401 when a password is needed, 404 otherwise.
	 *
	 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 60)]
	#[BruteForceProtection(action: PublicShareService::ACTION_SHARE_ACCESS)]
	public function share(string $token, int $placementId): DataResponse {
		return $this->answer(
			token: $token,
			placementId: $placementId,
			dashboards: fn (): array => [
				$this->shares->sharedDashboardUuid(
					token: $token,
					ipAddress: $this->request->getRemoteAddress(),
					password: $this->headerPassword()
				),
			]
		);
	}//end share()

	/**
	 * `GET /kiosk/{token}/placements/{placementId}`: a live widget's data on a kiosk screen.
	 *
	 * @param string  $token       The playlist token.
	 * @param integer $placementId The placement.
	 *
	 * @return DataResponse 200 with `{widget, data}`, 404 otherwise.
	 *
	 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 60)]
	#[BruteForceProtection(action: PublicShareService::ACTION_SHARE_ACCESS)]
	public function kiosk(string $token, int $placementId): DataResponse {
		return $this->answer(
			token: $token,
			placementId: $placementId,
			dashboards: fn (): array => $this->kiosk->playlistDashboardUuids(token: $token)
		);
	}//end kiosk()

	/**
	 * Read the placement for the dashboards a token opens, and answer.
	 *
	 * An unknown token is throttled as a failed share access; a placement
	 * that is not a live widget on those dashboards is a plain 404.
	 *
	 * @param string                       $token       The token.
	 * @param integer                      $placementId The placement.
	 * @param Closure(): list<string>      $dashboards  Opens the token: the UUIDs of the dashboards it shows.
	 *
	 * @return DataResponse
	 *
	 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	private function answer(string $token, int $placementId, Closure $dashboards): DataResponse {
		try {
			$uuids = $dashboards();
		} catch (SharePasswordRequiredException) {
			return new DataResponse(data: ['passwordRequired' => true], statusCode: Http::STATUS_UNAUTHORIZED);
		} catch (ShareNotFoundException | PlaylistNotFoundException) {
			$response = new DataResponse(data: ['error' => 'Not found'], statusCode: Http::STATUS_NOT_FOUND);
			$response->throttle(['action' => PublicShareService::ACTION_SHARE_ACCESS]);
			return $response;
		}

		// A bearer request: any mutation on this path trips ShareReadOnlyException.
		$this->shareContext->markBearer(token: $token);

		try {
			$result = $this->reads->read(dashboardUuids: $uuids, placementId: $placementId);
		} catch (DoesNotExistException) {
			return new DataResponse(data: ['error' => 'Not found'], statusCode: Http::STATUS_NOT_FOUND);
		} catch (Exception $exception) {
			$this->logger->error(
				message: 'Public widget read failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			return new DataResponse(data: ['error' => 'Internal error'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (isset($result['data']['error']) === true) {
			return new DataResponse(
				data: ['widget' => $result['widget'], 'status' => 'error', 'error' => $result['data']['error']],
				statusCode: (self::ERROR_STATUS[$result['widget']] ?? Http::STATUS_NOT_FOUND)
			);
		}

		return new DataResponse(data: $result);
	}//end answer()

	/**
	 * The share password from the X-Share-Password header, never the query string.
	 *
	 * @return string|null The password, or null when none was sent.
	 *
	 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	private function headerPassword(): ?string {
		$password = $this->request->getHeader('X-Share-Password');
		if ($password === '') {
			return null;
		}

		return $password;
	}//end headerPassword()
}//end class
