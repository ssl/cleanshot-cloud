<?php
require 'aapje.php';

$config = parse_ini_file('.env');
aapje::setDbConfig([
    'host' => $config['dbhost'],
    'dbname' => $config['dbname'],
    'user' => $config['dbuser'],
    'password' => $config['dbpassword'],
]);

// View upload image
aapje::route('GET', '/@slug', function ($slug) {
    try {
        $upload = aapje::select('uploads', '*', ['slug' => $slug]);

        if (empty($upload)) {
            throw new Exception('Upload not found');
        }

        $filePath = 'uploads/' . $upload['id'] . '.png';

        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        $file = file_get_contents($filePath);
        aapje::response()->header('Content-Type', 'image/png')
            ->echo($file, false);
    } catch (Exception $e) {
        aapje::response()->statusCode(404)->echo(['error' => 'Not found']);
    }
});

// Get user info
aapje::route('GET', '/v1/user', function () {
    aapje::response()->echo(userData());
});

// Logout user
aapje::route('GET', '/v1/auth/logout', function () {
    aapje::response()->echo(['message' => 'ok']);
});

// Redeem login code
aapje::route('POST', '/v1/auth/code/redeem', function () {
    aapje::response()->echo(userData());
});

// Generate image upload URL
aapje::route('POST', '/v1/media/image', function () {
    $data = aapje::request()->input();

    // Generate a unique slug
    $foundSlug = false;
    while (!$foundSlug) {
        try {
            $slug = uniqid('', true);
            if (empty(aapje::select('uploads', '*', ['slug' => $slug]))) {
                $foundSlug = true;
            }
        } catch (Exception $e) {
            return;
        }
    }

    $id = aapje::insert('uploads', [
        'slug' => $slug,
        'created_at' => time(),
    ]);

    $url = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $slug;
    $response = [
        "data" => [
            "media" => [
                "full_url" => $url,
                "download_url" => $url,
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
        $upload = aapje::select('uploads', '*', ['id' => $id]);
        if (empty($upload)) {
            throw new Exception('Upload not found');
        }

        if ($upload['completed'] == 1) {
            throw new Exception('Upload already completed');
        }

        $file = aapje::request()->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File not uploaded');
        }

        $fileContents = file_get_contents($file['tmp_name']);

        if ($fileContents === false || $fileContents === null) {
            throw new Exception('File not read');
        }

        file_put_contents('uploads/' . $id . '.png', $fileContents);

        // Set status code to 204 No Content
        aapje::response()->statusCode(204)->echo('');
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => $e->getMessage()]);
    }
});

// Upload completed
aapje::route('POST', '/v1/media/image/@id/upload-completed', function ($id) {
    aapje::update('uploads', ['completed' => 1], ['id' => $id]);
    aapje::response()->echo([]);
});

function userData() {
    $userData = file_get_contents('user.json');
    return json_decode($userData, true);
}

// Run the application
aapje::run();