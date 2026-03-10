/**
 * @file build.js
 * Incremental build script for compiling SCSS, JS, and SVG sprites.
 *
 * - Only recompiles / copies files whose source is newer than the output.
 * - Removes orphaned output when a source file or component is deleted.
 * - Pass --clean to force a full rebuild (deletes output dirs first).
 *
 * Output structure:
 * - Component SCSS   → components/<name>/css/<name>.css (+.map)
 * - Component JS     → components/<name>/js/<name>.js
 * - Component assets → components/<name>/<name>.component.yml, <name>.twig
 * - Global SCSS      → dist/css/styles.css (+.map)
 * - Individual globals → dist/css/tokens.css, foundation.css, etc.
 * - SVG sprite       → dist/icons.svg
 */

import { execSync } from "node:child_process";
import {
  readdirSync,
  statSync,
  existsSync,
  mkdirSync,
  copyFileSync,
  readFileSync,
  writeFileSync,
  rmSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Project root is one level up from build/
const ROOT_DIR = path.resolve(__dirname, "..");
const SRC_DIR = path.join(ROOT_DIR, "src");
const COMPONENTS_SRC_DIR = path.join(SRC_DIR, "components");
const COMPONENTS_OUT_DIR = path.join(ROOT_DIR, "components");
const DIST_DIR = path.join(ROOT_DIR, "dist");
const ASSETS_DIR = path.join(ROOT_DIR, "assets");
const ICONS_DIR = path.join(ASSETS_DIR, "icons");

const FORCE_CLEAN = process.argv.includes("--clean");

// ──────────────────────────────────────
// Helpers
// ──────────────────────────────────────

/**
 * Return true when `srcPath` is newer than `destPath` (or dest doesn't exist).
 */
function isNewer(srcPath, destPath) {
  if (!existsSync(destPath)) return true;
  return statSync(srcPath).mtimeMs > statSync(destPath).mtimeMs;
}

/**
 * Recursively collect all .scss files under a directory.
 */
function collectScssFiles(dir) {
  let files = [];
  if (!existsSync(dir)) return files;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files = files.concat(collectScssFiles(full));
    } else if (entry.name.endsWith(".scss")) {
      files.push(full);
    }
  }
  return files;
}

/**
 * Return the most-recent mtime (ms) among all .scss files under `dirs`.
 * Used to detect when a shared dependency (token, mixin, etc.) changes.
 */
function latestScssMtime(...dirs) {
  let latest = 0;
  for (const dir of dirs) {
    for (const f of collectScssFiles(dir)) {
      const mt = statSync(f).mtimeMs;
      if (mt > latest) latest = mt;
    }
  }
  return latest;
}

/**
 * Compile a single SCSS file to CSS with source maps.
 */
function compileSCSS(inputPath, outputPath) {
  const outputDir = path.dirname(outputPath);
  if (!existsSync(outputDir)) {
    mkdirSync(outputDir, { recursive: true });
  }
  try {
    execSync(
      `npx sass "${inputPath}" "${outputPath}" --style=expanded --source-map --load-path="${SRC_DIR}"`,
      { stdio: "pipe" },
    );
    console.log(
      `  ✓ SCSS: ${path.relative(ROOT_DIR, inputPath)} → ${path.relative(ROOT_DIR, outputPath)}`,
    );
  } catch (err) {
    console.error(`  ✗ SCSS: ${path.relative(ROOT_DIR, inputPath)}`);
    console.error(err.stderr?.toString() || err.message);
    process.exitCode = 1;
  }
}

/**
 * Copy a file to the target location.
 */
function copyFile(inputPath, outputPath, label = "COPY") {
  const outputDir = path.dirname(outputPath);
  if (!existsSync(outputDir)) {
    mkdirSync(outputDir, { recursive: true });
  }
  copyFileSync(inputPath, outputPath);
  console.log(
    `  ✓ ${label}: ${path.relative(ROOT_DIR, inputPath)} → ${path.relative(ROOT_DIR, outputPath)}`,
  );
}

/**
 * Remove a file/dir and log it.
 */
function removeOutput(target, label = "DEL") {
  if (existsSync(target)) {
    rmSync(target, { recursive: true, force: true });
    console.log(`  ✗ ${label}: ${path.relative(ROOT_DIR, target)}`);
  }
}

/**
 * Recursively find all relative component paths (e.g. "atoms/button").
 */
function walkComponentDirs(dir, basePath) {
  if (!existsSync(dir)) return [];
  let results = [];
  const entries = readdirSync(dir, { withFileTypes: true });
  const hasFiles = entries.some((e) => e.isFile() && !e.name.startsWith("."));

  if (hasFiles && dir !== basePath) {
    results.push(path.relative(basePath, dir));
  }

  for (const entry of entries) {
    if (entry.isDirectory() && !entry.name.startsWith(".")) {
      results = results.concat(
        walkComponentDirs(path.join(dir, entry.name), basePath),
      );
    }
  }
  return results;
}

/**
 * Get all component directory names.
 */
function getComponentDirs() {
  return walkComponentDirs(COMPONENTS_SRC_DIR, COMPONENTS_SRC_DIR);
}

/**
 * Get all existing output component directory names.
 */
function getOutputComponentDirs() {
  return walkComponentDirs(COMPONENTS_OUT_DIR, COMPONENTS_OUT_DIR);
}

// ==============================
// Optional full clean (--clean)
// ==============================
if (FORCE_CLEAN) {
  console.log("\n🧹 --clean: Removing all build output...\n");
  for (const dir of [COMPONENTS_OUT_DIR, DIST_DIR]) {
    if (existsSync(dir)) {
      rmSync(dir, { recursive: true, force: true });
      console.log(`  ✓ Removed ${path.relative(ROOT_DIR, dir)}/`);
    }
  }
}

// Pre-calculate the latest mtime of all shared SCSS (tokens, foundation, layout, util)
// so component SCSS rebuilds when a dependency changes.
const sharedScssDirs = ["tokens", "foundation", "layout", "util"].map((d) =>
  path.join(SRC_DIR, d),
);
const sharedLatest = latestScssMtime(...sharedScssDirs);

// ==============================
// Build Components → components/<name>/
// ==============================
console.log("\n📦 Building components...\n");

const srcComponents = getComponentDirs();
const outComponents = getOutputComponentDirs();
let componentChanges = 0;

// Remove output dirs for deleted source components
for (const name of outComponents) {
  if (!srcComponents.includes(name)) {
    removeOutput(path.join(COMPONENTS_OUT_DIR, name), "DEL");
    componentChanges++;
  }
}

for (const component of srcComponents) {
  const srcDir = path.join(COMPONENTS_SRC_DIR, component);
  const outDir = path.join(COMPONENTS_OUT_DIR, component);
  const componentName = path.basename(component);

  // --- SCSS ---
  const scssFile = path.join(srcDir, `${componentName}.scss`);
  const cssOutput = path.join(outDir, `${componentName}.css`);
  if (existsSync(scssFile)) {
    // Rebuild if the component scss OR any shared scss dependency is newer
    const srcMtime = Math.max(statSync(scssFile).mtimeMs, sharedLatest);
    const needsBuild =
      !existsSync(cssOutput) || srcMtime > statSync(cssOutput).mtimeMs;
    if (needsBuild) {
      compileSCSS(scssFile, cssOutput);
      componentChanges++;
    }
  } else {
    // Source removed → clean output css file
    removeOutput(path.join(outDir, `${componentName}.css`), "DEL");
    removeOutput(path.join(outDir, `${componentName}.css.map`), "DEL");
  }

  // --- JS ---
  const jsFile = path.join(srcDir, `${componentName}.js`);
  const jsOutput = path.join(outDir, `${componentName}.js`);
  if (existsSync(jsFile)) {
    if (isNewer(jsFile, jsOutput)) {
      copyFile(jsFile, jsOutput, "JS");
      componentChanges++;
    }
  } else {
    removeOutput(path.join(outDir, `${componentName}.js`), "DEL");
  }

  // --- .component.yml ---
  const ymlFile = path.join(srcDir, `${componentName}.component.yml`);
  const ymlOutput = path.join(outDir, `${componentName}.component.yml`);
  if (existsSync(ymlFile)) {
    if (isNewer(ymlFile, ymlOutput)) {
      copyFile(ymlFile, ymlOutput, "YML");
      componentChanges++;
    }
  } else {
    removeOutput(ymlOutput, "DEL");
  }

  // --- .twig ---
  const twigFile = path.join(srcDir, `${componentName}.twig`);
  const twigOutput = path.join(outDir, `${componentName}.twig`);
  if (existsSync(twigFile)) {
    if (isNewer(twigFile, twigOutput)) {
      copyFile(twigFile, twigOutput, "TWIG");
      componentChanges++;
    }
  } else {
    removeOutput(twigOutput, "DEL");
  }
}

if (componentChanges === 0) {
  console.log("  ● No component changes detected");
}

// ==============================
// Build Global Styles → dist/css/
// ==============================
console.log("\n📦 Building global styles...\n");

let globalChanges = 0;

// Compile the main styles.scss entry point
const globalScss = path.join(SRC_DIR, "styles.scss");
const globalCssOutput = path.join(DIST_DIR, "css", "styles.css");
if (existsSync(globalScss)) {
  // Rebuild if styles.scss or any shared scss is newer
  const srcMtime = Math.max(statSync(globalScss).mtimeMs, sharedLatest);
  if (
    !existsSync(globalCssOutput) ||
    srcMtime > statSync(globalCssOutput).mtimeMs
  ) {
    compileSCSS(globalScss, globalCssOutput);
    globalChanges++;
  }
} else {
  removeOutput(globalCssOutput, "DEL");
  removeOutput(`${globalCssOutput}.map`, "DEL");
}

// Also compile individual global SCSS sets for granular inclusion
const globalSets = [
  {
    input: path.join(SRC_DIR, "tokens", "tokens.scss"),
    output: path.join(DIST_DIR, "css", "tokens.css"),
  },
  {
    input: path.join(SRC_DIR, "foundation", "foundation.scss"),
    output: path.join(DIST_DIR, "css", "foundation.css"),
  },
  {
    input: path.join(SRC_DIR, "layout", "layout.scss"),
    output: path.join(DIST_DIR, "css", "layout.css"),
  },
  {
    input: path.join(SRC_DIR, "util", "util.scss"),
    output: path.join(DIST_DIR, "css", "util.css"),
  },
];

for (const { input, output } of globalSets) {
  if (existsSync(input)) {
    // Use the latest mtime of the entire subdirectory (partials may have changed)
    const dirLatest = latestScssMtime(path.dirname(input));
    if (!existsSync(output) || dirLatest > statSync(output).mtimeMs) {
      compileSCSS(input, output);
      globalChanges++;
    }
  } else {
    removeOutput(output, "DEL");
    removeOutput(`${output}.map`, "DEL");
  }
}

if (globalChanges === 0) {
  console.log("  ● No global style changes detected");
}

// ==============================
// Build SVG Sprite → dist/icons.svg
// ==============================
console.log("\n📦 Building SVG sprite...\n");

if (existsSync(ICONS_DIR)) {
  const svgFiles = readdirSync(ICONS_DIR).filter((f) => f.endsWith(".svg"));
  const spriteOutput = path.join(DIST_DIR, "icons.svg");

  if (svgFiles.length > 0) {
    // Check if any icon is newer than the sprite, or sprite doesn't exist
    const spriteExists = existsSync(spriteOutput);
    const spriteMtime = spriteExists ? statSync(spriteOutput).mtimeMs : 0;
    const anyIconNewer = svgFiles.some(
      (f) => statSync(path.join(ICONS_DIR, f)).mtimeMs > spriteMtime,
    );
    // Also rebuild if icon count changed (one was deleted)
    const needsRebuild = !spriteExists || anyIconNewer || FORCE_CLEAN;

    if (needsRebuild) {
      const symbols = svgFiles.map((file) => {
        const id = path.basename(file, ".svg");
        let svgContent = readFileSync(path.join(ICONS_DIR, file), "utf8");

        // Extract the inner content and viewBox from the SVG
        const viewBoxMatch = svgContent.match(/viewBox=["']([^"']+)["']/);
        const viewBox = viewBoxMatch ? viewBoxMatch[1] : "0 0 24 24";

        // Strip the outer <svg> wrapper, keep inner content
        svgContent = svgContent
          .replace(/<\?xml[^?]*\?>\s*/g, "")
          .replace(/<!--[\s\S]*?-->\s*/g, "")
          .replace(/<svg[^>]*>/i, "")
          .replace(/<\/svg>\s*$/i, "")
          .trim();

        return `  <symbol id="icon-${id}" viewBox="${viewBox}">\n    ${svgContent}\n  </symbol>`;
      });

      const sprite = `<svg xmlns="http://www.w3.org/2000/svg" style="display:none">\n${symbols.join("\n")}\n</svg>\n`;

      if (!existsSync(DIST_DIR)) {
        mkdirSync(DIST_DIR, { recursive: true });
      }
      writeFileSync(spriteOutput, sprite, "utf8");
      console.log(`  ✓ SVG:  ${svgFiles.length} icon(s) → dist/icons.svg`);
    } else {
      console.log("  ● No icon changes detected");
    }
  } else {
    // No SVG files → remove sprite if it exists
    removeOutput(spriteOutput, "DEL");
    console.log("  ⊘ No SVG files found in assets/icons/");
  }
} else {
  const spriteOutput = path.join(DIST_DIR, "icons.svg");
  removeOutput(spriteOutput, "DEL");
  console.log(
    "  ⊘ assets/icons/ directory not found, skipping sprite generation",
  );
}

console.log("\n✅ Build complete!\n");
