<?php

/**
 * Tests for WidgetProposalService: the assistant line of Add widget proposes
 * only an offered card, runs one text-to-text task, and fails closed.
 *
 * @category  Tests
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Service\WidgetProposalService;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToText;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
#[Small]
class WidgetProposalServiceTest extends TestCase {

	/**
	 * The task processing manager.
	 *
	 * @var IManager&MockObject
	 */
	private IManager&MockObject $manager;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * The service under test.
	 *
	 * @var WidgetProposalService
	 */
	private WidgetProposalService $service;

	private const CARDS = [
		['key' => 'type:pie-chart', 'name' => 'Pie chart', 'description' => 'Shares of a whole'],
		['key' => 'widget:notes', 'name' => 'Notes', 'description' => 'Your notes'],
	];

	/**
	 * Set up the service with mocked collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->manager = $this->createMock(originalClassName: IManager::class);
		$this->logger  = $this->createMock(originalClassName: LoggerInterface::class);
		$this->service = new WidgetProposalService(taskProcessing: $this->manager, logger: $this->logger);
	}//end setUp()

	/**
	 * A finished task with the given text output.
	 *
	 * @param string  $text   The assistant's answer.
	 * @param integer $status The task status.
	 *
	 * @return Task
	 */
	private function answer(string $text, int $status=Task::STATUS_SUCCESSFUL): Task {
		$task = new Task(taskTypeId: TextToText::ID, input: ['input' => ''], appId: 'launchpad', userId: 'pieter');
		$task->setStatus(status: $status);
		$task->setOutput(output: ['output' => $text]);
		return $task;
	}//end answer()

	/**
	 * The assistant is available exactly when a text-to-text type is.
	 *
	 * @return void
	 */
	public function testAvailabilityFollowsTheTextToTextType(): void {
		$this->manager->method('getAvailableTaskTypeIds')->willReturnOnConsecutiveCalls(
			['core:text2image', TextToText::ID],
			['core:text2image']
		);
		$this->assertTrue(condition: $this->service->isAvailable(userId: 'pieter'));
		$this->assertFalse(condition: $this->service->isAvailable(userId: 'pieter'));
	}//end testAvailabilityFollowsTheTextToTextType()

	/**
	 * A failing type read counts as unavailable.
	 *
	 * @return void
	 */
	public function testAFailingTypeReadIsUnavailable(): void {
		$this->manager->method('getAvailableTaskTypeIds')->willThrowException(exception: new RuntimeException(message: 'down'));
		$this->logger->expects($this->once())->method('warning');
		$this->assertFalse(condition: $this->service->isAvailable(userId: 'pieter'));
	}//end testAFailingTypeReadIsUnavailable()

	/**
	 * One text-to-text task for the user carries the need and every offered card.
	 *
	 * @return void
	 */
	public function testAProposalIsAnOfferedCard(): void {
		$sent = null;
		$this->manager->expects($this->once())->method('runTask')->willReturnCallback(
			function (Task $task) use (&$sent) {
				$sent = $task;
				return $this->answer(text: 'Sure: {"key": "type:pie-chart", "reason": "It shows <b>shares</b> at a glance."}');
			}
		);

		$proposal = $this->service->propose(userId: 'pieter', need: ' a chart of open cases ', cards: self::CARDS);

		$this->assertSame(
			expected: ['key' => 'type:pie-chart', 'name' => 'Pie chart', 'reason' => 'It shows shares at a glance.'],
			actual: $proposal
		);
		$this->assertSame(expected: TextToText::ID, actual: $sent->getTaskTypeId());
		$this->assertSame(expected: 'pieter', actual: $sent->getUserId());
		$this->assertSame(expected: 'launchpad', actual: $sent->getAppId());
		$prompt = $sent->getInput()['input'];
		$this->assertStringContainsString(needle: "<<<\na chart of open cases\n>>>", haystack: $prompt);
		$this->assertStringContainsString(needle: '- type:pie-chart | Pie chart | Shares of a whole', haystack: $prompt);
		$this->assertStringContainsString(needle: '- widget:notes | Notes | Your notes', haystack: $prompt);
	}//end testAProposalIsAnOfferedCard()

	/**
	 * An answer naming a card that was not offered, no card, or no JSON is no proposal.
	 *
	 * @return void
	 */
	public function testAnAnswerOutsideTheOfferIsNoProposal(): void {
		$offers = $this->service->cleanCards(cards: self::CARDS);
		$this->assertNull(actual: $this->service->parse(answer: '{"key": "type:iframe", "reason": "x"}', offers: $offers));
		$this->assertNull(actual: $this->service->parse(answer: '{"key": null, "reason": ""}', offers: $offers));
		$this->assertNull(actual: $this->service->parse(answer: 'type:pie-chart', offers: $offers));
		$this->assertNull(actual: $this->service->parse(answer: '{not json}', offers: $offers));
	}//end testAnAnswerOutsideTheOfferIsNoProposal()

	/**
	 * A failed or unsuccessful task is no proposal, and the failure is logged.
	 *
	 * @return void
	 */
	public function testAFailingTaskIsNoProposal(): void {
		$this->manager->method('runTask')->willReturnOnConsecutiveCalls(
			$this->throwException(exception: new RuntimeException(message: 'provider down')),
			$this->answer(text: '{"key": "widget:notes"}', status: Task::STATUS_FAILED)
		);
		$this->logger->expects($this->once())->method('warning');

		$this->assertNull(actual: $this->service->propose(userId: 'pieter', need: 'notes', cards: self::CARDS));
		$this->assertNull(actual: $this->service->propose(userId: 'pieter', need: 'notes', cards: self::CARDS));
	}//end testAFailingTaskIsNoProposal()

	/**
	 * Without a need or without cards no task runs.
	 *
	 * @return void
	 */
	public function testNothingToChooseRunsNoTask(): void {
		$this->manager->expects($this->never())->method('runTask');
		$this->assertNull(actual: $this->service->propose(userId: 'pieter', need: '   ', cards: self::CARDS));
		$this->assertNull(actual: $this->service->propose(userId: 'pieter', need: 'notes', cards: [['name' => 'no key'], 'text']));
	}//end testNothingToChooseRunsNoTask()

	/**
	 * Cards are kept once per key, clipped, and at most MAX_CARDS; the need and the reason are clipped.
	 *
	 * @return void
	 */
	public function testInputAndOutputAreBounded(): void {
		$many = [];
		for ($index = 0; $index < 200; $index++) {
			$many[] = ['key' => 'widget:w'.$index, 'name' => str_repeat(string: 'n', times: 400), 'description' => 7];
		}

		$many[] = ['key' => 'widget:w0', 'name' => 'Duplicate'];

		$offers = $this->service->cleanCards(cards: $many);
		$this->assertCount(expectedCount: WidgetProposalService::MAX_CARDS, haystack: $offers);
		$this->assertSame(expected: WidgetProposalService::MAX_FIELD_LENGTH, actual: mb_strlen(string: $offers['widget:w0']['name']));
		$this->assertSame(expected: '', actual: $offers['widget:w0']['description']);

		$this->manager->method('runTask')->willReturnCallback(
			function (Task $task) {
				$this->assertStringNotContainsString(
					needle: str_repeat(string: 'x', times: WidgetProposalService::MAX_NEED_LENGTH + 1),
					haystack: $task->getInput()['input']
				);
				return $this->answer(text: '{"key": "widget:w1", "reason": "'.str_repeat(string: 'r', times: 500).'"}');
			}
		);
		$proposal = $this->service->propose(userId: 'pieter', need: str_repeat(string: 'x', times: 900), cards: $many);
		$this->assertSame(expected: WidgetProposalService::MAX_REASON_LENGTH, actual: mb_strlen(string: $proposal['reason']));
	}//end testInputAndOutputAreBounded()
}//end class
