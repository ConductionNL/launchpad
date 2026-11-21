<?php

/**
 * DashboardTranslationService
 *
 * Service that owns the per-language content variants for dashboards —
 * create / update / delete / promote-primary plus the locale-resolution
 * lookup used by the dashboard read endpoints. REQ-DASH-038..044
 * (dashboard-language-content).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IDBConnection;
use Throwable;

/**
 * Service for managing dashboard language variants.
 */
class DashboardTranslationService {
	/**
	 * Sentinel error message: language variant already exists for the
	 * dashboard. Mapped to HTTP 409 by the controller. REQ-DASH-040.
	 *
	 * @var string
	 */
	public const ERR_LANGUAGE_EXISTS = 'Language variant already exists';

	/**
	 * Sentinel error message: cannot delete the only remaining variant.
	 * Mapped to HTTP 400 by the controller. REQ-DASH-041.
	 *
	 * @var string
	 */
	public const ERR_LAST_VARIANT = 'Cannot delete the only language variant';

	/**
	 * Sentinel error message: cannot delete the primary variant directly
	 * — promote another variant first. REQ-DASH-041.
	 *
	 * @var string
	 */
	public const ERR_DELETE_PRIMARY = 'Cannot delete the primary variant; promote another variant first';

	/**
	 * Sentinel error message: language code is missing or empty after
	 * normalisation. REQ-DASH-040.
	 *
	 * @var string
	 */
	public const ERR_INVALID_LANGUAGE = 'Language code is required';

	/**
	 * Constructor
	 *
	 * @param DashboardTranslationMapper $translationMapper Translation mapper.
	 * @param IDBConnection $db DB connection
	 *                          for the
	 *                          transactional
	 *                          promote-primary
	 *                          flip.
	 * @param IConfig $config Nextcloud
	 *                        per-user
	 *                        preference
	 *                        storage; used
	 *                        to read
	 *                        `core/lang`
	 *                        for new
	 *                        dashboard
	 *                        auto-seed.
	 */
	public function __construct(
		private readonly DashboardTranslationMapper $translationMapper,
		private readonly IDBConnection $db,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Get every translation variant for a dashboard.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 *
	 * @return DashboardTranslation[] The variants.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function listVariants(string $dashboardUuid): array {
		return $this->translationMapper->findByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);
	}//end listVariants()

	/**
	 * List the available language codes for a dashboard, sorted ASC.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 *
	 * @return string[] The language codes.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function listAvailableLanguages(string $dashboardUuid): array {
		$variants = $this->listVariants(dashboardUuid: $dashboardUuid);
		$languages = [];
		foreach ($variants as $variant) {
			$code = (string)$variant->getLanguageCode();
			if ($code !== '') {
				$languages[] = $code;
			}
		}

		sort($languages);
		return $languages;
	}//end listAvailableLanguages()

	/**
	 * Resolve the best translation for a dashboard given a viewer's
	 * locale. Returns the matched variant + an `isFallback` flag
	 * indicating whether the primary fallback was used. Returns null
	 * when the dashboard has no variants at all.
	 *
	 * REQ-DASH-039.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $preferredLanguage The viewer's raw locale.
	 *
	 * @return array{translation: DashboardTranslation, isFallback: bool}|null
	 *                                                                         The matched variant or null when no variants exist.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function resolveForLocale(
		string $dashboardUuid,
		string $preferredLanguage,
	): array|null {
		$exact = $this->exactVariant(dashboardUuid: $dashboardUuid, preferredLanguage: $preferredLanguage);
		if ($exact !== null) {
			return [
				'translation' => $exact,
				'isFallback' => false,
			];
		}

		$primary = $this->translationMapper->findPrimaryByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);
		if ($primary === null) {
			return null;
		}

		return [
			'translation' => $primary,
			'isFallback' => true,
		];
	}//end resolveForLocale()

	/**
	 * The variant in exactly the preferred language; null when the
	 * language normalises to nothing or has no variant.
	 *
	 * @param string $dashboardUuid     The dashboard UUID.
	 * @param string $preferredLanguage The caller's raw language preference.
	 *
	 * @return DashboardTranslation|null The variant, or null.
	 */
	private function exactVariant(string $dashboardUuid, string $preferredLanguage): DashboardTranslation|null {
		$normalised = DashboardTranslationMapper::normaliseLanguageCode(
			raw: $preferredLanguage
		);
		if ($normalised === '') {
			return null;
		}

		return $this->translationMapper->findByDashboardUuidAndLanguage(
			dashboardUuid: $dashboardUuid,
			languageCode: $normalised
		);
	}//end exactVariant()

	/**
	 * Auto-seed a primary translation for a freshly-created dashboard.
	 *
	 * Called by {@see DashboardService::createDashboard()} immediately
	 * after the new row is persisted. The seed row carries the
	 * dashboard's name + description verbatim, an empty widget tree,
	 * and `isPrimary = 1`. The language code is the dashboard owner's
	 * Nextcloud locale, normalised to the 2-character base code, with
	 * a fallback to {@see DashboardTranslation::DEFAULT_LANGUAGE} when
	 * the user has no `core/lang` preference set.
	 *
	 * REQ-DASH-038, REQ-DASH-044.
	 *
	 * @param Dashboard $dashboard The newly-persisted dashboard.
	 *
	 * @return DashboardTranslation The seeded primary translation.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function seedPrimaryFor(Dashboard $dashboard): DashboardTranslation {
		$uuid = (string)$dashboard->getUuid();
		if ($uuid === '') {
			throw new InvalidArgumentException(
				message: 'Cannot seed translation: dashboard has no UUID'
			);
		}

		// Already seeded — degrade to a no-op to keep the call
		// idempotent for callers that retry.
		$existing = $this->translationMapper->findPrimaryByDashboardUuid(
			dashboardUuid: $uuid
		);
		if ($existing !== null) {
			return $existing;
		}

		$translation = $this->materialiseLegacyVariant(dashboard: $dashboard);
		self::stampCreated(translation: $translation);

		return $this->translationMapper->insert(entity: $translation);
	}//end seedPrimaryFor()

	/**
	 * Stamp a new translation's created and updated time with now.
	 *
	 * @param DashboardTranslation $translation The new translation.
	 *
	 * @return void
	 */
	private static function stampCreated(DashboardTranslation $translation): void {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$translation->setCreatedAt($now);
		$translation->setUpdatedAt($now);
	}//end stampCreated()

	/**
	 * Create a new translation variant for a dashboard, optionally
	 * seeded from an existing variant. REQ-DASH-040.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode The (raw) language code; will
	 *                             be normalised before storage.
	 * @param array $content Optional `name`, `description` and
	 *                       `widgetTreeJson` (explicit values win over the
	 *                       seed) and `copyFromLanguage`, the language code
	 *                       to seed from (`'primary'` or another language;
	 *                       the primary variant when null).
	 *
	 * @return DashboardTranslation The created variant.
	 *
	 * @throws InvalidArgumentException When the language code is empty
	 *                                  after normalisation.
	 * @throws Exception When a row already exists for
	 *                   the same `(uuid, language)` pair.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function createVariant(string $dashboardUuid, string $languageCode, array $content=[]): DashboardTranslation {
		$content += ['name' => null, 'description' => null, 'widgetTreeJson' => null, 'copyFromLanguage' => null];

		$translation = new DashboardTranslation();
		$translation->setDashboardUuid($dashboardUuid);
		$translation->setLanguageCode($this->unusedLanguageCode(dashboardUuid: $dashboardUuid, languageCode: $languageCode));
		$this->fillVariantContent(translation: $translation, content: $content);
		$translation->setIsPrimary(0);
		self::stampCreated(translation: $translation);

		return $this->translationMapper->insert(entity: $translation);
	}//end createVariant()

	/**
	 * The normalised language code of a new variant.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode  The requested language code.
	 *
	 * @return string The normalised code.
	 *
	 * @throws InvalidArgumentException When the code normalises to nothing.
	 * @throws Exception               When the dashboard already has that language.
	 */
	private function unusedLanguageCode(string $dashboardUuid, string $languageCode): string {
		$normalised = DashboardTranslationMapper::normaliseLanguageCode(
			raw: $languageCode
		);
		if ($normalised === '') {
			throw new InvalidArgumentException(
				message: self::ERR_INVALID_LANGUAGE
			);
		}

		$existing = $this->translationMapper->findByDashboardUuidAndLanguage(
			dashboardUuid: $dashboardUuid,
			languageCode: $normalised
		);
		if ($existing !== null) {
			throw new Exception(message: self::ERR_LANGUAGE_EXISTS);
		}

		return $normalised;
	}//end unusedLanguageCode()

	/**
	 * Fill a new variant's name, description and widget tree: each given
	 * value, else the seed's (the primary or the `copyFromLanguage` variant).
	 *
	 * @param DashboardTranslation $translation The new variant.
	 * @param array<string, mixed> $content     The given content and `copyFromLanguage`.
	 *
	 * @return void
	 */
	private function fillVariantContent(DashboardTranslation $translation, array $content): void {
		$seed = $this->resolveSeed(
			dashboardUuid: (string)$translation->getDashboardUuid(),
			copyFromLanguage: $content['copyFromLanguage']
		);

		$translation->setName(
			$this->coalesce(value: $content['name'], fallback: $seed?->getName())
		);
		$translation->setDescription(
			$this->coalesce(
				value: $content['description'],
				fallback: $seed?->getDescription()
			)
		);
		$translation->setWidgetTreeJson(
			$this->coalesce(
				value: $content['widgetTreeJson'],
				fallback: $seed?->getWidgetTreeJson()
			)
		);
	}//end fillVariantContent()

	/**
	 * Update an existing translation variant.
	 *
	 * Accepts a partial patch — only the keys present in `$patch` are
	 * touched. Supported keys: `name`, `description`, `widgetTreeJson`.
	 * Returns the updated entity. REQ-DASH-041.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode The (raw) language code.
	 * @param array $patch The patch payload.
	 *
	 * @return DashboardTranslation The updated entity.
	 *
	 * @throws DoesNotExistException When no variant exists for the
	 *                               (uuid, language) pair.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function updateVariant(
		string $dashboardUuid,
		string $languageCode,
		array $patch,
	): DashboardTranslation {
		$translation = $this->requireVariant(
			dashboardUuid: $dashboardUuid,
			languageCode: $languageCode
		);

		if (array_key_exists(key: 'name', array: $patch) === true) {
			$translation->setName($patch['name']);
		}

		if (array_key_exists(key: 'description', array: $patch) === true) {
			$translation->setDescription($patch['description']);
		}

		if (array_key_exists(key: 'widgetTreeJson', array: $patch) === true) {
			$translation->setWidgetTreeJson($patch['widgetTreeJson']);
		}

		$translation->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		return $this->translationMapper->update(entity: $translation);
	}//end updateVariant()

	/**
	 * Delete a translation variant. Guards against deleting the only
	 * remaining variant or the primary variant. REQ-DASH-042.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode The (raw) language code.
	 *
	 * @return void
	 *
	 * @throws DoesNotExistException When the variant does not exist.
	 * @throws Exception When the guard rejects the delete.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function deleteVariant(
		string $dashboardUuid,
		string $languageCode,
	): void {
		$translation = $this->requireVariant(
			dashboardUuid: $dashboardUuid,
			languageCode: $languageCode
		);

		if ((int)$translation->getIsPrimary() === 1) {
			throw new Exception(message: self::ERR_DELETE_PRIMARY);
		}

		$count = $this->translationMapper->countByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);
		if ($count <= 1) {
			throw new Exception(message: self::ERR_LAST_VARIANT);
		}

		$this->translationMapper->delete(entity: $translation);
	}//end deleteVariant()

	/**
	 * Promote a non-primary variant to become the new primary,
	 * downgrading the current primary in the same DB transaction.
	 *
	 * Idempotent: promoting the existing primary is a no-op (returns the
	 * row untouched). REQ-DASH-043.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode The (raw) language code.
	 *
	 * @return DashboardTranslation The promoted variant.
	 *
	 * @throws DoesNotExistException When the variant does not exist.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function promoteVariantToPrimary(
		string $dashboardUuid,
		string $languageCode,
	): DashboardTranslation {
		$translation = $this->requireVariant(
			dashboardUuid: $dashboardUuid,
			languageCode: $languageCode
		);

		if ((int)$translation->getIsPrimary() === 1) {
			return $translation;
		}

		$this->db->beginTransaction();
		try {
			$updated = $this->makePrimary(translation: $translation, dashboardUuid: $dashboardUuid);
			$this->db->commit();
			return $updated;
		} catch (Throwable $t) {
			$this->db->rollBack();
			throw $t;
		}
	}//end promoteVariantToPrimary()

	/**
	 * Clear the other primary rows of the dashboard and flag this one.
	 *
	 * @param DashboardTranslation $translation   The variant to promote.
	 * @param string               $dashboardUuid The dashboard UUID.
	 *
	 * @return DashboardTranslation The updated variant.
	 */
	private function makePrimary(DashboardTranslation $translation, string $dashboardUuid): DashboardTranslation {
		$this->translationMapper->clearPrimary(
			dashboardUuid: $dashboardUuid,
			exceptId: $translation->getId()
		);
		$translation->setIsPrimary(1);
		$translation->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		return $this->translationMapper->update(
			entity: $translation
		);
	}//end makePrimary()

	/**
	 * Cascade-delete every translation variant for a dashboard. Called
	 * from the dashboard delete path so the translation rows do not
	 * outlive their parent. REQ-DASH-044.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 *
	 * @return int The number of rows deleted.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function deleteAllForDashboard(string $dashboardUuid): int {
		return $this->translationMapper->deleteByDashboardUuid(
			dashboardUuid: $dashboardUuid
		);
	}//end deleteAllForDashboard()

	/**
	 * Backwards-compatibility helper for dashboards that have not yet
	 * had a primary variant seeded (legacy rows pre-dating REQ-DASH-038).
	 * Materialises an in-memory translation from the dashboard's own
	 * fields so the read endpoints can return a uniform envelope shape
	 * regardless of seed status. REQ-DASH-044.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return DashboardTranslation An in-memory variant (NOT persisted).
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	public function materialiseLegacyVariant(
		Dashboard $dashboard,
	): DashboardTranslation {
		$translation = new DashboardTranslation();
		$translation->setDashboardUuid((string)$dashboard->getUuid());
		$translation->setLanguageCode(
			$this->resolveOwnerLocale(userId: $dashboard->getUserId())
		);
		$translation->setName($dashboard->getName());
		$translation->setDescription($dashboard->getDescription());
		$translation->setWidgetTreeJson(null);
		$translation->setIsPrimary(1);
		return $translation;
	}//end materialiseLegacyVariant()

	/**
	 * Look up the dashboard owner's Nextcloud `core/lang` preference,
	 * normalise it to the canonical 2-char base code, and fall back to
	 * {@see DashboardTranslation::DEFAULT_LANGUAGE} when the user has
	 * no preference set or no owner is associated with the dashboard.
	 *
	 * @param string|null $userId The dashboard owner's user ID.
	 *
	 * @return string The normalised language code.
	 */
	private function resolveOwnerLocale(string|null $userId): string {
		if ($userId === null || $userId === '') {
			return DashboardTranslation::DEFAULT_LANGUAGE;
		}

		$raw = (string)$this->config->getUserValue(
			userId: $userId,
			appName: 'core',
			key: 'lang',
			default: ''
		);

		$normalised = DashboardTranslationMapper::normaliseLanguageCode(
			raw: $raw
		);
		if ($normalised === '') {
			return DashboardTranslation::DEFAULT_LANGUAGE;
		}

		return $normalised;
	}//end resolveOwnerLocale()

	/**
	 * Resolve the seed variant for a new translation row.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string|null $copyFromLanguage The optional source language;
	 *                                      `null` or `'primary'` ⇒ use
	 *                                      the primary variant.
	 *
	 * @return DashboardTranslation|null The seed variant, or null when
	 *                                   no candidate exists.
	 */
	private function resolveSeed(
		string $dashboardUuid,
		string|null $copyFromLanguage,
	): DashboardTranslation|null {
		if ($copyFromLanguage === null
			|| $copyFromLanguage === ''
			|| strtolower($copyFromLanguage) === 'primary'
		) {
			return $this->translationMapper->findPrimaryByDashboardUuid(
				dashboardUuid: $dashboardUuid
			);
		}

		return $this->translationMapper->findByDashboardUuidAndLanguage(
			dashboardUuid: $dashboardUuid,
			languageCode: $copyFromLanguage
		);
	}//end resolveSeed()

	/**
	 * Look up a single variant or throw `DoesNotExistException`.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $languageCode The (raw) language code.
	 *
	 * @return DashboardTranslation The variant.
	 *
	 * @throws DoesNotExistException When the variant does not exist.
	 */
	private function requireVariant(
		string $dashboardUuid,
		string $languageCode,
	): DashboardTranslation {
		$variant = $this->translationMapper->findByDashboardUuidAndLanguage(
			dashboardUuid: $dashboardUuid,
			languageCode: $languageCode
		);
		if ($variant === null) {
			throw new DoesNotExistException(
				msg: 'Translation variant not found'
			);
		}

		return $variant;
	}//end requireVariant()

	/**
	 * Return `$value` when it is a non-null string, otherwise `$fallback`.
	 *
	 * Helper used by createVariant to coalesce caller-supplied fields
	 * with seed-derived fallbacks while preserving the explicit empty
	 * string (treated as a real value, not a missing one).
	 *
	 * @param string|null $value The caller-supplied value.
	 * @param string|null $fallback The fallback value.
	 *
	 * @return string|null The chosen value.
	 */
	private function coalesce(?string $value, ?string $fallback): ?string {
		if ($value !== null) {
			return $value;
		}

		return $fallback;
	}//end coalesce()
}//end class
