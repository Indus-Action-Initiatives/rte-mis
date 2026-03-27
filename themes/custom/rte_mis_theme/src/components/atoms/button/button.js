/**
 * @file button.js
 * JavaScript behavior for the Button component (Drupal behavior).
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.button = {
    attach(context) {
      const buttons = context.querySelectorAll('.c-button');
      buttons.forEach((_button) => {
        // Add any progressive enhancement here.
      });
    },
  };
})(Drupal);
