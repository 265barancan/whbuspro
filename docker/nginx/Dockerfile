FROM nginx:alpine

# Nginx varsayılan sunucu ayarlarını kopyala
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Statik varlıklar ve index.php için public klasörünü kopyala
COPY public /var/www/public
