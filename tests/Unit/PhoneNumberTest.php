<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_normalizes_turkish_mobile_numbers(): void
    {
        $this->assertSame('905321112233', PhoneNumber::normalize('0532 111 22 33'));
        $this->assertSame('905321112233', PhoneNumber::normalize('5321112233'));
        $this->assertSame('905321112233', PhoneNumber::normalize('+90 532 111 22 33'));
        $this->assertSame('+905321112233', PhoneNumber::e164('0532 111 22 33'));
        $this->assertSame('+900000000000', PhoneNumber::e164OrRaw('+90 0000000000'));
        $this->assertSame('0532 111 22 33', PhoneNumber::display('905321112233'));
        $this->assertNull(PhoneNumber::normalize('02121112233'));
    }
}
