<?php

use Illuminate\Support\Facades\Blade;

it('renders optional head and script stacks through the shared layout', function () {
    $this->withoutVite();

    $rendered = Blade::render(<<<'BLADE'
@extends('layouts.app')

@push('head')
    <meta name="stack-head-marker" content="present">
@endpush

@section('body')
    <main>Stacked body</main>
@endsection

@push('scripts')
    <script>window.__stackMarker = true;</script>
@endpush
BLADE);

    expect($rendered)
        ->toContain('stack-head-marker')
        ->toContain('window.__stackMarker = true;')
        ->toContain('Stacked body');
});

it('does not require head or script stacks for the shared layout to render', function () {
    $this->withoutVite();

    $rendered = Blade::render(<<<'BLADE'
@extends('layouts.app')

@section('body')
    <main>Plain body</main>
@endsection
BLADE);

    expect($rendered)
        ->toContain('Plain body')
        ->not->toContain('stack-head-marker')
        ->not->toContain('window.__stackMarker = true;');
});
