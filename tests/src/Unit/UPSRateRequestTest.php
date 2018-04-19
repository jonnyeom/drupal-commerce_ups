<?php

namespace Drupal\Tests\commerce_ups\Unit;

use Drupal\commerce_ups\UPSRateRequest;
use Drupal\physical\WeightUnit;

/**
 * Class UPSRateRequestTest.
 *
 * @coversDefaultClass \Drupal\commerce_ups\UPSRateRequest
 * @group commerce_ups
 */
class UPSRateRequestTest extends UPSUnitTestBase {
  /**
   * @var \Drupal\commerce_ups\UPSRateRequest
   */
  protected $rate_request;

  /**
   * Set up requirements for test.
   */
  public function setUp() {
    parent::setUp();
    $this->rate_request = new UPSRateRequest();
    $this->rate_request->setConfig($this->configuration);
  }

  /**
   * Test getAuth response.
   *
   * @covers ::getAuth
   */
  public function testAuth() {
    $auth = $this->rate_request->getAuth();
    $this->assertEquals($auth['access_key'], $this->configuration['api_information']['access_key']);
    $this->assertEquals($auth['user_id'], $this->configuration['api_information']['user_id']);
    $this->assertEquals($auth['password'], $this->configuration['api_information']['password']);
  }

  /**
   * Test useIntegrationMode().
   *
   * @covers ::useIntegrationMode
   */
  public function testIntegrationMode() {
    $mode = $this->rate_request->useIntegrationMode();
    $this->assertEquals(TRUE, $mode);
  }

  /**
   * Test getRateType().
   *
   * @covers ::getRateType
   */
  public function testRateType() {
    $type = $this->rate_request->getRateType();
    $this->assertEquals(TRUE, $type);
  }

  /**
   * Test rate requests return valid rates.
   *
   * @param string $weight_unit
   *   Weight unit.
   *
   * @covers ::getRates
   *
   * @dataProvider weightUnitsDataProvider
   */
  public function testRateRequest($weight_unit) {
    // Create a mock commerce shipment object.
    $shipment = $this->mockShipment($weight_unit);

    // Invoke the rate request object.
    $this->rate_request->setShipment($shipment);
    $rates = $this->rate_request->getRates();

    // Make sure at least one rate was returned.
    $this->assertArrayHasKey(0, $rates);

    foreach ($rates as $rate) {
      /* @var \Drupal\commerce_shipping\ShippingRate $rate */
      $this->assertInstanceOf('Drupal\commerce_shipping\ShippingRate', $rate);
      $this->assertInstanceOf('Drupal\commerce_price\Price', $rate->getAmount());
      $this->assertGreaterThan(0, $rate->getAmount()->getNumber());
      $this->assertEquals($rate->getAmount()->getCurrencyCode(), 'USD');
      $this->assertNotEmpty($rate->getService()->getLabel());
    }
  }

  /**
   * Data provider for testRateRequest()
   *
   * @return array
   *   List of measurement units.
   */
  public function weightUnitsDataProvider() {
    return [
      [WeightUnit::GRAM],
      [WeightUnit::KILOGRAM],
      [WeightUnit::OUNCE],
      [WeightUnit::POUND],
    ];
  }

}
