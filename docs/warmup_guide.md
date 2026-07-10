# WhatsApp Business Numarası Warm-up (Isınma) ve Limit Büyütme Kılavuzu

Yeni kurulan bir Meta WhatsApp Business Cloud API hesabında, spam gönderimleri önlemek amacıyla Meta tarafından günlük mesaj limitleri uygulanır. Bu kılavuz, numara kalitesini yeşil (GREEN) tutarak limitleri spama takılmadan güvenle yükseltmeniz için hazırlanmıştır.

---

## 1. Meta Mesajlaşma Limit Tiers (Limit Dereceleri)

Meta, işletme doğrulama (Business Verification) durumunuza ve gönderim geçmişinize göre hesabınıza şu limitleri atar:
*   **Tier 1:** Günlük 1.000 benzersiz alıcıya mesaj gönderimi (Yeni numaraların varsayılan başlangıç limiti).
*   **Tier 2:** Günlük 10.000 benzersiz alıcıya mesaj gönderimi.
*   **Tier 3:** Günlük 100.000 benzersiz alıcıya mesaj gönderimi.
*   **Tier 4:** Sınırsız günlük alıcı (Unlimited).

> [!NOTE]
> Bu limitler sadece işletme tarafından başlatılan (şablonlu) konuşmaları sınırlar. Kullanıcılardan gelen mesajlara verilen yanıtlar bu limite dahil değildir.

---

## 2. Limitleri Yükseltme Kriterleri (Nasıl Tier Atlanır?)

Meta, limitinizi bir üst seviyeye otomatik olarak yükseltir. Bunun için şu 3 şartın karşılanması gerekir:
1.  **Numara Durumunun Sağlıklı Olması:** Telefon numarası durumunun `CONNECTED` olması.
2.  **Yüksek Kalite Puanı:** Numara Kalite Puanının (Quality Rating) **Yeşil (GREEN)** veya **Sarı (YELLOW)** olması. (Kırmızı `RED` olmamalıdır).
3.  **Hacim Eşiğinin Aşılması:** Son 7 gün içinde, mevcut günlük limitinizin toplamda en az **2 katı** kadar hacimde mesaj gönderilmiş olması.
    *   *Örnek (Tier 1 için):* Günlük limitiniz 1.000'dir. 7 gün içerisinde toplamda en az 2.000 benzersiz alıcıya mesaj göndermiş olmanız gerekir.

---

## 3. Örnek Warm-up Programı (7-10 Günlük Takvim)

Limitinizin otomatik olarak Tier 1'den (1.000) Tier 2'ye (10.000) geçmesini tetiklemek için aşağıdaki gibi kademeli bir gönderim programı uygulayabilirsiniz.

| Gün | Hedef Benzersiz Alıcı Sayısı | Açıklama |
|---|---|---|
| **1. Gün** | 200 | En sadık/aktif müşteri kitlesine gönderim yapın (Geri bildirim ihtimali yüksek olanlar). |
| **2. Gün** | 350 | Gönderim yapılan kişilerden "Engelleme" (Block) veya şikayet gelmediğini Webhook üzerinden takip edin. |
| **3. Gün** | 500 | Gönderim hacmini artırın. Gönderimler arasına jitter (bekleme) eklemeyi unutmayın. |
| **4. Gün** | 800 | 4. gün sonunda toplamda 1.850 gönderime ulaşıldı. |
| **5. Gün** | 1.000 | Günlük maksimum limite ulaşın (Havuz doldu). |
| **6. Gün** | 1.000 | İkinci kez günlük limiti doldurun. Toplam 7 günlük gönderim 3.850 oldu (2.000 barajı aşıldı). |
| **7. Gün** | - | Meta'nın limiti otomatik olarak **10.000 (Tier 2)** yapmasını bekleyin (Genellikle 24 saat içinde yansır). |

---

## 4. Kalite Puanını Yeşil (GREEN) Tutmak İçin Önemli Kurallar

Spam/ban riskini sıfıra indirmek için teknik ve operasyonel olarak şu kurallara uyun:

1.  **Hızlı Opt-out (STOP) İşleme:**
    *   Sistemimizdeki WebhookController, gelen mesajlarda "DUR/STOP" algıladığında kullanıcıyı anında engeller. Bu otomasyonun çalışır durumda olduğundan emin olun. Alıcılar engellemek yerine STOP yazmayı tercih ederse numara kaliteniz korunur.
2.  **Şablon Alaka Düzeyi:**
    *   Şablonlarınızı çok net, yanıltıcı olmayan ve doğrudan müşterinin rızası dahilindeki konularda (kargo bildirimi, sipariş onayı, fatura vb.) oluşturun. "Soğuk" pazarlama mesajlarını kitlelere aniden yollamayın.
3.  **Hız Sınırlaması (Throttling):**
    *   Yeni numaralarda gönderim limitinizi dakikada en fazla 30-60 mesaj (`throttle_per_minute = 30`) olarak ayarlayın. Yüksek hızda ani gönderimler Meta'nın spambot algoritmalarını tetikler.
4.  **Circuit Breaker Takibi:**
    *   Gönderim esnasında hata oranı %10'u geçerse sistemin kampanyayı otomatik durdurduğunu (Faz 5 koruması) unutmayın. Hatalı numaraları listenizden temizleyip kampanyayı öyle devam ettirin.
