<?php

/**
 * WorkPackagesService Test.
 *
 * Covers reading open work packages through the OpenProject integration.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\WorkPackages
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\WorkPackages;

use OCA\LaunchPad\Service\WorkPackages\WorkPackagesService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/OpenProjectAPIServiceDouble.php';
require_once __DIR__ . '/ArrayCacheDouble.php';

/**
 * Unit tests for the work packages reader.
 */
class WorkPackagesServiceTest extends TestCase {
	/**
	 * The integration's API service double.
	 *
	 * @var OpenProjectAPIService
	 */
	private OpenProjectAPIService $api;

	/**
	 * The in-memory cache.
	 *
	 * @var ArrayCacheDouble|null
	 */
	private ?ArrayCacheDouble $cache = null;

	/**
	 * Build the service with the integration enabled or not.
	 *
	 * @param bool $enabled Whether the integration is enabled for the person.
	 *
	 * @return WorkPackagesService
	 */
	private function service(bool $enabled=true): WorkPackagesService {
		$this->api = new OpenProjectAPIService();
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isEnabledForUser')->willReturn($enabled);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturn('https://op.zuiddrecht.example/');
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnMap(
			[
				[ICacheFactory::class, $this->cacheFactory()],
				['OCA\\OpenProject\\Service\\OpenProjectAPIService', $this->api],
			]
		);

		return new WorkPackagesService(
			appManager: $apps,
			appConfig: $config,
			container: $container,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * A cache factory whose cache keeps values in memory.
	 *
	 * @return ICacheFactory
	 */
	private function cacheFactory(): ICacheFactory {
		$this->cache = $this->cache ?? new ArrayCacheDouble();
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($this->cache);
		return $factory;
	}//end cacheFactory()

	/**
	 * A second read within the cache time answers from the cache; a failure is not kept.
	 *
	 * @return void
	 */
	public function testAnOkAnswerIsKeptAndAFailureIsNot(): void {
		$service = $this->service();
		$this->api->response = ['_embedded' => ['elements' => [['id' => 1, 'subject' => 'A']]]];
		$service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4);
		$this->api->lastRequest = [];
		$again = $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4);
		$this->assertSame(expected: 'A', actual: $again['workPackages'][0]['subject']);
		$this->assertSame(expected: [], actual: $this->api->lastRequest);

		$this->api->response = ['error' => 'down', 'statusCode' => 500];
		$service->openWorkPackages(userId: 'sanne', scope: 'mine', project: '', limit: 4);
		$this->api->lastRequest = [];
		$service->openWorkPackages(userId: 'sanne', scope: 'mine', project: '', limit: 4);
		$this->assertNotSame(expected: [], actual: $this->api->lastRequest);
	}//end testAnOkAnswerIsKeptAndAFailureIsNot()

	/**
	 * The person's own open work packages, due date first, as the widget shows them.
	 *
	 * @return void
	 */
	public function testListsThePersonsOpenWorkPackagesDueDateFirst(): void {
		$service = $this->service();
		$this->api->response = ['_embedded' => ['elements' => [
			[
				'id' => 12,
				'subject' => 'Welstandsadvies verwerken',
				'dueDate' => '2026-10-05',
				'_links' => [
					'status' => ['title' => 'In uitvoering'],
					'project' => ['title' => 'Havenpark'],
					'assignee' => ['title' => 'Pieter Jansen'],
				],
			],
			['subject' => 'no id'],
		]]];

		$result = $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4);

		$this->assertSame(expected: 'ok', actual: $result['state']);
		$this->assertSame(
			expected: [[
				'id' => 12,
				'subject' => 'Welstandsadvies verwerken',
				'status' => 'In uitvoering',
				'project' => 'Havenpark',
				'assignee' => 'Pieter Jansen',
				'dueDate' => '2026-10-05',
				'url' => 'https://op.zuiddrecht.example/work_packages/12',
			]],
			actual: $result['workPackages']
		);
		[$userId, $endpoint, $params] = $this->api->lastRequest;
		$this->assertSame(expected: 'pieter', actual: $userId);
		$this->assertSame(expected: 'work_packages', actual: $endpoint);
		$this->assertSame(
			expected: [['status' => ['operator' => 'o', 'values' => []]], ['assignee' => ['operator' => '=', 'values' => ['me']]]],
			actual: json_decode($params['filters'], true)
		);
		$this->assertSame(expected: [['dueDate', 'asc']], actual: json_decode($params['sortBy'], true));
		$this->assertSame(expected: 4, actual: $params['pageSize']);
	}//end testListsThePersonsOpenWorkPackagesDueDateFirst()

	/**
	 * A project list reads the project's work packages for every assignee.
	 *
	 * @return void
	 */
	public function testAProjectListReadsEveryAssigneeOfTheProject(): void {
		$service = $this->service();
		$this->api->response = ['_embedded' => ['elements' => []]];

		$service->openWorkPackages(userId: 'marieke', scope: 'project', project: 'havenpark', limit: 99);

		[, $endpoint, $params] = $this->api->lastRequest;
		$this->assertSame(expected: 'projects/havenpark/work_packages', actual: $endpoint);
		$this->assertSame(expected: [['status' => ['operator' => 'o', 'values' => []]]], actual: json_decode($params['filters'], true));
		$this->assertSame(expected: WorkPackagesService::MAX_LIMIT, actual: $params['pageSize']);
	}//end testAProjectListReadsEveryAssigneeOfTheProject()

	/**
	 * Without the integration nothing is asked.
	 *
	 * @return void
	 */
	public function testWithoutTheIntegrationTheStateIsNotInstalled(): void {
		$service = $this->service(enabled: false);
		$this->assertSame(expected: 'not-installed', actual: $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4)['state']);
		$this->assertSame(expected: [], actual: $this->api->lastRequest);
	}//end testWithoutTheIntegrationTheStateIsNotInstalled()

	/**
	 * Without a token, or when OpenProject refuses it, the person is asked to connect.
	 *
	 * @return void
	 */
	public function testWithoutAConnectedAccountThePersonIsAskedToConnect(): void {
		$service = $this->service();
		$this->api->token = '';
		$result = $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4);
		$this->assertSame(expected: 'not-connected', actual: $result['state']);
		$this->assertSame(expected: '/settings/user/openproject', actual: $result['connectUrl']);
		$this->assertSame(expected: [], actual: $this->api->lastRequest);

		$this->api->token = 'expired';
		$this->api->response = ['error' => 'Unauthorized', 'statusCode' => 401];
		$this->assertSame(expected: 'not-connected', actual: $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4)['state']);
	}//end testWithoutAConnectedAccountThePersonIsAskedToConnect()

	/**
	 * Any other failure of OpenProject is an error.
	 *
	 * @return void
	 */
	public function testAnotherFailureIsAnError(): void {
		$service = $this->service();
		$this->api->response = ['error' => 'down', 'statusCode' => 500];
		$this->assertSame(expected: 'error', actual: $service->openWorkPackages(userId: 'pieter', scope: 'mine', project: '', limit: 4)['state']);
	}//end testAnotherFailureIsAnError()

	/**
	 * A work package keeps no link without an http(s) OpenProject address.
	 *
	 * @return void
	 */
	public function testNoLinkWithoutAnHttpAddress(): void {
		$this->assertSame(expected: '', actual: WorkPackagesService::workPackage(element: ['id' => 3], base: 'javascript:')['url']);
	}//end testNoLinkWithoutAnHttpAddress()
}//end class
