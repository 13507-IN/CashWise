<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the categories initialization file with proper path
$init_categories_path = dirname(__FILE__) . '/database/init_categories.php';
// We only need the function, not the execution of the script
function addDefaultCategoriesForUser($conn, $userId) {
    // First check if the user already has categories
    $check_sql = "SELECT COUNT(*) as count FROM categories WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        return false;
    }
    
    $check_stmt->bind_param("i", $userId);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    // If user already has categories, don't add them again
    if ($row['count'] > 0) {
        return true;
    }
    
    // Use INSERT IGNORE to prevent duplicate key errors
    $sql = "INSERT IGNORE INTO categories (user_id, name, type, color, icon, is_system) 
            SELECT ?, name, type, color, icon, 0 FROM categories WHERE user_id IS NULL";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        return true;
    } else {
        // Log the error for debugging
        error_log("Failed to add default categories: " . $stmt->error);
        return false;
    }
}

// Initialize variables
$username = $email = $password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Check if email is valid
        if (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if email exists
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("s", $param_email);
                
                // Set parameters
                $param_email = trim($_POST["email"]);
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    // Store result
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $email_err = "This email is already registered.";
                    } else {
                        $email = trim($_POST["email"]);
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                $stmt->close();
            }
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("sss", $param_username, $param_email, $param_password);
            
            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Insert default categories for the new user
                $user_id = $conn->insert_id;
                if (addDefaultCategoriesForUser($conn, $user_id)) {
                    // Redirect to login page
                    setFlashMessage("Registration successful! You can now log in.", "success");
                    redirect("login.php");
                } else {
                    setFlashMessage("Registration successful, but there was an issue setting up default categories.", "warning");
                    redirect("login.php");
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - FinMate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a5acd;
            --primary-light: #9c8cff;
            --primary-dark: #483d8b;
            --secondary-color: #4caf50;
            --accent-color: #ff9800;
            --text-color: #333;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --bg-dark: #343a40;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eafc 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-color);
        }
        
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .register-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            overflow: hidden;
            background-color: var(--white);
        }
        
        .benefits-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .benefits-side h1 {
            font-size: 2.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--white);
        }
        
        .benefits-side p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .benefits-list {
            margin-top: 2rem;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .benefit-item i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: var(--accent-color);
        }
        
        .benefit-item div {
            flex: 1;
        }
        
        .benefit-item h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .benefit-item p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.8;
        }
        
        .register-side {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h2 {
            font-size: 2rem;
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: var(--text-light);
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating label {
            color: var(--text-light);
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            height: calc(3.5rem + 2px);
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(106, 90, 205, 0.15);
        }
        
        .register-btn {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        .register-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 90, 205, 0.2);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        .register-footer p {
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        
        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .register-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 8s infinite ease-in-out;
        }
        
        .bubble-1 {
            width: 100px;
            height: 100px;
            top: 20%;
            left: -50px;
            animation-delay: 0s;
        }
        
        .bubble-2 {
            width: 150px;
            height: 150px;
            top: 60%;
            right: -75px;
            animation-delay: 2s;
        }
        
        .bubble-3 {
            width: 80px;
            height: 80px;
            bottom: 10%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .benefits-side {
                padding: 2rem;
                order: 2;
            }
            
            .register-side {
                padding: 2rem;
                order: 1;
            }
            
            .benefits-list {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 1rem;
            }
            
            .benefits-side, .register-side {
                padding: 1.5rem;
            }
            
            .register-header h2 {
                font-size: 1.5rem;
            }
        }
        
        /* For extremely small devices */
        @media (max-width: 320px) {
            .register-side {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="register-container">
            <!-- Benefits Side with Animation -->
            <div class="benefits-side">
                <!-- Decorative bubbles for animation -->
                <div class="bubble bubble-1"></div>
                <div class="bubble bubble-2"></div>
                <div class="bubble bubble-3"></div>
                
                <h1>Join FinMate</h1>
                <p>Create your account and start your journey to better financial management as a student.</p>
                
                <div class="benefits-list">
                    <div class="benefit-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <h3>Secure & Private</h3>
                            <p>Your financial data stays private and secure</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-bolt"></i>
                        <div>
                            <h3>Easy Setup</h3>
                            <p>Start tracking your finances in minutes</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <h3>Financial Growth</h3>
                            <p>Learn better money habits with smart insights</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <h3>Access Anywhere</h3>
                            <p>Works on all your devices</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Register Side -->
            <div class="register-side">
                <div class="register-header">
                    <h2>Create Account</h2>
                    <p>Fill in your details to get started</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-floating mb-3">
                        <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" placeholder="Username" value="<?php echo $username; ?>">
                        <label for="username">Username</label>
                        <?php if(!empty($username_err)): ?>
                            <div class="invalid-feedback">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo $username_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>    
                    
                    <div class="form-floating mb-3">
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" placeholder="Email" value="<?php echo $email; ?>">
                        <label for="email">Email</label>
                        <?php if(!empty($email_err)): ?>
                            <div class="invalid-feedback">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo $email_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>    
                    
                    <div class="form-floating mb-3">
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Password">
                        <label for="password">Password</label>
                        <?php if(!empty($password_err)): ?>
                            <div class="invalid-feedback">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo $password_err; ?>
                            </div>
                        <?php else: ?>
                            <div class="form-text">Password must be at least 6 characters long</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm Password">
                        <label for="confirm_password">Confirm Password</label>
                        <?php if(!empty($confirm_password_err)): ?>
                            <div class="invalid-feedback">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo $confirm_password_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 register-btn">
                        <i class="fas fa-user-plus me-2"></i> Create Account
                    </button>
                    
                    <div class="register-footer">
                        <p>Already have an account? <a href="login.php">Log in</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>