<?php

namespace Drupal\commerce_quote_cart\Plugin\Commerce\CheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;

/**
 * Provides the quote multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "multistep_quote",
 *   label = "Multistep - Quote",
 * )
 */
class MultistepQuote extends MultistepDefault {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    $steps = parent::getSteps();

    $steps['order_information']['label'] = $this->t('Quote information');

    return $steps;
  }

}
