-- =============================================================================
-- HQ Laptop — قاعدة البيانات الكاملة (MySQL / MariaDB)
-- =============================================================================
-- هذا الملف وحده يكفي لإنشاء المشروع من الصفر بعد حذف القاعدة القديمة.
-- لا حاجة لملفات migration إن نفّذت هذا الملف كاملًا من البداية للنهاية.
--
-- ┌─────────────────────────────────────────────────────────────────────────┐
-- │ خطوات التنفيذ (phpMyAdmin)                                              │
-- ├─────────────────────────────────────────────────────────────────────────┤
-- │ 1) في phpMyAdmin اختر القاعدة: if0_41646885_hq_laptop (أو أنشئها من      │
-- │    لوحة الاستضافة إن لزم).                                               │
-- │ 2) تبويب SQL → انسخ هذا الملف كاملًا → تنفيذ (Go).                       │
-- └─────────────────────────────────────────────────────────────────────────┘
--
-- من الطرفية (من أي مجلد):
--   mysql -u root -p < /Applications/XAMPP/xamppfiles/htdocs/hqlaptop/database/schema.sql
--
-- الاتصال من المشروع: backend/db.php (اسم القاعدة الافتراضي if0_41646885_hq_laptop).
--
-- ┌─────────────────────────────────────────────────────────────────────────┐
-- │ الجداول الثلاثة وعلاقتها                                                │
-- ├─────────────────────────────────────────────────────────────────────────┤
-- │  products        ← منتجات المتجر (سعر بالدينار د.ج، مخزون، صورة، حالة)   │
-- │       ↑                                                                  │
-- │       │ product_id (FK، RESTRICT عند حذف المنتج إن وُجدت طلبات)         │
-- │       │                                                                  │
-- │  order_items   ← بنود كل طلب (عدة صفوف لنفس order_id)                   │
-- │       ↑                                                                  │
-- │       │ order_id (FK، CASCADE: حذف الطلب يحذف البنود)                   │
-- │       │                                                                  │
-- │  orders        ← طلب واحد = عميل + عنوان + إجمالي + حالة + طريقة دفع  │
-- └─────────────────────────────────────────────────────────────────────────┘
--
-- رفع الصور: الملف على القرص؛ الحقل image_path يخزّن مسارًا نسبيًا فقط.
-- إن فشل الرفع فالسبب صلاحيات مجلد assets/images/uploads وليس هذا الملف.
--
-- =============================================================================

-- إعادة بناء تلقائية (محلي XAMPP فقط — غالبًا ممنوع على استضافة مشتركة):
-- DROP DATABASE IF EXISTS if0_41646885_hq_laptop;
-- CREATE DATABASE IF NOT EXISTS if0_41646885_hq_laptop
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE if0_41646885_hq_laptop;

-- =============================================================================
-- 1) products — المنتجات
-- =============================================================================
-- slug           معرّف فريد للروابط (إنجليزي عادةً).
-- price          بالدينار الجزائري (د.ج) — عرض فقط في الواجهة.
-- image_path     مثل assets/images/placeholder-laptop.svg أو uploads/...
-- status         available = يظهر في المتجر مع is_active=1 | sold = مباع
-- is_active      0 = مخفي عن المتجر حتى لو available
-- =============================================================================

CREATE TABLE products (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(180) NOT NULL COMMENT 'اسم العرض',
  slug            VARCHAR(200) NOT NULL COMMENT 'فريد في الموقع',
  description     TEXT NULL,
  price           DECIMAL(10, 2) NOT NULL COMMENT 'د.ج',
  image_path      VARCHAR(255) NULL COMMENT 'مسار نسبي من جذر الموقع',
  warranty_months TINYINT UNSIGNED NOT NULL DEFAULT 6 COMMENT 'ضمان بالأشهر',
  stock           INT UNSIGNED NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'available' COMMENT 'available | sold',
  is_active       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=نشط',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_store (is_active, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2) orders — الطلبات (صف واحد = عملية شراء واحدة)
-- =============================================================================
-- total            مجموع البنود بالد.ج (يُحسب من السلة عند الإرسال).
-- status           new → completed / cancelled (من لوحة الطلبات).
-- payment_method   cod = عند التسليم | online = واجهة بطاقة تجريبية (لا بوابة حقيقية)
-- =============================================================================

CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name    VARCHAR(120) NOT NULL,
  phone            VARCHAR(40) NOT NULL,
  address          VARCHAR(500) NOT NULL,
  total            DECIMAL(10, 2) NOT NULL COMMENT 'إجمالي الطلب د.ج',
  status           VARCHAR(32) NOT NULL DEFAULT 'new' COMMENT 'new | completed | cancelled',
  payment_method   VARCHAR(32) NOT NULL DEFAULT 'cod' COMMENT 'cod | online',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orders_created (created_at),
  KEY idx_orders_status (status),
  KEY idx_orders_payment (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3) product_images — عدة صور لكل منتج
-- =============================================================================
-- المنتج الواحد يقدر يملك معرض صور، والترتيب عبر sort_order.
-- عند حذف المنتج تُحذف صوره تلقائيًا (CASCADE).
-- =============================================================================

CREATE TABLE product_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  image_path  VARCHAR(255) NOT NULL COMMENT 'مسار نسبي للصورة',
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_images_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE CASCADE,
  KEY idx_product_images_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4) order_items — بنود الطلب
-- =============================================================================
-- لكل سطر: منتج + كمية + سعر الوحدة وقت الشراء (لقطة، بالد.ج).
-- =============================================================================

CREATE TABLE order_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  product_name  VARCHAR(180) NOT NULL COMMENT 'اسم المنتج وقت الطلب',
  quantity      INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price    DECIMAL(10, 2) NOT NULL COMMENT 'سعر الوحدة د.ج وقت الطلب',
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE RESTRICT,
  KEY idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5) بيانات تجريبية — أربعة لابتوبات (يمكنك حذفها أو تعديلها من الأدمن)
-- =============================================================================

INSERT INTO products (
  name, slug, description, price, image_path, warranty_months, stock, status, is_active
) VALUES
(
  'MacBook Pro 14" M1',
  'macbook-pro-14-m1',
  'شاشة ريتينا، بطارية ممتازة، مناسب للتصميم والبرمجة.',
  2899.00,
  'assets/images/placeholder-laptop.svg',
  12,
  3,
  'available',
  1
),
(
  'Dell XPS 13',
  'dell-xps-13',
  'خفيف، إطار InfinityEdge، حالة ممتازة مع ضمان المتجر.',
  1599.00,
  'assets/images/placeholder-laptop.svg',
  6,
  5,
  'available',
  1
),
(
  'Lenovo ThinkPad T14',
  'lenovo-thinkpad-t14',
  'لوحة مفاتيح كلاسيكية، مثالي للعمل اليومي.',
  1299.00,
  'assets/images/placeholder-laptop.svg',
  6,
  4,
  'available',
  1
),
(
  'HP EliteBook 840',
  'hp-elitebook-840',
  'أمان وتشغيل سلس، تم فحصه بالكامل قبل البيع.',
  999.00,
  'assets/images/placeholder-laptop.svg',
  3,
  6,
  'available',
  1
);

-- =============================================================================
-- نهاية الملف
-- • الطلبات لا تُدرج هنا — تُنشأ من checkout عبر backend/create_order.php
-- • عند «تم التسليم» يُخصم المخزون من products (update_order_status.php)
-- =============================================================================
