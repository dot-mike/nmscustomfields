@extends('nmscustomfields::includes.pluginadmin')

@section('title', 'Custom Fields Plugin')

@section('content2')

<div class="panel panel-default" id="manage-device-customfields">
    <div class="panel-heading">
        <h3 class="panel-title">Devices and Their Custom Fields</h3>
    </div>
    <div class="panel-body">
        <div class="alert alert-info">
            <strong>Info:</strong> Custom fields are used to store additional information about a device. You can bulk edit custom fields for multiple devices at once.
        </div>
        <div class="col">
            <div class="table-responsive">
                <table id="device-customfields-table" class="table table-condensed table-hover table-striped">
                    <thead>
                        <tr>
                            <th data-column-id="device_id" data-identifier="true" data-type="numeric" data-visible="false">Device ID</th>
                            <th data-column-id="hostname" data-formatter="link">hostname</th>
                            <th data-column-id="sysName" data-formatter="link">sysName</th>
                            <th data-column-id="custom_field_value">Field Value</th>
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

<!-- Bulk Edit Modal -->
<div class="modal fade" id="bulkEditModal" tabindex="-1" role="dialog" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkEditModalLabel">Bulk Edit Devices</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bulk-edit-form" action="{{ route('plugin.nmscustomfields.devicefield.bulkedit') }}">
                    <div class="form-group">
                        <label for="bulk-edit-select-devices">Apply to devices</label>
                        <select id="bulk-edit-select-devices" name="device_ids[]" class="form-control" multiple="multiple"></select>
                        <span class="help-block">Select devices to apply the custom field value to.<br>
                            <strong>Note:</strong> If you remove a device from the list, the custom field value will also be removed from that device.</span>
                        </span>
                    </div>
                    <div id="alert-container" class="alert alert-warning hidden" role="alert"></div>
                    <div class="form-group">
                        <label for="custom-field-value">Custom Field Value</label>
                        <input type="text" class="form-control" id="blkeddit-custom-field-value" name="custom_field_value" placeholder="Enter value">
                    </div>
                    <input type="hidden" id="blkedit_custom_field_id" name=" custom_field_id" value="{{ $customfield->id }}">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="save-bulk-edit-btn">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" role="dialog" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeviceModalLabel">Add custom field to device</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-device-form" action="{{ route('plugin.nmscustomfields.devicefield.bulkedit') }}">
                    <div class="form-group">
                        <label for="add-device-select-devices">Apply to devices</label>
                        <select id="add-device-select-devices" name="device_ids[]" class="form-control" multiple="multiple"></select>
                        <span class="help-block">Select devices to apply the custom field value to.</span>
                    </div>
                    <div id="alert-container" class="alert alert-warning hidden" role="alert"></div>
                    <div class="form-group">
                        <label for="custom-field-value">Custom Field Value</label>
                        <input type="text" class="form-control" id="adddevice-custom-field-value" name="custom_field_value" placeholder="Enter value">
                    </div>
                    <input type="hidden" id="adddevice_custom_field_id" name=" custom_field_id" value="{{ $customfield->id }}">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="adddevice-btn">Save changes</button>
            </div>
        </div>
    </div>
</div>



@endsection

@php
$headerTemplate = view('nmscustomfields::customfield.partials.bootgrid-header-template',['customfield' => $customfield])->render();
$escapedHeaderTemplate = json_encode($headerTemplate);
$escapedHeaderTemplate = substr($escapedHeaderTemplate, 1, -1);
@endphp


@section('scripts')
<script>
    var fieldEditUrl = "{!! route('plugin.nmscustomfields.devicefield.edit', ['device' => ':device', 'customdevicefield' => ':customfield']) !!}";
    var fieldDeleteUrl = "{!! route('plugin.nmscustomfields.devicefield.destroy', ['device' => ':device', 'customdevicefield' => ':customfield']) !!}";

    $(function() {
        var grid = $("#device-customfields-table");

        grid.on("initialized.rs.jquery.bootgrid", function(e) {
            let device_id = $("#device_id");
            let custom_field_id = $("#custom_field_id");
            let grid = $("#device-customfields-table");

            // create customfield object for select2
            // initalize filter
            let customfield_object = {
                id: "{{ $customfield->id }}",
                text: "{{ $customfield->name }}"
            }

            // initailize select2 for device_id and custom_field_id
            init_select2(device_id, 'device', {}, '', 'All Devices');
            init_select2(custom_field_id, 'customfield', {}, customfield_object, 'All Fields', {
                ajax: {
                    url: '{{ route("plugin.nmscustomfields.select.customfields") }}',
                }
            });

            // add event listener for device_id and custom_field_id
            // to reload the table when filter is changed
            device_id.on("select2:select", function(e) {
                grid.bootgrid("reload");
            }).on("select2:clear", function(e) {
                grid.bootgrid("reload");
            });

            custom_field_id.on("select2:select", function(e) {
                grid.bootgrid("reload");
            }).on("select2:clearing", function(e) {
                e.preventDefault();
            });
        })

        grid.bootgrid({
            url: '{{ route("plugin.nmscustomfields.table.customfieldvalues") }}',
            ajax: true,
            rowCount: [25, 50, 100, 250, -1],
            columnSelection: true,
            selection: true,
            multiSelect: true,
            rowSelect: false,
            keepSelection: false,
            sorting: true,
            navigation: 3,

            searchSettings: {
                includeHidden: false
            },

            templates: {
                header: "{!! nl2br($escapedHeaderTemplate) !!}",
                noResults: "<tr><td colspan=\"@{{ctx.columns}}\" class=\"no-results\">No results found for field: <span id=\"bootgrid_selected_field_name\"></span><button id=\"add-devices-btn\" class=\"btn btn-primary\">Add devices</button></td></tr>"
            },

            formatters: {
                "link": function(column, row) {
                    let url = "{{ url('device') }}" + '/' + row.device_id;
                    return "<a href=\"" + url + "\">" + row[column.id] + "</a>";
                },
                "commands": function(column, row) {
                    let editUrl = fieldEditUrl.replace(':device', row.device_id).replace(':customfield', row.custom_field_value_id);
                    return "<a href=\"" + editUrl + "\" class=\"btn btn-xs btn-default command-edit\"><span class=\"glyphicon glyphicon-edit\"></span > </a> " +
                        "<button class=\"btn btn-xs btn-default command-delete\" x-data-device_id=\"" + row.device_id + "\" x-data-custom_field_value_id=\"" + row.custom_field_value_id + "\"><span class=\"glyphicon glyphicon-trash\"></span></button>";
                }
            },

            requestHandler: function(request) {
                request.device_id = $("#device_id").val();
                request.custom_field_id = $("#custom_field_id").val();
                return request;
            },
        }).on("loaded.rs.jquery.bootgrid", function(e) {
            $('#bulk-edit-btn').prop('disabled', true);
            $('#bulk-delete-btn').prop('disabled', true);

            // log total number of rows
            let total = grid.bootgrid("getTotalRowCount");
            if (total === 0) {
                let selected_field_name = $('#custom_field_id option:selected').text();
                // bind add devices button to open bulk edit modal
                $('#bootgrid_selected_field_name').text(selected_field_name);

                $("#add-devices-btn").on('click', function(event) {
                    $('#bulkEditModal').modal('show');
                });
            }

            $("button.command-delete").on("click", function() {
                // confirm delete
                if (!confirm("Are you sure you want to delete this custom field value?")) {
                    return;
                }

                let device_id = $(this).attr('x-data-device_id');
                let custom_field_value_id = $(this).attr('x-data-custom_field_value_id');
                let url = fieldDeleteUrl.replace(':device', device_id).replace(':customfield', custom_field_value_id);
                $.ajax({
                    type: "DELETE",
                    url: url,
                    success: function(response) {
                        grid.bootgrid("reload");
                    },
                    error: function(response) {
                        console.log(response);
                    }
                });
            });

        }).on("selected.rs.jquery.bootgrid", function(e, rows) {
            // Enable bulk edit button when rows are selected
            $('#bulk-edit-btn').prop('disabled', false);
            $('#bulk-delete-btn').prop('disabled', false);
        }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
            // Disable bulk edit button when rows are deselected
            $('#bulk-edit-btn').prop('disabled', true);
            $('#bulk-delete-btn').prop('disabled', true);
        });

        // Handle bulk edit button click
        $("div#manage-device-customfields").on('click', '#bulk-edit-btn', function() {
            $('#bulkEditModal').modal('show');
        });

        // handle device add button click
        $("div#manage-device-customfields").on('click', '#device-add-btn', function() {
            $('#addDeviceModal').modal('show');
        });

        // Handle bulk delete button click
        $("div#manage-device-customfields").on('click', '#bulk-delete-btn', function() {
            let selectedRowIds = grid.bootgrid("getSelectedRows");
            let rows = grid.bootgrid("getCurrentRows");
            let selectedRows = rows.filter(row => selectedRowIds.includes(row.device_id));
            if (selectedRows.length === 0) {
                return;
            }
            let device_ids = selectedRows.map(row => row.device_id).join(",");

            let custom_field_id = $("#custom_field_id").val();
            let url = "{{ route('plugin.nmscustomfields.devicefield.bulkdestroy') }}";
            let data = {
                device_ids: device_ids,
                custom_field_id: custom_field_id
            };

            $.ajax({
                type: "POST",
                url: url,
                data: data,
                success: function(response) {
                    grid.bootgrid("reload");
                },
                error: function(response) {
                    console.log(response);
                }
            });
        });


        // bind shown to modal to focus on input field
        $('#bulkEditModal').on('shown.bs.modal', function() {
            // Initialize Select2 for the multi-select field
            $('#bulk-edit-select-devices').select2({
                placeholder: "Search for devices",
                ajax: {
                    url: '{{ route("ajax.select.device") }}',
                    dataType: 'json',
                    delay: 250,
                    cache: true
                }
            });

            let selectDevices = $('#bulk-edit-select-devices');
            selectDevices.empty();

            let inputField = $('#blkedit-custom-field-value');
            inputField.val('');

            let selectedRowIds = grid.bootgrid("getSelectedRows");
            let multipleValuesWarning = 'Warning: Multiple different values selected. This will override all selected devices with this value.';

            if (selectedRowIds.length !== 0) {
                let rows = grid.bootgrid("getCurrentRows");
                let selectedRows = rows.filter(row => selectedRowIds.includes(row.device_id));
                let uniqueValues = [...new Set(selectedRows.map(row => row.custom_field_value))];
                if (uniqueValues.length === 1) {
                    inputField.val(uniqueValues[0]);
                    inputField.attr('placeholder', '');
                } else {
                    inputField.val('');
                    $('#alert-container').text(multipleValuesWarning).removeClass('hidden');
                }

                // Populate Select2 with selected devices
                selectedRows.forEach(function(row) {
                    let option = new Option(row.hostname, row.device_id, true, true);
                    selectDevices.append(option).trigger('change');
                });

                // set the selected device ids in the hidden input field
                $('#device_ids').val(selectedRows.map(row => row.device_id).join(","));
            }

            $('#blkedit-custom-field-value').focus();
            // set the blkedit_custom_field_id to the value of custom_field_id
            $('#blkedit_custom_field_id').val($("#custom_field_id").val());
        });

        $("div#bulkEditModal").on('click', '#save-bulk-edit-btn', function() {
            let form = $('#bulk-edit-form');
            let url = form.attr('action');
            let data = form.serialize();
            // log post values
            console.log(data);
            /// return
            $.ajax({
                type: "POST",
                url: url,
                data: data,
                success: function(response) {
                    $('#bulkEditModal').modal('hide');
                    grid.bootgrid("reload");
                },
                error: function(response) {
                    console.log(response);
                }
            });
        });

        // bind shown to modal to focus on input field
        $('#addDeviceModal').on('shown.bs.modal', function() {
            // Initialize Select2 for the multi-select field
            $('#add-device-select-devices').select2({
                placeholder: "Search for devices",
                ajax: {
                    url: '{{ route("ajax.select.device") }}',
                    dataType: 'json',
                    delay: 250,
                    cache: true
                }
            });

            let selectDevices = $('#add-device-select-devices');
            selectDevices.empty();

            let inputField = $('#adddevice-custom-field-value');
            inputField.val('');

            $('#custom-field-value').focus();
            $('#adddevice_custom_field_id').val($("#custom_field_id").val());
        });

        $("div#addDeviceModal").on('click', '#adddevice-btn', function() {
            let form = $('#add-device-form');
            let url = form.attr('action');
            let data = form.serialize();
            // log post values
            console.log(data);
            /// return
            $.ajax({
                type: "POST",
                url: url,
                data: data,
                success: function(response) {
                    $('#addDeviceModal').modal('hide');
                    grid.bootgrid("reload");
                },
                error: function(response) {
                    console.log(response);
                }
            });
        });

        $("#device_filter").submit(function(e) {
            e.preventDefault();
            grid.bootgrid("search", $("#hostname").val());
        });


    });
</script>
@endsection