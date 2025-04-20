<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("login.php"); 
}

// Create uploads directory and default profile image if needed
$uploads_dir = 'uploads/profiles';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// Default profile image path
$default_profile = $uploads_dir . '/default.png';
if (!file_exists($default_profile)) {
    // Create a simple default profile image or copy from assets if available
    if (file_exists('assets/img/default-profile.png')) {
        copy('assets/img/default-profile.png', $default_profile);
    } else {
        // Create a simple default image
        $img = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($img, 240, 240, 240);
        imagefill($img, 0, 0, $bg);
        imagepng($img, $default_profile);
        imagedestroy($img);
    }
}

// Get current user ID and data
$user_id = $_SESSION["user_id"];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Check if profile image exists
if (empty($user_data['profile_image']) || !file_exists($user_data['profile_image'])) {
    // Update to default profile image if current profile doesn't exist
    $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $default_profile, $user_id);
    $stmt->execute();
    $user_data['profile_image'] = $default_profile;
}

// Handle profile image upload (separate from profile update)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            setFlashMessage("Only JPG, PNG and GIF images are allowed.", "danger");
            logError("Invalid image type uploaded: " . $_FILES['profile_image']['type']);
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            setFlashMessage("Image size should be less than 5MB.", "danger");
            logError("Oversized image uploaded: " . ($_FILES['profile_image']['size'] / 1024 / 1024) . "MB");
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                // Delete old profile image if exists
                if (!empty($user_data['profile_image']) && 
                    file_exists($user_data['profile_image']) && 
                    $user_data['profile_image'] != $default_profile) {
                    @unlink($user_data['profile_image']);
                }
                
                // Update profile image in database
                $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $target_file, $user_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Profile image updated successfully!", "success");
                    // Update user data for page display
                    $user_data['profile_image'] = $target_file;
                    
                    // Log success
                    logError("Profile image updated for user ID: $user_id - New image: $target_file");
                } else {
                    setFlashMessage("Error updating profile image: " . $conn->error, "danger");
                    logError("Error updating profile image in database: " . $conn->error);
                }
            } else {
                setFlashMessage("Failed to upload image.", "danger");
                logError("Failed to move uploaded image to destination: $target_file");
            }
        }
    } else if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != 0) {
        // Log file upload error
        $error_message = "File upload error: ";
        switch ($_FILES['profile_image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message .= "The uploaded file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message .= "No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message .= "Missing a temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message .= "Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message .= "A PHP extension stopped the file upload.";
                break;
            default:
                $error_message .= "Unknown error code: " . $_FILES['profile_image']['error'];
        }
        setFlashMessage("Error uploading image: " . $error_message, "danger");
        logError($error_message);
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = sanitizeInput($_POST['username']);
    $fullname = sanitizeInput($_POST['fullname']);
    $gender = sanitizeInput($_POST['gender']);
    $profession = sanitizeInput($_POST['profession']);
    $bio = sanitizeInput($_POST['bio']);
    
    // Validate inputs
    if (empty($username)) {
        setFlashMessage("Username is required.", "danger");
    } else {
        // Check if username already exists (if changed)
        $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            setFlashMessage("Username already exists.", "danger");
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET username = ?, fullname = ?, gender = ?, profession = ?, bio = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssi", $username, $fullname, $gender, $profession, $bio, $user_id);
            
            if ($update_stmt->execute()) {
                setFlashMessage("Profile updated successfully!", "success");
                // Update user data for display
                $user_data['username'] = $username;
                $user_data['fullname'] = $fullname;
                $user_data['gender'] = $gender;
                $user_data['profession'] = $profession;
                $user_data['bio'] = $bio;
            } else {
                setFlashMessage("Error updating profile: " . $conn->error, "danger");
            }
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        setFlashMessage("All password fields are required.", "danger");
        logError("Empty password fields in change password attempt for user ID: $user_id");
    } elseif ($new_password != $confirm_password) {
        setFlashMessage("New passwords do not match.", "danger");
        logError("New passwords don't match in change password attempt for user ID: $user_id");
    } elseif (strlen($new_password) < 8) {
        setFlashMessage("New password must be at least 8 characters long.", "danger");
        logError("Password too short in change password attempt for user ID: $user_id");
    } else {
        // Get current password hash from database to verify
        $check_sql = "SELECT password FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result && $result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $stored_hash = $row['password'];
            
            // Verify current password
            if (password_verify($current_password, $stored_hash)) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Password changed successfully!", "success");
                    logError("Password changed successfully for user ID: $user_id");
                    redirect("profile.php");
                } else {
                    setFlashMessage("Error changing password: " . $conn->error, "danger");
                    logError("Database error changing password for user ID: $user_id - " . $conn->error);
                }
            } else {
                setFlashMessage("Current password is incorrect.", "danger");
                logError("Incorrect current password in change password attempt for user ID: $user_id");
            }
        } else {
            setFlashMessage("Error retrieving user information.", "danger");
            logError("Error retrieving user data in change password attempt for user ID: $user_id");
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2 class="border-bottom pb-2"><?php echo $action == 'edit' ? 'Edit Profile' : 'My Profile'; ?></h2>
    </div>
    
    <?php if ($action == 'edit'): ?>
    <!-- Edit Profile Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-info" type="button" role="tab">Profile Information</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#change-password" type="button" role="tab">Change Password</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Profile Information Tab -->
                    <div class="tab-pane fade show active" id="profile-info" role="tabpanel">
                        <form action="profile.php?action=edit" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-3 text-center mb-3">
                                    <div class="position-relative mx-auto" style="width: 150px; height: 150px;">
                                        <img 
                                            src="<?php echo !empty($user_data['profile_image']) ? htmlspecialchars($user_data['profile_image']) : $default_profile; ?>" 
                                            class="img-thumbnail rounded-circle w-100 h-100 object-fit-cover" 
                                            id="profile-preview"
                                            alt="Profile"
                                            style="cursor: pointer;"
                                            onclick="document.getElementById('profile_image').click();"
                                        >
                                        <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;" onclick="document.getElementById('profile_image').click();">
                                            <i class="fas fa-camera"></i>
                                        </div>
                                    </div>
                                    <input type="file" id="profile_image" name="profile_image" class="d-none" accept="image/*" onchange="previewAndSubmitImage(this)">
                                    <small class="text-muted d-block mt-2">Click on the image to change your profile picture</small>
                                </div>
                                <div class="col-md-9">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="username" class="form-label">Username*</label>
                                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="fullname" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="male" <?php echo ($user_data['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($user_data['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo ($user_data['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                                <option value="prefer_not_to_say" <?php echo ($user_data['gender'] ?? '') == 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="profession" class="form-label">Profession</label>
                                            <input type="text" class="form-control" id="profession" name="profession" value="<?php echo htmlspecialchars($user_data['profession'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="bio" class="form-label">About Me</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                                <small class="text-muted">Tell us a little about yourself</small>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="change-password" role="tabpanel">
                        <form action="profile.php?action=edit" method="post">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password*</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password*</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="text-muted">Password should be at least 8 characters</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password*</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Profile Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Keep your profile information up to date
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Use a strong, unique password
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Profile picture should be a square image for best results
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Add your profession to customize your experience
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Profile View -->
    <div class="col-md-4">
        <div class="card text-center mb-4">
            <div class="card-body">
                <!-- Hidden form for profile image upload -->
                <form id="imageUploadForm" action="profile.php" method="post" enctype="multipart/form-data" class="d-inline">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?php echo !empty($user_data['profile_image']) ? htmlspecialchars($user_data['profile_image']) : $default_profile; ?>" 
                            class="img-thumbnail rounded-circle" 
                            style="width: 150px; height: 150px; object-fit: cover; cursor: pointer;" 
                            alt="Profile Picture" 
                            onclick="document.getElementById('profile_image_view').click();"
                            title="Click to change profile picture">
                        <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;" onclick="document.getElementById('profile_image_view').click();">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <input type="file" name="profile_image" id="profile_image_view" class="d-none" accept="image/*" onchange="previewAndSubmitImage(this)">
                    <input type="hidden" name="upload_image" value="1">
                </form>
                
                <h4 class="mb-1" style="cursor: pointer;" onclick="window.location.href='profile.php?action=edit';"><?php echo htmlspecialchars($user_data['fullname'] ?? $user_data['username']); ?></h4>
                <?php if (!empty($user_data['profession'])): ?>
                    <p class="text-muted"><?php echo htmlspecialchars($user_data['profession']); ?></p>
                <?php endif; ?>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="profile.php?action=edit" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </a>
                </div>
                <small class="text-muted mt-2 d-block">Click on your image or name to edit</small>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Account Information</h5>
                <a href="profile.php?action=edit" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i>
                </a>
            </div>
            <div class="card-body">
                <p><strong><i class="fas fa-user me-2"></i>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                <p><strong><i class="fas fa-envelope me-2"></i>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?> <small class="text-muted">(cannot be changed)</small></p>
                <?php if (!empty($user_data['gender'])): ?>
                    <p><strong><i class="fas fa-venus-mars me-2"></i>Gender:</strong> <?php echo htmlspecialchars(ucfirst($user_data['gender'])); ?></p>
                <?php endif; ?>
                <p><strong><i class="fas fa-calendar-alt me-2"></i>Member Since:</strong> <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if (!empty($user_data['bio'])): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">About Me</h5>
                <a href="profile.php?action=edit" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i>
                </a>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($user_data['bio'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Financial Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <h3 class="text-success"><?php 
                                $currentMonth = date('Y-m-01');
                                $lastDay = date('Y-m-t');
                                echo number_format(getTotalIncome($conn, $user_id, $currentMonth, $lastDay), 2); 
                            ?></h3>
                            <p class="text-muted">Monthly Income</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <h3 class="text-danger"><?php 
                                echo number_format(getTotalExpense($conn, $user_id, $currentMonth, $lastDay), 2); 
                            ?></h3>
                            <p class="text-muted">Monthly Expenses</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <?php
                                $sql = "SELECT COUNT(*) as count FROM transactions WHERE user_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $transactions_count = $stmt->get_result()->fetch_assoc()['count'];
                            ?>
                            <h3><?php echo $transactions_count; ?></h3>
                            <p class="text-muted">Transactions</p>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <a href="dashboard.php" class="btn btn-outline-primary">View Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for profile image preview and automatic submission -->
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var preview = document.getElementById('profile-preview');
            if (preview) {
                preview.src = e.target.result;
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function previewAndSubmitImage(input) {
    if (input.files && input.files[0]) {
        // Validate file type and size
        var fileType = input.files[0].type;
        var fileSize = input.files[0].size;
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        var maxSize = 5 * 1024 * 1024; // 5MB
        
        if (allowedTypes.indexOf(fileType) === -1) {
            alert('Only JPG, PNG and GIF images are allowed.');
            input.value = '';
            return false;
        }
        
        if (fileSize > maxSize) {
            alert('Image size should be less than 5MB.');
            input.value = '';
            return false;
        }
        
        var reader = new FileReader();
        
        reader.onload = function(e) {
            // Preview if preview element exists
            var preview = document.getElementById('profile-preview');
            if (preview) {
                preview.src = e.target.result;
            }
            
            // Also update preview in view mode if exists
            var viewPreview = document.querySelector('.img-thumbnail.rounded-circle');
            if (viewPreview && viewPreview !== preview) {
                viewPreview.src = e.target.result;
            }
            
            // Submit the form automatically
            setTimeout(function() {
                input.form.submit();
            }, 500);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?> 