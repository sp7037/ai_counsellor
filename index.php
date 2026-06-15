<?php

if (PHP_VERSION_ID < 80300) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>PHP 8.3 required</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1rem;line-height:1.5}code,pre{background:#f4f4f5;padding:.2rem .4rem;border-radius:.25rem}</style>';
    echo '</head><body>';
    echo '<h1>PHP 8.3 required</h1>';
    echo '<p>Apache is using PHP <strong>'.htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8').'</strong>. This app needs PHP 8.3 or newer.</p>';
    echo '<p>For local development, use the built-in server:</p>';
    echo '<pre>cd /d D:\\xampp\\htdocs\\ai_counsellor
D:\\php83\\php.exe artisan serve --host=127.0.0.1 --port=8000</pre>';
    echo '<p>Then open <a href="http://127.0.0.1:8000">http://127.0.0.1:8000</a>.</p>';
    echo '</body></html>';
    exit;
}

require __DIR__.'/public/index.php';
