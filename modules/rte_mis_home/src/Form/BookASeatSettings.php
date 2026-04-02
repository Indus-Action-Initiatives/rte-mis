<?php

declare(strict_types=1);

namespace Drupal\rte_mis_home\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure rte_mis_home book a seat block settings.
 */
final class BookASeatSettings extends ConfigFormBase
{
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a BookASeatSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct($config_factory, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'rte_mis_home_book_a_seat_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['rte_mis_home.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('rte_mis_home.settings');

    $form['book_a_seat'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Book A Seat Block Settings'),
      '#tree' => TRUE,
    ];

    $form['book_a_seat']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('book_a_seat.title') ?? 'Book A Seat Now',
    ];

    $form['book_a_seat']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $config->get('book_a_seat.description') ?? 'Join thousands of families who have transformed their children\'s future through quality education',
    ];

    $form['book_a_seat']['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Text'),
      '#default_value' => $config->get('book_a_seat.button_text') ?? 'Check Documents & Guidelines',
    ];

    $form['book_a_seat']['button_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Link'),
      '#default_value' => $config->get('book_a_seat.button_link') ?? '#',
    ];

    $image_fid = $config->get('book_a_seat.background_image');
    $form['book_a_seat']['background_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Background Image'),
      '#upload_location' => 'public://block_images/',
      '#default_value' => $image_fid ? [$image_fid] : [],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->configFactory->getEditable('rte_mis_home.settings');
    $values = $form_state->getValue('book_a_seat');

    // Handle file permanent status.
    if (!empty($values['background_image'])) {
      $fid = reset($values['background_image']);
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $values['background_image'] = $fid;
      }
    }
    else {
      $values['background_image'] = NULL;
    }

    $config->set('book_a_seat', $values);
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
