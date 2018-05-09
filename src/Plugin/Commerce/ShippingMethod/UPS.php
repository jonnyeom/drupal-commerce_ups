<?php

namespace Drupal\commerce_ups\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_ups\UPSRequestInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @CommerceShippingMethod(
 *  id = "ups",
 *  label = @Translation("UPS"),
 *  services = {
 *    "_01" = @translation("UPS Next Day Air"),
 *    "_02" = @translation("UPS Second Day Air"),
 *    "_03" = @translation("UPS Ground"),
 *    "_07" = @translation("UPS Worldwide Express"),
 *    "_08" = @translation("UPS Worldwide Expedited"),
 *    "_11" = @translation("UPS Standard"),
 *    "_12" = @translation("UPS Three-Day Select"),
 *    "_13" = @translation("Next Day Air Saver"),
 *    "_14" = @translation("UPS Next Day Air Early AM"),
 *    "_54" = @translation("UPS Worldwide Express Plus"),
 *    "_59" = @translation("UPS Second Day Air AM"),
 *    "_65" = @translation("UPS Saver"),
 *    "_70" = @translation("UPS Access Point Economy")
 *   }
 * )
 */
class UPS extends ShippingMethodBase {
  /**
   * @var \Drupal\commerce_ups\UPSRateRequest
   */
  protected $ups_rate_service;

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $packageTypeManager
   *   The package type manager.
   * @param \Drupal\commerce_ups\UPSRequestInterface $ups_rate_request
   *   The rate request service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $packageTypeManager, UPSRequestInterface $ups_rate_request) {
    // Rewrite the service keys to be integers.
    $plugin_definition = $this->preparePluginDefinition($plugin_definition);

    parent::__construct($configuration, $plugin_id, $plugin_definition, $packageTypeManager);
    $this->ups_rate_service = $ups_rate_request;
    $this->ups_rate_service->setConfig($configuration);
  }

  /**
   * Prepares the service array keys to support integer values.
   *
   * See https://www.drupal.org/node/2904467 for more information.
   * todo: Remove once core issue has been addressed.
   *
   * @param array $plugin_definition
   *   The plugin definition provided to the class.
   *
   * @return array
   *   The prepared plugin definition.
   */
  private function preparePluginDefinition(array $plugin_definition) {
    // Cache and unset the parsed plugin definitions for services.
    $services = $plugin_definition['services'];
    unset($plugin_definition['services']);

    // Loop over each service definition and redefine them with
    // integer keys that match the UPS API.
    foreach ($services as $key => $service) {
      // Remove the "_" from the service key.
      $key_trimmed = str_replace('_', '', $key);
      $plugin_definition['services'][$key_trimmed] = $service;
    }

    return $plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('commerce_ups.ups_rate_request')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_information' => [
        'access_key' => '',
        'user_id' => '',
        'password' => '',
        'account_number' => '',
        'mode' => 'test',
      ],
      'rate_options' => [
        'rate_type' => 0,
      ] ,
      'options' => [
        'log' => [],
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $this->isConfigured() ? $this->t('Update your UPS API information.') : $this->t('Fill in your UPS API information.'),
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];

    $form['api_information']['access_key'] = [
      '#type' => 'textfield',
      '#title' => t('Access Key'),
      '#default_value' => $this->configuration['api_information']['access_key'],
      '#required' => TRUE,
    ];
    $form['api_information']['user_id'] = [
      '#type' => 'textfield',
      '#title' => t('User ID'),
      '#default_value' => $this->configuration['api_information']['user_id'],
      '#required' => TRUE,
    ];

    $form['api_information']['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $this->configuration['api_information']['password'],
      '#required' => TRUE,
    ];

    $form['api_information']['account_number'] = [
      '#type' => 'textfield',
      '#title' => t('Account Number'),
      '#description' => $this->t('This is only needed and used when requesting Negotiated Rates'),
      '#default_value' => $this->configuration['api_information']['account_number'],
      '#states' => [
        'required' => [
          ':input[name="plugin[0][target_plugin_configuration][ups][rate_options][rate_type]"]' => [
            'value' => 1,
          ]
        ],
      ],
    ];

    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use the test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];

    $form['rate_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate options'),
      '#description' => $this->t('Options to pass during rate requests.'),
    ];

    $form['rate_options']['rate_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Rate Type'),
      '#description' => $this->t('Choose between negotiated and standard rates.'),
      '#options' => [
        0 => $this->t('Standard Rates'),
        1 => $this->t('Negotiated Rates'),
      ],
      '#default_value' => $this->configuration['rate_options']['rate_type'],
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('UPS Options'),
      '#description' => $this->t('Additional options for UPS'),
    ];
    $form['options']['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['options']['log'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['access_key'] = $values['api_information']['access_key'];
      $this->configuration['api_information']['user_id'] = $values['api_information']['user_id'];
      $this->configuration['api_information']['password'] = $values['api_information']['password'];
      $this->configuration['api_information']['account_number'] = $values['api_information']['account_number'];
      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];
      $this->configuration['rate_options']['rate_type'] = $values['rate_options']['rate_type'];
      $this->configuration['options']['log'] = $values['options']['log'];

    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Calculates rates for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The rates.
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $rates = [];

    // Only attempt to collect rates if an address exits on the shipment.
    if (!$shipment->getShippingProfile()->get('address')->isEmpty()) {
      $this->ups_rate_service->setShipment($shipment);
      $rates = $this->ups_rate_service->getRates();
    }

    return $rates;
  }

  /**
   * Determine if we have the minimum information to connect to UPS.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {
    $api_information = $this->configuration['api_information'];

    return (
      !empty($api_information['access_key'])
      && !empty($api_information['user_id'])
      && !empty($api_information['password'])
    );
  }

}
