@php
    $initials = fn ($n) => collect(preg_split('/\s+/', preg_replace('/^(Dr|Mrs|Mr|Hafiz|Syed)\.?\s+/', '', $n)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $lvl = fn ($d) => str_starts_with((string) $d, 'Associate') ? 0 : (str_starts_with((string) $d, 'Assistant') ? 1 : 2);
@endphp
<x-site.layout title="Faculty" active="Faculty" mtitle="Faculty">
    @include('site.partials.hero', ['title' => 'Faculty', 'lede' => $all->count().' teachers in '.$departments->count().' departments — senior faculty first.', 'image' => 'images/site/faculty-students-2.jpg', 'alt' => 'Faculty of the college', 'pos' => '35%'])

    <section class="wrap sec">
        <div class="filters">
            <label class="sr-only" for="faculty-q" style="position:absolute;left:-9999px">Search by name or subject</label>
            <input id="faculty-q" type="search" placeholder="Search by name, subject or degree" autocomplete="off">
            <label for="faculty-dept" style="position:absolute;left:-9999px">Department</label>
            <select id="faculty-dept">
                <option value="">All departments</option>
                @foreach ($departments->keys() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
        </div>
        <p id="faculty-count" style="color:#555;margin:0 0 24px" aria-live="polite">Showing all {{ $all->count() }} teachers, senior faculty first in each department.</p>
        <p id="faculty-empty" class="empty" hidden>No teacher matches. Try a different name, or choose “All departments”.</p>

        @forelse ($departments as $dept => $people)
            <div class="dept" data-dept="{{ $dept }}">
                <h3>{{ $dept }} <span>{{ $people->count() }} {{ \Illuminate\Support\Str::plural('teacher', $people->count()) }}</span></h3>
                <div class="plist">
                    @foreach ($people as $f)
                        <div class="prow" data-person="{{ strtolower($f->name.' '.$f->designation.' '.$f->qualification.' '.$dept) }}">
                            @if ($f->photo_path)<img src="{{ Storage::disk('public')->url($f->photo_path) }}" alt="{{ $f->name }}" loading="lazy">@else<span class="initials">{{ $initials($f->name) }}</span>@endif
                            <span style="min-width:0"><b>{{ $f->name }}</b><span>{{ $f->qualification }}</span></span>
                            <em class="s{{ $lvl($f->designation) }}">{{ \Illuminate\Support\Str::before($f->designation, ' ·') }}</em>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="empty">The faculty directory is being updated. <a href="{{ route('site.contact') }}">Contact the office</a> to reach a department.</p>
        @endforelse
    </section>
</x-site.layout>
