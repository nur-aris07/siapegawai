<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Internal draft ingestion primitive. Does not grant access to media to any visitor.
 * Uses exclusive file creation; immutable objects are never overwritten/deleted here.
 */
final class QuestionMediaStorageService
{
    public const DISK = 'question_media_private';

    private const FORMATS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    ];

    /** @return array{bytes:string,sha256:string,mime_type:string,byte_size:int,width_px:int,height_px:int,extension:string} */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $limit = (int) config('question_media.max_bytes', 5242880);
        $maxSide = (int) config('question_media.max_dimension', 4096);
        $maxPixels = (int) config('question_media.max_pixels', 12000000);
        if (!$path || !is_file($path) || !is_readable($path) || $limit <= 0 || $maxSide <= 0 || $maxPixels <= 0) {
            throw ValidationException::withMessages(['media' => 'Berkas gambar tidak valid.']);
        }
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > $limit) {
            throw ValidationException::withMessages(['media' => 'Ukuran media tidak memenuhi batas.']);
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || strlen($bytes) !== $size) {
            throw RuntimeException('Pembacaan media gagal.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bytes);
        $dimensions = @getimagesizefromstring($bytes);
        if (!is_string($mime) || !isset(self::FORMATS[$mime]) || $dimensions === false
            || (string) ($dimensions['mime'] ?? '') !== $mime) {
            throw ValidationException::withMessages(['media' => 'Hanya gambar JPEG, PNG, atau WebP yang valid diizinkan.']);
        }
        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 1 || $height < 1 || $width > $maxSide || $height > $maxSide
            || ($width * $height) > $maxPixels || $width > 65535 || $height > 65535) {
            throw ValidationException::withMessages(['media' => 'Dimensi gambar tidak memenuhi batas.']);
        }
        // Header saja tidak cukup: decoder wajib berhasil membaca seluruh bitmap.
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Ekstensi GD diperlukan untuk memvalidasi isi gambar.');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['media' => 'Isi gambar rusak atau tidak dapat didekode.']);
        }
        imagedestroy($image);
        return [
            'bytes' => $bytes, 'sha256' => hash('sha256', $bytes),
            'mime_type' => $mime, 'byte_size' => $size,
            'width_px' => $width, 'height_px' => $height,
            'extension' => self::FORMATS[$mime],
        ];
    }

    /**
     * Fail closed for drive-relative paths (E:foo) and UNC shares; accept
     * local absolute POSIX roots and Windows drive roots (both slash styles).
     * In PHP single-quoted regexes, the backslash character class must use
     * FOUR backslashes in source so PCRE receives [ / \\ ] correctly.
     */
    private static function isAbsoluteLocalRoot(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('~\A[A-Za-z]:[/\\\\]~', $path) === 1;
    }
    /** @param array{bytes:string,sha256:string,extension:string} $inspected */
    public function storeImmutable(array $inspected): string
    {
        $disk = Storage::disk(self::DISK);
        $configuredRoot = config('filesystems.disks.'.self::DISK.'.root');
        if (config('filesystems.disks.'.self::DISK.'.driver') !== 'local'
            || !is_string($configuredRoot)
            || !self::isAbsoluteLocalRoot($configuredRoot)) {
            throw new RuntimeException('Private media disk must be a configured absolute local path.');
        }
        // Resolve the ACTUAL disk root. Storage::fake() replaces the driver
        // root at runtime without necessarily modifying the config value.
        // Checking only the configured root would incorrectly reject tests
        // (or validate a different location than the one receiving bytes).
        $actualRoot = rtrim($disk->path(''), DIRECTORY_SEPARATOR);
        $public = realpath(public_path());
        $root = realpath($actualRoot);
        if ($root === false) {
            if (!@mkdir($actualRoot, 0700, true) && !is_dir($actualRoot)) {
                throw new RuntimeException('Gagal membuat direktori media privat.');
            }
            $root = realpath($actualRoot);
        }
        if ($root === false || is_link($actualRoot) || is_link($configuredRoot)
            || ($public !== false && ($root === $public || str_starts_with($root, $public.DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Root media berada pada jalur publik atau tidak aman.');
        }
        $hex = bin2hex(random_bytes(24));
        $key = 'questions/'.substr($hex, 0, 2).'/'.$hex.'.'.$inspected['extension'];
        $absolute = $disk->path($key);
        $directory = dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Direktori media privat tidak dapat dibuat.');
        }
        $realDirectory = realpath($directory);
        if ($realDirectory === false || !str_starts_with($realDirectory, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Direktori media keluar dari private root.');
        }
        $handle = @fopen($absolute, 'x+b'); // O_EXCL: never overwrite existing bytes
        if ($handle === false) {
            throw new RuntimeException('Gagal membuat objek media immutable.');
        }
        $ok = false;
        try {
            $written = fwrite($handle, $inspected['bytes']);
            $ok = $written === strlen($inspected['bytes']) && fflush($handle)
                && hash_file('sha256', $absolute) === $inspected['sha256'];
        } finally {
            fclose($handle);
        }
        if (!$ok) {
            // Preserve failed object for controlled reconciliation; do not delete referenced paths.
            throw new RuntimeException('Penyimpanan media gagal verifikasi; periksa orphan secara read-only.');
        }
        @chmod($absolute, 0600);
        return $key;
    }

    public function verify(string $key, string $expectedSha): bool
    {
        if (!preg_match('~^questions/[a-f0-9]{2}/[a-f0-9]{48}\.(?:jpg|png|webp)$~D', $key)) {
            return false;
        }
        $disk = Storage::disk(self::DISK);
        return $disk->exists($key) && hash_file('sha256', $disk->path($key)) === $expectedSha;
    }
}
