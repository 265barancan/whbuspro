# WhatsApp Business Toplu Mesajlaşma Platformu — Proje Manifestosu

## 1. Proje Özeti

Kurumsal WhatsApp Business hesabı üzerinden, **Meta WhatsApp Business Platform API** (Cloud API) kullanılarak, spam/ban riskini minimize eden, onaylı şablon (template) tabanlı, hız sınırlamalı (rate-limited) toplu mesaj gönderim sistemi.

**Kritik not:** Meta, WhatsApp Business API üzerinden "soğuk" pazarlama spamını zaten engeller. Sistem 24 saatlik müşteri hizmet penceresi (Customer Service Window) dışına sadece **önceden onaylanmış mesaj şablonları** ile mesaj gönderebilir. Bu manifest bu kısıtı mimarinin merkezine koyar; bunu bypass etmeye çalışan bir tasarım hem Meta politikalarını ihlal eder hem de hesabın kalıcı banlanmasına yol açar.

---

## 2. Teknoloji Yığını

| Katman | Teknoloji | Not |
|---|---|---|
| Backend Dili | PHP 8.2+ | Kurumsal ortamda yaygın, hızlı geliştirme |
| Framework | Slim 4 veya Laravel (opsiyonel) | Slim: hafif, tam kontrol. Laravel: hazır queue/scheduler altyapısı |
| Veritabanı | MySQL 8.x + **PDO** | İstenildiği gibi, prepared statements zorunlu |
| Kuyruk (Queue) | Redis + PHP kuyruk işçisi (worker) veya `beanstalkd` | Toplu gönderim ASLA senkron yapılmaz |
| Zamanlayıcı | Cron (Linux) + `supervisord` (worker'ları canlı tutmak için) | |
| API Entegrasyonu | Meta WhatsApp Cloud API (Graph API v20+) | Resmi entegrasyon, webhook desteği |
| Frontend | Vue 3 veya vanilla JS + Blade/Twig | SPA gerekmiyorsa basit tutulmalı |
| Kimlik Doğrulama | JWT veya session-based auth | Panel için |
| Loglama | Monolog | Her API çağrısı loglanmalı |
| Sunucu | Nginx + PHP-FPM | HTTPS zorunlu (webhook için) |
| Konteynerizasyon | Docker + docker-compose | Geliştirme/prod tutarlılığı |

---

## 3. Meta WhatsApp Cloud API — Temel Kavramlar (Mimariyi Belirler)

1. **Message Templates (Şablonlar):** Pazarlama/bildirim amaçlı toplu mesajlar SADECE Meta tarafından onaylanmış şablonlarla gönderilebilir. Şablonlar Meta Business Manager'da oluşturulup onaya gönderilir.
2. **Customer Service Window (24 saat):** Kullanıcı size mesaj attıktan sonraki 24 saat içinde serbest metin gönderebilirsiniz; bu pencere dışında yalnızca template mesaj gider.
3. **Opt-in zorunluluğu:** Alıcının açıkça onay vermiş olması gerekir (Meta politikası + KVKK/GDPR).
4. **Quality Rating & Messaging Limits:** Meta, hesabınıza kalite puanına göre günlük gönderim limiti atar (1K/10K/100K/sınırsız). Şikayet oranı (block/report) yükselirse limit düşer veya hesap kısıtlanır — bu yüzden "spama düşmeme" aslında büyük ölçüde Meta'nın kendi mekanizması, sizin işiniz bunu desteklemek.
5. **Webhook:** Teslimat durumları (sent/delivered/read/failed) ve gelen mesajlar webhook ile alınır.

---

## 4. Sistem Mimarisi

```
[Admin Panel (Vue/Blade)]
        │
        ▼
[REST API (PHP + PDO)]
        │
        ├──► [MySQL: kişiler, listeler, şablonlar, kampanyalar, mesaj logları]
        │
        ├──► [Redis Kuyruk] ──► [Worker (PHP CLI, supervisord ile)]
        │                              │
        │                              ▼
        │                   [Meta Graph API /messages]
        │
        └──► [Webhook Endpoint] ◄── [Meta Cloud API (delivery/status/inbound)]
```

**Neden kuyruk zorunlu?**
- Meta API rate limit'lerine (ör. saniyede X mesaj) uymak
- Gönderimler arası bekleme (throttling) ile "bot gibi" davranışı azaltmak
- Hata/retry yönetimi (geçici hatalarda otomatik yeniden deneme, kalıcı hatalarda durdurma)
- Kullanıcıyı bloklamadan büyük listeleri (10.000+) arka planda işlemek

---

## 5. Veritabanı Şeması (MySQL, PDO ile erişilecek)

```sql
-- Kişiler
CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,     -- E.164 formatı: +905xxxxxxxxx
    full_name VARCHAR(150),
    opted_in TINYINT(1) NOT NULL DEFAULT 0,       -- Meta + yasal zorunluluk
    opted_in_at DATETIME NULL,
    opted_out_at DATETIME NULL,
    status ENUM('active','blocked','invalid') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kişi Listeleri (segmentasyon)
CREATE TABLE contact_lists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contact_list_members (
    list_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (list_id, contact_id),
    FOREIGN KEY (list_id) REFERENCES contact_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);

-- Meta'da onaylı şablonlar (senkronize edilir)
CREATE TABLE templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_template_name VARCHAR(150) NOT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'tr',
    category VARCHAR(30),               -- MARKETING, UTILITY, AUTHENTICATION
    status VARCHAR(20),                 -- APPROVED, PENDING, REJECTED
    body_variables_count TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kampanyalar
CREATE TABLE campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    list_id BIGINT UNSIGNED NOT NULL,
    status ENUM('draft','queued','sending','completed','failed','paused') DEFAULT 'draft',
    throttle_per_minute INT DEFAULT 60,   -- dakikada max gönderim
    scheduled_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id),
    FOREIGN KEY (list_id) REFERENCES contact_lists(id)
);

-- Tekil mesaj kayıtları (her alıcı için)
CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    wa_message_id VARCHAR(100) NULL,     -- Meta'dan dönen ID
    status ENUM('queued','sent','delivered','read','failed') DEFAULT 'queued',
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    read_at DATETIME NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    INDEX idx_status (status),
    INDEX idx_campaign (campaign_id)
);

-- API çağrı logları (denetim ve hata ayıklama için)
CREATE TABLE api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(150),
    request_payload JSON,
    response_payload JSON,
    http_status INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 6. Spam / Ban Riskini Azaltan Yazılımsal Önlemler

1. **Throttling:** Kampanya başına dakikada/saatte gönderim limiti (`throttle_per_minute`) — worker bu limite göre kuyruktan çeker, `usleep()`/gecikme ile gönderir.
2. **Kademeli ısınma (warm-up):** Yeni hesaplarda ilk günlerde düşük hacimle başlayıp kademeli artırma.
3. **Opt-in zorunluluğu:** `opted_in = 1` olmayan kişiye kampanya gönderilemez (uygulama katmanında zorunlu kontrol).
4. **Opt-out / STOP yönetimi:** Webhook'tan gelen "dur/çık" gibi mesajlarda `opted_in_at` otomatik güncellenir, kişi otomatik bloklanır.
5. **Şablon çeşitliliği:** Aynı şablonun kısa aralıklarla tekrar tekrar aynı kişiye gönderilmesini engelleyen cooldown kontrolü (ör. aynı kişiye 24 saat içinde max 1 kampanya).
6. **Quality Rating izleme:** Meta Business Manager API'sinden hesap kalite puanı periyodik çekilip düşüşte otomatik durdurma (circuit breaker).
7. **Hata oranı izleme:** `messages.status = 'failed'` oranı belirli eşiği geçerse kampanya otomatik `paused` durumuna alınır.
8. **Geçerli numara doğrulama:** E.164 format kontrolü, gönderim öncesi.

---

## 7. Geliştirme Yol Haritası

### Faz 0 — Hazırlık (1 hafta)
- Meta Business Manager hesabı, WhatsApp Business Platform erişimi, sistem kullanıcısı (System User) ve kalıcı erişim token'ı (permanent token) oluşturma
- En az 1 mesaj şablonunun Meta'ya onaya gönderilmesi (onay süresi birkaç saat–gün sürebilir, en başta başlatılmalı)
- Sunucu, domain, SSL sertifikası, webhook için public HTTPS endpoint hazırlığı

### Faz 1 — Altyapı (1 hafta)
- Docker/docker-compose ortamı: PHP-FPM, Nginx, MySQL, Redis
- MySQL şemasının migration dosyalarıyla kurulması (yukarıdaki tablo yapısı)
- PDO tabanlı veritabanı erişim katmanı (Repository pattern önerilir, ham SQL string birleştirme yasak — prepared statement zorunlu)
- Temel proje iskeleti (routing, .env yönetimi, config)

### Faz 2 — Meta API Entegrasyonu (1-1.5 hafta)
- Graph API istemci sınıfı (`WhatsAppClient`): template listeleme/senkronizasyon, mesaj gönderme, medya yükleme
- Webhook endpoint: doğrulama (verify token), gelen event işleme (status update + inbound mesaj + opt-out algılama)
- API loglama (`api_logs` tablosu) her istek/yanıt için

### Faz 3 — Kişi ve Liste Yönetimi (1 hafta)
- CSV/Excel import (opt-in sütunu zorunlu alan olarak)
- Kişi CRUD, liste oluşturma/segmentasyon
- Numara formatı doğrulama ve tekilleştirme (duplicate) kontrolü

### Faz 4 — Kampanya ve Kuyruk Sistemi (1.5-2 hafta)
- Kampanya oluşturma ekranı: şablon seç, liste seç, throttle ayarla, zamanla
- Kampanya tetiklendiğinde `messages` tablosuna her alıcı için `queued` satır açılması
- Redis kuyruğa job push eden servis
- Worker script (`supervisord` ile arka planda sürekli çalışan) — kuyruktan çek, throttle uygula, Meta API'ye gönder, sonucu `messages` tablosuna yaz
- Otomatik retry mekanizması (geçici hatalar için, ör. max 3 deneme + exponential backoff)

### Faz 5 — İzleme, Raporlama ve Güvenlik Önlemleri (1 hafta)
- Kampanya bazlı gönderim/teslim/okunma/hata oranı dashboard'u
- Quality rating izleme job'u (cron ile periyodik Meta'dan çekme)
- Hata oranı eşiği aşılırsa otomatik durdurma mantığı
- Opt-out/STOP otomasyonu testleri

### Faz 6 — Panel/Kullanıcı Arayüzü Cilası (1 hafta)
- Kimlik doğrulama, rol bazlı yetkilendirme (admin/operatör)
- Şablon önizleme, değişken (variable) doldurma arayüzü
- Kampanya takvimi, canlı ilerleme çubuğu

### Faz 7 — Test ve Canlıya Alma (1 hafta)
- Küçük gerçek liste ile pilot kampanya (warm-up)
- Yük testi (worker'ın throttle'a uyduğunun doğrulanması)
- Prod deployment, cron/supervisord kurulumu, izleme/alarm (ör. Slack/e-posta bildirimleri hata durumunda)

**Toplam tahmini süre:** ~8-10 hafta (tek geliştirici, orta karmaşıklık)

---

## 8. Güvenlik Kontrol Listesi

- Tüm SQL erişimleri **PDO prepared statements** ile (SQL injection önleme)
- Meta erişim token'ları `.env` dosyasında, asla repoya commit edilmez
- Webhook doğrulama token'ı ve `X-Hub-Signature-256` imza kontrolü zorunlu
- Panel için CSRF koruması, rate-limit'li login
- Kişisel veriler (telefon, isim) için KVKK/GDPR uyumlu saklama ve silme (opt-out sonrası) politikası
- HTTPS zorunlu (Meta webhook HTTP'yi kabul etmez)

---

## 9. Önerilen Ek Kütüphaneler (Composer)

```bash
composer require guzzlehttp/guzzle       # HTTP istemcisi (Graph API çağrıları)
composer require vlucas/phpdotenv        # .env yönetimi
composer require monolog/monolog         # Loglama
composer require predis/predis           # Redis kuyruk
composer require respect/validation       # Girdi doğrulama
```

---

## 10. Riskler ve Uyarılar

- Meta politikalarını ihlal eden (opt-in olmayan kişilere pazarlama şablonu gönderme, aşırı yüksek hacimde ani gönderim) kullanım hesabın **kalıcı banlanmasına** yol açabilir; bu manifest bu riski azaltmak üzere tasarlanmıştır ama nihai sorumluluk kullanım politikalarına uymaktır.
- Şablon onay süreci Meta tarafında olduğundan geliştirme takviminin dışında bir bağımlılıktır — Faz 0'da erken başlatılmalı.