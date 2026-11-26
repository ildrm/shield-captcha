@extends('site.layout')
@section('title', __('site.downloads').' · '.config('app.name'))
@section('content')
<section class="frame text-page">
    <h1>{{ __('site.downloads') }}</h1>
    <p class="lede">{{ __('site.downloads_intro') }}</p>
    <div class="categories">
        @foreach (['all', 'sdk', 'cms', 'examples', 'self-hosted'] as $category)
            <a class="button outline" href="{{ route('downloads', $category === 'all' ? [] : ['category' => $category]) }}">{{ __('site.category_'.$category) }}</a>
        @endforeach
    </div>
    @forelse ($artifacts as $artifact)
        <article class="artifact">
            <h2><a href="{{ route('download', $artifact['id']) }}"><bdi>{{ $artifact['filename'] }}</bdi></a></h2>
            <p>{{ __('site.release_meta', ['version' => $artifact['version'], 'status' => __('site.release_'.$artifact['status']), 'bytes' => number_format($artifact['size_bytes'])]) }}</p>
            <code dir="ltr" class="digest">SHA-256 {{ $artifact['sha256'] }}</code>
        </article>
    @empty
        <p>{{ __('site.empty_release') }}</p>
    @endforelse
</section>
@endsection
