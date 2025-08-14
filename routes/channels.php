<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


// Public Channel for general auction status (anyone can listen)
Broadcast::channel('auction.public.{auctionId}', function ($user, $auctionId) {
    return true; // Anyone can view this channel
});

Broadcast::channel('auction.private.{auctionId}', function ($user, $auctionId) {
    return true; // Allow all for now
});
