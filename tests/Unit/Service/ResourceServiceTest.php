<?php

/**
 * ResourceService Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Exception\FileTooLargeException;
use OCA\LaunchPad\Exception\InvalidDataUrlException;
use OCA\LaunchPad\Exception\InvalidImageFormatException;
use OCA\LaunchPad\Exception\MimeMismatchException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Service\ImageMimeValidator;
use OCA\LaunchPad\Service\ResourceService;
use OCA\LaunchPad\Service\SvgSanitiser;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceServiceTest extends TestCase
{
    private ResourceService $service;

    /**
     * @var IAppData&MockObject
     */
    private $appData;

    /**
     * @var ImageMimeValidator&MockObject
     */
    private $mimeValidator;

    /**
     * @var ISimpleFolder&MockObject
     */
    private $folder;

    protected function setUp(): void
    {
        $this->appData       = $this->createMock(IAppData::class);
        $this->mimeValidator = $this->createMock(ImageMimeValidator::class);
        $this->folder        = $this->createMock(ISimpleFolder::class);

        $this->service = new ResourceService(
            appData: $this->appData,
            mimeValidator: $this->mimeValidator,
            svgSanitiser: new SvgSanitiser(),
        );
    }

    /**
     * Tiny 1x1 PNG bytes (red pixel).
     */
    private function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==',
            true
        );
    }

    /**
     * Build a base64 data URL from raw bytes and a declared type.
     */
    private function dataUrl(string $type, string $bytes): string
    {
        return 'data:image/' . $type . ';base64,' . base64_encode($bytes);
    }

    public function testMissingDataUrlPrefixIsRejected(): void
    {
        $this->expectException(InvalidDataUrlException::class);
        $this->service->upload(base64DataUrl: 'iVBORw0KGgo');
    }

    public function testEmptyInputIsRejected(): void
    {
        $this->expectException(InvalidDataUrlException::class);
        $this->service->upload(base64DataUrl: '');
    }

    public function testDisallowedTypeIsRejected(): void
    {
        $this->expectException(InvalidImageFormatException::class);
        $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'bmp', bytes: 'whatever')
        );
    }

    public function testMixedCaseDeclaredTypeIsAcceptedAndLowercased(): void
    {
        $this->mimeValidator->expects($this->once())->method('validate')
            ->with('png', $this->tinyPng());

        $this->folder->expects($this->once())->method('newFile')
            ->willReturnCallback(function (string $name, $content): ISimpleFile {
                $this->assertStringEndsWith('.png', $name);
                $this->assertStringStartsWith('resource_', $name);

                return $this->createMock(ISimpleFile::class);
            });

        $this->appData->expects($this->once())->method('getFolder')
            ->with(ResourceService::FOLDER)->willReturn($this->folder);

        $result = $this->service->upload(
            base64DataUrl: 'data:image/PNG;base64,' . base64_encode($this->tinyPng())
        );

        $this->assertSame('success', 'success'); // sanity
        $this->assertStringStartsWith('/apps/launchpad/resource/resource_', $result['url']);
        $this->assertStringEndsWith('.png', $result['url']);
        $this->assertSame(strlen($this->tinyPng()), $result['size']);
    }

    public function testSvgPlusXmlNormalisesToSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        $this->mimeValidator->expects($this->once())->method('validate')
            ->with('svg', $svg);

        $this->folder->method('newFile')
            ->willReturnCallback(function (string $name): ISimpleFile {
                $this->assertStringEndsWith('.svg', $name);
                return $this->createMock(ISimpleFile::class);
            });

        $this->appData->method('getFolder')->willReturn($this->folder);

        $result = $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'svg+xml', bytes: $svg)
        );

        $this->assertStringEndsWith('.svg', $result['name']);
    }

    public function testOversizePayloadIsRejectedBeforeValidator(): void
    {
        // 6 MB blob.
        $oversize = str_repeat('A', (6 * 1024 * 1024));
        $this->mimeValidator->expects($this->never())->method('validate');
        $this->appData->expects($this->never())->method('getFolder');

        $this->expectException(FileTooLargeException::class);
        $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: $oversize)
        );
    }

    public function testSizeAtCapIsAccepted(): void
    {
        // Exactly 5 MB → allowed (cap is "exceeds", not "equals").
        $atCap = str_repeat('A', (5 * 1024 * 1024));
        $this->mimeValidator->expects($this->once())->method('validate');
        $this->folder->method('newFile')
            ->willReturn($this->createMock(ISimpleFile::class));
        $this->appData->method('getFolder')->willReturn($this->folder);

        $result = $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: $atCap)
        );

        $this->assertSame((5 * 1024 * 1024), $result['size']);
    }

    public function testMimeMismatchBubblesUp(): void
    {
        $this->mimeValidator->method('validate')
            ->willThrowException(new MimeMismatchException());

        $this->expectException(MimeMismatchException::class);
        $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: 'whatever')
        );
    }

    public function testFolderAutoCreatedWhenMissing(): void
    {
        $this->mimeValidator->method('validate');
        $this->appData->expects($this->once())->method('getFolder')
            ->with(ResourceService::FOLDER)
            ->willThrowException(new NotFoundException());
        $this->appData->expects($this->once())->method('newFolder')
            ->with(ResourceService::FOLDER)->willReturn($this->folder);
        $this->folder->expects($this->once())->method('newFile')
            ->willReturn($this->createMock(ISimpleFile::class));

        $result = $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: $this->tinyPng())
        );

        $this->assertStringStartsWith('resource_', $result['name']);
    }

    public function testStorageFailureIsWrapped(): void
    {
        $this->mimeValidator->method('validate');
        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('newFile')
            ->willThrowException(new NotPermittedException('disk full'));

        $this->expectException(StorageFailureException::class);
        $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: $this->tinyPng())
        );
    }

    public function testFilenameMatchesSpecPattern(): void
    {
        $this->mimeValidator->method('validate');
        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('newFile')
            ->willReturn($this->createMock(ISimpleFile::class));

        $result = $this->service->upload(
            base64DataUrl: $this->dataUrl(type: 'png', bytes: $this->tinyPng())
        );

        // resource_<uniqid_with_more_entropy>.png — uniqid with
        // more_entropy=true returns hex + dot + decimal.
        $this->assertMatchesRegularExpression(
            '#^resource_[a-f0-9.]+\.(jpeg|jpg|png|gif|svg|webp)$#',
            $result['name']
        );
    }

    // ---------------------------------------------------------------
    // uploadRaw() — raw multipart path (REQ-RES-014).
    // ---------------------------------------------------------------

    public function testUploadRawStoresBytesAndReturnsEnvelope(): void
    {
        $this->mimeValidator->expects($this->once())->method('validate')
            ->with('png', $this->tinyPng());
        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->expects($this->once())->method('newFile')
            ->willReturnCallback(function (string $name): ISimpleFile {
                $this->assertStringStartsWith('resource_', $name);
                $this->assertStringEndsWith('.png', $name);
                return $this->createMock(ISimpleFile::class);
            });

        $result = $this->service->uploadRaw(bytes: $this->tinyPng(), declaredType: 'png');

        $this->assertStringStartsWith('/apps/launchpad/resource/resource_', $result['url']);
        $this->assertStringEndsWith('.png', $result['url']);
        $this->assertSame(strlen($this->tinyPng()), $result['size']);
    }

    public function testUploadRawNormalisesUppercaseAndSvgXmlType(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        $this->mimeValidator->method('validate');
        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('newFile')
            ->willReturnCallback(function (string $name): ISimpleFile {
                $this->assertStringEndsWith('.svg', $name);
                return $this->createMock(ISimpleFile::class);
            });

        // 'SVG+XML' (uppercase, +xml suffix) must normalise to 'svg'.
        $result = $this->service->uploadRaw(bytes: $svg, declaredType: 'SVG+XML');

        $this->assertStringEndsWith('.svg', $result['name']);
    }

    public function testUploadRawEmptyBytesRejected(): void
    {
        $this->expectException(InvalidDataUrlException::class);
        $this->appData->expects($this->never())->method('getFolder');
        $this->service->uploadRaw(bytes: '', declaredType: 'png');
    }

    public function testUploadRawDisallowedTypeRejected(): void
    {
        $this->expectException(InvalidImageFormatException::class);
        $this->appData->expects($this->never())->method('getFolder');
        $this->service->uploadRaw(bytes: 'whatever', declaredType: 'bmp');
    }

    public function testUploadRawOversizeRejectedBeforeValidator(): void
    {
        $oversize = str_repeat('A', (6 * 1024 * 1024));
        $this->mimeValidator->expects($this->never())->method('validate');
        $this->appData->expects($this->never())->method('getFolder');

        $this->expectException(FileTooLargeException::class);
        $this->service->uploadRaw(bytes: $oversize, declaredType: 'png');
    }

    public function testUploadRawSanitisesSvgScriptBeforePersisting(): void
    {
        // Uses the real SvgSanitiser (wired in setUp): a <script> in the raw
        // multipart payload MUST be stripped from the persisted bytes.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<script>alert(1)</script><rect width="10" height="10"/></svg>';
        $this->mimeValidator->method('validate');
        $this->appData->method('getFolder')->willReturn($this->folder);

        $persisted = null;
        $this->folder->method('newFile')
            ->willReturnCallback(function (string $name, $content) use (&$persisted): ISimpleFile {
                $persisted = $content;
                return $this->createMock(ISimpleFile::class);
            });

        $this->service->uploadRaw(bytes: $svg, declaredType: 'svg');

        $this->assertIsString($persisted);
        $this->assertStringNotContainsStringIgnoringCase('<script', $persisted);
        // The benign element survives sanitisation.
        $this->assertStringContainsString('<rect', $persisted);
    }

    public function testUploadRawMimeMismatchBubblesUp(): void
    {
        // REQ-RES-003 cross-check on the raw path: bytes whose detected MIME
        // does not match the declared type are rejected.
        $this->mimeValidator->method('validate')
            ->willThrowException(new MimeMismatchException());
        $this->appData->expects($this->never())->method('getFolder');

        $this->expectException(MimeMismatchException::class);
        $this->service->uploadRaw(bytes: $this->tinyPng(), declaredType: 'webp');
    }
}
