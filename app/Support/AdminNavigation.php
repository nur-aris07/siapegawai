<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Presentation-only navigation map. Authorization stays in route middleware and controllers.
 * All destinations are existing parameter-free named GET routes; editor family routes only
 * determine active state, and must never be linked globally.
 */
final class AdminNavigation
{
    public static function definition(): array
    {
        return [
            ['type' => 'link', 'id' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'chart-bar', 'route' => 'admin.dashboard', 'matches' => ['admin.dashboard']],
            ['type' => 'group', 'id' => 'people', 'label' => 'Peserta & Registrasi', 'icon' => 'users', 'items' => [
                ['label' => 'Registrasi Peserta', 'icon' => 'users', 'route' => 'registration.index', 'matches' => ['registration.*']],
                ['label' => 'Direktori Peserta', 'icon' => 'users', 'route' => 'admin.participants.index', 'matches' => ['admin.participants.*']],
            ]],
            ['type' => 'group', 'id' => 'questions', 'label' => 'Bank Soal', 'icon' => 'book', 'items' => [
                ['label' => 'Bank Soal', 'icon' => 'book', 'route' => 'questions.admin.index', 'matches' => ['questions.admin.*']],
                ['label' => 'Kategori & Topik', 'icon' => 'book', 'route' => 'subjects.admin.index', 'matches' => ['subjects.admin.*']],
                ['label' => 'Impor CSV', 'icon' => 'clipboard', 'route' => 'questions.import.index', 'matches' => ['questions.import.*']],
            ]],
            ['type' => 'group', 'id' => 'learning', 'label' => 'Program Belajar', 'icon' => 'graduation-cap', 'items' => [
                ['label' => 'Template Tryout', 'icon' => 'clipboard', 'route' => 'tryouts.admin.index', 'matches' => ['tryouts.admin.*']],
                ['label' => 'Quiz Kilat', 'icon' => 'clipboard', 'route' => 'admin.quick-quizzes.index', 'matches' => ['admin.quick-quizzes.*']],
                ['label' => 'Kelas & Materi', 'icon' => 'book', 'route' => 'admin.classes.index', 'matches' => ['admin.classes.*', 'admin.class-practices.*']],
            ]],
            ['type' => 'group', 'id' => 'packages', 'label' => 'Paket & Kuota', 'icon' => 'package', 'items' => [
                ['label' => 'Katalog Paket', 'icon' => 'book', 'route' => 'admin.packages.index', 'matches' => ['admin.packages.*'], 'role' => 'superadmin'],
                ['label' => 'Manfaat Quiz Kilat', 'icon' => 'clipboard', 'route' => 'admin.qq-quota.index', 'matches' => ['admin.qq-quota.*'], 'role' => 'superadmin'],
            ]],
            ['type' => 'group', 'id' => 'system', 'label' => 'Pengaturan Sistem', 'icon' => 'settings', 'items' => [
                ['label' => 'Pengaturan Website', 'icon' => 'settings', 'route' => 'admin.site-settings.index', 'matches' => ['admin.site-settings.*'], 'role' => 'superadmin'],
                ['label' => 'Email & SMTP', 'icon' => 'info', 'route' => 'admin.account-emails.index', 'matches' => ['admin.account-emails.*'], 'role' => 'superadmin'],
            ]],
            ['type' => 'link', 'id' => 'security', 'label' => 'Keamanan Akun', 'icon' => 'shield-check', 'route' => 'admin.security.edit', 'matches' => ['admin.security.*']],
        ];
    }

    public static function forUser(User $user, ?string $routeName): array
    {
        $navigation = [];
        foreach (self::definition() as $entry) {
            if (($entry['role'] ?? null) && $entry['role'] !== $user->role) {
                continue;
            }
            if ($entry['type'] === 'group') {
                $visible = [];
                foreach ($entry['items'] as $item) {
                    if (($item['role'] ?? null) && $item['role'] !== $user->role) {
                        continue;
                    }
                    $item['active'] = self::matches($routeName, $item['matches']);
                    $visible[] = $item;
                }
                if ($visible === []) {
                    continue;
                }
                $entry['items'] = $visible;
                $entry['active'] = collect($visible)->contains('active', true);
            } else {
                $entry['active'] = self::matches($routeName, $entry['matches']);
            }
            $navigation[] = $entry;
        }
        return $navigation;
    }

    private static function matches(?string $routeName, array $patterns): bool
    {
        if (! $routeName) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }
        return false;
    }
}
