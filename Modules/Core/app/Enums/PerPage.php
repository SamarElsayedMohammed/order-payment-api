<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum PerPage: int
{
    case Min = 1;
    case Default = 10;
    case Max = 100;

    public static function resolve(?int $requested): int
    {
        if ($requested === null) {
            return self::Default->value;
        }

        return min(max($requested, self::Min->value), self::Max->value);
    }

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['sometimes', 'integer', 'min:'.self::Min->value, 'max:'.self::Max->value];
    }
}
