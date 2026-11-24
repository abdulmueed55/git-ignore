<?php

namespace Tests\Feature\Portal;

use App\Models\ContactMessage;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_renders_with_the_approved_design(): void
    {
        foreach (['/', '/about', '/admission', '/programmes', '/faculty', '/campus-life', '/notices', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertSee('FG Liaquat Ali Degree College')->assertSee('Apply online');
        }

        $this->get('/')->assertSee('Announcements')->assertSee(config('site.achievers_year'));
        $this->get('/admission')->assertSee('HSSC Part-I');
    }

    public function test_only_live_posts_are_listed_and_opened(): void
    {
        $base = ['type' => 'notice', 'excerpt' => str_repeat('Summary ', 4), 'body' => '<p>Body</p>', 'published_at' => now()->subHour()];
        Post::create($base + ['title' => 'Live notice', 'status' => 'published']);
        $draft = Post::create($base + ['title' => 'Draft notice', 'status' => 'draft']);

        $this->get('/notices')->assertSee('Live notice')->assertDontSee('Draft notice');
        $this->get('/notices/'.$draft->slug)->assertNotFound();
    }

    public function test_contact_form_validates_and_blocks_bots(): void
    {
        $this->post('/contact', ['name' => 'A'])->assertSessionHasErrors(['name', 'subject', 'message', 'email']);

        $this->post('/contact', ['name' => 'Spam Bot', 'email' => 'x@example.com', 'subject' => 'Hello there', 'message' => 'Buy cheap things now please', 'website' => 'http://spam'])
            ->assertSessionHasErrors('website');
        $this->assertSame(0, ContactMessage::count());

        $this->post('/contact', ['name' => 'Ali Raza', 'phone' => '0300-1234567', 'subject' => 'Admission query', 'message' => 'When does the BS admission open this year?'])
            ->assertRedirect('/contact')->assertSessionHas('sent');
        $this->assertSame('Ali Raza', ContactMessage::first()->name);
    }

    public function test_user_content_is_not_rendered_as_html(): void
    {
        Post::create(['type' => 'notice', 'title' => '<script>alert(1)</script>Exam notice', 'excerpt' => str_repeat('Summary ', 4),
            'body' => '<p>ok</p><script>alert(2)</script>', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->get('/notices')->assertDontSee('<script>alert(1)</script>', false);
        $post = Post::first();
        $this->get('/notices/'.$post->slug)->assertOk()->assertDontSee('<script>alert(2)</script>', false);
    }
}
