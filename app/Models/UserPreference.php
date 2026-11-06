<?php

namespace App\Models;

use App\Enums\Theme;
use Database\Factories\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Theme $theme
 * @property string $timezone
 * @property string $locale
 * @property array<string, bool>|null $notification_preferences
 */
#[Fillable(['theme', 'timezone', 'locale', 'notification_preferences'])]
class UserPreference extends Model
{
    /** @use HasFactory<UserPreferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => Theme::class,
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
