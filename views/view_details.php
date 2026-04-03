<?php
// Start the session to store the cart data
session_start();

// Database configuration
require_once('../includes/dbconfig.php');

// Check if user is logged in and is a customer
include('../assets/php/authorization.php');
customerAccess($conn, "/view_details");

//For Timeout
require_once('../assets/php/authentication.php');

// Get the user's email from the session
$userEmail = $_SESSION['user_email'];

// Get the property ID
$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$property = null;

if ($property_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $property = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle adding items to the cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['property_id'])) {
    $propertyId = (int)$_POST['property_id'];

    // Check if the property ID is valid
    if ($propertyId > 0) {
        // Initialize cart if it doesn't exist
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Add property to the cart
        if (!isset($_SESSION['cart'][$propertyId])) {
            $_SESSION['cart'][$propertyId] = 1;  // Assuming the user is adding one item at a time
        } else {
            $_SESSION['cart'][$propertyId] += 1;  // Increment the quantity if the item is already in the cart
        }

        // Check if unpaid order exists for user
        $stmt = $conn->prepare("SELECT o.order_id FROM orders o JOIN transaction_log t ON o.order_id=t.order_id WHERE o.email=? AND t.payment_status='unpaid' ORDER BY o.order_date DESC LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $orderId = $row['order_id'];
        } else {
            // No unpaid order - create a new one
            // Calculate total amount from session cart
            $totalAmount = 0;
            foreach ($_SESSION['cart'] as $id => $qty) {
                $stmt2 = $conn->prepare("SELECT price FROM properties WHERE property_id=?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $res = $stmt2->get_result();
                if ($r2 = $res->fetch_assoc()) {
                    $totalAmount += $r2['price'] * $qty;
                }
                $stmt2->close();
            }

            $currencyId = 1; // Whatever your default is
            $stmtInsert = $conn->prepare("INSERT INTO orders (email, order_date, total_amount, currency_id) VALUES (?, CURDATE(), ?, ?)");
            $stmtInsert->bind_param("sdi", $userEmail, $totalAmount, $currencyId);
            $stmtInsert->execute();
            $orderId = $stmtInsert->insert_id;
            $stmtInsert->close();

            $stmtTrans = $conn->prepare("INSERT INTO transaction_log (order_id, payment_status) VALUES (?, 'unpaid')");
            $stmtTrans->bind_param("i", $orderId);
            $stmtTrans->execute();
            $stmtTrans->close();
        }

        // Now update or insert order_items with the current session cart quantities
        foreach ($_SESSION['cart'] as $id => $qty) {
            $stmtCheck = $conn->prepare("SELECT quantity FROM order_items WHERE order_id=? AND property_id=?");
            $stmtCheck->bind_param("ii", $orderId, $id);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();

            if ($resCheck->fetch_assoc()) {
                $stmtUpdate = $conn->prepare("UPDATE order_items SET quantity=? WHERE order_id=? AND property_id=?");
                $stmtUpdate->bind_param("iii", $qty, $orderId, $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                $stmtInsertItem = $conn->prepare("INSERT INTO order_items (order_id, property_id, quantity) VALUES (?, ?, ?)");
                $stmtInsertItem->bind_param("iii", $orderId, $id, $qty);
                $stmtInsertItem->execute();
                $stmtInsertItem->close();
            }
            $stmtCheck->close();
        }


        // Sync session cart with order_items table
        foreach ($_SESSION['cart'] as $id => $quantity) {
            // Check if order_item exists
            $stmtCheck = $conn->prepare("SELECT quantity FROM order_items WHERE order_id = ? AND property_id = ?");
            $stmtCheck->bind_param("ii", $orderId, $id);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();

            if ($rowCheck = $resCheck->fetch_assoc()) {
                // Update quantity on existing item
                $newQty = $_SESSION['cart'][$id]; // This is the total quantity for this item in the session cart
                $stmtUpdate = $conn->prepare("UPDATE order_items SET quantity = ? WHERE order_id = ? AND property_id = ?");
                $stmtUpdate->bind_param("iii", $newQty, $orderId, $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                // Insert new item
                $stmtInsertItem = $conn->prepare("INSERT INTO order_items (order_id, property_id, quantity) VALUES (?, ?, ?)");
                $stmtInsertItem->bind_param("iii", $orderId, $id, $quantity);
                $stmtInsertItem->execute();
                $stmtInsertItem->close();
            }
            $stmtCheck->close();
        }

        // For debugging
        error_log('Cart Contents: ' . print_r($_SESSION['cart'], true));

        // Redirect after POST
        header("Location: shopping_cart.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/view_details.css">
    <title>View Property Details</title>
    <script>
        // JavaScript function to show a popup if the property is sold
        function checkPropertyStatus() {
            var status = "<?php echo $property ? $property['offer_type'] : ''; ?>";
            if (status === 'Sold') {
                alert("Sorry, this property has already been sold.");
                return false; // Prevent form submission
            }
            return true; // Proceed with form submission
        }
    </script>
</head>
<body>
    <!-- Background gradient -->
    <div class="property-bg-gradient"></div>

    <!-- Header -->
    <header>
        <h1 class="site_header">Bahay Ni Kuya</h1>
        <p>Finding your dream home with us is easier than surviving Kuya’s weekly eviction!</p>
        <a href="shopping_cart.php" class="cart-button">
            <span>Cart 🛒</span>
            <span class="cart-count">0</span>
        </a>
    </header>

    <!-- Navigation Bar -->
    <nav>
        <ul>
            <li><a class="topNavBar" href="shopping_cart.php">Shopping Cart</a></li>
            <li><a class="topNavBar" href="checkout.php">Checkout Page</a></li>
            <li><a class="topNavBar" href="#">About Us</a></li>
            <li><a class="topNavBar" href="#">Contact</a></li>
            <li><a class="topNavBar" href='logout.php'">Sign Out</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <?php if ($property): ?>
            <!-- Back Button -->
            <a href="property_listing.php" class="backButton">Back</a>

            <!-- Big Blue Rectangle -->
            <div class="blueContainer">

                <!-- Left section: Image and info -->
                <div class="leftContainer">
                    <h1 class="propertyName">
                        <?php echo htmlspecialchars($property['property_name']); ?>
                    </h1>
                    <img src="<?php echo htmlspecialchars($property['photo']); ?>" alt="Property Image" style="width: 100%; border-radius: 20px;">
                    <p class="propertyAddress"><?php echo htmlspecialchars($property['address']); ?></p>
                    <p class="propertyPrice">
                        ₱<?php echo number_format($property['price'], 0); ?>
                    </p>
                </div>

                <!-- Right section: Description -->
                <div class="rightContainer">
                    <h2 class="descriptionHeader">DESCRIPTION</h2>
                    <p class="descriptionBody"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                    <p class="propertyStatus">Status:
                        <?php
                        if (isset($property['offer_type'])) {
                            echo $property['offer_type'] === 'For Sale' ? 'For Sale' : 'Sold';
                        }
                        ?>
                    </p>

                    <!-- Add to Cart Form -->
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['property_id']) ? '?property_id=' . $_GET['property_id'] : '')); ?>" 
                    style="margin-top: 20px;" onsubmit="return checkPropertyStatus()">
                        <input type="hidden" name="property_id" value="<?php echo $property['property_id']; ?>">
                        <button type="submit" style="background-color: red; color: white; border: none; padding: 12px 24px; border-radius: 50px; font-size: 18px; font-weight: bold; cursor: pointer;">
                            ADD TO CART
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <h2>Property not found.</h2>
        <?php endif; ?>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Prime Properties is the leading real estate agency in the Philippines, helping clients find their dream homes since 2010.</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="#">Home</a>
                <a href="#">Properties</a>
                <a href="#">Services</a>
                <a href="#">Contact</a>
            </div>
            
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p>123 Real Estate Ave, Makati</p>
                <p>Phone: (02) 8123 4567</p>
                <p>Email: info@primeproperties.ph</p>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; 2023 Very Good Properties. All rights are not reserved.</p>
        </div>
    </footer>
</body>
</html>
