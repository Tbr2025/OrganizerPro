@extends('fast-auction.layout')

@section('title', 'Auction Panel · ' . $boot['auctionName'])
@section('screen', 'panel')

@section('switch')
    <x-auction.mode-switch :to="route('admin.auction.organizer.panel', $boot['auctionId'])"
                           label="Classic panel"
                           title="Switch to the classic organizer panel" />
@endsection
