# VDS Sunucuda Terminal (SSH) ile Docker Compose Kurulum Kılavuzu

Bu kılavuz, projenin bir VDS (Ubuntu/Debian) sunucu üzerinde, SSH terminali kullanarak Docker ve Docker Compose ile nasıl kurulacağını açıklamaktadır.

---

## 1. Gerekli Araçların Kurulumu (Docker & Git)

Eğer sunucunuzda Docker ve Git kurulu değilse, SSH terminaline root olarak bağlandıktan sonra sırasıyla şu komutları çalıştırın:

```bash
# Sistem paketlerini güncelle
sudo apt-get update && sudo apt-get upgrade -y

# Git, Curl ve gerekli araçları kur
sudo apt-get install git curl -y

# Docker'ı resmi script ile otomatik kur
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Docker Compose'u kur ve yetkilendir
sudo apt-get install docker-compose-plugin -y
```

---

## 2. Projenin Sunucuya Çekilmesi ve Hazırlanması

1.  Proje klasörünü `/var/www/` dizini altında oluşturup GitHub'dan kodları çekin:
    ```bash
    git clone https://github.com/265barancan/whbuspro.git /var/www/whbuspro
    cd /var/www/whbuspro
    ```

2.  Çevre değişkenlerini (`.env`) oluşturun ve düzenleyin:
    ```bash
    cp .env.example .env
    nano .env
    ```
    *   *Nano editöründe düzenlemeniz gereken kritik alanlar:*
        ```env
        APP_ENV=production
        APP_DEBUG=false
        APP_URL=http://whbuspro.clerkglobal.net

        DB_CONNECTION=mysql
        DB_HOST=db                 # Değiştirmeyin (Docker Mysql Servisi)
        DB_DATABASE=clerkglobal_whpro
        DB_USERNAME=root
        DB_PASSWORD=guclu_bir_sifre  # Buraya güçlü bir şifre yazın

        QUEUE_CONNECTION=redis
        REDIS_HOST=cache           # Değiştirmeyin (Docker Redis Servisi)
        ```
    *   Nano editörünü kaydetmek için: `CTRL+O` -> `Enter` -> Çıkmak için `CTRL+X`.

---

## 3. Konteynerlerin Derlenmesi ve Başlatılması

Aşağıdaki komutla tüm Docker servislerini (Nginx, PHP, MySQL, Redis, Worker) arka planda (`-d` detach moduyla) ve sıfırdan derleyerek (`--build`) çalıştırın:

```bash
docker compose up -d --build
```

---

## 4. Konteyner İçi İlk Kurulum Komutları

Konteynerler ayağa kalktıktan sonra, uygulama içindeki bağımlılıkları yüklemek ve veritabanını hazırlamak için ana sunucu terminalinden şu tek satırlık komutları sırasıyla çalıştırın:

```bash
# 1. PHP Composer bağımlılıklarını kurun
docker compose exec -u www-data app composer install --no-dev --optimize-autoloader

# 2. Şifreleme anahtarını üretin
docker compose exec -u www-data app php artisan key:generate

# 3. Veritabanı tablolarını oluşturun ve varsayılan yöneticiyi seed edin
docker compose exec -u www-data app php artisan migrate --seed

# 4. Dosya izinlerini optimize edin (Gerekiyorsa)
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

---

## 5. Konteyner Durumlarını ve Logları Kontrol Etme

Konteynerlerin sorunsuz çalıştığını doğrulamak için şu komutları kullanabilirsiniz:

```bash
# Çalışan konteynerleri listeler
docker compose ps

# Canlı uygulama loglarını izler (Çıkmak için CTRL+C)
docker compose logs -f app
```
