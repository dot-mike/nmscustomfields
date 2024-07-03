@extends('layouts.librenmsv1')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1> Custom Fields Plugin </h1>
        </div>
        <div class="col-md-12">
            @include('nmscustomfields::includes.navigation')
        </div>
        <div class="col-md-12">
            @foreach(['success', 'warning', 'info', 'danger'] as $alert)
            @if (Session::has($alert))
            <div class="alert alert-{{$alert}}" role="alert">{{Session::get($alert)}}</div>
            @endif
            @endforeach
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{!! $error !!}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            @yield('content2')
        </div>
    </div>
</div>
@endsection