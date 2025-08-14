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

// Private Channel for authenticated users (Organizer, Team Managers)
Broadcast::channel('auction.private.{auctionId}', function ($user, $auctionId) {
    // Add logic to check if user is part of the auction, e.g.,
    // is organizer, or a member of a team in this auction's tournament.
    // For now, simple check if authenticated:
    return $user !== null;
});

Broadcast::channel('auction.{auctionId}', function ($user, $auctionId) {
    // Optional: return true if user is allowed
    return true;
});
