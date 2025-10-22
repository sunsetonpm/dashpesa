<?php 
include 'header.php'; 

// Check for error messages in the URL
$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
?>

<main class="py-20 bg-blue-50">
    <div class="container mx-auto px-6">
        <div class="max-w-xl mx-auto bg-white p-8 md:p-12 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-4">
                Check Your Eligibility
            </h2>
            <p class="text-center text-gray-600 mb-8">
                Fill in the form below. It only takes a minute.
            </p>

            <!-- Display Error Message if it exists -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                    <strong class="font-bold">Oops!</strong>
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- The form posts to the processing script -->
            <form action="process_eligibility.php" method="POST" class="space-y-6">
                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Jane Doe">
                </div>
                
                <!-- Phone Number -->
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">M-Pesa Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., 0712345678">
                </div>

                <!-- ID Number -->
                <div>
                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-1">National ID Number</label>
                    <input type="text" id="id_number" name="id_number" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., 12345678">
                </div>

                <!-- Loan Type -->
                <div>
                    <label for="loan_type" class="block text-sm font-medium text-gray-700 mb-1">Loan Type</label>
                    <select id="loan_type" name="loan_type" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="" disabled selected>– Select Loan Type –</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Education Loan">Education Loan</option>
                        <option value="Medical Loan">Medical Loan</option>
                        <option value="Car Loan">Car Loan</option>
                        <option value="Emergency Loan">Emergency Loan</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit"
                            class="w-full bg-blue-600 text-white px-8 py-3 rounded-lg font-bold text-lg hover:bg-blue-700 shadow-lg transition duration-300 transform hover:scale-105">
                        Check Eligibility
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
