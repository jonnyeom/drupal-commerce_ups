<?php

namespace Drupal\Tests\commerce_ups\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\physical\Length;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Ups\Entity\PackagingType;
use CommerceGuys\Addressing\Address;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Class UPSUnitTestBase.
 *
 * @package Drupal\Tests\commerce_ups\Unit
 */
abstract class UPSUnitTestBase extends UnitTestCase {
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->configuration = [
      'api_information' => [
        'access_key' => '5D3096C1F3C7953D',
        'user_id' => 'drupal_commerce',
        'password' => 'DCDev101!',
        'mode' => 'test',
      ],
      'rate_options' => [
        'rate_type' => TRUE,
      ],
      'services' => [
        "01" => "01",
        "02" => "02",
        "03" => "03",
        "07" => "07",
        "08" => "08",
        "11" => "11",
        "12" => "12",
        "13" => "13",
        "14" => "14",
        "54" => "54",
        "59" => "59",
        "65" => "65",
        "70" => "70",
      ],
    ];
  }

  /**
   * Creates a mock Drupal Commerce shipment entity.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   A mocked commerce shipment object.
   */
  public function mockShipment($weight_unit = 'lb') {
    // Mock a Drupal Commerce Order and associated objects.
    $order = $this->prophesize(OrderInterface::class);
    $store = $this->prophesize(StoreInterface::class);

    // Mock the getAddress method to return a US address.
    $store->getAddress()->willReturn(new Address('US', 'NC', 'Asheville', '', 28806, '', '1025 Brevard Rd'));
    $order->getStore()->willReturn($store->reveal());

    // Mock a Drupal Commerce shipment and associated objects.
    $shipment = $this->prophesize(ShipmentInterface::class);
    $profile = $this->prophesize(ProfileInterface::class);
    $address_list = $this->prophesize(FieldItemListInterface::class);

    // Mock the address list to return a US address.
    $address_list->first()->willReturn(new Address('US', 'NC', 'Asheville', '', 28806, '', '1025 Brevard Rd'));
    $profile->get('address')->willReturn($address_list->reveal());
    $shipment->getShippingProfile()->willReturn($profile->reveal());
    $shipment->getOrder()->willReturn($order->reveal());

    // Mock a package type including dimensions and remote id.
    $package_type = $this->prophesize(PackageTypeInterface::class);
    $package_type->getHeight()->willReturn(new Length(10, 'in'));
    $package_type->getLength()->willReturn(new Length(10, 'in'));
    $package_type->getWidth()->willReturn(new Length(3, 'in'));
    $package_type->getRemoteId()->willReturn(PackagingType::PT_UNKNOWN);

    // Mock the shipments weight and package type.
    $shipment->getWeight()->willReturn(new Weight(10, $weight_unit));
    $shipment->getPackageType()->willReturn($package_type->reveal());

    // Return the mocked shipment object.
    return $shipment->reveal();
  }

}
