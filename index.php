<?php
require 'aapje.php';

$config = parse_ini_file('.env');

aapje::setConfig([
    'cors' => [
        'enabled' => true
    ],
]);

// Upload and redirect to imgur
aapje::route('GET', '/@slug', function ($slug) {
    try {
        if(!preg_match('/^[a-z0-9]+$/', $slug)) {
            throw new Exception('Invalid slug');
        }
        
        $filePath = "uploads/$slug.png";
        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        $file = Helpers::getFile($filePath);

        $imgurUrl = uploadToImgur($filePath);
        if (!$imgurUrl) {
            throw  new Exception('Failed to upload to Imgur');
        }

        unlink($filePath);
        header('Location: ' . $imgurUrl);
        exit();
        
    } catch (Exception $e) {
        aapje::response()->statusCode(404)->echo(['error' => 'Not found']);
    }
});

// Maintenance ping
aapje::route('GET', '/v1/maintenance', function () {
    aapje::response()
    ->echo('all ok :)');
});

// Get user info
aapje::route('GET', '/v1/user', function () {
    aapje::response()->echo(userData());
});

// Logout user
aapje::route('GET', '/v1/auth/logout', function () {
    aapje::response()->echo(['message' => 'ok']);
});

// Login user
aapje::route('POST', '/v1/auth/login', function () {
    aapje::response()->echo(userData());
});

// Code user
aapje::route('POST', '/v1/auth/code', function () {
    aapje::response()->echo(userData());
});

// Redeem login code
aapje::route('POST', '/v1/auth/code/redeem', function () {
    aapje::response()->echo(userData());
});

// Generate image upload URL
aapje::route('POST', '/v1/media/image', function () {
    $id = bin2hex(random_bytes(5));
    $response = [
        "data" => [
            "media" => [
                "full_url" => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $id,
                "download_url" => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $id,
                "id" => $id,
            ],
            "upload_url" => 'https://' . $_SERVER['HTTP_HOST'] . '/v1/media/upload/' . $id,
        ],
    ];
    aapje::response()->echo($response);
});

// Upload image
aapje::route('POST', '/v1/media/upload/@id', function ($id) {
    try {
        $file = aapje::request()->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File not uploaded');
        }

        $image = Helpers::getFile($file['tmp_name']);
        if ($image === false || $image === null) {
            throw new Exception('File not read');
        }

        Helpers::putFile("uploads/{$id}.png", $image);

        aapje::response()->statusCode(204)->echo('');
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => $e->getMessage()]);
    }
});

// Upload completed
aapje::route('POST', '/v1/media/image/@id/upload-completed', function ($id) {
    aapje::response()->echo([]);
});

// Get user data
function userData() {
    $userData = Helpers::getFile('user.json');
    return json_decode($userData, true);
}

// Upload image to imgur
function uploadToImgur($filePath) {
    try {
        $imageData = base64_encode(file_get_contents($filePath));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID b50a7351eee91f0',
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'image' => $imageData,
            'type' => 'base64'
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && $data['success'] && isset($data['data']['link'])) {
                return $data['data']['link'];
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Run the API
aapje::run();
