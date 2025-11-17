<?php

namespace Drupal\smsgateway_msg91_custom\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for sending SMS messages using MSG91 API.
 */
class Msg91SMSService {

  /**
   * The configuration object for MSG91 settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * The HTTP client for making requests.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a MSG91SMSService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory->get('smsgateway_msg91_custom.settings');
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('smsgateway_msg91_custom');
  }

  /**
   * Sends a message using MSG91 API.
   *
   * @param string $mobile_number
   *   The recipient mobile number.
   * @param string $message
   *   The message text.
   * @param string $template_id
   *   The MSG91 flow/template ID.
   * @param array $tempVar
   *   Additional template variables.
   *
   * @return array|bool
   *   The decoded response array or FALSE on failure.
   */
  public function sendMessage($mobile_number, $message = '', $template_id = '', array $tempVar = []) {
    $api_url = $this->configFactory->get('auth_url');
    $auth_key = $this->configFactory->get('auth_key');
    $template_id = $template_id ?: $this->configFactory->get('template_id');
    $country = $this->configFactory->get('country_code') ?: '91';

    if (empty($tempVar['mobiles'])) {
      $tempVar['mobiles'] = $mobile_number;
    }

    if (!preg_match('/^\d+$/', $tempVar['mobiles'])) {
      $this->logger->error('Invalid mobile format: @num', ['@num' => $tempVar['mobiles']]);
      return FALSE;
    }

    // Ensure proper formatting.
    if (substr($tempVar['mobiles'], 0, strlen($country)) !== $country) {
      $tempVar['mobiles'] = $country . $tempVar['mobiles'];
    }

    $payload = [
      'flow_id' => $template_id,
      'recipients' => [$tempVar],
    ];

    try {
      $response = $this->httpClient->post($api_url, [
        'headers' => [
          'authkey' => $auth_key,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($payload),
      ]);

      $decoded = json_decode($response->getBody(), TRUE);
      $this->logger->info('MSG91 Response: @response', ['@response' => json_encode($decoded)]);
      return $decoded;
    }
    catch (RequestException $e) {
      $this->logger->error('MSG91 API error: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

}
