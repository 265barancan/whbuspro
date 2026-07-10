<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Services\PhoneValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactImporter
{
    protected PhoneValidator $phoneValidator;

    public function __construct(PhoneValidator $phoneValidator)
    {
        $this->phoneValidator = $phoneValidator;
    }

    /**
     * CSV dosyasından kişileri okur ve belirtilen listeye ekler.
     *
     * @param string $filePath CSV dosyasının mutlak yolu
     * @param int $listId Hedef kişi listesi ID'si
     * @param string $defaultCountryCode Telefon numaraları için varsayılan ülke kodu
     * @return array İçe aktarım istatistikleri ve hatalar
     * @throws \Exception
     */
    public function importFromCsv(string $filePath, int $listId, string $defaultCountryCode = '+90'): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("CSV dosyası bulunamadı veya okunabilir değil.");
        }

        $list = ContactList::find($listId);
        if (!$list) {
            throw new \Exception("Hedef kişi listesi bulunamadı (ID: {$listId}).");
        }

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new \Exception("Dosya açılamadı.");
        }

        // Header satırını oku
        $headers = fgetcsv($file, 0, ',');
        if (!$headers) {
            fclose($file);
            throw new \Exception("CSV dosyasının başlık satırı boş.");
        }

        // Sütun indekslerini belirle (küçük harfe çevirip eşleştirerek)
        $headers = array_map('strtolower', array_map('trim', $headers));
        
        $phoneIndex = $this->findHeaderIndex($headers, ['phone_number', 'phone', 'telefon', 'numara']);
        $nameIndex = $this->findHeaderIndex($headers, ['full_name', 'name', 'ad_soyad', 'ad', 'isim']);
        $optInIndex = $this->findHeaderIndex($headers, ['opted_in', 'opt_in', 'onay', 'optin']);

        // Zorunlu alan kontrolleri
        if ($phoneIndex === -1) {
            fclose($file);
            throw new \Exception("Zorunlu sütun eksik: Telefon numarası sütunu bulunamadı.");
        }
        if ($optInIndex === -1) {
            fclose($file);
            throw new \Exception("Zorunlu sütun eksik: 'opted_in' onay sütunu bulunmalıdır.");
        }

        $stats = [
            'total_rows' => 0,
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($file, 0, ',')) !== false) {
                // Boş satırları atla
                if (empty(array_filter($row))) {
                    continue;
                }

                $stats['total_rows']++;
                $rowNumber = $stats['total_rows'] + 1; // 1-based row count including header

                $rawPhone = $row[$phoneIndex] ?? '';
                $rawName = $nameIndex !== -1 ? ($row[$nameIndex] ?? null) : null;
                $rawOptIn = $row[$optInIndex] ?? '';

                // Telefon numarasını formatla ve doğrula
                $formattedPhone = $this->phoneValidator->format($rawPhone, $defaultCountryCode);
                if (!$formattedPhone) {
                    $stats['failed']++;
                    $stats['errors'][] = "Satır {$rowNumber}: Geçersiz telefon numarası formatı ({$rawPhone})";
                    continue;
                }

                // Opt-in değerini boolean yap
                $optedIn = $this->parseBoolean($rawOptIn);

                // Kişiyi bul veya oluştur (upsert)
                $contact = Contact::where('phone_number', $formattedPhone)->first();
                $isNew = !$contact;

                $data = [
                    'phone_number' => $formattedPhone,
                    'full_name' => $rawName ? trim($rawName) : null,
                ];

                // Opt-in durumu değiştiyse tarihleri güncelle
                if ($isNew) {
                    $data['opted_in'] = $optedIn;
                    $data['opted_in_at'] = $optedIn ? now() : null;
                    $data['opted_out_at'] = !$optedIn ? now() : null;
                    $data['status'] = $optedIn ? 'active' : 'blocked';
                    
                    $contact = Contact::create($data);
                    $stats['imported']++;
                } else {
                    // Mevcut kişi ise ve opt-in durumu güncelleniyorsa
                    if ($contact->opted_in !== $optedIn) {
                        $data['opted_in'] = $optedIn;
                        if ($optedIn) {
                            $data['opted_in_at'] = now();
                            $data['opted_out_at'] = null;
                            $data['status'] = 'active';
                        } else {
                            $data['opted_out_at'] = now();
                            $data['status'] = 'blocked';
                        }
                    }
                    
                    $contact->update($data);
                    $stats['updated']++;
                }

                // Pivot tabloya (contact_list_members) bağla
                if (!$list->contacts()->where('contact_id', $contact->id)->exists()) {
                    $list->contacts()->attach($contact->id);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            Log::error("CSV Import Transaction Failed: " . $e->getMessage());
            throw $e;
        }

        fclose($file);
        return $stats;
    }

    /**
     * Eşleşen başlık indeksini bulur.
     */
    protected function findHeaderIndex(array $headers, array $searchTerms): int
    {
        foreach ($searchTerms as $term) {
            $index = array_search($term, $headers);
            if ($index !== false) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Metinsel boolean değerleri (1, true, yes, active, onay, vb.) boolean'a çevirir.
     */
    protected function parseBoolean(string $value): bool
    {
        $normalized = strtolower(trim($value));
        
        $truthy = ['1', 'true', 'yes', 'y', 'on', 'active', 'onay', 'evet', 'opt_in', 'opted_in'];
        
        return in_array($normalized, $truthy, true);
    }
}
