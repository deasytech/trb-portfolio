<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;

return new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $country = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
        $this->country = Auth::user()->country ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $this->email = Str::lower(trim($this->email));

        $rules = $this->profileRules($user->id);
        $rules['email'] = [
            ...array_filter($rules['email'], fn ($rule): bool => ! $rule instanceof Unique),
            // Case-insensitive, so a mixed-case copy of another address is still a duplicate.
            fn (string $attribute, mixed $value, \Closure $fail) => User::query()->whereRaw('LOWER(email) = ?', [Str::lower($value)])->whereKeyNot($user->id)->exists()
                ? $fail(__('validation.unique', ['attribute' => $attribute]))
                : null,
        ];

        $validated = $this->validate($rules + [
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{6,32}$/'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
        ]);

        $user->fill(collect($validated)->only(['name', 'email'])->all());
        $user->phone = $validated['phone'] ?: null;
        $user->country = $validated['country'] ? strtoupper($validated['country']) : null;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('customer.dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
};
