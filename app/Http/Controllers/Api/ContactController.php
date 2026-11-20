<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:160'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,}$/'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:15', 'max:3000'],
            'website' => ['prohibited'], // honeypot
        ], [
            'email.required_without' => 'Give an email or a phone number so the office can reply.',
            'phone.required_without' => 'Give a phone number or an email so the office can reply.',
        ]);
        unset($data['website']);
        $message = ContactMessage::create($data);

        return response()->json(['message' => 'Thank you — the college office will get back to you.', 'id' => $message->id], 201);
    }
}
