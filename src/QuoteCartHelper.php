<?php

namespace Drupal\commerce_quote_cart;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

class QuoteCartHelper {
  public static function hasQuoteCart() {
    /** @var \Drupal\commerce_cart\CartProvider $cartProvider */
    $cartProvider = \Drupal::getContainer()->get('commerce_cart.cart_provider');

    $carts = $cartProvider->getCarts();

    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });

    $hasQuoteCart = FALSE;

    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    foreach ($carts as $cart) {
      if (self::isQuoteCart($cart)) {
        $hasQuoteCart = TRUE;
        break;
      }
    }

    return $hasQuoteCart;
  }

  public static function isMixedCart(OrderInterface $cartOrder) {
    return self::isQuoteCart($cartOrder) && self::isPurchaseCart($cartOrder);
  }

  public static function isPurchaseCart(OrderInterface $cartOrder) {
    $isPurchaseCart = FALSE;

    $field = 'field_quote';

    /** @var OrderItemInterface $item */
    foreach ($cartOrder->getItems() as $item) {
      if (!$item->hasField($field) || !$item->get($field)->value) {
        $isPurchaseCart = TRUE;
        break;
      }
    }

    return $isPurchaseCart;
  }

  public static function isQuoteCart(OrderInterface $cartOrder) {
    $isQuoteCart = FALSE;

    $field = 'field_quote';

    foreach ($cartOrder->getItems() as $item) {
      if ($item->hasField($field) && $item->get($field)->value) {
        $isQuoteCart = TRUE;
        break;
      }
    }

    return $isQuoteCart;
  }

  public static function convertToQuote(OrderInterface $cartOrder) {
    $field = 'field_quote';
    $save = FALSE;

    foreach ($cartOrder->getItems() as $item) {
      if (!$item->hasField($field)) {
        continue;
      }

      if (!$item->get($field)->value) {
        self::convertItemToQuote($item);

        $save = TRUE;
      }
    }

    if ($save) {
      $cartOrder->save();
    }
  }

  public static function convertItemToQuote(OrderItemInterface $item) {
    $field = 'field_quote';

    if (!$item->hasField($field)) {
      return;
    }

    $field = $item->get($field);

    if ($field->value) {
      return;
    }

    $field->value = TRUE;

    $item->save();
  }

  public static function filterShippingMethods(array $shippingMethods, $quote = FALSE) {
    $quoteMethodName = 'Quote';

    return array_filter($shippingMethods, function (ShippingMethodInterface $shippingMethod) use ($quote, $quoteMethodName) {
      return $quote
        ? ($shippingMethod->getName() === $quoteMethodName)
        : ($shippingMethod->getName() !== $quoteMethodName);
    });
  }
}
