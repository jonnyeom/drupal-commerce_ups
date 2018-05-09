<?php

namespace Drupal\commerce_ups;

use CommerceGuys\Addressing\AddressInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\Address;
use Ups\Entity\PackagingType;
use Ups\Entity\ShipFrom;
use Ups\Entity\Shipment as APIShipment;
use Ups\Entity\Dimensions;

class UPSShipment extends UPSEntity {
  protected $shipment;
  protected $api_shipment;
  protected $shipper_number;

  /**
   * UPSShipment constructor.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   A commerce shipping shipment object.
   * @param bool|string $account_number
   *   The Account/Shipper Number or FALSE.
   */
  public function __construct(ShipmentInterface $shipment, $account_number = FALSE) {
    parent::__construct();
    $this->shipment = $shipment;
    $this->shipper_number = $account_number;
  }

  /**
   * Creates and returns a Ups API shipment object.
   *
   * @return \Ups\Entity\Shipment
   *   A Ups API shipment object.
   */
  public function getShipment() {
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
   */
  public function setShipTo(APIShipment $api_shipment) {
    /** @var AddressInterface $address */
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
    /** @var AddressInterface $address */
    $address = $this->shipment->getOrder()->getStore()->getAddress();
    $from_address = new Address();
    $from_address->setAddressLine1($address->getAddressLine1());
    $from_address->setAddressLine2($address->getAddressLine2());
    $from_address->setCity($address->getDependentLocality());
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
    $dimensions->setHeight($this->shipment->getPackageType()->getHeight()->getNumber());
    $dimensions->setWidth($this->shipment->getPackageType()->getWidth()->getNumber());
    $dimensions->setLength($this->shipment->getPackageType()->getLength()->getNumber());
    $unit = $this->getUnitOfMeasure($this->shipment->getPackageType()->getLength()->getUnit());
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
    $remote_id = $this->shipment->getPackageType()->getRemoteId();
    $attributes = new \stdClass();
    $attributes->Code = !empty($remote_id) && $remote_id != 'custom' ? $remote_id : PackagingType::PT_UNKNOWN;
    $ups_package->setPackagingType(new PackagingType($attributes));
  }

}
