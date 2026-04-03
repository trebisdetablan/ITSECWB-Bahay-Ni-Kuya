<?php
session_start();
require_once('../includes/dbconfig.php');

// Check if user is logged in and is a customer
include('../assets/php/authorization.php');
customerAccess($conn, "/profile");

//For Timeout
require_once('../assets/php/authentication.php');

$email = $_SESSION['user_email'];

// Fetch user details
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - Bahay Ni Kuya</title>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<style>
    :root {
        --MainBlue: #0476D0;
        --MainRed: #E32227;
        --MainYellow: #FFDF00;
        --DefaultFont: 'Lato', serif;
        --DefaultHeaderFont: 'Bebas Neue', sans-serif;
        --DefaultFontColor: black;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: var(--DefaultFont); }
    body {
        background-image: url('../images/pbb house.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        color: #333;
        min-height: 100vh;
    }
    .property-bg-gradient {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        z-index: 0;
        background: linear-gradient(135deg, rgba(80,124,147,0.7) 0%, rgba(255,223,0,0.3) 100%);
    }
    body > *:not(.property-bg-gradient) { position: relative; z-index: 1; }
    header {
        background-color: var(--MainRed);
        color: white;
        padding: 20px 0;
        text-align: center;
    }
    nav {
        background-color: var(--MainYellow);
        padding: 10px 0;
    }
    nav ul {
        display: flex; justify-content: center; list-style: none; align-items: center;
    }
    nav ul li { margin: 0 15px; }
    nav ul li a {
        color: var(--DefaultFontColor);
        text-decoration: none;
        font-weight: bold;
        font-size: 18px;
    }
    .container {
        max-width: 800px;
        margin: 30px auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        text-align: center;
    }
    .profile-header {
        font-family: var(--DefaultHeaderFont);
        font-size: 36px;
        margin-bottom: 20px;
        color: var(--MainBlue);
    }
    .profile-info {
        font-size: 18px;
        margin: 10px 0;
    }
    .change-password-btn {
        background-color: var(--MainBlue);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        margin-top: 20px;
    }
    .change-password-btn:hover { background-color: #035a9e; }
    footer {
        background-color: var(--MainBlue);
        color: white;
        text-align: center;
        padding: 30px 0;
        margin-top: 40px;
    }
    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        text-align: left;
        padding: 0 20px;
    }
    .footer-section h3 { margin-bottom: 15px; font-size: 18px; }
    .footer-section p, .footer-section a {
        color: #ecf0f1; margin-bottom: 10px; display: block; text-decoration: none;
    }
    .copyright {
        margin-top: 20px; padding-top: 20px; border-top: 1px solid #4a6278;
    }
    @media (max-width: 768px) {
        .footer-content { grid-template-columns: 1fr; gap: 20px; }
    }

    .login_input[type="password"] {
    width: 60%;                
    padding: 12px;             
    border: 2px solid black; 
    border-radius: 8px;
    background-color: #f0f8ff;
    color: black;               /* Text color */
    font-size: 16px;           /* Larger font */
    margin: 5px auto 15px auto; /* Top, auto (left/right for centering), bottom */
    box-sizing: border-box;
    display: block;
    font-family: 'Lato', serif;
}
    .close:hover { color: black; }
</style>
</head>
<body>
<div class="property-bg-gradient"></div>
<header>
    <h1 class="site_header">Bahay Ni Kuya</h1>
    <p>Your Profile Information</p>
</header>

<nav>
    <ul>
        <li><a href="property_listing.php">Properties</a></li>
        <li><a href="shopping_cart.php">Shopping Cart</a></li>
        <li><a href="checkout.php">Checkout</a></li>
        <li><a href="logout.php">Sign Out</a></li>
    </ul>
</nav>

<div class="container">
    <h2 class="profile-header">My Profile</h2>
    <?php if ($user): ?>
        <p class="profile-info"><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
        <p class="profile-info"><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
        <p class="profile-info"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p class="profile-info">
            <strong>Set Up Account Recovery:</strong>
        </p>
        <button id="securityQuestionBtn" class="change-password-btn">Set Security Question</button>

        <p class="profile-info">
            <strong>Type Current Password:</strong>
            <input class="login_input" type="password" id="currentPassword" name="current_password" placeholder="Enter current password">
        </p>
        <button id="changePasswordBtn" class="change-password-btn">Change Password</button>
        <p id="errorMsg" style="color:red;"></p>
    <?php else: ?>
        <p>User not found.</p>
    <?php endif; ?>
</div>

<script>
document.getElementById('changePasswordBtn').addEventListener('click', function() {
    let currentPassword = document.getElementById('currentPassword').value;

    fetch('verify_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'current_password=' + encodeURIComponent(currentPassword)
    })
    .then(response => response.text()) // read as plain text
    .then(text => {
        if (text.trim() === 'success') {
            window.location.href = 'reset_password.php';
        } else {
            document.getElementById('errorMsg').textContent = 'Incorrect password.';
        }
    });
});

document.getElementById('securityQuestionBtn').addEventListener('click', function() {
    window.location.href = 'security_question.php';
});
</script>

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
        <p>&copy; 2023 Prime Properties. All rights reserved.</p>
    </div>
</footer>

</body>
</html>
