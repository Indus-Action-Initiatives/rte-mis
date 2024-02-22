<?php

namespace Drupal\rte_mis\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Installer\Form\SiteConfigureForm as CoreSiteConfigureForm;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the installer's site configuration form for RTE-MIS.
 */
class SiteConfigureForm extends ConfigFormBase {

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  private $moduleInstaller;

  /**
   * The state interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The decorated site configuration form object.
   *
   * @var \Drupal\Core\Installer\Form\SiteConfigureForm
   */
  protected $siteForm;

  /**
   * SiteConfigureForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Installer\Form\SiteConfigureForm $site_form
   *   The decorated site configuration form object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleInstallerInterface $module_installer, StateInterface $state, CoreSiteConfigureForm $site_form) {
    parent::__construct($config_factory);
    $this->moduleInstaller = $module_installer;
    $this->state = $state;
    $this->siteForm = $site_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_installer'),
      $container->get('state'),
      CoreSiteConfigureForm::create($container),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rte_mis.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->siteForm->getFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = $this->siteForm->buildForm($form, $form_state);
    // Set default value for site name.
    $form['site_information']['site_name']['#default_value'] = $this->t('RTE-MIS');

    // We don't want to show the notification as of now.
    $form['update_notifications']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->siteForm->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->siteForm->submitForm($form, $form_state);
  }

}
