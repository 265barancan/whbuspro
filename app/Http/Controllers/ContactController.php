<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use App\Services\PhoneValidator;
use App\Services\ContactImporter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    protected PhoneValidator $phoneValidator;
    protected ContactImporter $contactImporter;

    public function __construct(PhoneValidator $phoneValidator, ContactImporter $contactImporter)
    {
        $this->phoneValidator = $phoneValidator;
        $this->contactImporter = $contactImporter;
    }

    /**
     * Kişi listesini filtreler ve sayfalar.
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        // Arama (İsim veya Numara)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Opt-in durumu filtresi
        if ($request->has('opted_in')) {
            $query->where('opted_in', $request->boolean('opted_in'));
        }

        // Statü filtresi
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Belirli bir listedekiler
        if ($request->filled('list_id')) {
            $query->whereHas('lists', function($q) use ($request) {
                $q->where('contact_lists.id', $request->input('list_id'));
            });
        }

        $contacts = $query->latest()->paginate($request->input('per_page', 25));

        return response()->json($contacts);
    }

    /**
     * Yeni kişi oluşturur (E.164 doğrulamalı).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'full_name' => 'nullable|string|max:150',
            'opted_in' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(['active', 'blocked', 'invalid'])]
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rawPhone = $request->input('phone_number');
        $formattedPhone = $this->phoneValidator->format($rawPhone);

        if (!$formattedPhone) {
            return response()->json([
                'errors' => ['phone_number' => ['Telefon numarası E.164 formatına uygun değil veya geçersiz.']]
            ], 422);
        }

        // Çakışan numara kontrolü
        if (Contact::where('phone_number', $formattedPhone)->exists()) {
            return response()->json([
                'errors' => ['phone_number' => ['Bu telefon numarası zaten sistemde kayıtlı.']]
            ], 422);
        }

        $optedIn = $request->boolean('opted_in', false);

        $contact = Contact::create([
            'phone_number' => $formattedPhone,
            'full_name' => $request->input('full_name'),
            'opted_in' => $optedIn,
            'opted_in_at' => $optedIn ? now() : null,
            'opted_out_at' => !$optedIn ? now() : null,
            'status' => $request->input('status', $optedIn ? 'active' : 'blocked'),
        ]);

        return response()->json($contact, 201);
    }

    /**
     * Tekil kişi detayını döner.
     */
    public function show(Contact $contact)
    {
        return response()->json($contact->load('lists'));
    }

    /**
     * Kişi günceller.
     */
    public function update(Request $request, Contact $contact)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string',
            'full_name' => 'nullable|string|max:150',
            'opted_in' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(['active', 'blocked', 'invalid'])]
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];

        if ($request->filled('full_name')) {
            $data['full_name'] = $request->input('full_name');
        }

        if ($request->filled('phone_number')) {
            $rawPhone = $request->input('phone_number');
            $formattedPhone = $this->phoneValidator->format($rawPhone);

            if (!$formattedPhone) {
                return response()->json([
                    'errors' => ['phone_number' => ['Telefon numarası E.164 formatına uygun değil.']]
                ], 422);
            }

            // Başka birinin numarası ile çakışıyor mu?
            if (Contact::where('phone_number', $formattedPhone)->where('id', '!=', $contact->id)->exists()) {
                return response()->json([
                    'errors' => ['phone_number' => ['Bu telefon numarası başka bir kullanıcı tarafından kullanılıyor.']]
                ], 422);
            }

            $data['phone_number'] = $formattedPhone;
        }

        if ($request->has('opted_in')) {
            $optedIn = $request->boolean('opted_in');
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
        }

        if ($request->filled('status')) {
            $data['status'] = $request->input('status');
        }

        $contact->update($data);

        return response()->json($contact);
    }

    /**
     * Kişi siler.
     */
    public function destroy(Contact $contact)
    {
        $contact->delete();
        return response()->json(['message' => 'Kişi başarıyla silindi.']);
    }

    /**
     * CSV dosyasından kişileri içe aktarır.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_id' => 'required|exists:contact_lists,id',
            'file' => 'required|file|mimes:csv,txt|max:10240', // Maksimum 10MB CSV
            'default_country_code' => 'nullable|string|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $listId = $request->input('list_id');
        $defaultCountry = $request->input('default_country_code', '+90');

        try {
            $stats = $this->contactImporter->importFromCsv(
                $file->getRealPath(),
                $listId,
                $defaultCountry
            );

            return response()->json([
                'message' => 'İçe aktarım tamamlandı.',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'İçe aktarım başarısız oldu.',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
