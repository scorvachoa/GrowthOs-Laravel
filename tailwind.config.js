import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    safelist: [
        'bg-indigo-500/20', 'group-hover:bg-indigo-500/30', 'text-indigo-400',
        'bg-purple-500/20', 'group-hover:bg-purple-500/30', 'text-purple-400',
        'bg-emerald-500/20', 'group-hover:bg-emerald-500/30', 'text-emerald-400',
        'bg-amber-500/20', 'group-hover:bg-amber-500/30', 'text-amber-400',
        'bg-rose-500/20', 'group-hover:bg-rose-500/30', 'text-rose-400',
        'bg-cyan-500/20', 'group-hover:bg-cyan-500/30', 'text-cyan-400',
        'bg-orange-500/20', 'group-hover:bg-orange-500/30', 'text-orange-400',
        'bg-teal-500/20', 'group-hover:bg-teal-500/30', 'text-teal-400',
        'bg-violet-500/20', 'group-hover:bg-violet-500/30', 'text-violet-400',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
