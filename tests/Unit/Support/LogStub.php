<?php

// Minimal stand-in for Laravel's Log facade, so the library's Shopify services can be unit tested without a Laravel
// application. The host app provides the real facade.

namespace Illuminate\Support\Facades;

if (!class_exists(Log::class)) {
    class Log
    {
        /** @var array<int, array{0: string, 1: string, 2: array}> */
        public static array $records = [];

        public static function __callStatic(string $level, array $arguments): void
        {
            self::$records[] = [$level, $arguments[0] ?? '', $arguments[1] ?? []];
        }
    }
}
