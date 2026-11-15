<?php

/**
 * PublicStartController
 *
 * The administrator's side of the public start page: which public link it
 * shows, whether it is on, why it is off, and which links may be chosen.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\PublicStartPage;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * `GET` and `PUT /api/admin/public-start`, for LaunchPad administrators only.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */
class PublicStartController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request   The request.
	 * @param PublicStartPage $startPage Chooses and checks the start page.
	 */
	public function __construct(
		IRequest $request,
		private readonly PublicStartPage $startPage,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The start page's status and the links that may be chosen.
	 *
	 * @return JSONResponse `{enabled, token, active, reason, candidates}`.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(): JSONResponse {
		return new JSONResponse($this->startPage->status() + ['candidates' => $this->startPage->candidates()]);
	}//end show()

	/**
	 * Choose the link and switch the start page on or off; a link that cannot be shown is refused with 400.
	 *
	 * @param bool   $enabled Whether the start page is on.
	 * @param string $token   The chosen link's token, or '' for none.
	 *
	 * @return JSONResponse The new status, or `{message}` with 400.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(bool $enabled = false, string $token = ''): JSONResponse {
		try {
			return new JSONResponse($this->startPage->configure(enabled: $enabled, token: $token));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end update()
}//end class
