# Docker Host Deployment Guide

This guide explains how to deploy the PPV Streaming Platform on your Docker host.

## Quick Start

### 1. Clone Repository on Docker Host

```bash
git clone https://github.com/NoroNetwork/ppv-streaming.git
cd ppv-streaming
```

### 2. Production Deployment (Recommended)

```bash
# Create environment file
cp .env.production .env

# Edit environment variables
nano .env

# Deploy using production compose
docker-compose -f docker-compose.prod.yml up -d
```

### 3. Development Deployment (Build from Source)

```bash
# Create environment file
cp .env.example .env

# Edit environment variables
nano .env

# Deploy and build
docker-compose up -d --build
```

## Environment Configuration

### Required Environment Variables

Create a `.env` file with these variables:

```bash
# Database
DB_PASSWORD=your_secure_database_password
MYSQL_ROOT_PASSWORD=your_secure_root_password

# Application Security
JWT_SECRET=your_64_character_jwt_secret_key_here_make_it_random_and_secure
APP_SECRET=your_64_character_app_secret_key_here_make_it_random_and_secure

# Stripe Payment
STRIPE_SECRET_KEY=sk_live_your_stripe_secret_key
STRIPE_PUBLIC_KEY=pk_live_your_stripe_public_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# MediaMTX (optional)
MEDIAMTX_API_TOKEN=your_mediamtx_api_token
```

### Generate Secure Secrets

```bash
# Generate JWT and App secrets
openssl rand -hex 32
openssl rand -hex 32

# Generate strong database passwords
openssl rand -base64 32
```

## Service Access

After deployment, services will be available on:

- **Web Application**: http://your-docker-host
- **Admin Panel**: http://your-docker-host/admin
- **RTMP Streaming**: rtmp://your-docker-host:1935/
- **HLS Streaming**: http://your-docker-host:8888/
- **MediaMTX API**: http://your-docker-host:9997/v3/

### Default Admin Login

- Email: `admin@example.com`
- Password: `admin123`

**⚠️ Change this immediately after first login!**

## Deployment Commands

### Production Deployment

```bash
# Start services
docker-compose -f docker-compose.prod.yml up -d

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Stop services
docker-compose -f docker-compose.prod.yml down

# Update to latest version
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d
```

### Development Deployment

```bash
# Start and build
docker-compose up -d --build

# Rebuild single service
docker-compose up -d --build app

# View logs
docker-compose logs -f app

# Stop services
docker-compose down
```

## Database Setup

The database will be automatically initialized on first run. If you need to manually run migrations:

```bash
# Access MySQL container
docker-compose exec mysql mysql -u ppv_user -p ppv_streaming

# Or restore from backup
docker-compose exec mysql mysql -u ppv_user -p ppv_streaming < backup.sql
```

## Health Checks

Check if services are running:

```bash
# Check service status
docker-compose ps

# Check application health
curl http://your-docker-host/health

# Check MediaMTX status
curl http://your-docker-host:9997/v3/config

# Check logs
docker-compose logs app
docker-compose logs mysql
docker-compose logs mediamtx
```

## Backup and Restore

### Backup

```bash
# Database backup
docker-compose exec mysql mysqldump -u ppv_user -p ppv_streaming > backup_$(date +%Y%m%d).sql

# Volume backup
docker run --rm -v ppv-streaming_mysql_data:/data -v $(pwd):/backup alpine tar czf /backup/mysql_backup_$(date +%Y%m%d).tar.gz /data

# Full application backup
docker run --rm -v ppv-streaming_app_storage:/app_storage -v ppv-streaming_app_uploads:/app_uploads -v $(pwd):/backup alpine tar czf /backup/app_backup_$(date +%Y%m%d).tar.gz /app_storage /app_uploads
```

### Restore

```bash
# Database restore
docker-compose exec mysql mysql -u ppv_user -p ppv_streaming < backup_20240101.sql

# Volume restore
docker run --rm -v ppv-streaming_mysql_data:/data -v $(pwd):/backup alpine tar xzf /backup/mysql_backup_20240101.tar.gz -C /
```

## Monitoring

### Log Monitoring

```bash
# Real-time logs
docker-compose logs -f

# Application logs only
docker-compose logs -f app

# Database logs
docker-compose logs -f mysql

# Streaming logs
docker-compose logs -f mediamtx
```

### Resource Monitoring

```bash
# Container stats
docker stats

# Disk usage
docker system df

# Volume usage
docker volume ls
```

## Troubleshooting

### Common Issues

1. **Services won't start**
   ```bash
   # Check logs
   docker-compose logs

   # Check system resources
   docker system df
   df -h
   ```

2. **Database connection issues**
   ```bash
   # Check MySQL status
   docker-compose exec mysql mysqladmin -u root -p ping

   # Reset database
   docker-compose down
   docker volume rm ppv-streaming_mysql_data
   docker-compose up -d
   ```

3. **Streaming not working**
   ```bash
   # Check MediaMTX logs
   docker-compose logs mediamtx

   # Test MediaMTX API
   curl http://localhost:9997/v3/config
   ```

4. **Application errors**
   ```bash
   # Check PHP logs
   docker-compose exec app tail -f /var/log/php_errors.log

   # Check nginx logs
   docker-compose exec app tail -f /var/log/nginx/error.log
   ```

### Reset Everything

```bash
# Stop and remove everything
docker-compose down -v

# Remove all volumes (⚠️ This deletes all data!)
docker volume prune

# Remove all images
docker-compose down --rmi all

# Start fresh
docker-compose up -d --build
```

## Security Considerations

### Firewall Configuration

```bash
# Allow necessary ports
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 1935/tcp  # RTMP
ufw allow 8888/tcp  # HLS

# Block direct database access from outside
ufw deny 3306/tcp
```

### SSL/TLS Setup

For production, use a reverse proxy like Nginx or Traefik with Let's Encrypt:

```bash
# Example with Nginx
server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    location / {
        proxy_pass http://localhost:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Regular Maintenance

```bash
# Update containers monthly
docker-compose pull
docker-compose up -d

# Clean up unused images/volumes
docker system prune -a

# Backup database weekly
docker-compose exec mysql mysqldump -u ppv_user -p ppv_streaming > backup_$(date +%Y%m%d).sql
```

## Performance Tuning

### Resource Limits

Add to your docker-compose file:

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
        reservations:
          memory: 512M

  mysql:
    deploy:
      resources:
        limits:
          memory: 1G
        reservations:
          memory: 512M
```

### MySQL Optimization

Create `mysql.cnf` file:

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 256M
max_connections = 500
```

Mount it in docker-compose:

```yaml
mysql:
  volumes:
    - ./mysql.cnf:/etc/mysql/conf.d/mysql.cnf
```

This deployment guide provides everything needed to run the PPV Streaming Platform on your Docker host.