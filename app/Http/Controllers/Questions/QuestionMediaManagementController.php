<?php

namespace App\Http\Controllers\Questions;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionVersionMedia;
use App\Services\QuestionEditorialMediaCoordinator;
use App\Services\QuestionMediaStorageService;
use App\Services\QuestionVersionMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** P2 routes are strictly admin-only, never re-used for QQ/CAT/Class media delivery. */
final class QuestionMediaManagementController extends Controller
{
    public function upload(Request $request, Question $question, QuestionEditorialMediaCoordinator $coordinator, QuestionVersionMediaService $service): RedirectResponse
    {
        Gate::authorize('update', $question);
        $input = $request->validate([
            'edit_token' => ['required','string','size:64'],
            'slot' => ['required','in:stem,option_A,option_B,option_C,option_D,option_E,explanation'],
            'position' => ['required','integer','between:1,8'],
            'media' => ['required','file','max:5120'],
            'alt_text' => ['nullable','string','max:300'],
            'caption' => ['nullable','string','max:300'],
        ]);
        $coordinator->withLockedDraft($question, $input['edit_token'], function (Question $locked, $draft) use ($service,$request,$input): void {
            $service->addToDraft($request->user(), $draft, $input['slot'], (int) $input['position'],
                $request->file('media'), $input['alt_text'] ?? null, $input['caption'] ?? null);
            // Option E is optional, but a media-only E must have a stable option row.
            if ($input['slot'] === 'option_E' && !$draft->options()->where('label','E')->exists()) {
                $draft->options()->create(['label'=>'E','body'=>'','is_correct'=>false,'sort_order'=>5]);
                $draft->touch();
            }
        });
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Gambar privat ditambahkan pada draft.');
    }

    public function detach(Request $request, Question $question, QuestionVersionMedia $media, QuestionEditorialMediaCoordinator $coordinator, QuestionVersionMediaService $service): RedirectResponse
    {
        Gate::authorize('update', $question);
        $data = $request->validate(['edit_token'=>['required','string','size:64'], 'confirmed_detach'=>['required','accepted']]);
        $coordinator->withLockedDraft($question, $data['edit_token'], function (Question $locked, $draft) use ($media,$service,$request): void {
            $owned = $draft->media()->whereKey($media->id)->firstOrFail();
            $service->detachFromDraft($request->user(), $owned);
        });
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Referensi media dilepas; bytes immutable disimpan untuk audit/recovery.');
    }

    public function reorder(Request $request, Question $question, QuestionEditorialMediaCoordinator $coordinator): RedirectResponse
    {
        Gate::authorize('update', $question);
        $data = $request->validate([
            'edit_token'=>['required','string','size:64'],
            'slot'=>['required','in:stem,option_A,option_B,option_C,option_D,option_E,explanation'],
            'order'=>['required','array','min:2','max:8'],
            'order.*'=>['required','integer','min:1'],
        ]);
        $coordinator->reorder($question, $request->user(), $data['slot'], $data['order'], $data['edit_token']);
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Urutan gambar draft disimpan.');
    }

    public function metadata(Request $request, Question $question, QuestionVersionMedia $media, QuestionEditorialMediaCoordinator $coordinator): RedirectResponse
    {
        Gate::authorize('update', $question);
        $data = $request->validate([
            'edit_token'=>['required','string','size:64'],
            'alt_text'=>['nullable','string','max:300'],
            'caption'=>['nullable','string','max:300'],
        ]);
        $coordinator->changeMetadata($question, $request->user(), $media->id,
            $data['alt_text'] ?? null, $data['caption'] ?? null, $data['edit_token']);
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Metadata gambar draft diperbarui.');
    }

    /** Pure GET, no call to attempt/read/settle/expire or draft state mutation. */
    public function preview(Question $question, QuestionVersionMedia $media, QuestionMediaStorageService $storage): StreamedResponse
    {
        Gate::authorize('view', $question);
        $owned = $question->versions()->whereKey($media->question_version_id)->exists()
            && $media->version?->question_id === $question->id;
        abort_unless($owned, 404);
        abort_unless(in_array($media->mime_type, ['image/jpeg','image/png','image/webp'], true), 404);
        abort_unless($storage->verify($media->object_key, $media->sha256), 404);
        $stream = Storage::disk(QuestionMediaStorageService::DISK)->readStream($media->object_key);
        abort_unless(is_resource($stream), 404);
        return response()->stream(function () use ($stream): void {
            try { fpassthru($stream); } finally { fclose($stream); }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="question-preview"',
            'Content-Length' => (string) $media->byte_size,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
