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
  status ENUM('Active','Returned','Overdue','Damaged','Under Repair') DEFAULT 'Active',
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





/* ===========================================================
   🚀 SAMPLE DATA INSERTS (100% VALID — NO FK ERRORS)
   =========================================================== */

-- 1. Roles
INSERT INTO roles (name, description) VALUES
('Admin', 'System administrator'),
('Staff', 'Regular staff'),
('Borrower', 'Barangay resident borrower');

-- 2. Users
INSERT INTO users (role_id, username, password, fullname, email, contact, address, avatar)
VALUES


(1, 'admin', 'admin123', 'System Administrator', 'admin@example.com', '09123456789', 'Barangay Iba', NULL),
(2, 'staff1', 'password1', 'Inventory Staff One', 'staff1@example.com', '09998887777', 'Barangay Iba', NULL);

-- 3. Equipment
INSERT INTO equipment (code, name, description, category, total_quantity, available_quantity, `condition`, location, created_by)
VALUES
('EQ-001', 'Portable Speaker', 'High-power outdoor speaker', 'Audio', 5, 5, 'Good', 'Storage Room A', 1),
('EQ-002', 'Plastic Chair', 'Monoblock chair', 'Furniture', 50, 50, 'Good', 'Storage Room B', 1),
('EQ-003', 'Tent 10x10', 'Event tent', 'Shelter', 3, 3, 'Good', 'Storage Room C', 2);

-- 4. Equipment Photos
INSERT INTO equipment_photos (equipment_id, filename, caption)
VALUES
(1, 'speaker1.jpg', 'Front view'),
(2, 'chair1.jpg', 'Stacked chairs'),
(3, 'tent1.jpg', 'Sample setup');

-- 5. Maintenance Logs
INSERT INTO maintenance_logs (equipment_id, action, remarks, performed_by)
VALUES
(1, 'Checked', 'All parts working', 2),
(2, 'Serviced', 'Cleaned and sanitized', 1);

-- 6. Requests
INSERT INTO requests (request_no, created_by, borrower_name, borrower_address, borrower_contact, date_needed, expected_return_date, status, remarks)
VALUES
('REQ-0001', 1, 'Juan Dela Cruz', 'Barangay Iba', '09192223333', '2025-02-10', '2025-02-12', 'Pending', 'For barangay meeting'),
('REQ-0002', 2, 'Maria Santos', 'Barangay Iba', '09221114444', '2025-02-15', '2025-02-16', 'Approved', 'For event use');

-- 7. Request Items
INSERT INTO request_items (request_id, equipment_id, quantity, unit_condition)
VALUES
(1, 2, 10, 'Good'),
(2, 1, 1, 'Good');

-- 8. Borrowings
INSERT INTO borrowings (borrowing_no, request_id, borrower_name, borrower_contact, borrower_address, issued_by, approved_by, expected_return_date)
VALUES
('BRW-0001', 2, 'Maria Santos', '09221114444', 'Barangay Iba', 2, 1, '2025-02-16');

-- 9. Borrowing Items
INSERT INTO borrowing_items (borrowing_id, equipment_id, quantity, condition_out)
VALUES
(1, 1, 1, 'Good');

-- 10. Return Photos
INSERT INTO return_photos (borrowing_item_id, filename)
VALUES
(1, 'speaker_return.jpg');

-- 11. Reports
INSERT INTO reports (name, type, params, generated_by, path)
VALUES
('Monthly Borrowing Report', 'PDF', '{"month":"February"}', 1, 'reports/monthly_feb.pdf');

-- 12. Audit Logs
INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address)
VALUES
(1, 'Create Request', 'Request', '1', 'Request REQ-0001 created', '127.0.0.1'),
(2, 'Approve Request', 'Request', '2', 'Request REQ-0002 approved', '127.0.0.1');

-- 13. Settings
INSERT INTO settings (key_name, key_value)
VALUES
('site_name', 'Barangay Inventory System'),
('borrow_limit', '150');

-- 14. Backups
INSERT INTO backups (filename, size_bytes)
VALUES
('backup_2025_02_01.sql', 204800);
