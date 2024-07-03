{{-- This page will render the custom fields in device overview page --}}
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed device-overview">
            <div class="panel-heading">
                <strong>Custom Device Fields Plugin</strong> <a href="{{ route('plugin.nmscustomfields.device.index', ['device' => $device['device_id']]) }}">[EDIT]</a>
            </div>
            <div class="panel-body">
                <table class="table table-condensed table-striped">
                    <thead>
                        <tr>
                            <th>Field Name</th>
                            <th>Field Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customFields as $customField)
                        <tr>
                            <td><strong>{{ $customField->field_name }}</strong></td>
                            <td>{{ $customField->field_value }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2">No custom fields found.</td>
                        </tr>
                        @endforelse
                </table>
            </div>
        </div>
    </div>
</div>