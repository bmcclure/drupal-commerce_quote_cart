<?php

namespace Drupal\commerce_quote_cart\Plugin\Commerce\CheckoutFlow;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;
use Drupal\commerce_quote_cart\QuoteCartHelper;

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
  /**
   * {@inheritdoc}
   */
  public function redirectToStep($step_id) {
    $this->order->set('checkout_step', $step_id);
    if ($step_id == 'complete') {
      $transition = $this->order->getState()->getWorkflow()->getTransition('place');
      $this->order->getState()->applyTransition($transition);
    }
    $this->order->save();
    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
      'step' => $step_id,
    ], $this->getUrlOptions($step_id));
    throw new NeedsRedirectException($url->toString());
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if ($next_step_id = $this->getNextStepId($form['#step_id'])) {
      if ($next_step_id == 'complete') {
        $form_state->setRedirect('commerce_checkout.form', [
          'commerce_order' => $this->order->id(),
          'step' => $next_step_id,
        ], $this->getUrlOptions($next_step_id));
      }
    }
  }

  protected function getUrlOptions($step_id) {
    $options = [];
    if ($step_id == 'complete') {
      $type = 'quote';
      if (QuoteCartHelper::isMixedCart($this->order)) {
        $type = 'mixed';
      } elseif (QuoteCartHelper::isPurchaseCart($this->order)) {
        $type = 'order';
      }

      $options = ['query' => ['type' => $type]];
    }

    return $options;
  }
}
