<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $q = trim((string) $request->query('q'));
        $messages = ContactMessage::query()
            ->when($filter === 'unread', fn ($w) => $w->whereNull('read_at'))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")->orWhere('message', 'like', "%{$q}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('studio.messages.index', [
            'messages' => $messages, 'filter' => $filter, 'q' => $q,
            'unreadCount' => ContactMessage::whereNull('read_at')->count(),
            'total' => ContactMessage::count(),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return view('studio.messages.show', [
            'message' => $message,
            'previous' => ContactMessage::where('id', '<', $message->id)->latest('id')->first(),
            'next' => ContactMessage::where('id', '>', $message->id)->oldest('id')->first(),
        ]);
    }

    public function unread(ContactMessage $message): RedirectResponse
    {
        $message->update(['read_at' => null]);

        return redirect()->route('studio.messages.index')->with('success', 'Marked as unread.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('studio.messages.index')->with('success', 'Message deleted.');
    }
}
