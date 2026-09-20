<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A locked, monotonically increasing counter.
 *
 * MUST be called inside an open transaction: the row is locked with
 * SELECT ... FOR UPDATE and stays locked until that transaction commits, which
 * is what serialises two simultaneous requests. Outside a transaction the lock
 * releases immediately and two callers can get the same number.
 */
class Sequence extends Model
{
    protected $fillable = ['scope', 'year', 'last_number'];

    /**
     * Take the next number for a scope and year.
     *
     * @param  'booking'|'invoice'  $scope
     */
    public static function next(string $scope, int $year): int
    {
        // firstOrCreate first so there is a row to lock. The unique index on
        // (scope, year) means a race here produces a duplicate-key error
        // rather than two rows, and the retry below picks up the winner.
        try {
            self::firstOrCreate(
                ['scope' => $scope, 'year' => $year],
                ['last_number' => 0],
            );
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), '1062')) {
                throw $e;
            }
        }

        $row = self::query()
            ->where('scope', $scope)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $next = $row->last_number + 1;

        DB::table('sequences')->where('id', $row->id)->update(['last_number' => $next]);

        return $next;
    }
}
