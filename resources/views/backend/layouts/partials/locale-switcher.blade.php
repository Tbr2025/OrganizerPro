@php
    $currentLocale = app()->getLocale();
    $lang = get_languages()[$currentLocale] ?? [
        'code' => strtoupper($currentLocale),
        'name' => strtoupper($currentLocale),
        'icon' => '/images/flags/default.svg',
    ];

    $buttonClass = $buttonClass ?? 'hover:text-dark-900 relative flex p-2 items-center justify-center rounded-full text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white';

    $iconClass = $iconClass ?? 'text-gray-700 transition-colors hover:text-gray-800 dark:text-gray-300 dark:hover:text-white';

    $iconSize = $iconSize ?? '24';
@endphp




