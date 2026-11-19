<?php

/**
 * A controller fixture for the OpenAPI generator tests; never loaded, only parsed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Scripts\Fixtures\OpenApi
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Scripts\Fixtures\OpenApi;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Gadgets for the generator tests.
 *
 * More prose that the tag description leaves out.
 */
class GadgetController extends Controller {

	/**
	 * List the gadgets. Second sentence.
	 *
	 * See {@see self::show()} for one.
	 *
	 * @param string   $filter A filter.
	 * @param int|null $limit  At most this many.
	 *
	 * @return JSONResponse The gadgets.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, list<FixtureGadget>, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 */
	#[NoAdminRequired]
	public function index(string $filter, ?int $limit = null): JSONResponse {
		return new JSONResponse(data: [], statusCode: Http::STATUS_CREATED);
	}

	/**
	 * Show one gadget.
	 *
	 * @param string $uuid The gadget.
	 *
	 * @return JSONResponse The gadget.
	 */
	#[NoAdminRequired]
	public function show(string $uuid): JSONResponse {
		if ($uuid === '') {
			return $this->refuse();
		}

		$this->actionRefusal(action: 'gadget.show');
		return new JSONResponse(data: []);
	}

	/**
	 * Create a gadget.
	 *
	 * @param string       $name The name.
	 * @param array<mixed> $tags The tags.
	 *
	 * @return JSONResponse The gadget.
	 */
	#[AuthorizedAdminSetting(settings: 'Fixture')]
	public function create(string $name, array $tags = []): JSONResponse {
		$colour = $this->request->getParam(key: 'colour', default: 'red');
		return new JSONResponse(data: [$name, $tags, $colour], statusCode: Http::STATUS_CREATED);
	}

	/**
	 * Read the public face of a gadget.
	 *
	 * @param int $id The gadget id.
	 *
	 * @return JSONResponse The face.
	 */
	#[PublicPage]
	public function face(int $id): JSONResponse {
		return new JSONResponse(data: [$id]);
	}

	/**
	 * Gone.
	 *
	 * @return JSONResponse Always 410.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_GONE, array{status: 'gone'}, array<string, mixed>>
	 */
	public function retired(): JSONResponse {
		return new JSONResponse(data: ['status' => 'gone'], statusCode: 410);
	}

	/**
	 * A refusal the routes reach through `$this`.
	 *
	 * @return JSONResponse The 404.
	 */
	private function refuse(): JSONResponse {
		return new JSONResponse(data: [], statusCode: Http::STATUS_NOT_FOUND);
	}
}
