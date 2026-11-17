import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
import SelectButton from 'primevue/selectbutton';
import StyleClass from 'primevue/styleclass';
import Tooltip from 'primevue/tooltip';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

import { citraPrimaryPalette } from './layout/theme';
import './assets/styles.scss';

const CitraPreset = definePreset(Aura, {
    primitive: {
        borderRadius: {
            sm: '0.375rem',
            md: '0.5rem',
            lg: '0.5rem',
        },
    },
    semantic: {
        primary: citraPrimaryPalette,
        formField: {
            borderRadius: '{border.radius.md}',
            paddingY: '0.625rem',
            focusRing: {
                width: '2px',
                style: 'solid',
                color: '{primary.color}',
                offset: '2px',
                shadow: 'none',
            },
        },
        focusRing: {
            width: '2px',
            style: 'solid',
            color: '{primary.color}',
            offset: '2px',
            shadow: 'none',
        },
    },
});

createInertiaApp({
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h(App, props),
        });

        app.use(plugin);

        app.use(PrimeVue, {
            theme: {
                preset: CitraPreset,
                options: {
                    darkModeSelector: '.app-dark',
                },
            },
        });

        app.directive('styleclass', StyleClass);
        app.directive('tooltip', Tooltip);
        app.component('SelectButton', SelectButton);
        app.use(ToastService);
        app.use(ConfirmationService);

        app.mount(el);
    },
});