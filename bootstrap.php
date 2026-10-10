<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validasi konfigurasi wajib
if (empty($_ENV['API_KEY'])) {
    throw new RuntimeException('API_KEY belum dikonfigurasi.');
}
