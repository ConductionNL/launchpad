<?php

/**
 * IframeControllerTest
 *
 * Covers REQ-IFRAME-002 controller behaviour:
 *  - 401 when the caller is anonymous (no fetch of the allow-list is
 *    even attempted).
 *  - 200 with `{valid, errors}` for both a valid and an invalid config.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\IframeController;
use OCA\LaunchPad\Service\IframeService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Small]
class IframeControllerTest extends TestCase
{

    /**
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * @var IframeService&MockObject
     */
    private $iframeService;

    /**
     * @var IUserSession&MockObject
     */
    private $userSession;

    private IframeController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request       = $this->createMock(originalClassName: IRequest::class);
        $this->iframeService = $this->createMock(originalClassName: IframeService::class);
        $this->userSession   = $this->createMock(originalClassName: IUserSession::class);

        $this->controller = new IframeController(
            request: $this->request,
            iframeService: $this->iframeService,
            userSession: $this->userSession,
        );
    }//end setUp()

    public function testValidateUrlReturns401ForAnonymousCaller(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->iframeService->expects($this->never())->method('validateConfig');

        $response = $this->controller->validateUrl();

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testValidateUrlReturns401ForAnonymousCaller()

    public function testValidateUrlReturnsValidTrueWithNoErrors(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $config = ['url' => 'https://status.example.com/board', 'title' => 'Status'];
        $this->request->method('getParam')->with('config')->willReturn($config);
        $this->iframeService->method('validateConfig')->with($config)->willReturn([]);

        $response = $this->controller->validateUrl();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame(['valid' => true, 'errors' => []], $response->getData());
    }//end testValidateUrlReturnsValidTrueWithNoErrors()

    public function testValidateUrlReturnsErrorsForDisallowedHost(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $config = ['url' => 'https://evil.example.net/', 'title' => 'Evil'];
        $this->request->method('getParam')->with('config')->willReturn($config);
        $this->iframeService->method('validateConfig')->with($config)->willReturn(['host_not_allowed']);

        $response = $this->controller->validateUrl();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame(['valid' => false, 'errors' => ['host_not_allowed']], $response->getData());
    }//end testValidateUrlReturnsErrorsForDisallowedHost()

    public function testValidateUrlTreatsNonArrayConfigAsEmpty(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->request->method('getParam')->with('config')->willReturn(null);
        $this->iframeService->method('validateConfig')->with([])->willReturn(['url_required', 'title_required']);

        $response = $this->controller->validateUrl();

        $this->assertSame(['valid' => false, 'errors' => ['url_required', 'title_required']], $response->getData());
    }//end testValidateUrlTreatsNonArrayConfigAsEmpty()
}//end class
