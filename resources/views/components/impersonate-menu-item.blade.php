@props(['user'])

@can('impersonate', $user)
    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="eye" class="w-full cursor-pointer" data-test="impersonate">
            {{ $slot->isEmpty() ? __('Bekijk als deze gebruiker') : $slot }}
        </flux:menu.item>
    </form>
@endcan
