@extends('nmscustomfields::includes.device')

@section('title', $device->displayName() . ' - Custom Fields')

@section('content2')

<div class="panel panel-default" id="custom-fields-panel">
    <div class="panel-heading">
        <h3 class="panel-title">Add custom field for {{ $device->displayName() }}</h3>
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
            <form action="{{ route('plugin.nmscustomfields.devicefield.store', $device) }}" method="POST" role="form" class="form-horizontal device-group-form col-md-10 col-md-offset-1 col-lg-8 col-lg-offset-2 col-sm-12">
                <legend>Field Details</legend>
                @csrf
                <div class="form-group">
                    <label for="name" class="control-label col-sm-3 col-md-2 text-nowrap">Custom Field</label>
                    <div class="col-sm-9 col-md-10">
                        <select class="form-control" id="custom_field_id" name="custom_field_id"></select>
                        <span class="help-block"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="desc" class="control-label col-sm-3 col-md-2 text-nowrap">Text</label>
                    <div class="col-sm-9 col-md-10">
                        <input type="text" class="form-control" id="value" name="value" value="">
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

@section('scripts')
<script>
    $(document).ready(function() {
        init_select2('#custom_field_id', 'customfield', {}, '', 'Select a field...', {
            ajax: {
                url: "{!! route('plugin.nmscustomfields.select.customfields', ['filter' => 'unassigned', 'device' => $device->device_id]) !!}",
            }
        });
    });
</script>
@endsection