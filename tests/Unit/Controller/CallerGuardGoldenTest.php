<?php

/**
 * CallerGuardGoldenTest
 *
 * Golden record of the caller guard in front of every action-gated
 * endpoint of the metadata, version, manifest, people, rule and tile
 * controllers: anonymous callers, a session without a user and a denied
 * action are refused with the recorded status and body; an allowed
 * caller is asked for the recorded action and gets past the guard.
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
use OCA\LaunchPad\Controller\DashboardMetadataController;
use OCA\LaunchPad\Controller\DashboardVersionApiController;
use OCA\LaunchPad\Controller\ManifestController;
use OCA\LaunchPad\Controller\MetadataApiCaller;
use OCA\LaunchPad\Controller\PeopleWidgetController;
use OCA\LaunchPad\Controller\RuleApiController;
use OCA\LaunchPad\Controller\TileApiController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Refused and allowed outcomes of the caller guard, endpoint by endpoint.
 */
class CallerGuardGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/caller-guard.json';

	private const CONTROLLERS = [
		DashboardMetadataController::class,
		DashboardVersionApiController::class,
		ManifestController::class,
		PeopleWidgetController::class,
		RuleApiController::class,
		TileApiController::class,
	];

	private const SCENARIOS = ['anonymous', 'no session user', 'action denied', 'allowed'];

	/**
	 * Actions asked of the action guard in the current call.
	 *
	 * @var string[]
	 */
	private array $asked = [];

	/**
	 * Every gated endpoint in every scenario gives the recorded outcome.
	 *
	 * @return void
	 */
	public function testGuardOutcomes(): void {
		$actual = [];
		foreach (self::CONTROLLERS as $class) {
			foreach ($this->gatedEndpoints(class: $class) as $method) {
				foreach (self::SCENARIOS as $scenario) {
					$short = (new ReflectionClass($class))->getShortName();
					$actual[$short.'::'.$method->getName().' / '.$scenario] = $this->outcome(method: $method, scenario: $scenario);
				}
			}
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testGuardOutcomes()

	/**
	 * The public endpoints whose body asks the action guard.
	 *
	 * @param class-string $class The controller.
	 *
	 * @return ReflectionMethod[]
	 */
	private function gatedEndpoints(string $class): array {
		$lines   = file((string) (new ReflectionClass($class))->getFileName());
		$methods = [];
		foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() === true) {
				continue;
			}
			$body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
			if (preg_match('/requireAction|Not authenticated|actionRefusal\(/', $body) === 1) {
				$methods[] = $method;
			}
		}

		return $methods;
	}//end gatedEndpoints()

	/**
	 * Call one endpoint in one scenario.
	 *
	 * @param ReflectionMethod $method   The endpoint.
	 * @param string           $scenario The caller situation.
	 *
	 * @return array<string, mixed> Status and body (or the thrown class) plus the asked actions.
	 */
	private function outcome(ReflectionMethod $method, string $scenario): array {
		$this->asked = [];
		$controller  = $this->controller(class: $method->getDeclaringClass()->getName(), scenario: $scenario);
		try {
			$response = $method->invokeArgs($controller, $this->arguments(method: $method));
			$result   = ['status' => $response->getStatus()];
			if ($scenario !== 'allowed' && method_exists($response, 'getData') === true) {
				$result['body'] = $response->getData();
			}
		} catch (Throwable $e) {
			$result = ['thrown' => get_class($e)];
		}

		$result['asked'] = $this->asked;
		return $result;
	}//end outcome()

	/**
	 * Argument values for an endpoint: defaults where declared, else a plain value per type.
	 *
	 * @param ReflectionMethod $method The endpoint.
	 *
	 * @return array<int, mixed>
	 */
	private function arguments(ReflectionMethod $method): array {
		$args = [];
		foreach ($method->getParameters() as $param) {
			if ($param->isDefaultValueAvailable() === true) {
				$args[] = $param->getDefaultValue();
				continue;
			}
			$type   = $param->getType();
			$name   = ($type instanceof ReflectionNamedType) ? $type->getName() : 'mixed';
			$args[] = match ($name) {
				'int' => 7,
				'string' => 'uuid-a',
				'array' => [],
				'bool' => true,
				default => null,
			};
		}

		return $args;
	}//end arguments()

	/**
	 * The controller with every collaborator mocked and the caller set up for the scenario.
	 *
	 * @param class-string $class    The controller.
	 * @param string       $scenario The caller situation.
	 *
	 * @return object
	 */
	private function controller(string $class, string $scenario): object {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($scenario !== 'no session user') {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
		}
		$session->method('getUser')->willReturn($user);

		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('requireAction')->willReturnCallback(
			function (IUser $user, string $action) use ($scenario): void {
				$this->asked[] = $action;
				if ($scenario === 'action denied') {
					throw new OCSForbiddenException('denied');
				}
			}
		);

		$args = [];
		foreach ((new ReflectionClass($class))->getConstructor()->getParameters() as $param) {
			$type = $param->getType();
			$name = ($type instanceof ReflectionNamedType) ? $type->getName() : 'mixed';
			$args[] = match (true) {
				$param->getName() === 'userId' => ($scenario === 'anonymous') ? null : 'alice',
				$name === ActionAuthService::class => $auth,
				$name === IUserSession::class => $session,
				$name === ActionCaller::class => new ActionCaller(actionAuth: $auth, userSession: $session),
				$name === MetadataApiCaller::class => new MetadataApiCaller(
					sources: $this->createMock(\OCA\LaunchPad\Controller\MetadataSources::class),
					actionAuth: $auth,
					userSession: $session
				),
				default => $this->createMock($name),
			};
		}

		return new $class(...$args);
	}//end controller()
}//end class
