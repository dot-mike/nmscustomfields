@extends('nmscustomfields::includes.pluginadmin')

@section('title', 'Custom Fields Plugin')

@section('content2')

<div class="panel panel-default" id="manage-customfields">
    <div class="panel-heading">
        <h3 class="panel-title">Custom Fields</h3>
    </div>

    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <a type="button" class="btn btn-primary" href="{{ route('plugin.nmscustomfields.customfield.create') }}">
                    <i class="fa fa-plus"></i> Create Custom Field
                </a>
            </div>
        </div>
        <div class="col">
            <div class="table-responsive">
                <div class="table-responsive">
                    <table id="manage-port-groups-table" class="table table-striped table-hover table-condensed">
                        <thead class="thead-light">
                            <tr>
                                <th class="sticky-top">ID</th>
                                <th class="sticky-top">Name</th>
                                <th class="sticky-top">Type</th>
                                <th class="sticky-top">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customfields as $customfield)
                            <tr>
                                <td class="col-lg-1">{{ $customfield->id }}</td>
                                <td class="col-lg-3">{{ $customfield->name }}</td>
                                <td class="col-lg-3">{{ $customfield->type }}</td>
                                <td class="col-lg-5">
                                    <a href="{{ route('plugin.nmscustomfields.customfield.edit', $customfield) }}" class="btn btn-primary btn-sm" aria-label="Edit {{ $customfield->name }}">
                                        <i class="fa fa-pencil"></i> Edit
                                    </a>

                                    <form action="{{ route('plugin.nmscustomfields.customfield.destroy', $customfield) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete {{ $customfield->name }}">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No custom fields found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection