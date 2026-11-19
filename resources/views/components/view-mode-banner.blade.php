@use('App\Support\Impersonation')
@use('App\Support\StudentView')

@if(Impersonation::isActive())
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-blue px-4 py-3 text-sm text-white" data-test="impersonation-banner">
        <span class="flex items-center gap-2">
            <flux:icon.eye variant="mini" />
            <span>
                {{ __('Je bekijkt de app als :name.', ['name' => auth()->user()->name]) }}
                {{ __('Wat je hier wijzigt, wijzig je echt voor deze student.') }}
            </span>
        </span>

        <form method="POST" action="{{ route('impersonation.destroy') }}">
            @csrf
            @method('DELETE')
            <flux:button type="submit" size="sm" variant="primary" data-test="stop-impersonating">
                {{ __('Terug naar :name', ['name' => Impersonation::impersonator()?->name ?? __('admin')]) }}
            </flux:button>
        </form>
    </div>
@endif

@if(StudentView::isActive())
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-blue px-4 py-3 text-sm text-white" data-test="student-view-banner">
        <span class="flex items-center gap-2">
            <flux:icon.eye variant="mini" />
            <span><strong>{{ __('Studentweergave') }}</strong> · {{ __('Zo ziet de app eruit voor een student. Beheerfuncties zijn verborgen.') }}</span>
        </span>

        <form method="POST" action="{{ route('student-view.destroy') }}">
            @csrf
            @method('DELETE')
            <flux:button type="submit" size="sm" variant="primary">{{ __('Studentweergave verlaten') }}</flux:button>
        </form>
    </div>
@endif
