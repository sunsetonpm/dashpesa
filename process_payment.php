<?php
session_start();

// --- 1. CONFIGURATION ---
// !! IMPORTANT !!
// REPLACE WITH YOUR ACTUAL DARAJA API CREDENTIALS FROM SAFARICOM DEVELOPER PORTAL
$consumerKey = getenv('MPESA_CONSUMER_KEY'); 
$consumerSecret = getenv('MPESA_CONSUMER_SECRET');
$mpesaShortCode = getenv('MPESA_SHORTCODE');
$mpesaPasskey = getenv('MPESA_PASSKEY');
$callbackUrl = getenv('MPESA_CALLBACK_URL');

// Define API endpoints (use 'sandbox' for testing, 'api.safaricom.co.ke' for production)
$authUrl = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
$stkPushUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
// --- END CONFIGURATION ---


// Security check: Make sure user and form data exists
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['phone_number']) && isset($_POST['service_fee'])) {

    $phone_number = $_SESSION['phone_number']; // User's phone
    $service_fee = $_POST['service_fee'];     // The fee to charge
    $loan_amount = $_POST['loan_amount'];   // The loan they applied for
    
    // --- 3. REFORMAT PHONE NUMBER ---
    // Change format from 07... or 01... to 254...
    if (substr($phone_number, 0, 1) == "0") {
        $formattedPhone = "254" . substr($phone_number, 1);
    } else {
        $formattedPhone = $phone_number; // Assume it's already in 254... format
    }
    
    // For sandbox testing, Safaricom recommends using amount '1'
    // For production, use the actual $service_fee
    // $stkAmount = $service_fee; 
    $stkAmount = 1; // <-- Uncomment this line for SANDBOX testing ONLY

    // --- 4. GET ACCESS TOKEN ---
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $message = urlencode("Error: " . curl_error($ch));
        header("Location: status.php?status=error&message=$message");
        exit;
    }
    curl_close($ch);

    $authData = json_decode($response);
    
    if (!isset($authData->access_token)) {
        $message = urlencode("Error: Unable to get API access token. Check your Consumer Key and Secret.");
        header("Location: status.php?status=error&message=$message");
        exit;
    }
    $accessToken = $authData->access_token;

    // --- 5. INITIATE STK PUSH ---
    
    // Generate timestamp (YmdHis)
    $timestamp = date('YmdHis');
    
    // Generate password (Shortcode + Passkey + Timestamp, base64 encoded)
    $password = base64_encode($mpesaShortCode . $mpesaPasskey . $timestamp);

    // Build the STK Push payload
    $stkPayload = [
        'BusinessShortCode' => $mpesaShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerBuyGoodsOnline', // or 'CustomerPayBillOnline' for PayBill
        'Amount' => $stkAmount,
        'PartyA' => $formattedPhone,
        'PartyB' => $mpesaShortCode,
        'PhoneNumber' => $formattedPhone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => 'LoanApp', // Keep this short and simple
        'TransactionDesc' => "Service fee for Ksh. $loan_amount loan"
    ];

    $stkData = json_encode($stkPayload);

    // Send the cURL request
    $ch = curl_init($stkPushUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $stkData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $message = urlencode("Error: " . curl_error($ch));
        header("Location: status.php?status=error&message=$message");
        exit;
    }
    curl_close($ch);

    $stkResponse = json_decode($response);

    // --- 6. HANDLE SAFARICOM'S RESPONSE ---
    if (isset($stkResponse->ResponseCode) && $stkResponse->ResponseCode == "0") {
        // STK Push was successfully *initiated*
        // Safaricom will send the actual payment result to your $callbackUrl
        $message = urlencode($stkResponse->CustomerMessage);
        header("Location: status.php?status=success&message=$message");
        exit;
    } else {
        // STK Push *initiation* failed
        $errorMessage = $stkResponse->errorMessage ?? $stkResponse->ResponseDescription ?? 'An unknown error occurred during STK push initiation.';
        $message = urlencode("Error: " . $errorMessage);
        header("Location: status.php?status=error&message=$message");
        exit;
    }

} else {
    // If data is missing (e.g., direct access to this file), redirect
    header("Location: index.php");
    exit;
}
?>

