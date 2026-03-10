/** @type {import('stylelint').Config} */
export default {
  extends: ['stylelint-config-standard-scss'],
  rules: {
    // Allow BEM-style class selectors: .c-button__label--primary
    'selector-class-pattern': [
      '^[a-z]([a-z0-9-]+)?(__([a-z0-9]+-?)+)?(--([a-z0-9]+-?)+)?$',
      {
        message: 'Expected class selector to match BEM pattern (e.g. .c-block__element--modifier)',
      },
    ],
    // Drupal uses CSS custom properties extensively
    'custom-property-pattern': null,
    // Allow nesting depth for SCSS
    'max-nesting-depth': [
      3,
      {
        ignoreAtRules: ['media', 'supports', 'include'],
      },
    ],
    // Disable no-descending-specificity for component-based architecture
    'no-descending-specificity': null,
    // Allow @import in SCSS (will be deprecated but still used)
    'scss/at-import-no-partial-leading-underscore': null,
    // Allow Sass nesting of pseudo-classes/elements
    'scss/selector-no-redundant-nesting-selector': null,
  },
  ignoreFiles: ['dist/**', 'node_modules/**', 'storybook-static/**'],
};
