# Deployment Guide

This guide covers deploying the PPV Streaming Platform to production environments.

## Production Environment Requirements

### Server Specifications

**Minimum Requirements:**
- CPU: 2 cores
- RAM: 4GB
- Storage: 50GB SSD
- Bandwidth: 100Mbps

**Recommended (for 100+ concurrent users):**
- CPU: 4-8 cores
- RAM: 8-16GB
- Storage: 100GB+ SSD
- Bandwidth: 1Gbps

### Software Requirements

- Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- PHP 8.1+ with extensions: pdo_mysql, openssl, curl, gd, zip
- MySQL 8.0+ or MariaDB 10.5+
- Nginx 1.18+ or Apache 2.4+
- MediaMTX latest version
- SSL certificate (Let's Encrypt recommended)

## Step-by-Step Deployment

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-curl \
php8.1-gd php8.1-zip php8.1-xml php8.1-mbstring composer git certbot \
python3-certbot-nginx

# Install Node.js (for build tools if needed)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Database Setup

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE ppv_streaming;
CREATE USER 'ppv_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON ppv_streaming.* TO 'ppv_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Application Deployment

```bash
# Create application directory
sudo mkdir -p /var/www/ppv-streaming
cd /var/www/ppv-streaming

# Clone application (or upload files)
sudo git clone <your-repo-url> .

# Install dependencies
sudo composer install --no-dev --optimize-autoloader

# Set up environment
sudo cp .env.example .env
sudo nano .env  # Configure production settings

# Set correct permissions
sudo chown -R www-data:www-data /var/www/ppv-streaming
sudo chmod -R 755 /var/www/ppv-streaming
sudo chmod -R 775 storage/ bootstrap/cache/  # If using Laravel-style directories
```

### 4. Database Migration

```bash
# Import database schema
mysql -u ppv_user -p ppv_streaming < database/schema.sql
mysql -u ppv_user -p ppv_streaming < database/security-tables.sql
```

### 5. Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/ppv-streaming
```

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/ppv-streaming/public;
    index index.php index.html;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # CSP Header
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://vjs.zencdn.net https://cdn.jsdelivr.net https://js.stripe.com; style-src 'self' 'unsafe-inline' https://vjs.zencdn.net; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https://api.stripe.com; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';";

    # Block access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~* \.(env|md|log|sql|htaccess|htpasswd|ini|conf|yaml|yml)$ {
        deny all;
    }

    location ~* ^/(vendor|src|config|database|tests)/ {
        deny all;
    }

    # Main application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;

        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
}

# RTMP configuration for MediaMTX
server {
    listen 1935;
    application live {
        live on;
        allow publish all;
        allow play all;
        record off;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/ppv-streaming /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. SSL Certificate Setup

```bash
# Install SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal setup (should be automatic)
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 7. MediaMTX Installation

```bash
# Download and install MediaMTX
cd /opt
sudo wget https://github.com/bluenviron/mediamtx/releases/latest/download/mediamtx_linux_amd64.tar.gz
sudo tar -xzf mediamtx_linux_amd64.tar.gz
sudo mv mediamtx /usr/local/bin/

# Create MediaMTX user and directories
sudo useradd -r -s /bin/false mediamtx
sudo mkdir -p /etc/mediamtx /var/log/mediamtx
sudo chown mediamtx:mediamtx /var/log/mediamtx
```

Create MediaMTX configuration:

```bash
sudo nano /etc/mediamtx/mediamtx.yml
```

```yaml
# MediaMTX Configuration
logLevel: info
logDestinations: [file]
logFile: /var/log/mediamtx/mediamtx.log

# API
api: true
apiAddress: :9997

# RTMP
rtmp: true
rtmpAddress: :1935

# HLS
hls: true
hlsAddress: :8888
hlsAllowOrigin: "*"

# WebRTC
webrtc: true
webrtcAddress: :8889

# Paths
paths:
  all:
    source: publisher

    # Recording
    record: false
    recordPath: /var/recordings/%path/%Y-%m-%d_%H-%M-%S-%f.mp4
    recordFormat: mp4
    recordPartDuration: 1s
    recordSegmentDuration: 1h
    recordDeleteAfter: 24h

    # HLS
    hlsVariant: lowLatency
    hlsSegmentCount: 7
    hlsSegmentDuration: 1s
    hlsPartDuration: 200ms
    hlsSegmentMaxSize: 50M

    # Authentication (optional)
    # publishUser: streamer
    # publishPass: secure_password
    # readUser: viewer
    # readPass: secure_password
```

Create systemd service:

```bash
sudo nano /etc/systemd/system/mediamtx.service
```

```ini
[Unit]
Description=MediaMTX
After=network.target

[Service]
Type=simple
User=mediamtx
Group=mediamtx
ExecStart=/usr/local/bin/mediamtx /etc/mediamtx/mediamtx.yml
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Start MediaMTX:

```bash
sudo systemctl daemon-reload
sudo systemctl enable mediamtx
sudo systemctl start mediamtx
sudo systemctl status mediamtx
```

### 8. PHP-FPM Optimization

```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

```ini
[www]
user = www-data
group = www-data

listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_execution_time] = 300
```

```bash
sudo systemctl restart php8.1-fpm
```

### 9. MySQL Optimization

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

```ini
[mysqld]
# Performance
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Connections
max_connections = 500
max_user_connections = 450

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

```bash
sudo systemctl restart mysql
```

### 10. Firewall Configuration

```bash
# Install UFW
sudo apt install ufw

# Default policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow necessary ports
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 1935/tcp  # RTMP
sudo ufw allow 8888/tcp  # HLS
sudo ufw allow 9997/tcp  # MediaMTX API (restrict if needed)

# Enable firewall
sudo ufw enable
```

### 11. Monitoring Setup

Install monitoring tools:

```bash
# Install htop and netstat
sudo apt install htop net-tools

# Install log monitoring
sudo apt install logwatch

# Create monitoring script
sudo nano /usr/local/bin/ppv-monitor.sh
```

```bash
#!/bin/bash

# PPV Platform Monitoring Script

LOG_FILE="/var/log/ppv-monitor.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$DATE] Starting monitoring check..." >> $LOG_FILE

# Check Nginx
if ! systemctl is-active --quiet nginx; then
    echo "[$DATE] ALERT: Nginx is down" >> $LOG_FILE
    systemctl restart nginx
fi

# Check PHP-FPM
if ! systemctl is-active --quiet php8.1-fpm; then
    echo "[$DATE] ALERT: PHP-FPM is down" >> $LOG_FILE
    systemctl restart php8.1-fpm
fi

# Check MySQL
if ! systemctl is-active --quiet mysql; then
    echo "[$DATE] ALERT: MySQL is down" >> $LOG_FILE
    systemctl restart mysql
fi

# Check MediaMTX
if ! systemctl is-active --quiet mediamtx; then
    echo "[$DATE] ALERT: MediaMTX is down" >> $LOG_FILE
    systemctl restart mediamtx
fi

# Check disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 85 ]; then
    echo "[$DATE] WARNING: Disk usage is ${DISK_USAGE}%" >> $LOG_FILE
fi

# Check memory usage
MEM_USAGE=$(free | awk 'NR==2{printf "%.2f", $3*100/$2}')
if (( $(echo "$MEM_USAGE > 90" | bc -l) )); then
    echo "[$DATE] WARNING: Memory usage is ${MEM_USAGE}%" >> $LOG_FILE
fi

echo "[$DATE] Monitoring check completed" >> $LOG_FILE
```

```bash
sudo chmod +x /usr/local/bin/ppv-monitor.sh

# Add to crontab
sudo crontab -e
# Add: */5 * * * * /usr/local/bin/ppv-monitor.sh
```

## Security Hardening

### 1. SSH Security

```bash
sudo nano /etc/ssh/sshd_config
```

```
Port 2222  # Change from default 22
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
```

### 2. Fail2Ban Setup

```bash
sudo apt install fail2ban

sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
action = iptables-multiport[name=ReqLimit, port="http,https", protocol=tcp]
logpath = /var/log/nginx/error.log
maxretry = 10

[php-url-fopen]
enabled = true
filter = php-url-fopen
action = iptables-multiport[name=php-url-fopen, port="http,https", protocol=tcp]
logpath = /var/log/nginx/access.log
maxretry = 1
```

### 3. Automated Backups

```bash
sudo nano /usr/local/bin/ppv-backup.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/backups"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="ppv_streaming"
DB_USER="ppv_user"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Application files backup
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz -C /var/www ppv-streaming

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/ppv-backup.sh

# Add to crontab for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/ppv-backup.sh
```

## Performance Tuning

### 1. Enable Caching

```bash
# Install Redis
sudo apt install redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Uncomment: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru

sudo systemctl restart redis-server
```

### 2. Enable OPcache

```bash
sudo nano /etc/php/8.1/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### 3. Optimize Nginx

```bash
sudo nano /etc/nginx/nginx.conf
```

```nginx
worker_processes auto;
worker_connections 1024;

http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 30;
    types_hash_max_size 2048;

    client_max_body_size 50M;
    client_body_timeout 30;
    client_header_timeout 30;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```

## Maintenance

### Regular Tasks

1. **Daily:**
   - Check system status
   - Review error logs
   - Monitor disk space

2. **Weekly:**
   - Update system packages
   - Review security logs
   - Check backup integrity

3. **Monthly:**
   - Security audit
   - Performance review
   - Update SSL certificates (if needed)

### Log Locations

- Nginx: `/var/log/nginx/`
- PHP-FPM: `/var/log/php8.1-fpm.log`
- MySQL: `/var/log/mysql/`
- MediaMTX: `/var/log/mediamtx/`
- Application: Database `security_logs` table

## Troubleshooting Production Issues

### Common Problems

1. **High CPU Usage:**
   - Check for runaway processes
   - Review slow query log
   - Monitor MediaMTX streams

2. **Memory Issues:**
   - Check PHP memory limits
   - Review MySQL buffer sizes
   - Monitor cache usage

3. **Database Locks:**
   - Check for long-running queries
   - Review table locks
   - Optimize problematic queries

4. **Streaming Issues:**
   - Verify MediaMTX status
   - Check network connectivity
   - Review firewall rules

### Emergency Procedures

1. **Service Down:**
   ```bash
   sudo systemctl restart nginx php8.1-fpm mysql mediamtx
   ```

2. **Database Corruption:**
   ```bash
   sudo mysqlcheck -u root -p --auto-repair --all-databases
   ```

3. **Disk Full:**
   ```bash
   # Clear logs
   sudo journalctl --vacuum-time=3d

   # Clear temp files
   sudo rm -rf /tmp/*

   # Check large files
   sudo du -h / | sort -rh | head -20
   ```

This deployment guide provides a comprehensive foundation for running the PPV Streaming Platform in production. Adjust configurations based on your specific requirements and traffic patterns.