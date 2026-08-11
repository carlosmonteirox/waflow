<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace($request)->contacts()->latest()->paginate(25)]);
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
