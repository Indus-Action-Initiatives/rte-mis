/**
 * @file twig-renderer.js
 * Shared utility for rendering Twig templates in Storybook.
 *
 * Registers custom Drupal-like Twig extensions:
 * - bem(block, element, modifiers, extra)  — BEM class generator function
 * - clean_class filter                     — sanitises a string for use as a CSS class
 * - without filter                         — removes keys from an object
 * - add_class / set_attribute helpers      — lightweight Attributes-like behaviour
 *
 * @example
 *   import template from './button.twig';
 *   import { renderTwig } from '../../../config/storybook-utils/twig-renderer';
 *
 *   export default {
 *     render: (args) => renderTwig(template, args),
 *   };
 */
import Twig from 'twig';

// Disable Twig.js caching so template changes are always picked up.
Twig.cache(false);

// ─────────────────────────────────────────────
// Drupal-like Attributes class (lightweight)
// ─────────────────────────────────────────────
class DrupalAttributes {
  constructor() {
    this._classes = [];
    this._attributes = {};
  }

  addClass(...args) {
    const flat = args.flat(Infinity).filter(Boolean);
    this._classes.push(...flat);
    return this;
  }

  removeClass(...args) {
    const remove = new Set(args.flat(Infinity));
    this._classes = this._classes.filter((c) => !remove.has(c));
    return this;
  }

  setAttribute(name, value) {
    this._attributes[name] = value;
    return this;
  }

  removeAttribute(name) {
    delete this._attributes[name];
    return this;
  }

  toString() {
    const parts = [];
    if (this._classes.length) {
      parts.push(`class="${[...new Set(this._classes)].join(' ')}"`);
    }
    for (const [k, v] of Object.entries(this._attributes)) {
      parts.push(`${k}="${v}"`);
    }
    return parts.join(' ');
  }
}

// ─────────────────────────────────────────────
// Register custom Twig extensions
// ─────────────────────────────────────────────

/**
 * bem(block, element, modifiers, extra)
 *
 * Generates BEM-style class attributes.
 *
 * Usage in twig:
 *   <div {{ bem('card') }}>
 *   <div {{ bem('card', 'media') }}>
 *   <div {{ bem('card', '', ['featured', 'large']) }}>
 *   <div {{ bem('card', 'title', ['large'], ['u-mt-1']) }}>
 */
Twig.extendFunction('bem', (block, element, modifiers, extra) => {
  const classes = [];

  if (element) {
    classes.push(`${block}__${element}`);
    if (Array.isArray(modifiers)) {
      modifiers.filter(Boolean).forEach((mod) => {
        classes.push(`${block}__${element}--${mod}`);
      });
    }
  } else {
    classes.push(block);
    if (Array.isArray(modifiers)) {
      modifiers.filter(Boolean).forEach((mod) => {
        classes.push(`${block}--${mod}`);
      });
    }
  }

  if (Array.isArray(extra)) {
    classes.push(...extra.filter(Boolean));
  }

  return `class="${classes.join(' ')}"`;
});

/**
 * clean_class filter — mirrors Drupal\Component\Utility\Html::getClass()
 * Converts a string to a valid CSS class name.
 */
Twig.extendFilter('clean_class', (value) => {
  if (typeof value !== 'string') return '';
  return value
    .toLowerCase()
    .replace(/[^a-z0-9_-]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
});

/**
 * without filter — removes specified keys from an object / attributes.
 * Usage: {{ content|without('field_image', 'field_tags') }}
 */
Twig.extendFilter('without', (obj, ...keys) => {
  if (!obj || typeof obj !== 'object') return obj;
  const flatKeys = keys.flat(Infinity);
  const copy = { ...obj };
  flatKeys.forEach((k) => delete copy[k]);
  return copy;
});

/**
 * Render a Twig template string with the given data.
 *
 * Automatically injects a lightweight `attributes` object if none is provided,
 * so Drupal-style `{{ attributes }}` calls work in Storybook.
 *
 * @param {string} templateSource - Raw Twig template string.
 * @param {Object} data - Variables to pass into the template.
 * @returns {HTMLElement} A DOM element wrapping the rendered HTML.
 */
export function renderTwig(templateSource, data = {}) {
  // Inject a fresh DrupalAttributes instance if not already provided.
  const context = {
    attributes: new DrupalAttributes(),
    ...data,
  };

  const template = Twig.twig({ data: templateSource });
  const html = template.render(context);

  const wrapper = document.createElement('div');
  wrapper.innerHTML = html.trim();

  // Return the single root element if there's only one, otherwise the wrapper.
  return wrapper.children.length === 1 ? wrapper.firstElementChild : wrapper;
}

