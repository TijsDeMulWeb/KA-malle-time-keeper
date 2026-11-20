<div
    x-data="{ heading: '', message: '', confirmLabel: '', onConfirm: null }"
    x-on:confirm-action.window="
        heading = $event.detail.heading ?? @js(__('Ben je zeker?'));
        message = $event.detail.message ?? '';
        confirmLabel = $event.detail.confirmLabel ?? @js(__('Verwijderen'));
        onConfirm = $event.detail.confirm;
        $flux.modal('confirm-action').show();
    "
>
    <flux:modal name="confirm-action" class="w-full max-w-sm">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="heading"></flux:heading>
                <flux:text x-text="message"></flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    x-on:click="onConfirm?.(); $flux.modal('confirm-action').close()"
                    data-test="confirm-action"
                >
                    <span x-text="confirmLabel"></span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
