import eslintReact from '@eslint-react/eslint-plugin';
import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    eslintReact.configs.recommended,
    {
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            // setState in effects is common for syncing props to state
            '@eslint-react/set-state-in-effect': 'off',

            // Deterministic display formatting (new Date) in render is fine
            '@eslint-react/purity': 'off',

            // Stable, non-reorderable lists can use index keys safely
            '@eslint-react/no-array-index-key': 'off',

            // Ref naming is a style preference, our conventions are clear
            '@eslint-react/naming-convention/ref-name': 'off',
        },
    },
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
