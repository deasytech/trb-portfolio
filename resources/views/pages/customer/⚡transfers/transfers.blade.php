<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->overview as $label => $count)
            <flux:card wire:key="overview-{{ $label }}">
                <flux:subheading>{{ __($label) }}</flux:subheading>
                <div class="text-2xl font-semibold tabular-nums">{{ $count }}</div>
            </flux:card>
        @endforeach
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif
    @error('rate')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
    @enderror

    @if ($this->accounts->isEmpty())
        <flux:callout icon="information-circle" :heading="__('You have no active accounts available for transfers.')" />
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">{{ __('Transfer Out') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Requests are reviewed before funds are moved.') }}</flux:text>

                @if ($confirmingOut)
                    <div class="mt-4 space-y-3">
                        <flux:heading>{{ __('Confirm your transfer') }}</flux:heading>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-zinc-500">{{ __('From') }}</dt>
                            <dd>{{ $this->accounts->firstWhere('id', (int) $sourceAccountId)?->maskedNumber() }}</dd>
                            <dt class="text-zinc-500">{{ __('To') }}</dt>
                            <dd>{{ $destinationKind === 'internal' ? $this->accounts->firstWhere('id', (int) $destinationAccountId)?->maskedNumber() : $beneficiaryName }}</dd>
                            <dt class="text-zinc-500">{{ __('Amount') }}</dt>
                            <dd class="tabular-nums">{{ $this->accounts->firstWhere('id', (int) $sourceAccountId)?->currency }} {{ $outAmount }}</dd>
                        </dl>
                        @error('amount') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                        <div class="flex gap-2">
                            <flux:button variant="primary" wire:click="submitOut" wire:loading.attr="disabled" wire:target="submitOut">{{ __('Confirm and submit') }}</flux:button>
                            <flux:button variant="ghost" wire:click="editOut">{{ __('Edit') }}</flux:button>
                        </div>
                    </div>
                @else
                    <form wire:submit="reviewOut" class="mt-4 space-y-4">
                        <flux:select wire:model.live="sourceAccountId" :label="__('From account')" :placeholder="__('Select an account')">
                            @foreach ($this->accounts as $account)
                                <flux:select.option :value="$account->id">{{ __($account->account_type->label()) }} {{ $account->maskedNumber() }} ({{ $account->currency }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('account') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror

                        <flux:radio.group wire:model.live="destinationKind" :label="__('Send to')">
                            <flux:radio value="external" :label="__('External recipient')" />
                            <flux:radio value="internal" :label="__('My other account')" />
                        </flux:radio.group>

                        @if ($destinationKind === 'internal')
                            <flux:select wire:model="destinationAccountId" :label="__('To account')" :placeholder="__('Select an account')">
                                @foreach ($this->accounts->where('id', '!=', (int) $sourceAccountId) as $account)
                                    <flux:select.option :value="$account->id">{{ __($account->account_type->label()) }} {{ $account->maskedNumber() }} ({{ $account->currency }})</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('destination') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                        @else
                            <flux:input wire:model="beneficiaryName" :label="__('Recipient name')" />
                            <flux:input wire:model="destinationIdentifier" :label="__('Recipient account identifier')" autocomplete="off" />
                        @endif

                        <flux:input wire:model="outAmount" :label="__('Amount')" inputmode="decimal" placeholder="0.00" />
                        @error('amount') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                        <flux:input wire:model="outDescription" :label="__('Reference / description')" />

                        <flux:button type="submit" variant="primary">{{ __('Review transfer') }}</flux:button>
                    </form>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('Transfer In') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Tell us about a deposit you are sending. Your balance is only credited after we verify receipt.') }}</flux:text>

                @if ($confirmingIn)
                    <div class="mt-4 space-y-3">
                        <flux:heading>{{ __('Confirm deposit notification') }}</flux:heading>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-zinc-500">{{ __('Into') }}</dt>
                            <dd>{{ $this->accounts->firstWhere('id', (int) $depositAccountId)?->maskedNumber() }}</dd>
                            <dt class="text-zinc-500">{{ __('Sender') }}</dt>
                            <dd>{{ $senderName }}</dd>
                            <dt class="text-zinc-500">{{ __('Expected amount') }}</dt>
                            <dd class="tabular-nums">{{ $this->accounts->firstWhere('id', (int) $depositAccountId)?->currency }} {{ $expectedAmount }}</dd>
                        </dl>
                        <flux:text>{{ __('This will be pending verification until funds are confirmed.') }}</flux:text>
                        <div class="flex gap-2">
                            <flux:button variant="primary" wire:click="submitIn" wire:loading.attr="disabled" wire:target="submitIn">{{ __('Confirm and submit') }}</flux:button>
                            <flux:button variant="ghost" wire:click="editIn">{{ __('Edit') }}</flux:button>
                        </div>
                    </div>
                @else
                    <form wire:submit="reviewIn" class="mt-4 space-y-4">
                        <flux:select wire:model="depositAccountId" :label="__('Deposit into')" :placeholder="__('Select an account')">
                            @foreach ($this->accounts as $account)
                                <flux:select.option :value="$account->id">{{ __($account->account_type->label()) }} {{ $account->maskedNumber() }} ({{ $account->currency }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('account') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                        <flux:input wire:model="senderName" :label="__('Sender name')" />
                        <flux:input wire:model="expectedAmount" :label="__('Expected amount')" inputmode="decimal" placeholder="0.00" />
                        <flux:input wire:model="depositReference" :label="__('Reference')" />
                        <flux:button type="submit" variant="primary">{{ __('Review deposit') }}</flux:button>
                    </form>
                @endif
            </flux:card>
        </div>
    @endif

    <flux:card>
        <flux:heading size="lg">{{ __('Transfer Requests') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Transfers are reviewed before they are posted to your account.') }}</flux:text>

        @if ($this->transfers->isEmpty())
            <flux:text class="mt-6">{{ __('You have not made any transfer requests yet.') }}</flux:text>
        @else
            <flux:table :paginate="$this->transfers" class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->transfers as $transfer)
                        <flux:table.row wire:key="transfer-{{ $transfer->id }}">
                            <flux:table.cell>
                                <flux:link :href="route('customer.transfers.show', $transfer)" wire:navigate>{{ $transfer->reference }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $transfer->created_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>{{ __($transfer->type->label()) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Services\Money::format($transfer->amount_minor, $transfer->currency) }}</flux:table.cell>
                            <flux:table.cell><x-status-badge :status="$transfer->status" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
