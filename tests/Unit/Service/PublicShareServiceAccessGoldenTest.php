<?php

/**
 * PublicShareService access golden test
 *
 * Pins, on the code before its split, which public-share operations
 * are allowed and which are refused, with every recorded attempt, log
 * line and stored field: share creation (authorisation first, token,
 * password, expiry formats, storage failure), opening a share (unknown,
 * revoked, expired in both stored formats, password gate) and the
 * unlock check. Expiry times are relative to now, never pinned.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\DB\Exception as DbException;
use OCP\IGroupManager;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PublicShareServiceAccessGoldenTest extends TestCase {
	private PublicShareMapper $shareMapper;
	private DashboardMapper $dashMapper;
	private IGroupManager $groupManager;
	private IHasher $hasher;
	private ISecureRandom $secureRandom;
	private IThrottler $throttler;
	private LoggerInterface $logger;
	private WidgetPlacementMapper $placementMapper;

	/** @var list<string> */
	private array $events = [];

	protected function setUp(): void {
		$this->shareMapper = $this->createMock(PublicShareMapper::class);
		$this->dashMapper = $this->createMock(DashboardMapper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->throttler = $this->createMock(IThrottler::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->throttler->method('registerAttempt')->willReturnCallback(function (string $action, string $ip): void {
			$this->events[] = 'attempt:' . $action . ':' . $ip;
		});
		$this->throttler->method('sleepDelayOrThrowOnMax')->willReturnCallback(function (string $ip, string $action): int {
			$this->events[] = 'sleep:' . $action . ':' . $ip;
			return 0;
		});
		$this->hasher->method('hash')->willReturnCallback(static fn (string $message): string => 'hashed:' . $message);
		$this->hasher->method('verify')->willReturnCallback(function (string $message, string $hash): bool {
			$this->events[] = 'verify:' . $message;
			return $hash === 'hashed:' . $message;
		});
	}

	private function service(): PublicShareService {
		return new PublicShareService(
			$this->shareMapper,
			$this->dashMapper,
			$this->groupManager,
			$this->hasher,
			$this->secureRandom,
			$this->throttler,
			$this->logger,
			$this->placementMapper,
		);
	}

	private function ownedDashboard(): void {
		$dashboard = new Dashboard();
		$dashboard->setId(3);
		$dashboard->setUserId('alice');
		$this->dashMapper->method('findByUuid')->willReturn($dashboard);
	}

	private function created(?string $password, ?string $expiresAt): PublicShare {
		$this->ownedDashboard();
		$this->secureRandom->method('generate')->willReturn(str_repeat('t', 64));
		$this->shareMapper->method('insert')->willReturnArgument(0);

		return $this->service()->createPublicShare(dashboardUuid: 'd-1', callerId: 'alice', password: $password, expiresAt: $expiresAt);
	}

	public function testNonOwnerNonAdminIsRefusedBeforeAnythingIsMade(): void {
		$this->ownedDashboard();
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->secureRandom->expects($this->never())->method('generate');
		$this->shareMapper->expects($this->never())->method('insert');
		$this->expectException(OCSForbiddenException::class);

		$this->service()->createPublicShare(dashboardUuid: 'd-1', callerId: 'bob', password: 'x', expiresAt: 'nonsense');
	}

	public function testCreatedShareFields(): void {
		$this->secureRandom->expects($this->once())->method('generate')->with(
			64,
			ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS
		)->willReturn(str_repeat('t', 64));
		$this->logger->expects($this->once())->method('debug')->with(
			'launchpad: public share created for dashboard d-1',
			['app' => 'launchpad']
		);

		$share = $this->created(null, null);

		$this->assertSame(['d-1', str_repeat('t', 64), 'alice', 0, null, null], [
			$share->getDashboardUuid(),
			$share->getToken(),
			$share->getCreatedBy(),
			$share->getViewCount(),
			$share->getPasswordHash(),
			$share->getExpiresAt(),
		]);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$share->getCreatedAt());
	}

	/**
	 * Passwords and the stored hash.
	 *
	 * @return array<string,array{0:?string,1:?string}>
	 */
	public static function passwords(): array {
		return ['none' => [null, null], 'empty' => ['', null], 'set' => ['pw', 'hashed:pw'], 'zero' => ['0', 'hashed:0']];
	}

	/**
	 * @dataProvider passwords
	 */
	public function testPasswordHash(?string $password, ?string $hash): void {
		$this->assertSame($hash, $this->created($password, null)->getPasswordHash());
	}

	/**
	 * Expiry inputs and the stored value (or refusal).
	 *
	 * @return array<string,array{0:?string,1:?string}>
	 */
	public static function expiries(): array {
		return [
			'none'       => [null, null],
			'empty'      => ['', null],
			'atom'       => ['2030-05-06T07:08:09+02:00', '2030-05-06 07:08:09'],
			'zulu'       => ['2030-05-06T07:08:09Z', '2030-05-06 07:08:09'],
			'database'   => ['2030-05-06 07:08:09', '2030-05-06 07:08:09'],
			'date only'  => ['2030-05-06', 'refused'],
			'nonsense'   => ['soon', 'refused'],
		];
	}

	/**
	 * @dataProvider expiries
	 */
	public function testExpiryParsing(?string $expiresAt, ?string $stored): void {
		if ($stored === 'refused') {
			$this->shareMapper->expects($this->never())->method('insert');
			$this->expectException(InvalidArgumentException::class);
			$this->expectExceptionMessage('expiresAt is not a valid date');
		}

		$this->assertSame($stored, $this->created('pw', $expiresAt)->getExpiresAt());
	}

	public function testStorageFailureKeepsOnlyTheExceptionClass(): void {
		$this->ownedDashboard();
		$this->secureRandom->method('generate')->willReturn('tok');
		$this->shareMapper->method('insert')->willThrowException(new DbException('dup tok'));
		$this->logger->expects($this->once())->method('error')->with(
			'launchpad: storing a public share failed',
			['app' => 'launchpad', 'exceptionClass' => DbException::class]
		);
		$this->logger->expects($this->never())->method('debug');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The public share could not be stored');

		$this->service()->createPublicShare(dashboardUuid: 'd-1', callerId: 'alice');
	}

	public function testAdminMayCreateForSomeoneElse(): void {
		$this->ownedDashboard();
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->secureRandom->method('generate')->willReturn('tok');
		$this->shareMapper->method('insert')->willReturnArgument(0);

		$this->assertSame('root', $this->service()->createPublicShare(dashboardUuid: 'd-1', callerId: 'root')->getCreatedBy());
	}

	private function storedShare(?string $revokedAt = null, ?string $expiresAt = null, ?string $hash = null): void {
		$share = new PublicShare();
		$share->setId(11);
		$share->setToken('tok');
		$share->setDashboardUuid('d-1');
		$share->setRevokedAt($revokedAt);
		$share->setExpiresAt($expiresAt);
		$share->setPasswordHash($hash);
		$this->shareMapper->method('findByToken')->willReturn($share);
	}

	private function viewable(): void {
		$this->ownedDashboard();
		$this->shareMapper->method('incrementViewCount')->willReturnCallback(function (int $id, string $token, string $ipAddress): void {
			$this->events[] = 'view:' . $id . ':' . $token . ':' . $ipAddress;
		});
		$this->placementMapper->method('findByDashboardId')->willReturnCallback(fn (int $dashboardId): array => ['placements-of-' . $dashboardId]);
	}

	private function stamp(string $modify, string $format = 'Y-m-d H:i:s'): string {
		return (new DateTime())->modify($modify)->format($format);
	}

	/**
	 * Shares that cannot be opened.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function closedShares(): array {
		return ['unknown' => ['unknown'], 'revoked' => ['revoked'], 'expired' => ['expired'], 'expired iso' => ['expired-iso']];
	}

	/**
	 * @dataProvider closedShares
	 */
	public function testClosedShareIsNotFoundAndRecorded(string $case): void {
		match ($case) {
			'unknown' => $this->shareMapper->method('findByToken')->willThrowException(new DoesNotExistException('x')),
			'revoked' => $this->storedShare(revokedAt: '2026-01-01 00:00:00', expiresAt: $this->stamp('+1 day')),
			'expired' => $this->storedShare(expiresAt: $this->stamp('-1 minute')),
			default => $this->storedShare(expiresAt: $this->stamp('-1 minute', DATE_ATOM)),
		};
		$this->shareMapper->expects($this->never())->method('incrementViewCount');

		try {
			$this->service()->renderShareContent(token: 'tok', ipAddress: '10.0.0.1', password: 'pw');
			$this->fail('expected a refusal');
		} catch (ShareNotFoundException) {
			$this->assertSame(['attempt:launchpad_share_access:10.0.0.1'], $this->events);
		}
	}

	/**
	 * Password gate cases: stored hash, supplied password, outcome, events.
	 *
	 * @return array<string,array{0:?string,1:?string,2:string,3:list<string>}>
	 */
	public static function passwordGates(): array {
		$view = 'view:11:tok:10.0.0.1';
		return [
			'open share'          => [null, null, 'open', [$view]],
			'open share with pw'  => [null, 'pw', 'open', [$view]],
			'missing password'    => ['hashed:pw', null, 'required', []],
			'empty password'      => ['hashed:pw', '', 'required', []],
			'wrong password'      => ['hashed:pw', 'nope', 'required', ['verify:nope', 'attempt:launchpad_share_password:10.0.0.1']],
			'right password'      => ['hashed:pw', 'pw', 'open', ['verify:pw', $view]],
			'future expiry'       => [null, null, 'open', [$view]],
		];
	}

	/**
	 * @dataProvider passwordGates
	 */
	public function testPasswordGate(?string $hash, ?string $password, string $outcome, array $events): void {
		$this->storedShare(expiresAt: $this->stamp('+1 hour'), hash: $hash);
		$this->viewable();

		try {
			$result = $this->service()->renderShareContent(token: 'tok', ipAddress: '10.0.0.1', password: $password);
			$this->assertSame('open', $outcome);
			$this->assertSame([11, 3, ['placements-of-3']], [$result['share']->getId(), $result['dashboard']->getId(), $result['placements']]);
		} catch (SharePasswordRequiredException) {
			$this->assertSame('required', $outcome);
		}

		$this->assertSame($events, $this->events);
	}

	/**
	 * Unlock cases: stored hash, supplied password, result, events.
	 *
	 * @return array<string,array{0:?string,1:string,2:bool,3:list<string>}>
	 */
	public static function unlocks(): array {
		$sleep = 'sleep:launchpad_share_password:10.0.0.1';
		return [
			'no password set' => [null, 'any', true, [$sleep]],
			'right'           => ['hashed:pw', 'pw', true, [$sleep, 'verify:pw']],
			'wrong'           => ['hashed:pw', 'no', false, [$sleep, 'verify:no', 'attempt:launchpad_share_password:10.0.0.1']],
		];
	}

	/**
	 * @dataProvider unlocks
	 */
	public function testUnlock(?string $hash, string $password, bool $result, array $events): void {
		$this->storedShare(hash: $hash);

		$this->assertSame($result, $this->service()->unlockShare(token: 'tok', password: $password, ipAddress: '10.0.0.1'));
		$this->assertSame($events, $this->events);
	}

	public function testUnlockOfAClosedShareSleepsFirst(): void {
		$this->storedShare(revokedAt: '2026-01-01 00:00:00');
		$this->expectException(ShareNotFoundException::class);

		try {
			$this->service()->unlockShare(token: 'tok', password: 'pw', ipAddress: '10.0.0.1');
		} finally {
			$this->assertSame(['sleep:launchpad_share_password:10.0.0.1', 'attempt:launchpad_share_access:10.0.0.1'], $this->events);
		}
	}
}//end class
