<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @2026-Alifiandra */
class PhoneNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['081234567890', '6281234567890'],
            ['0812-3456-7890', '6281234567890'],
            ['+62 812 3456 7890', '6281234567890'],
            ['6281234567890', '6281234567890'],
            ['81234567890', '6281234567890'],
            ['0060123456789', '60123456789'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input, '62'));
    }

    public function test_validation(): void
    {
        $this->assertTrue(PhoneNumber::isValid('6281234567890'));
        $this->assertFalse(PhoneNumber::isValid('123'));
        $this->assertFalse(PhoneNumber::isValid(''));
    }
}
