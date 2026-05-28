@extends('layouts.librenmsv1')

@section('content')
<x-device.page :device="$device">
    <div class="row">
        <div class="col-md-12">
            <h2>Custom Fields Plugin</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            @yield('content2')
        </div>
    </div>
</x-device.page>
@endsection