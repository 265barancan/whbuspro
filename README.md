# WhatsApp Business Pro - Toplu Mesajlaşma Platformu

Bu proje, Meta Cloud API (v20+) entegrasyonu ile çalışan, kuyruk yapısı (Queue Worker), hız sınırlayıcı (Throttling) ve güvenlik devre kesicisi (Circuit Breaker) barındıran toplu mesaj gönderim ve yönetim arayüzü yazılımıdır.

## Özellikler
* **Yönetim Paneli:** Single Page Application (SPA) mimarisinde, derleme adımı gerektirmeyen Blade + Tailwind + Chart.js kontrol paneli.
* **API Kimlik Doğrulama:** Laravel Sanctum tabanlı token korumalı endpoints.
* **Kişi & Segmentasyon:** CSV import, opt-in/opt-out (STOP kelimesi ile otomatik engel) doğrulama servisleri.
* **Kuyruk & Hız Sınırı:** Throttling (Redis/Veritabanı) tabanlı dakikalık limit kontrolleri.
* **Güvenlik (Resilience):** Circuit Breaker koruması ile yüksek hata oranında kampanyayı otomatik duraklatma.
* **Kalite Takibi:** Meta numara kalite puanı (RED durumu) durumunda otomatik durdurma scheduler job.

## Kurulum
### Canlı Sunucu (cPanel) Kurulumu
Detaylı cPanel kurulum adımları için [cPanel Dağıtım Rehberi](docs/cpanel_deployment.md) dosyasını inceleyin.

### Yerel Geliştirme (Docker)
1. Docker konteynerlerini ayağa kaldırın:
   ```bash
   docker-compose up -d --build
   ```
2. Bağımlılıkları kurun ve migration'ları çalıştırın:
   ```bash
   docker-compose exec app composer install
   docker-compose exec app php artisan migrate --seed
   ```
