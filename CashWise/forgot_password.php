<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if(isLoggedIn()) {
    redirect("dashboard.php");
}

// Initialize variables
$email = "";
$email_err = "";
$success_msg = "";

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email address.";
    } else {
        $email = trim($_POST["email"]);
        
        // Check if email exists in database
        $sql = "SELECT id, username FROM users WHERE email = ?";
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            
            if($stmt->execute()) {
                $stmt->store_result();
                
                if($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $username);
                    $stmt->fetch();
                    
                    // Generate unique reset token
                    $token = bin2hex(random_bytes(32));
                    $token_hash = password_hash($token, PASSWORD_DEFAULT);
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // First delete any existing tokens for this user
                    $delete_sql = "DELETE FROM password_resets WHERE user_id = ?";
                    if($delete_stmt = $conn->prepare($delete_sql)) {
                        $delete_stmt->bind_param("i", $user_id);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                    
                    // Create reset token in database
                    $token_sql = "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)";
                    if($token_stmt = $conn->prepare($token_sql)) {
                        $token_stmt->bind_param("iss", $user_id, $token_hash, $expiry);
                        
                        if($token_stmt->execute()) {
                            // Send email with reset link (placeholder - would implement actual email sending)
                            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token . "&email=" . urlencode($email);
                            
                            // For demonstration purposes, we'll just show the link
                            // In production, you would use a service to send an actual email
                            $success_msg = "Password reset instructions have been sent to your email address.";
                            
                            // Log the reset link for development purposes (remove in production)
                            logError("Password reset requested for user ID: $user_id ($username). Reset link: $reset_link");
                        } else {
                            $email_err = "Error creating password reset token. Please try again later.";
                            logError("Error creating password reset token: " . $token_stmt->error);
                        }
                        
                        $token_stmt->close();
                    } else {
                        $email_err = "Error preparing password reset statement. Please try again later.";
                        logError("Error preparing password reset statement: " . $conn->error);
                    }
                } else {
                    // Don't reveal if email exists for security
                    $success_msg = "If your email exists in our system, you will receive password reset instructions.";
                    logError("Password reset attempted for non-existent email: $email");
                }
            } else {
                $email_err = "Something went wrong. Please try again later.";
                logError("Error executing email check: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            $email_err = "Something went wrong. Please try again later.";
            logError("Error preparing email check statement: " . $conn->error);
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
                <h4>Forgot Password</h4>
            </div>
            <div class="card-body">
                <?php 
                if(!empty($success_msg)) {
                    echo '<div class="alert alert-success">' . $success_msg . '</div>';
                }
                if(!empty($email_err)) {
                    echo '<div class="alert alert-danger">' . $email_err . '</div>';
                }
                ?>
                
                <p class="mb-3">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                    </div>
                    <div class="text-center">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 