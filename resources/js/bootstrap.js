import './globals';   // 一定要放在第一行

/**
 * Bootstrap 與 Bootstrap Confirmation。
 * 執行到這裡時，window.$、window.jQuery、window.Popper 都已經設定好了。
 */
import * as bootstrap from 'bootstrap';
import './confirmation';

/**
 * axios
 */
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo（目前未使用）
 */
// import Echo from 'laravel-echo';
// window.Pusher = require('pusher-js');
// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: process.env.MIX_PUSHER_APP_KEY,
//     cluster: process.env.MIX_PUSHER_APP_CLUSTER,
//     forceTLS: true
// });