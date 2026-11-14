<?php

/**
 * PublicStartPage
 *
 * The public start page: one existing public link that signed-out visitors
 * see instead of the login form, chosen and switched on by an administrator.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Chooses, checks and reports the public start page (design D1).
 *
 * A link with a password, a revoked link and a link whose expiry has passed
 * cannot be chosen, so the start page never asks a visitor for a password.
 * When the chosen link later becomes one of those, the start page is off
 * ({@see active()} answers false) and {@see status()} says why; the stored
 * choice is kept so the administrator sees what was chosen.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */
class PublicStartPage {

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings The admin settings.
	 * @param PublicShareMapper  $shares   The public links.
	 * @param ITimeFactory       $time     The clock an expiry is measured against.
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly PublicShareMapper $shares,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * The start page as configured: switched on or not, the chosen link, whether it is live, and why not.
	 *
	 * @return array{enabled: bool, token: string, active: bool, reason: string|null}
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function status(): array {
		$enabled = ($this->settings->getValue(key: AdminSettingKey::PUBLIC_START_ENABLED->value, default: false) === true);
		$token = (string)$this->settings->getValue(key: AdminSettingKey::PUBLIC_START_SHARE_TOKEN->value, default: '');

		$reason = 'off';
		if ($enabled === true) {
			$reason = 'no-link';
			if ($token !== '') {
				$reason = $this->refusal(share: $this->find(token: $token));
			}
		}

		return ['enabled' => $enabled, 'token' => $token, 'active' => ($reason === null), 'reason' => $reason];
	}//end status()

	/**
	 * The link the start page shows, or null when it is off or its link can no longer be shown.
	 *
	 * @return PublicShare|null
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function active(): ?PublicShare {
		$status = $this->status();
		if ($status['active'] === false) {
			return null;
		}

		return $this->find(token: $status['token']);
	}//end active()

	/**
	 * Choose the link and switch the start page on or off.
	 *
	 * An empty token clears the choice, and is only accepted with the page off.
	 *
	 * @param bool   $enabled Whether the start page is on.
	 * @param string $token   The chosen link's token, or '' for none.
	 *
	 * @return array{enabled: bool, token: string, active: bool, reason: string|null} The new status.
	 *
	 * @throws InvalidArgumentException When the link cannot be the start page, or the page is switched on without one.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function configure(bool $enabled, string $token): array {
		$token = trim($token);
		if ($token === '' && $enabled === true) {
			throw new InvalidArgumentException(message: 'Choose a public link before switching the start page on.');
		}

		if ($token !== '') {
			$reason = $this->refusal(share: $this->find(token: $token));
			if ($reason !== null) {
				throw new InvalidArgumentException(message: self::message(reason: $reason));
			}
		}

		$this->settings->setSetting(key: AdminSettingKey::PUBLIC_START_SHARE_TOKEN->value, value: $token);
		$this->settings->setSetting(key: AdminSettingKey::PUBLIC_START_ENABLED->value, value: $enabled);

		return $this->status();
	}//end configure()

	/**
	 * The links that may be chosen: not revoked, not expired, no password.
	 *
	 * @return list<array{token: string, dashboardUuid: string, expiresAt: string|null}>
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function candidates(): array {
		$candidates = [];
		foreach ($this->shares->findStartPageCandidates() as $share) {
			if ($this->refusal(share: $share) === null) {
				$candidates[] = [
					'token' => (string)$share->getToken(),
					'dashboardUuid' => (string)$share->getDashboardUuid(),
					'expiresAt' => $share->getExpiresAt(),
				];
			}
		}

		return $candidates;
	}//end candidates()

	/**
	 * Why a link cannot be the start page, or null when it can.
	 *
	 * @param PublicShare|null $share The link, or null when the token names none.
	 *
	 * @return string|null `not-found`, `revoked`, `password` or `expired`.
	 */
	private function refusal(?PublicShare $share): ?string {
		if ($share === null) {
			return 'not-found';
		}

		if ($share->getRevokedAt() !== null) {
			return 'revoked';
		}

		if ($share->getPasswordHash() !== null && $share->getPasswordHash() !== '') {
			return 'password';
		}

		$expiresAt = $share->getExpiresAt();
		if ($expiresAt !== null && $expiresAt !== '') {
			$expiry = DateTime::createFromFormat('Y-m-d H:i:s', $expiresAt);
			if ($expiry === false || $expiry <= $this->time->getDateTime()) {
				return 'expired';
			}
		}

		return null;
	}//end refusal()

	/**
	 * The share with a token, or null.
	 *
	 * @param string $token The token.
	 *
	 * @return PublicShare|null
	 */
	private function find(string $token): ?PublicShare {
		try {
			return $this->shares->findByToken(token: $token);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}//end find()

	/**
	 * The refusal an administrator reads.
	 *
	 * @param string $reason The refusal reason.
	 *
	 * @return string
	 */
	private static function message(string $reason): string {
		return match ($reason) {
			'revoked' => 'This public link has been revoked and cannot be the start page.',
			'password' => 'A public link with a password cannot be the start page.',
			'expired' => 'This public link has expired and cannot be the start page.',
			default => 'There is no public link with this token.',
		};
	}//end message()
}//end class
