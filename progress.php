<?php

$project = $_GET['project'] ?? '';
$path = __DIR__ . "/projects/" . $project;

if (!is_dir($path)) {
    echo json_encode(["error" => "not found"]);
    exit;
}

$metaFile = $path . "/meta.json";
$total = 0;
$expectedFiles = [];
$expectedBaseNames = [];

// Ambil data total dan expected_files dari meta.json
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    $total = $meta['total'] ?? 0;
    $expectedFiles = $meta['expected_files'] ?? [];
    
    // Buat daftar nama file mentah (tanpa awalan final_)
    foreach ($expectedFiles as $ef) {
        $expectedBaseNames[] = str_replace('final_', '', $ef);
    }
}

$completed = [];
$downloading = [];
$downloaded = [];

foreach (scandir($path) as $f) {
    if ($f === '.' || $f === '..') continue;

    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $fullPath = $path . "/" . $f;

    if (in_array($ext, ['mp4'])) {
        if (str_starts_with($f, 'final_')) {
            // HANYA hitung jika file ini bagian dari proses saat ini
            if (in_array($f, $expectedFiles)) {
                $completed[] = $f;
            }
        }
        else
        {
            // HANYA hitung jika file mentah ini bagian dari proses saat ini
            if (in_array($f, $expectedBaseNames)) {
                $downloaded[] = $f;
            }
        }
    }
    else if ($ext === 'part') {
        // Cek apakah file .part ini milik salah satu file yang sedang diproses
        foreach ($expectedBaseNames as $baseName) {
            if (str_starts_with($f, $baseName)) {
                $downloading[] = [
                    "name" => $f,
                    "size" => filesize($fullPath)
                ];
                break; // Hentikan loop jika sudah cocok
            }
        }
    }
}

echo json_encode([
    "total" => $total,
    "completed" => count($completed),
    "files" => $completed,
    "downloading" => $downloading,
    "downloaded" => $downloaded
]);