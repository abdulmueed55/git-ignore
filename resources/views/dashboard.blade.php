<x-layouts.app title="Command centre">
    <x-ui.page-header eyebrow="Overview" title="Command centre" description="Placeholder — built after modules." />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Students" value="449" icon="users" delta="+12" hint="this week" />
        <div class="panel grid place-items-center p-5"><x-ui.ring :value="86.4" label="Present today" /></div>
        <div class="panel p-5"><x-ui.field name="x" label="Sample" required hint="hint" /><div class="mt-3 flex gap-2"><x-ui.status value="verified" /><x-ui.status value="flagged" /><x-ui.status value="mismatch" /></div></div>
    </div>
</x-layouts.app>
