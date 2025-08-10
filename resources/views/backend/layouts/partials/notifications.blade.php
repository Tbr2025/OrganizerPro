<style>
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    .slide-out {
        animation: slideOut 0.4s forwards ease-in-out;
    }

    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    .slide-out {
        animation: slideOut 0.4s forwards ease-in-out;
    }

    @keyframes toastIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes toastOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }

        to {
            opacity: 0;
            transform: translateY(20px);
        }
    }

    .toast {
        animation: toastIn 0.3s ease-out forwards;
    }

    .toast-hide {
        animation: toastOut 0.4s ease-in forwards;
    }
</style>

<!-- Toast Container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-3"></div>

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <!-- Bell Button -->
    <button id="notificationBtn" @click.prevent="open = !open"
        class="relative flex items-center justify-center w-10 h-10 rounded-full text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
        <iconify-icon icon="mdi:bell-outline" width="24" height="24"></iconify-icon>
        <span id="notificationCount"
            class="absolute top-1 right-1 bg-red-600 text-white text-xs font-bold rounded-full px-1.5 py-0.5 hidden">
        </span>
    </button>

    <!-- Dropdown -->
    <div id="notificationDropdown" x-show="open" x-transition
        class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 z-50"
        style="display: none;">

        <div class="flex justify-between items-center p-3 border-b border-gray-200 dark:border-gray-600">
            <span class="font-semibold text-gray-700 dark:text-gray-300">Notifications</span>
            <button id="markAllReadBtn" class="text-xs text-blue-500 hover:underline">
                Mark all as read
            </button>
        </div>

        <ul id="notificationList" class="max-h-64 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-600">
            @forelse(auth()->user()->unreadNotifications as $notification)
                <li class="px-4 py-2 flex justify-between items-start hover:bg-gray-100 dark:hover:bg-gray-600">
                    <a href="javascript:void(0)" class="flex-1"
                        onclick="markNotificationAsRead('{{ $notification->id }}', '{{ $notification->data['page'] ?? '' }}')">
                        {{ $notification->data['message'] ?? 'Notification' }}
                        <small class="block text-gray-500 dark:text-gray-400">
                            By {{ $notification->data['updated_by_name'] ?? '' }}
                        </small>
                    </a>
                    <button onclick="markNotificationAsRead('{{ $notification->id }}')"
                        class="text-gray-400 hover:text-red-500 ml-2">
                        ✕
                    </button>
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                    No new notifications
                </li>
            @endforelse
        </ul>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const countEl = document.getElementById("notificationCount");
        const listEl = document.getElementById("notificationList");
        const markAllBtn = document.getElementById("markAllReadBtn");
        let lastNotificationIds = new Set(); // track to detect new ones

        function renderNotifications(data) {
            if (data.count > 0) {
                countEl.textContent = data.count;
                countEl.classList.remove("hidden");
            } else {
                countEl.classList.add("hidden");
            }

            if (data.notifications.length > 0) {
                listEl.innerHTML = data.notifications.map(notification => `
                <li class="px-4 py-2 flex justify-between items-start hover:bg-gray-100 dark:hover:bg-gray-600">
                    <a href="javascript:void(0)" class="flex-1"
                       onclick="markNotificationAsRead('${notification.id}', '${notification.data?.page ?? ''}')">
                        ${notification.data?.message ?? 'Notification'}
                        <small class="block text-gray-500 dark:text-gray-400">
                            By ${notification.data?.updated_by_name ?? ''}
                        </small>
                    </a>
                    <button onclick="markNotificationAsRead('${notification.id}', '', this)"
    class="text-gray-400 hover:text-red-500 ml-2">
    ✕
</button>
                </li>
            `).join('');
            } else {
                listEl.innerHTML = `
                <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                    No new notifications
                </li>
            `;
            }

            // Detect and toast new notifications
            const currentIds = new Set(data.notifications.map(n => n.id));
            data.notifications.forEach(n => {
                if (!lastNotificationIds.has(n.id)) {
                    showToast(n.data?.message ?? "New notification");
                }
            });
            lastNotificationIds = currentIds;
        }

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = "toast bg-green-600 text-white px-5 py-3 rounded-lg shadow-lg flex items-center gap-2 min-w-[250px]";
    toast.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="flex-1">${message}</span>
    `;
    document.getElementById('toast-container').appendChild(toast);

    // Auto-hide after 3s
    setTimeout(() => {
        toast.classList.add('toast-hide');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}


        function fetchNotifications() {
            fetch("{{ route('admin.notifications.unread') }}")
                .then(res => res.json())
                .then(renderNotifications)
                .catch(err => console.error("Notification fetch error:", err));
        }

        function fetchNotificationCountOnly() {
            fetch("{{ route('admin.notifications.unread') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.count > 0) {
                        countEl.textContent = data.count;
                        countEl.classList.remove("hidden");
                    } else {
                        countEl.classList.add("hidden");
                    }
                });
        }

        window.markNotificationAsRead = function(id, page, el) {
            const listItem = el?.closest("li");
            if (listItem) {
                listItem.classList.add("slide-out");
                setTimeout(() => {
                    listItem.remove();
                    // Only fetch immediately if you must refresh the count right away
                    fetchNotificationCountOnly();
                }, 400); // match CSS duration
            }

            fetch(`/admin/notifications/read/${id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                }).then(res => res.json())
                .then(() => {
                    if (page) window.location.href = page;
                });
        };


        markAllBtn.addEventListener("click", function() {
            fetch(`/admin/notifications/read-all`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                }).then(res => res.json())
                .then(fetchNotifications);
        });

        fetchNotifications();
        setInterval(fetchNotifications, 10000);
    });

    window.markNotificationAsRead = function(id, page, el) {
        const listItem = el?.closest("li");
        if (listItem) {
            listItem.classList.add("slide-out");
            setTimeout(() => listItem.remove(), 400); // wait for animation
        }

        fetch(`/admin/notifications/read/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }).then(res => res.json())
            .then(() => {
                fetchNotifications();
                if (page) window.location.href = page;
            });
    };
</script>
