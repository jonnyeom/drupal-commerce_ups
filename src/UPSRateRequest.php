<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Ups\Rate;
use Ups\Entity\RateInformation;


/**
 * Class UPSRateRequest
 * @package Drupal\commerce_ups
 */
class UPSRateRequest extends UPSRequest {
  /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface  */
  protected $commerce_shipment;

  /** @var array */
  protected $configuration;

  /** @var UPSShipment */
  protected $ups_shipment;

  /**
   * UPSRateRequest constructor.
   * @param array $configuration
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   */
  public function __construct(array $configuration, ShipmentInterface $commerce_shipment) {
    parent::__construct($configuration);
    $this->commerce_shipment = $commerce_shipment;
  }

  /**
   * Fetch rates from the UPS API.
   */
  public function getRates() {
    $rates = [];
    $auth = $this->getAuth();

    $request = new Rate(
      $auth['access_key'],
      $auth['user_id'],
      $auth['password'],
      $this->useIntegrationMode()
    );

    try {
      $ups_shipment = new UPSShipment($this->commerce_shipment);
      $shipment = $ups_shipment->getShipment();

      // Enable negotiated rates, if enabled.
      if ($this->getRateType()) {
        $rate_information = new RateInformation;
        $rate_information->setNegotiatedRatesIndicator(TRUE);
        $rate_information->setRateChartIndicator(FALSE);
        $shipment->setRateInformation($rate_information);
      }


      // Shop Rates
      $ups_rates = $request->shopRates($shipment);
    }
    catch (\Exception $ex) {
      // todo: handle exceptions by logging.
      $ups_rates = [];
    }

    if (!empty($ups_rates->RatedShipment)) {
      foreach ($ups_rates->RatedShipment as $ups_rate) {
        $service_code = $ups_rate->Service->getCode();

        // Only add the rate if this service is enabled.
        if (!in_array($service_code, $this->configuration['services'])) {
          continue;
        }

        $cost = $ups_rate->TotalCharges->MonetaryValue;
        $currency = $ups_rate->TotalCharges->CurrencyCode;
        $price = new Price((string) $cost, $currency);
        $service_name = $ups_rate->Service->getName();

        $shipping_service = new ShippingService(
          $service_name,
          $service_name
        );
        $rates[] = new ShippingRate(
          $service_code,
          $shipping_service,
          $price
        );
      }
    }
    return $rates;
  }

  /**
   * Gets the rate type: whether we will use negotiated rates or standard rates.
   *
   * @return mixed
   */
  public function getRateType() {
    return intval($this->configuration['rate_options']['rate_type']);
  }
}
