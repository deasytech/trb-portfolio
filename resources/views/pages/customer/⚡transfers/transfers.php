<?php

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

return new #[Title('Transfers')] class extends Component {
    use WithPagination;

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
