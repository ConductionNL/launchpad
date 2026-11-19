<?php

/**
 * Unit tests for the OpenAPI generator (governance-rest-api-reference D1, task 2), over a fixture controller.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Scripts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Scripts;

use OCA\LaunchPad\Scripts\OpenApiControllerReader;
use OCA\LaunchPad\Scripts\OpenApiGenerator;
use OCA\LaunchPad\Scripts\OpenApiTypeMapper;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/scripts/OpenApiDocument.php';

/**
 * Routes and controller source become operations.
 *
 * @coversNothing
 */
class OpenApiGeneratorTest extends TestCase {

	/**
	 * The fixture directory.
	 *
	 * @var string
	 */
	private const FIXTURES = __DIR__.'/Fixtures/OpenApi';

	/**
	 * The routes of the fixture controller, versioned and not.
	 *
	 * @var array<string, mixed>
	 */
	private const ROUTES = ['routes' => [
		['name' => 'gadget#index', 'url' => '/api/v1/gadgets', 'verb' => 'GET'],
		['name' => 'gadget#show', 'url' => '/api/v1/gadgets/{uuid}', 'verb' => 'GET'],
		['name' => 'gadget#create', 'url' => '/api/v1/gadgets', 'verb' => 'POST'],
		['name' => 'gadget#face', 'url' => '/api/v1/gadgets/{id}/face', 'verb' => 'GET'],
		['name' => 'gadget#retired', 'url' => '/api/v1/gadgets/retired', 'verb' => 'DELETE'],
		['name' => 'gadget#index', 'url' => '/api/gadgets', 'verb' => 'GET'],
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	]];

	/**
	 * A generator over the fixtures.
	 *
	 * @return OpenApiGenerator
	 */
	private function generator(): OpenApiGenerator {
		$codes = OpenApiGenerator::statusCodes(httpFile: dirname(__DIR__, 3).'/vendor/nextcloud/ocp/OCP/AppFramework/Http.php');
		$types = new OpenApiTypeMapper(aliases: ['FixtureGadget' => true, 'FixtureCount' => true], statusCodes: $codes);
		return new OpenApiGenerator(
			reader: new OpenApiControllerReader(types: $types, statusCodes: $codes),
			types: $types,
			controllerDir: self::FIXTURES,
			namespace: 'OCA\LaunchPad\Tests\Unit\Scripts\Fixtures\OpenApi'
		);
	}//end generator()

	/**
	 * The fixture document, decoded the way it is written.
	 *
	 * @param array<string, mixed> $overlay The overlay.
	 *
	 * @return array<string, mixed>
	 */
	private function document(array $overlay=[]): array {
		$document = $this->generator()->generate(routes: self::ROUTES, overlay: $overlay);
		return json_decode(OpenApiGenerator::encode(document: $document), true);
	}//end document()

	/**
	 * Only the versioned routes become operations, under their path without the prefix.
	 *
	 * @return void
	 */
	public function testOnlyVersionedRoutesAreOperations(): void {
		$document = $this->document();

		self::assertSame('3.1.0', $document['openapi']);
		self::assertSame(['/gadgets', '/gadgets/retired', '/gadgets/{id}/face', '/gadgets/{uuid}'], array_keys($document['paths']));
		self::assertSame(['get', 'post'], array_keys($document['paths']['/gadgets']));
		self::assertSame([['name' => 'gadget', 'description' => 'Gadgets for the generator tests.']], $document['tags']);
	}//end testOnlyVersionedRoutesAreOperations()

	/**
	 * The docblock gives the summary, description and stability; the attribute gives the access sentence.
	 *
	 * @return void
	 */
	public function testTextStabilityAndAccess(): void {
		$index = $this->document()['paths']['/gadgets']['get'];

		self::assertSame('gadget-index', $index['operationId']);
		self::assertSame('public', $index['x-launchpad-stability']);
		self::assertSame('List the gadgets', $index['summary']);
		self::assertSame('List the gadgets. Second sentence. See `self::show()` for one. Needs a signed-in Nextcloud user.', $index['description']);
		self::assertSame('internal', $this->document()['paths']['/gadgets/{uuid}']['get']['x-launchpad-stability']);
	}//end testTextStabilityAndAccess()

	/**
	 * GET parameters go in the query, path parameters in the path, uuids get their format.
	 *
	 * @return void
	 */
	public function testPathAndQueryParameters(): void {
		$document = $this->document();

		self::assertSame(
			[
				['name' => 'filter', 'in' => 'query', 'required' => true, 'description' => 'A filter.', 'schema' => ['type' => 'string']],
				['name' => 'limit', 'in' => 'query', 'required' => false, 'description' => 'At most this many.', 'schema' => ['type' => 'integer']],
			],
			$document['paths']['/gadgets']['get']['parameters']
		);
		self::assertSame(
			['name' => 'uuid', 'in' => 'path', 'required' => true, 'description' => 'The gadget.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
			$document['paths']['/gadgets/{uuid}']['get']['parameters'][0]
		);
	}//end testPathAndQueryParameters()

	/**
	 * POST parameters, and what the method reads through getParam(), form the request body.
	 *
	 * @return void
	 */
	public function testRequestBody(): void {
		$body = $this->document()['paths']['/gadgets']['post']['requestBody'];

		self::assertTrue($body['required']);
		$schema = $body['content']['application/json']['schema'];
		self::assertSame(['name'], $schema['required']);
		self::assertSame(['name', 'tags', 'colour'], array_keys($schema['properties']));
		self::assertSame(['type' => 'string'], $schema['properties']['colour']);
		self::assertSame('array', $schema['properties']['tags']['type']);
	}//end testRequestBody()

	/**
	 * Status codes come from the body and the methods it calls; logins add 401, admin routes 403, public routes no security.
	 *
	 * @return void
	 */
	public function testResponsesFromTheCode(): void {
		$paths = $this->document()['paths'];

		self::assertSame([200, 401, 403, 404], array_keys($paths['/gadgets/{uuid}']['get']['responses']));
		self::assertSame([201, 401, 403], array_keys($paths['/gadgets']['post']['responses']));
		self::assertSame([200], array_keys($paths['/gadgets/{id}/face']['get']['responses']));
		self::assertSame([], $paths['/gadgets/{id}/face']['get']['security']);
		self::assertStringEndsWith('It needs no login.', $paths['/gadgets/{id}/face']['get']['description']);
		self::assertStringEndsWith('Needs an administrator, or a user the matching admin setting is delegated to.', $paths['/gadgets']['post']['description']);
		self::assertSame(['$ref' => '#/components/responses/Problem404'], $paths['/gadgets/{uuid}']['get']['responses']['404']);
	}//end testResponsesFromTheCode()

	/**
	 * A declared `@psalm-return` decides the successes and their bodies; a declared error extends the problem document.
	 *
	 * @return void
	 */
	public function testDeclaredResponseShapes(): void {
		$document = $this->document();
		$index    = $document['paths']['/gadgets']['get']['responses'];

		// The body scan found a 201, but the declaration says 200. Numeric keys decode as integers.
		self::assertSame([200, 401], array_keys($index));
		self::assertSame(
			['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FixtureGadget']],
			$index['200']['content']['application/json']['schema']
		);

		$retired = $document['paths']['/gadgets/retired']['delete']['responses'];
		self::assertSame([401, 403, 410], array_keys($retired));
		self::assertSame(['$ref' => '#/components/schemas/Problem'], $retired['410']['content']['application/problem+json']['schema']['allOf'][0]);
		self::assertSame('Gone.', $retired['410']['description']);
	}//end testDeclaredResponseShapes()

	/**
	 * The overlay frames the document and rewords operations, but never the access sentence.
	 *
	 * @return void
	 */
	public function testOverlay(): void {
		$document = $this->document(overlay: [
			'info'       => ['title' => 'Fixture', 'version' => '9'],
			'components' => ['schemas' => ['Problem' => ['type' => 'object']]],
			'operations' => [
				'GET /gadgets/{}' => [
					'description' => 'Reworded.',
					'responses'   => [
						'404' => ['description' => 'No such gadget.'],
						'200' => ['content' => ['application/json' => ['schema' => ['anyOf' => [['type' => 'object']]]]]],
					],
				],
			],
		]);
		$show = $document['paths']['/gadgets/{uuid}']['get'];

		self::assertSame(['title' => 'Fixture', 'version' => '9'], $document['info']);
		self::assertSame('Reworded. Needs a signed-in Nextcloud user.', $show['description']);
		self::assertSame(['description' => 'No such gadget.'], $show['responses']['404']);
		self::assertSame(['anyOf' => [['type' => 'object']]], $show['responses']['200']['content']['application/json']['schema']);
		self::assertArrayHasKey('Problem404', $document['components']['responses']);
		self::assertSame(['type' => 'object'], $document['components']['schemas']['Problem']);
	}//end testOverlay()

	/**
	 * The `@psalm-type` aliases of a definitions file become component schemas.
	 *
	 * @return void
	 */
	public function testDefinitions(): void {
		$types   = new OpenApiTypeMapper(aliases: ['FixtureGadget' => true, 'FixtureCount' => true]);
		$schemas = OpenApiGenerator::definitions(file: self::FIXTURES.'/ResponseDefinitions.php', types: $types);

		self::assertSame(['FixtureGadget', 'FixtureCount'], array_keys($schemas));
		self::assertSame(['id', 'name'], $schemas['FixtureGadget']['required']);
		self::assertTrue($schemas['FixtureGadget']['additionalProperties']);
		self::assertFalse($schemas['FixtureCount']['additionalProperties']);
	}//end testDefinitions()

	/**
	 * A route naming a method the controller does not have fails the generation.
	 *
	 * @return void
	 */
	public function testARouteWithoutItsMethodFails(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('gadget#missing');

		$this->generator()->generate(routes: ['routes' => [['name' => 'gadget#missing', 'url' => '/api/v1/x', 'verb' => 'GET']]], overlay: []);
	}//end testARouteWithoutItsMethodFails()

	/**
	 * Two routes to one method get distinct operation ids; kebab-case and merge behave.
	 *
	 * @return void
	 */
	public function testUniqueIdsKebabAndMerge(): void {
		$document = $this->generator()->generate(routes: ['routes' => [
			['name' => 'gadget#face', 'url' => '/api/v1/a/{id}', 'verb' => 'GET'],
			['name' => 'gadget#face', 'url' => '/api/v1/b/{id}', 'verb' => 'GET'],
		]], overlay: []);

		self::assertSame('gadget-face', $document['paths']['/a/{id}']['get']['operationId']);
		self::assertSame('gadget-face-2', $document['paths']['/b/{id}']['get']['operationId']);
		self::assertSame('dashboard-share-api', OpenApiGenerator::kebab(name: 'dashboardShareApi'));
		self::assertSame(
			['a' => ['b' => 1, 'c' => 2], 'r' => ['x' => 1], 'schema' => ['allOf' => [1]]],
			OpenApiGenerator::merge(base: ['a' => ['b' => 1], 'r' => ['$ref' => '#'], 'schema' => ['z' => 1]], override: ['a' => ['c' => 2], 'r' => ['x' => 1], 'schema' => ['allOf' => [1]]])
		);
	}//end testUniqueIdsKebabAndMerge()
}//end class
