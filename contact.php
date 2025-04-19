<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$success_message = '';
$error_message = '';

// Process contact form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contact_submit'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // Simple validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // In a real application, you would send an email here
        // For demo purposes, we'll just show a success message
        $success_message = "Thank you for your message! We'll get back to you soon.";
        
        // Clear form data after successful submission
        $name = $email = $subject = $message = '';
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">Contact Us</h1>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <p class="mb-4">Have questions, feedback, or need assistance with FinMate? We'd love to hear from you! Fill out the form below, and our team will get back to you as soon as possible.</p>
                    
                    <form method="post" action="contact.php">
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? $name : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                            <div class="form-text">We'll never share your email with anyone else.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="" selected disabled>Please select a topic</option>
                                <option value="General Inquiry" <?php if(isset($subject) && $subject == 'General Inquiry') echo 'selected'; ?>>General Inquiry</option>
                                <option value="Technical Support" <?php if(isset($subject) && $subject == 'Technical Support') echo 'selected'; ?>>Technical Support</option>
                                <option value="Feature Request" <?php if(isset($subject) && $subject == 'Feature Request') echo 'selected'; ?>>Feature Request</option>
                                <option value="Bug Report" <?php if(isset($subject) && $subject == 'Bug Report') echo 'selected'; ?>>Bug Report</option>
                                <option value="Account Issues" <?php if(isset($subject) && $subject == 'Account Issues') echo 'selected'; ?>>Account Issues</option>
                                <option value="Other" <?php if(isset($subject) && $subject == 'Other') echo 'selected'; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message) ? $message : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="contact_submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-envelope me-2 text-primary"></i> Email</h6>
                        <p>support@finmate.example.com</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-phone me-2 text-primary"></i> Phone</h6>
                        <p>+91 98765 43210</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-map-marker-alt me-2 text-primary"></i> Address</h6>
                        <p>
                            FinMate Headquarters<br>
                            123 Financial Street<br>
                            Tech Park, Mumbai 400001<br>
                            India
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Business Hours</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span>Monday - Friday:</span>
                        <span>9:00 AM - 6:00 PM</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Saturday:</span>
                        <span>10:00 AM - 2:00 PM</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Sunday:</span>
                        <span>Closed</span>
                    </div>
                    <hr>
                    <p class="small text-muted">All times are in Indian Standard Time (IST).</p>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Connect With Us</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-around">
                        <a href="#" class="text-primary fs-4"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-info fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-danger fs-4"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-primary fs-4"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Frequently Asked Questions</h5>
                    <p>Before contacting us, you might want to check our <a href="faq.php">FAQ page</a> to see if your question has already been answered.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 