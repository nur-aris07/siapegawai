<?php

namespace App\Console\Commands;

use App\Services\QuestionMediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Read-only reference/bytes audit of question_version_media for an isolated QA DB.
 * Never repairs/deletes orphan files, changes DB rows, or prints private object keys.
 * The digest compares metadata snapshots between separately invoked source/restore DBs;
 * the tool itself NEVER asserts that a physical restore has happened.
 */
final class FigP4MediaIntegrityAudit extends Command
{
    protected $signature = 'siapegawai:fig-p4-media-audit
        {--run : Execute SELECT-only reference and disk inspection}
        {--expected-db= : Exact database name to verify before reading}
        {--max-rows=20000 : Maximum references examined, fail closed if exceeded}';
    protected $description = 'FIG-P4: read-only media reference and SHA-256 integrity audit, isolated testing/restore DB only';

    private const SLOTS = ['stem', 'option_A', 'option_B', 'option_C', 'option_D', 'option_E', 'explanation'];
    private const MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /** One exact V-02 synthetic source DB; no wildcard for arbitrary *_source. */
    public static function isAllowedTestingDatabase(string $name): bool
    {
        return $name === 'siapegawai_testing'
            || $name === 'siapegawai_figp4_source'
            || (bool) preg_match('/^siapegawai_(?:[a-z0-9_]*restore_verify_testing)$/D', $name);
    }

    public function handle(): int
    {
        if (!$this->option('run')) {
            $this->line('DRY RUN | no DB or media read. Use --run --expected-db=siapegawai_testing for an isolated QA reference check.');
            $this->warn('This tool does NOT perform or certify an independent restore; V-02 stays OPEN.');
            return self::SUCCESS;
        }
        try {
            $expected = (string) $this->option('expected-db');
            $limitRaw = (string) $this->option('max-rows');
            if (!app()->environment('testing')
                || !self::isAllowedTestingDatabase($expected)
                || !preg_match('/^[1-9][0-9]{0,5}$/D', $limitRaw)
                || (int) $limitRaw > 50000) {
                throw new \RuntimeException('Environment, DB identity or bounded row limit invalid; isolated testing only.');
            }
            $configured = DB::connection()->getDatabaseName();
            $actual = DB::selectOne('select database() as db')?->db;
            if ($configured !== $expected || $actual !== $expected) {
                // No reference reads: do not mistake this for broken restored media.
                // Do not print the actual DB identity or connection details in QA logs.
                $this->warn('BLOCKED_DB_IDENTITY_MISMATCH | --expected-db must match the configured AND connected database.');
                $this->line('NEXT | For current siapegawai_testing: php artisan siapegawai:fig-p4-media-audit --env=testing --run --expected-db=siapegawai_testing');
                $this->warn('V-02 OPEN | Source/restore audit requires separate configured connections and private roots.');
                return 2;
            }
            $diskName = QuestionMediaStorageService::DISK;
            if (config('filesystems.disks.'.$diskName.'.driver') !== 'local'
                || config('filesystems.disks.'.$diskName.'.serve') !== false
                || config('filesystems.disks.'.$diskName.'.visibility') !== 'private') {
                throw new \RuntimeException('Private disk configuration does not meet FIG-P4 safety invariant.');
            }
            $disk = Storage::disk($diskName);
            $rootCandidate = $disk->path('');
            if (is_link($rootCandidate)) {
                throw new \RuntimeException('Private disk root must not be a symlink.');
            }
            $root = realpath($rootCandidate);
            $public = realpath(public_path());
            if ($root === false || !is_dir($root)
                || ($public !== false && ($root === $public || str_starts_with($root, $public.DIRECTORY_SEPARATOR)))) {
                throw new \RuntimeException('Private disk root missing or under public path.');
            }
            $limit = (int) $limitRaw;
            $rows = DB::table('question_version_media')->orderBy('id')->limit($limit + 1)->get();
            if ($rows->count() > $limit) {
                throw new \RuntimeException('Media references exceed inspection row cap; stop and plan bounded partition.');
            }
            if ($rows->isEmpty()) {
                $this->warn('BLOCKED_NO_FIXTURE | No version-bound media references to verify; V-02 OPEN.');
                return 2;
            }
            $versionIds = $rows->pluck('question_version_id')->unique()->all();
            $versions = [];
            foreach (array_chunk($versionIds, 500) as $batch) {
                foreach (DB::table('question_versions')->whereIn('id', $batch)
                    ->get(['id', 'review_status', 'published_at', 'version_hash']) as $versionRow) {
                    $versions[(int) $versionRow->id] = $versionRow;
                }
            }
            $seen = [];
            $counts = ['rows' => 0, 'published' => 0, 'draft' => 0, 'invalid' => 0, 'bytes' => 0];
            $canonical = hash_init('sha256');
            foreach ($rows as $row) {
                $counts['rows']++;
                $version = $versions[(int) $row->question_version_id] ?? null;
                $identity = $row->question_version_id.'|'.$row->slot.'|'.$row->position;
                $bad = $version === null
                    || !in_array($row->slot, self::SLOTS, true)
                    || !in_array($row->mime_type, self::MIMES, true)
                    || (int) $row->position < 1 || (int) $row->position > 8
                    || !preg_match('/^[a-f0-9]{64}$/D', (string) $row->sha256)
                    || !preg_match('~^questions/[a-f0-9]{2}/[a-f0-9]{48}\.(?:jpg|png|webp)$~D', (string) $row->object_key)
                    || isset($seen[$identity]);
                $seen[$identity] = true;
                $isPublished = $version !== null && $version->review_status === 'published' && $version->published_at !== null;
                $counts[$isPublished ? 'published' : 'draft']++;
                if (!$bad) {
                    $absolute = $disk->path($row->object_key);
                    // Reject symlinked subdirs/objects and any path escaping the private root.
                    $realFile = realpath($absolute);
                    $parent = dirname($absolute);
                    $bad = is_link($absolute) || is_link($parent)
                        || $realFile === false || !str_starts_with($realFile, $root.DIRECTORY_SEPARATOR)
                        || !is_file($realFile) || !is_readable($realFile)
                        || filesize($realFile) !== (int) $row->byte_size
                        || hash_file('sha256', $realFile) !== $row->sha256;
                    if (!$bad) {
                        $dimensions = @getimagesize($realFile);
                        $bad = $dimensions === false
                            || (string) ($dimensions['mime'] ?? '') !== $row->mime_type
                            || (int) $dimensions[0] !== (int) $row->width_px
                            || (int) $dimensions[1] !== (int) $row->height_px;
                    }
                }
                if ($bad) $counts['invalid']++;
                else $counts['bytes'] += (int) $row->byte_size;
                // All fields come from a SELECT-only DB snapshot. Object keys and identities
                // contribute to digest but are NEVER printed in the CLI result.
                hash_update($canonical, json_encode([
                    (int) $row->id, (int) $row->question_version_id,
                    $row->slot, (int) $row->position, $row->object_key,
                    $row->sha256, $row->mime_type, (int) $row->byte_size,
                    (int) $row->width_px, (int) $row->height_px,
                    $version?->review_status, $version?->published_at, $version?->version_hash,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
            }
            $this->line('FIG-P4 REFERENCE AUDIT | '.$expected.' | read-only');
            $this->line('References '.$counts['rows'].'; published '.$counts['published'].'; draft '.$counts['draft'].'; verified-bytes '.$counts['bytes'].'; invalid '.$counts['invalid']);
            $this->line('Catalog digest SHA-256: '.hash_final($canonical));
            $this->warn('V-02 OPEN: DB restored independently, two disk parity and authenticated app smoke are NOT checked by this command.');
            if ($counts['invalid'] > 0) {
                $this->error('FAIL_INTEGRITY | One or more referenced private image objects/metadata invalid.');
                return 2;
            }
            $this->info('PASS_REFERENCE_INTEGRITY_ONLY | No database or media mutations performed.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            // Never print raw DB exception, object key, private path, or connection secret.
            $this->error('STOP | Refused or failed reference audit. Check isolated DB identity, schema, disk root and permissions.');
            return 2;
        }
    }
}
