<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The single page where the studio owner edits everything about the site that
 * isn't a booking, a package or a photograph.
 *
 * It is a singleton — there is no list, no create, no delete. It reads and
 * writes the `settings` key/value table through App\Support\Settings, which
 * caches the whole table in one query and busts that cache on save.
 *
 * Only super_admin and admin can reach it; staff cannot (see canAccess()).
 */
class ManageSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site settings';

    protected static string|UnitEnum|null $navigationGroup = 'Studio';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Site settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Staff can work bookings but must not change pricing, terms or business details. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function mount(): void
    {
        // Prefill from saved values, falling back to the documented defaults
        // so a fresh install shows a usable form rather than empty fields.
        $this->form->fill(
            collect(Settings::DEFAULTS)
                ->mapWithKeys(fn ($default, string $key) => [
                    self::toFormKey($key) => Settings::get($key),
                ])
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()->tabs([

                    Tabs\Tab::make('Homepage')
                        ->icon(Heroicon::OutlinedHome)
                        ->schema([
                            Section::make('Hero')->schema([
                                Textarea::make('hero__headline')
                                    ->label('Headline')
                                    ->rows(3)
                                    ->helperText('The large statement over the first image.'),
                                TextInput::make('hero__location')
                                    ->label('Location line')
                                    ->helperText('Small text in the bottom corner of the hero.'),
                            ]),

                            Section::make('Studio statement')
                                ->description('The first sentence prints in solid black; the rest in grey.')
                                ->schema([
                                    TextInput::make('statement__eyebrow')->label('Small label'),
                                    Textarea::make('statement__lead')->label('First sentence (black)')->rows(2),
                                    Textarea::make('statement__rest')->label('Remainder (grey)')->rows(3),
                                ]),
                        ]),

                    Tabs\Tab::make('Statistics')
                        ->icon(Heroicon::OutlinedChartBar)
                        ->schema([
                            Section::make('The four numbers')
                                ->description('These count up as visitors scroll past.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('stats__years')->label('Number')->numeric(),
                                    TextInput::make('stats__years_label')->label('Label'),
                                    TextInput::make('stats__weddings')->label('Number')->numeric(),
                                    TextInput::make('stats__weddings_label')->label('Label'),
                                    TextInput::make('stats__awards')->label('Number')->numeric(),
                                    TextInput::make('stats__awards_label')->label('Label'),
                                    TextInput::make('stats__couples')->label('Number')->numeric(),
                                    TextInput::make('stats__couples_label')->label('Label'),
                                ]),
                        ]),

                    Tabs\Tab::make('Contact')
                        ->icon(Heroicon::OutlinedPhone)
                        ->schema([
                            Section::make('How couples reach you')->columns(2)->schema([
                                TextInput::make('contact__email')->label('Email')->email(),
                                TextInput::make('contact__phone')->label('Phone')->tel(),
                                TextInput::make('contact__whatsapp')
                                    ->label('WhatsApp number')
                                    ->helperText('Digits only, with country code — e.g. 60123456789.'),
                                Textarea::make('contact__address')->label('Studio address')->rows(3),
                            ]),

                            Section::make('Social')->columns(2)->schema([
                                TextInput::make('social__instagram')->label('Instagram URL')->url(),
                                TextInput::make('social__tiktok')->label('TikTok URL')->url(),
                            ]),
                        ]),

                    Tabs\Tab::make('Booking terms')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->schema([
                            Section::make()->schema([
                                TextInput::make('booking__deposit_percent')
                                    ->label('Deposit percentage')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->helperText('Used to work out the deposit on quotes and invoices.'),
                                Textarea::make('booking__payment_terms')->label('Payment terms')->rows(3),
                                Textarea::make('booking__cancellation_policy')->label('Cancellation policy')->rows(3),
                            ]),
                        ]),

                    Tabs\Tab::make('Invoicing')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            Section::make('Business details printed on invoices')
                                ->description('These appear on every invoice PDF. Enter your real registered details before sending an invoice to a client.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('invoice__registered_name')->label('Registered name'),
                                    TextInput::make('invoice__ssm_number')->label('SSM number'),
                                    Textarea::make('invoice__address')->label('Address')->rows(3)->columnSpanFull(),
                                    TextInput::make('invoice__bank_name')->label('Bank'),
                                    TextInput::make('invoice__bank_account_name')->label('Account name'),
                                    TextInput::make('invoice__bank_account_number')->label('Account number'),
                                ]),
                        ]),

                    Tabs\Tab::make('Search engines')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->schema([
                            Section::make()->schema([
                                TextInput::make('seo__title')
                                    ->label('Default page title')
                                    ->maxLength(60)
                                    ->helperText('Around 60 characters works best in Google results.'),
                                Textarea::make('seo__description')
                                    ->label('Default description')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->helperText('Around 160 characters.'),
                            ]),
                        ]),
                ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Group each setting so the admin form can be rebuilt from the table
        // later, and so related values stay together.
        $grouped = [];
        foreach ($state as $formKey => $value) {
            $key = self::toSettingKey($formKey);
            $grouped[explode('.', $key)[0]][$key] = $value;
        }

        foreach ($grouped as $group => $values) {
            Settings::setMany($values, group: $group);
        }

        // Public pages cache their payload; busting it here means a save is
        // visible on the site immediately rather than after the TTL expires.
        cache()->forget('home.payload');

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Your changes are live on the website.')
            ->send();
    }

    /** @return array<int, Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    /**
     * Setting keys contain dots ("hero.headline") but Livewire treats a dot as
     * nesting, so form fields use a double underscore instead. These two
     * helpers are the only place that translation happens.
     */
    private static function toFormKey(string $settingKey): string
    {
        return str_replace('.', '__', $settingKey);
    }

    private static function toSettingKey(string $formKey): string
    {
        return str_replace('__', '.', $formKey);
    }
}
