<?php

namespace App\Http\Controllers;

use App\Services\Timetable\ClassReminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The bell in the top bar: polled by the page, marks alerts read. */
class NotificationController extends Controller
{
    public function feed(Request $request, ClassReminders $reminders): JsonResponse
    {
        $user = $request->user();
        // Alerts also arrive without a scheduler running: due ones are created on this poll.
        if ($user->role === 'teacher' || $user->isLeadership()) {
            $reminders->run(null, $user);
        }

        $items = $user->notifications()->latest()->limit(12)->get()->map(fn ($n) => [
            'id' => $n->id,
            'kind' => $n->data['kind'] ?? 'info',
            'title' => $n->data['title'] ?? 'Notification',
            'body' => $n->data['body'] ?? '',
            'url' => $this->safeUrl($n->data['url'] ?? null),
            'at' => $n->created_at->diffForHumans(),
            'fresh' => $n->created_at->gt(now()->subMinutes(15)),
            'read' => $n->read_at !== null,
        ]);

        return response()->json(['unread' => $user->unreadNotifications()->count(), 'items' => $items]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['nullable', 'uuid']]);
        $q = $request->user()->unreadNotifications();
        $data['id'] ?? null ? $q->whereKey($data['id'])->update(['read_at' => now()]) : $q->update(['read_at' => now()]);

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    /** Only links inside this application are followed from the bell. */
    private function safeUrl(?string $url): ?string
    {
        return $url && str_starts_with($url, rtrim(url('/'), '/').'/') ? $url : null;
    }
}
