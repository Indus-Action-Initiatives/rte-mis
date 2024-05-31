<?php

namespace Drupal\rte_mis_mail\Element;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\Element\MobileNumber;

/**
 * Provides a form input element for entering an email address.
 *
 * Properties:
 * - #mobile_number
 *   - allowed_countries
 *   - verify
 *   - tfa
 *   - message
 *   - placeholder
 *   - token_data.
 *
 * Example usage:
 * @code
 * $form['mobile_number'] = array(
 *   '#type' => 'mobile_number',
 *   '#title' => $this->t('Mobile Number'),
 * );
 *
 * @end
 *
 * @FormElement("mobile_number")
 */
class RteMobileNumber extends MobileNumber {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [$this, 'rteMobileNumberProcess'],
      ],
      '#element_validate' => [
        [$this, 'mobileNumberValidate'],
      ],
      '#mobile_number' => [],
    ];
  }

  /**
   * Mobile number element process callback.
   *
   * @param array $element
   *   Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form.
   *
   * @return array
   *   Processed array.
   */
  public function rteMobileNumberProcess(array $element, FormStateInterface $form_state, array $complete_form) {
    $element = parent::mobileNumberProcess($element, $form_state, $complete_form);
    // Update the ajax call function and add the logic to show the timer &
    // disable the button.
    $element['send_verification']['#ajax']['callback'] = 'Drupal\rte_mis_mail\Element\RteMobileNumber::verifyAjax';
    $element['send_verification']['#ajax']['progress'] = ['type' => 'fullscreen'];
    $element['send_verification']['#value'] = $this->t('Send Verification Code');
    $element['verify']['#ajax']['progress'] = ['type' => 'fullscreen'];
    return $element;
  }

  /**
   * Mobile number element ajax callback.
   *
   * @param array $complete_form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public static function verifyAjax($complete_form, FormStateInterface $form_state) {
    $response = parent::verifyAjax($complete_form, $form_state);

    // Before doing anything, Just check if there are any errors.
    $errors = $form_state->getErrors();
    if (empty($errors)) {
      // Check the type of operation and set the settings for resend option.
      $operation = $form_state->getTriggeringElement();
      if (!empty($operation)
        && !empty($operation['#name'])
        && $operation['#name'] === 'field_phone_number__0__send_verification') {
        $settings['rte_mis_mail']['mobile_resend_time'] = time();
        $response->addCommand(new SettingsCommand($settings));

        // Disable the verification button after sending the OTP.
        $element = static::getTriggeringElementParent($complete_form, $form_state);
        if (array_key_exists('send_verification', $element)) {
          $element['send_verification']['#attributes']['disabled'] = 'disabled';
          // Add a special class to identify the button in js.
          $element['send_verification']['#attributes']['class'][] = 'mobile-send-btn';
          $response->addCommand(new ReplaceCommand(':input[name="field_phone_number__0__send_verification"]', $element['send_verification']));
        }
      }
    }

    return $response;
  }

}
