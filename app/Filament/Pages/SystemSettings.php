<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\Money;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use LogicException;
use UnitEnum;

/**
 * Approved operational settings only. Secrets stay in the environment.
 */
class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'System settings';

    protected static ?string $title = 'System settings';

    protected string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Only staff with the settings permission may open the page.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isActive() && $user->can(PermissionName::ManageSettings->value);
    }

    /**
     * Load the current effective settings into the form.
     */
    public function mount(): void
    {
        $this->fillForm();
    }

    /**
     * Define the settings form; financial fields are disabled without the elevated permission.
     */
    public function form(Schema $schema): Schema
    {
        $canEditLimits = $this->canEditFinancial();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('General')->schema([
                    TextInput::make(SettingsService::DISPLAY_NAME)->label('Application display name')->required()->maxLength(100),
                    TextInput::make(SettingsService::SUPPORT_EMAIL)->label('Support email')->email()->maxLength(255),
                    TextInput::make(SettingsService::SUPPORT_PHONE)->label('Support phone')->maxLength(50),
                    TextInput::make(SettingsService::DEFAULT_CURRENCY)->label('Default display currency')->required()->length(3),
                    Textarea::make(SettingsService::MAINTENANCE_NOTICE)->label('Maintenance notice')->maxLength(500),
                ]),
                Section::make('Transfers')
                    ->description($canEditLimits ? 'Amounts are in minor units (cents). Changes require a super administrator.' : 'Only a super administrator can change these.')
                    ->schema([
                        Toggle::make(SettingsService::TRANSFERS_ENABLED)->label('Customer transfer requests enabled')->disabled(! $canEditLimits),
                        TextInput::make(SettingsService::TRANSFER_MIN)->label('Minimum amount (minor units)')->numeric()->required()->disabled(! $canEditLimits),
                        TextInput::make(SettingsService::TRANSFER_MAX)->label('Maximum amount (minor units)')->numeric()->required()->disabled(! $canEditLimits),
                    ]),
            ]);
    }

    /**
     * The save action, which asks for an audit reason.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->action(function (array $data): void {
                    $values = $this->formSchema()->getState();

                    if (! $this->canEditFinancial()) {
                        $values = array_diff_key($values, array_flip(SettingsService::FINANCIAL_KEYS));
                    }

                    /** @var User $actor */
                    $actor = auth()->user();
                    $changed = app(SettingsService::class)->update($values, $actor, $data['reason']);

                    $this->fillForm();

                    Notification::make()
                        ->title($changed === [] ? 'No changes to save' : 'Settings saved')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * A readable summary of the effective limits, shown under the form.
     */
    public function limitsSummary(): string
    {
        $settings = app(SettingsService::class);
        $currency = (string) $settings->get(SettingsService::DEFAULT_CURRENCY);

        return Money::format($settings->transferMinMinor(), $currency).' to '.Money::format($settings->transferMaxMinor(), $currency);
    }

    /**
     * Reload the form from the stored settings.
     */
    private function fillForm(): void
    {
        $this->formSchema()->fill(app(SettingsService::class)->all());
    }

    /**
     * The page's form schema.
     */
    private function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('The settings form is not available.');
    }

    /**
     * Whether the current user may change transfer limits.
     */
    private function canEditFinancial(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(PermissionName::ManageFinancialLimits->value);
    }
}
