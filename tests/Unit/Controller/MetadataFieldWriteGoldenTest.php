<?php

/**
 * Metadata field write golden test
 *
 * Pins the field rows that the admin create and update endpoints write
 * through a real MetadataService, and their refusals, against a recorded
 * fixture.
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

use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\MetadataAdminController;
use OCA\LaunchPad\Db\MetadataField;
use OCA\LaunchPad\Db\MetadataFieldMapper;
use OCA\LaunchPad\Db\MetadataValueMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\MetadataValidationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The metadata field rows of the admin endpoints, case by case.
 *
 * @covers \OCA\LaunchPad\Controller\MetadataAdminController
 * @covers \OCA\LaunchPad\Service\MetadataService
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses   \OCA\LaunchPad\Db\MetadataField
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 */
class MetadataFieldWriteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/metadata-field-write.json';

	/**
	 * Rows the mapper was asked to write.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $rows = [];

	/**
	 * The controller over a real service and a recording mapper.
	 *
	 * @return MetadataAdminController
	 */
	private function controller(): MetadataAdminController {
		$existing = new MetadataField();
		$existing->setId(3);
		$existing->setFieldKey('dept');
		$existing->setLabel('Department');
		$existing->setType('select');
		$existing->setRequired(0);
		$existing->setSortOrder(1);
		$existing->setOptionsArray(options: ['a', 'b']);

		$mapper = $this->createMock(MetadataFieldMapper::class);
		$mapper->method('findByKey')->willReturnCallback(
			static function (string $key) use ($existing): MetadataField {
				if ($key === 'dept') {
					return $existing;
				}

				throw new DoesNotExistException('none');
			}
		);
		$mapper->method('findById')->willReturnCallback(
			static function (int $id) use ($existing): MetadataField {
				if ($id === 3) {
					return $existing;
				}

				throw new DoesNotExistException('none');
			}
		);
		foreach (['insert', 'update'] as $method) {
			$mapper->method($method)->willReturnCallback(
				function (MetadataField $field) use ($method): MetadataField {
					$row = $field->jsonSerialize();
					unset($row['createdAt'], $row['updatedAt']);
					$this->rows[] = [$method => $row];
					return $field;
				}
			);
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('root');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(true);

		return new MetadataAdminController(
			request: $this->createMock(IRequest::class),
			metadataService: new MetadataService(
				fieldMapper: $mapper,
				valueMapper: $this->createMock(MetadataValueMapper::class),
				validationService: $this->createMock(MetadataValidationService::class),
				logger: $this->createMock(LoggerInterface::class),
			),
			groupManager: $groups,
			caller: new ActionCaller(
				userSession: $session,
				actionAuth: $this->createMock(ActionAuthService::class)
			)
		);
	}//end controller()

	/**
	 * Every case writes the recorded row or answers the recorded refusal.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$create = [
			'text'             => ['key' => 'room', 'label' => 'Room', 'type' => 'text'],
			'select'           => ['key' => 'floor', 'label' => 'Floor', 'type' => 'select', 'options' => ['1', '2']],
			'required sorted'  => ['key' => 'cost', 'label' => 'Cost', 'type' => 'number', 'required' => 1, 'sortOrder' => 4],
			'required two'     => ['key' => 'cost2', 'label' => 'Cost', 'type' => 'text', 'required' => 2],
			'duplicate key'    => ['key' => 'dept', 'label' => 'Dept', 'type' => 'text'],
			'bad key'          => ['key' => 'Bad Key', 'label' => 'X', 'type' => 'text'],
			'bad type'         => ['key' => 'x', 'label' => 'X', 'type' => 'nope'],
			'select no option' => ['key' => 'y', 'label' => 'Y', 'type' => 'select'],
			'nothing'          => [],
		];
		$update = [
			'label'      => ['label' => 'Unit'],
			'all'        => ['label' => 'Unit', 'sortOrder' => 9, 'required' => 1, 'options' => ['c']],
			'rename key' => ['key' => 'other'],
			'missing'    => ['id' => 99, 'label' => 'x'],
			'nothing'    => [],
		];

		$actual = [];
		foreach ($create as $case => $args) {
			$this->rows = [];
			$response   = $this->controller()->createField(...$args);
			$actual['create '.$case] = ['status' => $response->getStatus(), 'rows' => $this->rows, 'error' => $response->getStatus() >= 400 ? $response->getData() : null];
		}

		foreach ($update as $case => $args) {
			$this->rows = [];
			$id         = $args['id'] ?? 3;
			unset($args['id']);
			$response   = $this->controller()->updateField($id, ...$args);
			$actual['update '.$case] = ['status' => $response->getStatus(), 'rows' => $this->rows, 'error' => $response->getStatus() >= 400 ? $response->getData() : null];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$lines = [];
			foreach ($actual as $case => $row) {
				$lines[] = json_encode($case).': '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			}

			file_put_contents(self::FIXTURE, "{\n".implode(",\n", $lines)."\n}\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents(self::FIXTURE))), json_encode($actual));
	}//end testRowsMatchTheRecordedFixture()

	/**
	 * A field created with only key, label and type gets the defaults.
	 *
	 * @return void
	 */
	public function testServiceDefaults(): void {
		$mapper = $this->createMock(MetadataFieldMapper::class);
		$mapper->method('findByKey')->willThrowException(new DoesNotExistException('none'));
		$mapper->method('insert')->willReturnArgument(0);
		$service = new MetadataService(
			fieldMapper: $mapper,
			valueMapper: $this->createMock(MetadataValueMapper::class),
			validationService: $this->createMock(MetadataValidationService::class),
			logger: $this->createMock(LoggerInterface::class),
		);

		$row = $service->createFieldDefinition(key: 'room', label: 'Room', type: 'text')->jsonSerialize();

		$this->assertSame([null, 0, 0], [$row['options'], $row['required'], $row['sortOrder']]);
	}//end testServiceDefaults()
}//end class
