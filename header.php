<?php
// Start session on every page that includes this header
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="./favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="./favicons/apple-touch-icon.png" />
    <link rel="manifest" href="./favicons/site.webmanifest" />

    <!-- === Primary SEO Meta Tags === -->
    <title>DashPesa - Fast, Simple, Reliable Loans</title>
    <meta name="description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork. Apply now with DashPesa.">
    <meta name="keywords"
        content="fast loans, mobile loans, kenya, mpesa loans, instant cash, no crb check, dashpesa, pesa chapchap">
    <meta name="author" content="DashPesa">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.yourdomain.com/"> <!-- TODO: Replace with your live domain -->

    <!-- === Open Graph / Facebook Meta Tags === -->
    <meta property="og:title" content="DashPesa - Fast, Simple, Reliable Loans">
    <meta property="og:description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.yourdomain.com/"> <!-- TODO: Replace with your live domain -->
    <meta property="og:image" content="https://www.yourdomain.com/social-image.jpg">
    <!-- TODO: Add a link to a social sharing image (e.g., 1200x630px) -->

    <!-- === Twitter Card Meta Tags === -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="DashPesa - Fast, Simple, Reliable Loans">
    <meta name="twitter:description"
        content="Get fast mobile loans up to Ksh. 10,000 sent directly to your M-Pesa in minutes. No CRB check, no paperwork.">
    <meta name="twitter:image" content="https://www.yourdomain.com/social-image.jpg">
    <!-- TODO: Use the same social image link -->

    <!-- === Stylesheets & Fonts === -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <!-- === Google Tag Snippet === -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YOUR_FIRST_TAG_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-YOUR_FIRST_TAG_ID');
        // gtag('config', 'AW-YOUR_SECOND_TAG_ID'); // You can add more tags here
    </script>
    <!-- End Google Tag Snippet -->

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
                <a href="index.php" class="text-gray-600 hover:text-blue-700 font-medium">Home</a>
                <a href="index.php#features" class="text-gray-600 hover:text-blue-700 font-medium">Features</a>
                <a href="eligibility.php"
                    class="bg-blue-600 text-white px-5 py-2 rounded-full font-medium hover:bg-blue-700 transition duration-300">
                    Apply Now
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <a href="eligibility.php"
                    class="bg-blue-600 text-white px-5 py-2 rounded-full font-medium hover:bg-blue-700 transition duration-300">
                    Apply Now
                </a>
            </div>
        </nav>
    </header>