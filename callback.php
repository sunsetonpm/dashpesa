<?php

// --- 1. SET UP LOGGING ---
// This is critical for debugging. Safaricom sends data here without a browser.
$logFile = "callback_log.txt";
// Get the raw POST data from Safaricom
$stkCallbackResponse = file_get_contents('php://input');

// Write the raw response to the log file
$log = fopen($logFile, "a");
fwrite($log, $stkCallbackResponse . "\n");
fclose($log);


// --- 2. DECODE THE RESPONSE ---
$data = json_decode($stkCallbackResponse);

// Check if JSON decoding was successful
if ($data === null) {
    // Log invalid JSON
    $log = fopen($logFile, "a");
    fwrite($log, "Invalid JSON received\n");
    fclose($log);
    
    // Respond to Safaricom
    header('Content-Type: application/json');
    echo '{"ResultCode": 1, "ResultDesc": "Failed to parse JSON"}';
    exit;
}


// --- 3. PROCESS THE CALLBACK ---
// This is the main part of the response
$resultCode = $data->Body->stkCallback->ResultCode;
$checkoutRequestID = $data->Body->stkCallback->CheckoutRequestID;
$resultDesc = $data->Body->stkCallback->ResultDesc;

if ($resultCode == 0) {
    // --- PAYMENT SUCCESSFUL ---
    // 1. Get transaction details
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

    // 2. Log the successful payment
    $logMessage = "SUCCESS: CheckoutID: $checkoutRequestID, Receipt: $mpesaReceiptNumber, Phone: $phoneNumber, Amount: $amount, Date: $transactionDate\n";
    $log = fopen($logFile, "a");
    fwrite($log, $logMessage);
    fclose($log);

    //
    // --- 4. !! CRITICAL: UPDATE YOUR DATABASE !! ---
    //
    // This is where you connect to your database and:
    // 1. Find the application associated with $checkoutRequestID or $phoneNumber.
    // 2. Mark the application as "Paid".
    // 3. Store the $mpesaReceiptNumber and $amount.
    // 4. Trigger the next step (e.g., approve the loan for disbursement).
    //
    /*
    try {
        // Example PDO Database Logic
        $db = new PDO("mysql:host=localhost;dbname=your_db_name", "your_username", "your_password");
        $stmt = $db->prepare("UPDATE loan_applications 
                             SET status = 'Paid', 
                                 mpesa_receipt = :receipt, 
                                 service_fee_paid = :amount 
                             WHERE checkout_id = :checkout_id"); // Or WHERE phone_number = :phone
        
        $stmt->execute([
            ':receipt' => $mpesaReceiptNumber,
            ':amount' => $amount,
            ':checkout_id' => $checkoutRequestID
            // ':phone' => $phoneNumber // Use phone if you didn't save CheckoutID
        ]);

    } catch (PDOException $e) {
        // Log database error
        $log = fopen($logFile, "a");
        fwrite($log, "DATABASE ERROR: " . $e->getMessage() . "\n");
        fclose($log);
    }
    */

} else {
    // --- PAYMENT FAILED OR CANCELED ---
    // (e.g., $resultCode == 1032 means user canceled)
    
    // 1. Log the failure
    $logMessage = "FAILED: CheckoutID: $checkoutRequestID, ResultCode: $resultCode, Desc: $resultDesc\n";
    $log = fopen($logFile, "a");
    fwrite($log, $logMessage);
    fclose($log);

    //
    // --- 5. UPDATE YOUR DATABASE (Optional) ---
    //
    // You might want to update your database to show the payment failed.
    /*
    $db = new PDO("mysql:host=localhost;dbname=your_db_name", "your_username", "your_password");
    $stmt = $db->prepare("UPDATE loan_applications SET status = 'Payment Failed' WHERE checkout_id = :checkout_id");
    $stmt->execute([':checkout_id' => $checkoutRequestID]);
    */
}


// --- 6. SEND ACKNOWLEDGEMENT TO SAFARICOM ---
// You MUST send this response, otherwise Safaricom will keep re-sending the callback.
header('Content-Type: application/json');
echo '{"ResultCode": 0, "ResultDesc": "Accepted"}';

exit;
?>
