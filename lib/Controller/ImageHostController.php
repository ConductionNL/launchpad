<?php

/**
 * ImageHostController
 *
 * The administrator's side of the external image host allow-list
 * (REQ-IMG-006): which hosts image widgets may load pictures from.
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
use OCA\LaunchPad\Service\ImageHostService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * `GET` and `PUT /api/admin/image-hosts`, for LaunchPad administrators only.
 *
 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
 */
class ImageHostController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest         $request    The request.
	 * @param ImageHostService $imageHosts Reads and stores the allow-list.
	 */
	public function __construct(
		IRequest $request,
		private readonly ImageHostService $imageHosts,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The allow-listed image hosts.
	 *
	 * @return JSONResponse `{hosts}`.
	 *
	 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(): JSONResponse {
		return new JSONResponse(['hosts' => $this->imageHosts->getAllowedHosts()]);
	}//end show()

	/**
	 * Replace the allow-list; a list holding something other than host names is refused with 400.
	 *
	 * @param mixed $hosts The host names.
	 *
	 * @return JSONResponse `{hosts}` as stored, or `{message}` with 400.
	 *
	 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(mixed $hosts = []): JSONResponse {
		try {
			return new JSONResponse(['hosts' => $this->imageHosts->setAllowedHosts(hosts: $hosts)]);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end update()
}//end class
