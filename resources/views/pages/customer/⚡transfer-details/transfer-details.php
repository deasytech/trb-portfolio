<?php

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Transfer Details')] class extends Component {
    #[Locked]
    public Transfer $transfer;

    public function mount(Transfer $transfer): void
    {
        $this->authorize('view', $transfer);

        $this->transfer = $transfer->load('sourceAccount');
    }

    /**
     * @return array<int, array{label: string, at: \Illuminate\Support\Carbon|null}>
     */
    #[Computed]
    public function timeline(): array
    {
        $t = $this->transfer;

        $steps = [['label' => 'Submitted', 'at' => $t->submitted_at ?? $t->created_at]];

        if ($t->approved_at) {
            $steps[] = ['label' => 'Approved', 'at' => $t->approved_at];
        }
        if ($t->processed_at) {
            $steps[] = ['label' => 'Processing', 'at' => $t->processed_at];
        }
        if ($t->completed_at) {
            $steps[] = ['label' => 'Completed', 'at' => $t->completed_at];
        }
        if ($t->rejected_at) {
            $steps[] = ['label' => 'Rejected', 'at' => $t->rejected_at];
        }
        if (in_array($t->status, [TransferStatus::Cancelled, TransferStatus::Failed], true)) {
            $steps[] = ['label' => $t->status->label(), 'at' => $t->updated_at];
        }

        return $steps;
    }
}; 