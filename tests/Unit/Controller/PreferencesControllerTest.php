<?php

/**
 * PreferencesController Contract Test
 *
 * Wire-contract coverage for the two routed public endpoints
 * `preferences#getPreference` (GET /api/preferences/{key}) and
 * `preferences#setPreference` (PUT /api/preferences/{key}).
 *
 * Both are `#[NoAdminRequired]`, so every authenticated account can reach
 * them with an arbitrary key. The behaviour that matters on the wire is
 * therefore not only the happy path but the three refusals: no session,
 * a key that sanitises to nothing, and a value past the 8 KiB DoS cap.
 * The `pref_` namespacing is asserted explicitly — it is the only thing
 * stopping a caller from reading or writing IConfig user values that do
 * not belong to this feature.
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

use OCA\LaunchPad\Controller\PreferencesController;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the per-user preferences endpoints.
 */
class PreferencesControllerTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Nextcloud config mock (user values).
     *
     * @var IConfig&MockObject
     */
    private $config;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;


    /**
     * Set up shared mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request     = $this->createMock(IRequest::class);
        $this->config      = $this->createMock(IConfig::class);
        $this->userSession = $this->createMock(IUserSession::class);

    }//end setUp()


    /**
     * Build the controller with the supplied logged-in user ID (or NULL
     * for an anonymous session).
     *
     * @param string|null $userId The acting user ID.
     *
     * @return PreferencesController
     */
    private function makeController(?string $userId): PreferencesController
    {
        $user = null;
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
        }

        $this->userSession->method('getUser')->willReturn($user);

        return new PreferencesController(
            request: $this->request,
            config: $this->config,
            userSession: $this->userSession,
        );

    }//end makeController()


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach IConfig.
     *
     * @return void
     */
    public function testGetPreferenceRejectsAnonymousWith401(): void
    {
        $this->config->expects($this->never())->method('getUserValue');

        $controller = $this->makeController(null);
        $response   = $controller->getPreference(key: 'support-dialog-seen');

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testGetPreferenceRejectsAnonymousWith401()


    /**
     * A key that sanitises to the empty string is rejected with 400 —
     * the guard that keeps the reachable key space inside `pref_`.
     *
     * @return void
     */
    public function testGetPreferenceRejectsKeyThatSanitisesToNothing(): void
    {
        $this->config->expects($this->never())->method('getUserValue');

        $controller = $this->makeController('alice');
        $response   = $controller->getPreference(key: '../../');

        $this->assertSame(
            expected: Http::STATUS_BAD_REQUEST,
            actual: $response->getStatus()
        );

    }//end testGetPreferenceRejectsKeyThatSanitisesToNothing()


    /**
     * A stored value is returned verbatim, read from the `pref_`-prefixed
     * key of the acting user only.
     *
     * @return void
     */
    public function testGetPreferenceReturnsStoredValueFromNamespacedKey(): void
    {
        $this->config->expects($this->once())
            ->method('getUserValue')
            ->with(
                userId: 'alice',
                appName: 'launchpad',
                key: 'pref_support-dialog-seen',
                default: ''
            )
            ->willReturn('1');

        $controller = $this->makeController('alice');
        $response   = $controller->getPreference(key: 'Support-Dialog-Seen');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: ['value' => '1'], actual: $response->getData());

    }//end testGetPreferenceReturnsStoredValueFromNamespacedKey()


    /**
     * An unset preference reads back as NULL, not as the empty string —
     * the frontend distinguishes "never set" from "set to empty".
     *
     * @return void
     */
    public function testGetPreferenceReturnsNullWhenUnset(): void
    {
        $this->config->method('getUserValue')->willReturn('');

        $controller = $this->makeController('alice');
        $response   = $controller->getPreference(key: 'never-set');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: ['value' => null], actual: $response->getData());

    }//end testGetPreferenceReturnsNullWhenUnset()


    /**
     * An anonymous caller MUST get 401 and MUST NOT write anything.
     *
     * @return void
     */
    public function testSetPreferenceRejectsAnonymousWith401(): void
    {
        $this->config->expects($this->never())->method('setUserValue');
        $this->config->expects($this->never())->method('deleteUserValue');

        $controller = $this->makeController(null);
        $response   = $controller->setPreference(key: 'support-dialog-seen', value: '1');

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testSetPreferenceRejectsAnonymousWith401()


    /**
     * A non-empty value is persisted under the `pref_`-prefixed key and
     * echoed back.
     *
     * @return void
     */
    public function testSetPreferenceWritesNamespacedKeyAndEchoesValue(): void
    {
        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with(
                userId: 'alice',
                appName: 'launchpad',
                key: 'pref_support-dialog-seen',
                value: '1'
            );
        $this->config->expects($this->never())->method('deleteUserValue');

        $controller = $this->makeController('alice');
        $response   = $controller->setPreference(key: 'support-dialog-seen', value: '1');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: ['value' => '1'], actual: $response->getData());

    }//end testSetPreferenceWritesNamespacedKeyAndEchoesValue()


    /**
     * An empty value CLEARS the preference (delete, not an empty write)
     * and reports the cleared state as NULL.
     *
     * @return void
     */
    public function testSetPreferenceWithEmptyValueDeletesTheKey(): void
    {
        $this->config->expects($this->once())
            ->method('deleteUserValue')
            ->with(
                userId: 'alice',
                appName: 'launchpad',
                key: 'pref_support-dialog-seen'
            );
        $this->config->expects($this->never())->method('setUserValue');

        $controller = $this->makeController('alice');
        $response   = $controller->setPreference(key: 'support-dialog-seen', value: '');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: ['value' => null], actual: $response->getData());

    }//end testSetPreferenceWithEmptyValueDeletesTheKey()


    /**
     * M6 DoS guard: a value over 8192 bytes is refused with 400 and
     * nothing is written to oc_preferences.
     *
     * @return void
     */
    public function testSetPreferenceRejectsOversizedValue(): void
    {
        $this->config->expects($this->never())->method('setUserValue');
        $this->config->expects($this->never())->method('deleteUserValue');

        $controller = $this->makeController('alice');
        $response   = $controller->setPreference(
            key: 'support-dialog-seen',
            value: str_repeat('x', 8193)
        );

        $this->assertSame(
            expected: Http::STATUS_BAD_REQUEST,
            actual: $response->getStatus()
        );

    }//end testSetPreferenceRejectsOversizedValue()


    /**
     * The byte exactly ON the limit is accepted — the boundary is
     * `> MAX`, not `>= MAX`, and a regression either way is invisible
     * without both sides asserted.
     *
     * @return void
     */
    public function testSetPreferenceAcceptsValueExactlyAtTheLimit(): void
    {
        $this->config->expects($this->once())->method('setUserValue');

        $controller = $this->makeController('alice');
        $response   = $controller->setPreference(
            key: 'support-dialog-seen',
            value: str_repeat('x', 8192)
        );

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

    }//end testSetPreferenceAcceptsValueExactlyAtTheLimit()


    /**
     * A key carrying path separators is sanitised, not rejected, when
     * safe characters remain — and the write still lands inside `pref_`.
     *
     * @return void
     */
    public function testSetPreferenceSanitisesKeyBeforeWriting(): void
    {
        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with(
                userId: 'alice',
                appName: 'launchpad',
                key: 'pref_configpasswordsalt',
                value: 'x'
            );

        $controller = $this->makeController('alice');
        $response   = $controller->setPreference(
            key: '../../config/passwordsalt',
            value: 'x'
        );

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

    }//end testSetPreferenceSanitisesKeyBeforeWriting()


}//end class
