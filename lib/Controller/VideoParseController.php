<?php

/**
 * VideoParseController
 *
 * `POST /api/widgets/video/parse`: checks a video URL from the widget form
 * and answers its id and canonical embed URL, or why it cannot be used
 * (REQ-VID-005).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\VideoParsingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Parses video URLs for the widget form.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
 */
class VideoParseController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest            $request HTTP request.
	 * @param VideoParsingService $parser  Parses the URL.
	 * @param UserResponses       $context The user id and the response helper.
	 */
	public function __construct(
		IRequest $request,
		private readonly VideoParsingService $parser,
		private readonly UserResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * POST /api/widgets/video/parse
	 *
	 * 200 `{isValid: true, sourceType, videoId, canonicalUrl}` for a usable
	 * URL; 400 `{isValid: false, error}` otherwise. Only a logged-in user is
	 * answered.
	 *
	 * @param string $videoUrl The URL as typed.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
	 */
	#[NoAdminRequired]
	public function parse(string $videoUrl=''): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		$result = $this->parser->parse(videoUrl: $videoUrl);
		$status = Http::STATUS_OK;
		if ($result['isValid'] === false) {
			$status = Http::STATUS_BAD_REQUEST;
		}

		return new JSONResponse(data: $result, statusCode: $status);
	}//end parse()
}//end class
