@php use App\Models\Download; @endphp
<x-layouts.app title="Downloads" area="studio">
    <x-ui.page-header eyebrow="Site content" title="Downloads" description="Prospectus, forms, date sheets, results and syllabi offered for download on the website.">
        <x-slot:actions><a href="{{ route('studio.downloads.create') }}" class="btn btn-primary"><x-icon name="upload" :size="16" /> Upload file</a></x-slot:actions>
    </x-ui.page-header>
    <div class="tabs mb-4">
        <a href="{{ route('studio.downloads.index') }}" class="tab" aria-selected="{{ $category ? 'false' : 'true' }}">All <span class="mono text-[10.5px] opacity-70">{{ $counts->sum() }}</span></a>
        @foreach (Download::CATEGORIES as $k => $l)
            <a href="{{ route('studio.downloads.index', ['category' => $k]) }}" class="tab" aria-selected="{{ $category === $k ? 'true' : 'false' }}">{{ $l }} <span class="mono text-[10.5px] opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <div class="panel overflow-hidden">
        @if ($downloads->isEmpty())
            <x-ui.empty icon="download" title="No files in this category" description="Upload the PDF students are asking for — it appears on the website immediately.">
                <a href="{{ route('studio.downloads.create') }}" class="btn btn-primary btn-sm">Upload a file</a>
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>File</th><th>Category</th><th>Status</th><th class="num">Size</th><th class="num">Downloads</th><th>Updated</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($downloads as $d)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-bad-soft text-[10px] font-bold uppercase text-bad">{{ pathinfo($d->file_name, PATHINFO_EXTENSION) }}</span>
                                        <div class="min-w-0"><div class="font-semibold text-ink">{{ $d->title }}</div><div class="mono truncate text-[11.5px] text-muted">{{ $d->file_name }}</div></div>
                                    </div>
                                </td>
                                <td><span class="badge badge-plain">{{ $d->categoryLabel() }}</span></td>
                                <td><x-ui.status :value="$d->is_published ? 'published' : 'draft'" :label="$d->is_published ? 'Public' : 'Hidden'" /></td>
                                <td class="num mono">{{ $d->sizeLabel() }}</td>
                                <td class="num mono">{{ number_format($d->download_count) }}</td>
                                <td class="whitespace-nowrap text-[12.5px]">{{ $d->updated_at->format('j M Y') }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ $d->url() }}" target="_blank" class="btn btn-ghost btn-sm btn-icon !w-8" title="Open"><x-icon name="eye" :size="14" /></a>
                                    <a href="{{ route('studio.downloads.edit', $d) }}" class="btn btn-ghost btn-sm">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
