<?php

/**
 * PublicShareService
 *
 * Business logic for creating, listing, revoking, and rendering public
 * dashboard shares. Password verification uses NC's IHasher (BCrypt).
 * Throttling uses IThrottler with IP-global action names per design D1/D2.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\Bruteforce\IThrottler;
use RuntimeException;
use OCP\Security\Bruteforce\MaxDelayReached;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * Service for public-share lifecycle management.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      Constructor wiring only. A share is a security boundary, so this one
 *      service must hold the three mappers it reads plus the four NC security
 *      collaborators the flow requires — IThrottler and MaxDelayReached for
 *      brute-force control, IHasher for the share password, ISecureRandom for
 *      the token — alongside IGroupManager and the logger.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
 */
class PublicShareService {

	/**
	 * Brute-force action name for general share-page access failures.
	 *
	 * @var string
	 */
	public const ACTION_SHARE_ACCESS = 'launchpad_share_access';

	/**
	 * Brute-force action name for wrong-password unlock attempts.
	 *
	 * @var string
	 */
	public const BRUTE_FORCE_ACTION_SHARE_UNLOCK = 'launchpad_share_password';

	/**
	 * Former name of BRUTE_FORCE_ACTION_SHARE_UNLOCK.
	 *
	 * @deprecated Use BRUTE_FORCE_ACTION_SHARE_UNLOCK.
	 *
	 * @var string
	 */
	public const ACTION_SHARE_PASSWORD = self::BRUTE_FORCE_ACTION_SHARE_UNLOCK;

	/**
	 * Constructor.
	 *
	 * @param PublicShareMapper $shareMapper Mapper for public shares.
	 * @param DashboardMapper $dashMapper Dashboard mapper for ownership checks.
	 * @param IGroupManager $groupManager NC group manager for admin checks.
	 * @param IHasher $hasher NC BCrypt hasher.
	 * @param ISecureRandom $secureRandom CSPRNG for token generation.
	 * @param IThrottler $throttler NC brute-force throttler.
	 * @param LoggerInterface $logger PSR-3 logger.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper (public render).
	 */
	public function __construct(
		private readonly PublicShareMapper $shareMapper,
		private readonly DashboardMapper $dashMapper,
		private readonly IGroupManager $groupManager,
		private readonly IHasher $hasher,
		private readonly ISecureRandom $secureRandom,
		private readonly IThrottler $throttler,
		private readonly LoggerInterface $logger,
		private readonly WidgetPlacementMapper $placementMapper,
	) {
	}//end __construct()

	/**
	 * Create a new public share for a dashboard.
	 *
	 * Only the dashboard owner or a NC admin may create shares (REQ-PSHR-001).
	 *
	 * @param string $dashboardUuid Dashboard UUID.
	 * @param string $callerId User ID of the creating user.
	 * @param string|null $password Optional plaintext password (BCrypt-hashed on store).
	 * @param string|null $expiresAt Optional ISO 8601 expiry timestamp.
	 *
	 * @return PublicShare The new share with URL populated.
	 *
	 * @throws DoesNotExistException When dashboard not found.
	 * @throws OCSForbiddenException Via 403 on auth failure.
	 * @throws InvalidArgumentException When expiresAt is given but is not a date.
	 * @throws RuntimeException When the share cannot be stored.
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function createPublicShare(
		string $dashboardUuid,
		string $callerId,
		#[\SensitiveParameter]
		?string $password = null,
		?string $expiresAt = null,
	): PublicShare {
		$dashboard = $this->dashMapper->findByUuid(uuid: $dashboardUuid);
		$this->authorizeShareMutation(dashboard: $dashboard, userId: $callerId);

		$share = $this->newShare(dashboardUuid: $dashboardUuid, callerId: $callerId);
		$this->applyPassword(share: $share, password: $password);
		$this->applyExpiry(share: $share, expiresAt: $expiresAt);

		return $this->storeShare(share: $share, dashboardUuid: $dashboardUuid);
	}//end createPublicShare()

	/**
	 * A new, unsaved share for a dashboard with a fresh token, its creator,
	 * its creation time and no views.
	 *
	 * @param string $dashboardUuid Dashboard UUID.
	 * @param string $callerId      User ID of the creating user.
	 *
	 * @return PublicShare The share.
	 */
	private function newShare(string $dashboardUuid, string $callerId): PublicShare {
		$share = new PublicShare();
		// phpcs:disable CustomSn.Functions.NamedParameters -- Entity magic __call breaks with named args.
		$share->setDashboardUuid($dashboardUuid);
		$share->setToken(
			$this->secureRandom->generate(
				length: 64,
				characters: ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS
			)
		);
		$share->setCreatedBy($callerId);
		$share->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));
		$share->setViewCount(0);
		// phpcs:enable CustomSn.Functions.NamedParameters

		return $share;
	}//end newShare()

	/**
	 * Store the hash of a non-empty password on the share.
	 *
	 * @param PublicShare $share    The new share.
	 * @param string|null $password Optional plaintext password.
	 *
	 * @return void
	 */
	private function applyPassword(
		PublicShare $share,
		#[\SensitiveParameter]
		?string $password,
	): void {
		if ($password !== null && $password !== '') {
			// phpcs:disable CustomSn.Functions.NamedParameters
			$share->setPasswordHash($this->hasher->hash(message: $password));
			// phpcs:enable CustomSn.Functions.NamedParameters
		}
	}//end applyPassword()

	/**
	 * Store a non-empty expiry on the share in the database format.
	 *
	 * @param PublicShare $share     The new share.
	 * @param string|null $expiresAt Optional expiry timestamp.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When expiresAt is given but is not a date.
	 */
	private function applyExpiry(PublicShare $share, ?string $expiresAt): void {
		if ($expiresAt === null || $expiresAt === '') {
			return;
		}

		$parsed = self::parseExpiry(expiresAt: $expiresAt);
		// phpcs:disable CustomSn.Functions.NamedParameters
		$share->setExpiresAt($parsed->format('Y-m-d H:i:s'));
		// phpcs:enable CustomSn.Functions.NamedParameters
	}//end applyExpiry()

	/**
	 * Parse an expiry in the ATOM, `Y-m-d\TH:i:s\Z` or `Y-m-d H:i:s`
	 * format, tried in that order.
	 *
	 * @param string $expiresAt The expiry timestamp.
	 *
	 * @return DateTime The parsed expiry.
	 *
	 * @throws InvalidArgumentException When no format fits.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 *      `DateTime::createFromFormat()` is a PHP built-in named constructor,
	 *      called once per accepted expiry format. There is no
	 *      instance-method equivalent and no collaborator to inject in its
	 *      place.
	 */
	private static function parseExpiry(string $expiresAt): DateTime {
		foreach ([DateTime::ATOM, 'Y-m-d\TH:i:s\Z', 'Y-m-d H:i:s'] as $format) {
			$parsed = DateTime::createFromFormat($format, $expiresAt);
			if ($parsed !== false) {
				return $parsed;
			}
		}

		throw new InvalidArgumentException(message: 'expiresAt is not a valid date');
	}//end parseExpiry()

	/**
	 * Insert the new share and log its creation.
	 *
	 * @param PublicShare $share         The new share.
	 * @param string      $dashboardUuid Dashboard UUID.
	 *
	 * @return PublicShare The stored share.
	 *
	 * @throws RuntimeException When the share cannot be stored.
	 */
	private function storeShare(PublicShare $share, string $dashboardUuid): PublicShare {
		$saved = $this->insertShare(share: $share);

		$this->logger->debug(
			message: sprintf('launchpad: public share created for dashboard %s', $dashboardUuid),
			context: ['app' => 'launchpad']
		);

		return $saved;
	}//end storeShare()

	/**
	 * Insert a share; a storage failure is logged by exception class only.
	 *
	 * @param PublicShare $share The new share.
	 *
	 * @return PublicShare The stored share.
	 *
	 * @throws RuntimeException When the share cannot be stored.
	 */
	private function insertShare(PublicShare $share): PublicShare {
		try {
			return $this->shareMapper->insert(entity: $share);
		} catch (Exception $e) {
			// The database error carries the token and password hash; only its class is kept.
			$this->logger->error(
				message: 'launchpad: storing a public share failed',
				context: ['app' => 'launchpad', 'exceptionClass' => $e::class]
			);
			throw new RuntimeException(message: 'The public share could not be stored');
		}
	}//end insertShare()

	/**
	 * List all active (non-revoked, non-expired) shares for a dashboard.
	 *
	 * @param string $dashboardUuid Dashboard UUID.
	 * @param string $callerId Caller user ID.
	 *
	 * @return PublicShare[]
	 *
	 * @throws DoesNotExistException When dashboard not found.
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function listActiveShares(string $dashboardUuid, string $callerId): array {
		$dashboard = $this->dashMapper->findByUuid(uuid: $dashboardUuid);
		$this->authorizeShareMutation(dashboard: $dashboard, userId: $callerId);

		return $this->shareMapper->findActiveByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);
	}//end listActiveShares()

	/**
	 * Soft-revoke a public share by ID.
	 *
	 * Idempotent: revoking an already-revoked share returns normally.
	 *
	 * @param string $dashboardUuid Dashboard UUID.
	 * @param int $shareId Share primary key.
	 * @param string $callerId Caller user ID.
	 *
	 * @return void
	 *
	 * @throws DoesNotExistException When dashboard not found.
	 * @throws ShareNotFoundException When the share does not belong to this dashboard.
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function revokeShare(
		string $dashboardUuid,
		int $shareId,
		string $callerId,
	): void {
		$dashboard = $this->dashMapper->findByUuid(uuid: $dashboardUuid);
		$this->authorizeShareMutation(dashboard: $dashboard, userId: $callerId);

		// Verify the share belongs to this dashboard.
		$shares = $this->shareMapper->findByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);

		$found = false;
		foreach ($shares as $s) {
			if ($s->getId() === $shareId) {
				$found = true;
				break;
			}
		}

		if ($found === false) {
			throw new ShareNotFoundException();
		}

		$this->shareMapper->softRevoke(id: $shareId);
	}//end revokeShare()

	/**
	 * Render a share's dashboard content for anonymous access.
	 *
	 * Validates token, expiry, revocation, and optional password.
	 * Increments view count (debounced by IP within 60-second window).
	 *
	 * @param string $token The share token.
	 * @param string $ipAddress Client IP address for debouncing and throttling.
	 * @param string|null $password Plaintext password supplied by the client.
	 *
	 * @return array{share: PublicShare, dashboard: Dashboard, placements: WidgetPlacement[]}
	 *
	 * @throws ShareNotFoundException When token invalid, revoked, or expired.
	 * @throws SharePasswordRequiredException When password is required but not supplied.
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function renderShareContent(
		string $token,
		string $ipAddress,
		#[\SensitiveParameter]
		?string $password = null,
	): array {
		$share = $this->resolveActiveShare(token: $token, ipAddress: $ipAddress);

		// Password gate.
		if ($this->lacksPassword(share: $share, password: $password) === true) {
			throw new SharePasswordRequiredException();
		}

		if ($this->rejectsPassword(share: $share, password: $password) === true) {
			$this->registerPasswordFailure(ipAddress: $ipAddress);
			throw new SharePasswordRequiredException();
		}

		return $this->shareContent(share: $share, visit: new PublicShareVisit(token: $token, ipAddress: $ipAddress));
	}//end renderShareContent()

	/**
	 * Whether a password-protected share was opened without a password.
	 *
	 * @param PublicShare $share    The active share.
	 * @param string|null $password The supplied password.
	 *
	 * @return bool True when a password is needed but missing.
	 */
	private function lacksPassword(
		PublicShare $share,
		#[\SensitiveParameter]
		?string $password,
	): bool {
		return $share->getPasswordHash() !== null && ($password === null || $password === '');
	}//end lacksPassword()

	/**
	 * Whether a password-protected share was opened with a wrong password.
	 *
	 * @param PublicShare $share    The active share.
	 * @param string|null $password The supplied, non-empty password.
	 *
	 * @return bool True when the password does not match.
	 */
	private function rejectsPassword(
		PublicShare $share,
		#[\SensitiveParameter]
		?string $password,
	): bool {
		return $share->getPasswordHash() !== null
			&& $this->hasher->verify(
				message: (string)$password,
				hash: (string)$share->getPasswordHash()
			) === false;
	}//end rejectsPassword()

	/**
	 * Record a failed password attempt for throttling.
	 *
	 * @param string $ipAddress Client IP address.
	 *
	 * @return void
	 */
	private function registerPasswordFailure(string $ipAddress): void {
		$this->throttler->registerAttempt(
			action: self::BRUTE_FORCE_ACTION_SHARE_UNLOCK,
			ip: $ipAddress
		);
	}//end registerPasswordFailure()

	/**
	 * Count the visit and answer with the share's dashboard and placements.
	 *
	 * @param PublicShare      $share The opened share.
	 * @param PublicShareVisit $visit The visit's token and IP address.
	 *
	 * @return array{share: PublicShare, dashboard: Dashboard, placements: WidgetPlacement[]}
	 */
	private function shareContent(PublicShare $share, PublicShareVisit $visit): array {
		$dashboard = $this->dashMapper->findByUuid(uuid: (string)$share->getDashboardUuid());

		$this->shareMapper->incrementViewCount(
			id: (int)$share->getId(),
			token: $visit->token,
			ipAddress: $visit->ipAddress
		);

		$placements = $this->placementMapper->findByDashboardId(
			dashboardId: (int)$dashboard->getId()
		);

		return ['share' => $share, 'dashboard' => $dashboard, 'placements' => $placements];
	}//end shareContent()

	/**
	 * Verify a password for a password-protected share (unlock endpoint).
	 *
	 * Applies `launchpad_share_password` throttle before verification.
	 *
	 * @param string $token The share token.
	 * @param string $password The supplied plaintext password.
	 * @param string $ipAddress Client IP address.
	 *
	 * @return bool True on success; false on wrong password.
	 *
	 * @throws ShareNotFoundException When token does not exist or is inactive.
	 * @throws MaxDelayReached When throttle limit is exceeded (429).
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function unlockShare(
		string $token,
		#[\SensitiveParameter]
		string $password,
		string $ipAddress,
	): bool {
		// Throttle check before any DB query to prevent enumeration timing attacks.
		$this->throttler->sleepDelayOrThrowOnMax(
			ip: $ipAddress,
			action: self::BRUTE_FORCE_ACTION_SHARE_UNLOCK
		);

		$share = $this->resolveActiveShare(token: $token, ipAddress: $ipAddress);

		if ($share->getPasswordHash() === null) {
			// No password on this share — unlock is trivially successful.
			return true;
		}

		$isValid = $this->hasher->verify(
			message: $password,
			hash: (string)$share->getPasswordHash()
		);

		if ($isValid === false) {
			$this->registerPasswordFailure(ipAddress: $ipAddress);
		}

		return $isValid;
	}//end unlockShare()

	/**
	 * Resolve a share that is active (not revoked, not expired).
	 *
	 * Throws ShareNotFoundException for any failure to avoid information leakage.
	 *
	 * @param string $token The share token.
	 * @param string $ipAddress Client IP (registered on access failure for D1 throttling).
	 *
	 * @return PublicShare
	 *
	 * @throws ShareNotFoundException
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 *      `DateTime::createFromFormat()` is a PHP built-in named constructor;
	 *      there is no instance-method equivalent to call and no collaborator
	 *      to inject in its place.
	 */
	private function resolveActiveShare(string $token, string $ipAddress): PublicShare {
		$share = $this->findShareByToken(token: $token, ipAddress: $ipAddress);

		if ($share->getRevokedAt() !== null || $this->isExpired(share: $share) === true) {
			$this->refuseAccess(ipAddress: $ipAddress);
		}

		return $share;
	}//end resolveActiveShare()

	/**
	 * The share with a token; an unknown token is recorded and refused.
	 *
	 * @param string $token     The share token.
	 * @param string $ipAddress Client IP address.
	 *
	 * @return PublicShare The share.
	 *
	 * @throws ShareNotFoundException When no share has the token.
	 */
	private function findShareByToken(string $token, string $ipAddress): PublicShare {
		try {
			return $this->shareMapper->findByToken(token: $token);
		} catch (DoesNotExistException) {
			$this->refuseAccess(ipAddress: $ipAddress);
		}
	}//end findShareByToken()

	/**
	 * Record a failed share access for throttling and refuse it.
	 *
	 * @param string $ipAddress Client IP address.
	 *
	 * @return never
	 *
	 * @throws ShareNotFoundException Always.
	 */
	private function refuseAccess(string $ipAddress): never {
		$this->throttler->registerAttempt(
			action: self::ACTION_SHARE_ACCESS,
			ip: $ipAddress
		);
		throw new ShareNotFoundException();
	}//end refuseAccess()

	/**
	 * Whether a share's expiry has passed.
	 *
	 * @param PublicShare $share The share.
	 *
	 * @return bool True when it has an expiry in the past.
	 */
	private function isExpired(PublicShare $share): bool {
		if ($share->getExpiresAt() === null) {
			return false;
		}

		$expiry = self::parseStoredExpiry(expiresAt: (string)$share->getExpiresAt());

		return $expiry < new DateTime();
	}//end isExpired()

	/**
	 * Parse a stored expiry: the database format, else any format
	 * `DateTime` understands.
	 *
	 * @param string $expiresAt The stored expiry.
	 *
	 * @return DateTime The expiry.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 *      `DateTime::createFromFormat()` is a PHP built-in named constructor;
	 *      there is no instance-method equivalent to call and no collaborator
	 *      to inject in its place.
	 */
	private static function parseStoredExpiry(string $expiresAt): DateTime {
		$expiry = DateTime::createFromFormat('Y-m-d H:i:s', $expiresAt);
		if ($expiry === false) {
			return new DateTime($expiresAt);
		}

		return $expiry;
	}//end parseStoredExpiry()

	/**
	 * Assert that the calling user may create/revoke shares for a dashboard.
	 *
	 * Passes for the dashboard owner or any NC admin.
	 *
	 * @param Dashboard $dashboard The dashboard to check.
	 * @param string $userId The calling user ID.
	 *
	 * @return void
	 *
	 * @throws \OCP\AppFramework\OCS\OCSForbiddenException Via 403.
	 *
	 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
	 */
	public function authorizeShareMutation(Dashboard $dashboard, string $userId): void {
		$isOwner = ($dashboard->getUserId() === $userId);
		$isAdmin = $this->groupManager->isAdmin(userId: $userId);

		if ($isOwner === false && $isAdmin === false) {
			throw new OCSForbiddenException('Not authorized');
		}
	}//end authorizeShareMutation()
}//end class
