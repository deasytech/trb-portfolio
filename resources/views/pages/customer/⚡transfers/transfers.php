<?php

use App\Enums\AccountStatus;
use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Models\Account;
use App\Models\Transfer;
use App\Services\Money;
use App\Services\TransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

return new #[Title('Transfers')] class extends Component {
    use WithPagination;

    public string $sourceAccountId = '';
    public string $destinationKind = 'external';
    public string $destinationAccountId = '';
    public string $beneficiaryName = '';
    public string $destinationIdentifier = '';
    public string $outAmount = '';
    public string $outDescription = '';
    public bool $confirmingOut = false;
    public string $outKey = '';

    public string $depositAccountId = '';
    public string $senderName = '';
    public string $expectedAmount = '';
    public string $depositReference = '';
    public bool $confirmingIn = false;
    public string $inKey = '';

    public function mount(): void
    {
        $this->outKey = (string) Str::uuid();
        $this->inKey = (string) Str::uuid();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Account>
     */
    #[Computed]
    public function accounts()
    {
        return Account::query()->where('user_id', Auth::id())->where('status', AccountStatus::Active)->orderBy('id')->get();
    }

    public function reviewOut(): void
    {
        $this->validate([
            'sourceAccountId' => ['required'],
            'destinationKind' => ['required', 'in:external,internal'],
            'destinationAccountId' => ['exclude_unless:destinationKind,internal', 'required'],
            'beneficiaryName' => ['exclude_unless:destinationKind,external', 'required', 'string', 'max:120'],
            'destinationIdentifier' => ['exclude_unless:destinationKind,external', 'required', 'string', 'max:64'],
            'outAmount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'outDescription' => ['nullable', 'string', 'max:255'],
        ], ['outAmount.regex' => __('Enter a valid amount, such as 250.00.')]);

        $this->confirmingOut = true;
    }

    public function editOut(): void
    {
        $this->confirmingOut = false;
    }

    public function submitOut(TransferService $transfers): void
    {
        if (! $this->confirmingOut || $this->tooManySubmissions()) {
            return;
        }

        $internal = $this->destinationKind === 'internal';

        try {
            $transfer = $transfers->request(Auth::user(), $internal ? TransferType::Internal : TransferType::OutgoingRequest, [
                'source_account_id' => (int) $this->sourceAccountId,
                'destination_account_id' => $internal ? (int) $this->destinationAccountId : null,
                'beneficiary_name' => $internal ? null : $this->beneficiaryName,
                'destination_identifier' => $internal ? null : $this->destinationIdentifier,
                'amount_minor' => Money::toMinor($this->outAmount) ?? 0,
                'description' => $this->outDescription ?: null,
            ], $this->outKey);
        } catch (ValidationException $e) {
            $this->confirmingOut = false;
            throw $e;
        }

        $this->reset('sourceAccountId', 'destinationKind', 'destinationAccountId', 'beneficiaryName', 'destinationIdentifier', 'outAmount', 'outDescription', 'confirmingOut');
        $this->outKey = (string) Str::uuid();
        $this->announce($transfer);
    }

    public function reviewIn(): void
    {
        $this->validate([
            'depositAccountId' => ['required'],
            'senderName' => ['required', 'string', 'max:120'],
            'expectedAmount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'depositReference' => ['nullable', 'string', 'max:255'],
        ], ['expectedAmount.regex' => __('Enter a valid amount, such as 250.00.')]);

        $this->confirmingIn = true;
    }

    public function editIn(): void
    {
        $this->confirmingIn = false;
    }

    public function submitIn(TransferService $transfers): void
    {
        if (! $this->confirmingIn || $this->tooManySubmissions()) {
            return;
        }

        try {
            $transfer = $transfers->request(Auth::user(), TransferType::IncomingRequest, [
                'destination_account_id' => (int) $this->depositAccountId,
                'beneficiary_name' => $this->senderName,
                'amount_minor' => Money::toMinor($this->expectedAmount) ?? 0,
                'description' => $this->depositReference ?: null,
            ], $this->inKey);
        } catch (ValidationException $e) {
            $this->confirmingIn = false;
            throw $e;
        }

        $this->reset('depositAccountId', 'senderName', 'expectedAmount', 'depositReference', 'confirmingIn');
        $this->inKey = (string) Str::uuid();
        $this->announce($transfer);
    }

    private function tooManySubmissions(): bool
    {
        $key = 'transfer-submit:'.Auth::id();

        if (! RateLimiter::attempt($key, config('portfolio.transfers.submissions_per_minute'), fn () => true, 60)) {
            $this->addError('rate', __('Too many requests. Please wait a moment and try again.'));

            return true;
        }

        return false;
    }

    private function announce(Transfer $transfer): void
    {
        $this->resetPage();
        session()->flash('status', __('Request :reference submitted. Status: :status.', ['reference' => $transfer->reference, 'status' => __($transfer->status->label())]));
    }

    /**
     * Transfer counts per status group for the overview cards.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function overview(): array
    {
        $counts = Transfer::query()->where('customer_id', Auth::id())
            ->selectRaw('status, COUNT(*) AS aggregate_count')->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $count = fn (TransferStatus ...$statuses): int => (int) collect($statuses)->sum(fn ($s) => $counts[$s->value] ?? 0);

        return [
            'Pending' => $count(...TransferStatus::open()),
            'Completed' => $count(TransferStatus::Completed),
            'Rejected' => $count(TransferStatus::Rejected, TransferStatus::Failed),
            'Cancelled' => $count(TransferStatus::Cancelled),
        ];
    }

    #[Computed]
    public function transfers()
    {
        return Transfer::query()->where('customer_id', Auth::id())->latest('id')->paginate(10);
    }
}; 