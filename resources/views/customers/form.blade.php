@extends('layouts.app')

@section('title', $mode === 'create' ? 'New Customer' : 'Edit Customer')
@section('heading', $mode === 'create' ? 'New Customer' : 'Edit ' . $customer->customer_id)

@section('content')
    <form method="POST"
          action="{{ $mode === 'create' ? route('customers.store') : route('customers.update', $customer->customer_id) }}"
          class="max-w-3xl space-y-6">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        <section class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            <h2 class="font-semibold text-slate-800">Identity</h2>

            @include('customers.partials.text-field', [
                'name' => 'customer_id',
                'label' => 'Customer ID',
                'required' => true,
                'extra' => $mode === 'edit' ? 'readonly class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed"' : null,
            ])

            @include('customers.partials.text-field', ['name' => 'name', 'label' => 'Full name', 'required' => true])

            <div class="grid gap-5 sm:grid-cols-2">
                @include('customers.partials.text-field', [
                    'name' => 'age',
                    'label' => 'Age',
                    'type' => 'number',
                    'required' => true,
                    'extra' => 'min="18" max="100"',
                ])

                @include('customers.partials.select-field', [
                    'name' => 'gender',
                    'label' => 'Gender',
                    'options' => \App\Support\ChurnVocabulary::GENDERS,
                ])
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                @include('customers.partials.text-field', ['name' => 'phone', 'label' => 'Phone (optional)'])
                @include('customers.partials.text-field', ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email'])
            </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            <h2 class="font-semibold text-slate-800">Classification</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                @include('customers.partials.select-field', [
                    'name' => 'district',
                    'label' => 'District',
                    'options' => \App\Support\ChurnVocabulary::DISTRICTS,
                ])

                @include('customers.partials.select-field', [
                    'name' => 'customer_segment',
                    'label' => 'Segment',
                    'options' => \App\Support\ChurnVocabulary::CUSTOMER_SEGMENTS,
                ])
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                @include('customers.partials.select-field', [
                    'name' => 'customer_status',
                    'label' => 'Status',
                    'options' => \App\Support\ChurnVocabulary::CUSTOMER_STATUSES,
                ])

                @include('customers.partials.text-field', [
                    'name' => 'registration_date',
                    'label' => 'Registration date',
                    'type' => 'date',
                    'required' => true,
                ])
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ $mode === 'create' ? 'Create customer' : 'Save changes' }}
            </button>
            <a href="{{ $mode === 'create' ? route('customers.index') : route('customers.show', $customer->customer_id) }}"
               class="text-sm text-slate-500 hover:text-slate-800 transition">Cancel</a>
        </div>
    </form>
@endsection
