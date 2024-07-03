<div class="modal fade" id="deviceAddCustomFieldModal" tabindex="-1" role="dialog" aria-labelledby="deviceCustomFieldLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="hidden" id="error-box" role="alert">
                @foreach(['success', 'warning', 'info', 'danger'] as $alert)
                @if (Session::has($alert))
                <div class="alert alert-{{$alert}}" role="alert">{{Session::get($alert)}}</div>
                @endif
                @endforeach
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="deviceCustomFieldLabel">Add Custom Field to Device</h5>
            </div>
            <div class="modal-body">
                <form id="deviceAddCustomFieldForm" action="{{ route('nmscustomfields.device.add', ['device' => $device->device_id]) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="selectBox">Select an option</label>
                        <select class="form-control" name="custom_field_id" id="selectBox">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" id="saveChangesBtn" class=" btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).on('show.bs.modal', '#deviceAddCustomFieldModal', function() {
        $.ajax({
            url: "{{ route('nmscustomfields.fields') }}",
            type: 'GET',
            success: function(data, status, xhr) {
                if (xhr.status == 204) {
                    $('#error-box').toggleClass('hidden');
                    return;
                }
                var selectBox = $('#selectBox');
                selectBox.empty();
                $.each(data, function(index, field) {
                    selectBox.append('<option value="' + field.id + '">' + field.name + '</option>');
                });
            },
            error: function(data) {
                $('#error-box').toggleClass('hidden');
            }
        });
    });

    $("#deviceAddCustomFieldModal").on('click', '#saveChangesBtn', function() {
        $('#deviceAddCustomFieldForm').submit();
    });
</script>