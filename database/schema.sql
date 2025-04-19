-- Student Finance Database Schema

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS student_finance;
USE student_finance;

-- Users table with enhanced profile fields
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100),
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    profession VARCHAR(100),
    bio TEXT,
    profile_image VARCHAR(255) DEFAULT 'uploads/profiles/default.png',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    role ENUM('user', 'admin') DEFAULT 'user',
    preferences JSON,
    allowance_day INT(2) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories table with enhanced constraints
CREATE TABLE IF NOT EXISTS categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11),
    name VARCHAR(50) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    icon VARCHAR(50),
    image_path VARCHAR(255),
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category (user_id, name, type)
) ENGINE=InnoDB;

-- Transactions table with additional metadata
CREATE TABLE IF NOT EXISTS transactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    category_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    payment_method VARCHAR(50),
    location VARCHAR(100),
    is_recurring BOOLEAN DEFAULT FALSE,
    receipt_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Budgets table with enhanced period options
CREATE TABLE IF NOT EXISTS budgets (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    category_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    period ENUM('weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    alert_threshold INT DEFAULT 80,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Goals table with enhanced tracking
CREATE TABLE IF NOT EXISTS goals (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(10,2) NOT NULL,
    current_amount DECIMAL(10,2) DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('active', 'completed', 'canceled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications table for user alerts
CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insights table for storing spending insights
CREATE TABLE IF NOT EXISTS insights (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    insight_text TEXT NOT NULL,
    insight_type VARCHAR(50) DEFAULT 'spending',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Savings tips table
CREATE TABLE IF NOT EXISTS savings_tips (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tip_text TEXT NOT NULL,
    category_id INT(11),
    tip_type ENUM('general', 'student', 'category-specific') DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Quick saves table for tracking goal contributions
CREATE TABLE IF NOT EXISTS quick_saves (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    goal_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    save_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default categories data with icons and colors
INSERT INTO categories (name, type, user_id, is_system, color, icon) VALUES
    ('Allowance', 'income', NULL, TRUE, '#28a745', 'fa-money-bill-wave'),
    ('Scholarship', 'income', NULL, TRUE, '#20c997', 'fa-award'),
    ('Part-time Job', 'income', NULL, TRUE, '#17a2b8', 'fa-briefcase'),
    ('Student Loan', 'income', NULL, TRUE, '#6610f2', 'fa-university'),
    ('Parents Support', 'income', NULL, TRUE, '#e83e8c', 'fa-hand-holding-heart'),
    ('Gift', 'income', NULL, TRUE, '#e83e8c', 'fa-gift'),
    ('Other Income', 'income', NULL, TRUE, '#6c757d', 'fa-wallet'),
    ('Campus Food', 'expense', NULL, TRUE, '#dc3545', 'fa-utensils'),
    ('Rent', 'expense', NULL, TRUE, '#fd7e14', 'fa-home'),
    ('Utilities', 'expense', NULL, TRUE, '#ffc107', 'fa-bolt'),
    ('Entertainment', 'expense', NULL, TRUE, '#6f42c1', 'fa-film'),
    ('Transportation', 'expense', NULL, TRUE, '#007bff', 'fa-bus'),
    ('Healthcare', 'expense', NULL, TRUE, '#20c997', 'fa-heart'),
    ('Textbooks', 'expense', NULL, TRUE, '#17a2b8', 'fa-book'),
    ('Course Materials', 'expense', NULL, TRUE, '#17a2b8', 'fa-pencil-alt'),
    ('Student Events', 'expense', NULL, TRUE, '#6f42c1', 'fa-calendar-day'),
    ('Clothing', 'expense', NULL, TRUE, '#e83e8c', 'fa-tshirt'),
    ('Savings', 'expense', NULL, TRUE, '#28a745', 'fa-piggy-bank'),
    ('Subscriptions', 'expense', NULL, TRUE, '#6c757d', 'fa-repeat'),
    ('Coffee', 'expense', NULL, TRUE, '#c45850', 'fa-coffee'),
    ('Dining Out', 'expense', NULL, TRUE, '#dc3545', 'fa-hamburger'),
    ('Other Expense', 'expense', NULL, TRUE, '#6c757d', 'fa-receipt');

-- Student-specific savings tips
INSERT INTO savings_tips (tip_text, tip_type) VALUES
    ('Look for used textbooks or digital versions to save money on course materials.', 'student'),
    ('Use your student ID for discounts on transportation, food, and entertainment.', 'student'),
    ('Take advantage of free campus resources like gym, events, and workshops.', 'student'),
    ('Cook meals at home instead of eating at the campus cafeteria every day.', 'student'),
    ('Share subscriptions like Netflix or Spotify with roommates using family plans.', 'student'),
    ('Check if your university offers free software that you might be paying for.', 'student'),
    ('Apply for scholarships and grants, even small ones add up over time.', 'student'),
    ('Use campus printers for free or reduced-cost printing instead of buying a printer.', 'student');

-- General savings tips
INSERT INTO savings_tips (tip_text, tip_type) VALUES
    ('Try the 50/30/20 rule: 50% for needs, 30% for wants, and 20% for savings.', 'general'),
    ('Track every expense for a month to identify areas where you can cut back.', 'general'),
    ('Set up automatic transfers to your savings account right after you get paid.', 'general'),
    ('Wait 24 hours before making any non-essential purchase over ₹50.', 'general'),
    ('Look for student discounts on software, services, and subscriptions.', 'general');

-- Indexes for better performance
CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_transactions_category ON transactions(category_id);
CREATE INDEX idx_transactions_date ON transactions(transaction_date);
CREATE INDEX idx_transactions_payment_method ON transactions(payment_method);
CREATE INDEX idx_transactions_recurring ON transactions(is_recurring);
CREATE INDEX idx_budgets_user ON budgets(user_id);
CREATE INDEX idx_budgets_period ON budgets(period);
CREATE INDEX idx_budgets_active ON budgets(is_active);
CREATE INDEX idx_goals_user ON goals(user_id);
CREATE INDEX idx_goals_status ON goals(status);
CREATE INDEX idx_goals_end_date ON goals(end_date);
CREATE INDEX idx_categories_user ON categories(user_id);
CREATE INDEX idx_categories_type ON categories(type);
CREATE INDEX idx_categories_system ON categories(is_system);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_users_role ON users(role); 