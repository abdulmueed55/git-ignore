<?php

namespace Tests\Feature\Studio;

use App\Models\ContactMessage;
use App\Models\Department;
use App\Models\Download;
use App\Models\FacultyProfile;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioContentTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($this->editor);
    }

    private function settings(array $over = []): array
    {
        return array_merge([
            'college_name' => 'F.G. Liaquat Ali Degree College', 'college_tagline' => 'Rawalpindi Cantt', 'established_year' => '1978',
            'address' => 'Rawalpindi Cantt', 'phone' => '051-0000000', 'phone_admissions' => '051-0000001', 'email' => 'info@example.edu.pk',
            'office_hours' => 'Mon–Fri 8–2', 'principal_name' => 'The Principal', 'principal_title' => 'Principal',
            'principal_message' => str_repeat('Welcome to the college. ', 6), 'admission_banner_enabled' => '1',
            'admission_banner_text' => 'Admissions open', 'ticker_enabled' => '1', 'brand_primary' => '#0f5a3a', 'brand_accent' => '#a8761f',
        ], $over);
    }

    public function test_settings_update_and_validation(): void
    {
        $this->get(route('studio.settings'))->assertOk()->assertSee('Site settings');

        $this->put(route('studio.settings.update'), $this->settings(['admission_banner_text' => '']))
            ->assertSessionHasErrors('admission_banner_text');
        $this->put(route('studio.settings.update'), $this->settings(['brand_primary' => 'green', 'email' => 'nope']))
            ->assertSessionHasErrors(['brand_primary', 'email']);

        $this->put(route('studio.settings.update'), $this->settings(['phone' => '051-9270614']))->assertSessionHasNoErrors();
        $this->assertSame('051-9270614', Setting::get('phone'));
        $this->assertNotNull(Setting::get('principal_message_updated_at'));
        $this->assertTrue(Setting::publicValues()['admission_banner_enabled']);
        $this->assertArrayNotHasKey('principal_message_updated_at', Setting::publicValues());
    }

    public function test_pages_crud_and_unique_slug(): void
    {
        $data = ['title' => 'Admission policy', 'summary' => 'How merit is calculated for every programme.', 'body' => 'Merit is based on marks and quotas.', 'meta_description' => 'Admission policy of the college, merit and quotas explained.', 'menu_order' => 1, 'in_menu' => 1, 'is_published' => 1];
        $this->post(route('studio.pages.store'), $data)->assertRedirect();
        $this->assertSame('admission-policy', Page::sole()->slug);
        $this->post(route('studio.pages.store'), $data)->assertSessionHasErrors('slug');
        $this->get(route('studio.pages.index'))->assertOk()->assertSee('Admission policy');
        $this->get(route('portal.page', 'admission-policy'))->assertOk()->assertSee('Merit is based on marks');
    }

    public function test_slides_require_a_wide_image(): void
    {
        $base = ['heading' => 'Admissions open now', 'subheading' => 'Apply online in minutes.', 'sort_order' => 1, 'is_active' => 1];
        $this->post(route('studio.slides.store'), $base + ['image' => UploadedFile::fake()->image('s.jpg', 400, 300)])->assertSessionHasErrors('image');
        $this->post(route('studio.slides.store'), $base + ['image' => UploadedFile::fake()->image('s.jpg', 1600, 640)])->assertSessionHasNoErrors();
        $this->assertSame(1, HeroSlide::count());
    }

    public function test_gallery_album_with_images(): void
    {
        $this->post(route('studio.gallery.store'), ['title' => 'Sports Gala', 'description' => 'Three days of athletics and cricket.', 'taken_on' => now()->subDay()->toDateString(), 'is_published' => 1])
            ->assertSessionHasErrors('images');
        $this->post(route('studio.gallery.store'), [
            'title' => 'Sports Gala', 'description' => 'Three days of athletics and cricket.', 'taken_on' => now()->subDay()->toDateString(), 'is_published' => 1,
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHasNoErrors();
        $album = GalleryAlbum::sole();
        $this->assertSame(2, $album->images()->count());
        $this->delete(route('studio.gallery.images.destroy', $album->images()->first()))->assertRedirect();
        $this->assertSame(1, $album->images()->count());
    }

    public function test_downloads_and_faculty_import(): void
    {
        $this->post(route('studio.downloads.store'), ['title' => 'Prospectus', 'category' => 'prospectus', 'is_published' => 1, 'file' => UploadedFile::fake()->create('p.pdf', 50, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Download::count());

        $dept = Department::create(['name' => 'Physics', 'code' => 'PHY']);
        User::factory()->count(2)->create(['role' => 'teacher', 'department_id' => $dept->id, 'designation' => 'Lecturer']);
        $this->post(route('studio.faculty.import'))->assertRedirect();
        $this->assertSame(2, FacultyProfile::count());
        $this->assertSame(0, FacultyProfile::where('is_published', true)->count());
        $this->post(route('studio.faculty.import'));
        $this->assertSame(2, FacultyProfile::count(), 'import is idempotent');
    }

    public function test_inbox_marks_messages_read(): void
    {
        $m = ContactMessage::create(['name' => 'Rashid', 'email' => 'r@example.com', 'subject' => 'Eligibility', 'message' => 'Is my son eligible for BS?']);
        $this->get(route('studio.messages.index', ['filter' => 'unread']))->assertOk()->assertSee('Eligibility');
        $this->get(route('studio.messages.show', $m))->assertOk();
        $this->assertNotNull($m->fresh()->read_at);
        $this->patch(route('studio.messages.unread', $m));
        $this->assertNull($m->fresh()->read_at);
    }

    public function test_every_studio_screen_renders(): void
    {
        foreach (['studio.dashboard', 'studio.posts.index', 'studio.posts.create', 'studio.pages.index', 'studio.pages.create', 'studio.slides.index', 'studio.slides.create', 'studio.gallery.index', 'studio.gallery.create', 'studio.downloads.index', 'studio.downloads.create', 'studio.faculty.index', 'studio.faculty.create', 'studio.messages.index', 'studio.settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
