<?php

namespace Database\Factories;

use App\Enums\Theme;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'theme' => Theme::System,
            'timezone' => 'UTC',
            'locale' => 'en',
            'notification_preferences' => ['email' => true, 'in_app' => true],
        ];
    }
}
