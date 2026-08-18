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

    header('Allow: POST');

    sendJson(405, [
        'success' => false,
        'message' => 'Method not allowed. This endpoint accepts POST only.'
    ]);
}

/*
|--------------------------------------------------------------------------
| EmailJS configuration
|--------------------------------------------------------------------------
|
| IMPORTANT:
| These values come from Vercel Environment Variables.
| Do NOT read .env from the GitHub repository.
|
*/

$serviceId = getenv('EMAILJS_SERVICE_ID') ?: '';

$templateId = getenv('EMAILJS_TEMPLATE_ID') ?: '';

$publicKey = getenv('EMAILJS_PUBLIC_KEY') ?: '';

$privateKey = getenv('EMAILJS_PRIVATE_KEY') ?: '';

/*
|--------------------------------------------------------------------------
| Check EmailJS configuration
|--------------------------------------------------------------------------
*/

if ($serviceId === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_SERVICE_ID is missing in Vercel Environment Variables.'
    ]);
}

if ($templateId === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_TEMPLATE_ID is missing in Vercel Environment Variables.'
    ]);
}

if ($publicKey === '') {

    sendJson(500, [
        'success' => false,
        'message' => 'EMAILJS_PUBLIC_KEY is missing in Vercel Environment Variables.'
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
    (string)($input['first_name'] ?? '')
);

$lastName = trim(
    (string)($input['last_name'] ?? '')
);

$fromEmail = trim(
    (string)($input['from_email'] ?? '')
);

$phone = trim(
    (string)($input['phone'] ?? '')
);

$subject = trim(
    (string)($input['subject'] ?? '')
);

$message = trim(
    (string)($input['message'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Validate required fields
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

    'last_name' => $lastName,

    'from_email' => $fromEmail,

    'phone' => $phone,

    'subject' => $subject,

    'message' => $message
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
| Private Key
|--------------------------------------------------------------------------
|
| Only add it if you have configured it in Vercel.
|
*/

if ($privateKey !== '') {

    $emailData['accessToken'] = $privateKey;
}

/*
|--------------------------------------------------------------------------
| Encode EmailJS request
|--------------------------------------------------------------------------
*/

$jsonData = json_encode(

    $emailData,

    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE
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
| Send request to EmailJS
|--------------------------------------------------------------------------
*/

$emailJsUrl =
    'https://api.emailjs.com/api/v1.0/email/send';

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

/*
|--------------------------------------------------------------------------
| Execute request
|--------------------------------------------------------------------------
*/

$response = curl_exec($ch);

$httpCode = (int)curl_getinfo(

    $ch,

    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);

/*
|--------------------------------------------------------------------------
| cURL error
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

$cleanResponse = trim(
    (string)$response
);

sendJson(

    $httpCode >= 400
        ? $httpCode
        : 500,

    [

        'success' => false,

        'message' =>
            'EmailJS rejected the request.',

        'emailjs_response' =>
            $cleanResponse !== ''
                ? $cleanResponse
                : 'No response received from EmailJS.',

        'http_code' =>
            $httpCode
    ]
);

?>
