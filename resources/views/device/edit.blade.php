@extends('nmscustomfields::includes.device')

@section('title', 'Custom Fields Plugin')

@section('content2')

<div class="panel panel-default" id="manage-customfields">
    <div class="panel-heading">
        <h3 class="panel-title">Edit Custom Field</h3>
    </div>

    <div class="panel-body">
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
            <form action="{{ route('plugin.nmscustomfields.devicefield.update', ['device' => $device, 'customdevicefield' => $customdevicefield]) }}" method="POST" role="form" class="form-horizontal device-group-form col-md-10 col-md-offset-1 col-lg-8 col-lg-offset-2 col-sm-12">
                <legend>Field Details</legend>
                @csrf
                @method('PUT')
                <div class="form-group ">
                    <label for="name" class="control-label col-sm-3 col-md-2 text-nowrap">Name</label>
                    <div class="col-sm-9 col-md-10">
                        <input type="text" class="form-control" name="name" id="name" disabled="disabled" value="{{ $customdevicefield->customField->name }}">
                        <span class="help-block"></span>
                    </div>
                </div>

                <div class="form-group ">
                    <label for="name" class="control-label col-sm-3 col-md-2 text-nowrap">Value</label>
                    <div class="col-sm-9 col-md-10">
                        <input type="text" class="form-control" name="value" id="value" value="{{ $customdevicefield->customFieldValue->value ?? old('value') }}" required="required" autofocus="autofocus">
                        <span class="help-block"></span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-9 col-sm-offset-3 col-md-10 col-sm-offset-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a type="button" class="btn btn-danger" href="{{ route('plugin.nmscustomfields.devicefield.index', $device) }}">Cancel</a>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

@endsection