@extends('nmscustomfields::includes.pluginadmin')

@section('title', 'Custom Fields Plugin')

@section('content2')

<div class="row">
    @if ($errors->any())
    <div class="text-red-500 text-sm mb-4">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    <div class="col-md-12">
        <h3>About Custom Fields Plugin</h3>
        <p>
            This plugin enables you to create and assign custom fields to devices.
        </p>
        <p>
            <strong>Version:</strong> {{ $nmscustomfields_version }}
        </p>
    </div>


</div>

@endsection