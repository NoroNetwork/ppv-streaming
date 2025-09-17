# PPV Streaming Platform

A secure, modern pay-per-view streaming platform built with PHP, featuring user portal and admin panel with MediaMTX integration for live streaming.

## Features

### User Portal
- 🎬 Stream browsing and discovery
- 💳 Secure payment processing with Stripe
- 📱 Responsive video player with HLS support
- 🔐 User authentication and authorization
- 📊 Real-time viewer statistics

### Admin Panel
- 📺 Stream management (create, edit, delete)
- 📊 Real-time analytics and statistics
- 🎛️ MediaMTX server integration
- 💰 Revenue tracking and reporting
- 👥 User management
- 🔒 Security monitoring

### Security Features
- 🛡️ Rate limiting and brute force protection
- 🔒 SQL injection and XSS prevention
- 🚨 Security event logging
- 🔐 JWT-based authentication
- 📝 Input validation and sanitization
- 🌐 Security headers and CSP

## Technology Stack

- **Backend**: PHP 8.1+
- **Database**: MySQL 8.0+
- **Streaming**: MediaMTX
- **Payments**: Stripe
- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **Video Player**: Video.js with HLS support
- **Authentication**: JWT tokens

## Installation

### Prerequisites

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer
- Web server (Apache/Nginx)
- MediaMTX server
- Stripe account

### Step 1: Clone and Install Dependencies

```bash
git clone <repository-url>
cd ppv-noronetwork
composer install
```

### Step 2: Environment Configuration

```bash
cp .env.example .env
```

Edit `.env` with your configuration:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=ppv_streaming
DB_USER=your_db_user
DB_PASS=your_db_password

# Security
JWT_SECRET=your-very-secure-jwt-secret-key-here
APP_SECRET=your-app-secret-key-here
BCRYPT_COST=12

# MediaMTX Configuration
MEDIAMTX_API_URL=http://localhost:9997/v3
MEDIAMTX_API_TOKEN=your_mediamtx_api_token

# Payment Configuration (Stripe)
STRIPE_PUBLIC_KEY=pk_test_your_stripe_public_key
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Application Settings
APP_URL=http://localhost:8000
ADMIN_URL=http://localhost:8000/admin
DEBUG=false
```

### Step 3: Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE ppv_streaming;"

# Import schema
mysql -u root -p ppv_streaming < database/schema.sql
mysql -u root -p ppv_streaming < database/security-tables.sql
```

### Step 4: MediaMTX Installation

Download and install MediaMTX:

```bash
# Download MediaMTX
wget https://github.com/bluenviron/mediamtx/releases/latest/download/mediamtx_linux_amd64.tar.gz
tar -xzf mediamtx_linux_amd64.tar.gz

# Configure MediaMTX
# Edit mediamtx.yml to enable API and set authentication
```

Example MediaMTX configuration:

```yaml
api: true
apiAddress: :9997

paths:
  all:
    source: publisher
    publishUser: streamer
    publishPass: secure_password
```

### Step 5: Web Server Configuration

#### Apache Configuration

Ensure `.htaccess` is enabled and mod_rewrite is active:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/ppv-noronetwork/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
}
```

### Step 6: Stripe Webhook Setup

1. Go to Stripe Dashboard → Webhooks
2. Add endpoint: `https://your-domain.com/api/payment/webhook`
3. Select events: `payment_intent.succeeded`
4. Copy webhook secret to `.env`

### Step 7: SSL Certificate (Production)

```bash
# Using Let's Encrypt
sudo certbot --apache -d your-domain.com
```

## Usage

### Default Admin Account

- Email: `admin@example.com`
- Password: `admin123`

**Important**: Change this password immediately after first login!

### Creating Streams

1. Login to admin panel: `/admin`
2. Go to Streams section
3. Click "Create New Stream"
4. Configure stream settings
5. Copy RTMP URL for broadcasting

### Broadcasting

Use OBS or similar software with the provided RTMP URL:
- Server: `rtmp://your-domain.com:1935/`
- Stream Key: Generated key from admin panel

### User Access

1. Users register/login on main site
2. Browse available streams
3. Purchase access (if paid)
4. Watch live or scheduled streams

## API Documentation

### Authentication Endpoints

```
POST /api/auth/login
POST /api/auth/register
POST /api/auth/logout
GET  /api/auth/me
```

### Stream Endpoints

```
GET  /api/streams
GET  /api/streams/{id}
GET  /api/streams/{id}/access
```

### Admin Endpoints

```
GET    /admin/api/streams
POST   /admin/api/streams
PUT    /admin/api/streams/{id}
DELETE /admin/api/streams/{id}
GET    /admin/api/streams/{id}/stats
GET    /admin/api/mediamtx/status
```

### Payment Endpoints

```
POST /api/payment/create-intent
POST /api/payment/webhook
```

## Security Considerations

### Production Checklist

- [ ] Change default admin password
- [ ] Set `DEBUG=false` in production
- [ ] Enable HTTPS with SSL certificate
- [ ] Configure firewall rules
- [ ] Regular security updates
- [ ] Monitor security logs
- [ ] Backup database regularly
- [ ] Use strong JWT secrets
- [ ] Enable rate limiting
- [ ] Configure CSP headers

### Rate Limiting

The platform includes built-in rate limiting:
- Login attempts: 10 per 15 minutes
- Registration: 3 per hour
- API requests: Configurable per endpoint

### Security Monitoring

Security events are logged to `security_logs` table:
- Failed login attempts
- Malicious input detection
- Registration attempts
- Admin actions

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `.env`
   - Ensure MySQL is running
   - Verify database exists

2. **MediaMTX Connection Failed**
   - Check MediaMTX is running
   - Verify API URL and token
   - Check firewall settings

3. **Stripe Webhook Failures**
   - Verify webhook URL is accessible
   - Check webhook secret matches
   - Review Stripe dashboard logs

4. **Video Player Not Loading**
   - Check HLS URL is accessible
   - Verify MediaMTX streaming is active
   - Check browser console for errors

### Log Files

- Application logs: Check web server error logs
- Security logs: Database `security_logs` table
- MediaMTX logs: MediaMTX console output
- Payment logs: Stripe dashboard

## Performance Optimization

### Database

- Enable query caching
- Index optimization for large datasets
- Regular ANALYZE TABLE operations

### Streaming

- Configure MediaMTX for optimal performance
- Use CDN for global distribution
- Implement adaptive bitrate streaming

### Caching

- Enable OPcache for PHP
- Use Redis/Memcached for session storage
- Configure browser caching headers

## Contributing

1. Fork the repository
2. Create feature branch
3. Implement changes with tests
4. Submit pull request

## License

This project is proprietary software. All rights reserved.

## Support

For technical support:
- Check troubleshooting guide
- Review security logs
- Contact system administrator