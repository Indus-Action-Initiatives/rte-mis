<?php

namespace Drupal\rte_mis_school\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for dblog routes.
 */
class FailedBulkDownload extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Constructs a DbLogController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->userStorage = $this->entityTypeManager()->getStorage('user');
  }

  /**
   * Displays details about a specific database log message.
   *
   * @param int $event_id
   *   Unique ID of the database log message.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object with Excel file content.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If no event found for the given ID.
   */
  public function widDetail($event_id) {

    $query = $this->database->select('watchdog', 'w');
    $query->fields('w');
    $query->addField('u', 'uid');
    $query->leftJoin('users', 'u', 'u.uid = w.uid');
    $query->condition('w.wid', $event_id);
    $dblog = $query->execute()->fetchObject();

    if (empty($dblog)) {
      throw new NotFoundHttpException();
    }
    $udise_errors = $this->extractErrorMessages($dblog->message);

    // Create a new PhpSpreadsheet instance.
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set data into the spreadsheet.
    $sheet->setCellValue('A1', 'Udise Code');
    $sheet->setCellValue('B1', 'Error Message');
    $sheet->setCellValue('C1', 'Uploaded By');
    $sheet->setCellValue('D1', 'Uploaded time');

    $boldStyle = [
      'font' => [
        'bold' => TRUE,
      ],
    ];
    $sheet->getStyle('A1:D1')->applyFromArray($boldStyle);
    $username = $dblog->uid ? $this->userStorage->load($dblog->uid)->getAccountName() : 'Anonymous';

    $row = 2;
    foreach ($udise_errors as $udise_code => $error_messages) {
      // If there are no error messages, add a single row with Udise code only.
      if (empty($error_messages)) {
        $sheet->setCellValue('A' . $row, $udise_code);
        $row++;
      }
      else {
        // If there are error messages.
        // Add a row for each error message with empty Udise code.
        foreach ($error_messages as $index => $error_message) {
          $sheet->setCellValue('A' . $row, $index === 0 ? $udise_code : '');
          $sheet->setCellValue('B' . $row, $error_message);
          $sheet->setCellValue('C' . $row, $username);
          $sheet->setCellValue('D' . $row, $this->dateFormatter->format($dblog->timestamp, 'long'));
          $row++;
        }
      }
    }
    $sheet->getColumnDimension('A')->setWidth(25);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(25);
    // Create a temporary file to save the spreadsheet.
    $temp_file = tempnam(sys_get_temp_dir(), 'excel');
    $writer = new Xlsx($spreadsheet);
    $writer->save($temp_file);

    // Read the file contents.
    $excel_content = file_get_contents($temp_file);

    // Create a response object with the file content.
    $response = new Response();
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment; filename="bulk_fail_' . $event_id . '.xlsx"');
    $response->setContent($excel_content);

    // Clean up temporary file.
    unlink($temp_file);

    return $response;
  }

  /**
   * Extracts Udise codes and error messages from the given message.
   *
   * @param string $message
   *   The message containing Udise codes and error messages.
   *
   * @return array
   *   Array containing Udise codes as keys and arrays of error messages.
   */
  private function extractErrorMessages(string $message) {
    // Regular expression to match Udise Code and error messages.
    $pattern = '/<h3>(.*?)<\/h3>(.*?)<\/ul>/s';

    // Find all matches in the message.
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);

    // Initialize array to store Udise codes and error messages.
    $udise_errors = [];

    // Iterate through matches and extract Udise code and error messages.
    foreach ($matches as $match) {
      $udise_code = $match[1];
      $error_messages = $match[2];
      // Extract Udise code from string.
      preg_match('/\d+/', $udise_code, $code_matches);
      $udise_code = $code_matches[0];
      // Extract individual error messages from the list.
      preg_match_all('/<li>(.*?)<\/li>/', $error_messages, $error_matches);
      $error_messages = $error_matches[1];
      // Add error messages to Udise code array.
      if (!isset($udise_errors[$udise_code])) {
        $udise_errors[$udise_code] = [];
      }
      foreach ($error_messages as $error_message) {
        $udise_errors[$udise_code][] = $error_message;
      }
    }

    return $udise_errors;
  }

}
