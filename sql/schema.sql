-- ============================================================
-- Captive Portal Hotspot & Dashboard Admin
-- Database Schema - PostgreSQL
-- ============================================================

-- Extension for UUID generation (optional)
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- 1. ACCOUNTS - Admin dashboard users
-- ============================================================
CREATE TABLE IF NOT EXISTS accounts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- bcrypt hashed
    role VARCHAR(10) NOT NULL DEFAULT 'read' CHECK (role IN ('full', 'read')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123 - CHANGE IMMEDIATELY)
-- NOTE: Hash akan di-regenerate oleh setup.sh saat deployment
INSERT INTO accounts (username, password, role) VALUES (
    'admin',
    'PLACEHOLDER_WILL_BE_REPLACED_BY_SETUP',
    'full'
) ON CONFLICT (username) DO NOTHING;

-- ============================================================
-- 2. USERS - Portal/hotspot users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username_identity VARCHAR(255) NOT NULL UNIQUE, -- email or generated ID
    name VARCHAR(255),
    gender VARCHAR(20),
    address TEXT,
    photo_url TEXT,
    login_method VARCHAR(20) NOT NULL DEFAULT 'free' CHECK (login_method IN ('google', 'facebook', 'free')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_login_method ON users(login_method);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);

-- ============================================================
-- 3. RADCHECK - FreeRADIUS standard table
-- DO NOT rename this table
-- ============================================================
CREATE TABLE IF NOT EXISTS radcheck (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT 'Cleartext-Password',
    op VARCHAR(2) NOT NULL DEFAULT ':=',
    value VARCHAR(253) NOT NULL DEFAULT '',
    CONSTRAINT radcheck_username_unique UNIQUE (username)
);

CREATE INDEX IF NOT EXISTS idx_radcheck_username ON radcheck(username);

-- ============================================================
-- 4. RADACCT - FreeRADIUS standard accounting table
-- DO NOT rename this table
-- ============================================================
CREATE TABLE IF NOT EXISTS radacct (
    radacctid BIGSERIAL PRIMARY KEY,
    acctsessionid VARCHAR(64) NOT NULL DEFAULT '',
    acctuniqueid VARCHAR(32) NOT NULL DEFAULT '',
    username VARCHAR(64) NOT NULL DEFAULT '',
    realm VARCHAR(64) DEFAULT '',
    nasipaddress VARCHAR(15) NOT NULL DEFAULT '',
    nasportid VARCHAR(32) DEFAULT NULL,
    nasporttype VARCHAR(32) DEFAULT NULL,
    acctstarttime TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    acctupdatetime TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    acctstoptime TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    acctinterval INTEGER DEFAULT NULL,
    acctsessiontime INTEGER DEFAULT NULL,
    acctauthentic VARCHAR(32) DEFAULT NULL,
    connectinfo_start VARCHAR(128) DEFAULT NULL,
    connectinfo_stop VARCHAR(128) DEFAULT NULL,
    acctinputoctets BIGINT DEFAULT NULL,
    acctoutputoctets BIGINT DEFAULT NULL,
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause VARCHAR(32) NOT NULL DEFAULT '',
    servicetype VARCHAR(32) DEFAULT NULL,
    framedprotocol VARCHAR(32) DEFAULT NULL,
    framedipaddress VARCHAR(15) NOT NULL DEFAULT '',
    framedipv6address VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    framedinterfaceid VARCHAR(44) NOT NULL DEFAULT '',
    delegatedipv6prefix VARCHAR(45) NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_radacct_username ON radacct(username);
CREATE INDEX IF NOT EXISTS idx_radacct_acctstarttime ON radacct(acctstarttime);
CREATE INDEX IF NOT EXISTS idx_radacct_acctstoptime ON radacct(acctstoptime);
CREATE INDEX IF NOT EXISTS idx_radacct_acctsessionid ON radacct(acctsessionid);
CREATE UNIQUE INDEX IF NOT EXISTS idx_radacct_acctuniqueid ON radacct(acctuniqueid);
CREATE INDEX IF NOT EXISTS idx_radacct_nasipaddress ON radacct(nasipaddress);
CREATE INDEX IF NOT EXISTS idx_radacct_calledstationid ON radacct(calledstationid);
CREATE INDEX IF NOT EXISTS idx_radacct_callingstationid ON radacct(callingstationid);

-- ============================================================
-- 4a. FREERADIUS AUXILIARY TABLES
-- Required by default queries.conf to prevent rlm_sql crash
-- ============================================================
CREATE TABLE IF NOT EXISTS radreply (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radgroupcheck (
    id SERIAL PRIMARY KEY,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '==',
    value VARCHAR(253) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radgroupreply (
    id SERIAL PRIMARY KEY,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radusergroup (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    priority INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS radpostauth (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    pass VARCHAR(64) NOT NULL DEFAULT '',
    reply VARCHAR(32) NOT NULL DEFAULT '',
    authdate TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 5. SETTINGS - Key-value config store
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
    ('google_client_id', ''),
    ('google_client_secret', ''),
    ('google_redirect_uri', ''),
    ('facebook_app_id', ''),
    ('facebook_app_secret', ''),
    ('facebook_redirect_uri', ''),
    ('mikrotik_ip', '192.168.88.1'),
    ('mikrotik_username', 'admin'),
    ('mikrotik_password', ''),
    ('mikrotik_port', '8728'),
    ('hotspot_login_url', 'http://hotspot.local/login'),
    ('free_session_limit_seconds', '3600'),
    ('site_name', 'Public Hotspot'),
    ('site_logo_url', '')
ON CONFLICT (setting_key) DO NOTHING;

-- ============================================================
-- 6. ADVERTISEMENTS - Iklan untuk success page
-- ============================================================
CREATE TABLE IF NOT EXISTS advertisements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    link_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_advertisements_active ON advertisements(is_active, display_order);

-- ============================================================
-- 7. FREE_SESSION_LOG - Track free 1-hour sessions
-- ============================================================
CREATE TABLE IF NOT EXISTS free_session_log (
    id SERIAL PRIMARY KEY,
    mac_address VARCHAR(17) NOT NULL,
    ip_address VARCHAR(45),
    username_identity VARCHAR(255),
    session_start TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    session_limit_seconds INTEGER DEFAULT 3600,
    is_expired BOOLEAN DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_free_session_mac ON free_session_log(mac_address, session_start);
