<?php

namespace Drupal\commerce_quote_cart\Plugin\Commerce\CheckoutFlow;
use Drupal\commerce_customizations\Plugin\Commerce\CheckoutFlow\MultistepOrder;

/**
 * Provides the quote multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "multistep_quote",
 *   label = "Multistep - Quote",
 * )
 */
class MultistepQuote extends MultistepOrder {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    $steps = parent::getSteps();

    $steps['order_information']['label'] = $this->t('Quote information');

    return $steps;
  }

}
