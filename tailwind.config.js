import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
                app: {
                    bg: 'rgb(var(--app-bg) / <alpha-value>)',
                    surface: 'rgb(var(--app-surface) / <alpha-value>)',
                    'surface-2': 'rgb(var(--app-surface-2) / <alpha-value>)',
                    border: 'rgb(var(--app-border) / <alpha-value>)',
                    text: 'rgb(var(--app-text) / <alpha-value>)',
                    muted: 'rgb(var(--app-muted) / <alpha-value>)',
                },
            },
            borderRadius: {
                xl: '0.75rem',
                '2xl': '1rem',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                dropdown: '0 12px 30px -12px rgb(15 23 42 / 0.24)',
                popover: '0 20px 40px -20px rgb(15 23 42 / 0.35)',
            },
        },
    },

    plugins: [forms],
};
