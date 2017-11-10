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
use Drupal\hook_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherEvents;
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
    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterCheckoutForm', -10];
    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterCartForm'];
    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterAddToCartForm'];

    return $events;
  }

  /**
   * @param FormAlterEvent $event
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function alterAddToCartForm(FormAlterEvent $event) {
    if (strpos($event->getFormId(), 'commerce_order_item_default_add_to_cart_') !== 0) {
      return;
    }

    $form = $event->getForm();
    $form_state = $event->getFormState();
    $variation = commerce_quote_cart_get_current_variation($form_state->getStorage());

    if (empty($variation)) {
      return;
    }

    $availableForQuote = $variation->get('field_available_for_quote')->value || QuoteCartHelper::hasQuoteCart();
    $availableForPurchase = $variation->get('field_available_for_purchase')->value;

    $form['actions']['submit']['#access'] = (bool) $availableForPurchase;

    $form['actions']['quote'] = [
      '#type' => 'submit',
      '#value' => t('Quote'),
      '#submit' => ['commerce_quote_cart_submit_quote'],
      '#button_type' => 'primary',
      '#weight' => 6,
      '#access' => (bool) $availableForQuote
    ];

    if (isset($form['actions']['submit']['#ajax'])) {
      $form['actions']['quote']['#ajax'] = $form['actions']['submit']['#ajax'];
    }

    $event->setForm($form);
  }

  public function alterCartForm(FormAlterEvent $event) {
    $form_id = $event->getFormId();

    if (strpos($form_id, 'views_form_commerce_cart_form_') !== 0) {
      return;
    }

    $form = $event->getForm();
    $form_state = $event->getFormState();

    /** @var \Drupal\views\ViewExecutable $view */
    $view = $form_state->getBuildInfo()['args'][0];

    foreach ($view->result as $resultRow) {
      if (!isset($resultRow->_entity)) {
        continue;
      }

      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $resultRow->_entity;

      if (QuoteCartHelper::isMixedCart($order)) {
        $form['actions']['convert'] = [
          '#type' => 'submit',
          '#value' => t('Convert to Quote'),
          '#submit' => ['commerce_quote_cart_submit_convert'],
          '#button_type' => 'primary',
          '#weight' => 0,
        ];
      }
    }

    $event->setForm($form);
  }

  /**
   * @param \Drupal\hook_event_dispatcher\Event\Form\FormAlterEvent $event
   */
  public function alterCheckoutForm(FormAlterEvent $event) {
    $form_id = $event->getFormId();

    if (strpos($form_id, 'commerce_checkout_flow_') !== 0) {
      return;
    }

    $form = $event->getForm();
    $is_quote = !QuoteCartHelper::isPurchaseCart();
    $label = $is_quote ? 'Quote' : 'Order';
    $shipping_label = $is_quote ? 'Contact' : 'Shipping';

    if ($is_quote && isset($form['totals'])) {
      unset($form['totals']);
    }

    if (isset($form['shipping_information'])) {
      $form['shipping_information']['#title'] = t($shipping_label . ' Information');
      $form['#attached']['library'][] = 'commerce_quote_cart/shipping-information';

      // Hide shipping information for quote orders
      if (isset($form['shipping_information']['shipments']) && $is_quote) {
        $form['shipping_information']['shipments']['#attributes']['class'][] = 'js-hide';
      }
    }

    if (isset($form['review']) && isset($form['review']['shipping_information'])) {
      $form['review']['shipping_information']['#title'] = str_replace('Shipping ', "$shipping_label ", $form['review']['shipping_information']['#title']);
    }

    if (isset($form['actions']['next'])) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $nextValue */
      $nextValue = $form['actions']['next']['#value'];

      if (strtolower($nextValue->getUntranslatedString()) == 'pay and complete purchase') {
        $form['actions']['next']['#value'] = t('Complete ' . strtolower($label));
      }
    }

    if (isset($form['sidebar']['order_summary'])) {
      $form['sidebar']['order_summary']['summary_title'] = [
        '#type' => 'container',
        '#weight' => -1,
        'title' => [
          '#markup' => t("$label summary"),
        ]
      ];
    }

    $event->setForm($form);
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
