# Deployment & Scaling Guide - قطر فين

## 1. Infrastructure Architecture

### A. Production Environment Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRODUCTION ARCHITECTURE                      │
└─────────────────────────────────────────────────────────────────┘

                    ┌─────────────────┐
                    │   CloudFlare    │
                    │   (CDN & WAF)   │
                    └─────────┬───────┘
                              │
                    ┌─────────▼───────┐
                    │  Load Balancer  │
                    │   (AWS ALB)     │
                    └─────────┬───────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
    ┌─────────▼───────┐ ┌─────▼─────┐ ┌─────▼─────┐
    │   Web Server    │ │Web Server │ │Web Server │
    │  (Laravel API)  │ │(Laravel)  │ │(Laravel)  │
    │     ECS Task    │ │ ECS Task  │ │ ECS Task  │
    └─────────┬───────┘ └─────┬─────┘ └─────┬─────┘
              │               │               │
              └───────────────┼───────────────┘
                              │
                    ┌─────────▼───────┐
                    │   Database      │
                    │   (RDS MySQL)   │
                    │ Master + Slaves │
                    └─────────┬───────┘
                              │
                    ┌─────────▼───────┐
                    │     Redis       │
                    │   (ElastiCache) │
                    │ Cache + Session │
                    └─────────────────┘

        ┌─────────────────┐    ┌─────────────────┐
        │  WebSocket      │    │  File Storage   │
        │  (Reverb on     │    │    (S3 +       │
        │   ECS Fargate)  │    │   CloudFront)   │
        └─────────────────┘    └─────────────────┘
```

### B. AWS Infrastructure Components

#### 1. Compute Layer
```yaml
# ECS Cluster Configuration
Services:
  - Laravel API (3-10 tasks)
  - Laravel Reverb WebSocket (2-5 tasks)
  - Queue Workers (2-8 tasks)
  - Scheduler (1 task)

Instance Types:
  - API: t3.medium to c5.large
  - WebSocket: t3.small to t3.medium
  - Workers: t3.small to t3.large
  - Scheduler: t3.micro
```

#### 2. Database Layer
```yaml
RDS Configuration:
  Engine: MySQL 8.0
  Instance: db.r5.large (or larger)
  Multi-AZ: true
  Read Replicas: 2-3 instances
  Storage: GP3 SSD, 500GB-2TB
  Backup: 7-30 days retention

Redis Configuration:
  Engine: Redis 7.x
  Instance: cache.r6g.large
  Cluster Mode: enabled
  Replicas: 2-3 per shard
  Backup: enabled
```

#### 3. Storage & CDN
```yaml
S3 Buckets:
  - App Assets (images, audio)
  - User Uploads (avatars)
  - Backups & Logs
  - Static Files

CloudFront:
  - Global CDN distribution
  - API caching (selective)
  - Asset optimization
  - Geographic restrictions
```

## 2. Containerization with Docker

### A. Laravel API Dockerfile

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    redis \
    curl \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js for asset compilation
RUN apk add --no-cache nodejs npm

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy application code
COPY . .

# Generate autoloader and optimize
RUN composer dump-autoload --optimize --no-dev

# Install and build assets
RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Copy configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### B. WebSocket Dockerfile (Reverb)

```dockerfile
# Dockerfile.reverb
FROM php:8.3-cli-alpine

# Install dependencies
RUN apk add --no-cache \
    mysql-client \
    redis \
    curl \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy application
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Expose WebSocket port
EXPOSE 8080

# Run Reverb server
CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
```

### C. Docker Compose for Development

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:80"
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - mysql
      - redis

  reverb:
    build:
      context: .
      dockerfile: Dockerfile.reverb
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: qatar_fein
      MYSQL_USER: qatar_fein
      MYSQL_PASSWORD: password
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx-proxy.conf:/etc/nginx/nginx.conf
    depends_on:
      - app

volumes:
  mysql_data:
  redis_data:
```

## 3. CI/CD Pipeline

### A. GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy Qatar Fein

on:
  push:
    branches: [main, staging]

env:
  AWS_REGION: me-south-1
  ECR_REPOSITORY: qatar-fein
  ECS_SERVICE: qatar-fein-api
  ECS_CLUSTER: qatar-fein-cluster
  ECS_TASK_DEFINITION: .aws/task-definition.json

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_mysql, redis
          
      - name: Install Dependencies
        run: composer install --no-progress --no-suggest --prefer-dist --optimize-autoloader
        
      - name: Run Tests
        run: |
          cp .env.testing .env
          php artisan key:generate
          php artisan test --coverage-clover coverage.xml
          
      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v2
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Login to Amazon ECR
        id: login-ecr
        uses: aws-actions/amazon-ecr-login@v1

      - name: Build and push API image
        env:
          ECR_REGISTRY: ${{ steps.login-ecr.outputs.registry }}
          IMAGE_TAG: ${{ github.sha }}
        run: |
          docker build -t $ECR_REGISTRY/$ECR_REPOSITORY:$IMAGE_TAG .
          docker push $ECR_REGISTRY/$ECR_REPOSITORY:$IMAGE_TAG

      - name: Build and push Reverb image
        env:
          ECR_REGISTRY: ${{ steps.login-ecr.outputs.registry }}
          IMAGE_TAG: ${{ github.sha }}
        run: |
          docker build -t $ECR_REGISTRY/$ECR_REPOSITORY-reverb:$IMAGE_TAG -f Dockerfile.reverb .
          docker push $ECR_REGISTRY/$ECR_REPOSITORY-reverb:$IMAGE_TAG

      - name: Deploy to ECS
        uses: aws-actions/amazon-ecs-deploy-task-definition@v1
        with:
          task-definition: ${{ env.ECS_TASK_DEFINITION }}
          service: ${{ env.ECS_SERVICE }}
          cluster: ${{ env.ECS_CLUSTER }}
          wait-for-service-stability: true

  deploy-mobile:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Flutter
        uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.24.0'
          
      - name: Build Android APK
        run: |
          cd qatar-fein-flutter
          flutter pub get
          flutter build apk --release
          
      - name: Upload to Play Store
        uses: r0adkll/upload-google-play@v1
        with:
          serviceAccountJsonPlainText: ${{ secrets.GOOGLE_PLAY_SERVICE_ACCOUNT }}
          packageName: com.qatarfein.app
          releaseFiles: qatar-fein-flutter/build/app/outputs/flutter-apk/app-release.apk
          track: internal
```

## 4. Scaling Strategies

### A. Horizontal Scaling

#### Auto Scaling Configuration
```yaml
# ECS Auto Scaling
API Service:
  Min Capacity: 3
  Max Capacity: 20
  Target CPU: 70%
  Target Memory: 80%
  Scale Out Cooldown: 300s
  Scale In Cooldown: 300s

WebSocket Service:
  Min Capacity: 2
  Max Capacity: 10
  Target CPU: 60%
  Target Memory: 70%
  
Queue Workers:
  Min Capacity: 2
  Max Capacity: 15
  Target Queue Depth: 10 jobs
  Scale based on SQS queue length
```

#### Database Scaling
```yaml
Read Replicas:
  - Automatic failover
  - Read query distribution
  - Lag monitoring < 1 second

Connection Pooling:
  - PgBouncer for PostgreSQL
  - MySQL connection pooling
  - Max connections: 100-500

Caching Strategy:
  - Redis cluster mode
  - Cache-aside pattern
  - TTL-based expiration
  - Cache warming strategies
```

### B. Vertical Scaling Guidelines

```yaml
Instance Sizing:
  Development:
    API: t3.small (2 vCPU, 2GB RAM)
    DB: db.t3.micro
    Redis: cache.t3.micro
    
  Staging:
    API: t3.medium (2 vCPU, 4GB RAM)
    DB: db.t3.small
    Redis: cache.t3.small
    
  Production:
    API: c5.large+ (2+ vCPU, 4+ GB RAM)
    DB: db.r5.large+ (2+ vCPU, 16+ GB RAM)
    Redis: cache.r6g.large+

Performance Thresholds:
  Scale Up When:
    - CPU > 80% for 5 minutes
    - Memory > 85% for 5 minutes
    - Response time > 2 seconds
    - Error rate > 1%
```

## 5. Monitoring & Observability

### A. Application Performance Monitoring

```yaml
# CloudWatch Metrics
API Metrics:
  - Request count and latency
  - Error rates (4xx, 5xx)
  - Database query performance
  - Cache hit/miss ratios
  - Queue depth and processing time

WebSocket Metrics:
  - Connection count
  - Message throughput
  - Connection duration
  - Broadcast latency

Business Metrics:
  - Active users
  - Train assignments
  - Community messages
  - Revenue (ads)
```

### B. Logging Strategy

```yaml
# Centralized Logging
Log Aggregation:
  - AWS CloudWatch Logs
  - Structured JSON logging
  - Log retention: 30-365 days
  - Real-time streaming

Log Levels:
  - ERROR: System errors, exceptions
  - WARN: Performance issues, deprecations
  - INFO: Business events, API calls
  - DEBUG: Development debugging

Security Logs:
  - Authentication attempts
  - Authorization failures
  - Suspicious activities
  - Data access patterns
```

### C. Alerting Configuration

```yaml
# Critical Alerts (24/7)
System Health:
  - Service unavailability > 1 minute
  - Error rate > 5%
  - Database connection failures
  - Redis unavailability

Performance:
  - Response time > 5 seconds
  - CPU usage > 90% for 10 minutes
  - Memory usage > 95%
  - Disk space > 85%

# Warning Alerts (Business Hours)
Capacity Planning:
  - CPU usage > 70% for 30 minutes
  - Memory usage > 80% for 30 minutes
  - Unusual traffic patterns
  - Queue backlog > 100 jobs

Business Metrics:
  - User registration drops
  - Assignment completion rate drops
  - WebSocket disconnection spikes
```

## 6. Security Hardening

### A. Network Security

```yaml
# VPC Configuration
Subnets:
  Public: Load balancers, NAT gateways
  Private: Application servers, databases
  Isolated: Sensitive data processing

Security Groups:
  Web Tier: Ports 80, 443 from ALB only
  App Tier: Port 8080 from web tier only
  Data Tier: Port 3306/5432 from app tier only
  Redis: Port 6379 from app tier only

# WAF Rules
Protection Against:
  - SQL injection
  - XSS attacks
  - DDoS attacks
  - Geographic restrictions
  - Rate limiting per IP
```

### B. Application Security

```yaml
# SSL/TLS Configuration
Certificates:
  - AWS Certificate Manager
  - Automatic renewal
  - TLS 1.2+ only
  - HSTS headers

API Security:
  - JWT token validation
  - Rate limiting (100 req/min per user)
  - Input validation and sanitization
  - CORS configuration
  - API versioning

Data Protection:
  - Encryption at rest (AES-256)
  - Encryption in transit (TLS)
  - Personal data anonymization
  - GDPR compliance measures
```

## 7. Disaster Recovery

### A. Backup Strategy

```yaml
# Database Backups
Automated Backups:
  - Daily full backups
  - Transaction log backups every 15 minutes
  - Cross-region backup copies
  - Point-in-time recovery capability
  - Backup retention: 30 days

Application Backups:
  - Infrastructure as Code (Terraform)
  - Configuration backups
  - Code repository backups
  - Environment variable backups
```

### B. Recovery Procedures

```yaml
# RTO/RPO Targets
Production:
  RTO: 4 hours
  RPO: 15 minutes
  
Staging:
  RTO: 8 hours
  RPO: 1 hour

# Recovery Steps
1. Incident Detection (< 5 minutes)
2. Impact Assessment (< 15 minutes)
3. Recovery Decision (< 30 minutes)
4. System Recovery (< 4 hours)
5. Service Validation (< 1 hour)
6. Post-Incident Review
```

## 8. Cost Optimization

### A. Resource Optimization

```yaml
# Cost-Effective Strategies
Compute:
  - Reserved Instances for steady workloads
  - Spot Instances for development/testing
  - Auto-scaling for variable loads
  - Right-sizing based on metrics

Storage:
  - S3 Intelligent Tiering
  - Lifecycle policies for old data
  - Compression for logs and backups
  - CDN for static content delivery

# Monthly Cost Estimates (USD)
Development: $200-500
Staging: $500-1,000
Production: $2,000-8,000 (based on scale)
```

### B. Performance vs Cost Balance

```yaml
# Optimization Priorities
1. Database Performance
   - Read replicas for read-heavy workloads
   - Connection pooling
   - Query optimization

2. Caching Strategy
   - Redis for session storage
   - Application-level caching
   - CDN for static assets

3. Efficient Scaling
   - Predictive scaling
   - Schedule-based scaling
   - Metric-driven decisions
```

This deployment and scaling guide provides a production-ready infrastructure for the "قطر فين – Where's My Train" system with comprehensive monitoring, security, and cost optimization strategies.