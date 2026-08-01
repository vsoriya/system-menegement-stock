# Deploying StockPro

For a **demo deployment** to Laravel Cloud, so other people can try the system.
Read the "Before a real shop uses this" section at the end before letting anyone
put real money through it.

> **Which machine?** Every command below is labelled `[MAC]` or `[SERVER]`.
> The two are not interchangeable. Running a `[SERVER]` command on your Mac is
> what removed your test tools earlier.

---

## 1. Give this project its own git repository `[MAC]`

> **Read this section before running anything.** Your whole home folder
> `/Users/soriya` is currently a git repository, and this project sits inside it.
> That repository already has remotes pointing at `hbd-pciture` and `test3` on
> GitHub. Running `git add .` and `git push` from here without the step below
> would push your home folder, including `.aws` and `.ssh`, to GitHub.
>
> Nothing has leaked so far. I checked: `.env` has never been committed, and
> none of those folders are tracked.

Create a repository that belongs to this project only:

```bash
cd /Users/soriya/system-management-stock
git init
```

Confirm it worked before going any further. This must print the project path,
**not** `/Users/soriya`:

```bash
git rev-parse --show-toplevel
```

Confirm `.env` will stay out of it. This must print a line mentioning
`.gitignore`:

```bash
git check-ignore -v .env
```

Only once both checks pass:

```bash
git add .
git commit -m "StockPro ready for demo deployment"
```

Now check nothing sensitive got in. Both commands must print `0`:

```bash
git ls-files | grep -c "^\.env$"
git ls-files | grep -c "vendor/"
```

Create an empty **private** repository on GitHub, then:

```bash
git remote add origin git@github.com:YOUR_USERNAME/stockpro.git
git branch -M main
git push -u origin main
```

### One standing rule

**Never run `git add .` while your terminal is in `/Users/soriya`.** That stages
your entire home folder, credentials included. The `git init` above protects you
inside this project, but not outside it.

Tidying up the home repository is worth doing one day. It is your data, so I have
not touched it.

---

## 2. Create the application on Laravel Cloud

1. Sign in at [cloud.laravel.com](https://cloud.laravel.com) with GitHub.
2. Create an application and pick the repository you just pushed.
3. **Region: choose the one closest to Cambodia**, normally Singapore.
   Cambodia to Singapore is roughly 30-50ms. Cambodia to Europe is over 200ms,
   and at a till you feel that on every tap.
4. Add a **MySQL** database. The `DB_*` variables are filled in for you.
5. Add **Object Storage**. This is what keeps product pictures alive, see step 4.
6. Set a **spending limit** so usage-based billing cannot surprise you.

---

## 3. Environment variables `[SERVER]`

Set these in the Laravel Cloud dashboard. Do **not** copy your local `.env`.

```
APP_NAME=StockPro
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-url

APP_LOCALE=km
APP_FALLBACK_LOCALE=en
APP_CURRENCY_SYMBOL=$

APP_SHOP_ADDRESS="Takhmao kandal"
APP_SHOP_PHONE="087840377"
APP_SHOP_TAX_NUMBER="0312"
APP_SHOP_FOOTER="ទំនិញលក់ហើយមិនអាចប្រគល់មកវិញ"

LOG_LEVEL=warning
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Then generate a key from the dashboard's command runner:

```bash
php artisan key:generate --force
```

Three things that matter here:

- **`APP_DEBUG=false` is not optional.** With `true`, any error page shows your
  database credentials and file paths to whoever hit it.
- **Do not reuse `SEED_PASSWORD`.** Leave it unset. Step 5 explains why.
- **Values containing spaces need double quotes**, which is what broke your
  local `.env` earlier.

---

## 4. Product pictures

Laravel Cloud runs the application in a container with a **throwaway disk**.
Pictures written to it are deleted on every deployment.

After adding Object Storage in step 2, set:

```
IMAGE_DISK=s3
```

The `AWS_*` variables are provided by the Object Storage resource. With this
set, pictures are stored outside the container and survive deployments.

If you skip this, everything still works but every product picture disappears
the next time you deploy.

---

## 5. First deployment

Laravel Cloud runs the build itself. Confirm the deploy commands include:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`storage:link` is only needed when `IMAGE_DISK=public`. It is harmless either way.

The three `:cache` commands are the largest speed win available and cost nothing.

### Create your account `[SERVER]`

```bash
php artisan db:seed --class=UserSeeder --force
```

With `SEED_PASSWORD` unset this generates a strong password and **prints it
once**. Copy it immediately, sign in, then change it under "My account".

Re-running the seeder later never overwrites an existing password, so it is safe
to run again.

Do not run `php artisan db:seed` without `--class`. The full seeder also loads
demo products and fake stock movements.

---

## 6. Check it worked

In this order, because each step depends on the one before:

1. The sign-in page loads over **https** and shows the ខ្មែរ / English switch.
2. You can sign in with the generated password.
3. The dashboard shows a stock value, not `$0.00`.
4. **Products → Add product**, upload a picture, save.
5. **Till** shows that product with its picture, not initials.
6. Sell it. Stock drops by the quantity sold.
7. Print the A5 invoice and the 80mm receipt.
8. **Products → the product → Stock history** shows the sale with the invoice
   number as its reference.

Step 8 is the one worth doing carefully. It proves selling and stock control are
actually connected rather than two separate features that agree by luck.

---

## Redeploying later `[MAC]`

```bash
composer test
git add .
git commit -m "describe the change"
git push
```

Laravel Cloud deploys on push. Run the tests **before** pushing, not after.

Never run `composer install --no-dev` on your Mac. That is a `[SERVER]` command
and it deletes the tools `composer test` needs.

---

## Before a real shop uses this

This deployment is fine for demonstrating the system. Three things are still
missing before a shop should depend on it daily.

**Backups.** There are none. If the database is lost, every sale, every product
and every customer is gone with no way back. This is the one item I would not
skip. For a shop it is the difference between an inconvenience and the end of
their records.

**Password reset emails.** `MAIL_MAILER` is not configured, so the "forgot
password" link does nothing. An administrator can still reset a staff password
from the Users page, so it is survivable, but staff will be confused.

**Internet dependency.** A cloud-hosted till stops working when the shop's
internet drops, and they cannot sell. Ask any shop this before selling to them.
If their connection is unreliable, the system belongs on a computer inside the
shop instead.
