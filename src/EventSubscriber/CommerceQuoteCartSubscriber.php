<?php

namespace Drupal\commerce_quote_cart\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_fedex\Event\CommerceFedExEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_quote_cart\QuoteCartHelper;
use Drupal\commerce_shipping\Event\BeforePackEvent;
use Drupal\commerce_shipping\Event\CommerceShippingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;

class CommerceQuoteCartSubscriber implements EventSubscriberInterface {

  var $quoteField = 'field_quote';
  var $purchaseField = 'field_purchase';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[CartEvents::ORDER_ITEM_COMPARISON_FIELDS][] = ['onOrderItemComparisonFields'];
    $events[OrderEvents::ORDER_ITEM_PRESAVE][] = ['onOrderItemPresave'];
    $events[OrderEvents::ORDER_ITEM_CREATE][] = ['onOrderItemCreate'];
    $events[CommerceShippingEvents::BEFORE_PACK][] = ['onBeforePack'];
    $events[CommerceFedExEvents::BEFORE_PACK][] = ['onBeforePackFedEx'];
    $events[CartEvents::CART_ENTITY_ADD][] = ['onCartEntityAdd'];
    $events[CartEvents::CART_ORDER_ITEM_UPDATE][] = ['onCartOrderItemUpdate'];
    $events[CartEvents::CART_ORDER_ITEM_REMOVE][] = ['onCartOrderItemRemove'];

    return $events;
  }

  public function onCartEntityAdd(CartEntityAddEvent $event) {
    $cart = $event->getCart();
    $this->setOrderType($cart);
    $this->cleanOrderInfo($cart);
  }

  public function onCartOrderItemUpdate(CartOrderItemUpdateEvent $event) {
    $cart = $event->getCart();
    $this->setOrderType($cart);
    $this->cleanOrderInfo($event->getCart());
  }

  public function onCartOrderItemRemove(CartOrderItemRemoveEvent $event) {
    $cart = $event->getCart();
    $this->setOrderType($cart);
    $this->cleanOrderInfo($event->getCart());
  }

  public function setOrderType(OrderInterface $order) {
    if ($order->hasField($this->quoteField)) {
      $order->get($this->quoteField)->value = QuoteCartHelper::isQuoteCart($order);
    }

    if ($order->hasField($this->purchaseField)) {
      $order->get($this->purchaseField)->value = QuoteCartHelper::isPurchaseCart($order);
    }
  }

  public function cleanOrderInfo(OrderInterface $cart) {
    // Don't do this if it's an order.
    if (QuoteCartHelper::isPurchaseCart($cart) || is_null($cart->getTotalPrice()) || $cart->getTotalPrice()->isZero()) {
      return;
    }

    $cart->clearAdjustments();
  }

  public function onBeforePack(BeforePackEvent $event) {
    $event->setOrderItems($this->filterQuoteItems($event->getOrder(), $event->getOrderItems()));
  }

  public function onBeforePackFedEx(\Drupal\commerce_fedex\Event\BeforePackEvent $event) {
    $event->setOrderItems($this->filterQuoteItems($event->getOrder(), $event->getOrderItems()));
  }

  protected function filterQuoteItems(OrderInterface $order, array $items) {
    // Only remove quote items from purchases, as we need as least one shippable item.
    if (QuoteCartHelper::isPurchaseCart($order)) {
      foreach ($items as $id => $orderItem) {
        if ($orderItem->hasField('field_quote') && $orderItem->get('field_quote')->value) {
          unset($items[$id]);
        }
      }
    }

    return $items;
  }

  public function onOrderItemComparisonFields(OrderItemComparisonFieldsEvent $event) {
    $fields = $event->getComparisonFields();

    $fields[] = $this->quoteField;

    $event->setComparisonFields($fields);
  }

  public function onOrderItemPresave(OrderItemEvent $event) {
    $orderItem = $event->getOrderItem();

    if ($orderItem->hasField($this->quoteField) && $orderItem->get($this->quoteField)->value) {
      $orderItem->setUnitPrice(new Price("0.00", 'USD'));
    }
  }

  public function onOrderItemCreate(OrderItemEvent $event) {
    $event->getOrderItem()->set($this->quoteField, ['value' => "0"]);
  }
}
