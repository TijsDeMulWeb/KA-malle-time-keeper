<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rapport')] class extends Component {
}; ?>

<div>
    <flux:heading size="xl" level="1">{{ __('Rapport') }}</flux:heading>
</div>
