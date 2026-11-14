<?php

/**
 * PublicStartPage Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Controller\PublicStartController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Service\PublicStartPage;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Choosing a link, refusing the ones a visitor could not open, and switching off when the link goes bad.
 */
#[CoversClass(PublicStartPage::class)]
#[CoversClass(PublicStartController::class)]
class PublicStartPageTest extends TestCase {

	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Public links by token.
	 *
	 * @var array<string, PublicShare>
	 */
	private array $links = [];

	/**
	 * A public link.
	 *
	 * @param string      $token    The token.
	 * @param string|null $password The password hash.
	 * @param string|null $expires  The expiry, `Y-m-d H:i:s`.
	 * @param string|null $revoked  When it was revoked.
	 *
	 * @return void
	 */
	private function link(string $token, ?string $password = null, ?string $expires = null, ?string $revoked = null): void {
		$share = new PublicShare();
		$share->setToken($token);
		$share->setDashboardUuid('dash-' . $token);
		$share->setPasswordHash($password);
		$share->setExpiresAt($expires);
		$share->setRevokedAt($revoked);
		$this->links[$token] = $share;
	}//end link()

	/**
	 * The service over in-memory settings and links, at 1 June 2026 noon.
	 *
	 * @return PublicStartPage
	 */
	private function service(): PublicStartPage {
		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => ($this->stored[$key] ?? $default));
		$settings->method('setSetting')->willReturnCallback(function (string $key, mixed $value): AdminSetting {
			$this->stored[$key] = $value;
			return new AdminSetting();
		});
		$shares = $this->createMock(PublicShareMapper::class);
		$shares->method('findByToken')->willReturnCallback(function (string $token): PublicShare {
			if (isset($this->links[$token]) === false) {
				throw new DoesNotExistException('no share');
			}

			return $this->links[$token];
		});
		$shares->method('findStartPageCandidates')->willReturnCallback(fn (): array => array_values($this->links));
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new DateTime('2026-06-01 12:00:00'));

		return new PublicStartPage(settings: $settings, shares: $shares, time: $time);
	}//end service()

	/**
	 * The library link becomes the start page and is live.
	 *
	 * @return void
	 */
	public function testAnOpenLinkBecomesTheStartPage(): void {
		$this->link(token: 'noord', expires: '2026-12-31 00:00:00');

		$status = $this->service()->configure(enabled: true, token: 'noord');

		$this->assertSame(['enabled' => true, 'token' => 'noord', 'active' => true, 'reason' => null], $status);
		$this->assertSame('noord', $this->service()->active()->getToken());
	}//end testAnOpenLinkBecomesTheStartPage()

	/**
	 * A protected, revoked, expired or unknown link is refused with 400 and nothing is stored.
	 *
	 * @return void
	 */
	public function testALinkAVisitorCouldNotOpenIsRefused(): void {
		$this->link(token: 'protected', password: '$2y$hash');
		$this->link(token: 'revoked', revoked: '2026-05-01 00:00:00');
		$this->link(token: 'expired', expires: '2026-06-01 11:59:59');
		$cases = [
			'protected' => 'A public link with a password cannot be the start page.',
			'revoked' => 'This public link has been revoked and cannot be the start page.',
			'expired' => 'This public link has expired and cannot be the start page.',
			'unknown' => 'There is no public link with this token.',
		];

		foreach ($cases as $token => $message) {
			try {
				$this->service()->configure(enabled: true, token: $token);
				$this->fail('Accepted ' . $token);
			} catch (InvalidArgumentException $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}

		$this->assertSame([], $this->stored);

		$response = (new PublicStartController(request: $this->createMock(IRequest::class), startPage: $this->service()))->update(enabled: true, token: 'protected');
		$this->assertSame([400, ['message' => $cases['protected']]], [$response->getStatus(), $response->getData()]);
	}//end testALinkAVisitorCouldNotOpenIsRefused()

	/**
	 * Switching on without a link is refused; switching off clears nothing it does not have to.
	 *
	 * @return void
	 */
	public function testSwitchingOnNeedsALink(): void {
		$this->expectExceptionMessage('Choose a public link before switching the start page on.');

		$this->service()->configure(enabled: true, token: ' ');
	}//end testSwitchingOnNeedsALink()

	/**
	 * When the chosen link is later protected, revoked or expires, the start page is off and says why.
	 *
	 * @return void
	 */
	public function testTheStartPageSwitchesItselfOffAndSaysWhy(): void {
		$this->link(token: 'noord', expires: '2026-12-31 00:00:00');
		$this->service()->configure(enabled: true, token: 'noord');

		$this->links['noord']->setPasswordHash('$2y$hash');
		$this->assertSame(['enabled' => true, 'token' => 'noord', 'active' => false, 'reason' => 'password'], $this->service()->status());
		$this->assertNull($this->service()->active());

		$this->links['noord']->setPasswordHash(null);
		$this->links['noord']->setExpiresAt('2026-06-01 12:00:00');
		$this->assertSame('expired', $this->service()->status()['reason']);

		unset($this->links['noord']);
		$this->assertSame('not-found', $this->service()->status()['reason']);
	}//end testTheStartPageSwitchesItselfOffAndSaysWhy()

	/**
	 * Off by default; switched off with a link kept, the page is off and the link stays chosen.
	 *
	 * @return void
	 */
	public function testOffByDefaultAndWhenSwitchedOff(): void {
		$this->assertSame(['enabled' => false, 'token' => '', 'active' => false, 'reason' => 'off'], $this->service()->status());

		$this->link(token: 'noord');
		$this->service()->configure(enabled: false, token: 'noord');
		$this->assertSame(['enabled' => false, 'token' => 'noord', 'active' => false, 'reason' => 'off'], $this->service()->status());
		$this->assertNull($this->service()->active());
	}//end testOffByDefaultAndWhenSwitchedOff()

	/**
	 * Only open links are offered, and both routes are for LaunchPad administrators.
	 *
	 * @return void
	 */
	public function testOnlyOpenLinksAreOfferedToAdministrators(): void {
		$this->link(token: 'noord');
		$this->link(token: 'protected', password: '$2y$hash');
		$this->link(token: 'expired', expires: '2026-01-01 00:00:00');

		$response = (new PublicStartController(request: $this->createMock(IRequest::class), startPage: $this->service()))->show();

		$this->assertSame([['token' => 'noord', 'dashboardUuid' => 'dash-noord', 'expiresAt' => null]], $response->getData()['candidates']);
		foreach (['show', 'update'] as $method) {
			$this->assertCount(1, (new ReflectionMethod(PublicStartController::class, $method))->getAttributes(AuthorizedAdminSetting::class));
		}
	}//end testOnlyOpenLinksAreOfferedToAdministrators()
}//end class
