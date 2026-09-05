@extends('fast-auction.layout')

@section('title', 'Bidding · ' . $boot['auctionName'])
@section('screen', 'team-bidding')

@section('switch')
    <x-auction.mode-switch :to="route('team.auction.bidding.show', $boot['auctionId'])"
                           label="Classic screen"
                           title="Switch to the classic bidding screen" />
@endsection
