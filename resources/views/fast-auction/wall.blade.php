@extends('fast-auction.layout')

@section('title', 'Auction Wall · ' . $boot['auctionName'])
@section('screen', 'wall')

@section('switch')
    <x-auction.mode-switch :to="route('public.auction.live', $boot['auctionId'])"
                           label="Classic wall"
                           title="Switch to the classic public wall" />
@endsection
