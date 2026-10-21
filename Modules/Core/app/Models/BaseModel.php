<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasFactory;

    /**
     * Child models declare $fillable. This default keeps the primary key out of mass assignment.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DateTimeInterface::ATOM);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc($query->getModel()->getQualifiedKeyName());
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null && $from !== '') {
            $query->whereDate($query->getModel()->getTable().'.created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->whereDate($query->getModel()->getTable().'.created_at', '<=', $to);
        }

        return $query;
    }
}
