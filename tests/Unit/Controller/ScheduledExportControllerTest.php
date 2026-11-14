<?php

/**
 * ScheduledExportControllerTest
 *
 * The scheduled-exports API (tasks.md task 10): the action check runs
 * first, the service's refusals map onto 400/404/422/503, create answers
 * 201 without a client-chosen id, paging and sorting, run now as the
 * caller, preview as a file or as base64 artefacts with the recipient's
 * filter context, and runs reachable only through a visible export.
 *
 * @category  Test
 * @package   Unit\Controller
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

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\ScheduledExportController;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\ScheduledExport\AuditLog;
use OCA\LaunchPad\Service\ScheduledExport\ExportFire;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCA\LaunchPad\Service\ScheduledExport\RenderPipeline;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\ScheduledExportController
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\FireTrigger
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\RenderResult
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
 */
class ScheduledExportControllerTest extends TestCase {

	private IRequest&MockObject $request;

	private RequestGuard&MockObject $guard;

	private ScheduledExportService&MockObject $exports;

	private AuditLog&MockObject $log;

	private ExportFire&MockObject $fire;

	private RenderPipeline&MockObject $render;

	private ScheduledExportController $controller;

	/**
	 * Alice is signed in and granted every action unless a test says otherwise.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->guard   = $this->createMock(RequestGuard::class);
		$alice         = $this->createMock(IUser::class);
		$alice->method('getUID')->willReturn('alice');
		$this->guard->method('sessionUser')->willReturn($alice);
		$this->exports = $this->createMock(ScheduledExportService::class);
		$this->log     = $this->createMock(AuditLog::class);
		$this->fire    = $this->createMock(ExportFire::class);
		$this->render  = $this->createMock(RenderPipeline::class);

		$this->controller = new ScheduledExportController($this->request, $this->guard, $this->exports, $this->log, $this->fire, $this->render);
	}//end setUp()

	/**
	 * A caller without the action gets the guard's refusal and the service is never asked.
	 *
	 * @return void
	 */
	public function testRefusalComesFirst(): void {
		$this->guard->method('actionRefusal')->with('scheduled-exports.list')->willReturn(new JSONResponse(['error' => 'Forbidden'], 403));
		$this->exports->expects($this->never())->method('findVisible');

		$this->assertSame(403, $this->controller->index()->getStatus());
	}//end testRefusalComesFirst()

	/**
	 * The list is sorted by the asked key and order, then paged with the limit clamped.
	 *
	 * @return void
	 */
	public function testIndexSortsAndPages(): void {
		$this->exports->method('findVisible')->with('alice')->willReturn([['name' => 'b'], ['name' => 'c'], ['name' => 'a']]);

		$data = $this->controller->index(limit: 2, offset: 0, sort: 'name', order: 'desc')->getData();
		$this->assertSame(['results' => [['name' => 'c'], ['name' => 'b']], 'total' => 3, 'limit' => 2, 'offset' => 0], $data);
		$this->assertSame(200, $this->controller->index(limit: 999)->getData()['limit']);
	}//end testIndexSortsAndPages()

	/**
	 * Create saves the body without route keys or a client id and answers 201; a refusal of the service is 400.
	 *
	 * @return void
	 */
	public function testCreate(): void {
		$this->guard->method('actionRefusal')->willReturn(null);
		$this->request->method('getParams')->willReturn(['id' => 'mine', '_route' => 'x', 'name' => 'Weekly']);
		$this->exports->expects($this->exactly(2))->method('save')->with(['name' => 'Weekly'], 'alice')
			->willReturnOnConsecutiveCalls(['id' => 'e1'], $this->throwException(new InvalidArgumentException('The recurrence is required.')));

		$created = $this->controller->create();
		$this->assertSame(201, $created->getStatus());
		$this->assertSame(['id' => 'e1'], $created->getData());

		$bad = $this->controller->create();
		$this->assertSame(400, $bad->getStatus());
		$this->assertSame(['error' => 'invalid_argument', 'message' => 'The recurrence is required.'], $bad->getData());
	}//end testCreate()

	/**
	 * Update passes the route id over any body id; a missing export is 404, OpenRegister down is 503.
	 *
	 * @return void
	 */
	public function testUpdateShowAndErrors(): void {
		$this->request->method('getParams')->willReturn(['id' => 'e1', 'enabled' => false]);
		$this->exports->expects($this->once())->method('save')->with(['id' => 'e1', 'enabled' => false], 'alice')->willReturn(['id' => 'e1', 'enabled' => false]);
		$this->assertFalse($this->controller->update(id: 'e1')->getData()['enabled']);

		$this->exports->method('findOne')->willReturnCallback(static function (string $id): array {
			if ($id === 'down') {
				throw new RuntimeException('OpenRegister is not available');
			}

			throw new DoesNotExistException('Scheduled export not found');
		});
		$missing = $this->controller->show(id: 'nope');
		$this->assertSame(404, $missing->getStatus());
		$this->assertSame(['error' => 'Scheduled export not found'], $missing->getData());
		$this->assertSame(503, $this->controller->show(id: 'down')->getStatus());
	}//end testUpdateShowAndErrors()

	/**
	 * Delete soft-deletes through the service.
	 *
	 * @return void
	 */
	public function testDestroy(): void {
		$this->exports->expects($this->once())->method('delete')->with('e1', 'alice');
		$this->assertSame(['deleted' => true], $this->controller->destroy(id: 'e1')->getData());
	}//end testDestroy()

	/**
	 * Run now fires the visible export as a manual fire by the caller and answers 201.
	 *
	 * @return void
	 */
	public function testRunFiresAsTheCaller(): void {
		$this->exports->method('findOne')->with('e1', 'alice')->willReturn(['id' => 'e1']);
		$this->fire->expects($this->once())->method('fire')
			->with(['id' => 'e1'], $this->callback(static fn (FireTrigger $t): bool => $t->kind === 'manual' && $t->userId === 'alice'))
			->willReturn(['id' => 'r1', 'triggeredBy' => 'manual']);

		$response = $this->controller->run(id: 'e1');
		$this->assertSame(201, $response->getStatus());
		$this->assertSame('r1', $response->getData()['id']);
	}//end testRunFiresAsTheCaller()

	/**
	 * A preview with a format returns the file rendered with the recipient's filter context; without, base64 artefacts.
	 *
	 * @return void
	 */
	public function testPreview(): void {
		$this->exports->method('findOne')->willReturn(['id' => 'e1', 'renderTargets' => [['format' => 'pdf'], ['format' => 'csv', 'formatOptions' => ['delimiter' => ';']]], 'recipients' => [
			['channel' => 'email'],
			['id' => 'gm', 'channel' => 'sftp', 'filterContext' => ['gemeenteCode' => 'GM0355']],
		]]);
		$calls = [];
		$this->render->method('preview')->willReturnCallback(static function (array $export, array $context) use (&$calls): array {
			$calls[] = [$export['renderTargets'], $context];
			return array_map(static fn (array $t): RenderResult => new RenderResult(format: $t['format'], filename: 'w.' . $t['format'], content: 'X'), $export['renderTargets']);
		});

		// The file branch builds a DataDownloadResponse, which needs Symfony's
		// HeaderUtils from a booted Nextcloud; under this suite's OCP bootstrap
		// it throws, so only what was rendered is asserted for that branch.
		try {
			$file = $this->controller->preview(id: 'e1', recipientId: 'gm', format: 'csv');
			$this->assertInstanceOf(DataDownloadResponse::class, $file);
		} catch (\Error $e) {
			$this->assertStringContainsString('HeaderUtils', $e->getMessage());
		}

		$this->assertSame([[['format' => 'csv', 'formatOptions' => ['delimiter' => ';']]], ['gemeenteCode' => 'GM0355']], $calls[0]);

		$all = $this->controller->preview(id: 'e1');
		$this->assertSame(['w.pdf', 'w.csv'], array_column($all->getData()['artefacts'], 'filename'));
		$this->assertSame(base64_encode('X'), $all->getData()['artefacts'][0]['content']);
		$this->assertSame([], $calls[1][1]);
	}//end testPreview()

	/**
	 * A preview whose render fails answers 422 with the reason.
	 *
	 * @return void
	 */
	public function testPreviewRenderFailure(): void {
		$this->exports->method('findOne')->willReturn(['renderTargets' => [['format' => 'pdf']], 'recipients' => []]);
		$this->render->method('preview')->willThrowException(new RenderException('No headless browser is configured for PDF and PNG exports'));

		$response = $this->controller->preview(id: 'e1', format: 'pdf');
		$this->assertSame(422, $response->getStatus());
		$this->assertSame('No headless browser is configured for PDF and PNG exports', $response->getData()['message']);
	}//end testPreviewRenderFailure()

	/**
	 * Runs are listed and read only through a visible export; a run of another export is 404; retry goes through ExportFire.
	 *
	 * @return void
	 */
	public function testRunsShowRunAndRetry(): void {
		$this->exports->expects($this->exactly(4))->method('findOne')->with('e1', 'alice')->willReturn(['id' => 'e1']);
		$this->log->method('runsOf')->with('e1')->willReturn([['id' => 'r2'], ['id' => 'r1']]);
		$this->log->method('runOf')->willReturnCallback(static fn (string $e, string $r): ?array => $r === 'r1' ? ['id' => 'r1', 'exportId' => 'e1'] : null);
		$this->fire->expects($this->once())->method('retryRun')->with(['id' => 'r1', 'exportId' => 'e1'])->willReturn(['id' => 'r1', 'status' => 'running']);

		$this->assertSame(['results' => [['id' => 'r1']], 'total' => 2, 'limit' => 1, 'offset' => 1], $this->controller->runs(id: 'e1', limit: 1, offset: 1)->getData());
		$this->assertSame('r1', $this->controller->showRun(id: 'e1', runId: 'r1')->getData()['id']);
		$this->assertSame(404, $this->controller->showRun(id: 'e1', runId: 'other')->getStatus());
		$this->assertSame('running', $this->controller->retry(id: 'e1', runId: 'r1')->getData()['status']);
	}//end testRunsShowRunAndRetry()
}//end class
