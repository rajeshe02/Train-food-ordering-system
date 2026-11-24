-- ============================================================
--  Train Food Ordering System — MySQL Schema
--  File: database/schema.sql
--  Run once to set up all tables
-- ============================================================

CREATE DATABASE IF NOT EXISTS train_food_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE train_food_db;

-- ============================================================
-- 1. USERS (passengers)
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    phone       VARCHAR(15)         NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,          -- bcrypt hash
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. VENDORS (food stalls / restaurants at stations)
-- ============================================================
CREATE TABLE vendors (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150)        NOT NULL,
    station_name VARCHAR(100)        NOT NULL,
    station_code VARCHAR(10)         NOT NULL,
    email        VARCHAR(150)        NOT NULL UNIQUE,
    phone        VARCHAR(15)         NOT NULL,
    password     VARCHAR(255)        NOT NULL,          -- bcrypt hash
    is_active    TINYINT(1)          DEFAULT 1,
    created_at   TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. ADMINS
-- ============================================================
CREATE TABLE admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 4. MENU CATEGORIES
-- ============================================================
CREATE TABLE menu_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id   INT          NOT NULL,
    name        VARCHAR(100) NOT NULL,               -- e.g. Breakfast, Snacks
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- ============================================================
-- 5. MENU ITEMS
-- ============================================================
CREATE TABLE menu_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id    INT             NOT NULL,
    category_id  INT             NOT NULL,
    name         VARCHAR(150)    NOT NULL,
    description  TEXT,
    price        DECIMAL(8,2)    NOT NULL,
    image        VARCHAR(255),                        -- path to image file
    is_veg       TINYINT(1)      DEFAULT 1,
    is_available TINYINT(1)      DEFAULT 1,
    created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)   REFERENCES vendors(id)         ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
);

-- ============================================================
-- 6. ORDERS
-- ============================================================
CREATE TABLE orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    vendor_id       INT             NOT NULL,
    train_number    VARCHAR(10)     NOT NULL,
    pnr_number      VARCHAR(15)     NOT NULL,
    seat_number     VARCHAR(10)     NOT NULL,
    coach_number    VARCHAR(10)     NOT NULL,
    delivery_station VARCHAR(100)   NOT NULL,
    total_amount    DECIMAL(10,2)   NOT NULL,
    payment_method  ENUM('COD','UPI','CARD') DEFAULT 'COD',
    payment_status  ENUM('pending','paid','failed') DEFAULT 'pending',
    status          ENUM(
                      'placed',
                      'confirmed',
                      'preparing',
                      'out_for_delivery',
                      'delivered',
                      'cancelled'
                    ) DEFAULT 'placed',
    special_note    TEXT,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- ============================================================
-- 7. ORDER ITEMS (line items inside each order)
-- ============================================================
CREATE TABLE order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT             NOT NULL,
    menu_item_id INT             NOT NULL,
    quantity     INT             NOT NULL DEFAULT 1,
    unit_price   DECIMAL(8,2)   NOT NULL,             -- price at time of order
    subtotal     DECIMAL(10,2)  NOT NULL,
    FOREIGN KEY (order_id)     REFERENCES orders(id)     ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- ============================================================
-- 8. PAYMENTS
-- ============================================================
CREATE TABLE payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT             NOT NULL UNIQUE,
    amount          DECIMAL(10,2)   NOT NULL,
    method          ENUM('COD','UPI','CARD') NOT NULL,
    transaction_id  VARCHAR(100),                     -- from payment gateway
    status          ENUM('pending','success','failed') DEFAULT 'pending',
    paid_at         TIMESTAMP       NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
-- 9. ORDER STATUS LOG (history of every status change)
-- ============================================================
CREATE TABLE order_status_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT          NOT NULL,
    status     VARCHAR(50)  NOT NULL,
    changed_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
-- 10. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- INDEXES (for faster queries)
-- ============================================================
CREATE INDEX idx_orders_user      ON orders(user_id);
CREATE INDEX idx_orders_vendor    ON orders(vendor_id);
CREATE INDEX idx_orders_status    ON orders(status);
CREATE INDEX idx_menu_vendor      ON menu_items(vendor_id);
CREATE INDEX idx_status_log_order ON order_status_log(order_id);

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- password = admin123 (bcrypt — change immediately after setup)
-- ============================================================
INSERT INTO admins (name, email, password) VALUES (
  'Super Admin',
  'admin@trainfoods.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);
