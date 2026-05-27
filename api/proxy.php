<?php
/**
 * Universal CORS Proxy (Cara kerja mirip AllOrigins / CORS Anywhere)
 * Cara Penggunaan: http://domain-anda.com/proxy.php?url=http://target-api.com/endpoint
 */

// 1. Atur Header CORS agar ramah terhadap semua Client/Simulator
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 2. Tangani Preflight Request (OPTIONS) dari Browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// 3. Ambil URL Target dari Parameter 'url'
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false, 
        "message" => "Missing 'url' parameter. Usage: proxy.php?url=http://example.com/api"
    ]);
    exit();
}

$target_url = $_GET['url'];

// Validasi sederhana memastikan format URL valid
if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Invalid URL format."]);
    exit();
}

// 4. Inisialisasi cURL untuk Menembak ke URL Target
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Ikuti jika ada redirect
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// 5. Teruskan Payload POST / PUT jika ada
$input_data = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($input_data)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input_data);
}

// 6. Teruskan Header Penting dari Simulator (termasuk Bearer Token)
$request_headers = [];
foreach (getallheaders() as $name => $value) {
    $name_lower = strtolower($name);
    // Teruskan header esensial, abaikan host bawaan proxy
    if (in_array($name_lower, ['content-type', 'authorization', 'accept'])) {
        $request_headers[] = "$name: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

// 7. Eksekusi Request dan Ambil Info
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Tangani jika cURL Error
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Proxy Error: " . $error_msg]);
    curl_close($ch);
    exit();
}

curl_close($ch);

// 8. Kembalikan Response Asli ke Simulator
http_response_code($http_code);
if ($content_type) {
    header("Content-Type: " . $content_type);
} else {
    header("Content-Type: application/json");
}

echo $response;