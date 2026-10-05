# Fresh server setup — RikoWallet

These commands target a **fresh Ubuntu 24.04 LTS VPS with sudo access**, Nginx,
PHP 8.3 and MySQL. They are not cPanel/shared-hosting commands. Replace
`wallet.example.com`, `admin@example.com` and the database password below.
Point the domain's A record to the VPS first; only publish an AAAA record if
IPv6 is configured. This installs the current review build, not a certified
production wallet. Complete `PENDING-WORK.txt` before admitting real payouts.

## 1. Install dependencies

Run under your ordinary sudo-enabled deployment user, not the www-data user.

```bash
sudo apt update
sudo apt install -y nginx mysql-server git unzip composer cron certbot python3-certbot-nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-bcmath php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl
sudo systemctl enable --now nginx mysql php8.3-fpm cron
sudo install -d -o "$USER" -g www-data -m 2750 /var/www/rikowallet
git clone https://github.com/Vishal431/rikowallet.git /var/www/rikowallet
cd /var/www/rikowallet
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
cp .env.example .env
chmod 640 .env
```

For a private repository, authenticate Git using your normal credential manager
or a read-only deploy key. Never put a token in the clone URL or commit it.
The current website uses static CSS/JS; Node/npm is not needed to serve it.

## 2. Create a dedicated MySQL database

Open the MySQL console without saving its command history:

```bash
sudo env MYSQL_HISTFILE=/dev/null mysql
```

Run this SQL, replacing `CHANGE_TO_A_LONG_RANDOM_DB_PASSWORD` with a unique
password (escape any SQL quote characters), then exit:

```sql
CREATE DATABASE rikowallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rikowallet'@'localhost' IDENTIFIED BY 'CHANGE_TO_A_LONG_RANDOM_DB_PASSWORD';
GRANT ALL PRIVILEGES ON rikowallet.* TO 'rikowallet'@'localhost';
EXIT;
```

## 3. Configure environment

```bash
nano .env
```

Change these existing entries (do not leave duplicate keys):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wallet.example.com
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=rikowallet
DB_USERNAME=rikowallet
DB_PASSWORD="YOUR_DATABASE_PASSWORD"
RIKO_REWARDS_ONLY=true
RIKO_SANDBOX=false
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Configure approved SMTP details when email flows are ready. The default log
mailer does not send messages. Callback/HTTP-cron secrets can remain empty
until their integrations are configured; never use example secrets. The CLI
scheduler below does not need an HTTP-cron secret.

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan riko:admin-create admin@example.com --name="Riko Admin" --country=IN
```

The command prompts privately for a new password and confirmation. There is no
default production password. It never promotes existing accounts, grants KYC,
or adds demo balances. **Do not run db:seed on this server.** Keep APP_KEY backed
up securely; regenerating it after use makes encrypted recipient data unreadable.

Set read-only application code for the web worker and writable runtime folders:

```bash
sudo chown -R "$USER":www-data /var/www/rikowallet
sudo find /var/www/rikowallet -type d -exec chmod 2750 {} +
sudo find /var/www/rikowallet -type f -exec chmod 640 {} +
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} +
sudo find storage bootstrap/cache -type f -exec chmod 660 {} +
sudo -u www-data php artisan optimize
```

No public storage symlink is required for method images or KYC files. KYC
documents stay private. Do not expose storage/app/private through Nginx.

## 4. Nginx and HTTPS

```bash
sudo nano /etc/nginx/sites-available/rikowallet
```

Paste this server block with your domain:

```nginx
server {
    listen 80;
    server_name wallet.example.com;
    root /var/www/rikowallet/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/rikowallet /etc/nginx/sites-enabled/rikowallet
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d wallet.example.com --redirect
sudo certbot renew --dry-run
```

Allow inbound TCP 80/443 in your VPS firewall/security group. Preserve your SSH
access rule before enabling a firewall; do not expose MySQL port 3306 publicly.
Sign in only after HTTPS is working. Set `upload_max_filesize=10M` and
`post_max_size=12M` in `/etc/php/8.3/fpm/php.ini` if needed for KYC uploads, then
`sudo systemctl reload php8.3-fpm`. Method logos still have a 1 MB app limit.

## 5. Cron and queue worker

```bash
sudo tee /etc/cron.d/rikowallet > /dev/null <<'CRON'
* * * * * www-data cd /var/www/rikowallet && /usr/bin/php artisan schedule:run >> /var/www/rikowallet/storage/logs/scheduler.log 2>&1
CRON
sudo chmod 644 /etc/cron.d/rikowallet
sudo tee /etc/systemd/system/rikowallet-queue.service > /dev/null <<'SERVICE'
[Unit]
Description=RikoWallet Laravel queue
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/rikowallet
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=5
UMask=0007

[Install]
WantedBy=multi-user.target
SERVICE
sudo systemctl daemon-reload
sudo systemctl enable --now rikowallet-queue
sudo -u www-data php artisan schedule:list
```

Configure log rotation for scheduler.log. Monitor failed jobs, scheduler runs,
disk space, aged payout requests and uptime; do not rely on a one-time install.

## 6. Login and initial configuration

- Sign in: `https://wallet.example.com/login`
- Admin panel: `https://wallet.example.com/admin`
- Email: the address passed to `riko:admin-create`.
- Password: the one you set in its hidden prompt. Local demo credentials do not
  exist on a fresh server and are never uploaded to GitHub.
- Manual withdrawals: add currencies/rates first, then methods, countries,
  min/max RC, fees, logo and instructions. Enable only ready methods.
- Keep web/app earning disabled in admin while configuring funded task budgets,
  providers, referral rules and KYC. No live reward partners are auto-configured.
- Set backup/restore procedures for MySQL, private storage and .env/APP_KEY.
- Verify login, permissions, KYC, fee preview, hold/reject/paid flows with
  controlled test accounts before inviting users. Never mark a real payout paid
  unless it actually happened externally.

## 7. Future updates

Take a database/private-storage backup before updates. Review migrations and
release notes; run from /var/www/rikowallet as the deployment user:

```bash
sudo -u www-data php artisan down
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo -u www-data php artisan queue:restart
sudo -u www-data php artisan up
```

If any step fails, resolve it before `up`; do not blindly run the remaining
commands. Reapply runtime permissions if your deployment process changes them.
Never use migrate:fresh, db:wipe or demo seeding on the live database.

## Flutter production endpoint

After server verification, configure Android release signing as described in
the Flutter project and build on a machine with Flutter/Android SDK installed:

```bash
cd flutter_apk
flutter pub get
flutter build apk --release --dart-define=API_BASE_URL=https://wallet.example.com/api
```

Signing credentials remain local. The supplied debug APK uses a development
endpoint and is not a production/store release. Production native earning still
requires device-integrity integration; see PENDING-WORK.txt.
