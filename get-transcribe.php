<?php

function sanitizeProjectName($name) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function formatTime($time) {
    return explode(',', $time)[0];
}

// Fungsi parsing dipertahankan sesuai permintaan
function convertSrtToCustom($inputFile, $outputFile) {
    $content = file_get_contents($inputFile);
    $blocks = preg_split('/\\n\\s*\\n/', trim($content));

    $out = [];

    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        if (count($lines) < 3) continue;

        list($start, $end) = explode(' --> ', $lines[1]);

        $start = formatTime($start);
        $end = formatTime($end);

        $text = trim(preg_replace('/\\s+/', ' ', implode(' ', array_slice($lines, 2))));

        $out[] = "$start|$end|$text";
    }

    file_put_contents($outputFile, implode("\n", $out));
}

header('Content-Type: application/json');

if (!isset($_POST['url'])) {
    echo json_encode(["success" => false, "message" => "No URL provided"]);
    exit;
}

if (!isset($_POST['project_name'])) {
    echo json_encode(["success" => false, "message" => "No Project Name provided"]);
    exit;
}

$url = $_POST['url'] ?? '';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(["success" => false, "message" => "invalid url"]);
    exit;
}

$escapedUrl = escapeshellarg($url);
$ytDlpPath = "yt-dlp_linux";

$rawProjectName = trim($_POST['project_name']);
$projectName = sanitizeProjectName($rawProjectName);
if (strlen($projectName) === 0 || strlen($projectName) > 64) {
    echo json_encode(["success" => false, "message" => "Project name must be between 1 and 64 characters"]);
    exit;
}

$folderName = $projectName;
$dir = __DIR__ . "/projects/" . $folderName;

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($dir . "/url.txt", $url);
$lang = $_POST['lang'] ?? 'id';
$safeLang = escapeshellarg(trim($lang));

/* DOWNLOAD SUBTITLE */
$cmd = "$ytDlpPath \
--sleep-interval 3 \
--max-sleep-interval 6 \
--write-auto-subs \
--sub-lang $safeLang \
--sub-format srt \
--skip-download \
-o \"$dir/%(title)s.%(ext)s\" \
$escapedUrl 2>/dev/null";

shell_exec($cmd);

/* FIND SRT */
$files = glob("$dir/*.srt");

if (!$files) {
    echo json_encode(["success" => false, "message" => "subtitle not found"]);
    exit;
}

/* PROCESS: Jadikan 1 file utuh */
$outputFile = $dir . "/transcript.txt";
convertSrtToCustom($files[0], $outputFile);

echo json_encode([
    "success" => true,
    "message" => "done",
    "project_name" => $folderName
]);