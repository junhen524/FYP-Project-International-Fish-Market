-- =============================================================================
-- FISHERIES ECOSYSTEM — SUPABASE POSTGRESQL SCHEMA
-- PostgreSQL 15+  |  Supabase-ready  |  Django-ready  |  AI Analytics Ready
-- =============================================================================
-- Three connected systems sharing ONE centralized database:
--   1. Port Management (Core)  — owns inventory
--   2. Domestic Fish Market   — separate users/orders
--   3. International Market   — separate users/orders
-- =============================================================================

-- #############################################################################
-- SECTION 0 : EXTENSIONS
-- #############################################################################
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- #############################################################################
-- SECTION 1 : ENUM TYPES
-- #############################################################################

CREATE TYPE port_status AS ENUM ('active', 'inactive', 'maintenance', 'closed');
CREATE TYPE vessel_status AS ENUM ('docked', 'at_sea', 'returning', 'maintenance', 'decommissioned');
CREATE TYPE catch_status AS ENUM ('landed', 'inspected', 'processed', 'stored', 'rejected');
CREATE TYPE warehouse_type AS ENUM ('cold_storage', 'dry_storage', 'live_tank', 'processing');
CREATE TYPE inventory_status AS ENUM ('available', 'reserved', 'sold', 'expired', 'damaged', 'quarantined');

CREATE TYPE product_unit AS ENUM ('kg', 'g', 'piece', 'pack', 'bundle', 'box', 'tray', 'carton');
CREATE TYPE product_freshness AS ENUM ('live', 'fresh', 'chilled', 'frozen', 'processed', 'preserved');
CREATE TYPE product_category AS ENUM ('fish', 'shellfish', 'crustacean', 'mollusc', 'cephalopod', 'seaweed', 'processed', 'other');

CREATE TYPE user_role AS ENUM ('super_admin', 'port_admin', 'quality_inspector', 'logistics', 'finance', 'viewer');
CREATE TYPE customer_verification_level AS ENUM ('unverified', 'basic', 'verified', 'premium');
CREATE TYPE account_status AS ENUM ('active', 'inactive', 'suspended', 'banned');
CREATE TYPE movement_type AS ENUM ('receive', 'transfer_in', 'transfer_out', 'sale', 'return', 'damage', 'expired', 'adjustment');

CREATE TYPE order_status AS ENUM ('pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled', 'refunded');
CREATE TYPE payment_status AS ENUM ('pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded');
CREATE TYPE payment_method AS ENUM ('wallet', 'online_banking', 'card', 'cash_on_delivery', 'bank_transfer', 'crypto');

CREATE TYPE export_order_stage AS ENUM ('quote', 'deposit_paid', 'processing', 'shipping', 'customs', 'delivered', 'cancelled');
CREATE TYPE contract_status AS ENUM ('draft', 'active', 'fulfilled', 'disputed', 'terminated');

CREATE TYPE qr_status AS ENUM ('active', 'scanned', 'expired', 'revoked');

CREATE TYPE meal_type AS ENUM ('breakfast', 'lunch', 'dinner', 'appetiser', 'side', 'snack');
CREATE TYPE recipe_difficulty AS ENUM ('easy', 'medium', 'hard', 'professional');

CREATE TYPE analytics_event_type AS ENUM (
    'page_view', 'product_view', 'search', 'add_to_cart', 'checkout',
    'order_placed', 'payment_completed', 'wallet_topup', 'login',
    'export_quote', 'contract_signed', 'qr_verified'
);

-- #############################################################################
-- SECTION 2 : CORE PORT SYSTEM
-- #############################################################################

-- 2.1  PORTS
CREATE TABLE ports (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    code            VARCHAR(10) NOT NULL UNIQUE,
    country         VARCHAR(60) NOT NULL DEFAULT 'Malaysia',
    state           VARCHAR(60),
    latitude        DECIMAL(9,6),
    longitude       DECIMAL(9,6),
    status          port_status NOT NULL DEFAULT 'active',
    contact_email   VARCHAR(200),
    contact_phone   VARCHAR(30),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE ports IS 'Port management hubs — inventory ownership root';
COMMENT ON COLUMN ports.code IS 'Short unique code e.g. PKL, PEN, JHR';

-- 2.2  VESSELS
CREATE TABLE vessels (
    id              BIGSERIAL PRIMARY KEY,
    port_id         BIGINT NOT NULL REFERENCES ports(id) ON DELETE RESTRICT,
    name            VARCHAR(150) NOT NULL,
    registration_no VARCHAR(50) NOT NULL UNIQUE,
    vessel_type     VARCHAR(60),
    captain_name    VARCHAR(120),
    capacity_tonnes DECIMAL(10,2),
    status          vessel_status NOT NULL DEFAULT 'docked',
    last_docked_at  TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE vessels IS 'Registered fishing vessels per port';

-- 2.3  FISH CATCHES
CREATE TABLE fish_catches (
    id              BIGSERIAL PRIMARY KEY,
    vessel_id       BIGINT NOT NULL REFERENCES vessels(id) ON DELETE RESTRICT,
    port_id         BIGINT NOT NULL REFERENCES ports(id) ON DELETE RESTRICT,
    catch_date      DATE NOT NULL,
    landing_time    TIMESTAMPTZ,
    catch_location  VARCHAR(150),
    catch_lat       DECIMAL(9,6),
    catch_lon       DECIMAL(9,6),
    species         VARCHAR(150) NOT NULL,
    weight_kg       DECIMAL(10,2) NOT NULL CHECK (weight_kg > 0),
    quality_grade   CHAR(1) CHECK (quality_grade IN ('A','B','C','D')),
    temperature_c   DECIMAL(4,1),
    status          catch_status NOT NULL DEFAULT 'landed',
    inspector_notes TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE fish_catches IS 'Daily catch records from vessel landings';
CREATE INDEX idx_fish_catches_vessel ON fish_catches(vessel_id);
CREATE INDEX idx_fish_catches_date ON fish_catches(catch_date);
CREATE INDEX idx_fish_catches_port ON fish_catches(port_id);

-- 2.4  WAREHOUSES
CREATE TABLE warehouses (
    id              BIGSERIAL PRIMARY KEY,
    port_id         BIGINT NOT NULL REFERENCES ports(id) ON DELETE RESTRICT,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(20) NOT NULL UNIQUE,
    warehouse_type  warehouse_type NOT NULL DEFAULT 'cold_storage',
    capacity_kg     DECIMAL(12,2),
    temperature_min DECIMAL(4,1),
    temperature_max DECIMAL(4,1),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE warehouses IS 'Storage facilities at each port';
CREATE INDEX idx_warehouses_port ON warehouses(port_id);

-- #############################################################################
-- SECTION 3 : PRODUCT & INVENTORY SYSTEM (SHARED / CENTRAL)
-- #############################################################################

-- 3.1  PRODUCTS (central catalogue — shared by all markets)
CREATE TABLE products (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    name_zh         VARCHAR(200),
    slug            VARCHAR(220) NOT NULL UNIQUE,
    sku             VARCHAR(50) NOT NULL UNIQUE,
    category        product_category NOT NULL,
    freshness       product_freshness NOT NULL DEFAULT 'fresh',
    unit            product_unit NOT NULL DEFAULT 'kg',

    domestic_price  DECIMAL(10,2) NOT NULL CHECK (domestic_price >= 0),
    export_price    DECIMAL(10,2) NOT NULL CHECK (export_price >= 0),

    description     TEXT,
    origin          VARCHAR(100),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    is_featured     BOOLEAN NOT NULL DEFAULT FALSE,
    shelf_life_days SMALLINT CHECK (shelf_life_days > 0),

    -- Default warehouse linkage
    default_warehouse_id BIGINT REFERENCES warehouses(id) ON DELETE SET NULL,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE products IS 'Central product catalogue shared across all systems';
COMMENT ON COLUMN products.domestic_price IS 'Price for domestic fish market (RM)';
COMMENT ON COLUMN products.export_price IS 'Price for international market / export (RM)';
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_products_active ON products(is_active) WHERE is_active = TRUE;
CREATE INDEX idx_products_featured ON products(is_featured) WHERE is_featured = TRUE;

-- 3.2  PRODUCT IMAGES
CREATE TABLE product_images (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    url             TEXT NOT NULL,
    alt_text        VARCHAR(200),
    is_primary      BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE product_images IS 'Multiple images per product';
CREATE INDEX idx_product_images_product ON product_images(product_id);
CREATE INDEX idx_product_images_primary ON product_images(product_id, is_primary)
    WHERE is_primary = TRUE;

-- 3.3  INVENTORY (real-time stock — OWNED BY PORT SYSTEM)
CREATE TABLE inventory (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    warehouse_id    BIGINT NOT NULL REFERENCES warehouses(id) ON DELETE RESTRICT,
    quantity        DECIMAL(12,2) NOT NULL CHECK (quantity >= 0),
    reserved_qty    DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (reserved_qty >= 0),
    batch_no        VARCHAR(50),
    received_date   DATE NOT NULL DEFAULT CURRENT_DATE,
    expiry_date     DATE,
    status          inventory_status NOT NULL DEFAULT 'available',
    status_changed_at TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT inventory_non_negative
        CHECK (quantity >= reserved_qty),
    CONSTRAINT inventory_unique_batch
        UNIQUE (product_id, warehouse_id, batch_no)
);

COMMENT ON TABLE inventory IS 'Real-time stock — only Port System can modify';
COMMENT ON COLUMN inventory.reserved_qty IS 'Quantity reserved by active orders';
CREATE INDEX idx_inventory_product ON inventory(product_id);
CREATE INDEX idx_inventory_warehouse ON inventory(warehouse_id);
CREATE INDEX idx_inventory_status ON inventory(status);
CREATE INDEX idx_inventory_expiry ON inventory(expiry_date)
    WHERE expiry_date IS NOT NULL AND status != 'expired';

-- 3.4  STOCK MOVEMENTS (audit trail for all inventory changes)
CREATE TABLE stock_movements (
    id              BIGSERIAL PRIMARY KEY,
    inventory_id    BIGINT NOT NULL REFERENCES inventory(id) ON DELETE RESTRICT,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    warehouse_id    BIGINT NOT NULL REFERENCES warehouses(id) ON DELETE RESTRICT,
    movement_type   movement_type NOT NULL,
    quantity        DECIMAL(12,2) NOT NULL CHECK (quantity != 0),
    balance_before  DECIMAL(12,2) NOT NULL CHECK (balance_before >= 0),
    balance_after   DECIMAL(12,2) NOT NULL CHECK (balance_after >= 0),
    reference_type  VARCHAR(30),
    reference_id    BIGINT,
    notes           TEXT,
    created_by_type VARCHAR(20),
    created_by_id   BIGINT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE stock_movements IS 'Audit trail for every inventory quantity change';
COMMENT ON COLUMN stock_movements.reference_type IS 'e.g. fish_catch, domestic_order, export_order, adjustment';
CREATE INDEX idx_stock_movements_inventory ON stock_movements(inventory_id);
CREATE INDEX idx_stock_movements_product ON stock_movements(product_id);
CREATE INDEX idx_stock_movements_type ON stock_movements(movement_type);
CREATE INDEX idx_stock_movements_created ON stock_movements(created_at DESC);
CREATE INDEX idx_stock_movements_ref ON stock_movements(reference_type, reference_id)
    WHERE reference_type IS NOT NULL;

-- #############################################################################
-- SECTION 4 : USERS (SEPARATE PER MARKET)
-- #############################################################################

-- 4.1  DOMESTIC USERS
CREATE TABLE domestic_users (
    id              BIGSERIAL PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    ic_number       VARCHAR(20),
    ic_last_6       VARCHAR(6) GENERATED ALWAYS AS
                        (RIGHT(COALESCE(ic_number, ''), 6)) STORED,
    address_line1   VARCHAR(255),
    address_line2   VARCHAR(255),
    city            VARCHAR(100),
    state           VARCHAR(100),
    postcode        VARCHAR(10),
    verification_level customer_verification_level NOT NULL DEFAULT 'unverified',
    account_status  account_status NOT NULL DEFAULT 'active',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE domestic_users IS 'Domestic fish market users (local customers)';
COMMENT ON COLUMN domestic_users.ic_number IS 'MyKad / Identity card number';
COMMENT ON COLUMN domestic_users.ic_last_6 IS 'Auto-generated — last 6 chars of IC for verification';
CREATE INDEX idx_domestic_users_email ON domestic_users(email);
CREATE INDEX idx_domestic_users_ic_last6 ON domestic_users(ic_last_6);

-- 4.2  INTERNATIONAL USERS
CREATE TABLE international_users (
    id              BIGSERIAL PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    company_name    VARCHAR(200),
    business_type   VARCHAR(50),
    phone           VARCHAR(20),
    passport_number VARCHAR(50),
    identification_no VARCHAR(50) DEFAULT '',
    passport_last_6 VARCHAR(6) GENERATED ALWAYS AS
                        (RIGHT(COALESCE(passport_number, ''), 6)) STORED,
    country_code    VARCHAR(5) NOT NULL,
    verification_level customer_verification_level NOT NULL DEFAULT 'unverified',
    account_status  account_status NOT NULL DEFAULT 'active',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE international_users IS 'International market / export buyers';
COMMENT ON COLUMN international_users.passport_last_6 IS 'Last 6 chars of passport for QR verification';
CREATE INDEX idx_international_users_email ON international_users(email);
CREATE INDEX idx_international_users_passport_last6 ON international_users(passport_last_6);

-- 4.2a  DELIVERY DRIVERS (local)
CREATE TABLE drivers (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20) DEFAULT '',
    port_id         BIGINT REFERENCES ports(id) ON DELETE SET NULL,
    license_no      VARCHAR(50) DEFAULT '',
    vehicle_no      VARCHAR(50) DEFAULT '',
    identification_no VARCHAR(50) DEFAULT '',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE drivers IS 'Local delivery drivers';

-- 4.2b  INTERNATIONAL DRIVERS (export shipping)
CREATE TABLE intl_drivers (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20) DEFAULT '',
    port_id         BIGINT REFERENCES ports(id) ON DELETE SET NULL,
    license_no      VARCHAR(50) DEFAULT '',
    vehicle_no      VARCHAR(50) DEFAULT '',
    identification_no VARCHAR(50) DEFAULT '',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE intl_drivers IS 'International export shipping drivers';

-- 4.3  RESTAURANTS (international)
CREATE TABLE restaurants (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES international_users(id) ON DELETE CASCADE,
    name            VARCHAR(200) NOT NULL,
    cuisine_type    VARCHAR(100),
    address         TEXT,
    city            VARCHAR(100),
    country         VARCHAR(60) NOT NULL,
    license_no      VARCHAR(100),
    contact_name    VARCHAR(150),
    contact_phone   VARCHAR(20),
    is_verified     BOOLEAN NOT NULL DEFAULT FALSE,
    account_status  account_status NOT NULL DEFAULT 'active',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE restaurants IS 'International restaurants — bulk buyers';
CREATE INDEX idx_restaurants_user ON restaurants(user_id);

-- #############################################################################
-- SECTION 5 : WALLETS & FINANCIAL
-- #############################################################################

-- 5.1  WALLETS (one per user — polymorphism via user_type)
CREATE TABLE wallets (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international')),
    user_id         BIGINT NOT NULL,
    balance         DECIMAL(14,2) NOT NULL DEFAULT 0.00 CHECK (balance >= 0),
    currency        VARCHAR(5) NOT NULL DEFAULT 'MYR',
    is_frozen       BOOLEAN NOT NULL DEFAULT FALSE,
    daily_limit     DECIMAL(14,2),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_wallet_user UNIQUE (user_type, user_id)
);

COMMENT ON TABLE wallets IS 'E-wallets — polymorphic via user_type + user_id';
CREATE INDEX idx_wallets_user ON wallets(user_type, user_id);

-- 5.2  WALLET TRANSACTIONS
CREATE TABLE wallet_transactions (
    id              BIGSERIAL PRIMARY KEY,
    wallet_id       BIGINT NOT NULL REFERENCES wallets(id) ON DELETE RESTRICT,
    transaction_type VARCHAR(20) NOT NULL
        CHECK (transaction_type IN ('topup', 'payment', 'refund', 'withdrawal', 'deposit', 'fee')),
    amount          DECIMAL(14,2) NOT NULL,
    balance_before  DECIMAL(14,2) NOT NULL,
    balance_after   DECIMAL(14,2) NOT NULL,
    reference_type  VARCHAR(30),
    reference_id    BIGINT,
    description     VARCHAR(255),
    status          payment_status NOT NULL DEFAULT 'completed',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT positive_amount CHECK (amount > 0)
);

COMMENT ON TABLE wallet_transactions IS 'Audit trail for all wallet movements';
COMMENT ON COLUMN wallet_transactions.reference_type IS 'e.g. domestic_order, export_order, topup';
CREATE INDEX idx_wallet_transactions_wallet ON wallet_transactions(wallet_id);
CREATE INDEX idx_wallet_transactions_created ON wallet_transactions(created_at DESC);
CREATE INDEX idx_wallet_transactions_ref ON wallet_transactions(reference_type, reference_id)
    WHERE reference_type IS NOT NULL;

-- #############################################################################
-- SECTION 6 : DOMESTIC ORDER SYSTEM
-- #############################################################################

-- 6.1  DOMESTIC ORDERS
CREATE TABLE domestic_orders (
    id              BIGSERIAL PRIMARY KEY,
    order_number    VARCHAR(30) NOT NULL UNIQUE,
    user_id         BIGINT NOT NULL REFERENCES domestic_users(id) ON DELETE RESTRICT,
    wallet_id       BIGINT REFERENCES wallets(id) ON DELETE SET NULL,
    status          order_status NOT NULL DEFAULT 'pending',
    total_amount    DECIMAL(12,2) NOT NULL CHECK (total_amount >= 0),
    shipping_address TEXT,
    shipping_city   VARCHAR(100),
    shipping_state  VARCHAR(100),
    shipping_postcode VARCHAR(10),
    tracking_number VARCHAR(100),
    notes           TEXT,
    paid_at         TIMESTAMPTZ,
    delivered_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE domestic_orders IS 'Domestic market customer orders';
CREATE INDEX idx_domestic_orders_user ON domestic_orders(user_id);
CREATE INDEX idx_domestic_orders_status ON domestic_orders(status);
CREATE INDEX idx_domestic_orders_created ON domestic_orders(created_at DESC);
CREATE INDEX idx_domestic_orders_number ON domestic_orders(order_number);

-- 6.2  DOMESTIC ORDER ITEMS
CREATE TABLE domestic_order_items (
    id              BIGSERIAL PRIMARY KEY,
    order_id        BIGINT NOT NULL REFERENCES domestic_orders(id) ON DELETE CASCADE,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity        DECIMAL(10,2) NOT NULL CHECK (quantity > 0),
    unit_price      DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    subtotal        DECIMAL(12,2) NOT NULL CHECK (subtotal >= 0),
    inventory_id    BIGINT REFERENCES inventory(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE domestic_order_items IS 'Line items per domestic order';
CREATE INDEX idx_domestic_order_items_order ON domestic_order_items(order_id);
CREATE INDEX idx_domestic_order_items_product ON domestic_order_items(product_id);

-- 6.3  DOMESTIC PAYMENTS
CREATE TABLE domestic_payments (
    id              BIGSERIAL PRIMARY KEY,
    order_id        BIGINT NOT NULL REFERENCES domestic_orders(id) ON DELETE RESTRICT,
    wallet_transaction_id BIGINT REFERENCES wallet_transactions(id) ON DELETE SET NULL,
    method          payment_method NOT NULL DEFAULT 'wallet',
    amount          DECIMAL(12,2) NOT NULL CHECK (amount > 0),
    status          payment_status NOT NULL DEFAULT 'pending',
    reference_no    VARCHAR(100),
    paid_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE domestic_payments IS 'Payment records for domestic orders';
CREATE INDEX idx_domestic_payments_order ON domestic_payments(order_id);

-- #############################################################################
-- SECTION 7 : INTERNATIONAL / EXPORT ORDER SYSTEM
-- #############################################################################

-- 7.1  EXPORT ORDERS
CREATE TABLE export_orders (
    id              BIGSERIAL PRIMARY KEY,
    order_number    VARCHAR(30) NOT NULL UNIQUE,
    user_id         BIGINT NOT NULL REFERENCES international_users(id) ON DELETE RESTRICT,
    restaurant_id   BIGINT REFERENCES restaurants(id) ON DELETE SET NULL,
    wallet_id       BIGINT REFERENCES wallets(id) ON DELETE SET NULL,
    stage           export_order_stage NOT NULL DEFAULT 'quote',
    total_amount    DECIMAL(14,2) NOT NULL CHECK (total_amount >= 0),
    deposit_amount  DECIMAL(14,2) CHECK (deposit_amount >= 0),
    currency        VARCHAR(5) NOT NULL DEFAULT 'MYR',
    shipping_terms  VARCHAR(100),
    destination_port VARCHAR(200),
    destination_country VARCHAR(100),
    incoterm        VARCHAR(10),
    container_no    VARCHAR(50),
    tracking_number VARCHAR(100),
    notes           TEXT,
    ordered_at      TIMESTAMPTZ,
    shipped_at      TIMESTAMPTZ,
    delivered_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE export_orders IS 'International/export orders with deposit and shipping stages';
CREATE INDEX idx_export_orders_user ON export_orders(user_id);
CREATE INDEX idx_export_orders_stage ON export_orders(stage);
CREATE INDEX idx_export_orders_created ON export_orders(created_at DESC);

-- 7.2  EXPORT ORDER ITEMS
CREATE TABLE export_order_items (
    id              BIGSERIAL PRIMARY KEY,
    order_id        BIGINT NOT NULL REFERENCES export_orders(id) ON DELETE CASCADE,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity        DECIMAL(10,2) NOT NULL CHECK (quantity > 0),
    unit_price      DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    subtotal        DECIMAL(14,2) NOT NULL CHECK (subtotal >= 0),
    inventory_id    BIGINT REFERENCES inventory(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE export_order_items IS 'Line items per export order';
CREATE INDEX idx_export_order_items_order ON export_order_items(order_id);
CREATE INDEX idx_export_order_items_product ON export_order_items(product_id);

-- 7.3  EXPORT PAYMENTS
CREATE TABLE export_payments (
    id              BIGSERIAL PRIMARY KEY,
    order_id        BIGINT NOT NULL REFERENCES export_orders(id) ON DELETE RESTRICT,
    wallet_transaction_id BIGINT REFERENCES wallet_transactions(id) ON DELETE SET NULL,
    payment_type    VARCHAR(20) NOT NULL CHECK (payment_type IN ('deposit', 'final', 'partial')),
    method          payment_method NOT NULL DEFAULT 'bank_transfer',
    amount          DECIMAL(14,2) NOT NULL CHECK (amount > 0),
    status          payment_status NOT NULL DEFAULT 'pending',
    reference_no    VARCHAR(100),
    paid_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE export_payments IS 'Deposit and final payments for export orders';
CREATE INDEX idx_export_payments_order ON export_payments(order_id);

-- #############################################################################
-- SECTION 8 : SMART CONTRACTS (International)
-- #############################################################################

CREATE TABLE smart_contracts (
    id              BIGSERIAL PRIMARY KEY,
    order_id        BIGINT NOT NULL REFERENCES export_orders(id) ON DELETE RESTRICT,
    contract_no     VARCHAR(50) NOT NULL UNIQUE,
    terms_summary   TEXT NOT NULL,
    total_value     DECIMAL(14,2) NOT NULL CHECK (total_value > 0),
    deposit_percent DECIMAL(5,2) DEFAULT 30.00 CHECK (deposit_percent >= 0 AND deposit_percent <= 100),
    delivery_deadline DATE,
    penalty_clause  TEXT,
    status          contract_status NOT NULL DEFAULT 'draft',
    signed_by_buyer_at   TIMESTAMPTZ,
    signed_by_seller_at  TIMESTAMPTZ,
    fulfilled_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE smart_contracts IS 'Auto-generated contracts for export orders';
CREATE INDEX idx_smart_contracts_order ON smart_contracts(order_id);
CREATE INDEX idx_smart_contracts_status ON smart_contracts(status);

-- #############################################################################
-- SECTION 9 : QR DELIVERY VERIFICATION
-- #############################################################################

CREATE TABLE qr_delivery_verification (
    id              BIGSERIAL PRIMARY KEY,
    order_type      VARCHAR(20) NOT NULL CHECK (order_type IN ('domestic', 'export')),
    order_id        BIGINT NOT NULL,
    qr_code         VARCHAR(64) NOT NULL UNIQUE,
    verification_token UUID NOT NULL DEFAULT uuid_generate_v4(),
    status          qr_status NOT NULL DEFAULT 'active',
    scan_count      SMALLINT NOT NULL DEFAULT 0 CHECK (scan_count >= 0),
    max_scans       SMALLINT NOT NULL DEFAULT 1 CHECK (max_scans >= 1),
    recipient_name  VARCHAR(150),
    recipient_last_6_id VARCHAR(6),
    verified_at     TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT qr_one_time_use CHECK (scan_count <= max_scans)
);

COMMENT ON TABLE qr_delivery_verification IS 'One-time QR code verification for deliveries';
COMMENT ON COLUMN qr_delivery_verification.recipient_last_6_id IS 'Last 6 of IC or passport for identity match';
CREATE INDEX idx_qr_code ON qr_delivery_verification(qr_code);
CREATE INDEX idx_qr_order ON qr_delivery_verification(order_type, order_id);
CREATE INDEX idx_qr_status ON qr_delivery_verification(status) WHERE status = 'active';

-- #############################################################################
-- SECTION 10 : RECIPE SYSTEM
-- #############################################################################

CREATE TABLE recipes (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT REFERENCES products(id) ON DELETE SET NULL,
    title           VARCHAR(200) NOT NULL,
    slug            VARCHAR(220) NOT NULL UNIQUE,
    description     TEXT,
    prep_time_min   SMALLINT CHECK (prep_time_min > 0),
    cook_time_min   SMALLINT CHECK (cook_time_min > 0),
    difficulty      recipe_difficulty NOT NULL DEFAULT 'medium',
    meal_type       meal_type,
    servings        SMALLINT CHECK (servings > 0),
    calories        SMALLINT,
    is_published    BOOLEAN NOT NULL DEFAULT FALSE,
    created_by      BIGINT REFERENCES domestic_users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE recipes IS 'Cooking recipes linked to products';
CREATE INDEX idx_recipes_product ON recipes(product_id);
CREATE INDEX idx_recipes_published ON recipes(is_published) WHERE is_published = TRUE;
CREATE INDEX idx_recipes_meal ON recipes(meal_type) WHERE meal_type IS NOT NULL;

-- 10.1  RECIPE IMAGES
CREATE TABLE recipe_images (
    id              BIGSERIAL PRIMARY KEY,
    recipe_id       BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    url             TEXT NOT NULL,
    alt_text        VARCHAR(200),
    is_primary      BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_recipe_images_recipe ON recipe_images(recipe_id);

-- 10.2  COOKING STEPS
CREATE TABLE cooking_steps (
    id              BIGSERIAL PRIMARY KEY,
    recipe_id       BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    step_number     SMALLINT NOT NULL CHECK (step_number > 0),
    instruction     TEXT NOT NULL,
    duration_min    SMALLINT,
    image_url       TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_step_order UNIQUE (recipe_id, step_number)
);

CREATE INDEX idx_cooking_steps_recipe ON cooking_steps(recipe_id);

-- 10.3  COOKING GUIDES (extra tips / techniques)
CREATE TABLE cooking_guides (
    id              BIGSERIAL PRIMARY KEY,
    title           VARCHAR(200) NOT NULL,
    slug            VARCHAR(220) NOT NULL UNIQUE,
    content         TEXT NOT NULL,
    product_id      BIGINT REFERENCES products(id) ON DELETE SET NULL,
    is_published    BOOLEAN NOT NULL DEFAULT FALSE,
    created_by      BIGINT REFERENCES domestic_users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_cooking_guides_product ON cooking_guides(product_id);
CREATE INDEX idx_cooking_guides_published ON cooking_guides(is_published) WHERE is_published = TRUE;

-- 10.4  FAVORITES
CREATE TABLE favorites (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international')),
    user_id         BIGINT NOT NULL,
    recipe_id       BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_favorite UNIQUE (user_type, user_id, recipe_id)
);

CREATE INDEX idx_favorites_user ON favorites(user_type, user_id);
CREATE INDEX idx_favorites_recipe ON favorites(recipe_id);

-- #############################################################################
-- SECTION 11 : ANALYTICS & AI SUPPORT
-- #############################################################################

-- 11.1  USER ACTIVITY LOGS (AI behaviour tracking)
CREATE TABLE user_activity_logs (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international', 'port')),
    user_id         BIGINT,
    event_type      analytics_event_type NOT NULL,
    page_url        TEXT,
    referrer_url    TEXT,
    session_id      VARCHAR(100),
    ip_address      INET,
    user_agent      TEXT,
    metadata        JSONB DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE user_activity_logs IS 'Behaviour tracking for AI recommendation engine';
COMMENT ON COLUMN user_activity_logs.metadata IS 'Flexible JSON for AI feature extraction';
CREATE INDEX idx_activity_user ON user_activity_logs(user_type, user_id);
CREATE INDEX idx_activity_event ON user_activity_logs(event_type);
CREATE INDEX idx_activity_created ON user_activity_logs(created_at DESC);
CREATE INDEX idx_activity_metadata ON user_activity_logs USING gin(metadata);

-- 11.2  ANALYTICS LOGS (aggregated events)
CREATE TABLE analytics_logs (
    id              BIGSERIAL PRIMARY KEY,
    event_name      VARCHAR(100) NOT NULL,
    event_category  VARCHAR(50) NOT NULL,
    event_label     VARCHAR(200),
    event_value     NUMERIC,
    source          VARCHAR(30) NOT NULL DEFAULT 'port',
    metadata        JSONB DEFAULT '{}'::jsonb,
    recorded_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE analytics_logs IS 'Aggregated analytics events for dashboards';
COMMENT ON COLUMN analytics_logs.source IS 'port, domestic, international, system';
CREATE INDEX idx_analytics_name ON analytics_logs(event_name);
CREATE INDEX idx_analytics_category ON analytics_logs(event_category);
CREATE INDEX idx_analytics_recorded ON analytics_logs(recorded_at DESC);
CREATE INDEX idx_analytics_metadata ON analytics_logs USING gin(metadata);

-- 11.3  DASHBOARD STATISTICS (pre-computed / snapshot)
CREATE TABLE dashboard_statistics (
    id              BIGSERIAL PRIMARY KEY,
    stat_key        VARCHAR(100) NOT NULL,
    stat_value      NUMERIC NOT NULL,
    stat_label      VARCHAR(200),
    source          VARCHAR(30) NOT NULL DEFAULT 'port',
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    metadata        JSONB DEFAULT '{}'::jsonb,
    computed_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_stat_period UNIQUE (stat_key, period_start, period_end)
);

COMMENT ON TABLE dashboard_statistics IS 'Pre-computed KPIs for dashboard display';
CREATE INDEX idx_dashboard_stats_key ON dashboard_statistics(stat_key);
CREATE INDEX idx_dashboard_stats_period ON dashboard_statistics(period_start, period_end DESC);

-- #############################################################################
-- SECTION 12 : PORT SYSTEM USERS (Internal)
-- #############################################################################

CREATE TABLE port_users (
    id              BIGSERIAL PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    role            user_role NOT NULL DEFAULT 'viewer',
    port_id         BIGINT REFERENCES ports(id) ON DELETE SET NULL,
    phone           VARCHAR(20),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE port_users IS 'Internal port system staff accounts';
CREATE INDEX idx_port_users_port ON port_users(port_id);
CREATE INDEX idx_port_users_role ON port_users(role);

-- #############################################################################
-- SECTION 13 : SHOPPING CART (Domestic Market)
-- #############################################################################

CREATE TABLE cart_items (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES domestic_users(id) ON DELETE CASCADE,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    quantity        DECIMAL(10,2) NOT NULL CHECK (quantity > 0),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_cart_item UNIQUE (user_id, product_id)
);

COMMENT ON TABLE cart_items IS 'Persistent shopping cart for domestic market users';
CREATE INDEX idx_cart_items_user ON cart_items(user_id);
CREATE INDEX idx_cart_items_product ON cart_items(product_id);

-- #############################################################################
-- SECTION 14 : ORDER STATUS LOGS (Audit Trail)
-- #############################################################################

CREATE TABLE order_status_logs (
    id              BIGSERIAL PRIMARY KEY,
    order_type      VARCHAR(20) NOT NULL CHECK (order_type IN ('domestic', 'export')),
    order_id        BIGINT NOT NULL,
    from_status     VARCHAR(30),
    to_status       VARCHAR(30) NOT NULL,
    changed_by_type VARCHAR(20) CHECK (changed_by_type IN ('system', 'port_user', 'customer', 'admin')),
    changed_by_id   BIGINT,
    notes           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE order_status_logs IS 'Audit trail for all order status changes across domestic and export orders';
CREATE INDEX idx_order_status_logs_order ON order_status_logs(order_type, order_id);
CREATE INDEX idx_order_status_logs_created ON order_status_logs(created_at DESC);
CREATE INDEX idx_order_status_logs_by ON order_status_logs(changed_by_type, changed_by_id)
    WHERE changed_by_id IS NOT NULL;

-- #############################################################################
-- SECTION 15 : LOW STOCK ALERTS (Port Management)
-- #############################################################################

CREATE TABLE low_stock_alerts (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    warehouse_id    BIGINT REFERENCES warehouses(id) ON DELETE SET NULL,
    current_qty     DECIMAL(12,2) NOT NULL CHECK (current_qty >= 0),
    threshold_qty   DECIMAL(12,2) NOT NULL CHECK (threshold_qty > 0),
    alert_message   VARCHAR(255) NOT NULL,
    severity        VARCHAR(10) NOT NULL DEFAULT 'warning'
                        CHECK (severity IN ('info', 'warning', 'critical')),
    is_resolved     BOOLEAN NOT NULL DEFAULT FALSE,
    resolved_at     TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE low_stock_alerts IS 'Auto-generated alerts when inventory drops below threshold';
CREATE INDEX idx_low_stock_alerts_product ON low_stock_alerts(product_id);
CREATE INDEX idx_low_stock_alerts_unresolved ON low_stock_alerts(is_resolved)
    WHERE is_resolved = FALSE;
CREATE INDEX idx_low_stock_alerts_severity ON low_stock_alerts(severity)
    WHERE is_resolved = FALSE;

-- #############################################################################
-- SECTION 16 : PRODUCT RECOMMENDATIONS (AI / ML Output)
-- #############################################################################

CREATE TABLE product_recommendations (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international')),
    user_id         BIGINT NOT NULL,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    score           DECIMAL(5,4) NOT NULL CHECK (score >= 0 AND score <= 1),
    reason          VARCHAR(100),
    model_version   VARCHAR(50) DEFAULT 'v1',
    is_clicked      BOOLEAN,
    clicked_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE product_recommendations IS 'AI-generated product recommendations for personalisation';
COMMENT ON COLUMN product_recommendations.score IS 'Confidence score 0.0000 — 1.0000';
CREATE INDEX idx_recommendations_user ON product_recommendations(user_type, user_id);
CREATE INDEX idx_recommendations_product ON product_recommendations(product_id);
CREATE INDEX idx_recommendations_score ON product_recommendations(score DESC);

-- #############################################################################
-- SECTION 17 : AI PREDICTIONS (Future ML / AI Features)
-- #############################################################################

CREATE TABLE ai_predictions (
    id              BIGSERIAL PRIMARY KEY,
    prediction_type VARCHAR(50) NOT NULL
        CHECK (prediction_type IN (
            'catch_forecast', 'demand_forecast', 'price_optimisation',
            'stock_reorder', 'sales_forecast', 'vessel_activity'
        )),
    entity_type     VARCHAR(30) NOT NULL CHECK (entity_type IN ('port', 'vessel', 'product', 'warehouse', 'user')),
    entity_id       BIGINT NOT NULL,
    predicted_value NUMERIC NOT NULL,
    confidence_low  NUMERIC,
    confidence_high NUMERIC,
    features_used   JSONB DEFAULT '{}'::jsonb,
    model_version   VARCHAR(50) NOT NULL DEFAULT 'v1',
    prediction_date DATE NOT NULL DEFAULT CURRENT_DATE,
    valid_from      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    valid_until     TIMESTAMPTZ,
    actual_value    NUMERIC,
    accuracy_score  DECIMAL(5,4),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE ai_predictions IS 'ML/AI prediction outputs for catch forecasting, demand, pricing, and vessel activity';
COMMENT ON COLUMN ai_predictions.features_used IS 'JSONB of feature importance for explainability';
CREATE INDEX idx_ai_predictions_type ON ai_predictions(prediction_type);
CREATE INDEX idx_ai_predictions_entity ON ai_predictions(entity_type, entity_id);
CREATE INDEX idx_ai_predictions_date ON ai_predictions(prediction_date DESC);
CREATE INDEX idx_ai_predictions_model ON ai_predictions(model_version);
CREATE INDEX idx_ai_predictions_accuracy ON ai_predictions(accuracy_score)
    WHERE accuracy_score IS NOT NULL;

-- #############################################################################
-- SECTION 18 : REFRESH TOKENS (Django REST / JWT Auth)
-- #############################################################################

CREATE TABLE refresh_tokens (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international', 'port')),
    user_id         BIGINT NOT NULL,
    token           VARCHAR(512) NOT NULL UNIQUE,
    expires_at      TIMESTAMPTZ NOT NULL,
    is_revoked      BOOLEAN NOT NULL DEFAULT FALSE,
    revoked_at      TIMESTAMPTZ,
    device_info     VARCHAR(255),
    ip_address      INET,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE refresh_tokens IS 'JWT refresh tokens for Django REST API authentication';
CREATE INDEX idx_refresh_tokens_user ON refresh_tokens(user_type, user_id);
CREATE INDEX idx_refresh_tokens_expires ON refresh_tokens(expires_at)
    WHERE is_revoked = FALSE;
CREATE INDEX idx_refresh_tokens_token ON refresh_tokens(token);

-- #############################################################################
-- SECTION 19 : NOTIFICATIONS (Cross-System)
-- #############################################################################

CREATE TABLE notifications (
    id              BIGSERIAL PRIMARY KEY,
    user_type       VARCHAR(20) NOT NULL CHECK (user_type IN ('domestic', 'international', 'port')),
    user_id         BIGINT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    message         TEXT NOT NULL,
    category        VARCHAR(30) NOT NULL DEFAULT 'general'
                        CHECK (category IN ('order', 'payment', 'shipment', 'stock', 'promotion', 'general')),
    reference_type  VARCHAR(30),
    reference_id    BIGINT,
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    read_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE notifications IS 'Cross-system user notifications (order updates, low stock, promotions)';
CREATE INDEX idx_notifications_user ON notifications(user_type, user_id, is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at DESC);
CREATE INDEX idx_notifications_category ON notifications(category);

-- #############################################################################
-- SECTION 20 : USER ADDRESSES (Domestic — multiple addresses per user)
-- #############################################################################

CREATE TABLE domestic_addresses (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES domestic_users(id) ON DELETE CASCADE,
    label           VARCHAR(50) DEFAULT 'Home',
    address_line1   VARCHAR(255) NOT NULL,
    address_line2   VARCHAR(255),
    city            VARCHAR(100) NOT NULL,
    state           VARCHAR(100) NOT NULL,
    postcode        VARCHAR(10) NOT NULL,
    is_default      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE domestic_addresses IS 'Multiple saved addresses per domestic user';
CREATE INDEX idx_domestic_addresses_user ON domestic_addresses(user_id);

-- #############################################################################
-- SECTION 21 : TRIGGERS (auto-update updated_at)
-- #############################################################################

CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply to all tables with updated_at column
DO $$
DECLARE
    tbl TEXT;
BEGIN
    FOR tbl IN
        SELECT unnest(ARRAY[
            'ports', 'vessels', 'fish_catches', 'warehouses',
            'products', 'inventory', 'stock_movements',
            'domestic_users', 'domestic_orders', 'domestic_payments',
            'international_users', 'restaurants',
            'export_orders', 'export_payments', 'smart_contracts',
            'qr_delivery_verification',
            'wallets', 'wallet_transactions',
            'recipes', 'cooking_guides',
            'port_users',
            'cart_items', 'domestic_addresses'
        ])
    LOOP
        EXECUTE format(
            'CREATE TRIGGER set_updated_at_%I BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();',
            tbl, tbl
        );
    END LOOP;
END;
$$;

-- #############################################################################
-- SECTION 22 : ROW-LEVEL SECURITY (Supabase / PostgREST-ready)
-- #############################################################################

-- Enable RLS on all tables
DO $$
DECLARE
    tbl TEXT;
BEGIN
    FOR tbl IN
        SELECT tablename FROM pg_tables
        WHERE schemaname = 'public' AND tablename NOT LIKE 'pg_%'
    LOOP
        EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY;', tbl);
    END LOOP;
END;
$$;

-- =============================================================================
-- END OF SCHEMA
-- =============================================================================
-- Migration and seeding notes:
--   1.  Run:  psql -h <supabase-host> -d postgres -f supabase_schema.sql
--   2.  Admin user seeding via Django management command.
--   3.  product_images uses url — stored in Supabase Storage bucket 'product-images'.
--   4.  QR codes should be generated server-side (SHA-256 hash of order reference + secret).
--   5.  Analytics tables are append-only for ML pipeline readiness.
-- =============================================================================
