# cPanel Üzerinde Laravel Projesi Canlıya Alma ve Yapılandırma Kılavuzu

Bu doküman, projenin Docker barındırmayan geleneksel **cPanel (paylaşımlı hosting veya VPS)** sunucuları üzerinde nasıl kurulacağını, veritabanı ayarlarını, `.htaccess` güvenlik yapılandırmalarını ve kuyruk işçisi (queue worker) otomasyonlarını adım adım açıklamaktadır.

---

## 1. PHP Sürümü ve Eklenti Gereksinimleri

cPanel panelinizde **MultiPHP Yöneticisi** (MultiPHP Manager) veya **Select PHP Version** ekranına giderek şu ayarları yapın:
*   **PHP Sürümü:** En az **PHP 8.2** veya daha yeni bir sürüm seçin.
*   **Gerekli Eklentiler:** Aşağıdaki eklentilerin aktif olduğundan emin olun:
    *   `pdo_mysql` (Veritabanı bağlantısı için)
    *   `mbstring`, `openssl`, `xml`, `zip`
    *   `bcmath` (Meta API işlemleri için)
    *   `fileinfo` (CSV ve dosya yüklemeleri için)

---

## 1.5. cPanel Git™ Version Control ve GitHub ile Kurulum

Eğer projenizi zip olarak yüklemek yerine doğrudan GitHub üzerinden cPanel'e çekmek ve güncellemeleri tek tıkla almak istiyorsanız bu adımları takip edin:

### Adım 1: GitHub Deposunu cPanel'e Bağlama
1.  cPanel paneline giriş yapın ve **Git™ Version Control** (Git Versiyon Kontrolü) uygulamasına girin.
2.  Sağ üstteki **Create** (Oluştur) butonuna tıklayın.
3.  Aşağıdaki alanları doldurun:
    *   **Clone URL:** `https://github.com/265barancan/whbuspro.git`
    *   **File Path (Dizin):** `/home/clerkglobal/whbuspro.clerkglobal.net` *(Subdomain klasörünüz)*
    *   **Repository Name:** `whbuspro`
4.  **Create** butonuna tıklayın. cPanel, GitHub'daki kodlarınızı otomatik olarak sunucudaki subdomain dizinine klonlayacaktır.

### Adım 2: Bağımlılıkları ve Çevre Ayarlarını Yapma
Klonlama bittikten sonra cPanel Terminal'e bağlanıp sırasıyla şu komutları çalıştırın:
```bash
# 1. Proje dizinine geçin
cd /home/clerkglobal/whbuspro.clerkglobal.net

# 2. .env dosyasını oluşturun
cp .env.example .env

# 3. Kütüphaneleri indirin
composer install --no-dev --optimize-autoloader

# 4. Uygulama anahtarını oluşturun
php artisan key:generate
```

### Adım 3: Güncellemeleri Tek Tıkla Çekme (Update/Pull)
Geliştirme yapıp GitHub'a yeni kod gönderdiğinizde, cPanel'de **Git™ Version Control** alanına girip projenizin yanındaki **Manage** butonuna tıklayarak **Pull or Deploy** sekmesinden **Update** butonuna basarak sunucudaki kodlarınızı tek tıkla güncelleyebilirsiniz.

---

## 2. Güvenli Dosya Dizin Yapısı (cPanel Klasör Bölme)

cPanel'de projenizi doğrudan `public_html` klasörüne yüklemek `.env` dosyanızın ve PHP kaynak kodlarınızın dışarıdan okunmasına sebep olabilir (Güvenlik Açığı). Bu nedenle **klasör bölme** yöntemi uygulanmalıdır:

1.  cPanel Dosya Yöneticisinde (File Manager), ana dizinde (`public_html` klasörünün dışında) `whbuspro` adında bir klasör oluşturun.
2.  Projenin `public` klasörü **dışındaki** tüm dosya ve klasörlerini bu `whbuspro` dizinine yükleyin.
3.  Projenin `public` klasörünün **içindeki** dosyaları (index.php, .htaccess vb.) ise doğrudan `public_html` klasörünün içine yükleyin.

### `public_html/index.php` Düzenlemesi
Giriş noktasının dışarıdaki klasörü görebilmesi için `public_html/index.php` dosyasını açın ve yolları şu şekilde güncelleyin:

```php
// Satır 12 dolayları: Autoloader yolunu güncelle
require __DIR__.'/../whbuspro/vendor/autoload.php';

// Satır 16 dolayları: Bootstrap yolunu güncelle
$app = require_once __DIR__.'/../whbuspro/bootstrap/app.php';
```

### Alternatif: Subdomain Klasör Bölmeden Doğrudan Kurulum (Sizin Senaryonuz)
Eğer tüm proje dosyalarını (kodlar ve public dizini bir arada) subdomain için oluşturduğunuz klasörün (örn: `/home/username/whbuspro.clerkglobal.net/`) içine doğrudan yükleyecekseniz, iki yönteminiz vardır:

*   **Yöntem 1 (cPanel Document Root Değişikliği - En Temizi):**
    cPanel'den subdomain oluştururken veya düzenlerken **Document Root** (Belge Kökü) alanını `/whbuspro.clerkglobal.net` yerine `/whbuspro.clerkglobal.net/public` olarak ayarlayın. Bu durumda hiçbir dosya yolunu veya `.htaccess` dosyasını değiştirmenize gerek kalmaz, sunucu doğrudan `public/` dizinini çalıştırır.
*   **Yöntem 2 (Kök Dizine .htaccess Ekleme):**
    Eğer hosting firmanız subdomain belge kökünü `/public` yapmanıza izin vermiyorsa, projenin en üst kök dizinine (public klasörünün dışına) bir **[.htaccess](file:///Users/barancan/Desktop/whbuspro/.htaccess)** dosyası oluşturup tüm istekleri `public/` klasörüne yönlendirin. Projenin kök dizinine eklemeniz gereken [.htaccess](file:///Users/barancan/Desktop/whbuspro/.htaccess) dosyası oluşturulmuştur.

---

## 3. Apache `.htaccess` Yetkilendirme (Sanctum) Ayarı

cPanel sunucularındaki Apache web sunucusu varsayılan olarak `Authorization` (Bearer Token) başlığını PHP'ye aktarmaz ve API isteklerinde sürekli `401 Unauthorized` hatası alırsınız. Bunu çözmek için `public_html/.htaccess` dosyasının en üstüne şu satırları ekleyin:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Authorization header'ını PHP'ye aktar
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
```

---

## 4. Veritabanı Kurulumu ve `.env` Ayarları

1.  cPanel'den **MySQL® Veritabanı Sihirbazı**'nı kullanarak bir veritabanı ve kullanıcı oluşturun, şifresini belirleyin ve kullanıcıyı veritabanına tüm yetkilerle bağlayın.
2.  `whbuspro` dizinindeki `.env` dosyasını açıp bilgileri güncelleyin:
    ```env
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://sizin-alan-adiniz.com

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=cpanel_db_adi
    DB_USERNAME=cpanel_kullanici_adi
    DB_PASSWORD=veritabanı_sifreniz

    # cPanel'de Redis yoksa kuyruk database olarak ayarlanmalıdır
    QUEUE_CONNECTION=database
    ```

---

## 5. SSH (Terminal) Olmadan Komut Çalıştırma (Migration & Key Generate)

cPanel paketinizde SSH (Terminal) erişimi yoksa, Laravel komutlarını cPanel **Cron İşleri** (Cron Jobs) üzerinden çalıştırabilirsiniz:

1.  cPanel **Cron İşleri** sayfasına gidin.
2.  **Sıklık:** "Dakikada Bir" olarak ayarlayın.
3.  **Komut:** Aşağıdaki komutu ekleyip çalışmasını bekleyin (Migration gerçekleştikten sonra cron işini mutlaka silin):
    ```bash
    /usr/local/bin/php /home/CPANEL_KULLANICI_ADINIZ/whbuspro/artisan migrate --force
    ```
4.  Tablolar oluştuktan sonra bu cron kaydını panelden kaldırın.

---

## 6. cPanel Üzerinde Kuyruk (Queue) ve Zamanlayıcı (Scheduler) Yapılandırması

Paylaşımlı cPanel hostinglerde **Supervisord** kurulu olmadığı için kuyruktaki mesaj gönderim job'larını (`SendWhatsAppMessage`) sürekli işletecek daemon'lar çalıştırılamaz. Bunu çözmek için şu iki yöntem uygulanır:

### Yöntem A: Laravel Zamanlayıcı (Önerilen)
Kuyruk işçisini her dakika tetikleyip iş bittiğinde duracak şekilde cPanel Cron'a bağlarız.

1.  cPanel **Cron İşleri** ekranına gidin.
2.  **Sıklık:** Her dakika (`* * * * *`) olarak ayarlayın.
3.  **Komut:** Laravel zamanlayıcısını tetikleyin:
    ```bash
    /usr/local/bin/php /home/CPANEL_KULLANICI_ADINIZ/whbuspro/artisan schedule:run >> /dev/null 2>&1
    ```
4.  Ardından, projenin `whbuspro/routes/console.php` dosyasına şu satırı ekleyerek zamanlayıcının her dakika kuyruğu kontrol etmesini sağlayın:
    ```php
    use Illuminate\Support\Facades\Schedule;
    
    // Her dakika boşalana kadar kuyruğu çalıştırır
    Schedule::command('queue:work --stop-when-empty')->everyMinute();
    ```

### Yöntem B: Doğrudan Cron İşçisi
Zamanlayıcıyı araya sokmadan doğrudan her dakika kuyruğu kontrol eden bir cron işi oluşturabilirsiniz:
*   **Sıklık:** Her dakika (`* * * * *`)
*   **Komut:**
    ```bash
    /usr/local/bin/php /home/CPANEL_KULLANICI_ADINIZ/whbuspro/artisan queue:work --stop-when-empty >> /dev/null 2>&1
    ```
