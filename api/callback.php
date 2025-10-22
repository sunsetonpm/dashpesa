<?php

// --- 1. LOAD ENVIRONMENT VARIABLES ---
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- 2. SET UP LOGGING ---
$logFile = "callback_log.txt";
$stkCallbackResponse = file_get_contents('php://input');

$log = fopen($logFile, "a");
fwrite($log, $stkCallbackResponse . "\n");
fclose($log);

// --- 3. DECODE THE RESPONSE ---
$data = json_decode($stkCallbackResponse);

if ($data === null) {
    $log = fopen($logFile, "a");
    fwrite($log, "Invalid JSON received\n");
    fclose($log);
    
    header('Content-Type: application/json');
    echo '{"ResultCode": 1, "ResultDesc": "Failed to parse JSON"}';
    exit;
}

// --- 4. PROCESS THE CALLBACK ---
$resultCode = $data->Body->stkCallback->ResultCode;
$checkoutRequestID = $data->Body->stkCallback->CheckoutRequestID;
$resultDesc = $data->Body->stkCallback->ResultDesc;

if ($resultCode == 0) {
    // --- PAYMENT SUCCESSFUL ---
    $callbackMetadata = $data->Body->stkCallback->CallbackMetadata;
    $amount = null;
    $mpesaReceiptNumber = null;
    $transactionDate = null;
    $phoneNumber = null;

    foreach ($callbackMetadata->Item as $item) {
        if ($item->Name == 'Amount') {
            $amount = $item->Value;
        } elseif ($item->Name == 'MpesaReceiptNumber') {
            $mpesaReceiptNumber = $item->Value;
        } elseif ($item->Name == 'TransactionDate') {
            $transactionDate = $item->Value;
        } elseif ($item->Name == 'PhoneNumber') {
            $phoneNumber = $item->Value;
        }
    }

    $logMessage = "SUCCESS: CheckoutID: $checkoutRequestID, Receipt: $mpesaReceiptNumber, Phone: $phoneNumber, Amount: $amount, Date: $transactionDate\n";
    $log = fopen($logFile, "a");
    fwrite($log, $logMessage);
    fclose($log);

    //
    // --- 5. !! CRITICAL: UPDATE YOUR DATABASE !! ---
    //
    // Now you can securely get your DB credentials from getenv()
    /*
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');

    try {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $stmt = $db->prepare("UPDATE loan_applications 
                             SET status = 'Paid', 
                                 mpesa_receipt = :receipt, 
                                 service_fee_paid = :amount 
                             WHERE checkout_id = :checkout_id");
        
        $stmt->execute([
            ':receipt' => $mpesaReceiptNumber,
            ':amount' => $amount,
            ':checkout_id' => $checkoutRequestID
        ]);

    } catch (PDOException $e) {
        $log = fopen($logFile, "a");
        fwrite($log, "DATABASE ERROR: " . $e->getMessage() . "\n");
        fclose($log);
    }
    */

} else {
    // --- PAYMENT FAILED OR CANCELED ---
    $logMessage = "FAILED: CheckoutID: $checkoutRequestID, ResultCode: $resultCode, Desc: $resultDesc\n";
    $log = fopen($logFile, "a");
    fwrite($log, $logMessage);
    fclose($log);
}

// --- 6. SEND ACKNOWLEDGEMENT TO SAFARICOM ---
header('Content-Type: application/json');
echo '{"ResultCode": 0, "ResultDesc": "Accepted"}';

exit;
?>

