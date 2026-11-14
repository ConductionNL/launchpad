<?php

/**
 * ReportApiController Test
 *
 * REQ-DWMS-003 over HTTP: the gallery, a run with the request's parameters,
 * the 404 and 400 answers, and a save that the caller owns.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\ReportApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\Reporting\ReportLibrary;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReportApiControllerTest extends TestCase {

	/** @var ReportLibrary&MockObject */
	private $reports;

	/** @var IRequest&MockObject */
	private $request;

	protected function setUp(): void {
		parent::setUp();
		$this->reports = $this->createMock(ReportLibrary::class);
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getParams')->willReturn(['reportId' => 'cases-per-area', '_route' => 'x', 'from' => '2026-07-01']);
	}//end setUp()

	public function testTheGalleryIsTheLibrarysAndNeedsASignedInUser(): void {
		$this->reports->method('gallery')->willReturn([['id' => 'a', 'available' => false, 'missing' => ['dossiq/case']]]);

		$response = $this->controller(userId: 'anna')->index();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['reports' => [['id' => 'a', 'available' => false, 'missing' => ['dossiq/case']]]], $response->getData());

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(userId: null)->index()->getStatus());
	}//end testTheGalleryIsTheLibrarysAndNeedsASignedInUser()

	public function testARunPassesTheQueryValuesWithoutTheRouteOnes(): void {
		$this->reports->expects($this->once())->method('run')
			->with('cases-per-area', ['from' => '2026-07-01'])
			->willReturn(['id' => 'cases-per-area', 'available' => true]);

		$response = $this->controller(userId: 'anna')->run(reportId: 'cases-per-area');

		$this->assertSame(['id' => 'cases-per-area', 'available' => true], $response->getData());
	}//end testARunPassesTheQueryValuesWithoutTheRouteOnes()

	public function testAnUnknownReportIs404AndABadParameter400(): void {
		$this->reports->method('run')->willReturnOnConsecutiveCalls(null, $this->throwException(new InvalidArgumentException('Parameter from must be a date.')));

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller(userId: 'anna')->run(reportId: 'nope')->getStatus());
		$bad = $this->controller(userId: 'anna')->run(reportId: 'cases-per-area');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $bad->getStatus());
		$this->assertSame(['error' => 'Parameter from must be a date.'], $bad->getData());
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(userId: null)->run(reportId: 'x')->getStatus());
	}//end testAnUnknownReportIs404AndABadParameter400()

	public function testASaveIsOwnedByTheCallerAndARefusalIs400(): void {
		$dashboard = new Dashboard();
		$dashboard->setId(42);
		$dashboard->setUserId('anna');
		$this->reports->expects($this->exactly(2))->method('saveAsDashboard')
			->with('cases-per-area', ['from' => '2026-07-01'], 'anna')
			->willReturnOnConsecutiveCalls($dashboard, $this->throwException(new InvalidArgumentException('Report x cannot run here: missing a/b.')));

		$created = $this->controller(userId: 'anna')->save(reportId: 'cases-per-area');
		$this->assertSame(Http::STATUS_CREATED, $created->getStatus());
		$this->assertSame(42, $created->getData()['id']);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller(userId: 'anna')->save(reportId: 'cases-per-area')->getStatus());
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(userId: null)->save(reportId: 'x')->getStatus());
	}//end testASaveIsOwnedByTheCallerAndARefusalIs400()

	/**
	 * The controller for one caller.
	 *
	 * @param string|null $userId The caller.
	 *
	 * @return ReportApiController
	 */
	private function controller(?string $userId): ReportApiController {
		return new ReportApiController($this->request, $this->reports, $userId);
	}//end controller()
}//end class
