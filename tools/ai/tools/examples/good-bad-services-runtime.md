# Good / Bad: Services and Runtime

## Good

```bash
colima status
docker compose ps
docker compose logs --tail=100 app
docker system df
php artisan migrate:status
```

## Bad

```bash
docker compose up -d
docker compose down
docker system prune
colima start
```

without approval.

Why bad:

- mutates local services
- can stop running work
- can delete images/volumes/cache
