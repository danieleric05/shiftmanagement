import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#1E3A8A',
                    light: '#3B82F6',
                    50: '#EFF4FF',
                    100: '#DCE6FD',
                },
                success: {
                    DEFAULT: '#10B981',
                    50: '#ECFDF5',
                    600: '#059669',
                    700: '#047857',
                },
                warning: {
                    DEFAULT: '#F59E0B',
                    50: '#FFFBEB',
                },
                danger: {
                    DEFAULT: '#EF4444',
                    50: '#FEF2F2',
                },
                membre: {
                    DEFAULT: '#4F46E5',
                    50: '#EEF2FF',
                },
                neutral: {
                    50: '#F9FAFB',
                    100: '#F3F4F6',
                    300: '#D1D5DB',
                    600: '#4B5563',
                    900: '#111827',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(17 24 39 / 0.04), 0 1px 3px 0 rgb(17 24 39 / 0.06)',
            },
        },
    },

    plugins: [forms],
};
