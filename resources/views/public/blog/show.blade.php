@extends('public.blog.layout')

@section('title', $post->title . ' | ' . config('app.name'))
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 155))
@section('og_type', 'article')
@if($post->featured_image)
    @section('og_image', \Illuminate\Support\Facades\Storage::url($post->featured_image))
@endif

@section('content')
    <a href="{{ route('public.blog.index') }}" class="text-sm text-gray-500 hover:text-white transition">&larr; All posts</a>

    <h1 class="text-3xl md:text-4xl font-extrabold text-white mt-4 leading-tight">{{ $post->title }}</h1>
    <p class="text-xs text-gray-500 mt-2">
        {{ optional($post->published_at ?? $post->created_at)->format('j F Y') }}
    </p>

    @if($post->featured_image)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($post->featured_image) }}" alt="{{ $post->title }}"
             class="w-full rounded-xl mt-6 border border-white/10">
    @endif

    {{-- The body is HTML written by the model and stripped to a small tag set on the way in
         (BlogGenerationService::sanitiseHtml), which is why it can be echoed unescaped here. --}}
    <div class="article mt-8">
        {!! $post->content !!}
    </div>
@endsection
