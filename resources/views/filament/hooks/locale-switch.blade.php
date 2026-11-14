<nav class="fi-locale-switch" dir="ltr" aria-label="{{ __('trading.language') }}">
    <form method="POST" action="{{ route('locale.update', 'en') }}">
        @csrf
        <button type="submit" @class(['is-active' => app()->isLocale('en')])>EN</button>
    </form>
    <span aria-hidden="true">|</span>
    <form method="POST" action="{{ route('locale.update', 'ur') }}">
        @csrf
        <button type="submit" @class(['is-active' => app()->isLocale('ur')]) lang="ur">اردو</button>
    </form>
</nav>
