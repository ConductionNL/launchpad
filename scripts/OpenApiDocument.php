<?php
/**
 * OpenApiDocument
 *
 * Wires the OpenAPI generator to this repository's files, so the generate
 * script and the drift check build the document the same way
 * (governance-rest-api-reference D1, D3).
 *
 * @category  Tooling
 * @package   OCA\LaunchPad\Scripts
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

namespace OCA\LaunchPad\Scripts;

require_once __DIR__.'/OpenApiRouteCoverage.php';
require_once __DIR__.'/OpenApiTypeMapper.php';
require_once __DIR__.'/OpenApiControllerReader.php';
require_once __DIR__.'/OpenApiGenerator.php';

/**
 * Builds the document of a checkout.
 *
 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
 */
final class OpenApiDocument {

	/**
	 * The document of the checkout at `$root`, decoded.
	 *
	 * @param string $root The repository root.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function build(string $root): array {
		$codes       = OpenApiGenerator::statusCodes(httpFile: $root.'/vendor/nextcloud/ocp/OCP/AppFramework/Http.php');
		$aliasTypes  = OpenApiGenerator::aliasTypes(file: $root.'/lib/ResponseDefinitions.php');
		$types       = new OpenApiTypeMapper(aliases: array_fill_keys(array_keys($aliasTypes), true), statusCodes: $codes);
		$generator   = new OpenApiGenerator(
			reader: new OpenApiControllerReader(types: $types, statusCodes: $codes),
			types: $types,
			controllerDir: $root.'/lib/Controller',
			namespace: 'OCA\LaunchPad\Controller'
		);
		$overlayJson = (string) file_get_contents(__DIR__.'/openapi-overlay.json');
		$overlay     = (array) self::decoded(value: json_decode($overlayJson, false, 512, JSON_THROW_ON_ERROR));
		$routes      = require $root.'/appinfo/routes.php';
		$appVersion  = self::appVersion(infoXml: (string) file_get_contents($root.'/appinfo/info.xml'));
		if ($appVersion !== null) {
			$overlay['info']['version'] = $appVersion;
		}

		return $generator->generate(
			routes: $routes,
			overlay: $overlay,
			definitions: OpenApiGenerator::definitions(file: $root.'/lib/ResponseDefinitions.php', types: $types)
		);
	}//end build()

	/**
	 * A decoded JSON value as arrays, with every empty object kept an object so it is written back as `{}`.
	 *
	 * @param mixed $value The value `json_decode()` gave with objects.
	 *
	 * @return mixed
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function decoded(mixed $value): mixed {
		if ($value instanceof \stdClass) {
			$properties = get_object_vars($value);
			if ($properties === []) {
				return $value;
			}

			return array_map(static fn (mixed $item): mixed => self::decoded(value: $item), $properties);
		}

		if (is_array($value) === true) {
			return array_map(static fn (mixed $item): mixed => self::decoded(value: $item), $value);
		}

		return $value;
	}//end decoded()

	/**
	 * The `<version>` of an appinfo/info.xml, which the release workflow also writes into openapi.json.
	 *
	 * @param string $infoXml The contents of appinfo/info.xml.
	 *
	 * @return string|null Null when the file names no version.
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function appVersion(string $infoXml): ?string {
		if (preg_match('#<version>\s*([^<\s]+)\s*</version>#', $infoXml, $match) !== 1) {
			return null;
		}

		return $match[1];
	}//end appVersion()

	/**
	 * The document of the checkout at `$root`, encoded the way it is committed.
	 *
	 * @param string $root The repository root.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function encodedFor(string $root): string {
		return OpenApiGenerator::encode(document: self::build(root: $root));
	}//end encodedFor()
}//end class
