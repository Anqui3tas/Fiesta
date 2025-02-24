<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\\inetpub\\wwwroot\\php_error.log'); // Adjust as needed

$data = ['success' => false, 'message' => 'Unknown error'];

try {
    // Get CAPTCHA response from the form
    $captchaResponse = $_POST['cf-turnstile-response'] ?? '';

    if (empty($captchaResponse) || $captchaResponse === 'null') {
        throw new Exception('CAPTCHA verification failed: No valid response received.');
    }

    // Log the received Turnstile response
    error_log("Received Turnstile Response: " . $captchaResponse);

    // Verify with Cloudflare
    $secretKey = "SECRET KEY"; // Replace with your actual secret key
        // ENABLE CURL IN YOUR WEB HOST, IN MY CASE IIS
        // CHANGE CLOUDFLARE TURNSTILE TYPE TO NON-INTERACTIVE
    $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

    $verifyData = [
        'secret' => $secretKey,
        'response' => $captchaResponse
    ];

    // Initialize cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verifyData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification (only for debugging)

    $captchaVerifyResponse = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log("cURL Error: " . $curlError);
        throw new Exception('CAPTCHA verification failed: cURL error - ' . $curlError);
    }

    curl_close($ch);

    // Log Cloudflare's response for debugging
    error_log("Cloudflare API Response: " . ($captchaVerifyResponse ?: 'EMPTY RESPONSE'));

    $captchaResult = json_decode($captchaVerifyResponse, true);

    if (!$captchaResult || !isset($captchaResult['success']) || !$captchaResult['success']) {
        throw new Exception('CAPTCHA verification failed: ' . json_encode($captchaResult));
    }

    // Proceed with registration after CAPTCHA verification
    $serverName = "localhost";
    $connectionOptions = [
        "Database" => "Account",
        "Uid" => "sa",
        "PWD" => "PASSWORD"
    ];

    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        throw new Exception('Database connection failed: ' . print_r(sqlsrv_errors(), true));
    }

    $sUserID = $_POST['username'] ?? ''; 
    $sUserPW = md5($_POST['password'] ?? ''); 
    $sUserName = $_POST['username'] ?? '';
    $sUserIP = $_SERVER['REMOTE_ADDR']; 
    $bIsBlock = 0;
    $bIsDelete = 0;
    $nAuthID = 1;
    $dDate = date('Y-m-d H:i:s');
    $sUserNo = 0;
    $blsBlock = 0;
    $blsDelete = 0;

    $sql = "INSERT INTO [dbo].[tUser] 
            ([sUserID], [sUserPW], [sUserName], [sUserIP], [bIsBlock], [bIsDelete], [nAuthID], [dDate], [sUserNo], [blsBlock], [blsDelete]) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [$sUserID, $sUserPW, $sUserName, $sUserIP, $bIsBlock, $bIsDelete, $nAuthID, $dDate, $sUserNo, $blsBlock, $blsDelete];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $data['success'] = true;
    $data['message'] = 'User registered successfully';

} catch (Exception $e) {
    $data['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($data);
?>