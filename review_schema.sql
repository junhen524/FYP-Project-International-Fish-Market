-- =============================================================================
-- SCHEMA REVIEW — Final State (AFTER migration)
-- Generated 2026-05-31
-- =============================================================================

-- 🔒 Supabase internal schemas (auth, realtime, storage, vault) — untouched

-- ##############################################################################
-- PUBLIC SCHEMA — FINAL TABLES (24 tables)
-- ##############################################################################

-- ── Core / Shared ──
--   product           ← renamed from products
--   ports             ← unchanged
--   warehouses        ← unchanged
--   inventory         ← unchanged

-- ── Users ──
--   port_user         ← unchanged (was already the active one)
--   market_user       ← renamed from domestic_users
--   export_user       ← renamed from international_users

-- ── Orders ──
--   market_order      ← renamed from domestic_orders
--   market_order_item ← renamed from domestic_order_items
--   export_orders     ← unchanged

-- ── Logistics ──
--   drivers           ← unchanged
--   export_driver     ← renamed from intl_drivers
--   export_shipment   ← renamed from intl_shipments

-- ── Fishing / Vessel ──
--   vessels           ← unchanged
--   catch_record      ← renamed from fish_catches
--   fishery_zone      ← renamed from fishing_zones
--   vessel_track      ← renamed from vessel_tracking

-- ── Finance ──
--   wallets           ← unchanged
--   market_wallet_txn ← split from wallet_txn (domestic txns)
--   export_wallet_txn ← split from wallet_txn (international txns)

-- ── Analytics ──
--   stock_move        ← renamed from stock_movements
--   user_activity     ← renamed from user_activity_logs
--   analytics_log     ← renamed from analytics_logs
--   dashboard_stat    ← renamed from dashboard_statistics

-- ── Other ──
--   notifications     ← unchanged
--   system_settings   ← unchanged (but has no data — add when needed)

-- ##############################################################################
-- TABLES DROPPED (25 tables)
-- ##############################################################################

-- A1: From schema but unused (8)
--   restaurants, domestic_payments, export_order_items, export_payments,
--   cart_items, order_status_logs, shipments

-- A2: Django legacy (12)
--   django_migrations, django_content_type, django_admin_log, django_session,
--   auth_user, auth_group, auth_group_permissions, auth_permission,
--   auth_user_groups, auth_user_user_permissions, users_userprofile,
--   dashboard_useractivitylog

-- A3: Store legacy (6)
--   store_cart, store_cartitem, store_order, store_orderitem,
--   store_userprofile, store_wallettransaction

-- ##############################################################################
-- DONE
-- ##############################################################################
