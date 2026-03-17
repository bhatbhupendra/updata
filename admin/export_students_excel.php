<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$student_ids = $_POST['student_ids'] ?? [];
$fields = $_POST['fields'] ?? [];
$include_photo = isset($_POST['include_photo']) ? 1 : 0;

if($user_id <= 0){
    die("Invalid user.");
}

if(empty($fields)){
    die("Please select at least one field.");
}

if(empty($student_ids)){
    die("Please select at least one student (or Select all).");
}

// Whitelist fields
$allowedFields = [
  'student_name','student_name_jp','gender','date_of_birth','age','nationality','phone',
  'passport_number','permanent_address','current_address','highest_qualification',
  'last_institution_name','graduation_year','japanese_level','japanese_test_type',
  'japanese_training_hours','sponsor_name','sponsor_relationship','intake'
];

$fields = array_values(array_intersect($fields, $allowedFields));
if(empty($fields)){
    die("Invalid fields selected.");
}

// Load PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Labels for columns
$fieldLabels = [
  'student_name'=>'Name',
  'student_name_jp'=>'Name (JP)',
  'gender'=>'Gender',
  'date_of_birth'=>'DOB',
  'age'=>'Age',
  'nationality'=>'Nationality',
  'phone'=>'Phone',
  'passport_number'=>'Passport No.',
  'permanent_address'=>'Permanent Address',
  'current_address'=>'Current Address',
  'highest_qualification'=>'Highest Qualification',
  'last_institution_name'=>'Last Institution',
  'graduation_year'=>'Graduation Year',
  'japanese_level'=>'Japanese Level',
  'japanese_test_type'=>'Japanese Test',
  'japanese_training_hours'=>'Training Hours',
  'sponsor_name'=>'Sponsor Name',
  'sponsor_relationship'=>'Sponsor Relationship',
  'intake'=>'Intake',
];

// Build query for selected students
$ids = array_map('intval', $student_ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$selectCols = implode(',', array_map(fn($c) => "s.`$c`", $fields));

$sql = "SELECT s.id, $selectCols
        FROM students s
        WHERE s.user_id=? AND s.id IN ($placeholders)
        ORDER BY s.id DESC";

$stmt = $conn->prepare($sql);
if(!$stmt){
    die("Prepare failed: " . $conn->error);
}

// Bind params dynamically
$types = "i" . str_repeat("i", count($ids));
$params = array_merge([$user_id], $ids);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$rows = [];
while($r = $res->fetch_assoc()){
    $rows[] = $r;
}

if(empty($rows)){
    die("No students found to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Students");

// ---------- HEADER ROW ----------
$rowIndex = 1;
$colIndex = 1;

// Student ID
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, "Student ID");
$colIndex++;

// Selected fields
foreach($fields as $f){
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, $fieldLabels[$f] ?? $f);
    $colIndex++;
}

// Photo column
if($include_photo){
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, "Photo");
}

// Bold header
$sheet->getStyle("A1:" . $sheet->getHighestColumn() . "1")->getFont()->setBold(true);

// ---------- DATA ROWS ----------
$rowIndex = 2;

foreach($rows as $r){

    $colIndex = 1;

    // Student ID
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, (int)$r['id']);
    $colIndex++;

    // Fields
    foreach($fields as $f){
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, $r[$f] ?? '');
        $colIndex++;
    }

    // Photo
    if($include_photo){

        $sid = (int)$r['id'];
        $img_path = "";

        $q = $conn->query("
          SELECT sd.file_path
          FROM student_documents sd
          JOIN document_types dt ON dt.id = sd.doc_type_id
          WHERE sd.student_id = $sid
            AND LOWER(dt.file_type) = 'jpg'
          ORDER BY sd.uploaded_at DESC
          LIMIT 1
        ");

        if($q && $q->num_rows > 0){
            $img_path = $q->fetch_assoc()['file_path'] ?? '';
        }

        $photoColLetter = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $photoColLetter . $rowIndex;

        // Resolve local server path
        $localPath = "";
        if($img_path){
            // Most common: saved like "../uploads/..."
            $localPath = realpath(__DIR__ . "/" . $img_path);

            // Try alternative: "uploads/..."
            if(!$localPath){
                $localPath = realpath(__DIR__ . "/../" . ltrim($img_path, "/"));
            }
        }

        if($localPath && file_exists($localPath)){
            // Make row height bigger for image
            $sheet->getRowDimension($rowIndex)->setRowHeight(70);

            $drawing = new Drawing();
            $drawing->setName('Photo');
            $drawing->setPath($localPath);
            $drawing->setHeight(60);
            $drawing->setCoordinates($cell);
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        } else {
            // leave blank if no image
            $sheet->setCellValue($cell, "");
        }
    }

    $rowIndex++;
}

// Autosize columns
$highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
for($i = 1; $i <= $highestColIndex; $i++){
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output file
$filename = "students_export_user_" . $user_id . "_" . date("Y-m-d") . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;