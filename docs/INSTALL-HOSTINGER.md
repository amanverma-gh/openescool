# Install OpenEscool on a Hostinger VPS

This guide takes you from an empty VPS to a live site with HTTPS, about 30–45 minutes. You copy and paste each block into the server's terminal.

**What you need**

- A Hostinger VPS: **[get one here](https://www.hostg.xyz/aff_c?offer_id=6&aff_id=67383&url_id=3039)**. **KVM 1** is enough to start; pick **KVM 2** if you expect thousands of members or many video lessons.
- A domain, for example `koko.com` (from Hostinger or anywhere else).
- About 30 minutes.

Throughout this guide, replace `koko.com` with your domain.

---

## 1. Set up the VPS in hPanel

1. In hPanel, open **VPS** → your server → **OS & Panel** → **Operating System**, and choose a plain **Ubuntu 24.04** (no control panel).
2. Set a strong **root password**, and write down the server's **IP address** (shown on the VPS overview page).
3. Point your domain at the server: in your domain's **DNS settings**, add:

   | Type | Name | Points to |
   | --- | --- | --- |
   | A | `@` | your VPS IP |
   | A | `www` | your VPS IP |

   DNS can take a few minutes up to a few hours to update.

## 2. Connect to the server

On Windows, open **PowerShell**; on Mac, open **Terminal**. Then:

```bash
ssh root@YOUR_VPS_IP
```

Type `yes` the first time, then your root password. Or use the **Browser terminal** button in hPanel.

## 3. Install the software

PHP 8.3, MySQL, Nginx, Composer, Node.js and Git, in one go:

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server git unzip curl ufw certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
```

Turn on the firewall (keeps SSH and the website open):

```bash
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

Allow uploads up to 100 MB (for lesson videos and files):

```bash
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 100M/; s/^post_max_size.*/post_max_size = 110M/' /etc/php/8.3/fpm/php.ini
# Lets the one-click update in Admin settings find git, composer and npm.
sed -i 's/^;env\[PATH\]/env[PATH]/' /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm
```

## 4. Create the database

Pick your own password instead of `CHANGE_THIS_PASSWORD`, and keep it for step 5:

```bash
mysql -e "CREATE DATABASE openescool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'openescool'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON openescool.* TO 'openescool'@'localhost'; FLUSH PRIVILEGES;"
```

## 5. Download OpenEscool

The site runs as the `www-data` user, which also lets the one-click update work later.

```bash
mkdir -p /var/www && chown www-data:www-data /var/www
cd /var/www
sudo -u www-data git clone https://github.com/amanverma-gh/openescool.git
cd openescool
sudo -u www-data cp .env.example .env
```

Now edit the settings file:

```bash
nano .env
```

Change these lines (use your domain and the database password from step 4):

```ini
APP_NAME=OpenEscool
APP_ENV=production
APP_DEBUG=false
APP_URL=https://koko.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=openescool
DB_USERNAME=openescool
DB_PASSWORD=CHANGE_THIS_PASSWORD
```

Save with **Ctrl+O**, **Enter**, then exit with **Ctrl+X**.

Install everything and set up the database:

```bash
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan key:generate
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link
sudo -u www-data npm ci
sudo -u www-data npm run build
sudo -u www-data php artisan optimize
```

> Don't run `php artisan migrate --seed` on a live site; the seed adds demo users and posts.

## 6. Connect your domain (Nginx)

Put your domain on the first line, then paste the whole block:

```bash
DOMAIN=koko.com
cat > /etc/nginx/sites-available/openescool <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root /var/www/openescool/public;
    index index.php;
    client_max_body_size 110M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
EOF
ln -sf /etc/nginx/sites-available/openescool /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

## 7. Turn on HTTPS (free SSL)

Once your domain points to the server (step 1). Use your own email, for renewal notices. (If you opened a new terminal since step 6, run `DOMAIN=koko.com` again first.)

```bash
certbot --nginx -d $DOMAIN -d www.$DOMAIN --agree-tos -m you@example.com --redirect -n
```

The certificate renews itself automatically.

## 8. Background jobs (emails, reminders, renewals)

One cron entry runs everything: emails, digests, event reminders and subscription checks.

```bash
(crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/openescool && php artisan schedule:run >> /dev/null 2>&1") | crontab -u www-data -
```

## 9. Open your site

1. Go to `https://koko.com` and **sign up**. **The first account becomes the platform admin.**
2. Open the user menu → **Admin settings** and set:
   - **Site name and logo**
   - **Email (Brevo)**: paste your Brevo API key, then click **Send test email**
   - **Home page**: open one community, or show the Discover page
   - **Search engines**, **Upload limits**, **GIF picker** (GIPHY key)
3. Click **Create a community** and invite your members.

For payments, add your Stripe and/or Razorpay keys to `.env` (see [Payments](GUIDE.md#payments)), then run `sudo -u www-data php artisan optimize`.

---

## Updating

Back up first (hPanel → VPS → **Backups** → create a snapshot). Then:

```bash
cd /var/www/openescool
sudo -u www-data php artisan app:update
```

Or click **Update now** in **Admin settings → Updates**. It runs the same steps, but if something fails half-way you'll have to fix it from the terminal, so the command is the safer way.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| "500 Server Error" | Run `tail -50 /var/www/openescool/storage/logs/laravel.log` to see the error. |
| Page doesn't load at all | Check DNS points to the VPS IP; run `nginx -t` and `systemctl status php8.3-fpm`. |
| Emails don't arrive | Admin settings → Email → Send test email. Check the sender is verified in Brevo, and that the cron from step 8 is set: `crontab -u www-data -l`. |
| Uploads fail | Check step 3 (`upload_max_filesize`) and `client_max_body_size` in step 6, and the limits in Admin settings → Upload limits. |
| Changed `.env` but nothing happens | `sudo -u www-data php artisan optimize` |
| Permission errors | `chown -R www-data:www-data /var/www/openescool` |

Still stuck? Ask at [openescool.userjot.com](https://openescool.userjot.com/).
