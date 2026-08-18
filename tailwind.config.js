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
            },

            screens: {
                // "Opened on a PC" — a mouse-driven device. Used by the public
                // download page to show a QR code only where it helps.
                //
                // Deliberately not a width breakpoint: a phone in landscape
                // clears md, and a half-width laptop window does not, so width
                // gets this backwards in both directions. Pointer capability is
                // the thing actually being asked about.
                pc: { raw: '(hover: hover) and (pointer: fine)' },
            },
        },
    },

    plugins: [forms],
};
