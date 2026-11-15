<?php

/**
 * RequestGuardGoldenTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
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

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\AdminBulkController;
use OCA\LaunchPad\Controller\AdminCleanupController;
use OCA\LaunchPad\Controller\AdminDemoShowcasesController;
use OCA\LaunchPad\Controller\AdminOrgNavigationController;
use OCA\LaunchPad\Controller\AdminSettingsController;
use OCA\LaunchPad\Controller\AdminShippedTemplateController;
use OCA\LaunchPad\Controller\AttentionController;
use OCA\LaunchPad\Controller\DashboardLockApiController;
use OCA\LaunchPad\Controller\MetadataAdminController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Calls every endpoint of the admin, attention and lock controllers as an
 * anonymous visitor,
 * as bob (signed in, not an administrator) and as alice (administrator),
 * and pins the response, the users the administrator check ran for, the
 * user and action key of every action check (only alice is granted), and
 * whether the call reached the controller's services. Collaborators are
 * doubles built from the constructor signature (a `userId` parameter gets
 * the session user), so the harness follows a constructor that changes shape. The fixture was recorded with
 * LAUNCHPAD_GOLDEN_RECORD=1.
 *
 * @coversNothing
 */
class RequestGuardGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/request-guard.json';

	private const CONTROLLERS = [
		AdminBulkController::class,
		AdminCleanupController::class,
		AdminDemoShowcasesController::class,
		AdminOrgNavigationController::class,
		AdminSettingsController::class,
		AdminShippedTemplateController::class,
		AttentionController::class,
		DashboardLockApiController::class,
		MetadataAdminController::class,
	];

	private const CALLERS = ['anonymous' => null, 'bob (signed in)' => 'bob', 'alice (admin)' => 'alice'];

	/**
	 * Every endpoint for every caller.
	 *
	 * @return array<string, array{0: class-string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach (self::CONTROLLERS as $class) {
			foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() === true) {
					continue;
				}

				foreach (array_keys(self::CALLERS) as $caller) {
					$short = (new ReflectionClass($class))->getShortName();
					$cases[$short . '::' . $method->getName() . ' / ' . $caller] = [$class, $method->getName(), $caller];
				}
			}
		}

		return $cases;
	}

	/**
	 * The endpoint answers and checks what it did when recorded.
	 *
	 * @param class-string $class  The controller.
	 * @param string       $method The endpoint.
	 * @param string       $caller The caller case.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testEndpointMatchesTheRecording(string $class, string $method, string $caller): void {
		$log = ['isAdmin' => [], 'actions' => [], 'reachedServices' => false];
		$controller = $this->build(class: $class, uid: self::CALLERS[$caller], log: $log);
		try {
			$response = $controller->$method(...$this->arguments(method: new ReflectionMethod($class, $method)));
			$actual = ['status' => $response->getStatus()];
			if ($response->getStatus() === 401 || $response->getStatus() === 403) {
				$actual['data'] = $response->getData();
			}
		} catch (Throwable $e) {
			$actual = ['threw' => $e::class];
		}

		$actual += $log;
		$key = (new ReflectionClass($class))->getShortName() . '::' . $method . ' / ' . $caller;
		if (getenv('LAUNCHPAD_GOLDEN_RECORD') === '1') {
			$recorded = is_file(self::FIXTURE) ? json_decode((string)file_get_contents(self::FIXTURE), true) : [];
			$recorded[$key] = $actual;
			ksort($recorded);
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true)[$key] ?? null;
		$this->assertNotNull($expected, 'no recording for ' . $key);
		$this->assertSame($expected, $actual);
	}

	/**
	 * Build a controller from its constructor signature.
	 *
	 * @param class-string         $class The controller or bundle class.
	 * @param string|null          $uid   The session user.
	 * @param array<string, mixed> $log   The log, written by reference.
	 *
	 * @return object The instance.
	 */
	private function build(string $class, ?string $uid, array &$log): object {
		$args = [];
		foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $param) {
			$type = $param->getType();
			$name = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
			if ($param->getName() === 'userId') {
				$args['userId'] = $uid;
				continue;
			}

			$args[$param->getName()] = $this->double(type: $name, uid: $uid, log: $log, nullable: $param->allowsNull());
		}

		return new $class(...$args);
	}

	/**
	 * A double for one constructor parameter.
	 *
	 * @param string               $type     The parameter type.
	 * @param string|null          $uid      The session user.
	 * @param array<string, mixed> $log      The log, written by reference.
	 * @param bool                 $nullable Whether null is accepted.
	 *
	 * @return mixed The double.
	 */
	private function double(string $type, ?string $uid, array &$log, bool $nullable): mixed {
		if ($type === IUserSession::class) {
			$session = $this->createMock(IUserSession::class);
			$user = null;
			if ($uid !== null) {
				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($uid);
			}

			$session->method('getUser')->willReturn($user);
			return $session;
		}

		if ($type === IGroupManager::class) {
			$groups = $this->createMock(IGroupManager::class);
			$groups->method('isAdmin')->willReturnCallback(static function (string $userId) use (&$log): bool {
				$log['isAdmin'][] = $userId;
				return $userId === 'alice';
			});
			$groups->method('search')->willReturn([]);
			return $groups;
		}

		if (in_array($type, ['string', 'int', 'bool', 'array', 'float', 'mixed'], true) === true) {
			return $nullable === true ? null : match ($type) {
				'string' => 'x',
				'int' => 1,
				'bool' => false,
				'array' => [],
				'float' => 1.0,
				default => null,
			};
		}

		if ($type === ActionAuthService::class) {
			$auth = $this->createMock(ActionAuthService::class);
			$auth->method('requireAction')->willReturnCallback(static function (IUser $user, string $action) use (&$log): void {
				$log['actions'][] = [$user->getUID(), $action];
				if ($user->getUID() !== 'alice') {
					throw new OCSForbiddenException('denied');
				}
			});
			return $auth;
		}

		$reflection = new ReflectionClass($type);
		if ($reflection->isInterface() === false && str_starts_with($type, 'OCA\\LaunchPad\\') === true && str_ends_with($type, 'Service') === false && str_ends_with($type, 'Mapper') === false) {
			return $this->build(class: $type, uid: $uid, log: $log);
		}

		$double = $this->createMock($type);
		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isConstructor() === true || $method->isStatic() === true || $method->isFinal() === true || str_starts_with($method->getName(), '__') === true) {
				continue;
			}

			$double->method($method->getName())->willReturnCallback(function () use (&$log, $type): never {
				$log['reachedServices'] = true;
				throw new \RuntimeException('stop at ' . $type);
			});
		}

		return $double;
	}

	/**
	 * Arguments for an endpoint: a plain value per parameter.
	 *
	 * @param ReflectionMethod $method The endpoint.
	 *
	 * @return array<string, mixed> The arguments.
	 */
	private function arguments(ReflectionMethod $method): array {
		$args = [];
		foreach ($method->getParameters() as $param) {
			if ($param->isDefaultValueAvailable() === true) {
				continue;
			}

			$type = $param->getType();
			$name = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
			$args[$param->getName()] = match ($name) {
				'int' => 1,
				'bool' => false,
				'array' => [],
				default => 'x',
			};
		}

		return $args;
	}
}
