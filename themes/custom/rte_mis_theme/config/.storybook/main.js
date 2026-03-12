/** @type { import('@storybook/html-vite').StorybookConfig } */
const config = {
  stories: [
    // SDC components (flat structure)
    "../../src/components/**/*.stories.@(js|jsx|mjs|ts|tsx)",
    "../../src/components/**/*.mdx",
  ],
  addons: [
    "@chromatic-com/storybook",
    "@storybook/addon-vitest",
    "@storybook/addon-a11y",
    "@storybook/addon-docs",
  ],
  framework: "@storybook/html-vite",
  staticDirs: ["../../src"],

  /**
   * Extend Vite config so .twig files are importable as raw strings.
   * This enables Vite HMR — editing a .twig file triggers a hot reload.
   */
  viteFinal: (config) => {
    config.plugins = config.plugins || [];
    config.plugins.push({
      name: "vite-plugin-twig-raw",
      transform(code, id) {
        if (id.endsWith(".twig")) {
          return {
            code: `export default ${JSON.stringify(code)};`,
            map: null,
          };
        }
      },
    });
    return config;
  },
};
export default config;
