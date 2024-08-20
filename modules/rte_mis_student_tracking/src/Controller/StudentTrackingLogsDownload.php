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
   */
  public function getStudentsImportLogs(int $fid = 0) {
    $file = $this->entityTypeManager()->getStorage('file')->load($fid);
    if ($file instanceof FileInterface) {
      if ($file->getOwnerId() !== $this->currentUser()->id()) {
        throw new AccessDeniedHttpException('Cannot access the log.');
      }
      $destinationUri = 'public://student-import-logs';
      $this->fileSystem->prepareDirectory($destinationUri, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $newFile = $this->fileRepository->copy($file, $destinationUri, FileSystemInterface::EXISTS_RENAME);
      if ($newFile instanceof FileInterface) {
        $newFileUri = $this->fileSystem->realpath($newFile->getFileUri());
        $spreadsheet = IOFactory::load($newFileUri);
        // Make changes to the spreadsheet.
        $sheet = $spreadsheet->getActiveSheet();
        // Get the store collection.
        $store = $this->tempStoreFactory->get('rte_mis_student_tracking');
        $studentsImportLogs = $store->get('students_import_logs');
        if (!empty($studentsImportLogs)) {
          $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => [
              'bold' => TRUE,
            ],
          ]);
          $sheet->setCellValue('M1', 'Errors');
          $sheet->getColumnDimension('M')->setWidth(100);
          foreach ($studentsImportLogs as $key => $value) {
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
            $richText = new RichText();
            foreach ($error_messages as $index => $line) {
              $textRun = new Run($line);
              $font = $textRun->getFont();
              $font->setSize(10);
              $richText->addText($textRun);
              if ($index < count($error_messages) - 1) {
                $textRun = new Run("\n");
                $richText->addText($textRun);
              }
            }
            $sheet->setCellValue([13, $key], $richText);
          }
          // Save the modified spreadsheet to a temporary file.
          $writer = IOFactory::createWriter($spreadsheet, IOFactory::identify($newFileUri));
          $extension = pathinfo($newFileUri, PATHINFO_EXTENSION);
          $fileName = "students-import-log.$extension";
          $writer->save($fileName);
          // Create a BinaryFileResponse to return the file.
          $response = new BinaryFileResponse($fileName);
          // Set headers to force download.
          $response->setContentDisposition(
              ResponseHeaderBag::DISPOSITION_ATTACHMENT,
              $fileName
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
