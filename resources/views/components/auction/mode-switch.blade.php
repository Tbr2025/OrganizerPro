@props(['to', 'label', 'title' => null])

{{--
    Jump between the classic screen and its Fast (Vue) counterpart.

    Deliberately self-contained with inline styles rather than utility classes: this same control
    renders inside the Fast shell, the public wall and the Blade admin panel, and those three do
    not load the same stylesheet. A switch that only looks right on one of them is worse than no
    switch at all.

    Fixed bottom-right and faint until hovered, because one of the pages it appears on is a
    projection at a venue — a permanent opaque button burned into the LED wall is not wanted, but
    an operator who knows it is there can still find it.
--}}
<a href="{{ $to }}"
   title="{{ $title ?? $label }}"
   style="position:fixed; right:12px; bottom:12px; z-index:2147483000;
          display:inline-flex; align-items:center; gap:6px;
          padding:6px 11px; border-radius:9999px;
          font:600 11px/1 ui-sans-serif,system-ui,sans-serif; letter-spacing:.02em;
          color:#e2e8f0; background:rgba(15,23,42,.72); border:1px solid rgba(148,163,184,.35);
          text-decoration:none; backdrop-filter:blur(6px); opacity:.35;
          transition:opacity .15s ease, background .15s ease;"
   onmouseover="this.style.opacity='1';this.style.background='rgba(15,23,42,.95)'"
   onmouseout="this.style.opacity='.35';this.style.background='rgba(15,23,42,.72)'">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17 2l4 4-4 4"/><path d="M3 6h18"/><path d="M7 22l-4-4 4-4"/><path d="M21 18H3"/>
    </svg>
    {{ $label }}
</a>
