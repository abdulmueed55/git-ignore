<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index(); // announcement, news, event, notice
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 400);
            $table->longText('body');
            $table->string('category', 48)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->dateTime('event_starts_at')->nullable();
            $table->dateTime('event_ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('show_in_ticker')->default(false);
            $table->string('status', 12)->default('draft'); // draft, scheduled, published, archived
            $table->dateTime('published_at')->nullable()->index();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 400)->nullable();
            $table->longText('body');
            $table->string('meta_description', 300)->nullable();
            $table->boolean('in_menu')->default(true);
            $table->unsignedSmallInteger('menu_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('heading');
            $table->string('subheading')->nullable();
            $table->string('image_path');
            $table->string('cta_label', 48)->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description', 400)->nullable();
            $table->date('taken_on')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 48); // prospectus, forms, datesheets, results, syllabus
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('size')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('faculty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('designation');
            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->default('general');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 48); // e.g. application.verified
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'contact_messages', 'settings', 'faculty_profiles', 'downloads', 'gallery_images', 'gallery_albums', 'hero_slides', 'pages', 'posts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
