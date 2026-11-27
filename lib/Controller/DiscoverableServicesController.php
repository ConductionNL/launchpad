<?php

/**
 * DiscoverableServicesController
 *
 * The administrator's side of service discovery (REQ-TILE-044): which web
 * services on the server LaunchPad may ask whether they answer, each with a
 * name and an address.
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
use OCA\LaunchPad\Service\FoundServicesService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * `GET` and `PUT /api/admin/discoverable-services`, for LaunchPad administrators only.
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */
class DiscoverableServicesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest             $request       The request.
	 * @param FoundServicesService $foundServices Reads and stores the list.
	 */
	public function __construct(
		IRequest $request,
		private readonly FoundServicesService $foundServices,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The services an administrator allowed for discovery.
	 *
	 * @return DataResponse `{services: [{name, url}]}`.
	 *
	 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(): DataResponse {
		return new DataResponse(['services' => $this->foundServices->getServices()]);
	}//end show()

	/**
	 * Replace the list; an entry without a name or a web address is refused with 400.
	 *
	 * @param mixed $services The services, each `{name, url}`.
	 *
	 * @return DataResponse `{services}` as stored, or `{message}` with 400.
	 *
	 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(mixed $services = []): DataResponse {
		try {
			return new DataResponse(['services' => $this->foundServices->setServices(services: $services)]);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end update()
}//end class
