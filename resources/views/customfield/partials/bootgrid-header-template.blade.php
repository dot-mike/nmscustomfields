<div id="@{{ctx.id}}" class="@{{css.header}} row">
    <div class="col-sm-12 col-md-8">
        <form method="post" role="form" id="device_filter" class="form">
            {!! csrf_field() !!}

            <div class="row mb-2">
                <div class="col-sm-5 form-group">
                    <label for="device_id">@lang('Device')</label>
                    <select class="form-control" id="device_id" name="device_id" data-placeholder="Select a Device"></select>
                </div>
                <div class="col-sm-5 form-group">
                    <label for="custom_field_id">Selected Field</label>
                    <select class="form-control" id="custom_field_id" name="custom_field_id" data-allow-clear="false">
                        <option value="{{ $customfield->id }}">{{ $customfield->name }}</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-9 form-group">
                    <label for="global_search">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="global_search" name="global_search" placeholder="Search hostname, sysName or field value">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('Search')</button>
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="actionBar">
            <div style="margin-bottom: 10px;">
                <button id="device-add-btn" class="btn btn-primary"><i class="fa fa-plus"></i> Add device</button>
                <button id="bulk-edit-btn" class="btn btn-primary" disabled><i class="fa fa-pencil"></i> Bulk Edit</button>
                <button id="bulk-delete-btn" class="btn btn-danger" disabled><i class="fa fa-trash"></i> Bulk Delete</button>
                <button id="export-csv-btn" class="btn btn-success"><i class="fa fa-download"></i> Export CSV</button>
            </div>
            <div class="@{{css.actions}}" style="clear: both;"></div>
        </div>
    </div>
</div>