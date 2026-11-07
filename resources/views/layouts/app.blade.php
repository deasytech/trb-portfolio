<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @php($maintenanceNotice = app(\App\Services\SettingsService::class)->get(\App\Services\SettingsService::MAINTENANCE_NOTICE))
        @if ($maintenanceNotice)
            <div role="status" data-test="maintenance-notice" class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                {{ $maintenanceNotice }}
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
