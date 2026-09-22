@extends('sls.layouts.app')

@section('title', 'Crawler Page Error - 1G-SLS')
@section('eyebrow', 'Crawler Registry')
@section('page_title', $title ?? 'Crawler page could not load')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.organizations.index') }}#crawler-registry">Crawler registry</a>
    <a class="button secondary" href="{{ route('sls.organizations.crawlerResults') }}">Crawler results</a>
@endsection

@section('content')
    <section class="panel stack">
        <div>
            <p class="eyebrow">Crawler Diagnostics</p>
            <h2>{{ $title ?? 'Crawler page could not load' }}</h2>
            <p class="muted">{{ $message ?? 'The crawler dashboard could not be prepared.' }}</p>
        </div>

        @if (! empty($crawlerId))
            <p><strong>Crawler ID:</strong> {{ $crawlerId }}</p>
        @endif

        @if (! empty($exception))
            <div>
                <p class="eyebrow">Logged Error</p>
                <p><strong>{{ class_basename($exception) }}</strong></p>
                <p class="muted">{{ $exception->getMessage() }}</p>
            </div>
        @else
            <p class="muted">No exception was recorded for this state.</p>
        @endif

        <p class="muted">This error was also written to the Laravel log on the server.</p>
    </section>
@endsection
