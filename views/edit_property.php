<?php
// edit_property.php
    require_once('../includes/dbconfig.php');

    //For Timeout
    require_once('../assets/php/authentication.php');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Fetch property data
    $stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    
    if ($property) {
        ?>
        <form class="edit-property-form" action="sql_update_property.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $property['property_id'] ?>">
            
            <!-- Name -->
                <div class = "form-group">
                    <div class="form-group">
                    <label for="name">Property Name:</label>
                    <input type="text" id="name" name="name" maxlength="200" value="<?= htmlspecialchars($property['property_name']) ?>" required>
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

                <!-- Property Price -->
                <!-- Minimum of 1 to 999,999,999,999 for the price -->
                <div class="form-group">
                    <label for="price">Price (₱):</label>
                    <input type="number" id="price" name="price" step="0.01" min="1" max="999999999999" value="<?= htmlspecialchars(number_format((float)$property['price'], 2, '.', '')) ?>" required>
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
                    <textarea id="description" name="description" rows="4" maxlength="1500" required><?= htmlspecialchars($property['description']) ?></textarea>
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
                
                <!-- Addresss -->
                <div class="form-group">
                    <label for="addresss">Addresss:</label>

                    <!-- Limits the character inputs to 1500 only -->
                    <!-- Once the character limit is reached, it will not let the user to enter anymore input -->
                    <input type="text" id="address" name="address" maxlength="75" value="<?= htmlspecialchars($property['address']) ?>" required>
                </div>

                <!-- Front-end error thrower for description -->
                <!-- Will only run if the user somehow bypasses the character input limiter -->
                <script>
                    const addressInput = document.getElementById('address');
                    const maxAddressLen = 75;

                    addressInput.addEventListener('input', function () {
                        this.setCustomValidity(''); // reset message
                        if (this.value.length > maxAddressLen) {
                            this.setCustomValidity(`Addresss must not exceed ${maxAddressLen} characters.`);
                        }
                    });

                    addressInput.addEventListener('invalid', function () {
                        if (this.validity.valueMissing) {
                            this.setCustomValidity('Please enter the addresss.');
                        } else if (this.value.length > maxAddressLen) {
                            this.setCustomValidity(`Addresss must not exceed ${maxAddressLen} characters.`);
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                </script>
            
            <!-- Photo -->
            <div class="form-group">
                <label>Current Image:</label>
                <img src="../uploads/<?= $property['photo'] ?>" style="max-width:200px;display:block;margin:10px 0;">
            </div>
            
            <div class="form-group">
                <label for="image">New Image (leave blank to keep current):</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Update Property
            </button>
        </form>
        <?php
    } else {
        echo "<div class='alert alert-error'>Property not found</div>";
    }
} else {
    echo "<div class='alert alert-error'>No property ID specified</div>";
}
?>
