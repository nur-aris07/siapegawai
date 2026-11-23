<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable private bytes pinned to a question version. Never serialize object_key.
 * No direct media route is registered by FIG-P1.
 */
class QuestionVersionMedia extends Model
{
    protected $table = 'question_version_media';

    protected $guarded = ['id', 'question_version_id', 'object_key', 'sha256'];

    protected $hidden = ['object_key', 'sha256'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'byte_size' => 'integer', 'width_px' => 'integer', 'height_px' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Media version reference is immutable. Detach a draft reference explicitly.');
        });
        static::deleting(function (self $media): void {
            $version = $media->version;
            if ($version === null || $version->review_status !== 'draft' || $version->published_at !== null
                || (int) $version->question?->current_version_id === (int) $version->id) {
                throw new \LogicException('Published or unknown question media cannot be deleted.');
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id');
    }

    /** Safe metadata only; callers must still enforce phase/context before serialization. */
    public function safeMetadata(): array
    {
        return [
            'slot' => $this->slot, 'position' => $this->position,
            'mime_type' => $this->mime_type, 'width_px' => $this->width_px,
            'height_px' => $this->height_px,
            'alt_text' => $this->alt_text, 'caption' => $this->caption,
        ];
    }
}
