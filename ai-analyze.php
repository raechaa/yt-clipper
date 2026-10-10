<?php
header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap.php';

// 1. Kredensial & Model
$apiKey = $_ENV['API_KEY']; 
$model = $_ENV['AI_MODEL'];
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

$project = $input['project'] ?? '';
if (empty($project)) {
    echo json_encode(["success" => false, "message" => "Project name is required."]);
    exit;
}

$projectFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project);
$transcriptFile = __DIR__ . "/projects/" . $projectFolder . "/transcript.txt";

if (!file_exists($transcriptFile)) {
    echo json_encode(["success" => false, "message" => "Transcript file tidak ditemukan. Download transcribe terlebih dahulu."]);
    exit;
}

$transcriptContent = file_get_contents($transcriptFile);

// 2. Instruksi Sistem & Skema JSON
$systemInstruction = "Anda adalah \"Viral Content Curator\" ahli untuk TikTok, Instagram Reels, dan YouTube Shorts. Tugas Anda adalah menganalisa transkrip video yang diberikan dan mencari 3 hingga 5 segmen (klip) terbaik yang memiliki potensi viral tinggi (lucu, dramatis, kontroversial, penuh wawasan, atau titik balik).
Syarat mutlak:
1. Durasi tiap klip tidak boleh lebih dari 2 menit (120 detik).
2. Anda HARUS mengambil 'start' dan 'end' time persis (exact match) seperti yang tertulis dalam transkrip. Jangan membulatkan atau mengubah waktu.
3. Output harus STRICTLY berupa JSON array dengan struktur berikut, tanpa teks pengantar atau penutup:
[
  {
    \"start\": \"00:01:10\",
    \"end\": \"00:02:15\",
    \"title\": \"Judul Singkat Clickbait\",
    \"reason\": \"Alasan kenapa segmen ini berpotensi viral...\",
    \"caption\": \"Caption sosmed beserta hashtag (max 64 huruf)\"
  }
]";

$payload = [
    "system_instruction" => [
        "parts" => [["text" => $systemInstruction]]
    ],
    "contents" => [
        [
            "parts" => [["text" => "Berikut adalah transkripnya:\n\n" . $transcriptContent]]
        ]
    ],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

// 3. Eksekusi cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(["success" => false, "message" => "AI API Error: Gagal menghubungi Google AI Studio."]);
    exit;
}

$responseData = json_decode($response, true);
$aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($aiText)) {
    echo json_encode(["success" => false, "message" => "AI tidak mengembalikan konten."]);
    exit;
}

$aiJson = json_decode($aiText, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["success" => false, "message" => "AI tidak mengembalikan format JSON yang valid."]);
    exit;
}

echo json_encode(["success" => true, "data" => $aiJson]);
