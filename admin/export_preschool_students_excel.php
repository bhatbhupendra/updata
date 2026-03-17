<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$pre_school_id = isset($_POST['pre_school_id']) ? (int)$_POST['pre_school_id'] : 0;
$agent_id      = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0; // optional filter info
$student_ids   = $_POST['student_ids'] ?? [];
$fields        = $_POST['fields'] ?? [];
$include_photo = isset($_POST['include_photo']) ? 1 : 0;

if($pre_school_id <= 0){
    die("Invalid Pre School.");
}
if(empty($student_ids)){
    die("Please select at least one student.");
}
if(empty($fields)){
    die("Please select at least one field.");
}

/* ---------------------------
   Allowed fields (whitelist)
---------------------------- */
$allowedFields = [
  'student_name','student_name_jp','gender','date_of_birth','nationality','phone',
  'passport_number','current_address','permanent_address','japanese_level','intake',
  'agent_name','agent_email'
];

$fields = array_values(array_intersect($fields, $allowedFields));
if(empty($fields)){
    die("Invalid fields selected.");
}

/* ---------------------------
   Load PhpSpreadsheet
---------------------------- */
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* ---------------------------
   Fetch students (ONLY those still in Pre School)
---------------------------- */
$ids = array_map('intval', $student_ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$selectParts = [];
foreach($fields as $f){
    if($f === 'agent_name' || $f === 'agent_email'){
        // come from users table
        $selectParts[] = ($f === 'agent_name') ? "u.name AS agent_name" : "u.email AS agent_email";
    } else {
        $selectParts[] = "s.`$f`";
    }
}
$selectCols = implode(", ", $selectParts);

$sql = "
SELECT s.id, $selectCols
FROM students s
LEFT JOIN users u ON u.id = s.user_id
WHERE s.school_id = ? AND s.id IN ($placeholders)
ORDER BY s.id DESC
";

$stmt = $conn->prepare($sql);
if(!$stmt) die("Prepare failed: ".$conn->error);

$types = str_repeat('i', 1 + count($ids));
$params = array_merge([$pre_school_id], $ids);

// bind params by reference
$bind = [];
$bind[] = $types;
foreach($params as $k => $v){
    $bind[] = &$params[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$rows = [];
while($r = $res->fetch_assoc()){
    $rows[] = $r;
}

if(empty($rows)){
    die("No students found to export (maybe moved already).");
}

/* ---------------------------
   Spreadsheet
---------------------------- */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("PreSchool");

$fieldLabels = [
  'student_name'=>'Name',
  'student_name_jp'=>'Name (JP)',
  'gender'=>'Gender',
  'date_of_birth'=>'DOB',
  'nationality'=>'Nationality',
  'phone'=>'Phone',
  'passport_number'=>'Passport No.',
  'current_address'=>'Current Address',
  'permanent_address'=>'Permanent Address',
  'japanese_level'=>'Japanese Level',
  'intake'=>'Intake',
  'agent_name'=>'Agent Name',
  'agent_email'=>'Agent Email',
];

/* headers */
$colIndex = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++)."1", "Student ID");

foreach($fields as $f){
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++)."1", $fieldLabels[$f] ?? $f);
}

$photoColIndex = 0;
if($include_photo){
    $photoColIndex = $colIndex;
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++)."1", "Photo");
}

/* style header */
$highestCol = Coordinate::stringFromColumnIndex($colIndex - 1);
$sheet->getStyle("A1:{$highestCol}1")->getFont()->setBold(true);

/* fill rows */
$rowNum = 2;
foreach($rows as $r){
    $colIndex = 1;
    $sid = (int)$r['id'];

    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++).$rowNum, $sid);

    foreach($fields as $f){
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++).$rowNum, $r[$f] ?? '');
    }

    if($include_photo){
        // latest JPG/JPEG for this student
        $img_path = "";
        $q = $conn->query("
          SELECT sd.file_path
          FROM student_documents sd
          JOIN document_types dt ON dt.id = sd.doc_type_id
          WHERE sd.student_id = $sid
            AND LOWER(dt.file_type) IN ('jpg','jpeg')
          ORDER BY sd.uploaded_at DESC
          LIMIT 1
        ");
        if($q && $q->num_rows > 0){
            $img_path = $q->fetch_assoc()['file_path'] ?? '';
        }

        $cell = Coordinate::stringFromColumnIndex($photoColIndex) . $rowNum;

        // Resolve path (best effort)
        $localPath = "";
        if($img_path){
            $localPath = realpath(__DIR__ . "/" . $img_path);
            if(!$localPath){
                $localPath = realpath(__DIR__ . "/../" . ltrim($img_path, '/'));
            }
        }

        if($localPath && file_exists($localPath)){
            $sheet->getRowDimension($rowNum)->setRowHeight(70);

            $drawing = new Drawing();
            $drawing->setName('Photo');
            $drawing->setPath($localPath);
            $drawing->setHeight(60);
            $drawing->setCoordinates($cell);
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        } else {
            $sheet->setCellValue($cell, "");
        }
    }

    $rowNum++;
}

/* autosize columns (safe) */
$lastCol = Coordinate::stringFromColumnIndex($colIndex - 1);
foreach(range('A', $lastCol) as $c){
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

$filename = "preschool_students_" . date("Y-m-d") . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;