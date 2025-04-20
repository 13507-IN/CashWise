<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if(isLoggedIn()) {
    redirect("dashboard.php");
}

// Initialize variables
$email = $token = "";
$new_password = $confirm_password = "";
$email_err = $token_err = $password_err = $confirm_password_err = "";
$success = false;

// Check if token and email are provided in URL
if($_SERVER["REQUEST_METHOD"] == "GET") {
    if(isset($_GET["token"]) && isset($_GET["email"])) {
        $token = trim($_GET["token"]);
        $email = trim(urldecode($_GET["email"]));
    } else {
        // Redirect to forgot password page if token or email is missing
        redirect("forgot_password.php");
    }
}

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get hidden token and email
    $token = trim($_POST["token"]);
    $email = trim($_POST["email"]);
    
    // Validate password
    if(empty(trim($_POST["new_password"]))) {
        $password_err = "Please enter the new password.";     
    } elseif(strlen(trim($_POST["new_password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before updating the database
    if(empty($password_err) && empty($confirm_password_err)) {
        
        // Find user by email
        $sql = "SELECT id FROM users WHERE email = ?";
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            
            if($stmt->execute()) {
                $stmt->store_result();
                
                if($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id);
                    $stmt->fetch();
                    
                    // Check if token exists and is valid
                    $token_sql = "SELECT token, expiry FROM password_resets WHERE user_id = ? AND expiry > NOW()";
                    if($token_stmt = $conn->prepare($token_sql)) {
                        $token_stmt->bind_param("i", $user_id);
                        
                        if($token_stmt->execute()) {
                            $token_stmt->store_result();
                            
                            if($token_stmt->num_rows == 1) {
                                $token_stmt->bind_result($stored_token, $expiry);
                                $token_stmt->fetch();
                                
                                // Verify token
                                if(password_verify($token, $stored_token)) {
                                    // Token is valid, update password
                                    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                                    if($update_stmt = $conn->prepare($update_sql)) {
                                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                        $update_stmt->bind_param("si", $hashed_password, $user_id);
                                        
                                        if($update_stmt->execute()) {
                                            // Delete the used token
                                            $delete_sql = "DELETE FROM password_resets WHERE user_id = ?";
                                            if($delete_stmt = $conn->prepare($delete_sql)) {
                                                $delete_stmt->bind_param("i", $user_id);
                                                $delete_stmt->execute();
                                                $delete_stmt->close();
                                            }
                                            
                                            // Password updated successfully
                                            $success = true;
                                            
                                            // Log the password reset
                                            logError("Password reset successful for user ID: $user_id");
                                        } else {
                                            $token_err = "Error updating password. Please try again later.";
                                            logError("Error updating password for user ID: $user_id - " . $update_stmt->error);
                                        }
                                        
                                        $update_stmt->close();
                                    } else {
                                        $token_err = "Error preparing update statement. Please try again later.";
                                        logError("Error preparing update statement: " . $conn->error);
                                    }
                                } else {
                                    $token_err = "Invalid token. Please request a new password reset link.";
                                    logError("Invalid token provided for user ID: $user_id");
                                }
                            } else {
                                $token_err = "Password reset link has expired. Please request a new one.";
                                logError("Expired token for user ID: $user_id");
                            }
                        } else {
                            $token_err = "Error checking token validity. Please try again later.";
                            logError("Error checking token validity: " . $token_stmt->error);
                        }
                        
                        $token_stmt->close();
                    } else {
                        $token_err = "Error preparing token statement. Please try again later.";
                        logError("Error preparing token statement: " . $conn->error);
                    }
                } else {
                    $email_err = "No account found with that email.";
                    logError("No account found with email: $email during password reset");
                }
            } else {
                $email_err = "Something went wrong. Please try again later.";
                logError("Error executing user search: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            $email_err = "Something went wrong. Please try again later.";
            logError("Error preparing user search statement: " . $conn->error);
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Reset Password</h4>
            </div>
            <div class="card-body">
                <?php if($success): ?>
                
                <div class="alert alert-success">
                    <p>Your password has been reset successfully!</p>
                </div>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">Login with New Password</a>
                </div>
                
                <?php else: ?>
                
                <?php 
                if(!empty($token_err)) {
                    echo '<div class="alert alert-danger">' . $token_err . '</div>';
                }
                if(!empty($email_err)) {
                    echo '<div class="alert alert-danger">' . $email_err . '</div>';
                }
                ?>
                
                <p class="mb-3">Enter your new password below.</p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <small class="text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 