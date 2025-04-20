<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable debugging
$debug_mode = true; // Set to false in production

// Check if the user is already logged in
if(isset($_SESSION["user_id"])){
    redirect("dashboard.php");
}

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";
$debug_info = "";

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Check for debug mode
    if ($debug_mode) {
        $debug_info .= "Login attempt initiated\n";
        $debug_info .= "Connection status: " . ($conn ? "Connected" : "Not connected") . "\n";
    }
 
    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, email, password FROM users WHERE username = ?";
        
        if ($debug_mode) {
            $debug_info .= "Executing SQL: " . $sql . "\n";
        }
        
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Store result
                $stmt->store_result();
                
                if ($debug_mode) {
                    $debug_info .= "Found users: " . $stmt->num_rows . "\n";
                }
                
                // Check if username exists, if yes then verify password
                if($stmt->num_rows == 1){                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $email, $hashed_password);
                    if($stmt->fetch()){
                        if ($debug_mode) {
                            $debug_info .= "User found, verifying password\n";
                            // DON'T output password hashes in production!
                            $debug_info .= "Stored hash: " . substr($hashed_password, 0, 10) . "...(redacted)\n";
                        }
                        
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            
                            // Store data in session variables
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            
                            // Set welcome message
                            setFlashMessage("Welcome back, {$username}!", "success");
                            
                            if ($debug_mode) {
                                $debug_info .= "Login successful, redirecting to dashboard\n";
                                logError($debug_info);
                            }
                            
                            // Redirect user to dashboard
                            redirect("dashboard.php");
                        } else{
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                            if ($debug_mode) {
                                $debug_info .= "Password verification failed\n";
                                logError($debug_info);
                            }
                        }
                    }
                } else{
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                    if ($debug_mode) {
                        $debug_info .= "Username not found in database\n";
                        logError($debug_info);
                    }
                }
            } else{
                $login_err = "Something went wrong. Please try again later.";
                if ($debug_mode) {
                    $debug_info .= "Statement execution failed: " . $stmt->error . "\n";
                    logError($debug_info);
                }
            }

            // Close statement
            $stmt->close();
        } else {
            $login_err = "Something went wrong. Please try again later.";
            if ($debug_mode) {
                $debug_info .= "Statement preparation failed: " . $conn->error . "\n";
                logError($debug_info);
            }
        }
    } else if ($debug_mode) {
        $debug_info .= "Form validation failed\n";
        logError($debug_info);
    }
}

// Check if this is a first-time visitor using a cookie
$new_user = !isset($_COOKIE['returning_user']);
if ($new_user) {
    // Set cookie to track returning users (1 month expiry)
    setcookie('returning_user', '1', time() + (30 * 24 * 60 * 60), '/');
}

// Check if we have a welcome message
$show_welcome = $new_user;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinMate- Student Finance Management</title>
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
        
        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            overflow: hidden;
            background-color: var(--white);
        }
        
        .welcome-side {
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
        
        .welcome-side h1 {
            font-size: 2.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--white);
        }
        
        .welcome-side p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .welcome-side .features {
            margin-top: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .feature-item i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: var(--accent-color);
        }
        
        .feature-item div {
            flex: 1;
        }
        
        .feature-item h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .feature-item p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.8;
        }
        
        .login-side {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            font-size: 2rem;
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
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
        
        .login-btn {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        .login-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 90, 205, 0.2);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        .login-footer p {
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .login-footer a:hover {
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
        
        /* Budget Image Styling */
        .budget-image-container {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
            transition: all 0.5s ease;
        }
        
        .budget-image {
            max-width: 100%;
            height: auto;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
            transform: translateY(0);
            transition: transform 0.5s ease;
        }
        
        .budget-image-container:hover .budget-image {
            transform: translateY(-5px);
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }
        
        /* Welcome Modal Styling */
        .welcome-modal .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }
        
        .welcome-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .welcome-modal .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .welcome-modal .modal-body {
            padding: 2rem;
        }
        
        .welcome-modal .feature-list {
            margin-top: 1.5rem;
        }
        
        .welcome-modal .feature-item {
            margin-bottom: 1.25rem;
        }
        
        .welcome-modal .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
        }
        
        .welcome-modal .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .welcome-side {
                padding: 2rem;
            }
            
            .login-side {
                padding: 2rem;
            }
            
            .welcome-side .features {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 1rem;
            }
            
            .welcome-side, .login-side {
                padding: 1.5rem;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
        
        /* For extremely small devices */
        @media (max-width: 320px) {
            .login-side {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="login-container">
            <!-- Welcome Side with Animation -->
            <div class="welcome-side">
                <!-- Decorative bubbles for animation -->
                <div class="bubble bubble-1"></div>
                <div class="bubble bubble-2"></div>
                <div class="bubble bubble-3"></div>
                
                <h1>Welcome to FinMate</h1>
                <p>Your smart companion for student finances. Track expenses, set budgets, and reach your savings goals.</p>
                
                <!-- Budget Tracking Image -->
                <div class="budget-image-container">
                    <img src="assets/images/budget-tracking.svg" alt="Budget Tracking" class="budget-image">
                </div>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-chart-pie"></i>
                        <div>
                            <h3>Track Your Spending</h3>
                            <p>Visual insights into where your money goes</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-piggy-bank"></i>
                        <div>
                            <h3>Savings Goals</h3>
                            <p>Set financial targets and track your progress</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-wallet"></i>
                        <div>
                            <h3>Budget Wisely</h3>
                            <p>Create budgets and get alerts when you exceed them</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Login Side -->
            <div class="login-side">
                <div class="login-header">
                    <h2>Log In</h2>
                    <p>Welcome back! Please log in to continue</p>
                </div>
                
                <?php if(!empty($login_err)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $login_err; ?>
                    </div>
                <?php endif; ?>
                
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
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Password">
                        <label for="password">Password</label>
                        <?php if(!empty($password_err)): ?>
                            <div class="invalid-feedback">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo $password_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 login-btn">
                        <i class="fas fa-sign-in-alt me-2"></i> Log In
                    </button>
                    
                    <div class="login-footer">
                        <p>Don't have an account? <a href="register.php">Sign up now</a></p>
                        <p><a href="forgot_password.php"><i class="fas fa-key me-1"></i> Forgot your password?</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Welcome Modal for First-Time Visitors -->
    <?php if($show_welcome): ?>
    <div class="modal fade welcome-modal" id="welcomeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-rocket me-2"></i> Welcome to FinMate!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>FinMate is designed to help students like you manage finances better. Here's what you can do:</p>
                    
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-chart-line text-primary"></i>
                            <div>
                                <h5>Track Income & Expenses</h5>
                                <p>Record all your financial transactions in one place</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-bullseye text-primary"></i>
                            <div>
                                <h5>Set Financial Goals</h5>
                                <p>Save for what matters with clear targets</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-lightbulb text-primary"></i>
                            <div>
                                <h5>Get Smart Insights</h5>
                                <p>Receive tailored financial tips based on your spending habits</p>
                            </div>
                        </div>
                    </div>
                    
                    <p class="mt-3">Ready to take control of your finances?</p>
                </div>
                <div class="modal-footer">
                    <a href="register.php" class="btn btn-primary">Create an Account</a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Sign In Instead
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if($show_welcome): ?>
    <script>
        // Show welcome modal for first-time visitors
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
            welcomeModal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html> 