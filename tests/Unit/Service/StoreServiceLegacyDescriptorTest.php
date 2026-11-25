<?php

/**
 * StoreService against an OpenRegister release from before the descriptor
 * was split into a listing and a sharing part.
 *
 * Runs in its own process: the flat descriptor is declared here, before any
 * stub, so the listing and sharing classes do not exist and the service must
 * fall back to the flat named arguments.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\StoreService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class StoreServiceLegacyDescriptorTest extends TestCase {
	/**
	 * An older OpenRegister takes the flat descriptor; the service builds it
	 * with the same app, schema, register and card map.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-store/spec.md
	 */
	public function testAnOlderEngineGetsTheFlatDescriptor(): void {
		// phpcs:ignore Squiz.PHP.Eval.Discouraged -- declares the pre-split engine classes in this process only.
		eval(
			'namespace OCA\OpenRegister\AppHost\Service;'
			.'final class StoreDescriptor { public function __construct('
			.'public readonly string $appId, public readonly string $schema, public readonly string $defaultRegister,'
			.'public readonly array $cardFields = [], public readonly array $types = []) {} }'
			.'class GenericStoreService { public ?object $seen = null;'
			.'public function search(StoreDescriptor $descriptor, ?string $query = null, ?string $kind = null): array'
			.'{ $this->seen = $descriptor; return ["outcome" => "ok", "cards" => []]; } }'
		);

		$this->assertFalse(condition: class_exists('OCA\\OpenRegister\\AppHost\\Service\\StoreListing'));

		$discoveryClass = 'OCA\\OpenRegister\\AppHost\\Service\\GenericStoreService';
		$discovery = new $discoveryClass();
		$service = new StoreService(
			discovery: $discovery,
			importService: $this->createMock(originalClassName: ImportService::class),
			appConfig: $this->createMock(originalClassName: IAppConfig::class),
			logger: new NullLogger()
		);

		$service->search(query: 'sales');

		$this->assertSame(expected: 'launchpad', actual: $discovery->seen->appId);
		$this->assertSame(expected: StoreService::REMOTE_SCHEMA, actual: $discovery->seen->schema);
		$this->assertSame(expected: StoreService::DEFAULT_REGISTER, actual: $discovery->seen->defaultRegister);
		$this->assertSame(expected: StoreService::CARD_FIELDS, actual: $discovery->seen->cardFields);
	}//end testAnOlderEngineGetsTheFlatDescriptor()
}//end class
