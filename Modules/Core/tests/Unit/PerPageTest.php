<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit;

use Modules\Core\Enums\PerPage;
use PHPUnit\Framework\TestCase;

class PerPageTest extends TestCase
{
    public function test_missing_value_uses_the_default(): void
    {
        $this->assertSame(10, PerPage::resolve(null));
    }

    public function test_value_is_clamped_between_min_and_max(): void
    {
        $this->assertSame(25, PerPage::resolve(25));
        $this->assertSame(PerPage::Min->value, PerPage::resolve(0));
        $this->assertSame(PerPage::Max->value, PerPage::resolve(5000));
    }

    public function test_validation_rules_follow_the_enum_limits(): void
    {
        $this->assertSame(['sometimes', 'integer', 'min:1', 'max:100'], PerPage::rules());
    }
}
