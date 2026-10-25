<?php

// Entry point Laravel untuk Vercel (runtime vercel-php).
// Filesystem Vercel read-only kecuali /tmp, jadi siapkan folder tulis di sini.
foreach ([
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/storage/app/public',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

require __DIR__ . '/../public/index.php';
