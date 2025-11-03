<?php

namespace Drupal\rte_mis_school\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding notifications.
 */
class NotificationForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new NotificationForm.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rte_mis_school_notification_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#rows' => 5,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Notification'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $storage = $this->entityTypeManager->getStorage('mini_node');
      
      $notification = $storage->create([
        'type' => 'notification',
        'title' => $form_state->getValue('title'),
      ]);

      // If the bundle has a status/published field, publish the notification
      if ($notification->hasField('status')) {
        $notification->set('status', 1);
      }

      // Add description field if it exists
      if ($notification->hasField('field_description')) {
        $notification->set('field_description', $form_state->getValue('description'));
      }

      $notification->save();

  // Invalidate dashboard cache so new notification appears immediately.
  \Drupal\Core\Cache\Cache::invalidateTags(['rte_mis_school:dashboard']);

      $this->messenger()->addStatus($this->t('Notification "@title" has been created.', [
        '@title' => $form_state->getValue('title'),
      ]));

      $form_state->setRedirect('rte_mis_school.dashboard');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error creating notification: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
