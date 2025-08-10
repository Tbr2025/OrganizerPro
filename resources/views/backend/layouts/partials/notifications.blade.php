{{-- Put this in your layout (e.g. backend/layouts/app.blade.php) --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
/* Slide-out */
@keyframes slideOut { from { opacity: 1; transform: translateX(0);} to { opacity: 0; transform: translateX(100%);} }
.slide-out { animation: slideOut 0.36s forwards ease-in-out; }

/* Toasts */
@keyframes toastIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(12px); } }
.toast { animation: toastIn 0.28s ease-out forwards; }
.toast-hide { animation: toastOut 0.32s ease-in forwards; }
</style>

<!-- Toast container (bottom-right) -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-3 pointer-events-none"></div>

<!-- Notification bell + dropdown -->
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <!-- Bell Button -->
    <button id="notificationBtn" @click.prevent="open = !open"
        class="relative flex items-center justify-center w-10 h-10 rounded-full text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
        <iconify-icon icon="mdi:bell-outline" width="24" height="24"></iconify-icon>
        <span id="notificationCount"
            class="absolute top-1 right-1 bg-red-600 text-white text-xs font-bold rounded-full px-1.5 py-0.5 hidden"></span>
    </button>

    <!-- Dropdown -->
    <div id="notificationDropdown" x-show="open" x-transition
        class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 z-50"
        style="display: none;">

        <div class="flex justify-between items-center p-3 border-b border-gray-200 dark:border-gray-600">
            <span class="font-semibold text-gray-700 dark:text-gray-300">Notifications</span>

            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.notifications.index') }}" class="text-xs text-blue-500 hover:underline">
                    View All
                </a>
                <button id="markAllReadBtn" class="text-xs text-blue-500 hover:underline">Mark all as read</button>
            </div>
        </div>

        <ul id="notificationList" class="max-h-64 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-600">
            {{-- Optional server-side fallback initial content (will get replaced by JS). --}}
            @forelse(auth()->user()->unreadNotifications as $notification)
                <li data-id="{{ $notification->id }}" class="px-4 py-2 flex justify-between items-start hover:bg-gray-100 dark:hover:bg-gray-600">
                    <a href="javascript:void(0)" class="flex-1"
                       onclick="markNotificationAsRead('{{ $notification->id }}','{{ $notification->data['page'] ?? '' }}')">
                        {{ $notification->data['message'] ?? 'Notification' }}
                        <small class="block text-gray-500 dark:text-gray-400">By {{ $notification->data['updated_by_name'] ?? '' }}</small>
                    </a>
                    <button onclick="markNotificationAsRead('{{ $notification->id }}','', this)"
                        class="text-gray-400 hover:text-red-500 ml-2">✕</button>
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">No new notifications</li>
            @endforelse
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const countEl = document.getElementById('notificationCount');
    const listEl = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllReadBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let lastNotificationIds = new Set();
    let firstLoad = true;

    // Helper: create a safe notification <li> element (no innerHTML from data)
    function createNotificationListItem(n) {
        const li = document.createElement('li');
        li.className = "px-4 py-2 flex justify-between items-start hover:bg-gray-100 dark:hover:bg-gray-600";
        li.dataset.id = n.id;

        const a = document.createElement('a');
        a.href = 'javascript:void(0)';
        a.className = 'flex-1';
        a.addEventListener('click', () => markNotificationAsRead(n.id, n.data?.page || ''));

        // message
        const msg = document.createElement('div');
        msg.textContent = n.data?.message ?? 'Notification';

        // small by-line
        const small = document.createElement('small');
        small.className = 'block text-gray-500 dark:text-gray-400';
        small.textContent = 'By ' + (n.data?.updated_by_name ?? '');

        msg.appendChild(small);
        a.appendChild(msg);

        const btn = document.createElement('button');
        btn.className = 'text-gray-400 hover:text-red-500 ml-2';
        btn.type = 'button';
        btn.textContent = '✕';
        btn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            // pass the element so animation targets it
            markNotificationAsRead(n.id, '', ev.currentTarget);
        });

        li.appendChild(a);
        li.appendChild(btn);

        return li;
    }

    // Render notification list safely
    function renderNotifications(data, showToasts = false) {
        // update badge
        if (data.count && data.count > 0) {
            countEl.textContent = data.count;
            countEl.classList.remove('hidden');
        } else {
            countEl.classList.add('hidden');
        }

        // rebuild list (we rebuild; but close animation handles removal when user clicks button)
        listEl.innerHTML = '';
        if (data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(n => {
                listEl.appendChild(createNotificationListItem(n));
            });
        } else {
            const li = document.createElement('li');
            li.className = 'px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center';
            li.textContent = 'No new notifications';
            listEl.appendChild(li);
        }

        // detect and show toast for new notifications only after initial load
        const currentIds = new Set((data.notifications || []).map(x => x.id));
        if (showToasts && !firstLoad) {
            (data.notifications || []).forEach(n => {
                if (!lastNotificationIds.has(n.id)) {
                    showToast(n.data?.message ?? 'New notification');
                }
            });
        }
        lastNotificationIds = currentIds;
        firstLoad = false;
    }

    // show toast (bottom-right), auto hide
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast bg-green-600 text-white px-5 py-3 rounded-lg shadow-lg flex items-center gap-2 min-w-[250px]';
        toast.style.pointerEvents = 'auto'; // allow interactions if needed

        // icon
        toast.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span style="flex:1">${escapeHtml(message)}</span>
        `;
        const container = document.getElementById('toast-container');
        container.appendChild(toast);

        // auto hide
        setTimeout(() => {
            toast.classList.add('toast-hide');
            setTimeout(() => toast.remove(), 420);
        }, 3000);
    }

    // simple escape to avoid injecting HTML into toast content
    function escapeHtml(str) {
        return String(str).replace(/[&<>"'`=\/]/g, function (s) {
            return {
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
                "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
            }[s];
        });
    }

    // fetch notifications from server; showToasts indicates if new arrivals should show toast
    function fetchNotifications(showToasts = false) {
        fetch("{{ route('admin.notifications.unread') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => renderNotifications(data, showToasts))
            .catch(e => console.error('Notification fetch error:', e));
    }

    // mark single notification as read; el may be the button element to animate
    window.markNotificationAsRead = function(id, page = '', el = null) {
        // animate & remove list item locally if element provided
        let listItem;
        if (el) {
            listItem = el.closest('li');
        } else {
            listItem = document.querySelector(`#notificationList li[data-id="${id}"]`);
        }
        if (listItem) {
            listItem.classList.add('slide-out');
            // remove after animation
            setTimeout(() => {
                listItem.remove();
                // refresh count only (lightweight)
                fetchNotificationCountOnly();
            }, 380);
        }

        // send server request
        fetch(`/admin/notifications/read/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then(res => res.json())
          .then(() => {
              // optional: if the notification had a page and you want to go there:
              if (page) window.location.href = page;
          })
          .catch(e => console.error('Mark read error:', e));
    };

    // mark all as read
    markAllBtn.addEventListener('click', () => {
        fetch('/admin/notifications/read-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then(r => r.json())
          .then(() => fetchNotifications(false)) // update list/badge (no toast)
          .catch(e => console.error('Mark all error:', e));
    });

    // fetch only count to update badge after local remove
    function fetchNotificationCountOnly() {
        fetch("{{ route('admin.notifications.unread') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d.count && d.count > 0) {
                    countEl.textContent = d.count;
                    countEl.classList.remove('hidden');
                } else {
                    countEl.classList.add('hidden');
                }
            })
            .catch(e => console.error('Count fetch error:', e));
    }

    // Start: initial load (no toasts), then poll and allow toasts later
    fetchNotifications(false);              // first load: do NOT toast
    setInterval(() => fetchNotifications(true), 10000); // subsequent polls: toast new items
});
</script>
