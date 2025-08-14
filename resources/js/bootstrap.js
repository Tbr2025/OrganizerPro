import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// This is the part that creates window.Echo
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    
    // **THE FIX**
    wsHost: window.location.hostname, // This will be 'sportzley.com'
    wsPort: 443,      // Use the standard secure port
    wssPort: 443,     // Also use the standard secure port
    forceTLS: true,   // Force a secure (wss://) connection
    
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});