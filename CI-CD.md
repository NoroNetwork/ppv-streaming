# CI/CD Pipeline Documentation

This document describes the Continuous Integration and Continuous Deployment pipeline for the PPV Streaming Platform.

## Overview

The CI/CD pipeline is implemented using GitHub Actions and includes:

- **Continuous Integration (CI)**: Automated testing, code quality checks, and security scanning
- **Continuous Deployment (CD)**: Automated deployment to staging and production environments
- **Infrastructure as Code**: Docker containers and deployment scripts
- **Environment Management**: Separate configurations for development, staging, and production

## Pipeline Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Development   │    │     Staging     │    │   Production    │
│                 │    │                 │    │                 │
│ • Local testing │    │ • Auto deploy   │    │ • Manual deploy │
│ • Docker dev    │    │ • Integration   │    │ • Tag releases  │
│ • Hot reload    │    │ • E2E testing   │    │ • Health checks │
└─────────────────┘    └─────────────────┘    └─────────────────┘
        │                        │                        │
        │                        │                        │
        ▼                        ▼                        ▼
┌──────────────────────────────────────────────────────────────┐
│                    GitHub Actions                           │
│                                                              │
│ CI Pipeline (ci.yml)           CD Pipeline (cd.yml)         │
│ • PHP 8.1, 8.2, 8.3          • Build & Push Images         │
│ • Unit & Integration Tests     • Deploy to Staging          │
│ • Code Quality (PHPStan)       • Deploy to Production       │
│ • Security Scanning            • Health Checks              │
│ • Docker Build                 • Rollback on Failure        │
└──────────────────────────────────────────────────────────────┘
```

## Workflow Triggers

### CI Pipeline (`.github/workflows/ci.yml`)

**Triggers:**
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches

**Jobs:**
1. **test** - Run PHPUnit tests across PHP versions
2. **code-quality** - Static analysis and code style checks
3. **security-scan** - Security vulnerability scanning
4. **lint** - PHP syntax and formatting validation
5. **validate-environment** - Environment configuration checks
6. **docker-build** - Docker image build test

### CD Pipeline (`.github/workflows/cd.yml`)

**Triggers:**
- Push to `main` branch (staging deployment)
- Tagged releases `v*` (production deployment)
- Successful CI pipeline completion

**Jobs:**
1. **build-and-push** - Build and push Docker images
2. **deploy-staging** - Automatic staging deployment
3. **deploy-production** - Manual production deployment
4. **rollback** - Automatic rollback on failure

## Environment Setup

### Required GitHub Secrets

#### Staging Environment
```
STAGING_HOST=staging.your-domain.com
STAGING_USER=deploy
STAGING_SSH_KEY=<private-ssh-key>
STAGING_DB_USER=<database-user>
STAGING_DB_PASS=<database-password>
STAGING_DB_NAME=ppv_streaming_staging
```

#### Production Environment
```
PRODUCTION_HOST=your-domain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=<private-ssh-key>
PRODUCTION_DB_USER=<database-user>
PRODUCTION_DB_PASS=<database-password>
PRODUCTION_DB_NAME=ppv_streaming
```

#### Third-party Services
```
SNYK_TOKEN=<snyk-security-token>
SLACK_WEBHOOK_URL=<slack-webhook-for-notifications>
CODECOV_TOKEN=<codecov-upload-token>
```

### Setting Up Secrets

1. Go to GitHub repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Add each secret listed above

## Local Development

### Using Docker Compose

```bash
# Start development environment
docker-compose up -d

# View logs
docker-compose logs -f app

# Run tests
docker-compose exec app vendor/bin/phpunit

# Stop environment
docker-compose down
```

### Manual Setup

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Run tests
vendor/bin/phpunit

# Code quality checks
vendor/bin/phpcs --standard=PSR12 src/
vendor/bin/phpstan analyse src/
vendor/bin/psalm
```

## Testing Strategy

### Test Pyramid

```
                    ┌─────────────────┐
                    │  E2E Tests      │ ← Browser automation
                    │  (Manual/Auto)  │
                    └─────────────────┘
                ┌─────────────────────────┐
                │  Integration Tests      │ ← API endpoints
                │  (Feature Tests)        │   Database integration
                └─────────────────────────┘
        ┌─────────────────────────────────────────┐
        │          Unit Tests                     │ ← Individual classes
        │     (Security, Validation, Logic)      │   Pure functions
        └─────────────────────────────────────────┘
```

### Test Categories

1. **Unit Tests** (`tests/Unit/`)
   - Security functions
   - Validation logic
   - Utility classes
   - Business logic

2. **Integration Tests** (`tests/Integration/`)
   - Database operations
   - External API calls
   - Service interactions

3. **Feature Tests** (`tests/Feature/`)
   - HTTP endpoints
   - Authentication flows
   - User workflows

### Coverage Requirements

- **Minimum**: 70% code coverage
- **Target**: 85% code coverage
- **Critical paths**: 95% coverage (authentication, payments, security)

## Code Quality Gates

### Automated Checks

1. **PHP CodeSniffer** - PSR-12 compliance
2. **PHPStan** - Static analysis (level 8)
3. **Psalm** - Type checking and bug detection
4. **PHP-CS-Fixer** - Code formatting
5. **Security Checker** - Vulnerability scanning

### Quality Metrics

```yaml
Quality Gates:
  - Code Coverage: ≥ 70%
  - PHPStan: Level 8, 0 errors
  - Psalm: Error level 3, 0 errors
  - Security: 0 high/critical vulnerabilities
  - Performance: Response time < 200ms (95th percentile)
```

## Deployment Process

### Staging Deployment (Automatic)

1. **Trigger**: Push to `main` branch
2. **Process**:
   - CI pipeline passes ✅
   - Build Docker image
   - Deploy to staging server
   - Run smoke tests
   - Send Slack notification

```bash
# Manual staging deployment
./scripts/deploy.sh staging
```

### Production Deployment (Manual)

1. **Trigger**: Git tag `v*` (e.g., `v1.2.3`)
2. **Process**:
   - Create maintenance page
   - Backup current version
   - Deploy new version
   - Run health checks
   - Remove maintenance page
   - Send notifications

```bash
# Create production release
git tag v1.2.3
git push origin v1.2.3

# Manual production deployment
./scripts/deploy.sh production
```

### Deployment Checklist

**Pre-deployment:**
- [ ] All tests passing
- [ ] Code review approved
- [ ] Security scan clean
- [ ] Database migrations ready
- [ ] Backup verified

**Post-deployment:**
- [ ] Health checks passing
- [ ] Monitoring alerts clear
- [ ] Performance metrics normal
- [ ] User acceptance testing
- [ ] Rollback plan ready

## Monitoring and Observability

### Health Checks

```bash
# Application health
curl https://your-domain.com/health

# Database connectivity
curl https://your-domain.com/health/database

# MediaMTX status
curl https://your-domain.com/admin/api/mediamtx/status
```

### Key Metrics

1. **Application Metrics**
   - Response time (95th percentile)
   - Error rate
   - Throughput (requests/second)

2. **Infrastructure Metrics**
   - CPU usage
   - Memory consumption
   - Disk I/O
   - Network latency

3. **Business Metrics**
   - Active streams
   - User registrations
   - Payment transactions
   - Viewer count

### Log Aggregation

```bash
# Application logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# PHP-FPM logs
tail -f /var/log/php8.1-fpm.log

# Database logs
tail -f /var/log/mysql/error.log

# Security logs
mysql -e "SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 10"
```

## Security in CI/CD

### Security Scanning

1. **Dependency Scanning**
   - Composer security checker
   - Snyk vulnerability database
   - GitHub Dependabot

2. **Code Scanning**
   - Static analysis for security issues
   - SQL injection detection
   - XSS vulnerability checks

3. **Infrastructure Scanning**
   - Docker image scanning
   - Configuration validation
   - Secrets detection

### Secrets Management

- **Environment Variables**: Stored in GitHub Secrets
- **Encryption**: All secrets encrypted at rest
- **Rotation**: Regular secret rotation policy
- **Access Control**: Minimum privilege principle

## Rollback Procedures

### Automatic Rollback

Triggered when:
- Health checks fail after deployment
- Error rate exceeds threshold
- Critical monitoring alerts

### Manual Rollback

```bash
# Using deployment script
./scripts/deploy.sh production --rollback

# SSH to server
ssh deploy@your-domain.com
sudo systemctl stop php8.1-fpm nginx
sudo cp -r /var/backups/ppv-backup-latest /var/www/ppv-streaming
sudo systemctl start php8.1-fpm nginx
```

### Rollback Testing

- Monthly rollback drills
- Automated rollback validation
- Recovery time objectives (RTO): < 5 minutes

## Troubleshooting

### Common Issues

1. **CI Pipeline Failures**
   ```bash
   # Check test logs
   cat test-results.xml

   # Review code quality issues
   cat phpcs-report.xml
   cat phpstan-report.xml
   ```

2. **Deployment Failures**
   ```bash
   # Check deployment logs
   ssh deploy@server "journalctl -u deploy-service -n 50"

   # Verify service status
   ssh deploy@server "systemctl status php8.1-fpm nginx mysql"
   ```

3. **Performance Issues**
   ```bash
   # Check resource usage
   ssh deploy@server "htop"

   # Review slow queries
   ssh deploy@server "mysql -e 'SHOW PROCESSLIST'"
   ```

### Debug Commands

```bash
# Local development
docker-compose logs app
docker-compose exec app bash

# CI/CD debugging
gh workflow list
gh run list --workflow=ci.yml
gh run view <run-id>

# Server debugging
ssh deploy@server
sudo journalctl -f
sudo tail -f /var/log/nginx/error.log
```

## Performance Optimization

### Build Optimization

1. **Docker Layer Caching**
   - Multi-stage builds
   - Dependency caching
   - Composer cache

2. **Parallel Execution**
   - Matrix builds for PHP versions
   - Parallel test execution
   - Concurrent deployments

### Pipeline Performance

- **Target Build Time**: < 10 minutes
- **Target Test Time**: < 5 minutes
- **Target Deployment Time**: < 3 minutes

## Compliance and Governance

### Code Review Requirements

- [ ] Two approvals required for production
- [ ] Security team review for sensitive changes
- [ ] Automated checks must pass
- [ ] Documentation updated

### Audit Trail

- All deployments logged
- Change tracking in Git
- Security events recorded
- Performance metrics archived

### Compliance Checks

- GDPR compliance validation
- PCI DSS requirements (payments)
- Security best practices
- Performance benchmarks

## Continuous Improvement

### Pipeline Metrics

- Build success rate
- Test execution time
- Deployment frequency
- Mean time to recovery (MTTR)

### Regular Reviews

- **Weekly**: Pipeline performance review
- **Monthly**: Security scan review
- **Quarterly**: Infrastructure optimization
- **Annually**: Disaster recovery testing

### Feedback Loop

1. Monitor pipeline metrics
2. Identify bottlenecks
3. Implement improvements
4. Measure impact
5. Iterate

This CI/CD pipeline provides a robust, secure, and efficient deployment process for the PPV Streaming Platform, ensuring high-quality releases and minimal downtime.