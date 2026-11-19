<?php

/**
 * EmbedSdkController
 *
 * Serves the embed SDK from the app itself (embedded-analytics task 24): a
 * host page loads `GET /apps/launchpad/embed-sdk/embed-sdk.umd.js` (or the
 * `.esm.js` module) from the Nextcloud instance, so the SDK is versioned with
 * the app. Only the built bundles and their source maps under `js/embed-sdk/`
 * are served, by fixed name: no user data, no token, no path from the request.
 * The answer is public and cacheable, and any origin may load it.
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
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * The public, cacheable route of the embed SDK bundles.
 */
class EmbedSdkController extends Controller {

	/**
	 * The files this route serves, by name, with their content type.
	 */
	private const FILES = [
		'embed-sdk.esm.js'     => 'text/javascript; charset=utf-8',
		'embed-sdk.umd.js'     => 'text/javascript; charset=utf-8',
		'embed-sdk.esm.js.map' => 'application/json; charset=utf-8',
		'embed-sdk.umd.js.map' => 'application/json; charset=utf-8',
	];

	/**
	 * How long a host page may cache a bundle, in seconds (one day).
	 */
	private const CACHE_SECONDS = 86400;

	/**
	 * Constructor.
	 *
	 * @param IRequest    $request    The request.
	 * @param IAppManager $appManager Locates the app's directory and version.
	 */
	public function __construct(
		IRequest $request,
		private readonly IAppManager $appManager,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * One built SDK bundle, or a 404 for any other name.
	 *
	 * @param string $file The bundle's file name.
	 *
	 * @return Response
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-24
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function bundle(string $file): Response {
		if (array_key_exists($file, self::FILES) === false) {
			return $this->notFound();
		}

		$path    = $this->appManager->getAppPath(appId: Application::APP_ID).'/js/embed-sdk/'.$file;
		$content = (is_file($path) === true) ? file_get_contents($path) : false;
		if ($content === false) {
			return $this->notFound();
		}

		$response = new DataDisplayResponse(data: $content, statusCode: Http::STATUS_OK, headers: [
			'Content-Type'                 => self::FILES[$file],
			'Access-Control-Allow-Origin'  => '*',
			'Cross-Origin-Resource-Policy' => 'cross-origin',
			'X-Content-Type-Options'       => 'nosniff',
			'X-LaunchPad-Version'          => $this->appManager->getAppVersion(appId: Application::APP_ID),
			'Cache-Control'                => 'public, max-age='.self::CACHE_SECONDS,
		]);
		$response->setETag(ETag: hash('sha256', $content));

		return $response;
	}//end bundle()

	/**
	 * The answer for a name that is not a bundle, or a bundle not built.
	 *
	 * @return Response
	 */
	private function notFound(): Response {
		return new DataDisplayResponse(data: 'Not found', statusCode: Http::STATUS_NOT_FOUND, headers: ['Content-Type' => 'text/plain; charset=utf-8']);
	}//end notFound()
}//end class
