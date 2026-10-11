import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

let appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Nama aplikasi dinamis dari pengaturan (shared prop `name`), diperbarui di setiap navigasi
router.on('navigate', (event) => {
    const name = event.detail.page.props.name;
    if (typeof name === 'string' && name) appName = name;
});

// Judul halaman lama memakai akhiran "- Momoyo Ops" yang di-hardcode; ganti dengan nama aplikasi saat ini
const cleanTitle = (title: string) => title.replace(/\s*-\s*Momoyo Ops$/i, '');

void createInertiaApp({
    title: (title) => (cleanTitle(title) ? `${cleanTitle(title)} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
