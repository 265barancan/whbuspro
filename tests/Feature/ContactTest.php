<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contact;
use App\Models\ContactList;
use App\Services\PhoneValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected PhoneValidator $phoneValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->phoneValidator = new PhoneValidator();
    }

    /**
     * PhoneValidator test format method.
     */
    public function test_phone_validator_formatting(): void
    {
        // 1. Doğru format (+90532...)
        $this->assertEquals('+905321234567', $this->phoneValidator->format('+905321234567'));
        $this->assertTrue($this->phoneValidator->isValid('+905321234567'));

        // 2. Sıfırla başlayan yerel numara (0532...) -> +90 otomatik eklenmeli
        $this->assertEquals('+905321234567', $this->phoneValidator->format('05321234567'));

        // 3. Sıfırsız yerel numara (532...) -> +90 otomatik eklenmeli
        $this->assertEquals('+905321234567', $this->phoneValidator->format('5321234567'));

        // 4. Ülke kodlu ama artı işareti eksik numara (90532...) -> + eklenmeli
        $this->assertEquals('+905321234567', $this->phoneValidator->format('905321234567'));

        // 5. Çift sıfırlı uluslararası numara (0090532...) -> +90 olmalı
        $this->assertEquals('+905321234567', $this->phoneValidator->format('00905321234567'));

        // 6. Boşluklu ve parantezli numara -> Temizlenmeli
        $this->assertEquals('+905321234567', $this->phoneValidator->format('0 (532) 123-45-67'));

        // 7. Tamamen geçersiz numara -> null dönmeli
        $this->assertNull($this->phoneValidator->format('abc123xyz'));
        $this->assertFalse($this->phoneValidator->isValid('abc123xyz'));
    }

    /**
     * API contact store validations test.
     */
    public function test_contact_store_validation_and_duplicate_prevention(): void
    {
        // 1. Yeni kişi ekle
        $response = $this->postJson('/api/contacts', [
            'phone_number' => '05554443322',
            'full_name' => 'Can Baran',
            'opted_in' => true
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contacts', [
            'phone_number' => '+905554443322',
            'full_name' => 'Can Baran',
            'opted_in' => true,
            'status' => 'active'
        ]);

        // 2. Aynı kişi tekrar eklenmeye çalışıldığında hata vermeli
        $responseDuplicate = $this->postJson('/api/contacts', [
            'phone_number' => '+90 555 444 33 22',
            'full_name' => 'Can Baran Copy'
        ]);

        $responseDuplicate->assertStatus(422);
        $responseDuplicate->assertJsonValidationErrors(['phone_number']);
    }

    /**
     * CSV import feature integration test.
     */
    public function test_contact_csv_import_success(): void
    {
        // Hedef listeyi oluştur
        $list = ContactList::create(['name' => 'Test Müşterileri']);

        // CSV dosyası içeriği (header + 3 satır verisi)
        $csvContent = "phone_number,full_name,opted_in\n";
        $csvContent .= "05321112233,Ahmet Yilmaz,1\n";      // valid, opted_in=1
        $csvContent .= "905423334455,Mehmet Demir,true\n";   // valid, opted_in=true
        $csvContent .= "05051234567,,0\n";                   // valid, opted_in=0
        $csvContent .= "invalid-number,Hatalı Satır,1\n";    // invalid phone

        // Sahte dosya yükleme nesnesi
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->postJson('/api/contacts/import', [
            'list_id' => $list->id,
            'file' => $file,
            'default_country_code' => '+90'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'İçe aktarım tamamlandı.',
            'data' => [
                'total_rows' => 4,
                'imported' => 3, // 3 geçerli kişi eklendi/güncellendi
                'failed' => 1    // 1 hatalı numara
            ]
        ]);

        // Veritabanını kontrol et
        $this->assertDatabaseHas('contacts', [
            'phone_number' => '+905321112233',
            'full_name' => 'Ahmet Yilmaz',
            'opted_in' => true,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('contacts', [
            'phone_number' => '+905423334455',
            'full_name' => 'Mehmet Demir',
            'opted_in' => true
        ]);

        $this->assertDatabaseHas('contacts', [
            'phone_number' => '+905051234567',
            'opted_in' => false,
            'status' => 'blocked' // opted_in=0 olduğundan otomatik blok
        ]);

        // İlişkileri (Pivot) kontrol et
        $this->assertEquals(3, $list->contacts()->count());
    }

    /**
     * Contact lists CRUD and members attach/detach tests.
     */
    public function test_contact_lists_crud_and_attachments(): void
    {
        // 1. Liste Oluştur
        $response = $this->postJson('/api/contact-lists', ['name' => 'Kampanya A Grubu']);
        $response->assertStatus(201);
        $listId = $response->json('id');

        // 2. Kişiler oluştur
        $contact1 = Contact::create(['phone_number' => '+905551111111', 'opted_in' => true]);
        $contact2 = Contact::create(['phone_number' => '+905552222222', 'opted_in' => true]);

        // 3. Kişileri listeye bağla (Attach)
        $attachResponse = $this->postJson("/api/contact-lists/{$listId}/attach", [
            'contact_ids' => [$contact1->id, $contact2->id]
        ]);
        $attachResponse->assertStatus(200);
        $this->assertEquals(2, $attachResponse->json('contacts_count'));

        // 4. Kişileri listeden çıkar (Detach)
        $detachResponse = $this->postJson("/api/contact-lists/{$listId}/detach", [
            'contact_ids' => [$contact1->id]
        ]);
        $detachResponse->assertStatus(200);
        $this->assertEquals(1, $detachResponse->json('contacts_count'));
    }
}
