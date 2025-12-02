SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS barangay_inventory;
CREATE DATABASE barangay_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barangay_inventory;

-- =========================
-- Roles
-- =========================
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- Users
-- =========================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(150) NOT NULL,
  email VARCHAR(150),
  contact VARCHAR(50),
  address VARCHAR(255),
  avatar VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================
-- Equipment master
-- =========================
CREATE TABLE equipment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  category VARCHAR(100),
  total_quantity INT NOT NULL DEFAULT 0,
  available_quantity INT NOT NULL DEFAULT 0,
  `condition` ENUM('Good','Fair','Damaged') DEFAULT 'Good',
  location VARCHAR(150),
  photo VARCHAR(255),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Equipment photos
-- =========================
CREATE TABLE equipment_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  caption VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Maintenance logs
-- =========================
CREATE TABLE maintenance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT NOT NULL,
  action ENUM('Checked','Repaired','Serviced','Marked Damaged') NOT NULL,
  remarks TEXT,
  performed_by INT,
  performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Requests (staff-encoded)
-- =========================
CREATE TABLE requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(50) NOT NULL UNIQUE,
  created_by INT NOT NULL,
  borrower_name VARCHAR(150) NOT NULL,
  borrower_address VARCHAR(255),
  borrower_contact VARCHAR(80),
  borrower_id_photo VARCHAR(255),
  date_needed DATE NOT NULL,
  expected_return_date DATE NOT NULL,
  status ENUM('Pending','Approved','Declined','On Hold','Converted') DEFAULT 'Pending',
  remarks TEXT,
  approver_id INT DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Request items
-- =========================
CREATE TABLE request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  equipment_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_condition ENUM('Good','Fair','Damaged') DEFAULT 'Good',
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================
-- Borrowing transactions
-- =========================
CREATE TABLE borrowings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  borrowing_no VARCHAR(60) NOT NULL UNIQUE,
  request_id INT NULL,
  borrower_name VARCHAR(150) NOT NULL,
  borrower_contact VARCHAR(80),
  borrower_address VARCHAR(255),
  issued_by INT,
  approved_by INT,
  date_borrowed DATETIME DEFAULT CURRENT_TIMESTAMP,
  expected_return_date DATETIME,
  actual_return_date DATETIME NULL,
  status ENUM('Active','Returned','Overdue','Returned Damaged','Under Repair') DEFAULT 'Active',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE SET NULL,
  FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Borrowing items
-- =========================
CREATE TABLE borrowing_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  borrowing_id INT NOT NULL,
  equipment_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  condition_out ENUM('Good','Fair','Damaged') DEFAULT 'Good',
  condition_in ENUM('Good','Fair','Damaged') DEFAULT NULL,
  damage_photos VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON DELETE CASCADE,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================
-- Return photos
-- =========================
CREATE TABLE return_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  borrowing_item_id INT NOT NULL,
  filename VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (borrowing_item_id) REFERENCES borrowing_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Reports
-- =========================
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  type VARCHAR(50),
  params TEXT,
  generated_by INT,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  path VARCHAR(255),
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Audit log
-- =========================
CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100),
  resource_type VARCHAR(100),
  resource_id VARCHAR(100),
  details TEXT,
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Settings
-- =========================
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) UNIQUE,
  key_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- Backups
-- =========================
CREATE TABLE backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255),
  size_bytes BIGINT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- Indexes
-- =========================
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_borrowings_status ON borrowings(status);

SET FOREIGN_KEY_CHECKS=1;