<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($student_id <= 0) {
    die("Invalid student_id");
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

/**
 * 1) Check access (admin can download any, user can download only own student)
 */
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT id, user_id, student_name FROM students WHERE id=?");
    $stmt->bind_param("i", $student_id);
} else {
    $stmt = $conn->prepare("SELECT id, user_id, student_name FROM students WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $student_id, $user_id);
}
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found or access denied.");
}

$student_name = $student['student_name'];
$student_name_safe = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $student_name);

/**
 * 2) Get uploaded documents for this student
 */
$stmt = $conn->prepare("
    SELECT sd.file_name, sd.file_path, dt.doc_name
    FROM student_documents sd
    JOIN document_types dt ON dt.id = sd.doc_type_id
    WHERE sd.student_id=?
    ORDER BY dt.category ASC, dt.doc_name ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    die("No documents uploaded for this student.");
}

/**
 * 3) Create ZIP in temp folder
 * Make sure PHP Zip extension is enabled in XAMPP (php_zip.dll)
 */
$zip = new ZipArchive();

$tmpDir = sys_get_temp_dir();
$zipFileName = $student_name_safe . "_student_" . $student_id . "_" . date("Y-m-d") . ".zip";
$zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipFileName;

if (file_exists($zipPath)) {
    @unlink($zipPath); // remove old zip if same name exists
}

if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die("Could not create ZIP file. Enable ZipArchive in PHP.");
}

/**
 * 4) Add files to ZIP
 * Uses readable names inside ZIP like: "Identity/Passport.pdf"
 */
while ($row = $result->fetch_assoc()) {

    $filePath = $row['file_path'];
    $docName = $row['doc_name'];

    if (!$filePath || !file_exists($filePath)) {
        continue; // skip missing files
    }

    // safe names for inside zip
    $docNameSafe = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $docName);

    // Try to keep original file extension
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === '') $ext = 'pdf';

    // Add as "DocName.pdf"
    $insideZipName = $docNameSafe . "." . $ext;

    $zip->addFile($filePath, $insideZipName);
}

$zip->close();

/**
 * 5) Download ZIP
 */
if (!file_exists($zipPath)) {
    die("ZIP creation failed.");
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: public');
header('Cache-Control: must-revalidate');

readfile($zipPath);

// delete temp zip after download
@unlink($zipPath);
exit();