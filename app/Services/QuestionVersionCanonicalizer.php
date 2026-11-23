<?php

namespace App\Services;

use App\Models\QuestionVersion;
use InvalidArgumentException;

/**
 * Explicitly NOT connected to the existing publish path until FIG-P2.
 * Text-only hash is byte-compatible with QuestionEditorialService::publish().
 */
final class QuestionVersionCanonicalizer
{
    public function hashTextOnly(QuestionVersion $version): string
    {
        $version->loadMissing('options');
        $input = [
            'body' => $version->body,
            'explanation' => $version->explanation,
            'options' => $version->options->map(
                fn ($o): array => [$o->label, $o->body, (bool) $o->is_correct]
            )->values()->all(),
        ];
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function hashWithMedia(QuestionVersion $version): string
    {
        $version->loadMissing(['options', 'media']);
        if ($version->media->isEmpty()) {
            return $this->hashTextOnly($version);
        }
        $media = $version->media->sortBy([
            ['slot', 'asc'], ['position', 'asc'],
        ])->map(function ($m): array {
            if (!preg_match('/^[a-f0-9]{64}$/D', (string) $m->sha256)) {
                throw new InvalidArgumentException('Checksum media invalid.');
            }
            return [
                'slot' => (string) $m->slot,
                'position' => (int) $m->position,
                'sha256' => (string) $m->sha256,
                'mime_type' => (string) $m->mime_type,
                'width_px' => (int) $m->width_px,
                'height_px' => (int) $m->height_px,
                'alt_text' => $m->alt_text,
                'caption' => $m->caption,
            ];
        })->values()->all();
        $input = [
            'canonical_format' => 'question-media-v1',
            'body' => $version->body,
            'explanation' => $version->explanation,
            'options' => $version->options->sortBy('sort_order')->values()->map(
                fn ($o): array => [$o->label, $o->body, (bool) $o->is_correct]
            )->all(),
            'media' => $media,
        ];
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
