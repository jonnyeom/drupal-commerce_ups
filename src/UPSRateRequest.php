<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Ups\Rate;
use Ups\Entity\RateInformation;

/**
 * Class UPSRateRequest.
 *
 * @package Drupal\commerce_ups
 */
class UPSRateRequest extends UPSRequest {
  /**
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface*/
  protected $commerce_shipment;

  /**
   * @var array*/
  protected $configuration;

  /**
   * @var UPSShipment*/
  protected $ups_shipment;

  /**
   * Set the shipment for rate requests.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   A Drupal Commerce shipment entity.
   */
  public function setShipment(ShipmentInterface $commerce_shipment) {
    $this->commerce_shipment = $commerce_shipment;
  }

  /**
   * Fetch rates from the UPS API.
   */
  public function getRates() {
    // Validate a commerce shipment has been provided.
    if (empty($this->commerce_shipment)) {
      throw new \Exception('Shipment not provided');
    }

    $rates = [];
    $auth = $this->getAuth();

    $request = new Rate(
      $auth['access_key'],
      $auth['user_id'],
      $auth['password'],
      $this->useIntegrationMode()
    );

    try {
      // If requesting negotiated rates, we need the Shipper Number.
      $shipper_number = ($this->getRateType() && empty($this->configuration['api_information']['account_number'])) ? FALSE : $this->configuration['api_information']['account_number'];

      $ups_shipment = new UPSShipment($this->commerce_shipment, $shipper_number);
      $shipment = $ups_shipment->getShipment();

      // Enable negotiated rates, if enabled.
      if ($this->getRateType()) {
        $rate_information = new RateInformation();
        $rate_information->setNegotiatedRatesIndicator(TRUE);
        $rate_information->setRateChartIndicator(FALSE);
        $shipment->setRateInformation($rate_information);
      }

      // Shop Rates.
      $ups_rates = $request->shopRates($shipment);
    }
    catch (\Exception $ex) {
      \Drupal::logger('commerce_ups')->error($ex->getMessage());
      $ups_rates = [];
    }

    if (!empty($ups_rates->RatedShipment)) {
      foreach ($ups_rates->RatedShipment as $ups_rate) {
        $service_code = $ups_rate->Service->getCode();

        // Only add the rate if this service is enabled.
        if (!in_array($service_code, $this->configuration['services'])) {
          continue;
        }

        // Use negotiated rates if they were returned.
        if ($this->getRateType() && !empty($ups_rate->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue)) {
          $cost = $ups_rate->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue;
          $currency = $ups_rate->NegotiatedRates->NetSummaryCharges->GrandTotal->CurrencyCode;
        }

        // Otherwise, use the default rates.
        else {
          $cost = $ups_rate->TotalCharges->MonetaryValue;
          $currency = $ups_rate->TotalCharges->CurrencyCode;
        }

        $price = new Price((string) $cost, $currency);
        $service_name = $ups_rate->Service->getName();

        $shipping_service = new ShippingService(
          $service_code,
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
   * @return bool
   *   Returns true if negotiated rates should be requested.
   */
  public function getRateType() {
    return boolval($this->configuration['rate_options']['rate_type']);
  }

}
