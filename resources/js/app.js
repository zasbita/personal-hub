import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router/index.js';
import App from './views/App.vue';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');

// PWA — ponytail: register only if served over https or localhost
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}
