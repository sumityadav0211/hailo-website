<?php

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sendJson($statusCode, $data)
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Only POST is allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    sendJson(405, [
        'success' => false,
        'message' => 'Method not allowed. This endpoint accepts POST only.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Read .env
|--------------------------------------------------------------------------
*/

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {

    sendJson(500, [
        'success' => false,
        'message' => '.env file not found.'
    ]);
}

if (!is_readable($envFile)) {

    sendJson(500, [
        'success' => false,
        'message' => '.env file is not readable.'
    ]);
}

$env = [];

$lines = file(
    $envFile,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

if ($lines === false) {

    sendJson(500, [
        'success' => false,
        'message' => 'Unable to read .env file.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Parse .env
|--------------------------------------------------------------------------
*/

foreach ($lines as $line) {

    $line = trim($line);

    // Empty line
    if ($line === '') {
        continue;
    }

    // Comment
    if (substr($line, 0, 1) === '#') {
        continue;
    }

    // Invalid line
    if (strpos($line, '=') === false) {
        continue;
    }

    $parts = explode('=', $line, 2);

    $key = trim($parts[0]);
    $value = trim($parts[1]);

    // Remove quotes if present
    if (strlen($value) >= 2) {

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (
            ($first === '"' && $last === '"') ||
            ($first === "'" && $last === "'")
        ) {
            $value = substr($value, 1, -1);
        }
    }

    $env[$key] = $value;
}

/*
|--------------------------------------------------------------------------
| EmailJS credentials
|--------------------------------------------------------------------------
*/

$serviceId = isset($env['EMAILJS_SERVICE_ID'])
    ? trim($env['EMAILJS_SERVICE_ID'])
    : '';

$templateId = isset($env['EMAILJS_TEMPLATE_ID'])
    ? trim($env['EMAILJS_TEMPLATE_ID'])
    : '';

$publicKey = isset($env['EMAILJS_PUBLIC_KEY'])
    ? trim($env['EMAILJS_PUBLIC_KEY'])
    : '';

$privateKey = isset($env['EMAILJS_PRIVATE_KEY'])
    ? trim($env['EMAILJS_PRIVATE_KEY'])
    : '';

/*
|--------------------------------------------------------------------------
| Check configuration
|--------------------------------------------------------------------------
*/

if ($serviceId === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_SERVICE_ID is missing in .env.'
    ]);
}

if ($templateId === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_TEMPLATE_ID is missing in .env.'
    ]);
}

if ($publicKey === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_PUBLIC_KEY is missing in .env.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Read JSON request
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'Empty request body.'
    ]);
}

$input = json_decode($rawInput, true);

if (!is_array($input)) {

    sendJson(400, [
        'success' => false,
        'message' => 'Invalid JSON request.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Read form fields
|--------------------------------------------------------------------------
*/

$firstName = trim(
    isset($input['first_name'])
        ? (string)$input['first_name']
        : ''
);

$lastName = trim(
    isset($input['last_name'])
        ? (string)$input['last_name']
        : ''
);

$fromEmail = trim(
    isset($input['from_email'])
        ? (string)$input['from_email']
        : ''
);

$phone = trim(
    isset($input['phone'])
        ? (string)$input['phone']
        : ''
);

$subject = trim(
    isset($input['subject'])
        ? (string)$input['subject']
        : ''
);

$message = trim(
    isset($input['message'])
        ? (string)$input['message']
        : ''
);

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/

if ($firstName === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'First name is required.'
    ]);
}

if ($lastName === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'Last name is required.'
    ]);
}

if ($fromEmail === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'Email address is required.'
    ]);
}

if ($subject === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'Subject is required.'
    ]);
}

if ($message === '') {

    sendJson(400, [
        'success' => false,
        'message' => 'Message is required.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Validate email
|--------------------------------------------------------------------------
*/

if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {

    sendJson(400, [
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
}

/*
|--------------------------------------------------------------------------
| EmailJS template parameters
|--------------------------------------------------------------------------
*/

$templateParams = [
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'from_email' => $fromEmail,
    'phone'      => $phone,
    'subject'    => $subject,
    'message'    => $message
];

/*
|--------------------------------------------------------------------------
| Build EmailJS request
|--------------------------------------------------------------------------
*/

$emailData = [
    'service_id' => $serviceId,
    'template_id' => $templateId,
    'user_id' => $publicKey,
    'template_params' => $templateParams
];

/*
|--------------------------------------------------------------------------
| Add Private Key if available
|--------------------------------------------------------------------------
|
| EmailJS calls this accessToken.
|
*/

if ($privateKey !== '') {

    $emailData['accessToken'] = $privateKey;
}

/*
|--------------------------------------------------------------------------
| Encode JSON
|--------------------------------------------------------------------------
*/

$jsonData = json_encode(
    $emailData,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ($jsonData === false) {

    sendJson(500, [
        'success' => false,
        'message' => 'Could not create EmailJS request.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Check cURL
|--------------------------------------------------------------------------
*/

if (!function_exists('curl_init')) {

    sendJson(500, [
        'success' => false,
        'message' => 'PHP cURL is not enabled on this hosting.'
    ]);
}

/*
|--------------------------------------------------------------------------
| EmailJS API
|--------------------------------------------------------------------------
*/

$emailJsUrl = 'https://api.emailjs.com/api/v1.0/email/send';

$ch = curl_init($emailJsUrl);

if ($ch === false) {

    sendJson(500, [
        'success' => false,
        'message' => 'Could not initialize cURL.'
    ]);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],

    CURLOPT_POSTFIELDS => $jsonData,

    CURLOPT_CONNECTTIMEOUT => 15,

    CURLOPT_TIMEOUT => 30,

    CURLOPT_SSL_VERIFYPEER => true,

    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);

$httpCode = (int)curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);

/*
|--------------------------------------------------------------------------
| cURL connection error
|--------------------------------------------------------------------------
*/

if ($response === false) {

    sendJson(502, [
        'success' => false,
        'message' => 'Could not connect to EmailJS.',
        'error' => $curlError
    ]);
}

/*
|--------------------------------------------------------------------------
| EmailJS success
|--------------------------------------------------------------------------
*/

if ($httpCode >= 200 && $httpCode < 300) {

    sendJson(200, [
        'success' => true,
        'message' => 'Email sent successfully.'
    ]);
}

/*
|--------------------------------------------------------------------------
| EmailJS error
|--------------------------------------------------------------------------
*/

$cleanResponse = trim((string)$response);

sendJson(
    $httpCode >= 400 ? $httpCode : 500,
    [
        'success' => false,
        'message' => 'EmailJS rejected the request.',
        'emailjs_response' => $cleanResponse !== ''
            ? $cleanResponse
            : 'No response received from EmailJS.',
        'http_code' => $httpCode
    ]
);

?>