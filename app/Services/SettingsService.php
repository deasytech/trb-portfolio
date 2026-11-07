<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads and changes the small set of approved system settings.
 *
 * Stored values override config defaults. Financial limits need a stronger
 * permission than other settings, and every change is audited with a reason.
 */
class SettingsService
{
    public const DISPLAY_NAME = 'app_display_name';

    public const SUPPORT_EMAIL = 'support_email';

    public const SUPPORT_PHONE = 'support_phone';

    public const DEFAULT_CURRENCY = 'default_currency';

    public const TRANSFER_MIN = 'transfer_min_minor';

    public const TRANSFER_MAX = 'transfer_max_minor';

    public const TRANSFERS_ENABLED = 'transfers_enabled';

    public const MAINTENANCE_NOTICE = 'maintenance_notice';

    /**
     * The largest amount a customer can type into the transfer form, in minor units.
     */
    public const MAX_ENTERABLE_MINOR = 999_999_999_999;

    /**
     * Keys that change financial behaviour.
     *
     * @var list<string>
     */
    public const FINANCIAL_KEYS = [self::TRANSFER_MIN, self::TRANSFER_MAX, self::TRANSFERS_ENABLED];

    /**
     * Create the service.
     */
    public function __construct(private AuditLogger $audit) {}

    /**
     * Effective value of one setting: the stored value, else the config default.
     */
    public function get(string $key): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        return $setting instanceof Setting ? $setting->value : $this->default($key);
    }

    /**
     * Whether customers may currently submit transfer requests.
     */
    public function transfersEnabled(): bool
    {
        return (bool) $this->get(self::TRANSFERS_ENABLED);
    }

    /**
     * The effective minimum and maximum, read together in one query so they always belong to the same configuration.
     *
     * @return array{min: int, max: int}
     */
    public function transferLimits(): array
    {
        $stored = Setting::query()->whereIn('key', [self::TRANSFER_MIN, self::TRANSFER_MAX])->pluck('value', 'key');

        return [
            'min' => (int) ($stored[self::TRANSFER_MIN] ?? $this->default(self::TRANSFER_MIN)),
            'max' => (int) ($stored[self::TRANSFER_MAX] ?? $this->default(self::TRANSFER_MAX)),
        ];
    }

    /**
     * The effective minimum transfer amount in minor units.
     */
    public function transferMinMinor(): int
    {
        return $this->transferLimits()['min'];
    }

    /**
     * The effective maximum transfer amount in minor units.
     */
    public function transferMaxMinor(): int
    {
        return $this->transferLimits()['max'];
    }

    /**
     * Every approved setting with its effective value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Setting::query()->pluck('value', 'key');
        $values = [];

        foreach ($this->keys() as $key) {
            $values[$key] = $stored->has($key) ? $stored[$key] : $this->default($key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string> The keys that actually changed.
     */
    public function update(array $values, User $actor, string $reason): array
    {
        $unknown = array_diff(array_keys($values), $this->keys());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['settings' => 'Unknown setting: '.implode(', ', $unknown)]);
        }

        $this->authorize($actor, PermissionName::ManageSettings);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        $values = $this->normalise($values);

        // Serialises concurrent updates, including when no rows exist yet to lock.
        return Cache::lock('settings:update', 10)->block(5, fn (): array => $this->apply($values, $actor, $reason));
    }

    /**
     * Apply validated values inside one transaction while the update lock is held.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function apply(array $values, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($values, $actor, $reason): array {
            $current = $this->all();
            $changed = array_keys(array_filter($values, fn (mixed $value, string $key): bool => $value !== $current[$key], ARRAY_FILTER_USE_BOTH));

            if (array_intersect($changed, self::FINANCIAL_KEYS) !== []) {
                $this->authorize($actor, PermissionName::ManageFinancialLimits);
            }

            if (array_intersect($changed, [self::TRANSFER_MIN, self::TRANSFER_MAX]) !== []) {
                $merged = array_merge($current, $values);

                if ($merged[self::TRANSFER_MIN] >= $merged[self::TRANSFER_MAX]) {
                    throw ValidationException::withMessages([self::TRANSFER_MIN => 'The minimum must be lower than the maximum.']);
                }
            }

            foreach ($changed as $key) {
                $setting = Setting::query()->updateOrCreate(['key' => $key], ['value' => $values[$key]]);

                $this->audit->record('settings.updated', $setting, $actor, $reason, [
                    'key' => $key,
                    'from' => $current[$key],
                    'to' => $values[$key],
                ]);
            }

            return $changed;
        });
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return [
            self::DISPLAY_NAME,
            self::SUPPORT_EMAIL,
            self::SUPPORT_PHONE,
            self::DEFAULT_CURRENCY,
            self::TRANSFER_MIN,
            self::TRANSFER_MAX,
            self::TRANSFERS_ENABLED,
            self::MAINTENANCE_NOTICE,
        ];
    }

    /**
     * Config-backed default for a setting key.
     */
    private function default(string $key): mixed
    {
        return match ($key) {
            self::DISPLAY_NAME => config('app.name'),
            self::SUPPORT_EMAIL, self::SUPPORT_PHONE, self::MAINTENANCE_NOTICE => null,
            self::DEFAULT_CURRENCY => 'USD',
            self::TRANSFER_MIN => (int) config('portfolio.transfers.min_minor'),
            self::TRANSFER_MAX => (int) config('portfolio.transfers.max_minor'),
            self::TRANSFERS_ENABLED => true,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalise(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match ($key) {
                self::TRANSFER_MIN => $this->positiveInt($key, $value, self::MAX_ENTERABLE_MINOR),
                self::TRANSFER_MAX => $this->positiveInt($key, $value),
                self::TRANSFERS_ENABLED => (bool) $value,
                self::DEFAULT_CURRENCY => $this->currency($value),
                self::SUPPORT_EMAIL => $this->email($value),
                default => ($value === null || trim((string) $value) === '') ? null : mb_substr(trim((string) $value), 0, 500),
            };
        }

        return $values;
    }

    /**
     * Validate a whole number of at least one, optionally capped.
     */
    private function positiveInt(string $key, mixed $value, ?int $ceiling = null): int
    {
        if (! is_numeric($value) || (int) $value < 1 || (int) $value != $value) {
            throw ValidationException::withMessages([$key => 'Enter a whole number greater than zero.']);
        }

        if ($ceiling !== null && (int) $value > $ceiling) {
            throw ValidationException::withMessages([$key => 'Enter an amount no higher than '.number_format($ceiling).' minor units.']);
        }

        return (int) $value;
    }

    /**
     * Validate and normalise a three-letter currency code.
     */
    private function currency(mixed $value): string
    {
        $code = strtoupper(trim((string) $value));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw ValidationException::withMessages([self::DEFAULT_CURRENCY => 'Enter a three-letter currency code.']);
        }

        return $code;
    }

    /**
     * Validate an optional email address, returning null when blank.
     */
    private function email(mixed $value): ?string
    {
        $email = trim((string) $value);
        if ($email === '') {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([self::SUPPORT_EMAIL => 'Enter a valid email address.']);
        }

        return mb_substr($email, 0, 255);
    }

    /**
     * Require an active actor holding the given permission.
     */
    private function authorize(User $actor, PermissionName $permission): void
    {
        if (! $actor->isActive() || ! $actor->can($permission->value)) {
            throw new AuthorizationException('You are not permitted to perform this action.');
        }
    }
}
