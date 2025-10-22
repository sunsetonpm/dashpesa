<?php
// Start a session to store user data across pages
session_start();

// 1. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. Retrieve and sanitize form data
    $full_name = trim(htmlspecialchars($_POST['full_name']));
    $phone_number = trim(htmlspecialchars($_POST['phone_number']));
    $id_number = trim(htmlspecialchars($_POST['id_number']));
    $loan_type = trim(htmlspecialchars($_POST['loan_type']));

    // 3. Server-side validation
    $errors = [];

    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }

    // Validate Kenyan phone number (e.g., 07... or 01... with 10 digits total)
    if (!preg_match("/^(07|01)\d{8}$/", $phone_number)) {
        $errors[] = "Please enter a valid M-Pesa phone number (e.g., 0712345678).";
    }

    // Validate ID number (basic check for 7 or 8 digits)
    if (!preg_match("/^\d{7,8}$/", $id_number)) {
        $errors[] = "Please enter a valid National ID number.";
    }

    if (empty($loan_type)) {
        $errors[] = "Please select a Loan Type.";
    }

    // 4. Process the data
    if (count($errors) > 0) {
        // If there are errors, redirect back to the eligibility form with the first error message
        $error_message = urlencode($errors[0]);
        header("Location: eligibility.php?error=$error_message");
        exit;

    } else {
        // If data is valid, store it in the session
        $_SESSION['full_name'] = $full_name;
        $_SESSION['phone_number'] = $phone_number;
        $_SESSION['id_number'] = $id_number;
        $_SESSION['loan_type'] = $loan_type;

        // Redirect to the loan options page
        header("Location: apply.php");
        exit;
    }

} else {
    // If not a POST request, redirect to the homepage
    header("Location: index.php");
    exit;
}
?>