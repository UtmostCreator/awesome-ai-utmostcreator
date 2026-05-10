# Services and Runtime

Use for local containers, logs, DB clients, and external service CLIs.

---

## Preferred Commands

Docker/Colima:

```bash
colima status
docker compose ps
docker compose logs --tail=100 app
docker system df
```

Database:

```bash
mysql -h 127.0.0.1 -u root -p database -e "SHOW TABLES;"
php artisan migrate:status
```

Stripe:

```bash
stripe --version
```

Logs:

```bash
lnav storage/logs/*.log
tail -100 storage/logs/laravel.log
rg -n "ERROR|Exception|FAILED" storage/logs
```

---

## Use When

- diagnosing local services
- reading container logs
- checking DB state
- testing webhooks

---

## Approval Required

```bash
colima start
docker compose up -d
docker compose down
docker system prune
stripe listen --forward-to ...
mysql -e "DELETE ..."
```

Example: [`../examples/good-bad-services-runtime.md`](../examples/good-bad-services-runtime.md)
