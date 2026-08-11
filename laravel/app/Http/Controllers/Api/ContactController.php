<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'has_wa_id' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->contacts()->latest();
        $query->when(filled($attributes['search'] ?? null), function ($query) use ($attributes): void {
            $search = '%'.$attributes['search'].'%';
            $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('phone_number', 'like', $search)->orWhere('wa_id', 'like', $search));
        });
        $query->when(array_key_exists('has_wa_id', $attributes), fn ($query) => $attributes['has_wa_id'] ? $query->whereNotNull('wa_id') : $query->whereNull('wa_id'));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:32'],
            'wa_id' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ]);

        $contact = $this->workspace($request)->contacts()->updateOrCreate(
            ['phone_number' => $attributes['phone_number']],
            [
                'name' => $attributes['name'] ?? null,
                'wa_id' => $attributes['wa_id'] ?? null,
                'metadata' => $attributes['metadata'] ?? [],
            ],
        );

        return response()->json(['data' => $contact], 201);
    }

    public function show(Request $request, Contact $contact): JsonResponse
    {
        $this->ensureWorkspace($contact, $this->workspace($request));

        return response()->json(['data' => $contact]);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->ensureWorkspace($contact, $this->workspace($request));
        $attributes = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['sometimes', 'string', 'max:32'],
            'wa_id' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ]);

        $contact->update($attributes);

        return response()->json(['data' => $contact->fresh()]);
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->ensureWorkspace($contact, $this->workspace($request));
        $contact->delete();

        return response()->json(status: 204);
    }
}
