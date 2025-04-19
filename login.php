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

// Debug information for development only - REMOVE IN PRODUCTION!
if ($debug_mode && !empty($debug_info)) {
    // Uncomment to display debug info on page for development
    // $login_err .= "<pre>" . htmlspecialchars($debug_info) . "</pre>";
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Login</h4>
            </div>
            <div class="card-body">
                <?php 
                if(!empty($login_err)){
                    echo '<div class="alert alert-danger">' . $login_err . '</div>';
                }        
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    </div>    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </div>
                    <p class="text-center">Don't have an account? <a href="register.php">Sign up now</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 