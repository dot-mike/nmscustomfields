@extends('nmscustomfields::includes.pluginadmin')

@section('title', 'Custom Fields Plugin')

@section('content2')

<div class="panel panel-default" id="manage-customfields">
    <div class="panel-heading">
        <h3 class="panel-title">Create Custom Field</h3>
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
            <form action="{{ route('plugin.nmscustomfields.customfield.store') }}" method="POST" role="form" class="form-horizontal device-group-form col-md-10 col-md-offset-1 col-lg-8 col-lg-offset-2 col-sm-12">
                <legend>Field Details</legend>
                @csrf
                <div class="form-group">
                    <label for="name" class="control-label col-sm-3 col-md-2 text-nowrap">Name</label>
                    <div class="col-sm-9 col-md-10">
                        <input type="text" class="form-control" id="name" name="name" value="">
                        <span class="help-block"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="desc" class="control-label col-sm-3 col-md-2 text-nowrap">Type</label>
                    <div class="col-sm-9 col-md-10">
                        <select class="form-control" id="type" name="type">
                            <option value="text">Text</option>
                            <option value="integer">Number</option>
                        </select>
                        <span class="help-block"></span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-9 col-sm-offset-3 col-md-10 col-sm-offset-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a type="button" class="btn btn-danger" href="{{ route('plugin.nmscustomfields.customfield.index') }}">Cancel</a>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

@endsection