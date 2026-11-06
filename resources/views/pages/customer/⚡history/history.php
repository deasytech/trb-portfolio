<?php

use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

return new #[Title('History')] class extends Component {
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $type = '';
    #[Url] public string $accountId = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    #[Url] public string $minAmount = '';
    #[Url] public string $maxAmount = '';

    public ?int $selectedId = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_column(LedgerTransactionType::cases(), 'value'))],
            'accountId' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => $this->from !== '' ? ['nullable', 'date', 'after_or_equal:from'] : ['nullable', 'date'],
            'minAmount' => ['nullable', 'numeric', 'min:0'],
            'maxAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function rendering(): void
    {
        // Filters can arrive via the URL; validate before rendering so errors show with the controls.
        try {
            $this->validate();
        } catch (ValidationException $e) {
            // Keep the errors for display; transactions() returns an empty page.
            $this->setErrorBag($e->validator->errors());
        }
    }

    public function updated(string $property): void
    {
        if ($property !== 'selectedId') {
            $this->validateOnly($property);

            if ($property === 'from' && $this->to !== '') {
                $this->validateOnly('to');
            }

            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'accountId', 'from', 'to', 'minAmount', 'maxAmount');
        $this->resetValidation();
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = $this->baseQuery()->whereKey($id)->exists() ? $id : null;
    }

    #[Computed]
    public function accounts()
    {
        return Auth::user()->accounts()->orderBy('id')->get();
    }

    #[Computed]
    public function selected(): ?LedgerTransaction
    {
        return $this->selectedId ? $this->baseQuery()->with('account')->find($this->selectedId) : null;
    }

    #[Computed]
    public function transactions()
    {
        $query = $this->baseQuery()->with('account')->latest('posted_at')->latest('id');

        if ($this->getErrorBag()->isNotEmpty()) {
            return $query->whereRaw('1 = 0')->paginate(15);
        }

        return $query
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('reference', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%')))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->accountId !== '', fn ($q) => $q->where('account_id', (int) $this->accountId))
            ->when($this->from !== '', fn ($q) => $q->where('posted_at', '>=', \Illuminate\Support\Carbon::parse($this->from, Auth::user()->displayTimezone())->startOfDay()->utc()))
            ->when($this->to !== '', fn ($q) => $q->where('posted_at', '<=', \Illuminate\Support\Carbon::parse($this->to, Auth::user()->displayTimezone())->endOfDay()->utc()))
            ->when($this->minAmount !== '', fn ($q) => $q->where('amount_minor', '>=', (int) round($this->minAmount * 100)))
            ->when($this->maxAmount !== '', fn ($q) => $q->where('amount_minor', '<=', (int) round($this->maxAmount * 100)))
            ->paginate(15);
    }

    protected function baseQuery()
    {
        return LedgerTransaction::query()->whereIn('account_id', Auth::user()->accounts()->select('id'));
    }
};
