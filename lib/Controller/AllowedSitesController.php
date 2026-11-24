<?php

/**
 * AllowedSitesController
 *
 * The administrator's side of the allowed websites list (REQ-ASET-021):
 * which outside sites launchpad may embed, fetch from, or both.
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
use OCA\LaunchPad\Exception\InvalidAllowedSiteException;
use OCA\LaunchPad\Service\AllowedSitesService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * `GET` and `PUT /api/admin/allowed-sites`, for LaunchPad administrators only.
 *
 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
class AllowedSitesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request      The request.
	 * @param AllowedSitesService $allowedSites Reads and stores the list.
	 */
	public function __construct(
		IRequest $request,
		private readonly AllowedSitesService $allowedSites,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The allowed websites.
	 *
	 * @return DataResponse `{sites: [{host, embed, fetch}]}`.
	 *
	 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(): DataResponse {
		return new DataResponse(['sites' => $this->allowedSites->getSites()]);
	}//end show()

	/**
	 * Replace the list; an entry that is not a host, or has no purpose, is refused with 400.
	 *
	 * @param mixed $sites The sites, each `{host, embed, fetch}`.
	 *
	 * @return DataResponse `{sites}` as stored, or `{message}` with 400.
	 *
	 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(mixed $sites = []): DataResponse {
		try {
			return new DataResponse(['sites' => $this->allowedSites->setSites(sites: $sites)]);
		} catch (InvalidAllowedSiteException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end update()
}//end class
