<?php
// --- 0. START SESSION & LOAD LIBS ---
// Session must be started on every request
session_start();

// Load Composer's autoloader for .env files
// require __DIR__ . '/vendor/autoload.php';

// // Load environment variables from .env
// try {
//     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
//     $dotenv->load();
// } catch (Exception $e) {
//     // Handle error if .env file is missing
//     die("Error: .env file not found. Please create one from example.env.");
// }

// Get the page parameter, default to 'home'
$page = $_GET['page'] ?? 'home';

// --- 1. SPECIAL CASE: SAFARICOM CALLBACK ---
// The callback page must not output ANY HTML.
// We check for it here, run its logic, and then exit immediately.
if ($page == 'callback') {

    // --- START CALLBACK LOGIC ---
    $logFile = "callback_log.txt";
    $stkCallbackResponse = file_get_contents('php://input');

    // Log the raw response
    $log = fopen($logFile, "a");
    fwrite($log, $stkCallbackResponse . "\n");
    fclose($log);

    $data = json_decode($stkCallbackResponse);

    if ($data === null) {
        $log = fopen($logFile, "a");
        fwrite($log, "Invalid JSON received\n");
        fclose($log);

        header('Content-Type: application/json');
        echo '{"ResultCode": 1, "ResultDesc": "Failed to parse JSON"}';
        exit; // Stop script
    }

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
            if ($item->Name == 'Amount')
                $amount = $item->Value;
            elseif ($item->Name == 'MpesaReceiptNumber')
                $mpesaReceiptNumber = $item->Value;
            elseif ($item->Name == 'TransactionDate')
                $transactionDate = $item->Value;
            elseif ($item->Name == 'PhoneNumber')
                $phoneNumber = $item->Value;
        }

        $logMessage = "SUCCESS: CheckoutID: $checkoutRequestID, Receipt: $mpesaReceiptNumber, Phone: $phoneNumber, Amount: $amount, Date: $transactionDate\n";
        $log = fopen($logFile, "a");
        fwrite($log, $logMessage);
        fclose($log);

        /*
        // --- !! CRITICAL: UPDATE YOUR DATABASE !! ---
        $db_host = getenv('DB_HOST');
        $db_name = getenv('DB_NAME');
        $db_user = getenv('DB_USER');
        $db_pass = getenv('DB_PASS');
        try {
            $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
            $stmt = $db->prepare("UPDATE loan_applications SET status = 'Paid', mpesa_receipt = :r, service_fee_paid = :a WHERE checkout_id = :cid");
            $stmt->execute([':r' => $mpesaReceiptNumber, ':a' => $amount, ':cid' => $checkoutRequestID]);
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

    // --- SEND ACKNOWLEDGEMENT TO SAFARICOM ---
    header('Content-Type: application/json');
    echo '{"ResultCode": 0, "ResultDesc": "Accepted"}';

    exit; // Stop script
    // --- END CALLBACK LOGIC ---
}


// --- 2. SPECIAL CASE: PROCESS ELIGIBILITY FORM ---
// This processes a POST request and redirects. It sends no HTML.
if ($page == 'process_eligibility' && $_SERVER["REQUEST_METHOD"] == "POST") {

    // --- START ELIGIBILITY LOGIC ---
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $id_number = trim(filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_NUMBER_INT));
    $loan_type = trim(filter_input(INPUT_POST, 'loan_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    // Basic Validation
    $errors = [];
    if (empty($full_name) || strlen($full_name) < 3)
        $errors[] = "A valid full name is required.";
    if (empty($id_number) || !is_numeric($id_number) || strlen($id_number) < 7)
        $errors[] = "A valid ID number is required.";
    if (empty($loan_type))
        $errors[] = "Please select a loan type.";

    // Validate Kenyan Phone Number (basic)
    if (!preg_match('/^(07|01)\d{8}$/', $phone_number)) {
        $errors[] = "Please enter a valid phone number (e.g., 07... or 01...).";
    }

    if (!empty($errors)) {
        // Save errors to session and redirect back
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: index.php?page=eligibility");
        exit;
    }

    // Save user details to session to use on the next page
    $_SESSION['full_name'] = $full_name;
    $_SESSION['phone_number'] = $phone_number;
    $_SESSION['id_number'] = $id_number;
    $_SESSION['loan_type'] = $loan_type;

    /*
    // --- TODO: SAVE TO DATABASE ---
    // This is where you would save the initial application
    try {
        // $db = new PDO(...)
        // $stmt = $db->prepare("INSERT INTO loan_applications (name, phone, id_number, loan_type) VALUES (?, ?, ?, ?)");
        // $stmt->execute([$full_name, $phone_number, $id_number, $loan_type]);
    } catch (PDOException $e) {
        // Handle database error
        $_SESSION['errors'] = ["A database error occurred. Please try again later."];
        header("Location: index.php?page=eligibility");
        exit;
    }
    */

    // Clear any old errors and redirect to the loan options page
    unset($_SESSION['errors']);
    unset($_SESSION['form_data']);
    header("Location: index.php?page=apply");
    exit;
    // --- END ELIGIBILITY LOGIC ---
}


// --- 3. SPECIAL CASE: PROCESS PAYMENT FORM (STK PUSH) ---
// This processes a POST request and redirects. It sends no HTML.
if ($page == 'process_payment' && $_SERVER["REQUEST_METHOD"] == "POST") {

    // --- START PAYMENT LOGIC ---
    // // Get keys from environment
    // $consumerKey = getenv('MPESA_CONSUMER_KEY');
    // $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
    // $mpesaShortCode = getenv('MPESA_SHORTCODE');
    // $mpesaPasskey = getenv('MPESA_PASSKEY');
    // $callbackUrl = getenv('MPESA_CALLBACK_URL');
    // $environment = getenv('MPESA_ENVIRONMENT');

    $consumerKey = "qN3VPTVG7wd3hiWrntEU51GnGhbAtQlQShhoDmNOxilFyMIE";
    $consumerSecret = "sMGE5qDKAUtSbYj4oEK6hG2KZiNiYG3m4n5wYqtGaGtdsAoDbgz8kxTWppxy5gBj";
    $mpesaShortCode = "YOUR_PAYBILL_OR_TILL";
    $mpesaPasskey = "YOUR_MPESA_PASSKEY";
    $callbackUrl = "https://dashpesa.vercel.app/callback.php";
    $environment = "live";

    // Set API URLs
    $authUrl = ($environment == 'live') ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
    $stkPushUrl = ($environment == 'live') ? "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest" : "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

    // Security check
    if (!isset($_SESSION['phone_number']) || !isset($_POST['service_fee'])) {
        header("Location: index.php?page=eligibility");
        exit;
    }

    $phone_number = $_SESSION['phone_number']; // User's phone
    $service_fee = $_POST['service_fee'];     // The fee to charge
    $loan_amount = $_POST['loan_amount'];   // The loan they applied for

    // Reformat phone
    $formattedPhone = (substr($phone_number, 0, 1) == "0") ? "254" . substr($phone_number, 1) : $phone_number;

    // Use '1' for sandbox testing, real amount for live
    $stkAmount = ($environment == 'live') ? $service_fee : 1;

    // Get Access Token
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $message = urlencode("Error: " . curl_error($ch));
        header("Location: index.php?page=status&status=error&message=$message");
        exit;
    }
    curl_close($ch);
    $authData = json_decode($response);
    if (!isset($authData->access_token)) {
        $message = urlencode("Error: Unable to get API access token. Check Keys.");
        header("Location: index.php?page=status&status=error&message=$message");
        exit;
    }
    $accessToken = $authData->access_token;

    // Initiate STK Push
    $timestamp = date('YmdHis');
    $password = base64_encode($mpesaShortCode . $mpesaPasskey . $timestamp);

    $stkPayload = [
        'BusinessShortCode' => $mpesaShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $stkAmount,
        'PartyA' => $formattedPhone,
        'PartyB' => $mpesaShortCode,
        'PhoneNumber' => $formattedPhone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => 'DashPesa',
        'TransactionDesc' => "Service fee for Ksh. $loan_amount loan"
    ];

    // Store CheckoutRequestID in session to match callback
    // $_SESSION['checkout_id'] = $stkPayload['CheckoutRequestID']; // Note: Safaricom returns this ID in the *response*

    $stkData = json_encode($stkPayload);

    $ch = curl_init($stkPushUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $stkData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $message = urlencode("Error: " . curl_error($ch));
        header("Location: index.php?page=status&status=error&message=$message");
        exit;
    }
    curl_close($ch);

    $stkResponse = json_decode($response);

    if (isset($stkResponse->ResponseCode) && $stkResponse->ResponseCode == "0") {
        // Save CheckoutRequestID to match callback with user
        $_SESSION['CheckoutRequestID'] = $stkResponse->CheckoutRequestID;

        /*
        // --- TODO: SAVE CHECKOUT ID TO DATABASE ---
        // $db = new PDO(...)
        // $stmt = $db->prepare("UPDATE loan_applications SET checkout_id = ? WHERE phone_number = ? AND status = 'Pending'");
        // $stmt->execute([$stkResponse->CheckoutRequestID, $phone_number]);
        */

        $message = urlencode($stkResponse->CustomerMessage);
        header("Location: index.php?page=status&status=success&message=$message");
        exit;
    } else {
        $errorMessage = $stkResponse->errorMessage ?? $stkResponse->ResponseDescription ?? 'An unknown error occurred.';
        $message = urlencode("Error: " . $errorMessage);
        header("Location: index.php?page=status&status=error&message=$message");
        exit;
    }
    // --- END PAYMENT LOGIC ---
}


// --- 4. START HTML OUTPUT ---
// If the script hasn't exited by now, it means we are displaying a page.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- === Primary SEO Meta Tags === -->
    <title>DashPesa - Fast, Simple, Reliable Loans</title>
    <meta name="description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork. Apply now with DashPesa.">
    <meta name="keywords"
        content="fast loans, mobile loans, kenya, mpesa loans, instant cash, no crb check, dashpesa, pesa chapchap">
    <meta name="author" content="DashPesa">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="httpsa://www.yourdomain.com/"> <!-- TODO: Replace with your live domain -->

    <!-- === Open Graph / Facebook Meta Tags === -->
    <meta property="og:title" content="DashPesa - Fast, Simple, Reliable Loans">
    <meta property="og:description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="httpsa://www.yourdomain.com/"> <!-- TODO: Replace with your live domain -->
    <meta property="og:image" content="httpsa://www.yourdomain.com/social-image.jpg">
    <!-- TODO: Add a social image link -->

    <!-- === Twitter Card Meta Tags === -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="DashPesa - Fast, Simple, Reliable Loans">
    <meta name="twitter:description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork.">
    <meta name="twitter:image" content="httpsa://www.yourdomain.com/social-image.jpg">
    <!-- TODO: Use the same social image link -->

    <!-- === Stylesheets & Fonts === -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- === Google Tag Snippet === -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YOUR_TAG_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-YOUR_TAG_ID');
    </script>
    <!-- End Google Tag Snippet -->

    <!-- === INLINED CSS (from style.css) === -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .hero-bg {
            background-color: #F0F5FF;
            background-image: radial-gradient(#D6E3FF 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .feature-icon {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        }

        /* Payment Modal Styling */
        .modal {
            display: none;
            /* Hidden by default */
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            position: relative;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 450px;
            animation: slideIn 0.3s ease-out;
        }

        .modal-body {
            line-height: 1.6;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid #FFF;
            border-bottom-color: #4F46E5;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }

        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Header Navigation -->
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <!-- Logo -->
            <a href="index.php" class="text-3xl font-extrabold text-blue-700">
                DashPesa
            </a>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex space-x-6 items-center">
                <a href="index.php?page=home" class="text-gray-600 hover:text-blue-700 font-medium">Home</a>
                <a href="index.php?page=home#features"
                    class="text-gray-600 hover:text-blue-700 font-medium">Features</a>
                <a href="index.php?page=eligibility"
                    class="bg-blue-600 text-white px-5 py-2 rounded-full font-medium hover:bg-blue-700 transition duration-300">
                    Apply Now
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <a href="index.php?page=eligibility"
                    class="bg-blue-600 text-white px-5 py-2 rounded-full font-medium hover:bg-blue-700 transition duration-300">
                    Apply Now
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main>
        <?php
        // --- 5. PAGE ROUTER (SWITCH STATEMENT) ---
        // This switch decides which page content to show.
        
        switch ($page):

            // --- CASE: HOME PAGE ---
            case 'home':
                ?>
                <!-- Hero Section -->
                <section class="hero-bg py-20 md:py-32">
                    <div class="container mx-auto px-6 text-center">
                        <h1 class="text-4xl md:text-6xl font-extrabold text-gray-900 mb-6 leading-tight">
                            Fast, Simple, Reliable Loans
                        </h1>
                        <p class="text-lg md:text-xl text-gray-700 mb-10 max-w-2xl mx-auto">
                            Get the cash you need, right when you need it. Fast approval, no paperwork, no CRB check. Straight
                            to your M-Pesa.
                        </p>
                        <a href="index.php?page=eligibility"
                            class="bg-blue-600 text-white px-10 py-4 rounded-full font-semibold text-lg hover:bg-blue-700 transition duration-300 shadow-lg">
                            Apply for Your Loan Now
                        </a>
                    </div>
                </section>

                <!-- How It Works Section -->
                <section id="features" class="py-20 bg-white">
                    <div class="container mx-auto px-6">
                        <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">How It Works in 3 Easy Steps</h2>
                        <div class="flex flex-wrap -mx-4">
                            <!-- Step 1 -->
                            <div class="w-full md:w-1/3 px-4 mb-8">
                                <div class="bg-gray-50 p-8 rounded-2xl shadow-lg h-full">
                                    <div
                                        class="feature-icon text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 shadow-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">1. Check Eligibility</h3>
                                    <p class="text-gray-600">Fill in our simple 30-second form with your Name, Phone, and ID
                                        number to see the loan amount you qualify for.</p>
                                </div>
                            </div>
                            <!-- Step 2 -->
                            <div class="w-full md:w-1/3 px-4 mb-8">
                                <div class="bg-gray-50 p-8 rounded-2xl shadow-lg h-full">
                                    <div
                                        class="feature-icon text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 shadow-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2-1.343-2-3-2m0 8c-1.11 0-2.08-.402-2.599-1M12 16v1m0-1v-8" />
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">2. Pay Service Fee</h3>
                                    <p class="text-gray-600">Choose your desired loan amount and pay the small, one-time service
                                        fee securely via our M-Pesa STK push.</p>
                                </div>
                            </div>
                            <!-- Step 3 -->
                            <div class="w-full md:w-1/3 px-4 mb-8">
                                <div class="bg-gray-50 p-8 rounded-2xl shadow-lg h-full">
                                    <div
                                        class="feature-icon text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 shadow-xl">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">3. Get Your Cash</h3>
                                    <p class="text-gray-600">Once the fee is confirmed, your loan is instantly processed and
                                        disbursed directly to your M-Pesa wallet.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Call to Action Section -->
                <section class="bg-blue-700 text-white py-20">
                    <div class="container mx-auto px-6 text-center">
                        <h2 class="text-3xl md:text-4xl font-bold mb-6">Get Started with DashPesa Today</h2>
                        <p class="text-lg text-blue-100 mb-10 max-w-xl mx-auto">Don't wait. Get the financial boost you need in
                            minutes. Safe, secure, and reliable.</p>
                        <a href="index.php?page=eligibility"
                            class="bg-white text-blue-700 px-10 py-4 rounded-full font-semibold text-lg hover:bg-gray-100 transition duration-300 shadow-lg">
                            Check Your Eligibility
                        </a>
                    </div>
                </section>

                <?php
                break; // End 'home' page
        
            // --- CASE: ELIGIBILITY PAGE ---
            case 'eligibility':
                // Retrieve errors and form data from session if they exist
                $errors = $_SESSION['errors'] ?? [];
                $formData = $_SESSION['form_data'] ?? [];

                // Clear them so they don't show again
                unset($_SESSION['errors']);
                unset($_SESSION['form_data']);
                ?>

                <section class="py-16 md:py-24 bg-gray-50">
                    <div class="container mx-auto px-6">
                        <div class="max-w-xl mx-auto bg-white p-8 md:p-12 rounded-2xl shadow-xl">
                            <h2 class="text-3xl font-bold text-center text-gray-800 mb-2">Check Your Loan Eligibility</h2>
                            <p class="text-center text-gray-600 mb-8">Fill out the form to see what you qualify for.</p>

                            <?php if (!empty($errors)): ?>
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                                    <strong class="font-bold">Oops!</strong>
                                    <ul class="mt-2 list-disc list-inside">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form action="index.php?page=process_eligibility" method="POST" class="space-y-6">
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full
                                        Name</label>
                                    <input type="text" name="full_name" id="full_name"
                                        value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number
                                        (Safaricom)</label>
                                    <input type="tel" name="phone_number" id="phone_number"
                                        value="<?php echo htmlspecialchars($formData['phone_number'] ?? ''); ?>"
                                        placeholder="07... or 01..." required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-1">National ID
                                        Number</label>
                                    <input type="number" name="id_number" id="id_number"
                                        value="<?php echo htmlspecialchars($formData['id_number'] ?? ''); ?>" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="loan_type" class="block text-sm font-medium text-gray-700 mb-1">Loan
                                        Type</label>
                                    <select name="loan_type" id="loan_type" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                                        <option value="">– Select Loan Type –</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Business Loan') ? 'selected' : ''; ?>>Business Loan</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Personal Loan') ? 'selected' : ''; ?>>Personal Loan</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Education Loan') ? 'selected' : ''; ?>>Education Loan</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Medical Loan') ? 'selected' : ''; ?>>Medical Loan</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Car Loan') ? 'selected' : ''; ?>>Car Loan</option>
                                        <option <?php echo (isset($formData['loan_type']) && $formData['loan_type'] == 'Emergency Loan') ? 'selected' : ''; ?>>Emergency Loan</option>
                                    </select>
                                </div>

                                <div>
                                    <button type="submit"
                                        class="w-full bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-blue-700 transition duration-300 shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Check Eligibility
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <?php
                break; // End 'eligibility' page
        
            // --- CASE: APPLY (LOAN OPTIONS) PAGE ---
            case 'apply':
                // Security check: If user details aren't in session, redirect to eligibility
                if (!isset($_SESSION['full_name']) || !isset($_SESSION['phone_number'])) {
                    header("Location: index.php?page=eligibility");
                    exit;
                }
                $full_name = htmlspecialchars($_SESSION['full_name']);
                ?>

                <section class="py-16 md:py-24 bg-gray-50">
                    <div class="container mx-auto px-6">
                        <div class="max-w-2xl mx-auto text-center">
                            <h2 class="text-3xl font-bold text-gray-800 mb-3">Congratulations,
                                <?php echo explode(' ', $full_name)[0]; ?>!</h2>
                            <p class="text-lg text-gray-600 mb-8">You are eligible for the following loan options. Please select
                                one to proceed.</p>
                        </div>

                        <div class="max-w-2xl mx-auto space-y-6">

                            <!-- Loan Option 1 -->
                            <div
                                class="bg-white p-6 rounded-2xl shadow-xl border-2 border-transparent hover:border-blue-500 transition-all duration-300">
                                <div class="flex flex-col sm:flex-row justify-between items-center">
                                    <div class="mb-4 sm:mb-0">
                                        <h3 class="text-2xl font-bold text-gray-900">Loan Amount: Ksh. 3,400</h3>
                                        <p class="text-gray-600">Service Fee: <span class="font-semibold text-gray-800">Ksh.
                                                70</span></p>
                                    </div>
                                    <button onclick="showPaymentModal(3400, 70)"
                                        class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-full font-medium hover:bg-blue-700 transition duration-300 shadow-lg">
                                        Apply & Pay Fee
                                    </button>
                                </div>
                            </div>

                            <!-- Loan Option 2 -->
                            <div
                                class="bg-white p-6 rounded-2xl shadow-xl border-2 border-transparent hover:border-blue-500 transition-all duration-300">
                                <div class="flex flex-col sm:flex-row justify-between items-center">
                                    <div class="mb-4 sm:mb-0">
                                        <h3 class="text-2xl font-bold text-gray-900">Loan Amount: Ksh. 4,750</h3>
                                        <p class="text-gray-600">Service Fee: <span class="font-semibold text-gray-800">Ksh.
                                                95</span></p>
                                    </div>
                                    <button onclick="showPaymentModal(4750, 95)"
                                        class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-full font-medium hover:bg-blue-700 transition duration-300 shadow-lg">
                                        Apply & Pay Fee
                                    </button>
                                </div>
                            </div>

                            <!-- Loan Option 3 -->
                            <div
                                class="bg-white p-6 rounded-2xl shadow-xl border-2 border-transparent hover:border-blue-500 transition-all duration-300">
                                <div class="flex flex-col sm:flex-row justify-between items-center">
                                    <div class="mb-4 sm:mb-0">
                                        <h3 class="text-2xl font-bold text-gray-900">Loan Amount: Ksh. 5,300</h3>
                                        <p class="text-gray-600">Service Fee: <span class="font-semibold text-gray-800">Ksh.
                                                100</span></p>
                                    </div>
                                    <button onclick="showPaymentModal(5300, 100)"
                                        class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-full font-medium hover:bg-blue-700 transition duration-300 shadow-lg">
                                        Apply & Pay Fee
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- Payment Modal -->
                <div id="paymentModal" class="modal">
                    <div class="modal-content bg-white rounded-2xl shadow-2xl overflow-hidden">
                        <div class="p-6 md:p-8">
                            <h3 class="text-2xl font-bold text-center text-gray-900 mb-4" id="modalTitle">Confirm Payment</h3>

                            <!-- Initial Content -->
                            <div id="modal-initial-content" class="modal-body text-center">
                                <p class="text-gray-600 mb-6">You are about to pay a service fee of <strong id="modalServiceFee"
                                        class="text-gray-900"></strong> for a loan of <strong id="modalLoanAmount"
                                        class="text-gray-900"></strong>. A prompt will be sent to your phone <strong
                                        class="text-gray-900"><?php echo htmlspecialchars($_SESSION['phone_number']); ?></strong>.
                                </p>

                                <form id="paymentForm" action="index.php?page=process_payment" method="POST">
                                    <input type="hidden" name="loan_amount" id="formLoanAmount">
                                    <input type="hidden" name="service_fee" id="formServiceFee">

                                    <button type="submit" id="confirmPaymentBtn"
                                        class="w-full bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold text-lg hover:bg-blue-700 transition duration-300 shadow-lg">
                                        Yes, Send STK Push
                                    </button>
                                    <button type="button" onclick="closeModal()"
                                        class="w-full text-gray-600 px-8 py-3 rounded-lg font-medium hover:bg-gray-100 transition duration-300 mt-3">
                                        Cancel
                                    </button>
                                </form>
                            </div>

                            <!-- Loading Spinner Content -->
                            <div id="modal-loading-content" class="modal-body text-center hidden">
                                <div class="flex justify-center items-center mb-6">
                                    <span class="loader"></span>
                                </div>
                                <p class="text-lg font-semibold text-gray-800 mb-2">Processing Request...</p>
                                <p class="text-gray-600">Please check your phone and enter your M-Pesa PIN to authorize the
                                    payment.</p>
                            </div>

                        </div>
                    </div>
                </div>

                <script>
                    const modal = document.getElementById('paymentModal');
                    const modalTitle = document.getElementById('modalTitle');
                    const modalLoanAmount = document.getElementById('modalLoanAmount');
                    const modalServiceFee = document.getElementById('modalServiceFee');
                    const formLoanAmount = document.getElementById('formLoanAmount');
                    const formServiceFee = document.getElementById('formServiceFee');
                    const initialContent = document.getElementById('modal-initial-content');
                    const loadingContent = document.getElementById('modal-loading-content');
                    const paymentForm = document.getElementById('paymentForm');

                    function showPaymentModal(loanAmount, serviceFee) {
                        // Set values
                        modalLoanAmount.innerText = 'Ksh. ' + loanAmount.toLocaleString();
                        modalServiceFee.innerText = 'Ksh. ' + serviceFee.toLocaleString();
                        formLoanAmount.value = loanAmount;
                        formServiceFee.value = serviceFee;

                        // Show initial content
                        modalTitle.innerText = "Confirm Payment";
                        initialContent.classList.remove('hidden');
                        loadingContent.classList.add('hidden');

                        // Show modal
                        modal.style.display = 'block';
                    }

                    function closeModal() {
                        modal.style.display = 'none';
                    }

                    // Show loading spinner on form submit
                    paymentForm.addEventListener('submit', function () {
                        modalTitle.innerText = "Waiting for Payment...";
                        initialContent.classList.add('hidden');
                        loadingContent.classList.remove('hidden');
                    });

                    // Close modal if user clicks outside of it
                    window.onclick = function (event) {
                        if (event.target == modal) {
                            closeModal();
                        }
                    }
                </script>

                <?php
                break; // End 'apply' page
        
            // --- CASE: STATUS PAGE ---
            case 'status':
                $status = $_GET['status'] ?? 'error';
                $message = htmlspecialchars(urldecode($_GET['message'] ?? 'An unknown error occurred.'));

                if ($status == 'success') {
                    $bgColor = 'bg-green-100';
                    $borderColor = 'border-green-500';
                    $textColor = 'text-green-800';
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                    $title = "Request Sent!";
                } else {
                    $bgColor = 'bg-red-100';
                    $borderColor = 'border-red-500';
                    $textColor = 'text-red-800';
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                    $title = "Error Occurred";
                }
                ?>

                <section class="py-16 md:py-24 bg-gray-50">
                    <div class="container mx-auto px-6">
                        <div class="max-w-lg mx-auto bg-white p-8 md:p-12 rounded-2xl shadow-xl text-center">
                            <div class="flex justify-center <?php echo $textColor; ?> mb-6">
                                <?php echo $icon; ?>
                            </div>
                            <h2 class="text-3xl font-bold <?php echo $textColor; ?> mb-4"><?php echo $title; ?></h2>
                            <p class="text-lg text-gray-600 mb-8"><?php echo $message; ?></p>

                            <?php if ($status == 'success'): ?>
                                <p class="text-gray-600 mb-8">Please check your phone to complete the payment. Your loan will be
                                    processed immediately after we confirm the service fee.</p>
                            <?php endif; ?>

                            <a href="index.php?page=home" class="text-blue-600 font-medium hover:underline">
                                &larr; Go back to Home
                            </a>
                        </div>
                    </div>
                </section>

                <?php
                break; // End 'status' page
        
            // --- CASE: DEFAULT (404) ---
            default:
                // Send 404 header
                header("HTTP/1.0 404 Not Found");
                ?>
                <section class="py-16 md:py-24 bg-gray-50">
                    <div class="container mx-auto px-6 text-center">
                        <h1 class="text-6xl font-extrabold text-blue-700 mb-4">404</h1>
                        <h2 class="text-3xl font-bold text-gray-800 mb-6">Page Not Found</h2>
                        <p class="text-lg text-gray-600 mb-10">Sorry, the page you are looking for does not exist.</p>
                        <a href="index.php?page=home"
                            class="bg-blue-600 text-white px-10 py-4 rounded-full font-semibold text-lg hover:bg-blue-700 transition duration-300 shadow-lg">
                            Go to Homepage
                        </a>
                    </div>
                </section>
                <?php
                break; // End 'default' page
        
        endswitch;
        ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 py-12">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-6 md:mb-0">
                    <a href="index.php" class="text-3xl font-extrabold text-white">
                        DashPesa
                    </a>
                    <p class="mt-2 text-gray-500">Fast, Simple, Reliable Loans.</p>
                </div>
                <div class="flex space-x-6">
                    <a href="index.php?page=home" class="hover:text-white">Home</a>
                    <a href="index.php?page=home#features" class="hover:text-white">Features</a>
                    <a href="index.php?page=eligibility" class="hover:text-white">Apply Now</a>
                </div>
            </div>
            <hr class="border-gray-700 my-8">
            <div class="text-center text-gray-500">
                &copy; <?php echo date('Y'); ?> DashPesa. All rights reserved.
                <p class="text-sm mt-2">Disclaimer: This is a sample application. Not a real financial service.</p>
            </div>
        </div>
    </footer>

</body>

</html>