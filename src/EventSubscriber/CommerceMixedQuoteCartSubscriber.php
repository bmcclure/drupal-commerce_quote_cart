<?php

namespace Drupal\commerce_mixed_quote_cart\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Drupal\commerce_product\Event\ProductVariationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;

class CommerceMixedQuoteCartSubscriber implements EventSubscriberInterface {

  var $fieldName = 'field_quote';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[CartEvents::ORDER_ITEM_COMPARISON_FIELDS][] = ['onOrderItemComparisonFields'];
    $events[OrderEvents::ORDER_ITEM_PRESAVE][] = ['onOrderItemPresave'];
    $events[OrderEvents::ORDER_ITEM_CREATE][] = ['onOrderItemCreate'];
    $events[ProductEvents::PRODUCT_VARIATION_AJAX_CHANGE][] = ['onProductVariationAjaxChange'];

    return $events;
  }

  public function onProductVariationAjaxChange(ProductVariationAjaxChangeEvent $event) {
    // TODO: Add AJAX event to reload main product variation
    // (Currently only replaces Add to Cart block info)
  }

  public function onOrderItemComparisonFields(OrderItemComparisonFieldsEvent $event) {
    $fields = $event->getComparisonFields();

    $fields[] = $this->fieldName;

    $event->setComparisonFields($fields);
  }

  public function onOrderItemPresave(OrderItemEvent $event) {
    $orderItem = $event->getOrderItem();

    if ($orderItem->hasField($this->fieldName) && $orderItem->get($this->fieldName)->value) {
      $orderItem->setUnitPrice(new Price("0.00", 'USD'));
    }
  }

  public function onOrderItemCreate(OrderItemEvent $event) {
    $event->getOrderItem()->set($this->fieldName, ['value' => "0"]);
  }
}
