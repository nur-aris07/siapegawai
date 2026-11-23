<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\QuestionVersionMedia;
use App\Services\QuestionMediaContextAccess;
use App\Services\QuestionMediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuestionParticipantMediaController extends Controller
{
    public function qq(Request $request, int $attempt, int $item, QuestionVersionMedia $media,
        QuestionMediaContextAccess $access, QuestionMediaStorageService $storage): StreamedResponse
    {
        $access->authorize('qq',(int)$request->user()->id,$attempt,$item,$media);
        return $this->stream($media,$storage);
    }
    public function cat(Request $request, int $attempt, int $item, QuestionVersionMedia $media,
        QuestionMediaContextAccess $access, QuestionMediaStorageService $storage): StreamedResponse
    {
        $access->authorize('cat',(int)$request->user()->id,$attempt,$item,$media);
        return $this->stream($media,$storage);
    }
    public function practice(Request $request, int $class, int $attempt, int $item, QuestionVersionMedia $media,
        QuestionMediaContextAccess $access, QuestionMediaStorageService $storage): StreamedResponse
    {
        $access->authorize('class',(int)$request->user()->id,$attempt,$item,$media,$class);
        return $this->stream($media,$storage);
    }
    private function stream(QuestionVersionMedia $media, QuestionMediaStorageService $storage): StreamedResponse
    {
        abort_unless(in_array($media->mime_type, ['image/jpeg','image/png','image/webp'], true), 404);
        abort_unless($storage->verify($media->object_key,$media->sha256), 404);
        $stream = Storage::disk(QuestionMediaStorageService::DISK)->readStream($media->object_key);
        abort_unless(is_resource($stream),404);
        return response()->stream(static function () use ($stream): void {
            try { fpassthru($stream); } finally { fclose($stream); }
        },200,[
            'Content-Type'=>$media->mime_type,
            'Content-Length'=>(string)$media->byte_size,
            'Content-Disposition'=>'inline; filename="question-image"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'Pragma'=>'no-cache', 'X-Content-Type-Options'=>'nosniff',
            'X-Robots-Tag'=>'noindex, nofollow, noarchive',
        ]);
    }
}
