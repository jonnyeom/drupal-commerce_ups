<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\Address;
use Ups\Entity\PackagingType;
use Ups\Entity\ShipFrom;
use Ups\Entity\Shipment as APIShipment;
use Ups\Entity\Dimensions;

class UPSShipment extends UPSEntity implements UPSShipmentInterface {

  /**
   * The commerce shipment.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * The UPS API shipment entity.
   *
   * @var \Ups\Entity\Shipment
   */
  protected $api_shipment;

  /**
   * The Shipper/Account Number, used for negotiated rates.
   *
   * @var bool|string
   */
  protected $shipper_number;

  /**
   * The shipping method.
   *
   * @var \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface
   */
  protected $shipping_method;

  /**
   * Creates and returns a Ups API shipment object.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method
   *   The shipping method.
   * @param bool|string $account_number
   *   The Account/Shipper Number or FALSE.
   *
   * @return \Ups\Entity\Shipment
   *   A Ups API shipment object.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method, $account_number = FALSE) {
    $this->shipment = $shipment;
    $this->shipping_method = $shipping_method;
    $this->shipper_number = $account_number;
    $api_shipment = new APIShipment();
    $this->setShipTo($api_shipment);
    $this->setShipFrom($api_shipment);
    $this->setPackage($api_shipment);
    $this->setShipperNumber($api_shipment);
    return $api_shipment;
  }

  /**
   * Sets the ship to for a given shipment.
   *
   * @param \Ups\Entity\Shipment $api_shipment
   *   A Ups API shipment object.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function setShipTo(APIShipment $api_shipment) {
    /** @var \CommerceGuys\Addressing\AddressInterface $address */
    $address = $this->shipment->getShippingProfile()->get('address')->first();
    $to_address = new Address();
    $to_address->setAddressLine1($address->getAddressLine1());
    $to_address->setAddressLine2($address->getAddressLine2());
    $to_address->setCity($address->getLocality());
    $to_address->setCountryCode($address->getCountryCode());
    $to_address->setStateProvinceCode($address->getAdministrativeArea());
    $to_address->setPostalCode($address->getPostalCode());
    $api_shipment->getShipTo()->setAddress($to_address);
  }

  /**
   * Sets the ship from for a given shipment.
   *
   * @param \Ups\Entity\Shipment $api_shipment
   *   A Ups API shipment object.
   */
  public function setShipFrom(APIShipment $api_shipment) {
    /** @var \CommerceGuys\Addressing\AddressInterface $address */
    $address = $this->shipment->getOrder()->getStore()->getAddress();
    $from_address = new Address();
    $from_address->setAddressLine1($address->getAddressLine1());
    $from_address->setAddressLine2($address->getAddressLine2());
    $from_address->setCity($address->getLocality());
    $from_address->setCountryCode($address->getCountryCode());
    $from_address->setStateProvinceCode($address->getAdministrativeArea());
    $from_address->setPostalCode($address->getPostalCode());
    $ship_from = new ShipFrom();
    $ship_from->setAddress($from_address);
    $api_shipment->setShipFrom($ship_from);
  }

  /**
   * Sets the Shipper Number.
   *
   * This is only set if Negotiated Rates are requested and the Account
   * Number is configured.
   *
   * @param \Ups\Entity\Shipment $api_shipment
   *   A Ups API shipment object.
   */
  public function setShipperNumber(APIShipment $api_shipment) {
    if (!$this->shipper_number) {
      return;
    }
    $shipper = $api_shipment->getShipper();
    $shipper->setShipperNumber($this->shipper_number);
    $api_shipment->setShipper($shipper);
  }

  /**
   * Sets the package for a given shipment.
   *
   * @param \Ups\Entity\Shipment $api_shipment
   *   A Ups API shipment object.
   */
  public function setPackage(APIShipment $api_shipment) {
    $package = new UPSPackage();
    $this->setDimensions($package);
    $this->setWeight($package);
    $this->setPackagingType($package);
    $api_shipment->addPackage($package);
  }

  /**
   * Package dimension setter.
   *
   * @param \Ups\Entity\Package $ups_package
   *   A Ups API package object.
   */
  public function setDimensions(UPSPackage $ups_package) {
    $dimensions = new Dimensions();

    $dimensions->setHeight($this->getPackageType()->getHeight()->getNumber());
    $dimensions->setWidth($this->getPackageType()->getWidth()->getNumber());
    $dimensions->setLength($this->getPackageType()->getLength()->getNumber());
    $unit = $this->getUnitOfMeasure($this->getPackageType()->getLength()->getUnit());
    $dimensions->setUnitOfMeasurement($this->setUnitOfMeasurement($unit));
    $ups_package->setDimensions($dimensions);
  }

  /**
   * Define the package weight.
   *
   * @param \Ups\Entity\Package $ups_package
   *   A package object from the Ups API.
   */
  public function setWeight(UPSPackage $ups_package) {
    $ups_package_weight = $ups_package->getPackageWeight();
    $ups_package_weight->setWeight($this->shipment->getWeight()->getNumber());
    $unit = $this->getUnitOfMeasure($this->shipment->getWeight()->getUnit());
    $ups_package_weight->setUnitOfMeasurement($this->setUnitOfMeasurement($unit));
  }

  /**
   * Sets the package type for a UPS package.
   *
   * @param \Ups\Entity\Package $ups_package
   *   A Ups API package entity.
   */
  public function setPackagingType(UPSPackage $ups_package) {
    $remote_id = $this->getPackageType()->getRemoteId();
    $attributes = new \stdClass();
    $attributes->Code = !empty($remote_id) && $remote_id != 'custom' ? $remote_id : PackagingType::PT_UNKNOWN;
    $ups_package->setPackagingType(new PackagingType($attributes));
  }

  /**
   * Determine the package type for the shipment.
   *
   * @return \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface
   *   The package type.
   */
  protected function getPackageType() {
    // If the package is set on the shipment, use that.
    if (!empty($this->shipment->getPackageType())) {
      return $this->shipment->getPackageType();
    }
    // Or use the default package type for the shipping method.
    else {
      return $this->shipping_method->getDefaultPackageType();
    }
  }

}
