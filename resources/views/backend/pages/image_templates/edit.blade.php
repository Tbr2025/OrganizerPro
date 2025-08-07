@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <x-backend.card>
        <x-slot name="header">Edit Welcome Template</x-slot>

        <x-slot name="body">
            <div>
                <canvas id="canvas" width="1000" height="600" class="border"></canvas>

                <form method="POST" action="{{ route('admin.image-templates.save') }}" onsubmit="return saveLayout()">
                    @csrf
                    <textarea name="layout_data" id="layout_data" class="hidden"></textarea>
                    <button type="submit" class="btn btn-primary mt-4">Save Template</button>
                </form>
            </div>
        </x-slot>
    </x-backend.card>
@endsection

@push('after-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
    const canvas = new fabric.Canvas('canvas');
    @if ($layoutJson)
        canvas.loadFromJSON({!! $layoutJson !!});
    @endif

    window.saveLayout = function () {
        document.getElementById('layout_data').value = JSON.stringify(canvas.toJSON());
        return true;
    }
</script>
@endpush
