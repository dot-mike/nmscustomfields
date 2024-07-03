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
                            <th data-column-id="hostname">Device</th>
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
<div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
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
                        <label for="select-devices">Apply to devices</label>
                        <select id="select-devices" name="device_ids[]" class="form-control" multiple="multiple"></select>
                        <span class="help-block">Select devices to apply the custom field value to.<br>
                            <strong>Note:</strong> If you remove a device from the list, the custom field value will also be removed from that device.</span>
                        </span>
                    </div>
                    <div id="alert-container" class="alert alert-warning hidden" role="alert"></div>
                    <div class="form-group">
                        <label for="custom-field-value">Custom Field Value</label>
                        <input type="text" class="form-control" id="custom-field-value" name="custom_field_value" placeholder="Enter value">
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

    var selected_field_name = '';

    $(function() {
        var grid = $("#device-customfields-table");

        grid.on("initialized.rs.jquery.bootgrid", function(e) {
            var device_id = $("#device_id");
            var custom_field_id = $("#custom_field_id");
            var grid = $("#device-customfields-table");

            // create customfield object for select2
            // initalize filter
            var customfield_object = {
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
                selected_field_name = e.params.data.text;
            }).on("select2:clearing", function(e) {
                e.preventDefault();
            });
        })
        grid.bootgrid({
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
                noResults: "<tr><td colspan=\"@{{ctx.columns}}\" class=\"no-results\">No results found for <span id=\"bootgrid_selected_field_name\"></span>. <button id=\"add-devices-btn\" class=\"btn btn-primary\">Add devices</button></td></tr>"
            },

            formatters: {
                "commands": function(column, row) {
                    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.device_id + "\"><span class=\"glyphicon glyphicon-edit\"></span></button> " +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.device_id + "\"><span class=\"glyphicon glyphicon-trash\"></span></button>";
                }
            },

            requestHandler: function(request) {
                request.device_id = $("#device_id").val();
                request.custom_field_id = $("#custom_field_id").val();
                return request;
            },

            url: '{{ route("plugin.nmscustomfields.table.customfieldvalues") }}',
        }).on("loaded.rs.jquery.bootgrid", function(e) {
            $('#bulk-edit-btn').prop('disabled', true);
            $('#bulk-delete-btn').prop('disabled', true);

            // set the selected field name when no results are found
            if (grid.bootgrid("getTotalRowCount") != 0) {
                /* Executes after data is loaded and rendered */
                grid.find(".command-edit").on("click", function(e) {
                    var row_index = $(this).closest('tr').index();
                    var row = grid.bootgrid("getCurrentRows")[row_index];
                    var url = fieldEditUrl.replace(':device', row.device_id).replace(':customfield', row.custom_field_value_id);
                    window.location.href = url;
                }).end().find(".command-delete").on("click", function(e) {
                    var row_index = $(this).closest('tr').index();
                    var row = grid.bootgrid("getCurrentRows")[row_index];
                    var url = fieldDeleteUrl.replace(':device', row.device_id).replace(':customfield', row.custom_field_value_id);
                    window.location.href = url;
                });
            } else {
                $('#bootgrid_selected_field_name').text(selected_field_name);

                $('#add-devices-btn').on('click', function() {
                    $('#bulkEditModal').modal('show');
                });
            }

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

        $("div#manage-device-customfields").on('click', '#bulk-delete-btn', function() {
            var selectedRowIds = grid.bootgrid("getSelectedRows");
            var rows = grid.bootgrid("getCurrentRows");
            var selectedRows = rows.filter(row => selectedRowIds.includes(row.device_id));
            var device_ids = selectedRows.map(row => row.device_id).join(",");

            var custom_field_id = $("#custom_field_id").val();
            var url = "{{ route('plugin.nmscustomfields.devicefield.bulkdestroy') }}";
            var data = {
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
            var selectedRowIds = grid.bootgrid("getSelectedRows");

            var inputField = $('#custom-field-value');
            var multipleValuesWarning = 'Warning: Multiple different values selected. This will override all selected devices with this value.';

            if (selectedRowIds.length !== 0) {
                var rows = grid.bootgrid("getCurrentRows");
                var selectedRows = rows.filter(row => selectedRowIds.includes(row.device_id));
                var uniqueValues = [...new Set(selectedRows.map(row => row.custom_field_value))];
                if (uniqueValues.length === 1) {
                    inputField.val(uniqueValues[0]);
                    inputField.attr('placeholder', '');
                } else {
                    inputField.val('');
                    $('#alert-container').text(multipleValuesWarning).removeClass('hidden');
                }
            }

            // Initialize Select2 for the multi-select field
            $('#select-devices').select2({
                placeholder: "Search for devices",
                ajax: {
                    url: '{{ route("ajax.select.device") }}',
                    dataType: 'json',
                    delay: 250,
                    cache: true
                }
            });

            // Populate Select2 with selected devices
            var selectDevices = $('#select-devices');
            selectDevices.empty();
            selectedRows.forEach(function(row) {
                var option = new Option(row.hostname, row.device_id, true, true);
                selectDevices.append(option).trigger('change');
            });

            // set the selected device ids in the hidden input field
            $('#device_ids').val(selectedRows.map(row => row.device_id).join(","));

            $('#custom-field-value').focus();
            // set the blkedit_custom_field_id to the value of custom_field_id
            $('#blkedit_custom_field_id').val($("#custom_field_id").val());
        });

        // Handle add field to devices button click
        // when no results are found
        $("div#manage-device-customfields").on('click', '#add-devices-btn', function() {
            $('#bulkEditModal').modal('show');
        });

        $("div#bulkEditModal").on('click', '#save-bulk-edit-btn', function() {
            var form = $('#bulk-edit-form');
            var url = form.attr('action');
            var data = form.serialize();
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

        $("#device_filter").submit(function(e) {
            e.preventDefault();
            grid.bootgrid("search", $("#hostname").val());
        });
    });
</script>
@endsection