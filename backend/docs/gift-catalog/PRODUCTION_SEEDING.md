# Run the gift catalog and images on production

Loads the latest catalog (1,192 products) and its images onto the production server.

These steps assume the Docker setup in `backend/docker-compose.yml`. If production doesn't use Docker, see [Without Docker](#without-docker).

Replace these placeholders before running the commands:

| Placeholder | Meaning |
|---|---|
| `user@SERVER` | SSH login for the production server |
| `/path/to/3alraf` | Where the repo is on the server |
| `YOURDOMAIN` | The production domain |

## Before you start

**Upload the local images, don't skip it.** The seeder reuses any image already on disk, so it won't download them again from Amazon, Etsy and other sites. Name cards generated in the Docker image would also have no text, because the image doesn't include their font.

**Not checked when this was written:**

- Which migrations are pending on production. Step 4 lists them before running them.
- What `FoundationSeeder` creates. Read it before running a full `db:seed`.
- Whether the storefront has other caches beyond the one cleared in step 8.

## On your local machine

### 1. Commit and push the catalog

`main` doesn't have the seeder, only `ismail-fixes` does.

```bash
cd /var/www/projects/3alraf
git add backend/database/seeders/GiftCatalogSeeder.php backend/database/seeders/data/gift_catalog.json backend/docs/gift-catalog/
git commit -m "Update gift catalog data and seeder"
git push origin ismail-fixes
```

### 2. Package and upload the images

The count should be about 2,400 files. If it's far lower, stop and don't upload.

```bash
cd backend
find storage/app/media/catalog/products/gifts -type f | wc -l
tar -czf /tmp/gift-images.tgz -C storage/app/media catalog/products/gifts
scp /tmp/gift-images.tgz user@SERVER:/tmp/
```

## On the production server

### 3. Start a tmux session and back up

tmux keeps the seeder running if SSH drops.

```bash
tmux new -s seed
cd /path/to/3alraf/backend

docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' | gzip > ~/db-before-catalog.sql.gz
docker compose exec -T app tar -czf - -C storage/app/media . > ~/media-before-catalog.tgz
ls -lh ~/db-before-catalog.sql.gz ~/media-before-catalog.tgz
```

Both files should be larger than a few KB. Don't continue if either is empty.

### 4. Deploy the code

The code is built into the image, so `git pull` alone won't update the seeder. This also deploys everything else on `ismail-fixes`, so check that you want that live.

```bash
git fetch origin
git checkout ismail-fixes
git pull origin ismail-fixes
docker compose up -d --build
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate --force
```

### 5. Extract the images into the media volume

This runs inside the container, so the files get the right owner.

```bash
docker compose exec -T app tar -xzf - -C /var/www/html/storage/app/media < /tmp/gift-images.tgz
docker compose exec app sh -c 'find storage/app/media/catalog/products/gifts -type f | wc -l'
```

The count should match step 2.

Also check the production `.env`: `MEDIA_ROOT` and `MEDIA_URL` must point to your real paths and CDN domain, not `3alraf.test`.

### 6. Confirm an EGP store exists

Without one, the seeder creates products but no listings, and it doesn't warn you.

```bash
docker compose exec app php artisan tinker --execute="echo \App\Models\Tenant::where('currency_code','EGP')->pluck('slug');"
```

This should print something like `["egy"]`.

If it's empty because the database is new, you need the full `php artisan db:seed --force` instead of step 7. Read `FoundationSeeder` first, and don't run the full seed on a live database.

### 7. Run the seeder

```bash
docker compose exec -T app php -d memory_limit=-1 artisan db:seed --class=GiftCatalogSeeder --force
```

It's safe to re-run if it gets interrupted. The backup from step 3 covers these changes:

- **Removed products:** `GFT-` products no longer in the catalog are deleted, unless orders or stock documents reference them.
- **Rebuilt listings:** listings nobody has edited or ordered are recreated with the new prices and rank order.
- **Reset stock:** products whose only stock entry is the seeder's opening stock are reset to the buying plan.

### 8. Clear the cache and verify

```bash
docker compose exec app php artisan cache:clear
docker compose ps

docker compose exec app php artisan tinker --execute="
echo 'Products: '.\App\Models\Product::where('sku','like','GFT-%')->count().PHP_EOL;
echo 'Listings: '.\App\Models\TenantProduct::withoutGlobalScopes()->whereIn('product_id', \App\Models\Product::where('sku','like','GFT-%')->select('id'))->count().PHP_EOL;
echo 'Opening stock rows: '.DB::table('stock_movements')->where('note','Opening stock (gift catalog seed)')->count().PHP_EOL;"
```

With one EGP store, you should see:

| Check | Expected |
|---|---|
| Products | 1,192 |
| Listings | 1,192 |
| Opening stock rows | 298 |

Then open `https://egy.YOURDOMAIN/ar/category/beauty-care` and confirm the photos load from your CDN domain. About 43 products with no photo should show name cards.

If production already had an older version of this catalog, replaced photos keep the same filenames. The CDN config caches images for a year, so purge your CDN cache if you see old pictures.

### 9. Clean up

```bash
rm /tmp/gift-images.tgz
```

Keep the two backups in your home folder until you've confirmed the store looks right.

## Without Docker

Steps 1 and 3 still apply, but use a plain `mysqldump` for the backup and skip the Docker commands. `/var/www/3alraf` is the path from the repo's supervisor config, so change it if yours differs.

**Local machine (replaces the upload in step 2):**

```bash
rsync -az --mkpath --info=progress2 storage/app/media/catalog/products/gifts/ \
  user@SERVER:/var/www/3alraf/backend/storage/app/media/catalog/products/gifts/
```

**Server (replaces steps 4, 5, 7 and 8):**

```bash
cd /var/www/3alraf/backend
git fetch origin && git checkout ismail-fixes && git pull origin ismail-fixes
composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data storage/app/media
sudo -u www-data php artisan migrate --force
sudo -u www-data php -d memory_limit=-1 artisan db:seed --class=GiftCatalogSeeder --force
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan queue:restart
```

Run the step 6 check before seeding and the step 8 check afterwards, without the `docker compose exec app` prefix.
