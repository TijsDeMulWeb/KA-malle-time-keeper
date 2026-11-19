<x-layouts::auth :title="__('Account activeren')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welkom, :name', ['name' => $user->name])" :description="__('Kies een wachtwoord om je account te activeren.')" />

        <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-6" data-test="accept-invitation-form">
            @csrf
            @method('PUT')

            <flux:input :value="$user->email" :label="__('Email')" type="email" readonly />

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autofocus
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
                {{ __('Account activeren') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
