import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                heading: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta Tren Maya (igual que Dashboard y Tickets).
                brand: {
                    mist: '#F2F7F5',
                    mint: '#70C2AD',
                    teal: '#1F7460',
                    green: '#06534D',
                    'green-dark': '#043D39',
                },
            },
        },
    },

    plugins: [forms],
};
