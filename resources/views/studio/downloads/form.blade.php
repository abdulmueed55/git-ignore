@php use App\Models\Download; @endphp
<x-layouts.app :title="$download->exists ? 'Edit download' : 'Upload file'" area="studio">
    <x-ui.page-header eyebrow="Downloads" :title="$download->exists ? 'Edit download' : 'Upload a file'" description="PDF is best for forms and date sheets; Word and Excel are accepted for templates." />
    <form method="POST" enctype="multipart/form-data" action="{{ $download->exists ? route('studio.downloads.update', $download) : route('studio.downloads.store') }}" class="panel max-w-2xl space-y-5 p-5 sm:p-6">
        @csrf @if ($download->exists) @method('PUT') @endif
        <x-ui.field name="title" label="Title" required :value="$download->title" placeholder="e.g. Date sheet — December class tests" />
        <x-ui.field name="category" label="Category" type="select" required :options="Download::CATEGORIES" :value="$download->category" />
        @if ($download->exists)
            <div class="flex items-center gap-3 rounded-lg border border-line bg-sunken/50 px-4 py-3 text-[13px]">
                <x-icon name="file" class="text-bad" /><a href="{{ $download->url() }}" target="_blank" class="mono text-ink hover:text-brand">{{ $download->file_name }}</a><span class="ml-auto text-muted">{{ $download->sizeLabel() }} · {{ number_format($download->download_count) }} downloads</span>
            </div>
        @endif
        <x-ui.field name="file" :label="$download->exists ? 'Replace file' : 'File'" type="file" :required="! $download->exists" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" hint="PDF, Word, Excel or image · up to 20 MB" class="[&_input]:!h-auto [&_input]:py-2" />
        <label class="flex items-center gap-2 text-[13px] text-ink-2"><input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $download->is_published))> Show on the website</label>
        <div class="flex gap-2 border-t border-line pt-4">
            <button class="btn btn-primary">{{ $download->exists ? 'Save' : 'Upload & publish' }}</button>
            <a href="{{ route('studio.downloads.index') }}" class="btn btn-ghost">Cancel</a>
            @if ($download->exists)<button form="del-dl" class="btn btn-danger ml-auto" data-confirm="Remove this file from the website?">Remove</button>@endif
        </div>
    </form>
    @if ($download->exists)<form id="del-dl" method="POST" action="{{ route('studio.downloads.destroy', $download) }}" class="hidden">@csrf @method('DELETE')</form>@endif
</x-layouts.app>
