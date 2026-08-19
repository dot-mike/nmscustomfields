<div id="@{{ctx.id}}" class="@{{css.header}} row">
    <div class="col-sm-12 col-md-7">
        <form method="post" role="form" id="device_filter" class="form device-customfields-table-headers-table-menu">
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
                        <input type="text" class="form-control" id="global_search" name="searchPhrase" placeholder="Search hostname, sysName or field value" title="Whole words only. Use * as a wildcard, for example amp* or pdu*timeout.">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('Search')</button>
                        </span>
                    </div>
                    <span class="help-block">
                        Whole words only. <code>ampere</code> does not match <code>deciAmpere</code>.
                        Use <code>*</code> as a wildcard: <code>amp*</code>, <code>*ampere*</code>, <code>pdu*timeout</code>.
                    </span>
                </div>
            </div>
        </form>
    </div>

    <div class="col-sm-12 col-md-5">
        <div class="actionBar" style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center;">
            <button id="device-add-btn" class="btn btn-primary"><i class="fa fa-plus"></i> Add device</button>
            <button id="bulk-edit-btn" class="btn btn-primary" disabled><i class="fa fa-pencil"></i> Bulk Edit</button>
            <button id="bulk-delete-btn" class="btn btn-danger" disabled><i class="fa fa-trash"></i> Bulk Delete</button>
            <div class="@{{css.actions}}" style="flex-basis: 100%; display: flex; justify-content: flex-end; gap: 8px;"></div>
        </div>
    </div>
</div>