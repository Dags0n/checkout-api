<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use InvalidArgumentException;
use Payment\Domain\ValueObjects\CreditCard;
use PHPUnit\Framework\TestCase;

final class CreditCardTest extends TestCase
{
    public function test_it_accepts_a_valid_card(): void
    {
        $card = new CreditCard('4111111111111111', 'JOAO SILVA', '12/27', '123');

        $this->assertSame('4111111111111111', $card->number);
        $this->assertSame('JOAO SILVA', $card->holderName);
        $this->assertSame('12/27', $card->expiry);
        $this->assertSame('123', $card->cvv);
    }

    public function test_it_strips_spaces_from_number(): void
    {
        $card = new CreditCard('4111 1111 1111 1111', 'JOAO', '12/27', '123');

        $this->assertSame('4111111111111111', $card->number);
    }

    public function test_last_digit_returns_last_character_as_int(): void
    {
        $this->assertSame(2, (new CreditCard('4111111111111112', 'X', '12/27', '123'))->lastDigit());
        $this->assertSame(7, (new CreditCard('5500000000000007', 'X', '12/27', '123'))->lastDigit());
    }

    public function test_last4_returns_last_four_digits(): void
    {
        $card = new CreditCard('4111111111111111', 'X', '12/27', '123');

        $this->assertSame('1111', $card->last4());
    }

    public function test_it_rejects_non_digit_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be 13-19 digits');

        new CreditCard('4111-1111-1111-1111', 'X', '12/27', '123');
    }

    public function test_it_rejects_short_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreditCard('1234567890', 'X', '12/27', '123');
    }

    public function test_it_rejects_long_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreditCard('1'.str_repeat('2', 20), 'X', '12/27', '123');
    }

    public function test_it_rejects_bad_expiry_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MM/YY');

        new CreditCard('4111111111111111', 'X', '13/27', '123');
    }

    public function test_it_rejects_invalid_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreditCard('4111111111111111', 'X', '15/27', '123');
    }

    public function test_it_rejects_short_cvv(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreditCard('4111111111111111', 'X', '12/27', '12');
    }

    public function test_it_rejects_empty_holder_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreditCard('4111111111111111', '   ', '12/27', '123');
    }
}
