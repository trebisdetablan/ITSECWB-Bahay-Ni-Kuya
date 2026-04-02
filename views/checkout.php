<?php
session_start();

// Database configuration
require_once('../includes/dbconfig.php');

// Check if user is logged in and is a customer
include('../assets/php/authorization.php');
customerAccess($conn, "/checkout");

include('../assets/php/validation.php');

$resource = "/checkout";
$reason = "";
$status = "Fail";

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle order placement
$orderSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!empty($_SESSION['cart'])) {
        
        // Get customer information from form
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation rules
        if (!preg_match("/^[A-Za-z\s'-]{2,100}$/", $fullname)) {
            $reason = "Invalid full name entered";
            logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);

            $error = "Invalid full name. Only letters, spaces, apostrophes, and hyphens are allowed (2-100 characters).";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address format.";
            logValidation($conn, $_SESSION['user_email'], $resource, $error, $status);

        } elseif (!preg_match("/^\+?\d{7,15}$/", $phone)) {
            $error = "Invalid phone number. Must contain 7-15 digits, optional '+' at start.";
            logValidation($conn, $_SESSION['user_email'], $resource, $error, $status);
        }

        // Proceed only if no validation errors
        if (!isset($error)) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update each property in the cart to "Sold"
                foreach (array_keys($_SESSION['cart']) as $propertyId) {
                    $stmt = $conn->prepare("UPDATE properties SET offer_type = 'Sold' WHERE property_id = ?");
                    $stmt->bind_param("i", $propertyId);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Clear the cart
                $_SESSION['cart'] = [];
                
                // Set success flag
                $orderSuccess = true;

                // Log the successful transaction
                require_once('../assets/php/logger.php');
                writeToLogFile("TRANSACTION", $_SESSION['user_email'], "/checkout", "Order placed successfully", "Success");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error processing your order. Please try again.";

                // Log the failed transaction
                require_once('../assets/php/logger.php');
                writeToLogFile("TRANSACTION", $_SESSION['user_email'], "/checkout", "Transaction failed: " . $e->getMessage(), "Fail");
            }
        }
    }
}


// Get cart items details from database
$cartItems = [];
$totalPrice = 0;
$subtotal = 0;
$taxAmount = 0;
$serviceFee = 0;

if (!empty($_SESSION['cart'])) {
    $cartIds = implode(',', array_keys($_SESSION['cart']));
    $result = $conn->query("SELECT * FROM properties WHERE property_id IN ($cartIds)");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cartItems[] = $row;
            $subtotal += $row['price'];
        }
    }
    
    // Calculate taxes and fees (example values)
    $taxRate = 0.12; // 12% tax
    $serviceFee = 500.00; // Fixed service fee
    $taxAmount = $subtotal * $taxRate;
    $totalPrice = $subtotal + $taxAmount + $serviceFee;
}

// Handle currency conversion
$exchangeRates = [
    'PHP' => 1.0,
    'USD' => 0.018, // 1 PHP = 0.018 USD (example rate)
    'EUR' => 0.016  // 1 PHP = 0.016 EUR (example rate)
];

$selectedCurrency = 'PHP';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currency'])) {
    $selectedCurrency = $_POST['currency'];
}

// Convert amounts to selected currency
function convertCurrency($amount, $fromCurrency, $toCurrency, $rates) {
    if ($fromCurrency === $toCurrency) return $amount;
    return $amount * $rates[$toCurrency];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bahay Ni Kuya - Checkout</title>
    <link rel="stylesheet" href="../assets/css/checkout.css">
    <style>
        /* Floating Place Order Button - Always Visible */
        .floating-place-order {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }

        .floating-place-order-btn {
            background-color: <?php echo !empty($cartItems) ? '#2ecc71' : '#95a5a6'; ?>;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            cursor: <?php echo !empty($cartItems) ? 'pointer' : 'not-allowed'; ?>;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .floating-place-order-btn:hover {
            background-color: <?php echo !empty($cartItems) ? '#27ae60' : '#95a5a6'; ?>;
            transform: <?php echo !empty($cartItems) ? 'translateY(-3px)' : 'none'; ?>;
            box-shadow: <?php echo !empty($cartItems) ? '0 6px 20px rgba(0,0,0,0.3)' : '0 4px 15px rgba(0,0,0,0.2)'; ?>;
        }

        .currency-selector {
            margin-bottom: 20px;
        }

        .currency-selector select {
            padding: 8px 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 16px;
        }

        .currency-symbol {
            font-weight: bold;
        }

        .empty-cart-message {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .confirmation-message {
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .confirmation-message h2 {
            color: #2ecc71;
            margin-bottom: 15px;
        }
        
        .confirmation-message .continue-shopping {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="BahayniKuya-header">
        <h1>Bahay Ni Kuya</h1>
        <p>Checkout</p>
    </header>

    <nav>
        <ul>
            <li><a class="navButton" href="property_listing.php">Browse Properties</a></li>
            <li><a class="navButton" href="shopping_cart.php">Your Cart</a></li>
            <li><a class="navButton" href="profile.php">View Profile</a></li>
            <li><a class="navButton" onclick="window.location.href='logout.php'">Sign Out</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($orderSuccess): ?>
            <div class="confirmation-message">
                <h2>Thank You for Your Order!</h2>
                <p>Your purchase has been confirmed and the properties have been marked as sold.</p>
                <p>We'll contact you shortly with more details about your purchase.</p>
                <a href="property_listing.php" class="continue-shopping">Browse More Properties</a>
            </div>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="checkout-grid">
                <div class="checkout-items">
                    <h2>Your Order Summary</h2>
                    
                    <div class="currency-selector">
                        <form method="post">
                            <label for="currency">Select Currency: </label>
                            <select name="currency" id="currency" onchange="this.form.submit()">
                                <option value="PHP" <?php echo $selectedCurrency === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (₱)</option>
                                <option value="USD" <?php echo $selectedCurrency === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                <option value="EUR" <?php echo $selectedCurrency === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                            </select>
                        </form>
                    </div>
                    
                    <?php if (!empty($cartItems)): ?>
                        <div class="cart-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['photo']); ?>"
                                    alt="<?php echo htmlspecialchars($item['property_name']); ?>"
                                    class="cart-item-image">
                                    <div class="cart-item-details">
                                        <h3 class="cart-item-title"><?php echo $item['property_name']; ?></h3>
                                        <p class="cart-item-location"><?php echo $item['address']; ?></p>
                                        <p class="cart-item-price">
                                            <span class="currency-symbol">
                                                <?php 
                                                    echo $selectedCurrency === 'PHP' ? '₱' : 
                                                         ($selectedCurrency === 'USD' ? '$' : '€');
                                                ?>
                                            </span>
                                            <?php echo number_format(convertCurrency($item['price'], 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-cart-message">
                            <h3>Your cart is empty</h3>
                            <p>There are no items in your cart to checkout.</p>
                            <a href="property_listing.php" class="continue-shopping">Browse Properties</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($cartItems)): ?>
                <div class="checkout-summary">
                    <h2>Order Details</h2>
                    
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>
                                <span class="currency-symbol">
                                    <?php 
                                        echo $selectedCurrency === 'PHP' ? '₱' : 
                                             ($selectedCurrency === 'USD' ? '$' : '€');
                                    ?>
                                </span>
                                <?php echo number_format(convertCurrency($subtotal, 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Tax (<?php echo isset($taxRate) ? ($taxRate * 100) : 0; ?>%):</span>
                            <span>
                                <span class="currency-symbol">
                                    <?php 
                                        echo $selectedCurrency === 'PHP' ? '₱' : 
                                             ($selectedCurrency === 'USD' ? '$' : '€');
                                    ?>
                                </span>
                                <?php echo number_format(convertCurrency($taxAmount, 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Service Fee:</span>
                            <span>
                                <span class="currency-symbol">
                                    <?php 
                                        echo $selectedCurrency === 'PHP' ? '₱' : 
                                             ($selectedCurrency === 'USD' ? '$' : '€');
                                    ?>
                                </span>
                                <?php echo number_format(convertCurrency($serviceFee, 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
                            </span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>
                                <span class="currency-symbol">
                                    <?php 
                                        echo $selectedCurrency === 'PHP' ? '₱' : 
                                             ($selectedCurrency === 'USD' ? '$' : '€');
                                    ?>
                                </span>
                                <?php echo number_format(convertCurrency($totalPrice, 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
                            </span>
                        </div>
                    </div>

                    <div class="payment-section">
                        <h3>Payment Method</h3>
                        <div class="payment-options">
                            <div class="payment-option">
                                <input type="radio" id="credit-card" name="payment" value="credit-card" checked>
                                <label for="credit-card">Credit/Debit Card</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="gcash" name="payment" value="gcash">
                                <label for="gcash">GCash</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="paypal" name="payment" value="paypal">
                                <label for="paypal">PayPal</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="bank-transfer" name="payment" value="bank-transfer">
                                <label for="bank-transfer">Bank Transfer</label>
                            </div>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h3>Customer Information</h3>
                        <form class="customer-form" method="post">
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input type="text" id="fullname" name="fullname" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <input type="hidden" name="place_order" value="1">
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($cartItems) && !$orderSuccess): ?>
    <div class="floating-place-order">
        <button class="floating-place-order-btn" onclick="placeOrder()">
            Place Order - 
            <span class="currency-symbol">
                <?php 
                    echo $selectedCurrency === 'PHP' ? '₱' : 
                         ($selectedCurrency === 'USD' ? '$' : '€');
                ?>
            </span>
            <?php echo number_format(convertCurrency($totalPrice, 'PHP', $selectedCurrency, $exchangeRates), 2); ?>
        </button>
    </div>
    <?php endif; ?>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Bahay Ni Kuya is a premier real estate service helping clients find their dream homes in the Philippines.</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="property_listing.php">Properties</a>
                <a href="cart.php">Your Cart</a>
                <a href="#">Services</a>
                <a href="#">Contact</a>
            </div>
            
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p>123 Real Estate Ave, Makati</p>
                <p>Phone: (02) 8123 4567</p>
                <p>Email: info@bahaynikuya.ph</p>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; 2023 Bahay Ni Kuya. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function placeOrder() {
            // Validate form
            const fullname = document.getElementById('fullname').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            if (!fullname || !email || !phone) {
                alert('Please fill in all required customer information.');
                return;
            }
            
            // Submit the form
            document.querySelector('.customer-form').submit();
        }
    </script>
</body>
</html>
