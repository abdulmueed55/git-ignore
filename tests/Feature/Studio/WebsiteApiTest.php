<?php

namespace Tests\Feature\Studio;

use App\Models\ContactMessage;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $a): Post
    {
        return Post::create(array_merge(['type' => 'announcement', 'excerpt' => str_repeat('Summary ', 4), 'body' => '<p>Body text</p>', 'status' => 'published', 'published_at' => now()->subHour()], $a));
    }

    public function test_lists_return_only_live_posts(): void
    {
        $this->makePost(['title' => 'Live one']);
        $this->makePost(['title' => 'Draft one', 'status' => 'draft']);
        $this->makePost(['title' => 'Future one', 'status' => 'scheduled', 'published_at' => now()->addDay()]);
        $this->makePost(['title' => 'Expired one', 'expires_at' => now()->subMinute()]);
        $this->makePost(['title' => 'A notice', 'type' => 'notice']);

        $res = $this->getJson('/api/v1/announcements')->assertOk();
        $this->assertSame(['Live one'], array_column($res->json('data'), 'title'));
        $res->assertJsonStructure(['data' => [['title', 'slug', 'excerpt', 'published_at', 'url']], 'meta', 'links']);
        $this->assertArrayNotHasKey('body_html', $res->json('data.0'));

        $this->getJson('/api/v1/notices')->assertJsonPath('data.0.title', 'A notice');
    }

    public function test_single_post_counts_views_and_hides_drafts(): void
    {
        $live = $this->makePost(['title' => 'Live one']);
        $draft = $this->makePost(['title' => 'Draft one', 'status' => 'draft']);

        $this->getJson("/api/v1/posts/{$live->slug}")->assertOk()->assertJsonPath('data.body_html', '<p>Body text</p>');
        $this->assertSame(1, $live->fresh()->views);
        $this->getJson("/api/v1/posts/{$draft->slug}")->assertNotFound();
        $this->get(route('portal.post', $draft->slug))->assertNotFound();
    }

    public function test_events_are_upcoming_in_date_order(): void
    {
        $this->makePost(['title' => 'Later', 'type' => 'event', 'event_starts_at' => now()->addDays(10), 'venue' => 'Hall']);
        $this->makePost(['title' => 'Sooner', 'type' => 'event', 'event_starts_at' => now()->addDays(2), 'venue' => 'Hall']);
        $this->makePost(['title' => 'Past', 'type' => 'event', 'event_starts_at' => now()->subDays(5), 'event_ends_at' => now()->subDays(5), 'venue' => 'Hall']);

        $this->assertSame(['Sooner', 'Later'], array_column($this->getJson('/api/v1/events')->json('data'), 'title'));
        $this->assertSame(['Past'], array_column($this->getJson('/api/v1/events?when=past')->json('data'), 'title'));
    }

    public function test_settings_and_ticker(): void
    {
        Setting::put('phone', '051-1234567', 'contact');
        Setting::put('principal_message_updated_at', now()->toDateTimeString(), 'principal');
        Setting::put('ticker_enabled', '1', 'banners');
        $this->makePost(['title' => 'In ticker', 'show_in_ticker' => true]);

        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.phone', '051-1234567')->assertJsonMissingPath('data.principal_message_updated_at');
        $this->getJson('/api/v1/ticker')->assertJsonPath('data.0.title', 'In ticker');

        Setting::put('ticker_enabled', '0', 'banners');
        $this->getJson('/api/v1/ticker')->assertJsonCount(0, 'data');
    }

    public function test_other_endpoints_respond(): void
    {
        foreach (['menu', 'slides', 'gallery', 'downloads', 'faculty', 'news', 'events', 'notices'] as $e) {
            $this->getJson("/api/v1/{$e}")->assertOk()->assertJsonStructure(['data']);
        }
        $this->getJson('/api/v1/pages/missing')->assertNotFound();
    }

    public function test_contact_form_validates_and_is_throttled(): void
    {
        $this->postJson('/api/v1/contact', ['name' => 'A'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
        $this->postJson('/api/v1/contact', ['name' => 'Bot', 'email' => 'b@example.com', 'subject' => 'Hello there', 'message' => 'Buy cheap things now please', 'website' => 'spam.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('website');

        $ok = ['name' => 'Rashid Mehmood', 'phone' => '0301-5550192', 'subject' => 'Eligibility', 'message' => 'Is my son eligible for BS Computer Science?'];
        $this->postJson('/api/v1/contact', $ok)->assertCreated();
        $this->assertSame(1, ContactMessage::count());

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/contact', $ok);
        }
        $this->postJson('/api/v1/contact', $ok)->assertStatus(429);
    }

    public function test_public_home_renders_cms_content(): void
    {
        $this->makePost(['title' => 'Admissions open: BS 2027', 'is_pinned' => true]);
        $this->makePost(['title' => 'Hidden draft', 'status' => 'draft']);
        $this->get('/')->assertOk()->assertSee('Admissions open: BS 2027')->assertDontSee('Hidden draft');
    }
}
