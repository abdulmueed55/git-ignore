<x-site.layout :title="$page->title" active="" :mtitle="$page->title" :description="$page->meta_description ?? $page->summary">
    @include('site.partials.hero', ['title' => $page->title, 'lede' => $page->summary])
    <section class="wrap sec" style="max-width:880px">
        <div class="post-body">{!! $page->body !!}</div>
    </section>
</x-site.layout>
