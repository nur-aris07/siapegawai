<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** A stronger, command-only identity guard: no legacy schema or main/local DB. */
final class PilotLearningQaDatabase
{
    public static function assertReadOnlyTarget(): void
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('PILOT_OPS_TESTING_ENV_REQUIRED');
        }
        $db = DB::connection()->getDatabaseName();
        if (! is_string($db) || ! preg_match('/^siapegawai_pilot_access_[a-f0-9]{12}_testing$/D', $db)
            || $db !== config('pilot.qa_test_database')) {
            throw new \RuntimeException('PILOT_OPS_RUN_OWNED_DATABASE_REQUIRED');
        }
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port');
        if (! $identity || $identity->db !== $db || (int) $identity->port !== 3309) {
            throw new \RuntimeException('PILOT_OPS_DATABASE_IDENTITY_MISMATCH');
        }
        if ((string) config('database.connections.mysql.host') !== '127.0.0.1') {
            throw new \RuntimeException('PILOT_OPS_LOOPBACK_HOST_REQUIRED');
        }
    }
}
