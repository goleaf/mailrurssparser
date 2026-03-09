@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => $page['title']],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 space-y-4">
                <h1 class="text-4xl font-black">{{ $page['title'] }}</h1>
                <p class="max-w-3xl text-base leading-8 text-base-content/75">
                    {{ $page['subtitle'] }}
                </p>
            </div>
        </div>

        <div class="mt-8 space-y-6">
            @foreach($page['sections'] as $section)
                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>{{ $section['title'] }}</x-slot:title>
                    <div class="text-base leading-8 text-base-content/80">
                        {{ $section['body'] }}
                    </div>
                </x-mary-card>
            @endforeach
        </div>
    </div>
@endsection
