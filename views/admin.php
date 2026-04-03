<?php
session_start();

// Database configuration
require_once('../includes/dbconfig.php');

// Check if user is logged in and is an admin
include('../assets/php/authorization.php');
adminAccess($conn);

// For Timeout
require_once('../assets/php/authentication.php');

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bahay ni Kuya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
   
    <script src="../assets/js/admin.js"></script>
    <script>
        const userRole = '<?php echo $_SESSION['user_role']; ?>';

        document.addEventListener('DOMContentLoaded', function() {
            // Hide Action Column and Event Logs from Staff
            if(userRole == 'S'){
                document.querySelectorAll('th.action-buttons').forEach(th => {
                    th.style.display = 'none';
                });

                document.querySelectorAll('td.action-buttons').forEach(td => {
                    td.style.display = 'none';
                });

                document.querySelector('#add-property-btn').style.display = 'none';

                document.querySelector('#logs-btn').style.display = 'none';
         
            }

        });
    </script>
</head>
<body>
    <!-- Background gradient overlay -->
    <div class="property-bg-gradient"></div>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">Admin Dashboard</h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
        
        <?php
        // Display success/error messages if they exist
        if (isset($_SESSION['admin_message'])) {
            $messageType = $_SESSION['admin_message_type'] ?? 'success';
            echo "<div class='alert alert-$messageType'>" . $_SESSION['admin_message'] . "</div>";
            unset($_SESSION['admin_message']);
            unset($_SESSION['admin_message_type']);
        }
        ?>
        
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="openTab('properties', event)">
                <i class="fas fa-home"></i> Properties
            </button>
            <button class="tab-btn" onclick="openTab('orders', event)">
                <i class="fas fa-clipboard-list"></i> Orders
            </button>
            <button id="add-property-btn" class="tab-btn" onclick="openTab('add-property', event)">
                <i class="fas fa-plus-circle"></i> Add Property
            </button>

            <button id="logs-btn" class="tab-btn" onclick="openTab('logs', event)">
                <i class="fas fa-clipboard-list"></i> Event Logs
            </button>
        </div>
        
        <!-- Properties Tab -->
        <div id="properties" class="tab-content active">
            <h2><i class="fas fa-home"></i> Manage Properties</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="action-buttons">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch properties
                    $sql = "SELECT 
                        property_id as id, 
                        property_name as name, 
                        offer_type as status, 
                        price, 
                        photo as image,
                        'Property' as type,  
                        address as location
                        FROM bahaynikuya_db.properties";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            
                            $statusClass = 'status-' . strtolower($row['status']);
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td><img src='../uploads/{$row['image']}' alt='Property Image' style='width:80px;height:60px;object-fit:cover;'></td>
                                <td>{$row['name']}</td>
                                <td>{$row['type']}</td>
                                <td>₱" . number_format($row['price'], 2) . "</td>
                                <td class='$statusClass'>{$row['status']}</td>
                                <td class='action-buttons'>
                                    <button class='action-btn edit-btn' onclick='editProperty({$row['id']})'>
                                        <i class='fas fa-edit'></i> Edit
                                    </button>

                                    <button class='action-btn delete-btn' onclick='deleteProperty({$row['id']})'>
                                        <i class='fas fa-trash'></i> Delete
                                    </button>

                                    
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No properties found</td></tr>";
                    }
                    

                    ?>
                </tbody>
            </table>

        </div>
        
        <!-- Orders Tab -->
        <div id="orders" class="tab-content">
            <h2><i class="fas fa-clipboard-list"></i> View Orders</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Property</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    
                    // Fetch orders with property and customer details
                    $sql = "SELECT 
                        o.order_id as id, 
                        p.property_name, 
                        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                        o.order_date, 
                        'Completed' as status,
                        o.total_amount 
                        FROM bahaynikuya_db.orders o
                        JOIN bahaynikuya_db.order_items ot ON o.order_id = ot.order_id
                        JOIN bahaynikuya_db.properties p ON ot.property_id = p.property_id

                        JOIN bahaynikuya_db.users u ON o.email = u.email
                        ORDER BY o.order_date DESC";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $statusClass = 'status-' . strtolower($row['status']);
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['property_name']}</td>
                                <td> {$row['full_name']}</td>
                                <td>" . date('M d, Y', strtotime($row['order_date'])) . "</td>
                                <td class='$statusClass'>{$row['status']}</td>
                                <td>₱" . number_format($row['total_amount'], 2) . "</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No orders found</td></tr>";
                    }
                    
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Add Property Tab -->
        <div id="add-property" class="tab-content">
            <h2><i class="fas fa-plus-circle"></i> Add New Property</h2>
            <form class="add-property-form" method="POST" action="sql_add_property.php" enctype="multipart/form-data">
                <!-- Name -->
                <div class = "form-group">
                    <div class="form-group">
                    <label for="name">Property Name:</label>
                    <input type="text" id="name" name="name" maxlength="200" required>
                </div>

                <!-- Front-end error thrower -->
                <!-- This script will only show if the error has sucesfully entered more than 200 characters -->
                <script>
                    const nameInput = document.getElementById('name');
                    const maxLen = 200;

                    nameInput.addEventListener('input', function () {
                        this.setCustomValidity(''); // reset message
                        if (this.value.length > maxLen) {
                            this.setCustomValidity(`Property name must not exceed ${maxLen} characters.`);
                        }
                    });

                    nameInput.addEventListener('invalid', function () {
                        if (this.validity.valueMissing) {
                            this.setCustomValidity('Please enter a property name.');
                        } else if (this.value.length > maxLen) {
                            this.setCustomValidity(`Property name must not exceed ${maxLen} characters.`);
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                </script>

                    <!-- Minimum of 1 to 999,999,999,999 for the price -->
                    <div class="form-group">
                    <label for="price">Price (₱):</label>
                    <input type="number" id="price" name="price" step="0.01" min="1" max="999999999999" required>
                </div>

                <!-- Front-end error thrower for property price -->
                <script>
                    const priceInput = document.getElementById('price');

                    priceInput.addEventListener('invalid', function () {
                        if (this.validity.rangeUnderflow) {
                            this.setCustomValidity('Price must be at least ₱1.');
                        } else if (this.validity.rangeOverflow) {
                            this.setCustomValidity('Price must not exceed ₱999,999,999,999.');
                        } else if (this.validity.valueMissing) {
                            this.setCustomValidity('Please enter a price.');
                        } else {
                            this.setCustomValidity('');
                        }
                    });

                    priceInput.addEventListener('input', function () {
                        this.setCustomValidity('');
                    });
                    </script>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description:</label>

                    <!-- Limits the character inputs to 1500 only -->
                    <!-- Once the character limit is reached, it will not let the user to enter anymore input -->
                    <textarea id="description" name="description" rows="4" maxlength="1500" required></textarea>
                </div>

                <!-- Front-end error thrower for description -->
                <!-- Will only run if the user somehow bypasses the character input limiter -->
                <script>
                    const descriptionInput = document.getElementById('description');
                    const maxDescLen = 1500;

                    descriptionInput.addEventListener('input', function () {
                        this.setCustomValidity(''); // reset any old message
                        if (this.value.length > maxDescLen) {
                            this.setCustomValidity(`Description must not exceed ${maxDescLen} characters.`);
                        }
                    });

                    descriptionInput.addEventListener('invalid', function () {
                        if (this.validity.valueMissing) {
                            this.setCustomValidity('Please enter a description.');
                        } else if (this.value.length > maxDescLen) {
                            this.setCustomValidity(`Description must not exceed ${maxDescLen} characters.`);
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                </script>
                
                <!-- Location -->
                <div class="form-group">
                    <label for="location">Location:</label>

                    <!-- Limits the character inputs to 1500 only -->
                    <!-- Once the character limit is reached, it will not let the user to enter anymore input -->
                    <input type="text" id="address" name="address" maxlength="75" required>
                </div>

                <!-- Front-end error thrower for description -->
                <!-- Will only run if the user somehow bypasses the character input limiter -->
                <script>
                    const addressInput = document.getElementById('address');
                    const maxAddressLen = 75;

                    addressInput.addEventListener('input', function () {
                        this.setCustomValidity(''); // reset message
                        if (this.value.length > maxAddressLen) {
                            this.setCustomValidity(`Location must not exceed ${maxAddressLen} characters.`);
                        }
                    });

                    addressInput.addEventListener('invalid', function () {
                        if (this.validity.valueMissing) {
                            this.setCustomValidity('Please enter the location.');
                        } else if (this.value.length > maxAddressLen) {
                            this.setCustomValidity(`Location must not exceed ${maxAddressLen} characters.`);
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                </script>
                
                <!-- Property Image -->
                <div class="form-group">
                    <label for="image">Property Image:</label>
                    <input type="file" id="image" name="image" accept="image/*" required onchange="previewImage(this)">
                    <img id="imagePreview" class="property-image-preview" style="display:none;">
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Add Property
                </button>
            </form>
        </div>
    </div>

    <!-- Logs Tab -->
    <div id="logs" class="tab-content">
        <h2><i class="fas fa-clipboard-list"></i> Event Logs</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Datetime</th>
                    <th>User Email</th>
                    <th>Resource</th>
                    <th>Reason</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php
                
                // Fetch EVENT LOGS
                $sql = "SELECT *
                    FROM event_logs
                    ORDER BY datetime DESC";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        switch($row['type']) {
                            case 'A':
                                $type_label = 'Authorization';
                                break;
                            case 'C':
                                $type_label = 'Access Control';
                                break;
                            case 'I':
                                $type_label = 'Input Validation';
                                break;
                            default:
                                $type_label = 'Unknown';
                        }
                        echo "<tr>
                            <td>{$row['log_id']}</td>
                            <td>{$type_label}</td>
                            <td>{$row['datetime']}</td>
                            <td>{$row['user_email']}</td>
                            <td>{$row['resource']}</td>
                            <td>{$row['reason']}</td>
                            <td>{$row['result']}</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No logs found</td></tr>";
                }
                
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>


    <!-- Edit Property Modal -->
<div id="editPropertyModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editPropertyModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Property</h2>
        <div id="editPropertyContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>
</body>
</html>
