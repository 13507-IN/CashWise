<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">Privacy Policy</h1>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Last updated: <?php echo date('F d, Y'); ?></p>
                    
                    <div class="mb-4">
                        <h4>Introduction</h4>
                        <p>FinMate ("we", "our", or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our financial management application FinMate ("Application"). Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the Application.</p>
                        <p>We reserve the right to make changes to this Privacy Policy at any time and for any reason. We will alert you about any changes by updating the "Last updated" date of this privacy policy. Any changes or modifications will be effective immediately upon posting the updated Privacy Policy on the Application, and you waive the right to receive specific notice of each such change or modification.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Collection of Your Information</h4>
                        <p>We may collect information about you in a variety of ways. The information we may collect via the Application includes:</p>
                        
                        <h5>Personal Data</h5>
                        <p>Personally identifiable information, such as your name, email address, and password, that you voluntarily provide when registering with the Application. The personal information collected is used to identify you within the Application and to provide our services to you.</p>
                        
                        <h5>Financial Data</h5>
                        <p>We collect financial information that you manually enter into the Application, such as transaction details, income, expenses, budgets, and savings goals. This data is used to provide you with our core financial management services.</p>
                        
                        <h5>Derivative Data</h5>
                        <p>Information our servers automatically collect when you access the Application, such as your IP address, browser type, operating system, access times, and the pages you have viewed directly before and after accessing the Application.</p>
                        
                        <h5>Mobile Device Data</h5>
                        <p>Device information, such as your mobile device ID, model, and manufacturer, and information about the location of your device, if you access the Application from a mobile device.</p>
                        
                        <h5>Cookies and Web Beacons</h5>
                        <p>We may use cookies, web beacons, tracking pixels, and other tracking technologies to help customize the Application and improve your experience. For more information on how we use cookies, please refer to our Cookie Policy section below.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Use of Your Information</h4>
                        <p>Having accurate information about you permits us to provide you with a smooth, efficient, and customized experience. Specifically, we may use information collected about you via the Application to:</p>
                        <ul>
                            <li>Create and manage your account.</li>
                            <li>Provide and manage the services you request.</li>
                            <li>Process your financial data to generate insights, reports, and recommendations.</li>
                            <li>Email you regarding your account or activities in the Application.</li>
                            <li>Send you service updates and important notices.</li>
                            <li>Monitor and analyze usage and trends to improve your experience with the Application.</li>
                            <li>Increase the efficiency and operation of the Application.</li>
                            <li>Resolve disputes and troubleshoot problems.</li>
                            <li>Prevent fraudulent transactions and monitor against theft.</li>
                            <li>Generate a personal profile about you to make future visits to the Application more personalized.</li>
                            <li>Request feedback and contact you about your use of the Application.</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Disclosure of Your Information</h4>
                        <p>We may share information we have collected about you in certain situations. Your information may be disclosed as follows:</p>
                        
                        <h5>By Law or to Protect Rights</h5>
                        <p>If we believe the release of information about you is necessary to respond to legal process, to investigate or remedy potential violations of our policies, or to protect the rights, property, and safety of others, we may share your information as permitted or required by any applicable law, rule, or regulation.</p>
                        
                        <h5>Third-Party Service Providers</h5>
                        <p>We may share your information with third parties that perform services for us or on our behalf, including payment processing, data analysis, email delivery, hosting services, customer service, and marketing assistance.</p>
                        
                        <h5>Marketing Communications</h5>
                        <p>With your consent, or with an opportunity for you to withdraw consent, we may share your information with third parties for marketing purposes, as permitted by law.</p>
                        
                        <h5>Business Transfers</h5>
                        <p>If we are involved in a merger, acquisition, or sale of all or a portion of our assets, your information may be transferred as part of that transaction. We will notify you via email and/or a prominent notice on our Application of any change in ownership or uses of your information, as well as any choices you may have regarding your information.</p>
                        
                        <h5>Affiliates</h5>
                        <p>We may share your information with our affiliates, in which case we will require those affiliates to honor this Privacy Policy.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Security of Your Information</h4>
                        <p>We use administrative, technical, and physical security measures to help protect your personal information. While we have taken reasonable steps to secure the personal information you provide to us, please be aware that despite our efforts, no security measures are perfect or impenetrable, and no method of data transmission can be guaranteed against any interception or other type of misuse.</p>
                        <p>Any information disclosed online is vulnerable to interception and misuse by unauthorized parties. Therefore, we cannot guarantee complete security if you provide personal information.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Policy for Children</h4>
                        <p>We do not knowingly solicit information from or market to children under the age of 13. If you become aware of any data we have collected from children under age 13, please contact us using the contact information provided below.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Your Rights and Choices</h4>
                        <p>You may at any time review or change the information in your account or terminate your account by:</p>
                        <ul>
                            <li>Logging into your account settings and updating your account</li>
                            <li>Contacting us using the contact information provided below</li>
                        </ul>
                        <p>Upon your request to terminate your account, we will deactivate or delete your account and information from our active databases. However, some information may be retained in our files to prevent fraud, troubleshoot problems, assist with any investigations, enforce our Terms of Use and/or comply with legal requirements.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Cookie Policy</h4>
                        <p>Cookies are small files that a site or its service provider transfers to your computer's hard drive through your Web browser (if you allow) that enables the site's or service provider's systems to recognize your browser and capture and remember certain information.</p>
                        <p>We use cookies to:</p>
                        <ul>
                            <li>Help remember and process your login session.</li>
                            <li>Understand and save user's preferences for future visits.</li>
                            <li>Compile aggregate data about site traffic and site interactions in order to offer better site experiences and tools in the future.</li>
                        </ul>
                        <p>You can choose to have your computer warn you each time a cookie is being sent, or you can choose to turn off all cookies. You do this through your browser settings. Since each browser is a little different, look at your browser's Help Menu to learn the correct way to modify your cookies.</p>
                        <p>If you turn cookies off, some features will be disabled. It may affect the user experience and some of our services will not function properly.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h4>Contact Us</h4>
                        <p>If you have questions or comments about this Privacy Policy, please contact us at:</p>
                        <address>
                            FinMate<br>
                            123 Financial Street<br>
                            Sector-V, Kolkata - 700031<br>
                            India<br>
                            Email: finmate@gmail.com<br>
                            Phone: +91 34567 34567
                        </address>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 