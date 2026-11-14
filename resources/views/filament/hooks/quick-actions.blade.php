<div class="fi-quick-actions" role="navigation" aria-label="{{ __('trading.quick.pos_title') }}">
    <a href="{{ url('/admin') }}" class="fi-quick-action fi-quick-action--primary" title="{{ __('trading.quick.pos_title') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-7 4h8m-9 4h10M6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v10.5A2.25 2.25 0 0 1 17.25 19.5H6.75A2.25 2.25 0 0 1 4.5 17.25V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
        </svg>
        <span>{{ __('trading.quick.pos') }}</span>
    </a>

    <a href="{{ url('/admin') }}" class="fi-quick-action" title="{{ __('trading.quick.sale_title') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M4.5 6.75l1.2-2.4A1.5 1.5 0 0 1 7.05 3.75h9.9a1.5 1.5 0 0 1 1.35.85l1.2 2.4M6 8.25V18a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 18V8.25" />
        </svg>
        <span>{{ __('trading.quick.sale') }}</span>
    </a>

    <a href="{{ url('/admin') }}" class="fi-quick-action" title="{{ __('trading.quick.purchase_title') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.55-5.1 1.241-7.875A18.75 18.75 0 0 0 5.106 5.272M7.5 14.25 5.106 5.272m0 0L4.106 5.272" />
        </svg>
        <span>{{ __('trading.quick.purchase') }}</span>
    </a>
</div>
