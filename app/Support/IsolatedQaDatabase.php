<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Legacy FIG fixture guard plus the explicitly pinned isolated pilot QA database. */
final class IsolatedQaDatabase
{
    public static function acceptsName(string $name): bool
    {
        return $name === 'siapegawai_testing'
            || (app()->environment('testing')
                && $name === config('pilot.qa_test_database')
                && (bool) preg_match('/^siapegawai_pilot_access_[a-f0-9]{12}_testing$/D', $name));
    }

    public static function matchesConnection(): bool
    {
        if (!app()->environment('testing')) return false;
        $name = DB::connection()->getDatabaseName();
        if (!is_string($name) || !self::acceptsName($name)) return false;
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port');
        if (!$identity || $identity->db !== $name) return false;
        // Legacy guard keeps its exact name contract; new schema must be on approved QA instance.
        return $name === 'siapegawai_testing' || (int) $identity->port === 3309;
    }
}
