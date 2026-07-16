<?php

/**
 * ResourceController Test
 *
 * Covers both the read-side endpoints added by the `resource-serving`
 * change (REQ-RES-006..008) and the upload-side `POST /api/resources`
 * endpoint added by the `resource-uploads` change (REQ-RES-001 admin
 * guard, multipart rejection; REQ-RES-005 error envelope shape, stable
 * enum, no leakage of raw exception strings) plus the controller half
 * of every typed exception's HTTP-status mapping.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ResourceController;
use OCA\LaunchPad\Controller\ResourceUploadRequestParser;
use OCA\LaunchPad\Exception\CorruptImageException;
use OCA\LaunchPad\Exception\FileTooLargeException;
use OCA\LaunchPad\Exception\InvalidDataUrlException;
use OCA\LaunchPad\Exception\InvalidImageFormatException;
use OCA\LaunchPad\Exception\InvalidSvgException;
use OCA\LaunchPad\Exception\MimeMismatchException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Exception\UnsupportedMediaTypeException;
use OCA\LaunchPad\Service\ResourceService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ResourceControllerTest extends TestCase
{

    private ResourceController $controller;

    /** @var IRequest&MockObject */
    private $request;

    /** @var ResourceService&MockObject */
    private $service;

    /** @var ResourceUploadRequestParser&MockObject */
    private $parser;

    /** @var IUserSession&MockObject */
    private $userSession;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var LoggerInterface&MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->request      = $this->createMock(IRequest::class);
        $this->service      = $this->createMock(ResourceService::class);
        $this->parser       = $this->createMock(ResourceUploadRequestParser::class);
        $this->userSession  = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->logger       = $this->createMock(LoggerInterface::class);

        $this->controller = new ResourceController(
            request: $this->request,
            resourceService: $this->service,
            parser: $this->parser,
            userSession: $this->userSession,
            groupManager: $this->groupManager,
            logger: new NullLogger(),
        );
    }//end setUp()

    /**
     * Build a controller subclass that lets us inject a fake raw body
     * without touching `php://input`. Used by the upload-side tests.
     */
    private function buildController(string $rawBody = ''): ResourceController
    {
        return new class (
            $this->request,
            $this->service,
            $this->parser,
            $this->userSession,
            $this->groupManager,
            $this->logger,
            $rawBody,
        ) extends ResourceController {
            public function __construct(
                IRequest $request,
                ResourceService $resourceService,
                ResourceUploadRequestParser $parser,
                IUserSession $userSession,
                IGroupManager $groupManager,
                LoggerInterface $logger,
                private readonly string $fakeBody,
            ) {
                parent::__construct(
                    request: $request,
                    resourceService: $resourceService,
                    parser: $parser,
                    userSession: $userSession,
                    groupManager: $groupManager,
                    logger: $logger,
                );
            }

            protected function readRequestBody(): string
            {
                return $this->fakeBody;
            }
        };
    }

    /**
     * Build a controller subclass that injects a fake uploaded file (name +
     * bytes) so the multipart path can be exercised without `$_FILES`.
     * A `null` file simulates a missing/failed upload.
     *
     * @param array{name: string, bytes: string}|null $file the fake file.
     *
     * @return ResourceController the test double.
     */
    private function buildMultipartController(?array $file): ResourceController
    {
        return new class (
            $this->request,
            $this->service,
            $this->parser,
            $this->userSession,
            $this->groupManager,
            $this->logger,
            $file,
        ) extends ResourceController {
            public function __construct(
                IRequest $request,
                ResourceService $resourceService,
                ResourceUploadRequestParser $parser,
                IUserSession $userSession,
                IGroupManager $groupManager,
                LoggerInterface $logger,
                private readonly ?array $fakeFile,
            ) {
                parent::__construct(
                    request: $request,
                    resourceService: $resourceService,
                    parser: $parser,
                    userSession: $userSession,
                    groupManager: $groupManager,
                    logger: $logger,
                );
            }

            protected function readUploadedFile(): ?array
            {
                return $this->fakeFile;
            }
        };
    }

    private function adminUser(string $uid = 'admin'): IUser
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        return $user;
    }

    // ---------------------------------------------------------------
    // Upload-side tests (REQ-RES-001/005).
    // ---------------------------------------------------------------

    public function testNonAdminReceives403WithForbidden(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser('alice'));
        $this->groupManager->method('isAdmin')->with('alice')->willReturn(false);
        $this->parser->expects($this->never())->method('extractBase64');
        $this->service->expects($this->never())->method('upload');

        $controller = $this->buildController();
        $response   = $controller->upload();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $body = $response->getData();
        $this->assertSame('error', $body['status']);
        $this->assertSame('forbidden', $body['error']);
        $this->assertIsString($body['message']);
        $this->assertStringNotContainsString('Exception', $body['message']);
    }

    public function testUnauthenticatedReceives403(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->buildController()->upload();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertSame('forbidden', $response->getData()['error']);
    }

    public function testSuccessReturnsEnvelope(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
        $this->parser->method('extractBase64')->willReturn('data:image/png;base64,xxx');
        $this->service->method('upload')->willReturn([
            'url'  => '/apps/launchpad/resource/resource_abc.png',
            'name' => 'resource_abc.png',
            'size' => 1234,
        ]);

        $response = $this->buildController('{"base64":"data:image/png;base64,xxx"}')->upload();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $body = $response->getData();
        $this->assertSame('success', $body['status']);
        $this->assertSame('/apps/launchpad/resource/resource_abc.png', $body['url']);
        $this->assertSame('resource_abc.png', $body['name']);
        $this->assertSame(1234, $body['size']);
    }

    /**
     * @return array<string, array{0: \Throwable, 1: int, 2: string}>
     */
    public static function exceptionMatrix(): array
    {
        return [
            'unsupported_media_type' => [new UnsupportedMediaTypeException(), 415, 'unsupported_media_type'],
            'invalid_data_url'       => [new InvalidDataUrlException(), 400, 'invalid_data_url'],
            'invalid_image_format'   => [new InvalidImageFormatException(), 400, 'invalid_image_format'],
            'file_too_large'         => [new FileTooLargeException(), 400, 'file_too_large'],
            'mime_mismatch'          => [new MimeMismatchException(), 400, 'mime_mismatch'],
            'corrupt_image'          => [new CorruptImageException(), 400, 'corrupt_image'],
            'invalid_svg'            => [new InvalidSvgException(), 400, 'invalid_svg'],
            'storage_failure'        => [new StorageFailureException(), 500, 'storage_failure'],
        ];
    }

    /**
     * @dataProvider exceptionMatrix
     */
    public function testEachExceptionMapsToCorrectEnvelope(
        \Throwable $exception,
        int $expectedStatus,
        string $expectedCode
    ): void {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->parser->method('extractBase64')->willReturn('data:image/png;base64,xxx');
        $this->service->method('upload')->willThrowException($exception);

        $response = $this->buildController('{"base64":"x"}')->upload();
        $body     = $response->getData();

        $this->assertSame($expectedStatus, $response->getStatus());
        $this->assertSame('error', $body['status']);
        $this->assertSame($expectedCode, $body['error']);
        $this->assertIsString($body['message']);
        // Defence — the display message MUST NOT be the raw underlying class name
        // and MUST NOT leak any "Exception" substring (REQ-RES-005).
        $this->assertStringNotContainsString('Exception', $body['message']);
        $this->assertArrayNotHasKey('exception', $body);
        $this->assertArrayNotHasKey('trace', $body);
    }

    public function testUnexpectedThrowableIsMaskedAsStorageFailure(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->parser->method('extractBase64')->willReturn('data:image/png;base64,xxx');
        $this->service->method('upload')->willThrowException(
            new \RuntimeException('SECRET_INTERNAL_PATH /var/lib/secret')
        );

        $response = $this->buildController('{"base64":"x"}')->upload();
        $body     = $response->getData();

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
        $this->assertSame('error', $body['status']);
        $this->assertSame('storage_failure', $body['error']);
        $this->assertStringNotContainsString('SECRET_INTERNAL_PATH', $body['message']);
        $this->assertStringNotContainsString('/var/lib/secret', $body['message']);
    }

    public function testParserExceptionIsNotShortCircuitedByAdminGuard(): void
    {
        // The admin guard runs BEFORE the parser — confirm a parser
        // exception still goes through the typed-error envelope.
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->parser->method('extractBase64')->willThrowException(
            new UnsupportedMediaTypeException()
        );
        $this->service->expects($this->never())->method('upload');

        $response = $this->buildController('--multipart')->upload();
        $body     = $response->getData();

        $this->assertSame(415, $response->getStatus());
        $this->assertSame('unsupported_media_type', $body['error']);
    }

    // ---------------------------------------------------------------
    // Multipart upload tests (REQ-RES-014).
    // ---------------------------------------------------------------

    public function testMultipartNonAdminReceives403(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser('alice'));
        $this->groupManager->method('isAdmin')->with('alice')->willReturn(false);
        $this->service->expects($this->never())->method('uploadRaw');

        $response = $this->buildMultipartController(['name' => 'a.png', 'bytes' => 'x'])->uploadMultipart();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertSame('forbidden', $response->getData()['error']);
    }

    public function testMultipartUnauthenticatedReceives403(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->service->expects($this->never())->method('uploadRaw');

        $response = $this->buildMultipartController(['name' => 'a.png', 'bytes' => 'x'])->uploadMultipart();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertSame('forbidden', $response->getData()['error']);
    }

    public function testMultipartSuccessReturnsEnvelope(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
        $this->service->expects($this->once())
            ->method('uploadRaw')
            ->with('rawbytes', 'png')
            ->willReturn([
                'url'  => '/apps/launchpad/resource/resource_xyz.png',
                'name' => 'resource_xyz.png',
                'size' => 4321,
            ]);

        $response = $this->buildMultipartController(['name' => 'photo.PNG', 'bytes' => 'rawbytes'])->uploadMultipart();
        $body     = $response->getData();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('success', $body['status']);
        $this->assertSame('/apps/launchpad/resource/resource_xyz.png', $body['url']);
        $this->assertSame('resource_xyz.png', $body['name']);
        $this->assertSame(4321, $body['size']);
    }

    public function testMultipartMissingFileReturnsNoFile(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->expects($this->never())->method('uploadRaw');

        $response = $this->buildMultipartController(null)->uploadMultipart();
        $body     = $response->getData();

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('no_file', $body['error']);
    }

    public function testMultipartArrayFormFileIsRejectedAsNoFile(): void
    {
        // A `file[]` (multi-file) submission makes $_FILES['file']['error'] an
        // array. Exercise the REAL readUploadedFile() (via $this->controller,
        // which does not override it) to confirm it rejects cleanly.
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->expects($this->never())->method('uploadRaw');

        $_FILES['file'] = [
            'name'     => ['a.png'],
            'tmp_name' => ['/tmp/whatever'],
            'size'     => [10],
            'error'    => [UPLOAD_ERR_OK],
        ];
        try {
            $response = $this->controller->uploadMultipart();
        } finally {
            unset($_FILES['file']);
        }

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('no_file', $response->getData()['error']);
    }

    public function testMultipartRejectsCraftedTmpNameNotFromUpload(): void
    {
        // LFI defence: a tmp_name pointing at an arbitrary local path (not a
        // real PHP upload) must be rejected. is_uploaded_file() returns false
        // for any path in the PHPUnit process, so readUploadedFile() bails and
        // the crafted file is never read or stored.
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->expects($this->never())->method('uploadRaw');

        $_FILES['file'] = [
            'name'     => 'passwd.png',
            'tmp_name' => __FILE__,
            'size'     => 100,
            'error'    => UPLOAD_ERR_OK,
        ];
        try {
            $response = $this->controller->uploadMultipart();
        } finally {
            unset($_FILES['file']);
        }

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('no_file', $response->getData()['error']);
    }

    public function testMultipartOversizeRejectedBeforeReadingFile(): void
    {
        // The reported upload size exceeds the cap → rejected in readUploadedFile
        // before the bytes are pulled into memory, so uploadRaw is never reached.
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->expects($this->never())->method('uploadRaw');

        $_FILES['file'] = [
            'name'     => 'big.png',
            'tmp_name' => __FILE__,
            'size'     => (ResourceService::MAX_BYTES + 1),
            'error'    => UPLOAD_ERR_OK,
        ];
        try {
            $response = $this->controller->uploadMultipart();
        } finally {
            unset($_FILES['file']);
        }

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('file_too_large', $response->getData()['error']);
    }

    public function testMultipartServiceExceptionMapsToEnvelope(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->method('uploadRaw')->willThrowException(new FileTooLargeException());

        $response = $this->buildMultipartController(['name' => 'big.png', 'bytes' => 'x'])->uploadMultipart();
        $body     = $response->getData();

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('file_too_large', $body['error']);
        $this->assertStringNotContainsString('Exception', $body['message']);
    }

    public function testMultipartUnexpectedThrowableIsMaskedAsStorageFailure(): void
    {
        $this->userSession->method('getUser')->willReturn($this->adminUser());
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->service->method('uploadRaw')->willThrowException(
            new \RuntimeException('SECRET /var/lib/secret')
        );

        $response = $this->buildMultipartController(['name' => 'a.png', 'bytes' => 'x'])->uploadMultipart();
        $body     = $response->getData();

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
        $this->assertSame('storage_failure', $body['error']);
        $this->assertStringNotContainsString('SECRET', $body['message']);
    }
}//end class
