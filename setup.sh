#!/bin/bash
# ============================================================
# Captive Portal Hotspot - Auto Setup Script (Nginx)
# OS: Ubuntu 22.04 / 24.04 LTS
# Usage: sudo bash setup.sh
# ============================================================

set -e

# ===================== KONFIGURASI =====================
# UBAH VARIABEL INI SEBELUM MENJALANKAN SCRIPT!
# =======================================================

DB_NAME="captive_portal"
DB_USER="captive_user"
DB_PASS="GANTI_PASSWORD_DISINI"          # ← WAJIB GANTI di server!
INSTALL_DIR="/var/www/captive-portal"
SERVER_NAME="portal.fanns.my.id"         # Domain portal
FREERADIUS_SECRET="GANTI_SECRET_DISINI"  # ← WAJIB GANTI di server!

# =======================================================

# Warna output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

print_header() {
    echo ""
    echo -e "${CYAN}============================================================${NC}"
    echo -e "${CYAN} $1${NC}"
    echo -e "${CYAN}============================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check root
if [ "$EUID" -ne 0 ]; then
    print_error "Script harus dijalankan sebagai root!"
    echo "Usage: sudo bash setup.sh"
    exit 1
fi

# Konfirmasi
print_header "Captive Portal Hotspot - Auto Setup (Nginx)"
echo ""
echo "Script ini akan menginstall dan mengkonfigurasi:"
echo "  • Nginx + PHP-FPM 8.x + SSL (Let's Encrypt)"
echo "  • PostgreSQL"
echo "  • FreeRADIUS + PostgreSQL module"
echo ""
echo "Konfigurasi:"
echo "  • Database    : $DB_NAME"
echo "  • DB User     : $DB_USER"
echo "  • Install Dir : $INSTALL_DIR"
echo "  • Domain      : $SERVER_NAME"
echo ""
read -p "Lanjutkan instalasi? (y/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Instalasi dibatalkan."
    exit 0
fi

# ============================================================
# STEP 1: Update System
# ============================================================
print_header "Step 1/9 — Update System Packages"

apt update && apt upgrade -y
print_success "System updated"

# ============================================================
# STEP 2: Install Nginx + PHP-FPM
# ============================================================
print_header "Step 2/9 — Install Nginx & PHP-FPM"

apt install -y \
    nginx \
    php-fpm \
    php-pgsql \
    php-mbstring \
    php-json \
    php-curl \
    php-gd \
    php-xml \
    unzip \
    git

# Detect PHP-FPM version & socket
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

print_success "Nginx + PHP-FPM $PHP_VERSION installed"
echo "  PHP-FPM socket: $PHP_FPM_SOCK"

if [[ $(echo "$PHP_VERSION < 8.1" | bc -l 2>/dev/null || echo "0") == "1" ]]; then
    print_warning "PHP version < 8.1 detected. Beberapa fitur mungkin tidak bekerja."
fi

# Enable & start PHP-FPM
systemctl enable "php${PHP_VERSION}-fpm"
systemctl start "php${PHP_VERSION}-fpm"

print_success "PHP-FPM ${PHP_VERSION} running"

# ============================================================
# STEP 3: Install PostgreSQL
# ============================================================
print_header "Step 3/9 — Install PostgreSQL"

apt install -y postgresql postgresql-contrib

systemctl enable postgresql
systemctl start postgresql

print_success "PostgreSQL installed and running"

# ============================================================
# STEP 4: Setup Database
# ============================================================
print_header "Step 4/9 — Setup Database"

# Cek apakah user sudah ada
if sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; then
    print_warning "Database user '$DB_USER' sudah ada, skip pembuatan user"
else
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"
    print_success "Database user '$DB_USER' created"
fi

# Cek apakah database sudah ada
if sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
    print_warning "Database '$DB_NAME' sudah ada, skip pembuatan database"
else
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
    print_success "Database '$DB_NAME' created"
fi

# Grant privileges
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"

# Fix: FreeRADIUS 3.0 tidak support scram-sha-256, ubah ke md5
echo "Configuring PostgreSQL authentication for FreeRADIUS compatibility..."
sudo -u postgres psql -c "ALTER SYSTEM SET password_encryption = 'md5';"
sed -i 's/scram-sha-256/md5/g' /etc/postgresql/*/main/pg_hba.conf
systemctl restart postgresql

# Re-set password agar di-hash ulang sebagai md5
sudo -u postgres psql -c "ALTER USER $DB_USER WITH PASSWORD '$DB_PASS';"

print_success "PostgreSQL auth method set to md5 (FreeRADIUS compatible)"

# ============================================================
# STEP 5: Deploy Application Files
# ============================================================
print_header "Step 5/9 — Deploy Application Files"

# Buat direktori jika belum ada
mkdir -p "$INSTALL_DIR"

# Copy files dari direktori saat ini (tempat script dijalankan)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "$SCRIPT_DIR" != "$INSTALL_DIR" ]; then
    echo "Copying files from $SCRIPT_DIR to $INSTALL_DIR ..."
    cp -r "$SCRIPT_DIR"/.htaccess "$INSTALL_DIR"/ 2>/dev/null || true
    cp -r "$SCRIPT_DIR"/config "$INSTALL_DIR"/
    cp -r "$SCRIPT_DIR"/dashboard "$INSTALL_DIR"/
    cp -r "$SCRIPT_DIR"/includes "$INSTALL_DIR"/
    cp -r "$SCRIPT_DIR"/portal "$INSTALL_DIR"/
    cp -r "$SCRIPT_DIR"/sql "$INSTALL_DIR"/
    mkdir -p "$INSTALL_DIR"/uploads/ads
    mkdir -p "$INSTALL_DIR"/logs
    print_success "Files copied to $INSTALL_DIR"
else
    echo "Script sudah dijalankan dari $INSTALL_DIR, skip copy."
    mkdir -p "$INSTALL_DIR"/uploads/ads
    mkdir -p "$INSTALL_DIR"/logs
fi

# Import SQL schema
echo "Importing database schema..."
sudo -u postgres psql -d "$DB_NAME" -f "$INSTALL_DIR/sql/schema.sql"

# Grant table permissions
sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON ALL TABLES IN SCHEMA public TO $DB_USER;"
sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;"

# Generate correct bcrypt hash untuk admin password dan update database
echo "Generating admin password hash..."
ADMIN_HASH=$(php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")
sudo -u postgres psql -d "$DB_NAME" -c "UPDATE accounts SET password = '$ADMIN_HASH' WHERE username = 'admin';"

print_success "Database schema imported"
print_success "Default admin: admin / admin123 (SEGERA GANTI!)"

# ============================================================
# STEP 6: Configure Application
# ============================================================
print_header "Step 6/9 — Configure Application"

# Update database.php dengan kredensial yang benar
CONFIG_FILE="$INSTALL_DIR/config/database.php"

if [ -f "$CONFIG_FILE" ]; then
    # Backup original
    cp "$CONFIG_FILE" "${CONFIG_FILE}.bak"

    # Replace database credentials
    sed -i "s/private const DB_HOST = .*/private const DB_HOST = 'localhost';/" "$CONFIG_FILE"
    sed -i "s/private const DB_PORT = .*/private const DB_PORT = '5432';/" "$CONFIG_FILE"
    sed -i "s/private const DB_NAME = .*/private const DB_NAME = '$DB_NAME';/" "$CONFIG_FILE"
    sed -i "s/private const DB_USER = .*/private const DB_USER = '$DB_USER';/" "$CONFIG_FILE"
    sed -i "s/private const DB_PASS = .*/private const DB_PASS = '$DB_PASS';/" "$CONFIG_FILE"

    print_success "Database config updated"
else
    print_error "config/database.php tidak ditemukan!"
fi

# Set file permissions
echo "Setting file permissions..."
chown -R www-data:www-data "$INSTALL_DIR"
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$INSTALL_DIR/uploads"
chmod -R 775 "$INSTALL_DIR/logs"
chmod 640 "$INSTALL_DIR/config/database.php"
chmod 640 "$INSTALL_DIR/config/app.php"

print_success "File permissions set"

# ============================================================
# STEP 7: Configure PHP-FPM
# ============================================================
print_header "Step 7/9 — Configure PHP-FPM"

PHP_FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

if [ -f "$PHP_FPM_POOL" ]; then
    cp "$PHP_FPM_POOL" "${PHP_FPM_POOL}.bak"

    # Set upload & execution limits
    sed -i "s/^;*\s*php_admin_value\[upload_max_filesize\].*/php_admin_value[upload_max_filesize] = 5M/" "$PHP_FPM_POOL"
    sed -i "s/^;*\s*php_admin_value\[post_max_size\].*/php_admin_value[post_max_size] = 8M/" "$PHP_FPM_POOL"

    # Tambah jika belum ada
    if ! grep -q "upload_max_filesize" "$PHP_FPM_POOL"; then
        echo "php_admin_value[upload_max_filesize] = 5M" >> "$PHP_FPM_POOL"
    fi
    if ! grep -q "post_max_size" "$PHP_FPM_POOL"; then
        echo "php_admin_value[post_max_size] = 8M" >> "$PHP_FPM_POOL"
    fi

    print_success "PHP-FPM pool configured"
fi

# Set PHP ini values
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i "s/^upload_max_filesize.*/upload_max_filesize = 5M/" "$PHP_INI"
    sed -i "s/^post_max_size.*/post_max_size = 8M/" "$PHP_INI"
    sed -i "s/^max_execution_time.*/max_execution_time = 30/" "$PHP_INI"
    sed -i "s/^;*session.cookie_httponly.*/session.cookie_httponly = 1/" "$PHP_INI"
    sed -i "s/^;*session.use_strict_mode.*/session.use_strict_mode = 1/" "$PHP_INI"
    print_success "PHP ini configured"
fi

systemctl restart "php${PHP_VERSION}-fpm"

# ============================================================
# STEP 8: Configure Nginx Server Block
# ============================================================
print_header "Step 8/9 — Configure Nginx Server Block"

NGINX_CONF="/etc/nginx/sites-available/captive-portal"

cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;

    root $INSTALL_DIR;
    index index.php index.html;

    # ──────────────────────────────────────────────
    # Security Headers
    # ──────────────────────────────────────────────
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # ──────────────────────────────────────────────
    # Block access to sensitive directories
    # ──────────────────────────────────────────────
    location ~ ^/(config|includes|sql|logs)/ {
        deny all;
        return 403;
    }

    # Block access to hidden files (.env, .git, .htaccess, etc.)
    location ~ /\. {
        deny all;
        return 403;
    }

    # Block access to sensitive file types
    location ~* \.(sql|log|env|bak|old|orig)$ {
        deny all;
        return 403;
    }

    # ──────────────────────────────────────────────
    # Prevent directory listing
    # ──────────────────────────────────────────────
    autoindex off;

    # ──────────────────────────────────────────────
    # Upload size limit
    # ──────────────────────────────────────────────
    client_max_body_size 8M;

    # ──────────────────────────────────────────────
    # Static files caching
    # ──────────────────────────────────────────────
    location ~* \.(css|js|png|jpg|jpeg|gif|webp|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # ──────────────────────────────────────────────
    # PHP processing via PHP-FPM
    # ──────────────────────────────────────────────
    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;

        # PHP timeouts
        fastcgi_read_timeout 30;
        fastcgi_send_timeout 30;
    }

    # ──────────────────────────────────────────────
    # Logging
    # ──────────────────────────────────────────────
    access_log /var/log/nginx/captive-portal-access.log;
    error_log /var/log/nginx/captive-portal-error.log;
}
EOF

# Enable site, disable default
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/captive-portal
rm -f /etc/nginx/sites-enabled/default

# Test config
nginx -t

systemctl restart nginx

print_success "Nginx server block configured for $SERVER_NAME"

# ============================================================
# STEP 9: Install & Configure FreeRADIUS
# ============================================================
print_header "Step 9/9 — Configure FreeRADIUS"

apt install -y freeradius freeradius-postgresql

# Configure SQL module
RADIUS_SQL="/etc/freeradius/3.0/mods-available/sql"

if [ -f "$RADIUS_SQL" ]; then
    cp "$RADIUS_SQL" "${RADIUS_SQL}.bak"

    cat > "$RADIUS_SQL" <<EOF
sql {
    driver = "rlm_sql_postgresql"
    dialect = "postgresql"

    server = "localhost"
    port = 5432
    login = "$DB_USER"
    password = "$DB_PASS"

    radius_db = "$DB_NAME"

    acct_table1 = "radacct"
    acct_table2 = "radacct"
    authcheck_table = "radcheck"

    read_clients = no

    pool {
        start = 5
        min = 4
        max = 10
        spare = 3
        lifetime = 0
        idle_timeout = 60
    }

    # Load standard FreeRADIUS SQL queries
    \$INCLUDE \${modconfdir}/\${.:name}/main/\${dialect}/queries.conf
}
EOF

    # Enable SQL module
    ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
    chown -h freerad:freerad /etc/freeradius/3.0/mods-enabled/sql

    print_success "FreeRADIUS SQL module configured"
else
    print_warning "FreeRADIUS SQL config tidak ditemukan. Konfigurasi manual diperlukan."
fi

# Enable SQL in default site
RADIUS_DEFAULT="/etc/freeradius/3.0/sites-available/default"
if [ -f "$RADIUS_DEFAULT" ]; then
    # Uncomment sql in authorize and accounting sections (handles indentation)
    sed -i 's/^[ \t]*#[ \t]*-sql/        -sql/' "$RADIUS_DEFAULT" 2>/dev/null || true
    print_success "FreeRADIUS default site updated"
fi

# Add MikroTik client
RADIUS_CLIENTS="/etc/freeradius/3.0/clients.conf"
if [ -f "$RADIUS_CLIENTS" ]; then
    if ! grep -q "mikrotik_hotspot" "$RADIUS_CLIENTS"; then
        echo -e "\nclient mikrotik_hotspot {\n    ipaddr = 0.0.0.0/0\n    secret = $FREERADIUS_SECRET\n}" >> "$RADIUS_CLIENTS"
        print_success "MikroTik added to RADIUS clients"
    fi
fi

# Restart FreeRADIUS
systemctl enable freeradius
systemctl restart freeradius

print_success "FreeRADIUS configured and running"

# ============================================================
# SSL Certificate (Let's Encrypt)
# ============================================================
print_header "Bonus — SSL Certificate (Let's Encrypt)"

apt install -y certbot python3-certbot-nginx

echo ""
echo "Memasang SSL certificate untuk $SERVER_NAME ..."
echo ""

# Request SSL certificate
if certbot --nginx -d "$SERVER_NAME" --non-interactive --agree-tos --email "admin@$SERVER_NAME" --redirect; then
    print_success "SSL certificate installed for $SERVER_NAME"
    print_success "HTTP akan otomatis redirect ke HTTPS"

    # Setup auto-renewal
    systemctl enable certbot.timer 2>/dev/null || true
    print_success "Auto-renewal enabled"
else
    print_warning "SSL certificate gagal di-install."
    print_warning "Pastikan DNS A record untuk $SERVER_NAME sudah mengarah ke IP server ini."
    print_warning "Kamu bisa jalankan manual nanti: sudo certbot --nginx -d $SERVER_NAME"
fi

# ============================================================
# SELESAI!
# ============================================================
print_header "Setup Complete!"

echo ""
echo -e "  ${GREEN}Dashboard${NC}  : https://$SERVER_NAME/dashboard/login.php"
echo -e "  ${GREEN}Portal${NC}     : https://$SERVER_NAME/portal/"
echo ""
echo -e "  ${YELLOW}Default Login:${NC}"
echo -e "    Username : admin"
echo -e "    Password : admin123"
echo ""
echo -e "  ${RED}⚠  PENTING:${NC}"
echo -e "    1. Segera ganti password admin default!"
echo -e "    2. Set Google/Facebook OAuth credentials di dashboard Settings"
echo -e "    3. Konfigurasi MikroTik walled garden & RADIUS client"
echo -e "    4. Pastikan DNS A record 'portal.fanns.my.id' → IP server ini"
echo -e "    5. Di Winbox (IP > Hotspot > Server Profiles > RADIUS), CENTANG Accounting & isi Interim Update 00:01:00"
echo ""
echo -e "  ${CYAN}Stack:${NC}"
echo -e "    Web Server : Nginx"
echo -e "    PHP        : PHP-FPM ${PHP_VERSION}"
echo -e "    Database   : PostgreSQL → $DB_NAME"
echo -e "    RADIUS     : FreeRADIUS 3.0"
echo ""
echo -e "  ${CYAN}Test commands:${NC}"
echo -e "    psql -h localhost -U $DB_USER -d $DB_NAME"
echo -e "    radtest testuser testpass localhost 0 $FREERADIUS_SECRET"
echo -e "    curl -I https://$SERVER_NAME"
echo -e "    nginx -t"
echo -e "    systemctl status php${PHP_VERSION}-fpm"
echo ""
print_success "Selesai! Silakan akses dashboard di https://$SERVER_NAME/dashboard/"
