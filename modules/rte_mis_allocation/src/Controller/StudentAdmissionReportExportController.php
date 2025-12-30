<?php

namespace Drupal\rte_mis_allocation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Mpdf\Mpdf;

/**
 * Controller for exporting Student Admission Report.
 */
class StudentAdmissionReportExportController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The report controller.
   *
   * @var \Drupal\rte_mis_allocation\Controller\StudentAdmissionReportController
   */
  protected $reportController;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    ClassResolverInterface $classResolver,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->classResolver = $classResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('class_resolver')
    );

    // Automatically resolve the report controller – FIXED
    $instance->reportController = $instance->classResolver
      ->getInstanceFromDefinition(StudentAdmissionReportController::class);

    return $instance;
  }

  /**
   * Export to Excel.
   */
  public function exportExcel($id = NULL) {
    $headers = $this->reportController->getHeaders($id);
    $rows = $this->reportController->getData($id);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $col = 'A';
    foreach ($headers as $head) {
      $sheet->setCellValue($col . '1', $head);
      $col++;
    }

    $rowIndex = 2;
    foreach ($rows as $row) {
      $col = 'A';
      foreach ($row as $cell) {
        $value = is_array($cell) ? $this->resolveCellValue($cell) : $cell;
        $sheet->setCellValue($col . $rowIndex, $value);
        $col++;
      }
      $rowIndex++;
    }

    $fileName = 'student_admission_report_' . date('Ymd_His') . '.xlsx';
    $filePath = 'public://exports/' . $fileName;

    $directory = 'public://exports';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    (new Xlsx($spreadsheet))->save($filePath);

    return new BinaryFileResponse($filePath);
  }

  /**
   * Export PDF.
   */
  public function exportPdf($id = NULL) {
    $headers = $this->reportController->getHeaders($id);
    $rows = $this->reportController->getData($id);

    $html = '<h3>Student Admission Report</h3>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:12px;">';
    $html .= '<tr>';
    foreach ($headers as $h) {
      $html .= "<th style='background:#eee;'>$h</th>";
    }
    $html .= '</tr>';

    foreach ($rows as $row) {
      $html .= '<tr>';
      foreach ($row as $cell) {
        $value = is_array($cell) ? $this->resolveCellValue($cell) : $cell;
        $html .= "<td>$value</td>";
      }
      $html .= '</tr>';
    }

    $html .= '</table>';

    $fileName = 'student_admission_report_' . date('Ymd_His') . '.pdf';
    $filePath = 'public://exports/' . $fileName;

    $directory = 'public://exports';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $mpdf = new Mpdf();
    $mpdf->WriteHTML($html);
    $mpdf->Output($filePath, 'F');

    return new BinaryFileResponse($filePath);
  }

  /**
   * Resolve cell value.
   */
  private function resolveCellValue($cell) {
    if (is_array($cell) && isset($cell['data'])) {
      if (!empty($cell['data']['#title'])) {
        return $cell['data']['#title'];
      }
      if (!empty($cell['data']['#markup'])) {
        return strip_tags($cell['data']['#markup']);
      }
      return '';
    }
    return is_array($cell) ? '' : $cell;
  }

}
