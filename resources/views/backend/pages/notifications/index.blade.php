@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Notifications</h2>
        <a href="{{ route('admin.notifications.index') }}" class="text-blue-500 hover:underline text-sm">View All</a>
    </div>

    <div id="notifications-container">
        @include('backend.pages.notifications.partials.list', ['notifications' => $notifications])
    </div>
</div>

<script>
function markAsRead(id) {
    fetch(`/admin/notifications/read/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    }).then(res => res.json())
      .then(data => {
          if (data.status === 'success') {
              loadNotifications(window.currentPage || 1);
          }
      });
}

function markAsUnread(id) {
    fetch(`/admin/notifications/unread/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    }).then(res => res.json())
      .then(data => {
          if (data.status === 'success') {
              loadNotifications(window.currentPage || 1);
          }
      });
}

function loadNotifications(page = 1) {
    window.currentPage = page;
    fetch(`/admin/notifications?page=${page}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById('notifications-container').innerHTML = html;
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('#notifications-container .pagination a')) {
        e.preventDefault();
        const url = new URL(e.target.href);
        const page = url.searchParams.get('page');
        loadNotifications(page);
    }
});
</script>
@endsection
