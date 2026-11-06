<x-layouts::app :title="__('Dashboard')">
    <flux:card>
        <flux:heading size="lg">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:text class="mt-2">{{ __('Your portfolio summary will appear here.') }}</flux:text>
    </flux:card>
</x-layouts::app>
