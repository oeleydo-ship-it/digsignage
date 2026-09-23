<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

final class QueueTransaction
{
    private const ATTEMPTS = 5;

    /**
     * Retry concurrency failures such as deadlocks while preserving the
     * database driver's normal transaction semantics.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback): mixed
    {
        return DB::transaction($callback, self::ATTEMPTS);
    }
}
