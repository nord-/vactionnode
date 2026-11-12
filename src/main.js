import { createApp } from 'vue';
import App from './App.vue';
// Ikontypsnittet importeras här och inte från style.css: Tailwind 4 processar
// style.css med Lightning CSS, som inte skriver om de relativa font-URL:erna
// över @import-gränsen, så typsnittsfilerna hamnade aldrig i bygget.
import '@mdi/font/css/materialdesignicons.css';
import './style.css';

createApp(App).mount('#app');
