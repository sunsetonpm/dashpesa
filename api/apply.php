<?php
// Start the session to get user data
include 'header.php';

// Security check: If user data isn't in the session, redirect to the eligibility page
if (!isset($_SESSION['full_name']) || !isset($_SESSION['phone_number'])) {
    header("Location: eligibility.php?error=Please+fill+out+your+details+first.");
    exit;
}

// Get the user's name from the session to personalize the page
$full_name = htmlspecialchars($_SESSION['full_name']);
$first_name = explode(' ', $full_name)[0]; // Get the first name
?>

<main class="py-20 bg-gray-50">
    <div class="container mx-auto px-6">
        
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-4">
            Welcome, <?php echo $first_name; ?>!
        </h2>
        <p class="text-center text-gray-600 mb-10 text-lg">
            You are eligible for the following loan options. Please select one to proceed.
        </p>

        <!-- Loan Options Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            
            <!-- Option 1 -->
            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200 text-center flex flex-col justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-blue-600 mb-3">Ksh. 3,400</div>
                    <p class="text-gray-500 mb-4 text-sm">Service Fee: <span class="font-bold text-gray-700">Ksh. 70</span></p>
                </div>
                <button onclick="showPaymentPopup(3400, 70, '<?php echo htmlspecialchars($_SESSION['phone_number']); ?>')"
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 shadow-lg transition duration-300">
                    Apply Now
                </button>
            </div>

            <!-- Option 2 (Featured) -->
            <div class="bg-blue-700 text-white p-8 rounded-2xl shadow-2xl border border-blue-800 text-center flex flex-col justify-between transform scale-105">
                <span class="absolute top-0 right-4 -mt-3 bg-yellow-400 text-blue-900 text-xs font-bold px-3 py-1 rounded-full">POPULAR</span>
                <div>
                    <div class="text-4xl font-extrabold text-white mb-3">Ksh. 4,750</div>
                    <p class="text-blue-100 mb-4 text-sm">Service Fee: <span class="font-bold text-white">Ksh. 95</span></p>
                </div>
                <button onclick="showPaymentPopup(4750, 95, '<?php echo htmlspecialchars($_SESSION['phone_number']); ?>')"
                        class="w-full bg-white text-blue-700 px-6 py-3 rounded-lg font-bold hover:bg-gray-100 shadow-lg transition duration-300">
                    Apply Now
                </button>
            </div>

            <!-- Option 3 -->
            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200 text-center flex flex-col justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-blue-600 mb-3">Ksh. 5,300</div>
                    <p class="text-gray-500 mb-4 text-sm">Service Fee: <span class="font-bold text-gray-700">Ksh. 100</span></p>
                </div>
                <button onclick="showPaymentPopup(5300, 100, '<?php echo htmlspecialchars($_SESSION['phone_number']); ?>')"
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 shadow-lg transition duration-300">
                    Apply Now
                </button>
            </div>
        </div>
    </div>
</main>

<!-- Payment Popup Modal (Hidden by default) -->
<div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-6 hidden z-50">
    <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full relative animate-zoom-in">
        
        <!-- Close Button -->
        <button onclick="closePaymentPopup()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-bold text-gray-800 text-center mb-4">Confirm Payment</h3>
        <p class="text-center text-gray-600 mb-6">
            To receive your loan of <span id="popup-loan-amount-text" class="font-bold text-gray-900"></span>, please pay the 
            <span id="popup-fee-text" class="font-bold text-gray-900"></span> service fee.
        </p>
        <p class="text-center text-gray-600 mb-8">
            A payment request will be sent to your M-Pesa number:
            <br>
            <strong id="popup-phone-text" class="text-lg text-gray-900"></strong>
        </p>

        <!-- Payment Form -->
        <form id="payment-form" action="process_payment.php" method="POST">
            <!-- Hidden fields to send data to the server -->
            <input type="hidden" name="loan_amount" id="popup-loan-amount-input">
            <input type="hidden" name="service_fee" id="popup-service-fee-input">
            
            <button type="submit" id="stk-push-button"
                    class="w-full bg-green-600 text-white px-8 py-3 rounded-lg font-bold text-lg hover:bg-green-700 shadow-lg transition duration-300">
                Pay with M-Pesa
            </button>
            <p id="stk-loading-text" class="text-center text-gray-600 mt-4 hidden">
                Sending request to your phone...
            </p>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('payment-modal');
    const loanAmountText = document.getElementById('popup-loan-amount-text');
    const feeText = document.getElementById('popup-fee-text');
    const phoneText = document.getElementById('popup-phone-text');
    const loanAmountInput = document.getElementById('popup-loan-amount-input');
    const serviceFeeInput = document.getElementById('popup-service-fee-input');
    const payButton = document.getElementById('stk-push-button');
    const loadingText = document.getElementById('stk-loading-text');

    function showPaymentPopup(amount, fee, phone) {
        // Update text
        loanAmountText.textContent = 'Ksh. ' + amount.toLocaleString();
        feeText.textContent = 'Ksh. ' + fee.toLocaleString();
        phoneText.textContent = phone;

        // Update hidden form inputs
        loanAmountInput.value = amount;
        serviceFeeInput.value = fee;

        // Show the modal
        modal.classList.remove('hidden');
    }

    function closePaymentPopup() {
        // Hide the modal
        modal.classList.add('hidden');
        // Reset button state
        payButton.disabled = false;
        payButton.textContent = 'Pay with M-Pesa';
        loadingText.classList.add('hidden');
    }

    // Show loading state on form submit
    document.getElementById('payment-form').addEventListener('submit', function() {
        payButton.disabled = true;
        payButton.textContent = 'Sending...';
        loadingText.classList.remove('hidden');
    });

</script>

<?php include 'footer.php'; ?>

