<form action="{{ route('admin.players.importCsv') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="mb-4">
        <label for="csv_file" class="form-label">Upload CSV File</label>
        <input type="file" name="csv_file" id="csv_file" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Import Players</button>
</form>
