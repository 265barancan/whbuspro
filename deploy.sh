#!/bin/bash

# Renkli çıktı tanımları
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0;3m' # No Color

echo -e "${YELLOW}=== WHBusPro Tek Tıkla VDS Kurulumu Başlatılıyor ===${NC}"

# 1. Bozuk Docker önbelleklerini temizle (Hata almayı önlemek için)
echo -e "${YELLOW}[1/5] Bozuk Docker önbellekleri temizleniyor...${NC}"
docker builder prune -f
docker system prune -f

# 2. Çevre dosyası (.env) kontrolü ve kopyalama
if [ ! -f .env ]; then
    echo -e "${YELLOW}[2/5] .env dosyası bulunamadı, şablondan kopyalanıyor...${NC}"
    cp .env.example .env
    
    # Kullanıcıdan DB şifresini al veya rastgele üret
    read -p "MySQL için belirlemek istediğiniz veritabanı şifresini girin (Boş bırakırsanız rastgele oluşturulur): " DB_PASS
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(openssl rand -hex 12)
    fi
    
    # .env dosyasındaki varsayılan değerleri güncelle
    sed -i "s/DB_PASSWORD=secretpassword/DB_PASSWORD=$DB_PASS/g" .env
    sed -i "s/MYSQL_PASSWORD=secretpassword/MYSQL_PASSWORD=$DB_PASS/g" docker-compose.yml
    
    echo -e "${GREEN}✓ .env dosyası oluşturuldu ve şifreler güncellendi.${NC}"
else
    echo -e "${GREEN}✓ .env dosyası mevcut, devam ediliyor.${NC}"
fi

# 3. Docker Konteynerlerini Derle ve Arka Planda Başlat
echo -e "${YELLOW}[3/5] Docker imajları derleniyor ve servisler başlatılıyor...${NC}"
docker compose down
docker compose up -d --build

# Konteynerlerin ayağa kalkmasını 5 saniye bekle
sleep 5

# 4. Uygulama Anahtarını Üret ve Dosya İzinlerini Ayarla
echo -e "${YELLOW}[4/5] Laravel uygulama anahtarı oluşturuluyor...${NC}"
docker compose exec -u www-data app php artisan key:generate
docker compose exec app chown -R www-data:www-data storage bootstrap/cache

# 5. Veritabanını Yapılandır ve Seed Et
echo -e "${YELLOW}[5/5] Veritabanı tabloları ve varsayılan yönetici oluşturuluyor...${NC}"
docker compose exec -u www-data app php artisan migrate --seed --force

echo -e "${GREEN}==================================================${NC}"
echo -e "${GREEN}✓ KURULUM BAŞARIYLA TAMAMLANDI!${NC}"
echo -e "${GREEN}✓ Web arayüzü adresi: http://localhost:8080 (veya VDS IP adresiniz:8080)${NC}"
echo -e "${GREEN}✓ Varsayılan Giriş Bilgileri:${NC}"
echo -e "${GREEN}  E-Posta: admin@whbuspro.com${NC}"
echo -e "${GREEN}  Şifre: admin12345${NC}"
echo -e "${GREEN}==================================================${NC}"
