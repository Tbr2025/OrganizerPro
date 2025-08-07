@component('mail::message')
# Welcome to the Youselects IPL, {{ $player->name }}!

We are thrilled to have you on board. 🏏

Attached is your official welcome image. Save it and share your excitement!

@component('mail::button', ['url' => config('app.url')])
Visit Our Website
@endcomponent

Thanks,  
{{ config('app.name') }}
@endcomponent
