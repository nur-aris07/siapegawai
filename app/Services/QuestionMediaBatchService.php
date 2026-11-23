<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** FIG-P2 C-02: one editorial save may persist many private, version-bound images. */
final class QuestionMediaBatchService
{
    private const SLOTS = ['stem','option_A','option_B','option_C','option_D','option_E','explanation'];

    public function __construct(
        private readonly QuestionMediaStorageService $storage,
        private readonly QuestionVersionMediaService $media,
    ) {}

    /** Retain option E only when it has text, is correct, or has image content. */
    public function cleanupEmptyOptionalE(Question $question): void
    {
        $draft=$question->versions()->orderByDesc('version_number')->first();
        if (!$draft || $draft->review_status !== 'draft' || $draft->published_at !== null) return;
        $e=$draft->options()->where('label','E')->first();
        if ($e && trim((string)$e->body)==='' && !$e->is_correct
            && !$draft->media()->where('slot','option_E')->exists()) $e->delete();
    }

    /** @param array<int,UploadedFile> $files
     *  @param array<int,string> $slots
     *  @param array<int,string|null> $alts
     *  @param array<int,string|null> $captions
     */
    public function attach(Question $question, User $actor, array $files, array $slots, array $positions, array $alts, array $captions): void
    {
        if ($files === []) return;
        if (count($files) > 12 || count($files) !== count($slots) || count($files) !== count($positions)
            || count($files) !== count($alts) || count($files) !== count($captions)) {
            throw ValidationException::withMessages(['media_files'=>'Jumlah berkas dan metadata tidak sesuai (maksimal 12 berkas per simpan).']);
        }
        // Inspect every selected image BEFORE writing any reference/bytes, reducing partial-failure surface.
        foreach ($files as $i => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()
                || !in_array($slots[$i] ?? '', self::SLOTS, true)) {
                throw ValidationException::withMessages(['media_files'=>'Jenis berkas atau bagian soal tidak valid.']);
            }
            $this->storage->inspect($file);
        }
        DB::transaction(function () use ($question, $actor, $files, $slots, $positions, $alts, $captions): void {
            $draft = QuestionVersion::query()->where('question_id',$question->id)
                ->orderByDesc('version_number')->lockForUpdate()->firstOrFail();
            if ($draft->review_status !== 'draft' || $draft->published_at !== null
                || (int) $question->current_version_id === (int) $draft->id) {
                throw ValidationException::withMessages(['media_files'=>'Gambar hanya boleh ditambahkan ke draft yang sah.']);
            }
            $used = [];
            foreach (self::SLOTS as $slot) {
                $used[$slot] = $draft->media()->where('slot',$slot)->pluck('position')->map(fn($n)=>(int)$n)->all();
            }
            // Check the WHOLE planned ordering before the first private byte is written.
            // Otherwise an invalid later position creates avoidable orphan objects.
            $planned = [];
            foreach ($files as $i => $file) {
                $slot = $slots[$i];
                $position = filter_var($positions[$i] ?? null, FILTER_VALIDATE_INT);
                if ($position === false || $position < 1 || $position > 8
                    || in_array($position, $used[$slot], true)) {
                    throw ValidationException::withMessages(['media_files'=>'Posisi gambar bertabrakan, tidak valid, atau melebihi delapan gambar per bagian.']);
                }
                $used[$slot][] = $position;
                $planned[] = [$file, $slot, $position, $alts[$i], $captions[$i]];
            }
            foreach ($planned as [$file, $slot, $position, $alt, $caption]) {
                $this->media->addToDraft($actor, $draft, $slot, $position, $file, $alt, $caption);
                if ($slot === 'option_E' && !$draft->options()->where('label','E')->exists()) {
                    $draft->options()->create(['label'=>'E','body'=>'','is_correct'=>false,'sort_order'=>5]);
                }
            }
        },3);
    }
}
