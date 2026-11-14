import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Explicit entries keep Admin Workspace CSS/JS separate from public v4.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin-v1.css',
                'resources/js/admin-questions.js',
                'resources/js/admin-tryouts.js',
                'resources/js/admin-participants.js',
                'resources/js/admin-class-monitoring.js',
                'resources/js/admin-quick-quizzes.js',
                'resources/css/member-cat.css',
                'resources/css/s7-member.css',
                'resources/js/member-cat.js',
                'resources/js/member-quick-quizzes.js',
                'resources/js/member-qq-analytics.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
