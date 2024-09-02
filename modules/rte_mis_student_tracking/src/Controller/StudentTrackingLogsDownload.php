<?php

namespace Drupal\rte_mis_student_tracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\RichText\Run;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class to export logs related to student tracking module.
 */
class StudentTrackingLogsDownload extends ControllerBase {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service under test.
   *
   * @var \Drupal\file\FileRepository
   */
  protected $fileRepository;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Constructs StudentTrackingLogsDownload object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   *   The file repository.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(FileSystemInterface $file_system, FileRepositoryInterface $fileRepository, PrivateTempStoreFactory $temp_store_factory) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $fileRepository;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * Export the logs from students bulk import.
   *
   * @param int $fid
   *   The file ID of the uploaded file.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object with downloadable logs file.
   */
  public function getStudentsImportLogs(int $fid = 0) {
    $file = $this->entityTypeManager()->getStorage('file')->load($fid);
    if ($file instanceof FileInterface) {
      if ($file->getOwnerId() !== $this->currentUser()->id()) {
        throw new AccessDeniedHttpException('Cannot access the log.');
      }
      $destination_uri = 'public://student-import-logs';
      $this->fileSystem->prepareDirectory($destination_uri, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $new_file = $this->fileRepository->copy($file, $destination_uri, FileSystemInterface::EXISTS_RENAME);
      if ($new_file instanceof FileInterface) {
        $new_file_uri = $this->fileSystem->realpath($new_file->getFileUri());
        $spreadsheet = IOFactory::load($new_file_uri);
        $sheet_count = $spreadsheet->getSheetCount();
        // Make changes to the spreadsheet.
        $sheet = $spreadsheet->getSheet(0);
        // Delete second sheet as that contains the data format
        // and not required for logs file.
        if ($sheet_count > 1) {
          $spreadsheet->removeSheetByIndex(1);
        }
        // Get the store collection.
        $store = $this->tempStoreFactory->get('rte_mis_student_tracking');
        $students_import_logs = $store->get('students_import_logs');
        if (!empty($students_import_logs)) {
          $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => [
              'bold' => TRUE,
            ],
          ]);
          $sheet->setCellValue('O1', 'Errors');
          $sheet->getColumnDimension('O')->setWidth(100);
          foreach ($students_import_logs as $key => $value) {
            $error_messages = [];
            if (!empty($value['missing_values'])) {
              $error_messages[] = $this->t('Missing some required fields: @values', [
                '@values' => implode(', ', $value['missing_values']),
              ]);
            }
            if (!empty($value['errors'])) {
              foreach ($value['errors'] as $error_msg) {
                $error_messages[] = $error_msg;
              }
            }
            $error_messages_count = count($error_messages);
            // Set row height to keep an extra line space.
            $sheet->getRowDimension($key)->setRowHeight(($error_messages_count + 1) * 10);
            // Create a Rich Text object.
            $rich_text = new RichText();
            foreach ($error_messages as $index => $line) {
              $text_run = new Run($line);
              $font = $text_run->getFont();
              $font->setSize(10);
              $rich_text->addText($text_run);
              if ($index < count($error_messages) - 1) {
                $text_run = new Run("\n");
                $rich_text->addText($text_run);
              }
            }
            $sheet->setCellValue([15, $key], $rich_text);
          }
          // Save the modified spreadsheet to a temporary file.
          $writer = IOFactory::createWriter($spreadsheet, IOFactory::identify($new_file_uri));
          $extension = pathinfo($new_file_uri, PATHINFO_EXTENSION);
          $file_name = "students-import-log.$extension";
          $writer->save($file_name);
          // Create a BinaryFileResponse to return the file.
          $response = new BinaryFileResponse($file_name);
          // Set headers to force download.
          $response->setContentDisposition(
              ResponseHeaderBag::DISPOSITION_ATTACHMENT,
              $file_name
          );

          // Clean up temporary file after the response is sent.
          $response->deleteFileAfterSend(TRUE);
          return $response;
        }
      }
    }
    return new Response('File not found!', Response::HTTP_NOT_FOUND);
  }

}
