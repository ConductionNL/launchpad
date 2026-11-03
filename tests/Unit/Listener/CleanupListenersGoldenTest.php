<?php

/**
 * Cleanup listeners golden test
 *
 * Pins what each dashboard-deleted cleanup listener does: the collaborator
 * call it makes and the log line it writes, on success, on failure and for
 * an event it does not handle, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * The cleanup listeners, case by case.
 *
 * @covers \OCA\LaunchPad\Listener\LocksListener
 * @covers \OCA\LaunchPad\Listener\MetadataValuesListener
 * @covers \OCA\LaunchPad\Listener\ReactionsListener
 * @covers \OCA\LaunchPad\Listener\VersionsListener
 * @covers \OCA\LaunchPad\Listener\PublicSharesListener
 * @covers \OCA\LaunchPad\Listener\ViewAnalyticsListener
 * @covers \OCA\LaunchPad\Listener\DashboardSharesListener
 * @covers \OCA\LaunchPad\Listener\WidgetPlacementsListener
 * @covers \OCA\LaunchPad\Listener\TranslationsListener
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class CleanupListenersGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/cleanup-listeners.json';

	/**
	 * One case per listener.
	 *
	 * @return array<string, array{0: class-string}>
	 */
	public static function listeners(): array {
		$cases = [];
		$names = [
			'Locks', 'MetadataValues', 'Reactions', 'Versions', 'PublicShares',
			'ViewAnalytics', 'DashboardShares', 'WidgetPlacements', 'Translations',
		];
		foreach ($names as $name) {
			$cases[$name] = ['OCA\\LaunchPad\\Listener\\' . $name . 'Listener'];
		}

		return $cases;
	}

	/**
	 * Each listener makes the recorded calls and writes the recorded logs.
	 *
	 * @param class-string $class The listener.
	 *
	 * @return void
	 */
	#[DataProvider('listeners')]
	public function testListenerMatchesTheRecordedFixture(string $class): void {
		$deleted = static fn (?int $id): DashboardDeletedEvent => new DashboardDeletedEvent(
			dashboardUuid: 'd-1',
			ownerUserId: 'alice',
			type: 'user',
			deletedAt: new DateTimeImmutable('2001-02-03 04:05:06'),
			dashboardId: $id
		);
		$cases = [
			'deleted'         => [$deleted(null), false],
			'deleted with id' => [$deleted(12), false],
			'failing'         => [$deleted(12), true],
			'other event'     => [new Event(), false],
		];

		$actual = [];
		foreach ($cases as $name => [$event, $fail]) {
			$log = [];
			$this->listener(class: $class, log: $log, fail: $fail)->handle(event: $event);
			$actual[$name] = $log;
		}

		$short = substr(strrchr($class, '\\'), 1);
		$all = [];
		if (is_file(self::FIXTURE) === true) {
			$all = json_decode((string)file_get_contents(self::FIXTURE), true);
		}
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$all[$short] = $actual;
			ksort($all);
			file_put_contents(self::FIXTURE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$this->assertSame(expected: $all[$short] ?? null, actual: $actual);
	}

	/**
	 * Build the listener over recording doubles.
	 *
	 * @param class-string       $class The listener.
	 * @param array<int, string> $log   The record, by reference.
	 * @param bool               $fail  Whether the collaborator throws.
	 *
	 * @return object The listener.
	 */
	private function listener(string $class, array &$log, bool $fail): object {
		$args = [];
		foreach ((new ReflectionClass($class))->getConstructor()->getParameters() as $param) {
			$name = (string)$param->getType();
			$double = $this->getMockBuilder(className: $name)->disableOriginalConstructor()->getMock();
			foreach ((new ReflectionClass($name))->getMethods() as $method) {
				if ($method->isPublic() === false || $method->isStatic() === true || $method->isFinal() === true || $method->isConstructor() === true) {
					continue;
				}

				$methodName = $method->getName();
				$double->method($methodName)->willReturnCallback(
					static function (mixed ...$callArgs) use ($methodName, $name, $fail, &$log): mixed {
						$log[] = substr(strrchr($name, '\\'), 1) . '.' . $methodName . ' ' . json_encode($callArgs);
						if ($fail === true && $name !== LoggerInterface::class) {
							throw new RuntimeException('boom');
						}

						if ($name === LoggerInterface::class) {
							return null;
						}

						return 3;
					}
				);
			}

			$args[] = $double;
		}

		return new $class(...$args);
	}
}
