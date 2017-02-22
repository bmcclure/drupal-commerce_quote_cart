<?php

namespace Drupal\commerce_quote_cart;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Price;

/**
 * Provides an order processor that clears the price of quote items.
 */
class QuoteOrderProcessor implements OrderProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    foreach ($order->getItems() as $order_item) {
      if (QuoteCartHelper::isQuoteItem($order_item)) {
        $order_item->setUnitPrice(new Price("0.00", 'USD'));
      }
    }
  }
}
