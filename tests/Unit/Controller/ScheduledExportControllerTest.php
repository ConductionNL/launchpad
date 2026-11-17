<?php

/**
 * ScheduledExportControllerTest
 *
 * Every endpoint checks its action first; an export or run the user may not
 * see is 404; an invalid export is 400; a retry re-arms only the deliveries
 * that failed for good and rolls the run's status up again
 * (scheduled-exports task 10).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
 */

declare(strict_types=1);

namespace Unit\Controller;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\LaunchPad\Controller\ScheduledExportController;
use OCA\LaunchPad\Service\DeliveryDispatcher;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\RetryHandler;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ScheduledExportController.
 *
 * @covers \OCA\LaunchPad\Controller\ScheduledExportController
 * @uses   \OCA\LaunchPad\Service\RetryHandler
 * @uses   \OCA\LaunchPad\Middleware\Support\ApiPathAliases
 */
class ScheduledExportControllerTest extends TestCase {

	/**
	 * The exports double.
	 *
	 * @var ScheduledExportService&MockObject
	 */
	private ScheduledExportService $exports;

	/**
	 * The run-rows double.
	 *
	 * @var ScheduledExportAuditLog&MockObject
	 */
	private ScheduledExportAuditLog $auditLog;

	/**
	 * The dispatcher double.
	 *
	 * @var DeliveryDispatcher&MockObject
	 */
	private DeliveryDispatcher $dispatcher;

	/**
	 * The moment every call runs at.
	 */
	private DateTimeImmutable $now;

	/**
	 * The actions checked, in order.
	 *
	 * @var list<string>
	 */
	private array $checked = [];

	protected function setUp(): void {
		$this->exports    = $this->createMock(ScheduledExportService::class);
		$this->auditLog   = $this->createMock(ScheduledExportAuditLog::class);
		$this->dispatcher = $this->createMock(DeliveryDispatcher::class);
		$this->now        = new DateTimeImmutable('2026-11-17T10:00:00+00:00');
	}//end setUp()

	/**
	 * The controller for user `pieter`, refusing the listed actions.
	 *
	 * @param array<string, mixed> $params  The request params.
	 * @param list<string>         $refused Actions the matrix refuses.
	 *
	 * @return ScheduledExportController
	 */
	private function controller(array $params = [], array $refused = []): ScheduledExportController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => ($params[$key] ?? $default));

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pieter');

		$guard = $this->createMock(RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(function (string $action) use ($refused): ?JSONResponse {
			$this->checked[] = $action;
			return in_array($action, $refused, true) ? new JSONResponse(['error' => 'Forbidden'], 403) : null;
		});
		$guard->method('sessionUser')->willReturn($user);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn($this->now);

		return new ScheduledExportController($request, $this->exports, $this->auditLog, $this->dispatcher, new RetryHandler(), $guard, $time);
	}//end controller()

	public function testARefusedActionStopsBeforeTheServiceIsAsked(): void {
		$this->exports->expects($this->never())->method('findVisible');
		$this->exports->expects($this->never())->method('save');

		$this->assertSame(403, $this->controller(refused: ['scheduled-export.read'])->index()->getStatus());
		$this->assertSame(403, $this->controller(refused: ['scheduled-export.manage'])->create()->getStatus());
		$this->assertSame(['scheduled-export.read', 'scheduled-export.manage'], $this->checked);
	}//end testARefusedActionStopsBeforeTheServiceIsAsked()

	public function testTheListIsSortedByNameAndPaged(): void {
		$this->exports->expects($this->once())->method('findVisible')->with('pieter')->willReturn(
			[['id' => 'c', 'name' => 'Weekly'], ['id' => 'a', 'name' => 'annual'], ['id' => 'b', 'name' => 'Monthly']]
		);

		$data = $this->controller(params: ['limit' => '2', 'offset' => '1'])->index()->getData();

		$this->assertSame(['b', 'c'], array_column($data['results'], 'id'));
		$this->assertSame(3, $data['total']);
		$this->assertSame(2, $data['limit']);
		$this->assertSame(1, $data['offset']);
	}//end testTheListIsSortedByNameAndPaged()

	public function testAPageIsClampedToItsBounds(): void {
		$this->exports->method('findVisible')->willReturn([]);

		$data = $this->controller(params: ['limit' => '5000', 'offset' => '-3'])->index()->getData();

		$this->assertSame(200, $data['limit']);
		$this->assertSame(0, $data['offset']);
	}//end testAPageIsClampedToItsBounds()

	public function testAnExportTheUserMayNotSeeIs404(): void {
		$this->exports->method('findOne')->with('x', 'pieter')->willReturn(null);
		$this->exports->expects($this->never())->method('save');
		$this->auditLog->expects($this->never())->method('runsOf');

		$controller = $this->controller(params: ['name' => 'Changed']);
		$this->assertSame(404, $controller->show('x')->getStatus());
		$this->assertSame(404, $controller->update('x')->getStatus());
		$this->assertSame(404, $controller->runs('x')->getStatus());
		$this->assertSame(404, $controller->run('x', 'r1')->getStatus());
		$this->assertSame(404, $controller->retry('x', 'r1')->getStatus());
	}//end testAnExportTheUserMayNotSeeIs404()

	public function testCreateSavesOnlyTheExportFieldsAndAnswers201(): void {
		$params = ['name' => 'Weekly KPI', 'recurrence' => ['type' => 'weekly'], 'nextRunAt' => '2000-01-01T00:00:00+00:00', '_route' => 'x'];
		$this->exports->expects($this->once())->method('save')
			->with(['name' => 'Weekly KPI', 'recurrence' => ['type' => 'weekly']], null, $this->now)
			->willReturn(['id' => 'new', 'name' => 'Weekly KPI']);

		$response = $this->controller(params: $params)->create();

		$this->assertSame(201, $response->getStatus());
		$this->assertSame('new', $response->getData()['id']);
	}//end testCreateSavesOnlyTheExportFieldsAndAnswers201()

	public function testAnInvalidExportIs400WithTheReason(): void {
		$this->exports->method('save')->willThrowException(new InvalidArgumentException('The recurrence is invalid.'));

		$response = $this->controller(params: ['name' => 'x'])->create();

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('The recurrence is invalid.', $response->getData()['error']);
	}//end testAnInvalidExportIs400WithTheReason()

	public function testUpdateSavesTheSentFieldsOnTheExport(): void {
		$this->exports->method('findOne')->willReturn(['id' => 'e1']);
		$this->exports->expects($this->once())->method('save')
			->with(['enabled' => false], 'e1', $this->now)
			->willReturn(['id' => 'e1', 'enabled' => false]);

		$response = $this->controller(params: ['enabled' => false])->update('e1');

		$this->assertSame(200, $response->getStatus());
		$this->assertFalse($response->getData()['enabled']);
	}//end testUpdateSavesTheSentFieldsOnTheExport()

	public function testDeleteOfAnExportTheUserMayNotSeeIs404(): void {
		$this->exports->method('delete')->willReturnMap([['gone', 'pieter', false], ['e1', 'pieter', true]]);

		$this->assertSame(404, $this->controller()->destroy('gone')->getStatus());
		$this->assertSame(['deleted' => 'e1'], $this->controller()->destroy('e1')->getData());
	}//end testDeleteOfAnExportTheUserMayNotSeeIs404()

	public function testRunsArePagedFromTheAuditLog(): void {
		$this->exports->method('findOne')->willReturn(['id' => 'e1']);
		$this->auditLog->expects($this->once())->method('runsOf')->with('e1', 10, 20)->willReturn([['id' => 'r1']]);

		$data = $this->controller(params: ['limit' => '10', 'offset' => '20'])->runs('e1')->getData();

		$this->assertSame([['id' => 'r1']], $data['results']);
	}//end testRunsArePagedFromTheAuditLog()

	public function testARunOfAnotherExportIs404(): void {
		$this->exports->method('findOne')->willReturn(['id' => 'e1']);
		$this->auditLog->method('runOf')->willReturnMap([['e1', 'other', true, null], ['e1', 'r1', true, ['id' => 'r1', 'exportId' => 'e1']]]);

		$this->assertSame(404, $this->controller()->run('e1', 'other')->getStatus());
		$this->assertSame('r1', $this->controller()->run('e1', 'r1')->getData()['id']);
	}//end testARunOfAnotherExportIs404()

	public function testARetryRearmsOnlyTheDeliveriesThatFailedForGood(): void {
		$run = [
			'id' => 'r1',
			'exportId' => 'e1',
			'deliveries' => [
				['recipientId' => 'a', 'status' => 'success', 'attemptCount' => 1],
				['recipientId' => 'b', 'status' => 'failed-permanent', 'attemptCount' => 5],
			],
		];
		$this->exports->method('findOne')->willReturn(['id' => 'e1']);
		$this->exports->method('findForRun')->with('e1')->willReturn(['id' => 'e1', 'recipients' => []]);
		$this->auditLog->method('runOf')->willReturn($run);

		$this->dispatcher->expects($this->once())->method('retryDue')
			->with(
				['id' => 'e1', 'recipients' => []],
				$this->callback(function (array $sent): bool {
					$this->assertSame('success', $sent['deliveries'][0]['status']);
					$this->assertSame(1, $sent['deliveries'][0]['attemptCount']);
					$this->assertSame('retrying', $sent['deliveries'][1]['status']);
					$this->assertSame(0, $sent['deliveries'][1]['attemptCount']);
					$this->assertSame('2026-11-17T10:00:00+00:00', $sent['deliveries'][1]['nextAttemptAt']);
					return true;
				}),
				$this->now
			)
			->willReturnCallback(static function (array $export, array $sent): array {
				$sent['deliveries'][1]['status'] = 'success';
				return $sent;
			});
		$this->auditLog->expects($this->once())->method('finish')
			->with($this->callback(static fn (array $saved): bool => $saved['deliveries'][1]['status'] === 'success'), 'success', $this->now)
			->willReturnCallback(static fn (array $saved, string $status): array => $saved + ['status' => $status]);

		$response = $this->controller()->retry('e1', 'r1');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(1, $response->getData()['retried']);
		$this->assertSame('success', $response->getData()['run']['status']);
		$this->assertSame(['scheduled-export.manage'], $this->checked);
	}//end testARetryRearmsOnlyTheDeliveriesThatFailedForGood()

	public function testARetryWithNothingFailedForGoodIs409AndSendsNothing(): void {
		$this->exports->method('findOne')->willReturn(['id' => 'e1']);
		$this->exports->method('findForRun')->willReturn(['id' => 'e1']);
		$this->auditLog->method('runOf')->willReturn(['id' => 'r1', 'deliveries' => [['status' => 'retrying'], ['status' => 'success']]]);
		$this->dispatcher->expects($this->never())->method('retryDue');
		$this->auditLog->expects($this->never())->method('finish');

		$this->assertSame(409, $this->controller()->retry('e1', 'r1')->getStatus());
	}//end testARetryWithNothingFailedForGoodIs409AndSendsNothing()

	public function testEveryRoutedMethodIsDeclaredForNonAdmins(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$names  = array_column($routes['routes'], 'name');
		foreach (['index', 'create', 'show', 'update', 'destroy', 'runs', 'run', 'retry'] as $method) {
			$this->assertContains('scheduledExport#' . $method, $names);
			$attributes = (new \ReflectionMethod(ScheduledExportController::class, $method))->getAttributes();
			$this->assertSame(
				['OCP\AppFramework\Http\Attribute\NoAdminRequired'],
				array_map(static fn (\ReflectionAttribute $a): string => $a->getName(), $attributes),
				$method
			);
		}

		$actions = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/actions.seed.json'), true)['actions'];
		$this->assertArrayHasKey(ScheduledExportController::ACTION_READ, $actions);
		$this->assertArrayHasKey(ScheduledExportController::ACTION_MANAGE, $actions);
	}//end testEveryRoutedMethodIsDeclaredForNonAdmins()
}//end class
