#!/bin/bash
# ============================================================
# Install pgAdmin 4 using Docker
# Ini adalah cara paling aman agar tidak bentrok dengan Nginx utama
# ============================================================

# Warna
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}============================================================${NC}"
echo -e "${CYAN} Install pgAdmin4 via Docker${NC}"
echo -e "${CYAN}============================================================${NC}"

# Cek apakah docker sudah terinstall
if ! command -v docker &> /dev/null; then
    echo "Docker belum terinstall. Menginstal Docker..."
    if ! command -v curl &> /dev/null; then
        echo "Menginstal curl..."
        apt install -y curl
    fi
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    rm get-docker.sh
    echo -e "${GREEN}✓ Docker berhasil diinstal${NC}"
else
    echo -e "${GREEN}✓ Docker sudah terinstal${NC}"
fi

# Variabel konfigurasi pgAdmin
# Anda bisa mengubah email dan password ini sebelum menjalankan script
PGADMIN_EMAIL="admin@fanns.my.id"
PGADMIN_PASS="pgadmin123"
PGADMIN_PORT="5050"

echo ""
echo "Mengunduh dan menjalankan container pgAdmin4..."
# Menjalankan pgAdmin di background (-d), menggunakan port 5050
sudo docker run -p ${PGADMIN_PORT}:80 \
    -e "PGADMIN_DEFAULT_EMAIL=${PGADMIN_EMAIL}" \
    -e "PGADMIN_DEFAULT_PASSWORD=${PGADMIN_PASS}" \
    --name pgadmin4 \
    --restart unless-stopped \
    -d dpage/pgadmin4

echo -e "${CYAN}============================================================${NC}"
echo -e "${GREEN}✓ pgAdmin4 berhasil dijalankan!${NC}"
echo -e "Akses pgAdmin melalui browser Anda:"
echo -e "URL      : http://portal.fanns.my.id:${PGADMIN_PORT}"
echo -e "Email    : ${PGADMIN_EMAIL}"
echo -e "Password : ${PGADMIN_PASS}"
echo -e "${CYAN}============================================================${NC}"
echo "Catatan: Pastikan Anda telah membuka port ${PGADMIN_PORT} di MikroTik/Firewall Anda"
echo "jika Anda ingin mengaksesnya dari luar jaringan server."
