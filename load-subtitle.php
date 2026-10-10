<?php
header('Content-Type: application/json');

if (!isset($_GET['project'])) {
    echo json_encode(["success" => false, "message" => "Project not specified"]);
    exit;
}

$project = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_GET['project']);
$file = __DIR__ . "/projects/" . $project . "/transcript.txt";
$urlFile = __DIR__ . "/projects/" . $project . "/url.txt";

if (!file_exists($file)) {
    echo json_encode(["success" => false, "message" => "Transcript file tidak ditemukan. Download transcribe terlebih dahulu."]);
    exit;
}

$savedUrl = file_exists($urlFile) ? trim(file_get_contents($urlFile)) : "";
$content = file_get_contents($file);
$lines = explode("\n", trim($content));
$data = [];

foreach($lines as $line) {
    if (empty(trim($line))) continue;
    
    $parts = explode('|', $line, 3);
    if(count($parts) === 3) {
        $data[] = [
            'start' => $parts[0],
            'end' => $parts[1],
            'text' => $parts[2]
        ];
    }
}

echo json_encode([
    "success" => true,
    "data" => $data,
    "url" => $savedUrl
]);