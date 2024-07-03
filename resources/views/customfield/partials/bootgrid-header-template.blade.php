<div id="@{{ctx.id}}" class="@{{css.header}} row ">
    <div class="col-sm-6">
        <form method="post" role="form" id="device_filter" class="form-inline">
            {!! csrf_field() !!}
            <div class="row form-group">
                <span>@lang('Device')</span>
                <select class="form-control" id="device_id" name="device_id" data-placeholder="Select a Device"></select>
                <span>Selected Field</span>
                <select class="form-control" id="custom_field_id" name="custom_field_id" data-allow-clear="false">
                    <option value="{{ $customfield->id }}">{{ $customfield->name }}</option>
                </select>
            </div>
            <div class="row form-group tw-mt-5">
                <span>@lang('Device')</span>
                <input type="text" class="form-control" id="hostname" name="hostname" placeholder="Search device">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('Search')</button>
            </div>
        </form>
    </div>
    <div class="col-sm-6">
        <div class="actionBar pull-right">
            <div class="btn-toolbar" role="toolbar">
                <button id="bulk-edit-btn" class="btn btn-primary" disabled><i class="fa fa-pencil"></i> Bulk Edit</button>
                <button id="bulk-delete-btn" class="btn btn-danger" disabled><i class="fa fa-trash"></i> Bulk Delete</button>
                <div class="@{{css.actions}}"></div>
            </div>
        </div>
    </div>
</div>