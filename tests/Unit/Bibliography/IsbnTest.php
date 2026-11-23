<?php

namespace Tests\Unit\Bibliography;

use App\Services\Bibliography\Isbn;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    public function test_it_normalizes_isbn_13_and_isbn_10_to_isbn_13(): void
    {
        $this->assertSame('9782914603928', Isbn::normalize('2-914603-92-4'));
        $this->assertSame('9782914603928', Isbn::normalize('ISBN 978-2-914603-92-8'));
        $this->assertSame('9791098754814', Isbn::normalize('979-10-987548-1-4'));
        $this->assertSame('9782953580716', Isbn::normalize('978 2 9535807 1 6 (br.)'));
    }

    public function test_it_rejects_a_wrong_check_digit_and_non_isbn_numbers(): void
    {
        $this->assertNull(Isbn::normalize('978-2-914603-92-9'));
        $this->assertNull(Isbn::normalize('2-914603-92-5'));
        $this->assertNull(Isbn::normalize('1234567890123'));
        $this->assertNull(Isbn::normalize('1860'));
        $this->assertNull(Isbn::normalize(null));
    }

    public function test_it_reads_an_x_check_digit(): void
    {
        $this->assertSame('9780306406157', Isbn::normalize('0-306-40615-2'));
        $this->assertSame('9780804429573', Isbn::normalize('080442957X'));
    }

    public function test_it_picks_the_first_valid_isbn_of_a_text(): void
    {
        $this->assertSame('9782914603928', Isbn::normalize('Cabri, 2025, ISBN 2-914603-92-4, 415 p.'));
    }

    public function test_it_gives_the_isbn_10_of_a_978_isbn_13_and_nothing_for_979(): void
    {
        $this->assertSame('2914603924', Isbn::to10('9782914603928'));
        $this->assertSame('080442957X', Isbn::to10('9780804429573'));
        $this->assertNull(Isbn::to10('9791098754814'));
        $this->assertNull(Isbn::to10('2914603924'));
    }
}
