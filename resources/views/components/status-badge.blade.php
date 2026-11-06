@props(['status'])

<flux:badge :color="$status->color()" size="sm" inset="top bottom">{{ __($status->label()) }}</flux:badge>
