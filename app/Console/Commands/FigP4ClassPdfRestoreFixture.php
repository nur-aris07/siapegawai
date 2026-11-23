<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use App\Models\User;
use App\Services\ClassMediaService;
use App\Services\LearningMaterialRevisionService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Creates exactly one linked, published PDF for isolated V-02 tests via the existing upload service. */
final class FigP4ClassPdfRestoreFixture extends Command
{
    protected $signature = 'siapegawai:fig-p4-class-pdf
        {--dry-run : Validate the isolated QA context without writing}
        {--apply : Write one PDF and linked published material using existing ClassMediaService}
        {--verify : Verify DB, media bytes and enrolled participant (SELECT/read-only)}';
    protected $description = 'FIG-P4 V-02: one linked private Class PDF in siapegawai_testing only';

    private const DATABASE = 'siapegawai_testing';
    private const CLASS_SLUG = 'figp3-qa-kelas-01';
    private const TITLE = 'FIG-P4 QA - PDF Restore Evidence';
    private const PDF = 'database/fixtures/fig-p4/qa-class-restore.pdf';
    private const PDF_SHA256 = 'c90dc41392fd2f5eebe6e6fea5b492f463ad1956857b6a137b26a8d116ab7085';

    public function handle(ClassMediaService $mediaService, LearningMaterialRevisionService $revisions): int
    {
        $modeCount = (int) (bool) $this->option('dry-run')
            + (int) (bool) $this->option('apply')
            + (int) (bool) $this->option('verify');
        if ($modeCount !== 1) {
            $this->error('STOP | Choose exactly one of --dry-run, --apply, or --verify.');
            return self::FAILURE;
        }
        try {
            // Exact DB + environment guard on ALL modes; never use this command on restored/production schemas.
            if (!app()->environment('testing')
                || DB::connection()->getDatabaseName() !== self::DATABASE
                || DB::selectOne('SELECT DATABASE() AS db')?->db !== self::DATABASE) {
                throw new RuntimeException('Isolated QA database identity mismatch.');
            }
            if (config('filesystems.disks.class_media_private.driver') !== 'local'
                || config('filesystems.disks.class_media_private.serve') !== false
                || config('filesystems.disks.class_media_private.visibility') !== 'private') {
                throw new RuntimeException('Private Class disk configuration invalid.');
            }
            $disk = Storage::disk('class_media_private');
            $rootCandidate = rtrim($disk->path(''), '/\\');
            $root = realpath($rootCandidate);
            $parentPath = dirname($rootCandidate);
            $parent = realpath($parentPath);
            $publicRoot = realpath(public_path());
            // Missing private disk root is common on a fresh isolated QA clone.
            // A dry-run may validate it, but ONLY an explicit --apply can create it;
            // never follow a symlink/junction or create anything under public/.
            if (is_link($rootCandidate) || is_link($parentPath) || $parent === false || !is_dir($parent)
                || ($root === false && file_exists($rootCandidate))
                || ($root === false && !$this->option('apply') && !$this->option('dry-run'))
                || ($root !== false && !is_dir($root))
                || ($publicRoot !== false && (
                    $parent === $publicRoot || str_starts_with($parent, $publicRoot.DIRECTORY_SEPARATOR)
                    || ($root !== false && ($root === $publicRoot
                        || str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR)))))) {
                throw new RuntimeException('Class private disk missing/unsafe or public/symlinked.');
            }
            $source = base_path(self::PDF);
            $bytes = is_file($source) && !is_link($source) ? file_get_contents($source) : false;
            if ($bytes === false || strlen($bytes) < 100 || strlen($bytes) > 1024 * 1024
                || !hash_equals(self::PDF_SHA256, hash('sha256', $bytes))
                || !str_starts_with($bytes, '%PDF-')
                || (new \finfo(FILEINFO_MIME_TYPE))->file($source) !== 'application/pdf') {
                throw new RuntimeException('Synthetic PDF fixture missing or invalid.');
            }
            $class = LearningClass::query()->where('slug', self::CLASS_SLUG)->first();
            if ($class === null) {
                // RefreshDatabase commonly leaves the testing schema migrated but without
                // operational QA data. A missing prerequisite is not a broken PDF upload.
                // Never bootstrap a user, class or question implicitly from this command.
                $this->warn('BLOCKED_MISSING_FIGP3_FIXTURE | QA class is absent from siapegawai_testing. No writes performed.');
                $this->line('NEXT | After PHPUnit, run: php artisan siapegawai:fig-p3-demo --env=testing --dry-run --bootstrap-users --qa-quota');
                $this->line('NEXT | Then: php artisan siapegawai:fig-p3-demo --env=testing --apply --bootstrap-users --qa-quota');
                $this->line('NEXT | Then: php artisan siapegawai:fig-p3-demo --env=testing --verify');
                $this->line('NEXT | Retry: php artisan siapegawai:fig-p4-class-pdf --env=testing --dry-run');
                $this->warn('V-02 OPEN | A QA fixture is not an independent restore.');
                return 2;
            }
            // Reuse the actual FIG-P3 fixture class creator and one active enrollee.
            // FIG-P3 permits either bootstrap-users OR existing test user IDs;
            // do not assume email addresses or create new accounts during V-02.
            $admin = $class ? User::query()->find($class->created_by) : null;
            $enrolledUserIds = $class ? ClassEnrollment::query()
                ->where('class_id', $class->id)->where('status', 'active')
                ->pluck('user_id') : collect();
            $participant = $enrolledUserIds->isNotEmpty() ? User::query()
                ->whereIn('id', $enrolledUserIds)->where('role', 'participant')
                ->where('status', 'active')->orderBy('id')->first() : null;
            if (!$class || $class->status !== 'open' || !$admin || $admin->role !== 'superadmin'
                || $admin->status !== 'active' || !$participant) {
                throw new RuntimeException('FIG-P3 QA class, owner or active participant enrollment missing/inconsistent.');
            }
            $existing = LearningMaterial::query()->where('class_id', $class->id)
                ->where('title', self::TITLE)->get();
            if ($existing->count() > 1) {
                throw new RuntimeException('Duplicate V-02 materials; do not guess or overwrite.');
            }
            if ($existing->count() === 1) {
                if ($this->verifyMaterial($existing->first(), $source)) {
                    $this->info('PASS_CLASS_PDF_LINKED_ONLY | 1 published QA material, 1 linked private PDF, SHA-256 verified; V-02 OPEN.');
                    return self::SUCCESS;
                }
                throw new RuntimeException('Existing QA material incomplete or corrupt; refuse to overwrite.');
            }
            if ($this->option('verify')) {
                $this->warn('BLOCKED_NO_CLASS_PDF | Source PDF linked fixture not present; V-02 OPEN.');
                return 2;
            }
            if ($this->option('dry-run')) {
                $this->info('READY | Existing FIG-P3 QA class and enrolled participant valid; one linked PDF can be added with --apply. No mutations.');
                return self::SUCCESS;
            }

            if ($root === false) {
                // The DB prerequisite and synthetic PDF were validated above. Make
                // the expected private root only for explicit --apply, then recheck.
                if (!@mkdir($rootCandidate, 0700) && !is_dir($rootCandidate)) {
                    throw new RuntimeException('Could not create isolated private Class disk root.');
                }
                $root = realpath($rootCandidate);
                if ($root === false || !is_dir($root) || is_link($rootCandidate)
                    || ($publicRoot !== false && ($root === $publicRoot
                        || str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR)))) {
                    throw new RuntimeException('Created Class private disk root is unsafe.');
                }
            }
            $request = Request::create('/fig-p4/qa/isolated-pdf', 'POST');
            $request->setUserResolver(static fn () => $admin);
            $createdObject = null;
            try {
                DB::transaction(function () use ($class, $admin, $source, $request, $mediaService, $revisions, &$createdObject): void {
                    $material = $class->materials()->create([
                        'title' => self::TITLE,
                        'content' => 'Fixture dokumen privat untuk V-02 restore rehearsal; hanya database QA.',
                        'sort_order' => 1,
                        'status' => 'draft',
                    ]);
                    AuditLog::query()->create([
                        'actor_id' => $admin->id,
                        'action' => 'class.material_created',
                        'subject_type' => 'learning_material',
                        'subject_id' => $material->id,
                        'after_summary' => ['class_id' => $class->id, 'status' => 'draft', 'fixture' => 'FIG-P4-V02'],
                        'created_at' => now(),
                    ]);
                    // This is the existing production validation/storage/audit path, not an orphan disk write.
                    $file = new UploadedFile($source, basename($source), 'application/pdf', null, true);
                    $mediaService->addPdf($request, $class, $material, $file, 'FIG-P4 Synthetic Restore PDF');
                    $createdObject = LearningMaterialMedia::query()->where('material_id', $material->id)
                        ->where('kind', 'pdf')->sole()->private_path;
                    $material->update(['status' => 'published', 'published_at' => now()]);
                    $revisions->snapshotFirstPublish($material, (int) $admin->id);
                    AuditLog::query()->create([
                        'actor_id' => $admin->id,
                        'action' => 'class.material_published',
                        'subject_type' => 'learning_material',
                        'subject_id' => $material->id,
                        'before_summary' => ['status' => 'draft'],
                        'after_summary' => ['class_id' => $class->id, 'status' => 'published', 'fixture' => 'FIG-P4-V02'],
                        'created_at' => now(),
                    ]);
                }, 1);
            } catch (Throwable $exception) {
                // A DB rollback cannot undo filesystem writes: clean up ONLY our newly created object.
                if ($createdObject !== null) {
                    $disk->delete($createdObject);
                }
                throw $exception;
            }
            $material = LearningMaterial::query()->where('class_id', $class->id)
                ->where('title', self::TITLE)->sole();
            if (!$this->verifyMaterial($material, $source)) {
                throw new RuntimeException('Post-apply verification failed; do not attempt automatic repairs.');
            }
            $this->info('PASS_CLASS_PDF_LINKED_ONLY | 1 published QA material, 1 linked private PDF, SHA-256 verified; V-02 OPEN.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Avoid disclosing private paths, user IDs, DB exception internals or object keys.
            $this->error('STOP | FIG-P4 QA PDF fixture refused/failed. Check isolated DB, fixture, roles, enrollment and storage; no automatic overwrite.');
            return 2;
        }
    }

    private function verifyMaterial(LearningMaterial $material, string $source): bool
    {
        if ($material->status !== 'published' || !$material->published_at
            || (int) $material->published_version_number !== 1
            || $material->versions()->where('version_number', 1)->where('status', 'published')->count() !== 1) {
            return false;
        }
        $items = $material->mediaAssets()->get();
        if ($items->count() !== 1 || $items->first()->kind !== 'pdf') return false;
        $media = $items->first();
        if (!is_string($media->private_path) || !preg_match(
            '~^classes/[0-9]+/materials/[0-9]+/[0-9a-f-]{36}\.pdf$~D', $media->private_path)) {
            return false;
        }
        $disk = Storage::disk('class_media_private');
        $absolute = $disk->path($media->private_path);
        $root = realpath($disk->path(''));
        $real = realpath($absolute);
        if ($root === false || $real === false || is_link($absolute) || is_link(dirname($absolute))
            || !str_starts_with($real, $root.DIRECTORY_SEPARATOR) || !is_file($real)) return false;
        $hash = hash_file('sha256', $source);
        return $hash !== false && hash_equals($hash, (string) $media->sha256)
            && hash_equals($hash, (string) hash_file('sha256', $real))
            && filesize($real) === (int) $media->size_bytes
            && filesize($source) === (int) $media->size_bytes;
    }
}
