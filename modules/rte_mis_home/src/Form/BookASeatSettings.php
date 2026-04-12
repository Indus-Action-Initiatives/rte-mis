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

    $form_state->setCached(FALSE);
    $description_config = $config->get('book_a_seat.description');
    if (is_array($description_config)) {
      $description_value = $description_config['value'] ?? '';
      $description_format = $description_config['format'] ?? 'full_html';
    } else {
      $description_value = $description_config ?? 'Join thousands of families who have transformed their children\'s future through quality education';
      $description_format = 'full_html';
    }

    $form['book_a_seat']['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#format' => $description_format,
      '#default_value' => $description_value,
    ];

    $existing_buttons = $config->get('book_a_seat.buttons') ?? [];
    if (empty($existing_buttons) && !empty($config->get('book_a_seat.button_text'))) {
      $existing_buttons = [['text' => $config->get('book_a_seat.button_text'), 'link' => $config->get('book_a_seat.button_link') ?? '#']];
    }
    
    $num_buttons = $form_state->get('num_buttons');
    if ($num_buttons === NULL) {
      $num_buttons = max(1, count($existing_buttons));
      $form_state->set('num_buttons', $num_buttons);
    }

    $form['book_a_seat']['buttons_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Buttons (CTAs)'),
      '#open' => TRUE,
      '#prefix' => '<div id="buttons-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $num_buttons; $i++) {
      $form['book_a_seat']['buttons_wrapper'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Button @num', ['@num' => $i + 1]),
      ];
      $form['book_a_seat']['buttons_wrapper'][$i]['text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button Text'),
        '#default_value' => $existing_buttons[$i]['text'] ?? '',
      ];
      $form['book_a_seat']['buttons_wrapper'][$i]['link'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button Link'),
        '#default_value' => $existing_buttons[$i]['link'] ?? '',
      ];
    }

    $form['book_a_seat']['buttons_wrapper']['add_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another button'),
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::addButtonAjax',
        'wrapper' => 'buttons-wrapper',
      ],
      '#button_type' => 'secondary',
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

    // Extract buttons and clean up structure.
    $buttons = [];
    if (!empty($values['buttons_wrapper'])) {
      foreach ($values['buttons_wrapper'] as $key => $button_data) {
        if (is_numeric($key) && !empty($button_data['text']) && !empty($button_data['link'])) {
          $buttons[] = [
            'text' => $button_data['text'],
            'link' => $button_data['link'],
          ];
        }
      }
    }
    $values['buttons'] = $buttons;
    unset($values['buttons_wrapper']);

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

  /**
   * Submit handler for the "Add another button" button.
   */
  public function addButtonSubmit(array &$form, FormStateInterface $form_state): void {
    $num_buttons = $form_state->get('num_buttons') + 1;
    $form_state->set('num_buttons', $num_buttons);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another button" button.
   */
  public function addButtonAjax(array &$form, FormStateInterface $form_state): array {
    return $form['book_a_seat']['buttons_wrapper'];
  }
}
