#!/bin/bash
# Startup kustom Ion Jaya Motor: arahkan document root nginx ke folder public Laravel.
# Dijalankan tiap kali container App Service start (via Startup Command di Portal).
# Perintah-perintah di sini sama persis dengan yang sudah terbukti jalan via SSH.

sed -i 's|root /home/site/wwwroot;|root /home/site/wwwroot/public;|' /etc/nginx/sites-enabled/default

if ! grep -q 'try_files' /etc/nginx/sites-enabled/default; then
  sed -i '/location \/ {/a\        try_files $uri $uri/ /index.php?$query_string;' /etc/nginx/sites-enabled/default
fi

# Muat ulang nginx hanya bila sintaks config valid; kalau gagal, nginx tetap
# jalan dengan config lama (aman), dan php-fpm tetap dinyalakan di bawah.
if nginx -t; then
  sleep 2
  nginx -s reload || service nginx restart
fi

php-fpm
