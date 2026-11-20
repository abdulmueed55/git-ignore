<?php

namespace Tests\Feature\Studio;

use App\Http\Controllers\Studio\Concerns\HtmlSanitizer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioPostsTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'announcement',
            'title' => 'College closed on Monday',
            'excerpt' => 'The college will remain closed on Monday due to heavy rain in Rawalpindi.',
            'body' => "Classes will resume on Tuesday.\n\nThe admissions office remains open.",
            'category' => 'Administration',
            'publish_mode' => 'now',
        ], $over);
    }

    public function test_editor_lands_in_studio_after_login_and_cannot_open_erp(): void
    {
        $editor = $this->editor();

        $this->post('/login', ['email' => $editor->email, 'password' => 'password'])
            ->assertRedirect(route('studio.dashboard'));
        $this->get(route('studio.dashboard'))->assertOk()->assertSee('What visitors see today');
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_teacher_cannot_use_studio(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get(route('studio.dashboard'))->assertForbidden();
        $this->actingAs($teacher)->post(route('studio.posts.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, Post::count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('studio.posts.index'))->assertRedirect(route('login'));
    }

    public function test_publish_now_is_live_with_slug(): void
    {
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload())->assertRedirect();

        $post = Post::sole();
        $this->assertSame('published', $post->status);
        $this->assertSame('college-closed-on-monday', $post->slug);
        $this->assertSame('live', $post->liveState());
        $this->assertStringContainsString('<p>Classes will resume on Tuesday.</p>', $post->body);
        $this->assertSame(1, Post::live()->count());
    }

    public function test_scheduled_post_is_hidden_until_its_time(): void
    {
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload([
            'publish_mode' => 'schedule',
            'published_at' => now()->addDays(2)->format('Y-m-d H:i'),
        ]))->assertRedirect();

        $post = Post::sole();
        $this->assertSame('scheduled', $post->status);
        $this->assertSame('scheduled', $post->liveState());
        $this->assertSame(0, Post::live()->count());

        $this->travel(3)->days();
        $this->assertSame(1, Post::live()->count());
    }

    public function test_schedule_requires_a_time_and_expiry_must_follow_publish(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('studio.posts.store'), $this->payload(['publish_mode' => 'schedule']))
            ->assertSessionHasErrors('published_at');
        $this->actingAs($editor)->post(route('studio.posts.store'), $this->payload(['expires_at' => now()->subDay()->format('Y-m-d H:i')]))
            ->assertSessionHasErrors('expires_at');
        $this->assertSame(0, Post::count());
    }

    public function test_expired_post_leaves_the_site(): void
    {
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload([
            'expires_at' => now()->addHour()->format('Y-m-d H:i'),
        ]));
        $this->assertSame(1, Post::live()->count());
        $this->travel(2)->hours();
        $this->assertSame(0, Post::live()->count());
        $this->assertSame('expired', Post::sole()->liveState());
    }

    public function test_required_fields_and_event_details(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('studio.posts.store'), ['type' => 'announcement'])
            ->assertSessionHasErrors(['title', 'excerpt', 'body', 'category', 'publish_mode']);
        $this->actingAs($editor)->post(route('studio.posts.store'), $this->payload(['type' => 'event']))
            ->assertSessionHasErrors(['event_starts_at', 'venue']);

        $this->actingAs($editor)->post(route('studio.posts.store'), $this->payload([
            'type' => 'event', 'event_starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'venue' => 'College Auditorium',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('College Auditorium', Post::sole()->venue);
    }

    public function test_body_html_is_sanitised(): void
    {
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload([
            'body' => '<p onclick="steal()" style="color:red">Fee deadline is <strong>10 December</strong>.</p><script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://example.com">good</a><iframe src="https://evil"></iframe>',
        ]))->assertSessionHasNoErrors();

        $body = Post::sole()->body;
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringContainsString('<strong>10 December</strong>', $body);
        $this->assertStringContainsString('href="https://example.com"', $body);
    }

    public function test_body_that_is_only_unsafe_content_is_rejected(): void
    {
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload([
            'body' => '<script>alert("this is a long script body that should vanish")</script>',
        ]))->assertSessionHasErrors('body');
    }

    public function test_sanitizer_converts_plain_text_and_escapes_it(): void
    {
        $this->assertSame("<p>a &lt; b</p>\n<p>line</p>", HtmlSanitizer::clean("a < b\n\nline"));
    }

    public function test_cover_and_pdf_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs($this->editor())->post(route('studio.posts.store'), $this->payload([
            'type' => 'notice',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 675),
            'attachment' => UploadedFile::fake()->create('date-sheet.pdf', 120, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $post = Post::sole();
        Storage::disk('public')->assertExists([$post->cover_path, $post->attachment_path]);
        $this->assertSame('date-sheet.pdf', $post->attachment_name);
    }

    public function test_update_duplicate_pin_bulk_and_delete(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('studio.posts.store'), $this->payload());
        $post = Post::sole();

        $this->put(route('studio.posts.update', $post), $this->payload(['title' => 'College closed on Monday and Tuesday', 'publish_mode' => 'draft']))
            ->assertRedirect();
        $this->assertSame('draft', $post->fresh()->status);

        $this->patch(route('studio.posts.pin', $post))->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);

        $this->post(route('studio.posts.duplicate', $post))->assertRedirect();
        $this->assertSame(2, Post::count());
        $copy = Post::latest('id')->first();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($post->slug, $copy->slug);

        $this->post(route('studio.posts.bulk'), ['ids' => [$post->id, $copy->id], 'action' => 'publish'])->assertRedirect();
        $this->assertSame(2, Post::live()->count());

        $this->delete(route('studio.posts.destroy', $copy))->assertRedirect();
        $this->assertSame(1, Post::count());
    }

    public function test_index_filters_by_type_and_state(): void
    {
        $editor = $this->editor();
        Post::create(['type' => 'notice', 'title' => 'Winter vacations notification', 'excerpt' => str_repeat('x', 30), 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Post::create(['type' => 'news', 'title' => 'Sports gala results', 'excerpt' => str_repeat('x', 30), 'body' => '<p>x</p>', 'status' => 'draft']);

        $this->actingAs($editor)->get(route('studio.posts.index', ['type' => 'notice']))
            ->assertOk()->assertSee('Winter vacations notification')->assertDontSee('Sports gala results');
        $this->get(route('studio.posts.index', ['state' => 'draft']))
            ->assertOk()->assertSee('Sports gala results')->assertDontSee('Winter vacations notification');
    }
}
