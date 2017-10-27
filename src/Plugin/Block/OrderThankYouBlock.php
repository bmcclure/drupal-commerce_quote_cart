<?php

namespace Drupal\commerce_quote_cart\Plugin\Block;

use Drupal\commerce_quote_cart\QuoteCartHelper;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an "Order Thank You" block.
 *
 * @Block(
 *   id = "commerce_quote_cart_order_thank_you",
 *   admin_label = @Translation("Order Thank You block"),
 * )
 */
class OrderThankYouBlock extends OrderBlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['order_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order title'),
      '#description' => $this->t('The title to use for a full order.'),
      '#default_value' => isset($config['order_title']) ? $config['order_title'] : 'Thank You For Your Order!',
    ];

    $form['quote_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quote title'),
      '#description' => $this->t('The title to use for a quote-only order.'),
      '#default_value' => isset($config['quote_title']) ? $config['quote_title'] : 'Thank You For Your Quote!',
    ];

    $form['order_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Order body'),
      '#default_value' => isset($config['order_body']) ? $config['order_body'] : 'A full receipt will be emailed to the address you provided.',
    ];

    $form['quote_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Order body'),
      '#default_value' => isset($config['quote_body']) ? $config['quote_body'] : 'Your quote details will be emailed to the address you provided.',
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $this->setConfigurationValue('order_title', $form_state->getValue('order_title'));
    $this->setConfigurationValue('quote_title', $form_state->getValue('quote_title'));
    $this->setConfigurationValue('order_body', $form_state->getValue('order_body'));
    $this->setConfigurationValue('quote_body', $form_state->getValue('quote_body'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $order = \Drupal::routeMatch()->getParameter('commerce_order');

    $type = (!$order || QuoteCartHelper::isPurchaseCart($order)) ? 'order' : 'quote';

    $output = [
      '#title' => $config[$type . '_title'],
      '#markup' => $config[$type . '_body'],
      '#cache' => ['max-age' => 0],
    ];

    return $output;
  }
}
