import globals from 'globals';

/** @type {import('eslint').Linter.Config[]} */
export default [
  {
    // Lint all JS source files (not stories, not build output)
    files: ['src/**/*.js'],
    ignores: ['src/**/*.stories.js', 'dist/**'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'script', // Drupal behaviors use IIFE, not ES modules
      globals: {
        ...globals.browser,
        Drupal: 'readonly',
        drupalSettings: 'readonly',
        once: 'readonly',
        jQuery: 'readonly',
      },
    },
    rules: {
      // Error-level rules
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-undef': 'error',
      'no-redeclare': 'error',
      'no-dupe-keys': 'error',
      'no-duplicate-case': 'error',
      'no-unreachable': 'error',

      // Warning-level rules
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-debugger': 'warn',
      'no-empty': 'warn',
      'no-extra-semi': 'error',
      'no-var': 'warn',
      'prefer-const': 'warn',

      // Style
      'eqeqeq': ['error', 'always'],
      'curly': ['error', 'all'],
      'semi': ['error', 'always'],
      'quotes': ['error', 'single', { avoidEscape: true }],
    },
  },
  {
    // Storybook stories use ES modules
    files: ['src/**/*.stories.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'no-undef': 'error',
    },
  },
];
