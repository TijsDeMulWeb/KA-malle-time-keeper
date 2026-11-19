@use('App\Models\Category')
@use('App\Models\SchoolYear')
@use('App\Models\User')
@use('App\Support\Impersonation')
@use('App\Support\StudentView')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen border-t-4 border-brand-lime bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('entries.index') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="clock" :href="route('entries.index')" :current="request()->routeIs('entries.*')" wire:navigate>
                    {{ __('Mijn uren') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="folder" :href="route('projects.index')" :current="request()->routeIs('projects.*')" wire:navigate>
                    {{ __('Projecten') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                    {{ __('Rapport') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            @if(auth()->user()->can('viewAny', User::class) || auth()->user()->can('viewAny', Category::class))
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Beheer')" class="grid">
                        @can('viewAny', SchoolYear::class)
                            <flux:sidebar.item icon="academic-cap" :href="route('admin.school-years.index')" :current="request()->routeIs('admin.school-years.*', 'admin.school-classes.*')" wire:navigate data-test="nav-admin-school-years">
                                {{ __('Schooljaren') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', User::class)
                            <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate data-test="nav-admin-users">
                                {{ __('Gebruikers') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('viewAny', Category::class)
                            <flux:sidebar.item icon="tag" :href="route('admin.categories.index')" :current="request()->routeIs('admin.categories.*')" wire:navigate data-test="nav-admin-categories">
                                {{ __('Categorieën') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @endif

            <flux:spacer />

            <flux:sidebar.nav>
                @if(Impersonation::isActive())
                    <form method="POST" action="{{ route('impersonation.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <flux:sidebar.item as="button" type="submit" icon="arrow-uturn-left" class="w-full cursor-pointer !bg-brand-lime !text-black" data-test="sidebar-stop-impersonating">
                            {{ __('Terug naar je account') }}
                        </flux:sidebar.item>
                    </form>
                @elseif(StudentView::isActive())
                    <form method="POST" action="{{ route('student-view.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <flux:sidebar.item as="button" type="submit" icon="arrow-uturn-left" class="w-full cursor-pointer !bg-brand-lime !text-black" data-test="stop-student-view">
                            {{ __('Studentweergave verlaten') }}
                        </flux:sidebar.item>
                    </form>
                @elseif(auth()->user()->can('viewAny', User::class))
                    <form method="POST" action="{{ route('student-view.store') }}">
                        @csrf
                        <flux:sidebar.item as="button" type="submit" icon="eye" class="w-full cursor-pointer" data-test="start-student-view">
                            {{ __('Bekijk als student') }}
                        </flux:sidebar.item>
                    </form>
                @endif
                <flux:modal.trigger name="shortcuts">
                    <flux:sidebar.item icon="command-line" data-shortcut="?" data-test="open-shortcuts">
                        {{ __('Sneltoetsen') }}
                    </flux:sidebar.item>
                </flux:modal.trigger>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
