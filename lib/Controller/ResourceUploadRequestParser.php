<?php

/**
 * ResourceUploadRequestParser
 *
 * Parses the raw HTTP request for the `POST /api/resources` endpoint.
 * Lives next to ResourceController so we can keep the controller's
 * dependency graph small (PHPMD CouplingBetweenObjects limit).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use JsonException;
use OCA\LaunchPad\Exception\InvalidDataUrlException;
use OCA\LaunchPad\Exception\UnsupportedMediaTypeException;
use OCP\IRequest;

/**
 * Parses request bodies for the resource upload endpoint.
 */
class ResourceUploadRequestParser {
	/**
	 * Extract the `base64` field from the raw JSON body.
	 *
	 * @param IRequest $request The HTTP request.
	 * @param string $rawBody The raw request body bytes.
	 *
	 * @return string The base64 data URL.
	 *
	 * @throws UnsupportedMediaTypeException When the request looks
	 *                                       like multipart instead of
	 *                                       JSON.
	 * @throws InvalidDataUrlException When the body is missing,
	 *                                 not JSON, or lacks the
	 *                                 `base64` field.
	 *
	 * @spec openspec/specs/resource-uploads/spec.md
	 */
	public function extractBase64(IRequest $request, string $rawBody): string {
		self::refuseMultipart(request: $request);

		if ($rawBody === '') {
			throw new InvalidDataUrlException();
		}

		$decoded = self::decodeJsonBody(rawBody: $rawBody);
		if (is_array(value: $decoded) === false
			|| isset($decoded['base64']) === false
			|| is_string(value: $decoded['base64']) === false
		) {
			throw new InvalidDataUrlException();
		}

		return $decoded['base64'];
	}//end extractBase64()

	/**
	 * Refuse a multipart upload: this endpoint takes a JSON body.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return void
	 *
	 * @throws UnsupportedMediaTypeException On a multipart/form-data body.
	 */
	private static function refuseMultipart(IRequest $request): void {
		$contentType = (string)$request->getHeader(name: 'Content-Type');
		if ($contentType !== '' && stripos(
			haystack: $contentType,
			needle: 'multipart/form-data'
		) !== false
		) {
			throw new UnsupportedMediaTypeException();
		}
	}//end refuseMultipart()

	/**
	 * Decode the JSON request body.
	 *
	 * @param string $rawBody The non-empty request body.
	 *
	 * @return mixed The decoded body.
	 *
	 * @throws InvalidDataUrlException When the body is not valid JSON.
	 */
	private static function decodeJsonBody(string $rawBody): mixed {
		try {
			return json_decode(
				json: $rawBody,
				associative: true,
				depth: 4,
				flags: JSON_THROW_ON_ERROR
			);
		} catch (JsonException $e) {
			throw new InvalidDataUrlException(
				message: 'Body must be valid JSON'
			);
		}
	}//end decodeJsonBody()
}//end class
