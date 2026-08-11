# Waflow Laravel

This application is the only public boundary of Waflow. It provides the panel,
tenant-scoped REST API, API-key authentication, the 7-day trial, usage limits,
OpenWA provisioning, queued messaging, inbound event processing and signed
customer webhooks.

For the public HTTP contract and examples, see [`../docs/API.md`](../docs/API.md).

## Local Tests

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
./vendor/bin/pint --test
```

The test environment uses SQLite, the sync queue and HTTP fakes. The production
Compose stack uses PostgreSQL, Redis and dedicated queue/scheduler processes.
