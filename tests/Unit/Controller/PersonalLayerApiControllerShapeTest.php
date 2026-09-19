<?php

/**
 * PersonalLayerApiController Shape Test
 *
 * THE FIELD THAT CHANGED TYPE DEPENDING ON STATE.
 *
 * `GET /api/dashboards/{id}/personal-layer` answered `overrides` as a JSON
 * object keyed by placement id once a layer existed, and as a JSON array
 * (`[]`) when it did not, because PHP encodes an empty associative array as
 * `[]`. An empty object and an empty array are different types to every
 * consumer that validates the response or iterates the field, and the reset
 * put the endpoint back into the array state, so one dashboard answered both
 * types within a single session.
 *
 * Every assertion here is on `json_encode()` output rather than on the PHP
 * array the controller built, because in PHP `[] == []` and `[] === []` hold
 * for the empty object and the empty array alike: an assertion on the array
 * cannot see this bug at all, which is why it went unnoticed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PersonalLayerApiController;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The JSON types the personal-layer read answers with.
 */
class PersonalLayerApiControllerShapeTest extends TestCase {
	private const DASHBOARD = 1;

	private const USER = 'member';

	/** @var IRequest&MockObject */
	private $request;

	/** @var PersonalLayerService&MockObject */
	private $layers;

	/** @var PersonalLayerMapper&MockObject */
	private $mapper;

	/** @var DashboardService&MockObject */
	private $dashboards;

	/**
	 * Build the doubles.
	 *
	 * Every double names the methods it replaces with `onlyMethods()`, so a
	 * method the real class does not have is refused at build time rather
	 * than invented on the double and asserted against forever.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);

		$this->layers = $this->getMockBuilder(PersonalLayerService::class)
			->disableOriginalConstructor()
			->onlyMethods(['save', 'reset'])
			->getMock();

		$this->mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser'])
			->getMock();

		$this->dashboards = $this->getMockBuilder(DashboardService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getDashboardForUser'])
			->getMock();

		$this->dashboards->method('getDashboardForUser')->willReturn(['placements' => []]);
	}//end setUp()

	/**
	 * The controller under test.
	 *
	 * @return PersonalLayerApiController The controller.
	 */
	private function controller(): PersonalLayerApiController {
		return new PersonalLayerApiController(
			$this->request,
			$this->layers,
			$this->mapper,
			$this->dashboards,
			self::USER
		);
	}//end controller()

	/**
	 * The response body as it goes over the wire.
	 *
	 * @param JSONResponse $response The response.
	 *
	 * @return string The encoded body.
	 */
	private function body(JSONResponse $response): string {
		return (string)json_encode($response->getData());
	}//end body()

	/**
	 * The encoded GET body for one stored state.
	 *
	 * @param PersonalLayer|null $layer The stored layer, or null for none.
	 *
	 * @return string The encoded body.
	 */
	private function showWith(?PersonalLayer $layer): string {
		$this->mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser'])
			->getMock();
		$this->mapper->method('findForUser')->willReturn($layer);

		return $this->body($this->controller()->show(self::DASHBOARD));
	}//end showWith()

	/**
	 * A stored layer with one override on placement 1.
	 *
	 * @return PersonalLayer The layer.
	 */
	private function storedLayer(): PersonalLayer {
		$layer = new PersonalLayer();
		$layer->setId(3);
		$layer->setUserId(self::USER);
		$layer->setDashboardId(self::DASHBOARD);
		$layer->setOverrides('{"1":{"sortOrder":99}}');
		$layer->setHidden('[2]');
		$layer->setUpdatedAt('2026-09-19T10:00:00+00:00');

		return $layer;
	}//end storedLayer()

	/**
	 * With no layer, `overrides` is an empty JSON OBJECT, not an array.
	 *
	 * This is the state a person is in before they arrange anything, and the
	 * state the reset puts them back into.
	 *
	 * @return void
	 */
	public function testWithNoLayerOverridesIsAnEmptyJsonObject(): void {
		$body = $this->showWith(null);

		self::assertStringContainsString('"overrides":{}', $body);
		self::assertStringNotContainsString('"overrides":[]', $body);
	}//end testWithNoLayerOverridesIsAnEmptyJsonObject()

	/**
	 * With a saved layer, `overrides` is a JSON object keyed by placement id.
	 *
	 * @return void
	 */
	public function testWithASavedLayerOverridesIsAJsonObjectKeyedByPlacementId(): void {
		$body = $this->showWith($this->storedLayer());

		self::assertStringContainsString('"overrides":{"1":{"sortOrder":99}}', $body);
	}//end testWithASavedLayerOverridesIsAJsonObjectKeyedByPlacementId()

	/**
	 * A layer whose overrides were emptied still answers an object.
	 *
	 * `save()` writes `[]` into the column when nothing survives cleaning, so
	 * the row exists with no overrides in it. Without the cast this is the
	 * `hasLayer: true` response that still answers `[]`, which the two tests
	 * above cannot see.
	 *
	 * @return void
	 */
	public function testASavedLayerWithNoOverridesLeftStillAnswersAnObject(): void {
		$layer = $this->storedLayer();
		$layer->setOverrides('[]');

		$body = $this->showWith($layer);

		self::assertStringContainsString('"overrides":{}', $body);
		self::assertStringContainsString('"hasLayer":true', $body);
	}//end testASavedLayerWithNoOverridesLeftStillAnswersAnObject()

	/**
	 * `hidden` is a list in both states, because it genuinely is a list.
	 *
	 * The fix must not turn it into an object on the way past: the field
	 * holds placement ids with no keys worth naming.
	 *
	 * @return void
	 */
	public function testHiddenIsAJsonArrayInBothStates(): void {
		self::assertStringContainsString('"hidden":[]', $this->showWith(null));
		self::assertStringContainsString('"hidden":[2]', $this->showWith($this->storedLayer()));
	}//end testHiddenIsAJsonArrayInBothStates()

	/**
	 * Both states answer with the same keys.
	 *
	 * `id`, `dashboardId` and `updatedAt` used to appear only once a layer
	 * existed, so a client had to branch on `hasLayer` before it could read
	 * the envelope at all. They are now always present, null when there is no
	 * row to take them from.
	 *
	 * @return void
	 */
	public function testBothStatesAnswerTheSameKeys(): void {
		$empty = (array)json_decode($this->showWith(null), true);
		$saved = (array)json_decode($this->showWith($this->storedLayer()), true);

		$emptyKeys = array_keys($empty);
		$savedKeys = array_keys($saved);
		sort($emptyKeys);
		sort($savedKeys);

		self::assertSame($savedKeys, $emptyKeys);
		self::assertNull($empty['id']);
		self::assertSame(self::DASHBOARD, $empty['dashboardId']);
	}//end testBothStatesAnswerTheSameKeys()
}//end class
