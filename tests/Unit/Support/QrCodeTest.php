<?php

declare(strict_types=1);

namespace Jengo\Base\Tests\Unit\Support;

use Jengo\Base\Support\QrCode;
use PHPUnit\Framework\TestCase;

class QrCodeTest extends TestCase
{
    public function testGenerateSvg(): void
    {
        $svg = QrCode::svg('otpauth://totp/Example:alice?secret=JBSWY3DPEHPK3PXP');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function testGeneratePng(): void
    {
        $png = QrCode::png('hello world');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
    }

    public function testGenerateDataUri(): void
    {
        $uri = QrCode::pngDataUri('hello world');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function testHelperFunctions(): void
    {
        helper('jengo');
        $img = qr_code('https://jengo.dev', 150);
        $this->assertStringStartsWith('<img src="data:image/png;base64,', $img);

        $uri = qr_data_uri('test');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }
}
