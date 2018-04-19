<?php

namespace Drupal\commerce_ups;

use CommerceGuys\Addressing\AddressInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\physical\Length;
use Drupal\physical\LengthUnit;
use Drupal\physical\WeightUnit;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\Address;
use Ups\Entity\PackagingType;
use Ups\Entity\ShipFrom;
use Ups\Entity\Shipment as APIShipment;
use Ups\Entity\Dimensions;
use Ups\Entity\UnitOfMeasurement;

class UPSShipment extends UPSEntity {
  protected $shipment;
  protected $api_shipment;

  /**
   * UPSShipment constructor.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   A commerce shipping shipment object.
   */
  public function __construct(ShipmentInterface $shipment) {
    parent::__construct();
    $this->shipment = $shipment;
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
    $length = $this->shipment->getPackageType()->getLength();
    $height = $this->shipment->getPackageType()->getHeight();
    $width = $this->shipment->getPackageType()->getWidth();
    // Convert Units if it is not supported by UPS API.
    if (!$unit = $this->getUnitOfMeasure($length->getUnit())) {
      // @Todo: Temorarily hard coded. This default length unit should be configurable.
      $unit = LengthUnit::INCH;
      $length = $length->convert($unit);
      $height = $height->convert($unit);
      $width = $width->convert($unit);
      $unit = $this->getUnitOfMeasure($unit);
    }

    // Rounding Units since decimals are not allowed by the UPS API.
    $length = round($length->getNumber()) ?: 1;
    $height = round($height->getNumber()) ?: 1;
    $width = round($width->getNumber()) ?: 1;

    $dimensions->setHeight($length);
    $dimensions->setWidth($height);
    $dimensions->setLength($width);
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
    $weight = $this->shipment->getWeight();
    $valid_unit = $this->getValidWeightUnit($ups_package);

    // Convert weight measurement unit if it's not supported by UPS API or does
    // not match dimensions unit.
    if ($valid_unit && $weight->getUnit() !== $valid_unit) {
      $weight = $weight->convert($valid_unit);
    }

    $ups_package_weight->setWeight($weight->getNumber());
    $ups_package_weight->setUnitOfMeasurement($this->setUnitOfMeasurement($this->getUnitOfMeasure($weight->getUnit())));
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

  /**
   * Get valid weight measurement unit for a given package.
   *
   * @param \Ups\Entity\Package $ups_package
   *   A package object from the Ups API.
   *
   * @return string|null
   *   Valid measurement unit or NULL.
   */
  public function getValidWeightUnit(UPSPackage $ups_package) {
    $ups_weight_unit_code = $ups_package->getPackageWeight()
      ->getUnitOfMeasurement()
      ->getCode();

    $map = [
      UnitOfMeasurement::UOM_KGS => WeightUnit::KILOGRAM,
      UnitOfMeasurement::UOM_LBS => WeightUnit::POUND,
    ];

    return isset($map[$ups_weight_unit_code]) ? $map[$ups_weight_unit_code] : NULL;
  }

}
