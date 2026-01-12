-- MZ Profit BD Database Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS mz_profit_bd;
USE mz_profit_bd;

-- Users Table
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  role ENUM('investor', 'company', 'admin') DEFAULT 'investor',
  nid VARCHAR(20) UNIQUE,
  nid_verified BOOLEAN DEFAULT FALSE,
  trade_license VARCHAR(50),
  license_verified BOOLEAN DEFAULT FALSE,
  wallet_balance DECIMAL(10, 2) DEFAULT 0.00,
  account_status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
  last_ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Transactions Table (Deposits/Withdrawals)
CREATE TABLE transactions (
  trx_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  trx_type ENUM('deposit', 'withdrawal', 'earning') DEFAULT 'deposit',
  payment_method ENUM('bkash', 'nagad', 'system') DEFAULT 'bkash',
  payment_trx_id VARCHAR(50),
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  admin_notes TEXT,
  verified_by INT,
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Ads Table
CREATE TABLE ads (
  ad_id INT PRIMARY KEY AUTO_INCREMENT,
  company_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  target_url VARCHAR(500) NOT NULL,
  reward_value DECIMAL(5, 2) DEFAULT 0.16,
  daily_limit INT DEFAULT 5,
  total_budget DECIMAL(10, 2),
  spent_budget DECIMAL(10, 2) DEFAULT 0.00,
  clicks_count INT DEFAULT 0,
  status ENUM('active', 'paused', 'completed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX (status),
  INDEX (company_id)
);

-- Click Logs Table
CREATE TABLE click_logs (
  click_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  ad_id INT NOT NULL,
  session_id VARCHAR(100),
  server_completed BOOLEAN DEFAULT FALSE,
  clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  user_ip_address VARCHAR(45),
  click_date DATE GENERATED ALWAYS AS (DATE(clicked_at)) STORED,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (ad_id) REFERENCES ads(ad_id) ON DELETE CASCADE,
  INDEX (user_id, click_date),
  INDEX (ad_id),
  UNIQUE KEY unique_daily_click (user_id, ad_id, click_date)
);

-- Daily Ad Tracking (for quick daily limit checks)
CREATE TABLE daily_ad_tracking (
  tracking_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  ad_date DATE NOT NULL,
  clicks_today INT DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_date (user_id, ad_date)
);

-- Campaign Cards (Companies raising funds)
CREATE TABLE campaigns (
  campaign_id INT PRIMARY KEY AUTO_INCREMENT,
  company_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  target_amount DECIMAL(10, 2) NOT NULL,
  raised_amount DECIMAL(10, 2) DEFAULT 0.00,
  category VARCHAR(100),
  status ENUM('active', 'closed', 'completed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deadline DATE,
  FOREIGN KEY (company_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX (status)
);

-- Investments (Investors investing in campaigns)
CREATE TABLE investments (
  investment_id INT PRIMARY KEY AUTO_INCREMENT,
  investor_id INT NOT NULL,
  campaign_id INT NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  status ENUM('pending', 'approved', 'refunded') DEFAULT 'pending',
  invested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (investor_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
  INDEX (investor_id),
  INDEX (campaign_id)
);

-- Admin Logs (Audit trail)
CREATE TABLE admin_logs (
  log_id INT PRIMARY KEY AUTO_INCREMENT,
  admin_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT,
  target_user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (target_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX (admin_id),
  INDEX (created_at)
);

-- Create Indexes for Performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_transactions_user_status ON transactions(user_id, status);
CREATE INDEX idx_ads_company ON ads(company_id);
CREATE INDEX idx_click_logs_user ON click_logs(user_id);
