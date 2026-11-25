<?php

/**
 * WidgetProposalService
 *
 * Proposes one widget for a need the user describes in their own words, the
 * assistant line at the top of Add widget (REQ-WDG-044). The proposal comes
 * from the server's text-to-text task processing (the Nextcloud Assistant
 * providers) and is always one of the cards the picker offered: an answer
 * that names no offered card is no proposal. Nothing is stored.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToText;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asks the text-to-text assistant which offered widget fits a described need.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
class WidgetProposalService {

	/**
	 * The longest need that is sent on; the rest is cut off.
	 */
	public const MAX_NEED_LENGTH = 500;

	/**
	 * The most cards one proposal chooses from.
	 */
	public const MAX_CARDS = 120;

	/**
	 * The longest card key, name or description that is sent on.
	 */
	public const MAX_FIELD_LENGTH = 160;

	/**
	 * The longest reason that is passed back to the picker.
	 */
	public const MAX_REASON_LENGTH = 240;

	/**
	 * Constructor.
	 *
	 * @param IManager        $taskProcessing The server's task processing.
	 * @param LoggerInterface $logger         Logs a failing assistant.
	 */
	public function __construct(
		private readonly IManager $taskProcessing,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether a text-to-text provider is available for the user.
	 *
	 * @param string $userId The signed-in user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	public function isAvailable(string $userId): bool {
		try {
			$typeIds = $this->taskProcessing->getAvailableTaskTypeIds(showDisabled: false, userId: $userId);
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'Launchpad: the task processing types could not be read',
				context: ['exception' => $exception]
			);
			return false;
		}

		return in_array(needle: TextToText::ID, haystack: $typeIds, strict: true);
	}//end isAvailable()

	/**
	 * Propose one of the offered cards for a described need.
	 *
	 * @param string            $userId The signed-in user.
	 * @param string            $need   What the user needs, in their own words.
	 * @param array<int, mixed> $cards  The cards the picker offers: `[{key, name, description}]`.
	 *
	 * @return array{key: string, name: string, reason: string}|null The proposal, or null for none.
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	public function propose(string $userId, string $need, array $cards): ?array {
		$need   = $this->clip(text: $need, length: self::MAX_NEED_LENGTH);
		$offers = $this->cleanCards(cards: $cards);
		if ($need === '' || $offers === []) {
			return null;
		}

		$task = new Task(
			taskTypeId: TextToText::ID,
			input: ['input' => $this->prompt(need: $need, offers: $offers)],
			appId: Application::APP_ID,
			userId: $userId,
			customId: 'widget-proposal'
		);

		try {
			$done = $this->taskProcessing->runTask(task: $task);
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'Launchpad: the assistant could not propose a widget',
				context: ['exception' => $exception]
			);
			return null;
		}

		if ($done->getStatus() !== Task::STATUS_SUCCESSFUL) {
			return null;
		}

		$output = ($done->getOutput() ?? [])['output'] ?? '';
		if (is_string(value: $output) === false) {
			return null;
		}

		return $this->parse(answer: $output, offers: $offers);
	}//end propose()

	/**
	 * Keep the well-formed cards, each field clipped, at most MAX_CARDS, one per key.
	 *
	 * @param array<int, mixed> $cards The cards as the picker sent them.
	 *
	 * @return array<string, array{key: string, name: string, description: string}> Keyed by card key.
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	public function cleanCards(array $cards): array {
		$offers = [];
		foreach ($cards as $card) {
			if (is_array(value: $card) === false || is_string(value: $card['key'] ?? null) === false) {
				continue;
			}

			$key = $this->clip(text: $card['key'], length: self::MAX_FIELD_LENGTH);
			if ($key === '' || isset($offers[$key]) === true) {
				continue;
			}

			$name        = is_string(value: $card['name'] ?? null) === true ? $card['name'] : '';
			$description = is_string(value: $card['description'] ?? null) === true ? $card['description'] : '';

			$offers[$key] = [
				'key'         => $key,
				'name'        => $this->clip(text: $name, length: self::MAX_FIELD_LENGTH),
				'description' => $this->clip(text: $description, length: self::MAX_FIELD_LENGTH),
			];
			if (count(value: $offers) >= self::MAX_CARDS) {
				break;
			}
		}//end foreach

		return $offers;
	}//end cleanCards()

	/**
	 * The instruction the assistant answers.
	 *
	 * @param string                                                         $need   The need.
	 * @param array<string, array{key: string, name: string, description: string}> $offers The cards.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	public function prompt(string $need, array $offers): string {
		$lines = [];
		foreach ($offers as $offer) {
			$lines[] = '- '.$offer['key'].' | '.$offer['name'].' | '.$offer['description'];
		}

		return 'A user is adding one widget to a dashboard. These are the widgets they may add, one per line as "key | name | what it does":'."\n"
			.implode(separator: "\n", array: $lines)."\n\n"
			.'The user describes what they need, between the markers:'."\n"
			.'<<<'."\n".$need."\n".'>>>'."\n\n"
			.'Choose the one widget from the list that fits best. Answer with JSON only, in the language of the user: '
			.'{"key": "<the key from the list>", "reason": "<one short sentence why>"}. '
			.'If no widget fits, answer {"key": null, "reason": ""}.';
	}//end prompt()

	/**
	 * Read the assistant's answer; only a key from the offered cards counts.
	 *
	 * @param string                                                         $answer The assistant's text.
	 * @param array<string, array{key: string, name: string, description: string}> $offers The cards.
	 *
	 * @return array{key: string, name: string, reason: string}|null
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	public function parse(string $answer, array $offers): ?array {
		$key    = null;
		$reason = '';

		$matches = [];
		if (preg_match(pattern: '/\{.*\}/s', subject: $answer, matches: $matches) === 1) {
			$decoded = json_decode(json: $matches[0], associative: true);
			if (is_array(value: $decoded) === true) {
				$key    = is_string(value: $decoded['key'] ?? null) === true ? trim(string: $decoded['key']) : null;
				$reason = is_string(value: $decoded['reason'] ?? null) === true ? $decoded['reason'] : '';
			}
		}

		if ($key === null || isset($offers[$key]) === false) {
			return null;
		}

		return [
			'key'    => $key,
			'name'   => $offers[$key]['name'],
			'reason' => $this->clip(text: strip_tags(string: $reason), length: self::MAX_REASON_LENGTH),
		];
	}//end parse()

	/**
	 * Trim a text and cut it to a length.
	 *
	 * @param string  $text   The text.
	 * @param integer $length The longest result.
	 *
	 * @return string
	 */
	private function clip(string $text, int $length): string {
		return trim(string: mb_substr(string: trim(string: $text), start: 0, length: $length));
	}//end clip()
}//end class
