@php use App\Models\Setting; @endphp
<x-layouts.app title="Site settings" area="studio">
    <x-ui.page-header eyebrow="Settings" title="Site settings" description="College details used across the public website and returned by the website API.">
        <x-slot:actions><button form="settings-form" class="btn btn-primary">Save settings</button></x-slot:actions>
    </x-ui.page-header>
    <form id="settings-form" method="POST" action="{{ route('studio.settings.update') }}" class="grid gap-6 lg:grid-cols-[200px_minmax(0,1fr)]">
        @csrf @method('PUT')
        <nav class="hidden lg:block">
            <ul class="sticky top-[84px] space-y-0.5 text-[13px]">
                @foreach ($groups as $g => [$title])<li><a href="#{{ $g }}" class="block rounded-md px-3 py-1.5 text-ink-2 hover:bg-sunken hover:text-ink">{{ $title }}</a></li>@endforeach
            </ul>
        </nav>
        <div class="space-y-6">
            @foreach ($groups as $g => [$title, $desc])
                <section id="{{ $g }}" class="panel scroll-mt-24">
                    <div class="panel-head"><div><div class="panel-title">{{ $title }}</div><div class="text-[12px] text-muted">{{ $desc }}</div></div></div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        @foreach (Setting::FIELDS as $key => [$group, $label, $type])
                            @continue($group !== $g || $type === 'hidden')
                            @php $optional = in_array($key, ['map_url', 'facebook_url', 'youtube_url', 'instagram_url', 'x_url', 'admission_banner_text'], true); @endphp
                            @if ($type === 'toggle')
                                <label class="flex items-center justify-between gap-3 rounded-lg border border-line px-4 py-3 text-[13px] text-ink-2 sm:col-span-1">
                                    {{ $label }}
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <input type="checkbox" name="{{ $key }}" value="1" class="checkbox" @checked(old($key, $values[$key]))>
                                </label>
                            @elseif ($type === 'color')
                                <div>
                                    <label class="field-label">{{ $label }} <span class="req">*</span></label>
                                    <div class="flex items-center gap-2" x-data="{ c: @js(old($key, $values[$key] ?? '#0f5a3a')) }">
                                        <input type="color" x-model="c" class="h-[38px] w-12 cursor-pointer rounded-md border border-line-strong bg-surface p-1">
                                        <input name="{{ $key }}" x-model="c" class="input mono" maxlength="7" @error($key) aria-invalid="true" @enderror>
                                    </div>
                                    @error($key)<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                            @else
                                <x-ui.field :name="$key" :label="$label" :type="$type" :value="$values[$key]" :required="! $optional"
                                    class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}" :rows="$key === 'principal_message' ? 9 : 3"
                                    :hint="$key === 'principal_message' && $values['principal_message_updated_at'] ? 'Last changed '.\Illuminate\Support\Carbon::parse($values['principal_message_updated_at'])->diffForHumans() : ($key === 'admission_banner_text' ? 'Required while the banner is shown' : ($optional ? 'Optional' : null))" />
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
            <section class="panel p-5 text-[12.5px] text-muted">
                <div class="mb-1 font-semibold text-ink">For the website developer</div>
                These values are served at <a href="{{ route('api.settings') }}" target="_blank" class="mono text-brand">/api/v1/settings</a>.
                Other endpoints: <span class="mono">/api/v1/announcements · news · events · notices · ticker · slides · gallery · downloads · faculty · menu · pages/{slug}</span>.
            </section>
            <div class="flex justify-end"><button class="btn btn-primary">Save settings</button></div>
        </div>
    </form>
</x-layouts.app>
