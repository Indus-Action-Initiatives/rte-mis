<?php

namespace Drupal\rte_mis_smsgateway_msg91\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Plugin\SmsGatewayPluginBase;

/**
 * Provides a SMS Gateway for MSG91.
 *
 * @SmsGateway(
 *   id = "msg91_custom",
 *   label = @Translation("MSG91 Custom Gateway"),
 *   description = @Translation("Sends SMS via MSG91 API.")
 * )
 */
class Msg91Gateway extends SmsGatewayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'authkey' => '',
      'sender' => '',
      'route' => '4',
      'country' => '91',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['authkey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MSG91 Auth Key'),
      '#default_value' => $this->configuration['authkey'],
      '#required' => TRUE,
    ];
    $form['sender'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sender ID'),
      '#default_value' => $this->configuration['sender'],
      '#required' => TRUE,
    ];
    $form['route'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Route'),
      '#default_value' => $this->configuration['route'],
    ];
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country Code'),
      '#default_value' => $this->configuration['country'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValues();

    $this->configuration['authkey'] = $values['authkey'];
    $this->configuration['sender'] = $values['sender'];
    $this->configuration['route'] = $values['route'];
    $this->configuration['country'] = $values['country'];
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
    $mobile = $sms->getRecipients()[0];
    $text = $sms->getMessage();

    $authkey = $this->configuration['authkey'];
    $sender = $this->configuration['sender'];
    $route = $this->configuration['route'];
    $country = $this->configuration['country'];

    $url = 'https://api.msg91.com/api/v5/flow/';

    $payload = [
      'sender' => $sender,
      'route' => $route,
      'country' => $country,
      'sms' => [
        [
          'message' => $text,
          'to' => [$mobile],
        ],
      ],
    ];

    try {
      $client = \Drupal::httpClient();
      $response = $client->post($url, [
        'json' => $payload,
        'headers' => [
          'authkey' => $authkey,
          'Content-Type' => 'application/json',
        ],
        'timeout' => 15,
      ]);

      $status_code = $response->getStatusCode();
      $body = json_decode($response->getBody(), TRUE);

      if ($status_code == 200) {
        \Drupal::logger('rte_mis_smsgateway_msg91')->info('SMS sent successfully to @mobile. Response: @resp', [
          '@mobile' => $mobile,
          '@resp' => json_encode($body),
        ]);
        // ✅ Return a proper SmsMessageResult object
        return new SmsMessageResult(TRUE, 'Message sent successfully.');
      }
      else {
        \Drupal::logger('rte_mis_smsgateway_msg91')->error('Failed sending SMS to @mobile. Response: @resp', [
          '@mobile' => $mobile,
          '@resp' => json_encode($body),
        ]);
        return new SmsMessageResult(FALSE, 'Failed to send message. MSG91 response invalid.');
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('rte_mis_smsgateway_msg91')->error('MSG91 Exception: @msg', ['@msg' => $e->getMessage()]);
      return new SmsMessageResult(FALSE, 'Exception: ' . $e->getMessage());
    }

  }

}
