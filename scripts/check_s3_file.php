<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

$path = $argv[1] ?? 'ordenes/ODT-000016/01KSJWFYKN2AS4EFJKEX2M8QS5.jpeg';
$disk = Storage::disk('s3');

echo "Path: $path\n";
try {
    $exists = $disk->exists($path);
} catch (Exception $e) {
    echo "Error checking existence: " . $e->getMessage() . "\n";
    exit(1);
}

if (! $exists) {
    echo "No existe en S3.\n";
    exit(1);
}

try {
    $mime = $disk->mimeType($path);
} catch (Exception $e) {
    $mime = null;
}

try {
    $size = $disk->size($path);
} catch (Exception $e) {
    $size = null;
}

echo "mimeType: " . ($mime ?? 'null') . "\n";
echo "size: " . ($size ?? 'null') . "\n";

// Read first bytes
$stream = $disk->readStream($path);
if ($stream) {
    $bytes = fread($stream, 32);
    fclose($stream);
    $hex = bin2hex($bytes);
    echo "first32bytes(hex): $hex\n";
    // Also show ASCII snippet
    $snippet = preg_replace('/[^\x20-\x7E]/', '.', $bytes);
    echo "first32bytes(text): $snippet\n";
} else {
    echo "No se pudo leer el stream.\n";
}

// Show basename and extension
$basename = basename($path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
echo "basename: $basename\n";
echo "extension: $extension\n";
