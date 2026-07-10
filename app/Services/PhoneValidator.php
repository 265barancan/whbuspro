<?php

namespace App\Services;

class PhoneValidator
{
    /**
     * Telefon numarasının E.164 formatına uygunluğunu kontrol eder.
     * E.164 formatı: +[ülke_kodu][numara] (maksimum 15 karakter, örn: +905321234567)
     */
    public function isValid(string $phoneNumber): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber);
    }

    /**
     * Ham bir telefon numarasını temizler ve E.164 formatına çevirmeye çalışır.
     *
     * @param string $phoneNumber Ham telefon numarası
     * @param string $defaultCountryCode Varsayılan ülke kodu (+ işareti ile, örn: +90)
     * @return string|null Formatlanmış E.164 numarası veya geçersizse null
     */
    public function format(string $phoneNumber, string $defaultCountryCode = '+90'): ?string
    {
        // Karakterleri temizle (sadece sayılar ve + işareti kalsın)
        $clean = preg_replace('/[^\d+]/', '', $phoneNumber);

        if (empty($clean)) {
            return null;
        }

        // Çift sıfır ile başlıyorsa + işaretine çevir (örn: 0090532... -> +90532...)
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        // Başında + yoksa
        if (!str_starts_with($clean, '+')) {
            // Eğer numara yerel formatta sıfır ile başlıyorsa (örn: 0532...)
            // sıfırı atıp varsayılan ülke kodunu ekle
            if (str_starts_with($clean, '0')) {
                $clean = $defaultCountryCode . substr($clean, 1);
            } else {
                // Sıfır ile başlamıyorsa ve ülke kodu yoksa direkt varsayılan kodu ekle
                // Ancak eğer ülke kodu zaten eklenmiş ama + konmamışsa (örn: 90532...)
                if (str_starts_with($clean, ltrim($defaultCountryCode, '+'))) {
                    $clean = '+' . $clean;
                } else {
                    $clean = $defaultCountryCode . $clean;
                }
            }
        }

        // Son kontrolü yap
        if ($this->isValid($clean)) {
            return $clean;
        }

        return null;
    }
}
