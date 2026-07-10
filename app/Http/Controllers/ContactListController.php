<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContactList;
use Illuminate\Support\Facades\Validator;

class ContactListController extends Controller
{
    /**
     * Tüm listeleri üye sayılarıyla birlikte listeler.
     */
    public function index()
    {
        $lists = ContactList::withCount('contacts')->latest()->get();
        return response()->json($lists);
    }

    /**
     * Yeni liste oluşturur.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150|unique:contact_lists,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $list = ContactList::create([
            'name' => $request->input('name')
        ]);

        return response()->json($list, 201);
    }

    /**
     * Liste detayını ve listedeki kişileri döner.
     */
    public function show(ContactList $contactList)
    {
        return response()->json([
            'list' => $contactList,
            'contacts' => $contactList->contacts()->paginate(50)
        ]);
    }

    /**
     * Liste günceller.
     */
    public function update(Request $request, ContactList $contactList)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150|unique:contact_lists,name,' . $contactList->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contactList->update([
            'name' => $request->input('name')
        ]);

        return response()->json($contactList);
    }

    /**
     * Liste siler.
     */
    public function destroy(ContactList $contactList)
    {
        $contactList->delete();
        return response()->json(['message' => 'Liste başarıyla silindi.']);
    }

    /**
     * Belirtilen kişileri listeye ekler (Attach).
     */
    public function attachContacts(Request $request, ContactList $contactList)
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Senkronize etmeden eklemek için syncWithoutDetaching kullan
        $contactList->contacts()->syncWithoutDetaching($request->input('contact_ids'));

        return response()->json([
            'message' => 'Kişiler başarıyla listeye eklendi.',
            'contacts_count' => $contactList->contacts()->count()
        ]);
    }

    /**
     * Belirtilen kişileri listeden çıkarır (Detach).
     */
    public function detachContacts(Request $request, ContactList $contactList)
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contactList->contacts()->detach($request->input('contact_ids'));

        return response()->json([
            'message' => 'Kişiler başarıyla listeden çıkarıldı.',
            'contacts_count' => $contactList->contacts()->count()
        ]);
    }
}
