<?php

function sanitizeProjectName($name) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function extractVideoId($url) {
    preg_match('/(v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    return $matches[2] ?? null;
}

function timeToSeconds($time) {
    $parts = array_reverse(explode(':', $time));
    $seconds = 0;

    if (isset($parts[0])) $seconds += (int)$parts[0];
    if (isset($parts[1])) $seconds += (int)$parts[1] * 60;
    if (isset($parts[2])) $seconds += (int)$parts[2] * 3600;

    return $seconds;
}

function secondsToTime($seconds) {
    if ($seconds < 0) $seconds = 0;

    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

$projectName = sanitizeProjectName($_POST['project_name']);
$url = $_POST['url'];
$starts = $_POST['start'];
$ends = $_POST['end'];
$captions = $_POST['caption'] ?? [];

$videoId = extractVideoId($url);
if (!$videoId) {
    die("Invalid URL");
}

//$timestamp = date("ymd");
$folderName = $projectName;
$projectPath = __DIR__ . "/projects/" . $folderName;

if (!is_dir($projectPath)) {
    mkdir($projectPath, 0777, true);
}

$cleanUrl = "https://www.youtube.com/watch?v=" . $videoId;

$fileNames = [];
foreach ($starts as $i => $start) {
    if (!$start || !$ends[$i]) {
        $fileNames[$i] = "";
        continue;
    }
    
    // Ambil caption atau gunakan clip_1 jika kosong
    $captionText = isset($captions[$i]) ? trim($captions[$i]) : '';
    $fileNameOnly = !empty($captionText) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $captionText) : "clip_" . ($i + 1);
    
    // Cek apakah file mp4 dengan nama tersebut sudah ada di folder project
    if (file_exists("{$projectPath}/final_{$fileNameOnly}.mp4") || file_exists("{$projectPath}/{$fileNameOnly}.mp4")) {
        // Tambahkan suffix -mmss (menit dan detik)
        $fileNameOnly .= "-" . date("is");
    }
    
    // Simpan nama final untuk digunakan di STEP 1 dan STEP 2
    $fileNames[$i] = $fileNameOnly;
}

$scriptPath = "{$projectPath}/cmd.sh";
$scriptContent = "#!/bin/bash\n\n";

if (file_exists($scriptPath)) {
    unlink($scriptPath);
}

// ======================
// STEP 1: PARALLEL DOWNLOAD
// Download start-10 detik sampai end
// Output selalu .mp4
// Best video + best audio
// ======================
foreach ($starts as $i => $start) {
    if (!$start || !$ends[$i]) continue;

    $end = $ends[$i];

    $startSec = timeToSeconds($start);
    $downloadStart = secondsToTime($startSec - 10);

//    $baseName = "{$projectPath}/clip_" . ($i + 1);
// Tangkap caption, bersihkan karakter aneh. Jika kosong, kembali pakai clip_1 dst.
//    $captionText = isset($captions[$i]) ? trim($captions[$i]) : '';
//    $fileNameOnly = !empty($captionText) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $captionText) : "clip_" . ($i + 1);
    $fileNameOnly = $fileNames[$i];
    $baseName = "{$projectPath}/{$fileNameOnly}";

    $scriptContent .= "
yt-dlp_linux -S \"proto:https\" \\
-f \"bv[ext=mp4]+ba[ext=m4a]\" \\
--merge-output-format mp4 \\
--download-sections \"*{$downloadStart}-{$end}\" \\
-o \"{$baseName}.mp4\" \\
\"{$cleanUrl}\" \\
>> \"{$projectPath}/log.log\" 2>&1 &
";
}

// wait all download complete
$scriptContent .= "\nwait\n\n";

// ======================
// STEP 2: FFMPEG
// Buang 10 detik awal hasil download
// ======================
foreach ($starts as $i => $start) {
    if (!$start || !$ends[$i]) continue;

//    $captionText = isset($captions[$i]) ? trim($captions[$i]) : '';
//    $fileNameOnly = !empty($captionText) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $captionText) : "clip_" . ($i + 1);
    $fileNameOnly = $fileNames[$i];
    $baseName = "{$projectPath}/{$fileNameOnly}";

    $scriptContent .= "
if [ -f \"{$baseName}.mp4\" ]; then
    ffmpeg -y \\
        -ss 10 \\
        -i \"{$baseName}.mp4\" \\
        -c:v libx264 -c:a aac \\
        -f mp4 \"{$baseName}.mp4.part\" >> \"{$projectPath}/log.log\" 2>&1 \\
    && rm \"{$baseName}.mp4\" && mv \"{$baseName}.mp4.part\" \"{$projectPath}/final_{$fileNameOnly}.mp4\"
else
    echo \"Error: yt-dlp gagal membuat {$baseName}.mp4\" >> \"{$projectPath}/log.log\"
fi
";
}

// ======================
// SAVE FILE
// ======================
file_put_contents($scriptPath, $scriptContent);

// permission execute
chmod($scriptPath, 0755);

// ======================
// RUN BACKGROUND
// ======================
exec("bash " . escapeshellarg($scriptPath) . " > " . escapeshellarg($projectPath . "/log.log") . " 2>&1 &");

$validClips = 0;
foreach ($starts as $i => $start) {
    if ($start && $ends[$i]) {
        $validClips++;
    }
}

$expectedFiles = [];
// $fileNames adalah array yang sudah kita buat di tahap persiapan sebelumnya
foreach ($fileNames as $fn) {
    if (!empty($fn)) {
        $expectedFiles[] = "final_{$fn}.mp4";
    }
}

file_put_contents(
    $projectPath . "/meta.json",
    json_encode([
        "total" => $validClips,
        "expected_files" => $expectedFiles
    ])
);

// Redirect
header("Location: status.php?project=" . urlencode($folderName));
exit;