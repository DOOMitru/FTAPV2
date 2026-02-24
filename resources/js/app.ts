import './bootstrap';
import Alpine from 'alpinejs';
import { createApp } from 'vue';

window.Alpine = Alpine;
Alpine.start();
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';
import Dashboard from './components/Dashboard.vue';

// We will mount the dashboard component if the element exists
if (document.getElementById('dashboard-app')) {
    const app = createApp(Dashboard);
    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: '.dark',
            }
        }
    });
    app.mount('#dashboard-app');
}
