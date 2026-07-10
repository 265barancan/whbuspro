# Portainer ve Docker ile VDS Sunucu Kurulum Kılavuzu

Bu kılavuz, projenin Portainer (Docker Compose) kullanılarak bir VDS sunucu üzerinde baştan sona nasıl kurulacağını açıklamaktadır.

---

## 1. Portainer Stack (Yığın) Kurulumu

Portainer, git depolarından doğrudan Docker Compose dosyalarını okuyarak derleme yapabilir.

1.  Portainer paneline giriş yapın ve sol menüden **Stacks** sekmesine gidin.
2.  Sağ üstteki **Add stack** butonuna tıklayın.
3.  **Build method** (Derleme Yöntemi) olarak **Repository** seçeneğini işaretleyin.
4.  Aşağıdaki alanları doldurun:
    *   **Name:** `whbuspro`
    *   **Repository URL:** `https://github.com/265barancan/whbuspro.git`
    *   **Repository reference:** `refs/heads/main`
    *   **Compose path:** `docker-compose.yml`
5.  **Environment variables** (Çevre Değişkenleri) bölümünde **Advanced mode** seçeneğine tıklayın ve `.env.example` dosyasındaki tüm içeriği buraya yapıştırıp canlı sunucu değerlerinize göre güncelleyin.
    *   *Kritik Değişiklikler:*
        *   `DB_CONNECTION=mysql`
        *   `DB_HOST=db` (Docker MySQL servis adı)
        *   `DB_DATABASE=clerkglobal_whpro`
        *   `DB_USERNAME=root` veya belirlediğiniz kullanıcı
        *   `DB_PASSWORD=veritabanı_şifreniz`
        *   `QUEUE_CONNECTION=redis`
        *   `REDIS_HOST=cache` (Docker Redis servis adı)
6.  En alttaki **Deploy the stack** butonuna tıklayın. Portainer, GitHub'dan kodları çekecek, Dockerfile'ları derleyecek (Nginx ve PHP) ve konteynerleri ayağa kaldıracaktır.

---

## 2. İlk Kurulum Komutlarının Çalıştırılması (Migration & Seed)

Konteynerler ayağa kalktıktan sonra veritabanı şemasını oluşturmak ve admin kullanıcısını seed etmek gerekir:

1.  Portainer'da **Containers** sekmesine gidin.
2.  `whbuspro-app-1` (veya PHP / Laravel servisinin çalıştığı konteyner) adındaki konteynerin yanındaki **Console** (konsol) simgesine tıklayın.
3.  **User** alanını `www-data` olarak değiştirin ve **Connect** deyin.
4.  Terminal ekranında sırasıyla şu komutları çalıştırın:
    ```bash
    # 1. Laravel kütüphanelerini kurun
    composer install --no-dev --optimize-autoloader

    # 2. .env dosyasını oluşturun (Portainer env verilerini buraya yazın veya doğrudan oluşturun)
    php artisan key:generate

    # 3. Veritabanını migrate edin ve admin kullanıcısını oluşturun
    php artisan migrate:fresh --seed
    ```

---

## 3. SSL (HTTPS) ve Domain Yönlendirmesi

Nginx konteynerimiz varsayılan olarak `80` portundan (HTTP) yayın yapar. VDS sunucunuzda domaininizi `http://whbuspro.clerkglobal.net` adresine yönlendirmek ve ücretsiz SSL (Let's Encrypt) kurmak için Portainer önüne **Nginx Proxy Manager** (NPM) veya **Traefik** kurmanız önerilir:

### Nginx Proxy Manager Yapılandırması:
1.  Nginx Proxy Manager paneline gidin -> **Proxy Hosts** -> **Add Proxy Host**.
2.  **Domain Names:** `whbuspro.clerkglobal.net`
3.  **Scheme:** `http`
4.  **Forward IP/Host:** Sunucunuzun yerel IP'si (veya Docker network IP'si)
5.  **Forward Port:** `8080` (Docker Compose'da Nginx portu `8080` olarak dışa açılmıştır).
6.  **SSL** sekmesinden **Request a new SSL Certificate** diyerek SSL'i aktif edin.
