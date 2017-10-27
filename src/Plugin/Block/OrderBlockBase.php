<?php

namespace Drupal\commerce_quote_cart\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

abstract class OrderBlockBase extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    //$config = $this->getConfiguration();

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {

  }

}
