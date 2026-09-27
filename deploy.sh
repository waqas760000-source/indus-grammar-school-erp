#!/usr/bin/env bash
# ==============================================================================
# INDUS GRAMMAR SCHOOL ERP - ONE-CLICK PRODUCTION DEPLOYMENT SCRIPT (UBUNTU)
# ==============================================================================
set -e

echo "========================================================="
echo "   Indus Grammar School ERP - Ubuntu Production Deploy   "
echo "========================================================="

# 1. Check Root Privileges
if [ "$EUID" -ne 0 ]; then
  echo "[-] Please run this script as root (e.g. sudo bash deploy.sh)"
  exit 1
fi

# 2. Prompt for Domain and MySQL Password
read -p "Enter your Domain Name (e.g. erp.yourdomain.com): " DOMAIN_NAME
if [ -z "$DOMAIN_NAME" ]; then
  echo "[-] Domain name cannot be empty."
  exit 1
fi

read -sp "Enter a secure MySQL root password [Press Enter to use default]: " DB_PASSWORD
echo ""
DB_PASSWORD=${DB_PASSWORD:-"rH36@u2t"}

# 3. Update System & Install Core Tools
echo "[+] Updating Ubuntu packages..."
apt update && apt upgrade -y
apt install -y curl git ufw nginx certbot python3-certbot-nginx

# 4. Install Docker & Docker Compose if not present
if ! command -v docker &> /dev/null; then
    echo "[+] Installing Docker Engine..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
fi

# 5. Configure Firewall (UFW)
echo "[+] Configuring UFW Firewall..."
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw --force enable

# 6. Configure Environment File (.env.prod)
echo "[+] Generating .env.prod configuration..."
cat <<EOF > .env.prod
APP_ENV=production
APP_NAME="Indus Grammar School ERP"
APP_URL=https://${DOMAIN_NAME}
APP_TIMEZONE=Asia/Karachi
DB_HOST=db
DB_PORT=3306
DB_NAME=indus_grammar_school
DB_USER=root
DB_PASS=${DB_PASSWORD}
SECURE_SESSION=true
EOF

# 7. Start Production Docker Stack
echo "[+] Starting Docker containers..."
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build

# 8. Configure Nginx Reverse Proxy
echo "[+] Setting up Nginx virtual host for ${DOMAIN_NAME}..."
sed "s/YOUR_DOMAIN.COM/${DOMAIN_NAME}/g" nginx.conf.template > /etc/nginx/sites-available/${DOMAIN_NAME}.conf
ln -sf /etc/nginx/sites-available/${DOMAIN_NAME}.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# 9. Provision Free Let's Encrypt SSL
echo "[+] Generating Let's Encrypt SSL Certificate..."
certbot --nginx -d ${DOMAIN_NAME} --non-interactive --agree-tos --register-unsafely-without-email --redirect || {
    echo "[!] Certbot DNS validation failed. Ensure your DNS A-Record points to this VPS IP and run: certbot --nginx -d ${DOMAIN_NAME}"
}

# 10. Set Storage Permissions
chmod -R 775 ./indus-grammar-school-erp/storage
chmod -R 775 ./indus-grammar-school-erp/uploads

echo "========================================================="
echo "   🎉 DEPLOYMENT COMPLETE!                              "
echo "   URL: https://${DOMAIN_NAME}                          "
echo "========================================================="
