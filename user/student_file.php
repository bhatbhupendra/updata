<?php
session_start();
include '../config/db.php';
include '../includes/notification_functions.php';


if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

$success = "";
$error = "";

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if($student_id <= 0){
    die("No student ID provided!");
}

$user_id  = (int)$_SESSION['user_id'];
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : $user_id;
$role     = $_SESSION['role'] ?? 'user';

/* ---------------------------
   1) Load student + school
---------------------------- */
if($role === 'admin'){
    $stmt = $conn->prepare("
        SELECT s.*, sc.name AS school_name
        FROM students s
        JOIN schools sc ON sc.id = s.school_id
        WHERE s.id=?
    ");
    $stmt->bind_param("i", $student_id);
} else {
    $stmt = $conn->prepare("
        SELECT s.*, sc.name AS school_name
        FROM students s
        JOIN schools sc ON sc.id = s.school_id
        WHERE s.id=? AND s.user_id=?
    ");
    $stmt->bind_param("ii", $student_id, $user_id);
    }
    $stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$student){
    die("Student not found or access denied!");
}

$student_name        = $student['student_name'] ?? '';
$student_name_jp     = $student['student_name_jp'] ?? '';
$gender              = $student['gender'] ?? '';
$date_of_birth       = $student['date_of_birth'] ?? '';
$age                 = $student['age'] ?? '';
$nationality         = $student['nationality'] ?? '';
$intake              = $student['intake'] ?? '';
$school_id           = (int)($student['school_id'] ?? 0);
$school_name         = $student['school_name'] ?? '';
$marital_status = $student['marital_status'] ?? '';
$student_email       = $student['student_email'] ?? '';
$phone = $student['phone'] ?? '';
$permanent_address = $student['permanent_address'] ?? '';
$current_address = $student['current_address'] ?? '';

$passport_number     = $student['passport_number'] ?? '';
$passport_issue_date = $student['passport_issue_date'] ?? '';
$passport_expiry_date = $student['passport_expiry_date'] ?? '';

$father_name = $student['father_name'] ?? '';
$father_occupation = $student['father_occupation'] ?? '';
$mother_name = $student['mother_name'] ?? '';
$mother_occupation = $student['mother_occupation'] ?? '';

$highest_qualification = $student['highest_qualification'] ?? '';
$last_institution_name = $student['last_institution_name'] ?? '';
$graduation_year = $student['graduation_year'] ?? '';
$academic_gap_years = $student['academic_gap_years'] ?? '';
$japanese_level      = $student['japanese_level'] ?? '';
$japanese_test_type      = $student['japanese_test_type'] ?? '';
$japanese_exam_score      = $student['japanese_exam_score'] ?? '';
$japanese_exam_date      = $student['japanese_exam_date'] ?? '';

$sponsor_name_1      = $student['sponsor_name_1'] ?? '';
$sponsor_relationship_1      = $student['sponsor_relationship_1'] ?? '';
$sponsor_occupation_1      = $student['sponsor_occupation_1'] ?? '';
$sponsor_annual_income_1      = $student['sponsor_annual_income_1'] ?? '';
$sponsor_saving_amount_1      = $student['sponsor_saving_amount_1'] ?? '';

$sponsor_name_2      = $student['sponsor_name_2'] ?? '';
$sponsor_relationship_2      = $student['sponsor_relationship_2'] ?? '';
$sponsor_occupation_2      = $student['sponsor_occupation_2'] ?? '';
$sponsor_annual_income_2      = $student['sponsor_annual_income_2'] ?? '';
$sponsor_saving_amount_2      = $student['sponsor_saving_amount_2'] ?? '';

$japanese_training_hours              = $student['japanese_training_hours'] ?? '';
$information = $student['information'] ?? '';
$career_path = $student['career_path'] ?? '';


/* ---------------------------
   2) Verify handler (Admin only)
---------------------------- */
if($role === 'admin' && isset($_POST['verify_action'])){
    $doc_id  = (int)($_POST['doc_id'] ?? 0);
    $action  = strtolower(trim($_POST['action'] ?? ''));
    $message = trim($_POST['verify_message'] ?? '');

    if($doc_id <= 0){
        $error = "Invalid document.";
    } elseif(!in_array($action, ['approved','disapproved'], true)){
        $error = "Invalid action.";
    } else {

        $chk = $conn->prepare("SELECT id FROM student_documents WHERE id=? AND student_id=? LIMIT 1");
        $chk->bind_param("ii", $doc_id, $student_id);
        $chk->execute();
        $found = $chk->get_result()->fetch_assoc();
        $chk->close();

        if(!$found){
            $error = "Document not found for this student.";
        } else {

            $admin_id = (int)$_SESSION['user_id'];

            $stmt = $conn->prepare("
                UPDATE student_documents
                SET verify_status=?,
                    verify_message=?,
                    verified_by=?,
                    verified_at=NOW()
                WHERE id=? AND student_id=?
            ");
            $stmt->bind_param("ssiii", $action, $message, $admin_id, $doc_id, $student_id);

            if($stmt->execute()){
                $stmt->close();
                header("Location: ".$_SERVER['PHP_SELF']."?student_id=".$student_id."&agent_id=".$agent_id."&v=1");
                exit();
            } else {
                $error = "Verify failed: ".$stmt->error;
                $stmt->close();
            }
        }
    }
}
if(isset($_GET['v']) && $_GET['v'] == "1"){
    $success = "Document verification saved.";
}


// Photo preview (latest jpg)
        $img_url = "";
        $img_q = $conn->query("
            SELECT sd.file_path
            FROM student_documents sd
            JOIN document_types dt ON dt.id = sd.doc_type_id
            WHERE sd.student_id = $student_id
              AND LOWER(dt.file_type) = 'jpg'
            ORDER BY sd.uploaded_at DESC
            LIMIT 1
        ");
        if($img_q && $img_q->num_rows > 0){
            $img_url = $img_q->fetch_assoc()['file_path'] ?? '';
        }

        $photo_html = "<span class='text-muted'>No Photo to Preview</span>";
        if(!empty($img_url)){
            $photo_html = "<img src='".htmlspecialchars($img_url)."' width='150' height='auto' class='thumb' alt='Student Photo'>";
        }

/* ---------------------------
   2.5) LIVE CHAT SEND HANDLER (Admin + Agent)
   Append new message to ONE chat field
---------------------------- */
if(isset($_POST['chat_send'])){
    $doc_id = (int)($_POST['chat_doc_id'] ?? 0);
    $chat_message = trim($_POST['chat_message'] ?? '');

    if($doc_id <= 0){
        $error = "Invalid chat document.";
    } elseif($chat_message === ""){
        $error = "Chat message cannot be empty.";
    } else {
        // Ensure this document belongs to this student
        $chk = $conn->prepare("SELECT id FROM student_documents WHERE id=? AND student_id=? LIMIT 1");
        $chk->bind_param("ii", $doc_id, $student_id);
        $chk->execute();
        $found = $chk->get_result()->fetch_assoc();
        $chk->close();

        if(!$found){
            $error = "Document not found for this student.";
        } else {

            // sender label
            $sender = ($role === 'admin') ? "ADMIN" : "AGENT";

            // timestamp
            $ts = date("Y-m-d H:i");

            // sanitize single-line append (keep newlines but remove weird)
            $chat_message = str_replace(["\r\n", "\r"], "\n", $chat_message);

            $line = "[".$ts."] ".$sender.": ".$chat_message;

            // Load existing chat
            $stmt = $conn->prepare("SELECT id, chat FROM document_live_chat WHERE document_id=? LIMIT 1");
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if($existing){
                $newChat = trim((string)($existing['chat'] ?? ''));
                if($newChat !== ""){
                    $newChat .= "\n\n".$line;
                } else {
                    $newChat = $line;
                }

                $up = $conn->prepare("UPDATE document_live_chat SET chat=? WHERE document_id=?");
                $up->bind_param("si", $newChat, $doc_id);

                if($up->execute()){
                    $up->close();
                    header("Location: ".$_SERVER['PHP_SELF']."?student_id=".$student_id."&agent_id=".$agent_id."&chat=1&doc=".$doc_id);
                    exit();
                } else {
                    $error = "Chat save failed: ".$up->error;
                    $up->close();
                }

            } else {
                $ins = $conn->prepare("INSERT INTO document_live_chat (document_id, chat) VALUES (?, ?)");
                $ins->bind_param("is", $doc_id, $line);

                if($ins->execute()){
                    $ins->close();
                    header("Location: ".$_SERVER['PHP_SELF']."?student_id=".$student_id."&agent_id=".$agent_id."&chat=1&doc=".$doc_id);
                    exit();
                } else {
                    $error = "Chat insert failed: ".$ins->error;
                    $ins->close();
                }
            }
        }
    }
}

if(isset($_GET['chat']) && $_GET['chat'] == "1"){
    $success = "Chat message sent.";
}

/* ---------------------------
   3) Upload handler (locked if approved)
---------------------------- */
if(isset($_POST['upload_doc'])){
    $doc_type_id = (int)($_POST['doc_type_id'] ?? 0);

    if($doc_type_id <= 0){
        $error = "Invalid document type.";
    } elseif(!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK){
        $error = "File upload failed.";
    } else {

        $block = $conn->prepare("
            SELECT sd.id
            FROM student_documents sd
            WHERE sd.student_id=? AND sd.doc_type_id=? AND sd.verify_status='approved'
            LIMIT 1
        ");
        $block->bind_param("ii", $student_id, $doc_type_id);
        $block->execute();
        $alreadyApproved = $block->get_result()->fetch_assoc();
        $block->close();

        if($alreadyApproved){
            $error = "This document is VERIFIED. Upload is locked.";
        } else {

            $stmt = $conn->prepare("SELECT doc_name, category, file_type FROM document_types WHERE id=?");
            $stmt->bind_param("i", $doc_type_id);
            $stmt->execute();
            $docRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if(!$docRow){
                $error = "Document type not found.";
            } else {

                $expectedType = strtolower(trim($docRow['file_type'] ?? 'pdf'));

                $allowedTypes = ['pdf','jpg','jpeg','doc','docx','xls','xlsx'];
                if(!in_array($expectedType, $allowedTypes, true)){
                    $expectedType = 'pdf';
                }

                $tmp          = $_FILES['file']['tmp_name'];
                $originalName = $_FILES['file']['name'];
                $size         = (int)$_FILES['file']['size'];

                if($size > 10 * 1024 * 1024){
                    $error = "File too large. Max 10MB.";
                } else {

                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $tmp);
                    finfo_close($finfo);

                    $allowedExts  = [];
                    $allowedMimes = [];

                    switch($expectedType){
                        case 'pdf':
                            $allowedExts  = ['pdf'];
                            $allowedMimes = ['application/pdf'];
                            break;

                        case 'jpg':
                        case 'jpeg':
                            $allowedExts  = ['jpg','jpeg'];
                            $allowedMimes = ['image/jpeg'];
                            break;

                        case 'doc':
                            $allowedExts  = ['doc'];
                            $allowedMimes = ['application/msword','application/octet-stream'];
                            break;

                        case 'docx':
                            $allowedExts  = ['docx'];
                            $allowedMimes = [
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/zip'
                            ];
                            break;

                        case 'xls':
                            $allowedExts  = ['xls'];
                            $allowedMimes = ['application/vnd.ms-excel','application/octet-stream'];
                            break;

                        case 'xlsx':
                            $allowedExts  = ['xlsx'];
                            $allowedMimes = [
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/zip'
                            ];
                            break;
                    }

                    if(!in_array($ext, $allowedExts, true)){
                        $error = "Invalid extension. Required: ".strtoupper($expectedType);
                    } else {
                        if(!in_array($mime, $allowedMimes, true)){
                            $safeMimeBypass = in_array($expectedType, ['doc','xls'], true) && $mime === 'application/octet-stream';
                            $safeZipBypass  = in_array($expectedType, ['docx','xlsx'], true) && $mime === 'application/zip';

                            if(!$safeMimeBypass && !$safeZipBypass){
                                $error = "Invalid file type (MIME). Required: ".strtoupper($expectedType);
                            }
                        }
                    }

                    if($error === ""){

                        $docNameSafe     = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $docRow['doc_name']);
                        $studentNameSafe = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $student_name);
                        $today           = date("Y-m-d");
                        $finalExt        = $ext;

                        $finalFileName = $docNameSafe . "_" . $studentNameSafe . "_" . $today . "." . $finalExt;

                        $base_dir = "../uploads/user_{$student['user_id']}/student_{$student_id}/";
                        if(!file_exists($base_dir)){
                            mkdir($base_dir, 0777, true);
                        }

                        $target_file = $base_dir . $finalFileName;

                        if(file_exists($target_file)){
                            $finalFileName = $docNameSafe . "_" . $studentNameSafe . "_" . $today . "_" . time() . "." . $finalExt;
                            $target_file   = $base_dir . $finalFileName;
                        }

                        $stmt = $conn->prepare("SELECT id, file_path FROM student_documents WHERE student_id=? AND doc_type_id=?");
                        $stmt->bind_param("ii", $student_id, $doc_type_id);
                        $stmt->execute();
                        $existing = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if(move_uploaded_file($tmp, $target_file)){

                            if($existing && !empty($existing['file_path']) && file_exists($existing['file_path'])){
                                @unlink($existing['file_path']);
                            }

                            if($existing){
                                $stmt = $conn->prepare("
                                    UPDATE student_documents
                                    SET file_name=?, file_path=?, uploaded_at=NOW(),
                                        verify_status='pending', verify_message=NULL,
                                        verified_by=NULL, verified_at=NULL
                                    WHERE id=?
                                ");
                                $stmt->bind_param("ssi", $finalFileName, $target_file, $existing['id']);
                                $stmt->execute();
                                notifyFileUpload($conn,$student_id,$student_name,$docRow['doc_name'],$student['user_id'],$existing['id'] ?? null);
                                $stmt->close();
                            } else {
                                $stmt = $conn->prepare("
                                    INSERT INTO student_documents
                                      (student_id, doc_type_id, file_name, file_path, verify_status)
                                    VALUES
                                      (?, ?, ?, ?, 'pending')
                                ");
                                $stmt->bind_param("iiss", $student_id, $doc_type_id, $finalFileName, $target_file);
                                notifyFileUpload($conn,$student_id,$student_name,$docRow['doc_name'],$student['user_id'],$existing['id'] ?? null);
                                $stmt->execute();
                                $stmt->close();
                            }

                            header("Location: ".$_SERVER['PHP_SELF']."?student_id=".$student_id."&agent_id=".$agent_id);
                            exit();

                        } else {
                            $error = "Failed to move file. Check permissions.";
                        }
                    }
                }
            }
        }
    }
}

/* ---------------------------
   4) Fetch checklist
---------------------------- */
$sql = "
SELECT
  dt.id AS doc_type_id,
  dt.doc_name,
  dt.category,
  dt.file_type,
  sd.id AS submitted_id,
  sd.file_name,
  sd.file_path,
  sd.uploaded_at,
  sd.verify_status,
  sd.verify_message,
  sd.verified_at
FROM school_required_docs srd
JOIN document_types dt ON dt.id = srd.doc_type_id
LEFT JOIN student_documents sd
  ON sd.student_id = ? AND sd.doc_type_id = dt.id
WHERE srd.school_id = ?
ORDER BY 
  FIELD(dt.category,
    'Identity',
    'Educational',
    'Language',
    'JAPANESE TRANSLATED DOCUMENTS',
    'Financial',
    'Study Plan',
    'School',
    'Additional'
  ),
  dt.doc_name ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $school_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$groupedDocs = [];
$submittedDocIds = [];
while($row = $res->fetch_assoc()){
    $cat = trim($row['category'] ?? '');
    if($cat === '') $cat = 'Other';
    $groupedDocs[$cat][] = $row;

    if(!empty($row['submitted_id'])){
        $submittedDocIds[] = (int)$row['submitted_id'];
    }
}

/* ---------------------------
   5) Load chat for submitted documents (map doc_id => chat)
---------------------------- */
$chatMap = [];
if(!empty($submittedDocIds)){
    $submittedDocIds = array_values(array_unique($submittedDocIds));
    $in = implode(',', array_map('intval', $submittedDocIds));

    $cq = $conn->query("SELECT document_id, chat FROM document_live_chat WHERE document_id IN ($in)");
    if($cq){
        while($c = $cq->fetch_assoc()){
            $chatMap[(int)$c['document_id']] = $c['chat'] ?? '';
        }
    }
}

require_once '../template/login_status.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-container {
    max-width: 1400px;
    margin: 22px auto;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.card-box {
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
    background: #fff;
    margin-bottom: 16px;
}

.side-box {
    position: sticky;
    top: 16px;
}

.small-muted {
    font-size: 12px;
    color: #6c757d;
}

.badge-submitted {
    background: #198754;
}

.badge-missing {
    background: #dc3545;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 700;
}

.hr-tight {
    margin: 10px 0;
}

.table thead th {
    white-space: nowrap;
}

.file-cell {
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 8px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 12px;
    border: 1px solid transparent;
}

.chip-pending {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #9a3412;
}

.chip-approved {
    background: #ecfdf5;
    border-color: #bbf7d0;
    color: #166534;
}

.chip-disapproved {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

/* Viewer modal */
.viewer-modal .modal-dialog {
    max-width: 980px;
}

.viewer-frame {
    width: 100%;
    height: 70vh;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    overflow: auto;
    position: relative;
}

.viewer-inner {
    transform-origin: top left;
}

.viewer-img {
    max-width: 100%;
    height: auto;
    display: block;
}

/* Toast */
.toast-pop {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 1080;
    min-width: 280px;
    max-width: 420px;
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, .18);
    display: none;
}

/* Chat modal */
.chat-box {
    height: 320px;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f8fafc;
    padding: 10px;
    white-space: pre-wrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
}
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <div class="col-lg-9">

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="m-0">Student Details</h5>
                        <div class="small-muted">School: <strong><?php echo htmlspecialchars($school_name); ?></strong>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="download_zip.php?student_id=<?php echo (int)$student_id; ?>"
                            class="btn btn-sm btn-primary">ZIP FILES</a>
                        <?php if($role === 'admin'){

                            echo '<a href="../admin/dashboard.php" class="btn btn-sm btn-secondary">Dashboard</a>';
                        }elseif($role === 'user'){
                            echo '<a href="../user/dashboard.php" class="btn btn-sm btn-secondary">Dashboard</a>';
                        } ?>
                        <?php if($role === 'admin' && isset($_GET['from'])){
                            if($_GET['from'] === 'preschool'){
                                echo '<a href="preschool_students.php" class="btn btn-sm btn-info">Back</a>';
                            }
                            if($_GET['from'] === 'school_detail'){
                                echo '<a href="school_detail.php?school_id=' . (int)$_GET['school_id'] . '" class="btn btn-sm btn-info">Back</a>';
                            }
                        }else{
                            echo '<a href="view_user.php?user_id='.$agent_id.'" class="btn btn-sm btn-info">Back</a>';
                        } ?>
                        <a href="edit_student.php?student_id=<?php echo $student_id; ?>"
                            class="btn btn-sm btn-warning">Edit</a>
                    </div>
                </div>

                <hr class="hr-tight">

                <div class="row g-2">
                    <div class="col-md-3">
                        <div><b><u>Personal Information</u></b></div>
                        <div><b>Name:</b> <?php echo htmlspecialchars($student_name); ?>
                            (<?php echo htmlspecialchars($student_name_jp); ?>)</div>
                        <div><b>Gender:</b> <?php echo htmlspecialchars($gender); ?></div>
                        <div><b>DOB:</b> <?php echo htmlspecialchars($date_of_birth); ?>
                            (<?php echo htmlspecialchars($age); ?>)</div>
                        <div><b>Nationality:</b> <?php echo htmlspecialchars($nationality); ?></div>
                        <div><b>Intake:</b> <?php echo htmlspecialchars($intake); ?></div>
                        <div><b>School:</b> <?php echo htmlspecialchars($school_name); ?></div>
                        <div><b>Marital Status:</b> <?php echo htmlspecialchars($marital_status); ?></div>
                        <div><b>Email:</b> <?php echo htmlspecialchars($student_email); ?></div>
                        <div><b>Phone:</b> <?php echo htmlspecialchars($phone); ?></div>
                        <div><b>Permanent_address:</b> <?php echo htmlspecialchars($permanent_address); ?></div>
                        <div><b>Current_address:</b> <?php echo htmlspecialchars($current_address); ?></div>
                        <div><b><u>Family Information</u></b></div>
                        <div><b>Father Name:</b> <?php echo htmlspecialchars($father_name); ?></div>
                        <div><b>Father Occupation:</b> <?php echo htmlspecialchars($father_occupation); ?></div>
                        <div><b>Mother Name:</b> <?php echo htmlspecialchars($mother_name); ?></div>
                        <div><b>Mother Occupation:</b> <?php echo htmlspecialchars($mother_occupation); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div>
                            <b><u>Academics Information</u></b>
                        </div>
                        <div><b>Highest Qualification:</b> <?php echo htmlspecialchars($highest_qualification); ?></div>
                        <div><b>Last Institution:</b> <?php echo htmlspecialchars($last_institution_name); ?></div>
                        <div><b>Graduate Year:</b> <?php echo htmlspecialchars($graduation_year); ?></div>
                        <div><b>Academic Gap:</b> <?php echo htmlspecialchars($academic_gap_years); ?></div>
                        <div><b><u>Japanese Language Information</u></b></div>
                        <div><b>Level:</b> <?php echo htmlspecialchars($japanese_level); ?></div>
                        <div><b>Test Type:</b> <?php echo htmlspecialchars($japanese_test_type); ?></div>
                        <div><b>Exam Score:</b> <?php echo htmlspecialchars($japanese_exam_score); ?></div>
                        <div><b>Exam Date:</b> <?php echo htmlspecialchars($japanese_exam_date); ?></div>
                        <div><b>Training Hours:</b> <?php echo htmlspecialchars($japanese_training_hours); ?>
                        </div>
                        <div><b><u>Passport Information</u></b></div>
                        <div><b>Number:</b> <?php echo htmlspecialchars($passport_number); ?></div>
                        <div><b>Issue Date:</b> <?php echo htmlspecialchars($passport_issue_date); ?></div>
                        <div><b>Expiry Date:</b> <?php echo htmlspecialchars($passport_expiry_date); ?></div>

                    </div>
                    <div class="col-md-3">
                        <div><b><u>Sponsor 1 Information</u></b></div>
                        <div><b> Name:</b> <?php echo htmlspecialchars($sponsor_name_1); ?></div>
                        <div><b> Relationship:</b> <?php echo htmlspecialchars($sponsor_relationship_1); ?>
                        </div>
                        <div><b> Occupation:</b> <?php echo htmlspecialchars($sponsor_occupation_1); ?></div>
                        <div><b> Annual Income:</b> <?php echo htmlspecialchars($sponsor_annual_income_1); ?>
                        </div>
                        <div><b> Saving Amount:</b> <?php echo htmlspecialchars($sponsor_saving_amount_1); ?>
                        </div>
                        <div><b><u>Sponsor 2 Information</u></b></div>
                        <div><b>Name:</b> <?php echo htmlspecialchars($sponsor_name_2); ?></div>
                        <div><b>Relationship:</b> <?php echo htmlspecialchars($sponsor_relationship_2); ?>
                        </div>
                        <div><b>Occupation:</b> <?php echo htmlspecialchars($sponsor_occupation_2); ?></div>
                        <div><b>Annual Income:</b> <?php echo htmlspecialchars($sponsor_annual_income_2); ?>
                        </div>
                        <div><b>Saving Amount:</b> <?php echo htmlspecialchars($sponsor_saving_amount_2); ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div><b><u>Photo</u></b></div>
                        <div><?php echo $photo_html; ?></div>
                        <div><b>Information:</b> <?php echo htmlspecialchars($information); ?></div>
                        <div><b>Career Path:</b> <?php echo htmlspecialchars($career_path); ?></div>
                    </div>
                </div>
            </div>

            <?php if(!empty($success)): ?>
            <div id="toastMsg" class="toast-pop" style="background:#198754;color:#fff;">
                <div style="font-weight:900;">Success</div>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($error)): ?>
            <div id="toastMsg" class="toast-pop" style="background:#dc3545;color:#fff;">
                <div style="font-weight:900;">Error</div>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <?php if(empty($groupedDocs)): ?>
            <div class="card-box">
                <div class="alert alert-warning mb-0 py-2">No required documents found for this school.</div>
            </div>
            <?php else: ?>

            <?php foreach($groupedDocs as $category => $docs): ?>
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;"><?php echo htmlspecialchars($category); ?> Documents
                    </h6>
                    <span class="badge badge-soft"><?php echo count($docs); ?> items</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:26%;">Document</th>
                                <th style="width:18%;">Status</th>
                                <th style="width:31%;">Uploaded File</th>
                                <th style="width:25%;">Upload / Verify</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php foreach($docs as $row):
                  $docId  = (int)($row['submitted_id'] ?? 0);
                  $ft     = strtolower($row['file_type'] ?? 'pdf');
                  $verify = strtolower($row['verify_status'] ?? 'pending');
                  if($verify !== 'approved' && $verify !== 'disapproved') $verify = 'pending';

                  $chipClass = $verify === 'approved' ? 'chip-approved' : ($verify === 'disapproved' ? 'chip-disapproved' : 'chip-pending');
                  $chipText  = strtoupper($verify);

                  switch($ft){
                    case 'jpg':
                    case 'jpeg':
                      $accept = "image/jpeg";
                      break;
                    case 'doc':
                      $accept = ".doc,application/msword";
                      break;
                    case 'docx':
                      $accept = ".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
                      break;
                    case 'xls':
                      $accept = ".xls,application/vnd.ms-excel";
                      break;
                    case 'xlsx':
                      $accept = ".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
                      break;
                    default:
                      $accept = "application/pdf,.pdf";
                      break;
                  }

                  $chatText = $docId > 0 ? ($chatMap[$docId] ?? '') : '';
                ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['doc_name']); ?></td>

                                <td>
                                    <?php if($row['submitted_id']): ?>
                                    <span class="badge badge-submitted">SUBMITTED</span>
                                    <div class="small-muted">On:
                                        <?php echo htmlspecialchars($row['uploaded_at']); ?>
                                    </div>

                                    <div class="mt-1">
                                        <span
                                            class="status-chip <?php echo $chipClass; ?>"><?php echo $chipText; ?></span>
                                    </div>

                                    <?php if(!empty($row['verify_message'])): ?>
                                    <div class="small-muted mt-1"><b>Msg:</b>
                                        <?php echo htmlspecialchars($row['verify_message']); ?></div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="badge badge-missing">NOT SUBMITTED</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if($row['submitted_id']): ?>
                                    <div class="file-cell" title="<?php echo htmlspecialchars($row['file_name']); ?>">
                                        <?php echo htmlspecialchars($row['file_name']); ?>
                                    </div>

                                    <!-- hidden chat content -->
                                    <script type="text/plain" id="chatData_<?php echo $docId; ?>">
                                        <?php echo htmlspecialchars($chatText); ?></script>

                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <button type="button" class="btn btn-sm btn-success btnViewFile"
                                            data-docid="<?php echo $docId; ?>"
                                            data-filetype="<?php echo htmlspecialchars($ft); ?>"
                                            data-fileurl="<?php echo htmlspecialchars($row['file_path']); ?>"
                                            data-docname="<?php echo htmlspecialchars($row['doc_name']); ?>"
                                            data-verifystatus="<?php echo htmlspecialchars($verify); ?>">
                                            View File
                                        </button>

                                        <a class="btn btn-sm btn-outline-dark"
                                            href="<?php echo htmlspecialchars($row['file_path']); ?>" download>
                                            Download
                                        </a>

                                        <button type="button" class="btn btn-sm btn-outline-primary btnLiveChat"
                                            data-docid="<?php echo $docId; ?>"
                                            data-docname="<?php echo htmlspecialchars($row['doc_name']); ?>">
                                            Live Chat
                                        </button>
                                    </div>

                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if($row['submitted_id'] && $verify === 'approved'): ?>
                                    <div class="p-2 rounded" style="background:#ecfdf5;border:1px solid #bbf7d0;">
                                        <div style="font-weight:900;color:#166534;">Verified ✅</div>
                                        <div class="small-muted">Upload locked (approved).</div>
                                    </div>
                                    <?php else: ?>

                                    <?php if($role === 'admin' && $row['submitted_id']): ?>
                                    <div class="d-grid gap-1 mb-2">
                                        <button type="button" class="btn btn-sm btn-dark btnOpenVerify"
                                            data-docid="<?php echo $docId; ?>" data-action="approved"
                                            data-docname="<?php echo htmlspecialchars($row['doc_name']); ?>">
                                            Approve
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger btnOpenVerify"
                                            data-docid="<?php echo $docId; ?>" data-action="disapproved"
                                            data-docname="<?php echo htmlspecialchars($row['doc_name']); ?>">
                                            Disapprove
                                        </button>
                                    </div>
                                    <?php endif; ?>

                                    <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                                        <input type="hidden" name="doc_type_id"
                                            value="<?php echo (int)$row['doc_type_id']; ?>">
                                        <input type="file" name="file" class="form-control form-control-sm"
                                            accept="<?php echo $accept; ?>" required>
                                        <button type="submit" name="upload_doc"
                                            class="btn btn-sm btn-primary">Upload</button>
                                    </form>

                                    <div class="small-muted mt-1">
                                        Required: <strong><?php echo strtoupper(htmlspecialchars($ft)); ?></strong>
                                    </div>

                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <!-- RIGHT SIDE -->
        <div class="col-lg-3">
            <div class="card-box side-box">
                <h6 class="mb-2" style="font-weight:800;">About this page</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.5;">
                    Upload required documents and (Admin) verify them. Approved documents get locked.
                </div>

                <hr class="my-3">

                <div class="mb-2" style="font-weight:800;">Tips</div>
                <div class="d-flex flex-column gap-2">
                    <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                        Use <b>Live Chat</b> per file to communicate between admin and agent.
                    </div>
                    <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                        Word/Excel preview not supported in browser — use <b>Download</b>.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- =========================
     CENTER VIEWER MODAL (same)
========================== -->
<div class="modal fade viewer-modal" id="fileViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header py-2">
                <div>
                    <div style="font-weight:900;" id="viewerTitle">Document Viewer</div>
                    <div class="text-muted" style="font-size:12px;" id="viewerSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomOutBtn">-</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomResetBtn">Reset</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomInBtn">+</button>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-sm btn-outline-dark" id="viewerDownloadBtn" download>Download</a>

                        <?php if($role === 'admin'): ?>
                        <button type="button" class="btn btn-sm btn-dark" id="viewerApproveBtn">Approve</button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                            id="viewerDisapproveBtn">Disapprove</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="viewer-frame">
                    <div class="viewer-inner" id="viewerInner">

                        <iframe id="viewerPdf" src="" style="display:none;width:100%;height:70vh;border:0;"></iframe>
                        <img id="viewerImg" src="" alt="Image" class="viewer-img" style="display:none;">

                        <div id="viewerUnsupported" style="display:none;padding:14px;">
                            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e5e7eb;">
                                <div style="font-weight:900;">Preview not available</div>
                                <div class="text-muted" style="font-size:12px;">
                                    This file type cannot be previewed in browser. Please download it.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     VERIFY CONFIRM MODAL (same)
========================== -->
<div class="modal fade modal-mini" id="verifyConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:900;" id="verifyTitle">Verify</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="verify_action" value="1">
                    <input type="hidden" name="doc_id" id="verifyDocId" value="">
                    <input type="hidden" name="action" id="verifyAction" value="">

                    <div class="p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e5e7eb;">
                        <div style="font-weight:900;" id="verifyDocName">Document</div>
                        <div class="text-muted" style="font-size:12px;">
                            Are you sure you want to <b id="verifyActionText">approve</b> this file?
                        </div>
                    </div>

                    <label class="form-label" style="font-weight:900;">Message / Comment</label>
                    <textarea name="verify_message" class="form-control form-control-sm" rows="3"
                        placeholder="Write reason / comment (optional)"></textarea>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-dark" id="verifySubmitBtn">Confirm</button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- =========================
     LIVE CHAT MODAL (NEW)
========================== -->
<div class="modal fade" id="liveChatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header py-2">
                <div>
                    <div style="font-weight:900;" id="chatTitle">Live Chat</div>
                    <div class="text-muted" style="font-size:12px;">Admin ↔ Agent chat for this file</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="chat-box" id="chatHistory">No chat yet...</div>

                <form method="POST" class="mt-2">
                    <input type="hidden" name="chat_send" value="1">
                    <input type="hidden" name="chat_doc_id" id="chatDocId" value="">

                    <label class="form-label fw-bold mb-1">New Message</label>
                    <textarea name="chat_message" class="form-control form-control-sm" rows="3"
                        placeholder="Type message..." required></textarea>

                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-sm btn-primary">Send</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // toast
    const t = document.getElementById("toastMsg");
    if (t) {
        t.style.display = "block";
        setTimeout(() => t.style.display = "none", 3500);
    }

    const fileViewerModal = new bootstrap.Modal(document.getElementById('fileViewerModal'));
    const verifyConfirmModal = new bootstrap.Modal(document.getElementById('verifyConfirmModal'));
    const liveChatModal = new bootstrap.Modal(document.getElementById('liveChatModal'));

    let currentZoom = 1;
    let currentDocId = 0;
    let currentDocName = "";
    let currentVerifyStatus = "pending";

    const viewerInner = document.getElementById('viewerInner');
    const viewerPdf = document.getElementById('viewerPdf');
    const viewerImg = document.getElementById('viewerImg');
    const viewerUnsupported = document.getElementById('viewerUnsupported');

    const viewerTitle = document.getElementById('viewerTitle');
    const viewerSub = document.getElementById('viewerSub');
    const viewerDownloadBtn = document.getElementById('viewerDownloadBtn');

    function applyZoom() {
        viewerInner.style.transform = "scale(" + currentZoom + ")";
    }

    function resetZoom() {
        currentZoom = 1;
        applyZoom();
    }

    document.getElementById('zoomInBtn')?.addEventListener('click', () => {
        currentZoom = Math.min(3, currentZoom + 0.15);
        applyZoom();
    });
    document.getElementById('zoomOutBtn')?.addEventListener('click', () => {
        currentZoom = Math.max(0.5, currentZoom - 0.15);
        applyZoom();
    });
    document.getElementById('zoomResetBtn')?.addEventListener('click', resetZoom);

    function setViewerMode(mode) {
        viewerPdf.style.display = "none";
        viewerImg.style.display = "none";
        viewerUnsupported.style.display = "none";
        viewerPdf.src = "";
        viewerImg.src = "";

        if (mode === "pdf") viewerPdf.style.display = "block";
        if (mode === "img") viewerImg.style.display = "block";
        if (mode === "unsupported") viewerUnsupported.style.display = "block";
    }

    // open viewer
    document.querySelectorAll('.btnViewFile').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.fileurl || '';
            const ft = (btn.dataset.filetype || 'pdf').toLowerCase();
            currentDocId = parseInt(btn.dataset.docid || '0', 10);
            currentDocName = btn.dataset.docname || 'Document';
            currentVerifyStatus = btn.dataset.verifystatus || 'pending';

            viewerTitle.textContent = currentDocName;
            viewerSub.textContent = "Type: " + ft.toUpperCase() + " • Status: " +
                currentVerifyStatus.toUpperCase();
            viewerDownloadBtn.href = url;

            resetZoom();

            if (ft === 'jpg' || ft === 'jpeg') {
                setViewerMode("img");
                viewerImg.src = url;
            } else if (ft === 'pdf') {
                setViewerMode("pdf");
                viewerPdf.src = url;
            } else {
                setViewerMode("unsupported");
            }

            fileViewerModal.show();
        });
    });

    // verify modal open
    function openVerifyConfirm(action) {
        if (!currentDocId) return;

        document.getElementById('verifyDocId').value = currentDocId;
        document.getElementById('verifyAction').value = action;

        document.getElementById('verifyTitle').textContent = (action === 'approved') ? "Approve Document" :
            "Disapprove Document";
        document.getElementById('verifyDocName').textContent = currentDocName;
        document.getElementById('verifyActionText').textContent = (action === 'approved') ? "approve" :
            "disapprove";

        const submitBtn = document.getElementById('verifySubmitBtn');
        submitBtn.textContent = (action === 'approved') ? "Confirm Approve" : "Confirm Disapprove";
        submitBtn.className = "btn btn-sm " + ((action === 'approved') ? "btn-dark" : "btn-danger");

        verifyConfirmModal.show();
    }

    document.getElementById('viewerApproveBtn')?.addEventListener('click', () => openVerifyConfirm(
        'approved'));
    document.getElementById('viewerDisapproveBtn')?.addEventListener('click', () => openVerifyConfirm(
        'disapproved'));

    document.querySelectorAll('.btnOpenVerify').forEach(b => {
        b.addEventListener('click', () => {
            currentDocId = parseInt(b.dataset.docid || '0', 10);
            currentDocName = b.dataset.docname || 'Document';
            openVerifyConfirm((b.dataset.action || 'approved').toLowerCase());
        });
    });

    // LIVE CHAT open
    document.querySelectorAll('.btnLiveChat').forEach(b => {
        b.addEventListener('click', () => {
            const docId = parseInt(b.dataset.docid || '0', 10);
            const docName = b.dataset.docname || 'Document';

            document.getElementById('chatTitle').textContent = "Live Chat — " + docName;
            document.getElementById('chatDocId').value = docId;

            const raw = document.getElementById('chatData_' + docId)?.textContent || '';
            const history = raw.trim() ? raw : "No chat yet...";
            const box = document.getElementById('chatHistory');
            box.textContent = history;

            // auto scroll bottom
            setTimeout(() => {
                box.scrollTop = box.scrollHeight;
            }, 50);

            liveChatModal.show();
        });
    });

    // auto open chat after redirect (?chat=1&doc=ID)
    const urlParams = new URLSearchParams(window.location.search);
    const docAuto = parseInt(urlParams.get('doc') || '0', 10);
    if (urlParams.get('chat') === '1' && docAuto > 0) {
        const btn = document.querySelector('.btnLiveChat[data-docid="' + docAuto + '"]');
        if (btn) btn.click();
    }

});
</script>