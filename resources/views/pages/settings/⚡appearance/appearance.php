<?php

use App\Enums\Theme;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Settings')] class extends Component {
    public string $theme = 'system';
    public string $timezone = 'UTC';
    public bool $emailNotifications = true;
    public bool $inAppNotifications = true;

    public function mount(): void
    {
        $preference = Auth::user()->preference()->firstOrCreate()->refresh();

        $this->theme = $preference->theme->value;
        $this->timezone = $preference->timezone;
        $this->emailNotifications = $preference->notification_preferences['email'] ?? true;
        $this->inAppNotifications = $preference->notification_preferences['in_app'] ?? true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
            'timezone' => ['required', 'timezone:all'],
            'emailNotifications' => ['boolean'],
            'inAppNotifications' => ['boolean'],
        ]);

        Auth::user()->preference()->updateOrCreate([], [
            'theme' => $validated['theme'],
            'timezone' => $validated['timezone'],
            'notification_preferences' => [
                'email' => $validated['emailNotifications'],
                'in_app' => $validated['inAppNotifications'],
            ],
        ]);

        $this->dispatch('theme-saved', theme: $validated['theme']);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}; 