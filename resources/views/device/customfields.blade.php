@extends('nmscustomfields::includes.device')

@section('title', $device->displayName() . ' - Custom Fields')

@section('content2')
<div class="panel panel-default" id="custom-fields-panel">
    <div class="panel-heading">
        <h3 class="panel-title">Custom Fields for device {{ $device->displayName() }}</h3>
    </div>

    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>Info:</strong> Custom fields are used to store additional information about a device. You can add, edit or delete custom fields for this device.

                </div>
                <a type="button" class="btn btn-primary" href="{{ route('plugin.nmscustomfields.devicefield.create', $device) }}">
                    <i class="fa fa-plus"></i> Add field to device
                </a>
            </div>
        </div>
        <div class="col">
            <div class="table-responsive">
                <table id="manage-custom-fields-table" class="table table-striped table-hover table-responsive table-condensed">
                    <thead>
                        <tr>
                            <th data-column-id="custom_field_value_id" data-identifier="true" data-type="numeric" data-visible="false">Field ID</th>
                            <th data-column-id="custom_field_name">Field Name</th>
                            <th data-column-id="custom_field_value">Value</th>
                            <th data-column-id="commands" data-formatter="commands" data-sortable="false">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Table data will be loaded by bootgrid using JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    var device_id = "{!! $device->device_id !!}";
    var fieldEditUrl = "{!! route('plugin.nmscustomfields.devicefield.edit', ['device' => ':device', 'customdevicefield' => ':customfield']) !!}";
    var fieldDeleteUrl = "{!! route('plugin.nmscustomfields.devicefield.destroy', ['device' => ':device', 'customdevicefield' => ':customfield']) !!}";
    $(function() {
        var selected_fields = [];
        var grid = $("#manage-custom-fields-table");
        grid.bootgrid({
            ajax: true,
            rowCount: [25, 50, 100, 250, -1],
            columnSelection: true,
            selection: true,
            multiSelect: true,
            rowSelect: true,
            keepSelection: true,
            sorting: true,
            navigation: 3,

            formatters: {
                "commands": function(column, row) {
                    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.custom_field_value_id + "\"><span class=\"glyphicon glyphicon-edit\"></span></button> " +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.custom_field_value_id + "\"><span class=\"glyphicon glyphicon-trash\"></span></button>";
                }
            },

            requestHandler: function(request) {
                request.device_id = device_id;
                return request;
            },

            url: '{{ route("plugin.nmscustomfields.table.customfields") }}',
        }).on("loaded.rs.jquery.bootgrid", function(e) {
            // Set the selected_field_name in the noResults template
            // if row count is 0
            console.log("loaded.rs.jquery.bootgrid");
            console.log(e);

            grid.find(".command-edit").on("click", function(e) {
                var row_index = $(this).closest('tr').index();
                var row = grid.bootgrid("getCurrentRows")[row_index];
                var url = fieldEditUrl.replace(':device', device_id).replace(':customfield', row.custom_field_value_id);
                window.location.href = url;
            })

            grid.find(".command-delete").on("click", function(e) {
                var row_index = $(this).closest('tr').index();
                var row = grid.bootgrid("getCurrentRows")[row_index];
                var url = fieldDeleteUrl.replace(':device', device_id).replace(':customfield', row.custom_field_value_id);

                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(result) {
                        grid.bootgrid("reload");
                    }
                });
            });

        });
    });
</script>
@endsection