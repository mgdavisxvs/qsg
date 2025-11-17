# Enterprise Deployment Guide
## Legal Document & Contract Analyzer v5.0

**Enterprise Edition - Production-Grade Deployment System**

---

## Table of Contents

1. [Overview](#overview)
2. [Key Features](#key-features)
3. [Deployment Strategies](#deployment-strategies)
4. [Real-World Use Cases](#real-world-use-cases)
5. [Configuration Management](#configuration-management)
6. [Database Migrations](#database-migrations)
7. [Zero-Downtime Deployments](#zero-downtime-deployments)
8. [Monitoring & Observability](#monitoring--observability)
9. [Disaster Recovery](#disaster-recovery)
10. [Scaling Strategies](#scaling-strategies)
11. [Security Best Practices](#security-best-practices)
12. [Future-Proofing](#future-proofing)

---

## Overview

The Enterprise Deployment System is a **production-grade, battle-tested deployment framework** designed for:

- **Zero-downtime deployments** across multiple servers
- **Automated database migrations** with rollback capability
- **Comprehensive monitoring & observability** integration
- **Multi-environment support** (dev, staging, production, DR)
- **Advanced deployment strategies** (blue-green, canary, rolling)
- **Security hardening** and compliance logging
- **Performance profiling** and optimization
- **Disaster recovery** planning and execution

---

## Key Features

### 1. **Deployment Strategies**

| Strategy | Downtime | Risk Level | Best For |
|----------|----------|------------|----------|
| **Simple** | Yes (~30s) | Low | Development, small apps |
| **Blue-Green** | Zero | Medium | Production, instant rollback needed |
| **Canary** | Zero | Low | Production, gradual rollout |
| **Rolling** | Zero | Medium | Multi-server, distributed systems |

### 2. **Advanced Capabilities**

- ✅ **Deployment Locking**: Prevents concurrent deployments
- ✅ **Structured Logging**: JSON-formatted logs with context
- ✅ **Audit Logging**: Compliance-ready deployment tracking
- ✅ **Metrics Collection**: Performance and deployment metrics
- ✅ **Health Checks**: Comprehensive application validation
- ✅ **Smoke Tests**: Post-deployment functionality verification
- ✅ **Rollback**: One-command rollback to any previous version
- ✅ **Notifications**: Slack/Email deployment alerts
- ✅ **Performance Profiling**: Load testing and benchmarking

### 3. **Integration Ready**

- **CI/CD Pipelines**: Jenkins, GitLab CI, GitHub Actions
- **Monitoring**: New Relic, DataDog, Prometheus, Grafana
- **Load Balancers**: HAProxy, Nginx, AWS ELB/ALB
- **Container Orchestration**: Kubernetes, Docker Swarm
- **Service Mesh**: Istio, Linkerd (readiness built-in)
- **Secrets Management**: HashiCorp Vault, AWS Secrets Manager
- **Observability**: OpenTelemetry, Jaeger, Zipkin

---

## Deployment Strategies

### Strategy 1: Simple Deployment

**When to Use:**
- Development environments
- Single-server applications
- Small traffic (<1000 users/day)
- Acceptable downtime (~30 seconds)

**Example:**
```bash
./deploy-enterprise.sh dev simple
```

**Flow:**
1. Run prerequisites checks
2. Create backup
3. Run tests
4. Deploy new version
5. Restart services
6. Run health checks

**Downtime:** ~30 seconds

---

### Strategy 2: Blue-Green Deployment

**When to Use:**
- Production environments
- Instant rollback capability needed
- Zero-downtime requirement
- Sufficient resources (2x infrastructure)

**Example:**
```bash
./deploy-enterprise.sh production blue-green
```

**Flow:**
```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  Step 1: Current State                                     │
│  ┌──────────┐                                              │
│  │  Blue    │ ◄─── 100% traffic                            │
│  │ (v4.1)   │                                              │
│  └──────────┘                                              │
│  ┌──────────┐                                              │
│  │  Green   │      (idle)                                  │
│  │          │                                              │
│  └──────────┘                                              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Step 2: Deploy to Green                                   │
│  ┌──────────┐                                              │
│  │  Blue    │ ◄─── 100% traffic                            │
│  │ (v4.1)   │                                              │
│  └──────────┘                                              │
│  ┌──────────┐                                              │
│  │  Green   │ ◄─── Deploy v5.0                             │
│  │ (v5.0)   │      Run smoke tests                         │
│  └──────────┘                                              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Step 3: Switch Traffic                                    │
│  ┌──────────┐                                              │
│  │  Blue    │      (idle, kept for rollback)               │
│  │ (v4.1)   │                                              │
│  └──────────┘                                              │
│  ┌──────────┐                                              │
│  │  Green   │ ◄─── 100% traffic                            │
│  │ (v5.0)   │                                              │
│  └──────────┘                                              │
└─────────────────────────────────────────────────────────────┘
```

**Advantages:**
- Zero downtime
- Instant rollback (just switch back)
- Full testing before traffic switch
- Production-identical testing environment

**Disadvantages:**
- Requires 2x infrastructure
- Database migrations can be complex
- Shared state management needed

**Best Practices:**
- Test thoroughly on green before switching
- Keep blue running for 24-48 hours
- Monitor error rates closely after switch
- Use feature flags for gradual rollout

---

### Strategy 3: Canary Deployment

**When to Use:**
- High-risk deployments
- Large user base
- Need gradual rollout
- A/B testing requirements

**Example:**
```bash
./deploy-enterprise.sh production canary --traffic-percent 10
```

**Flow:**
```
Stage 1: 10% traffic to canary
┌─────────────────────────────────────┐
│ Stable (v4.1)  ████████████ 90%    │
│ Canary (v5.0)  █            10%    │
└─────────────────────────────────────┘

Stage 2: 25% traffic (if healthy)
┌─────────────────────────────────────┐
│ Stable (v4.1)  ███████      75%    │
│ Canary (v5.0)  ███          25%    │
└─────────────────────────────────────┘

Stage 3: 50% traffic
┌─────────────────────────────────────┐
│ Stable (v4.1)  █████        50%    │
│ Canary (v5.0)  █████        50%    │
└─────────────────────────────────────┘

Stage 4: 100% traffic (full rollout)
┌─────────────────────────────────────┐
│ Canary (v5.0)  ██████████  100%    │
└─────────────────────────────────────┘
```

**Monitoring During Canary:**
- Error rate comparison (canary vs stable)
- Response time percentiles (p50, p95, p99)
- Business metrics (conversion, engagement)
- Resource utilization (CPU, memory)

**Auto-Rollback Triggers:**
- Error rate > 5% higher than stable
- p99 latency > 2x stable
- HTTP 5xx errors > threshold
- Custom business metric degradation

---

### Strategy 4: Rolling Update

**When to Use:**
- Multi-server deployments
- Load-balanced infrastructure
- Limited resources (can't do blue-green)
- Need controlled server-by-server rollout

**Example:**
```bash
./deploy-enterprise.sh production rolling \
  --servers "web01,web02,web03,web04"
```

**Flow:**
```
Initial State:
Server01 (v4.1) ──┐
Server02 (v4.1) ──┤
Server03 (v4.1) ──├─► Load Balancer ──► Users
Server04 (v4.1) ──┘

Step 1: Update Server01
Server01 (v5.0) ──┐
Server02 (v4.1) ──┤
Server03 (v4.1) ──├─► Load Balancer ──► Users
Server04 (v4.1) ──┘

Step 2: Update Server02
Server01 (v5.0) ──┐
Server02 (v5.0) ──┤
Server03 (v4.1) ──├─► Load Balancer ──► Users
Server04 (v4.1) ──┘

... continues until all servers updated
```

**Configuration:**
- Batch size: 1 server at a time (safe) or N servers (faster)
- Wait time: 30s-300s between batches
- Health check: After each server update
- Auto-rollback: On first failure

---

## Real-World Use Cases

### Use Case 1: Startup MVP Deployment

**Scenario**: Small startup, 1 server, 500 users/day

**Setup:**
```bash
# Development
./deploy-enterprise.sh dev simple

# Production (manual approval)
./deploy-enterprise.sh production simple --notify slack
```

**Monitoring:**
- Basic uptime monitoring (UptimeRobot, Pingdom)
- Error tracking (Sentry)
- Simple metrics (server CPU, memory)

**Estimated Cost**: $50/month (DigitalOcean droplet + monitoring)

---

### Use Case 2: SaaS Application Scale-Up

**Scenario**: Growing SaaS, 3 servers, 10,000 users/day, 99.9% uptime SLA

**Setup:**
```bash
# Staging
./deploy-enterprise.sh staging simple

# Production (blue-green for zero downtime)
./deploy-enterprise.sh production blue-green \
  --notify slack \
  --profile \
  --health-check-url https://legal-analyzer.com/health
```

**Infrastructure:**
- 2x Blue-Green environments (6 servers total)
- Load balancer (HAProxy/Nginx)
- Database (managed PostgreSQL)
- CDN (Cloudflare)
- Monitoring (DataDog)

**Estimated Cost**: $500/month

---

### Use Case 3: Enterprise Multi-Region

**Scenario**: Enterprise, 20+ servers, 1M+ users/day, 99.99% uptime, multi-region

**Setup:**
```bash
# Deploy to US-East region
./deploy-enterprise.sh production canary \
  --servers "us-east-web01,us-east-web02,...,us-east-web10" \
  --traffic-percent 5 \
  --notify slack,email \
  --profile

# Deploy to EU region (after US success)
./deploy-enterprise.sh production canary \
  --servers "eu-west-web01,eu-west-web02,...,eu-west-web10" \
  --traffic-percent 5 \
  --notify slack,email
```

**Infrastructure:**
- Multi-region (US, EU, Asia)
- Auto-scaling groups (10-50 servers per region)
- Global load balancer (AWS Route 53 + CloudFront)
- Multi-AZ database clusters
- Redis clusters for caching
- Kubernetes for orchestration
- Full observability stack (Prometheus, Grafana, Jaeger)

**Deployment Flow:**
1. Deploy to 1% of US-East traffic (canary)
2. Monitor for 1 hour
3. Increase to 10%, then 50%, then 100% (US-East)
4. Deploy to EU-West (same canary process)
5. Deploy to Asia-Pacific
6. Total rollout time: 6-8 hours

**Estimated Cost**: $10,000-$50,000/month

---

## Configuration Management

### Environment-Specific Configuration

**Directory Structure:**
```
config/
├── deploy-dev.env
├── deploy-staging.env
├── deploy-production.env
└── deploy-dr.env
```

**Example: `config/deploy-production.env`**
```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_VERSION=5.0

# Caching
CACHE_ENABLED=true
CACHE_DRIVER=redis
REDIS_HOST=redis-cluster.internal
REDIS_PORT=6379

# Database (if applicable)
DB_HOST=db-cluster-primary.internal
DB_NAME=legal_analyzer_prod
DB_USER=app_user
# DB_PASS in secrets file

# Monitoring
ENABLE_MONITORING=true
NEW_RELIC_LICENSE_KEY=xxx
DATADOG_API_KEY=xxx
METRICS_ENDPOINT=https://metrics.internal/v1/deployments

# Notifications
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx
NOTIFICATION_EMAIL=ops@company.com

# Load Balancer
LB_API_ENDPOINT=https://lb.internal/api/v1
LB_API_KEY=xxx

# Performance
PHP_OPCACHE_ENABLE=1
PHP_OPCACHE_MEMORY=256
PHP_MAX_EXECUTION_TIME=60
PHP_MEMORY_LIMIT=512M

# Security
SSL_CERT_PATH=/etc/ssl/certs/legal-analyzer.crt
SSL_KEY_PATH=/etc/ssl/private/legal-analyzer.key
```

### Secrets Management

**Encrypt Secrets with `age`:**
```bash
# Install age
brew install age  # macOS
apt-get install age  # Ubuntu

# Generate key
age-keygen -o ~/.config/age/deploy-key.txt

# Encrypt secrets
cat > .deploy-secrets.env <<EOF
DB_PASS=super_secret_password
API_KEY=secret_api_key
SLACK_WEBHOOK_URL=https://hooks.slack.com/xxx
EOF

age -r $(cat ~/.config/age/deploy-key.txt | grep public | cut -d: -f2) \
  -o .deploy-secrets.enc \
  .deploy-secrets.env

# Secure deletion
shred -u .deploy-secrets.env
```

**Use in Deployment:**
```bash
# Script automatically decrypts if age is installed
./deploy-enterprise.sh production blue-green
# Secrets loaded from .deploy-secrets.enc
```

---

## Database Migrations

### Migration Framework

**Directory Structure:**
```
migrations/
├── 001_initial_schema.sql
├── 002_add_user_preferences.sql
├── 003_add_audit_log.sql
└── 004_add_analytics_table.sql
```

**Example Migration: `migrations/002_add_user_preferences.sql`**
```sql
-- Migration: Add user preferences table
-- Date: 2025-11-16
-- Author: Legal Analysis Team

CREATE TABLE IF NOT EXISTS user_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_preference_key (preference_key),
    UNIQUE KEY unique_user_preference (user_id, preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rollback script (in comments):
-- DROP TABLE IF EXISTS user_preferences;
```

**Running Migrations:**
```bash
# Automatic during deployment
./deploy-enterprise.sh production blue-green

# Skip migrations
./deploy-enterprise.sh production blue-green --skip-migrations

# Manual migration run
./run-migrations.sh production
```

### Zero-Downtime Migration Strategies

**Strategy: Expand-Migrate-Contract**

**Phase 1: Expand** (Add new schema without removing old)
```sql
-- Add new column (nullable, no data)
ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL;
```

Deploy code that writes to both old & new schema.

**Phase 2: Migrate** (Copy data from old to new)
```sql
-- Backfill data
UPDATE users SET email_verified_at = verified_at WHERE verified = 1;
```

**Phase 3: Contract** (Remove old schema)
```sql
-- After all code updated, remove old column
ALTER TABLE users DROP COLUMN verified;
```

This allows rollback at any point without data loss!

---

## Monitoring & Observability

### Structured Logging

**Log Format (JSON Lines):**
```json
{
  "timestamp": "2025-11-16T10:30:45.123Z",
  "level": "INFO",
  "deployment_id": "5.0-20251116_103045",
  "environment": "production",
  "message": "Deployment started",
  "context": {
    "strategy": "blue-green",
    "user": "deploy-bot",
    "git_commit": "db86eec"
  }
}
```

**Audit Log (Compliance):**
```json
{
  "timestamp": "2025-11-16T10:30:45.123Z",
  "deployment_id": "5.0-20251116_103045",
  "action": "blue_green_switch",
  "user": "alice@company.com",
  "ip": "10.0.1.50",
  "environment": "production",
  "details": "from=blue to=green"
}
```

### Metrics Collection

**Deployment Metrics:**
- `deployment.duration` (timer): Total deployment time
- `deployment.tests.duration` (timer): Test execution time
- `deployment.tests.failures` (counter): Number of test failures
- `deployment.health_checks.passed` (gauge): Health check status
- `deployment.backup.size_bytes` (gauge): Backup file size

**Application Metrics:**
- `deployment.response_time` (timer): App response time
- `deployment.performance.rps` (gauge): Requests per second
- `deployment.performance.latency` (gauge): Average latency

**Integration with Prometheus:**
```bash
# Export metrics to Prometheus
cat logs/metrics_*.jsonl | jq -r '
  "deployment_duration_seconds{deployment_id=\"\(.deployment_id)\",environment=\"\(.environment)\"} \(.value)"
' > /var/lib/prometheus/deployments.prom
```

---

## Disaster Recovery

### Backup Strategy

**Automated Backups:**
- Before every deployment
- Daily scheduled backups (cron)
- Retention: Last 10 backups + monthly snapshots

**Backup Types:**
```bash
# Pre-deployment backup
create_backup "pre-deployment"

# Pre-rollback safety backup
create_backup "pre-rollback"

# Scheduled backup
create_backup "daily"
```

**Off-Site Backup:**
```bash
# Sync to S3
aws s3 sync backups/ s3://company-backups/legal-analyzer/ \
  --storage-class GLACIER \
  --exclude "*" --include "*.tar.gz"
```

### Disaster Recovery Deployment

**Scenario: Primary datacenter fails**

```bash
# 1. Promote DR site to primary
./deploy-enterprise.sh dr simple --force

# 2. Update DNS to point to DR site
# (manual or automated via Route53)

# 3. Restore from latest backup
./deploy-enterprise.sh dr simple --rollback latest

# 4. Verify functionality
./run-smoke-tests.sh dr

# 5. Monitor closely
tail -f logs/deployment_*.log
```

**RTO (Recovery Time Objective)**: <15 minutes
**RPO (Recovery Point Objective)**: <1 hour (hourly backups)

---

## Scaling Strategies

### Horizontal Scaling

**Add New Servers:**
```bash
# Provision new servers (Terraform, CloudFormation, etc.)
# Add to deployment configuration
./deploy-enterprise.sh production rolling \
  --servers "web01,web02,web03,web04,web05,web06"
```

**Auto-Scaling Integration:**
```bash
# Deploy to auto-scaling group
# User data script on new instances:
#!/bin/bash
cd /var/www/legal-analyzer
git pull origin main
./deploy-enterprise.sh production simple --skip-tests
```

### Vertical Scaling

**Upgrade Server Resources:**
```bash
# 1. Create snapshot
./backup-server.sh web01

# 2. Upgrade instance type (AWS: t3.medium → t3.xlarge)
# 3. Update PHP configuration
echo "memory_limit = 1G" >> /etc/php/7.4/fpm/php.ini

# 4. Re-deploy with new settings
./deploy-enterprise.sh production simple
```

### Database Scaling

**Read Replicas:**
```sql
-- Configure read replicas
-- Update application to use read replicas for queries
DB_READ_HOSTS=db-replica-01,db-replica-02,db-replica-03
```

**Sharding (Future):**
```
User data partitioned by region:
- Shard 1: US users
- Shard 2: EU users
- Shard 3: Asia users
```

---

## Security Best Practices

### 1. Secrets Management

**Never Commit Secrets:**
```bash
# Add to .gitignore
echo ".deploy-secrets.enc" >> .gitignore
echo ".deploy-secrets.env" >> .gitignore
echo "config/*prod*" >> .gitignore
```

**Use Environment Variables:**
```bash
export DB_PASS=$(age -d -i ~/.config/age/deploy-key.txt .deploy-secrets.enc | grep DB_PASS | cut -d= -f2)
```

**Rotate Secrets Regularly:**
```bash
# Every 90 days
./rotate-secrets.sh --service database
./rotate-secrets.sh --service api-keys
```

### 2. Deployment Auditing

**Who Deployed What When:**
```bash
# Check audit log
jq '.action,.user,.timestamp' logs/audit_*.log | tail -20

# Example output:
"deployment_started" "alice@company.com" "2025-11-16T10:30:45Z"
"deployment_completed" "alice@company.com" "2025-11-16T10:45:12Z"
```

### 3. Least Privilege

**Deployment User:**
```bash
# Create dedicated deployment user
useradd -m -s /bin/bash deploy-bot

# Grant only necessary permissions
usermod -aG www-data deploy-bot

# Use SSH keys (no passwords)
ssh-keygen -t ed25519 -C "deploy-bot@company.com"
```

---

## Future-Proofing

### Preparing for Scale

**Current: 1 server → Future: 100+ servers**

**Checklist:**
- ✅ **Stateless application**: No local file storage
- ✅ **Centralized logging**: Send logs to ELK/Splunk
- ✅ **Distributed caching**: Redis cluster (not local cache)
- ✅ **Session storage**: Redis/database (not file-based)
- ✅ **Asset storage**: S3/CDN (not local disk)
- ✅ **Configuration management**: Consul/etcd
- ✅ **Service discovery**: DNS or service mesh

### Technology Evolution

**Current Stack:**
- PHP 7.4
- Apache/Nginx
- File-based sessions
- Local cache

**Future Stack (seamless migration):**
- PHP 8.x (deployment script compatible)
- Kubernetes deployment
- Redis sessions
- Redis cluster cache
- S3 for backups
- CloudFront CDN

**Migration Path:**
```bash
# Phase 1: Move to Redis sessions
./deploy-enterprise.sh staging blue-green --config redis-sessions

# Phase 2: Move to Redis cache
./deploy-enterprise.sh staging blue-green --config redis-cache

# Phase 3: Move to Kubernetes
./deploy-k8s.sh staging --strategy rolling
```

### API Versioning

**Prepare for API Changes:**
```php
// v1 endpoint (current)
/api/v1/analyze

// v2 endpoint (future - breaking changes)
/api/v2/analyze

// Both versions run simultaneously during migration
```

### Database Schema Evolution

**Non-Breaking Changes:**
- Add new tables: ✅ Safe
- Add new columns (nullable): ✅ Safe
- Add new indexes: ✅ Safe

**Breaking Changes:**
- Remove columns: ⚠️ Use Expand-Migrate-Contract
- Rename columns: ⚠️ Use Expand-Migrate-Contract
- Change data types: ⚠️ Use versioned migrations

---

## Conclusion

The Enterprise Deployment System provides a **comprehensive, production-ready framework** for deploying the Legal Document & Contract Analyzer at any scale.

**Key Takeaways:**
1. **Start simple**, scale as needed
2. **Automate everything** to reduce human error
3. **Monitor proactively** to catch issues early
4. **Test thoroughly** before production
5. **Plan for failure** with rollback strategies

**Next Steps:**
1. Review configuration examples
2. Set up monitoring integration
3. Test deployment strategies in staging
4. Document your specific deployment process
5. Train team on rollback procedures

---

**For Support:**
- Documentation: See all `*.md` files
- Issues: GitHub issue tracker
- Security: security@your-domain.com

---

**Version History:**
- v5.0 (2025-11-16): Enterprise deployment system
- v4.1 (2025-11-15): Knuth correctness fixes
- v4.0 (2025-11-15): Wolfram computational enhancement

**End of Enterprise Deployment Guide**
