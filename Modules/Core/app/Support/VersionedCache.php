<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

final class VersionedCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $tag, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags([$tag])->remember($key, $ttlSeconds, $callback);
        }

        $version = (int) Cache::get($this->versionKey($tag), 1);

        return Cache::remember($tag.':v'.$version.':'.$key, $ttlSeconds, $callback);
    }

    public function flush(string $tag): void
    {
        if ($this->supportsTags()) {
            Cache::tags([$tag])->flush();

            return;
        }

        Cache::add($this->versionKey($tag), 1);
        Cache::increment($this->versionKey($tag));
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    private function versionKey(string $tag): string
    {
        return 'cache-version:'.$tag;
    }
}
