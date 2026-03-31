import eslintReact from '@eslint-react/eslint-plugin';
import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import globals from 'globals';
import typescript from 'typescript-eslint';

export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    eslintReact.configs.recommended,
    {
        languageOptions: { globals: { ...globals.browser } },
        rules: {
            '@eslint-react/set-state-in-effect': 'off',
            '@eslint-react/purity': 'off',
            '@eslint-react/no-array-index-key': 'off',
            '@eslint-react/naming-convention/ref-name': 'off',
        },
    },
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr'] },
    prettier,
];
