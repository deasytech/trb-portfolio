<x-filament-panels::page>
    {{ $this->form }}

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Current transfer limits: {{ $this->limitsSummary() }}
    </p>
</x-filament-panels::page>
