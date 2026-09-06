@extends('fast-auction.layout')

@section('title', 'Ticker · ' . $boot['auctionName'])
@section('screen', 'ticker')

@section('switch')
    <x-auction.mode-switch :to="route('public.auction.ticker', $boot['auctionId'])"
                           label="Classic ticker"
                           title="Switch to the classic ticker" />
@endsection
