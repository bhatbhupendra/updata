<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../config/db.php';

if(isset($_GET['user_id'])){
    $user_id = (int)$_GET['user_id'];
}else{
    header("Location: ../login.php");
    exit();
}


/* -------------------------
ACTIONS: UPDATE / RESTORE STUDENT
--------------------------*/
$toast_success = "";
$toast_error = "";

//messages

if(isset($_GET['st_restore']) && $_GET['st_restore'] == "1"){
    $toast_success = "Student restored from recycle bin successfully!";
}

/* =========================
   1) Restore student (with safety check)======= */
/* =========================
   1) Restore student (with safety check)
========================= */
if(isset($_POST['restore_student'])){
    $student_id = (int)($_POST['student_id'] ?? 0);

    // use your actual recycle bin user id
    $recycle_bin_user_id = 0;

    if($student_id <= 0){
        $toast_error = "Invalid student.";
    } else {

        // student must currently be inside recycle bin
        $chk = $conn->prepare("
            SELECT id, user_id, recycle_bin_user_id
            FROM students
            WHERE id=? AND user_id=?
            LIMIT 1
        ");
        $chk->bind_param("ii", $student_id, $recycle_bin_user_id);
        $chk->execute();
        $student_row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if(!$student_row){
            $toast_error = "Student not found in recycle bin.";
        } else {
            $restore_user_id = (int)($student_row['recycle_bin_user_id'] ?? 0);

            if($restore_user_id <= 0){
                $toast_error = "Original user id not found for this student.";
            } else {
                // confirm original user still exists
                $u = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
                $u->bind_param("i", $restore_user_id);
                $u->execute();
                $user_exists = $u->get_result()->fetch_assoc();
                $u->close();

                if(!$user_exists){
                    $toast_error = "Original user no longer exists, so student cannot be restored.";
                } else {
                    $upd = $conn->prepare("
                        UPDATE students
                        SET user_id = ?,
                            recycle_bin_user_id = NULL
                        WHERE id = ? AND user_id = ?
                        LIMIT 1
                    ");
                    $upd->bind_param("iii", $restore_user_id, $student_id, $recycle_bin_user_id);

                    if($upd->execute()){
                        $upd->close();
                        header("Location: ".$_SERVER['PHP_SELF']."?user_id=0&restored=1");
                        exit();
                    } else {
                        $toast_error = "Restore failed: ".$upd->error;
                        $upd->close();
                    }
                }
            }
        }
    }
}



if(isset($_GET['success']) && $_GET['success'] == "1"){
    $toast_success = "User updated successfully!";
}

/* -------------------------
   LOAD AGENT
--------------------------*/
$user_result = $conn->query("SELECT * FROM users WHERE id='$user_id' AND role='admin'");
$user_data = $user_result ? $user_result->fetch_assoc() : null;
if(!$user_data){
    die("User not found.");
}

/* -------------------------
   FILTERS (INTAKE + SCHOOL)
--------------------------*/

// Load available intakes for this agent (latest first)
$intakes = [];
$qIntake = $conn->prepare("
    SELECT DISTINCT intake
    FROM students
    WHERE user_id=?
      AND intake IS NOT NULL
      AND intake <> ''
    ORDER BY intake DESC
");
$qIntake->bind_param("i", $user_id);
$qIntake->execute();
$rIntake = $qIntake->get_result();
while($row = $rIntake->fetch_assoc()){
    $intakes[] = $row['intake'];
}
$qIntake->close();

// intake from URL (optional). If missing -> default latest
$selected_intake = trim($_GET['intake'] ?? '');

// Default to latest intake (first item because we ordered DESC)
if($selected_intake === ''){
    $selected_intake = $intakes[0] ?? '';   // if no intakes exist, stays ''
}

$selected_school = $_GET['school_id'] ?? 'all';          // all / numeric id


// Load schools for this agent (dropdown options)
$schools = [];
$qSchools = $conn->prepare("
    SELECT DISTINCT sc.id, sc.name
    FROM students s
    JOIN schools sc ON sc.id = s.school_id
    WHERE s.user_id=? AND s.school_id IS NOT NULL AND s.school_id<>0
    ORDER BY sc.name ASC
");
$qSchools->bind_param("i", $user_id);
$qSchools->execute();
$rSchools = $qSchools->get_result();
while($row = $rSchools->fetch_assoc()){
    $schools[] = $row;
}
$qSchools->close();

// Build filtered students query (prepared)
$sql = "
    SELECT s.*, sc.name AS school_name
    FROM students s
    LEFT JOIN schools sc ON sc.id = s.school_id
    WHERE s.user_id = ?
";
$types = "i";
$params = [$user_id];

if($selected_intake !== '' && $selected_intake !== 'all'){
    $sql .= " AND s.intake = ? ";
    $types .= "s";
    $params[] = $selected_intake;
}

if($selected_school !== 'all' && ctype_digit((string)$selected_school)){
    $sql .= " AND s.school_id = ? ";
    $types .= "i";
    $params[] = (int)$selected_school;
}

$sql .= " ORDER BY s.id DESC ";

$stmt = $conn->prepare($sql);
if(!$stmt) die("Prepare failed: ".$conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$user_students = $stmt->get_result();
// IMPORTANT: keep $stmt open until after table+modal data is built

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

.table thead th {
    white-space: nowrap;
}

.doc-list {
    font-size: 12px;
    line-height: 1.35;
    max-height: 160px;
    overflow: auto;
}

.doc-ok {
    color: #198754;
    font-weight: 800;
}

.doc-miss {
    color: #dc3545;
    font-weight: 800;
}

.student-name {
    font-weight: 800;
    font-size: 13px;
}

.student-meta {
    color: #6c757d;
    font-size: 12px;
}

.thumb {
    width: 130px;
    /* height: 150px; */
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
    background: #fff;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 700;
}

/* Bottom-right mini modal */
.modal-mini .modal-dialog {
    position: fixed;
    right: 16px;
    bottom: 16px;
    margin: 0;
    width: 430px;
    max-width: calc(100vw - 32px);
}

.modal-mini .modal-content {
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, .2);
}

/* Bottom-right toast */
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
    animation: slideIn .22s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateY(10px);
        opacity: 0;
    }

    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.small-checks label {
    font-size: 12px;
}

.filter-bar .form-select,
.filter-bar .form-control {
    padding: .35rem .55rem;
    font-size: 12.5px;
}
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT 70% -->
        <div class="col-lg-9">

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="m-0">Recycle Bin</h3>
                        <div class="text-muted" style="font-size:12px;">This is recycle bin page for <b>Restoring</b>
                            deleted students</div>
                    </div>
                    <?php
                        if($_SESSION['role'] === 'admin') {
                            echo '<a href="../admin/dashboard.php" class="btn btn-secondary btn-sm">← Dashboard</a>';
                        }
                    ?>

                </div>
            </div>

            <!-- Agent Info -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="m-0" style="font-weight:800;">Agent Information</h6>
                        <div class="text-muted" style="font-size:12px;">User ID: <?php echo (int)$user_id; ?></div>
                    </div>
                    <span class="badge badge-soft">Role: ADMIN(RECYCLE BIN)</span>
                </div>
                <hr class="my-2">
                <div><b>Name:</b> <?php echo htmlspecialchars($user_data['name']); ?></div>
                <div><b>Email:</b> <?php echo htmlspecialchars($user_data['email']); ?></div>
            </div>

            <!-- Students -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;">Students List</h6>

                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#exportExcelModal">
                            Export to Excel
                        </button>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <form method="GET" class="filter-bar row g-2 align-items-end mb-2">
                    <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">

                    <div class="col-md-4">
                        <label class="form-label mb-1" style="font-weight:800;">Intake</label>
                        <select name="intake" class="form-select">
                            <?php if(empty($intakes)): ?>
                            <option value="all">All intake</option>
                            <?php else: ?>
                            <option value="all" <?php echo ($selected_intake === 'all' ? 'selected' : ''); ?>>
                                All intake
                            </option>
                            <?php foreach($intakes as $in): ?>
                            <option value="<?php echo htmlspecialchars($in); ?>"
                                <?php echo ($selected_intake === $in ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($in); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label mb-1" style="font-weight:800;">School</label>
                        <select name="school_id" class="form-select">
                            <option value="all" <?php echo ($selected_school==='all'?'selected':''); ?>>All schools
                            </option>
                            <?php foreach($schools as $sc): ?>
                            <option value="<?php echo (int)$sc['id']; ?>"
                                <?php echo ((string)$selected_school===(string)$sc['id']?'selected':''); ?>>
                                <?php echo htmlspecialchars($sc['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-grid">
                        <button class="btn btn-sm btn-primary" type="submit">Apply Filter</button>
                    </div>

                    <div class="col-12">
                        <div class="text-muted" style="font-size:12px;">
                            Showing:
                            <b><?php echo ($selected_intake==='all'?'All intakes':htmlspecialchars($selected_intake)); ?></b>
                            /
                            <b><?php echo ($selected_school==='all'?'All schools':'Selected school'); ?></b>
                            <a class="ms-2"
                                href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?user_id=<?php echo (int)$user_id; ?>">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:55px;">#</th>
                                <th>Student</th>
                                <th style="width:170px;">School</th>
                                <th style="width:360px;">Documents</th>
                                <th style="width:180px;">Photo</th>
                                <th style="width:190px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$count = 1;

// store filtered students for export modal
$studentListForModal = [];
$user_students->data_seek(0);
while($tmp = $user_students->fetch_assoc()){
    $studentListForModal[] = [
        'id' => (int)$tmp['id'],
        'name' => $tmp['student_name'] ?? '',
        'jp' => $tmp['student_name_jp'] ?? '',
        'school' => $tmp['school_name'] ?? ''
    ];
}
$user_students->data_seek(0);

if($user_students && $user_students->num_rows > 0){

    while($row = $user_students->fetch_assoc()){

        $student_id = (int)$row['id'];
        $student_name = htmlspecialchars($row['student_name'] ?? '');
        $student_name_jp = htmlspecialchars($row['student_name_jp'] ?? '');
        $gender = htmlspecialchars($row['gender'] ?? '');
        $nationality = htmlspecialchars($row['nationality'] ?? '');
        $intake = htmlspecialchars($row['intake'] ?? '');
        $age = htmlspecialchars($row['age'] ?? '');
        $school_id = (int)($row['school_id'] ?? 0);
        $school_name = htmlspecialchars($row['school_name'] ?? '');

        // Docs status
        $doc_output = "<div class='doc-list'>";
        if($school_id > 0){
            $doc_query = "
                SELECT dt.doc_name, sd.id AS submitted_id
                FROM school_required_docs srd
                JOIN document_types dt ON dt.id = srd.doc_type_id
                LEFT JOIN student_documents sd
                  ON sd.student_id = $student_id AND sd.doc_type_id = dt.id
                WHERE srd.school_id = $school_id
                ORDER BY dt.doc_name ASC
            ";
            $docs_result = $conn->query($doc_query);

            if($docs_result && $docs_result->num_rows > 0){
                while($d = $docs_result->fetch_assoc()){
                    $doc_name = htmlspecialchars($d['doc_name']);
                    $doc_output .= $d['submitted_id']
                        ? "<div class='doc-ok'>✔ {$doc_name}</div>"
                        : "<div class='doc-miss'>✖ {$doc_name}</div>";
                }
            } else {
                $doc_output .= "<span class='text-muted'>No required documents set.</span>";
            }
        } else {
            $doc_output .= "<span class='text-muted'>No school selected.</span>";
        }
        $doc_output .= "</div>";

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

        $photo_html = "<span class='text-muted'>—</span>";
        if(!empty($img_url)){
            $photo_html = "<img src='".htmlspecialchars($img_url)."' class='thumb' alt='Student Photo'>";
        }

        echo "
        <tr>
            <td>{$count}</td>
            <td>
                <div class='student-name'>
                    {$student_name}
                    ".(!empty($student_name_jp) ? " <span class='text-primary'>({$student_name_jp})</span>" : "")."
                </div>
                <div class='student-meta'>
                    ".(!empty($gender) ? "<span class='badge badge-soft me-1'>Gender: {$gender}</span>" : "")."
                    ".(!empty($nationality) ? "<span class='badge badge-soft me-1'>Nationality: {$nationality}</span>" : "")."
                    ".(!empty($intake) ? "<span class='badge badge-soft me-1'>Intake: {$intake}</span>" : "")."
                    ".(!empty($age) ? "Age: {$age}" : "")."
                </div>
            </td>
            <td>{$school_name}</td>
            <td>{$doc_output}</td>
            <td class='text-center'>{$photo_html}</td>
            <td>
                <a href='student_file.php?student_id={$student_id}&agent_id={$user_id}' class='btn btn-sm btn-success w-100 mb-1'>
                    View Student
                </a>
                <button type='button'
                    class='btn btn-sm btn-outline-success w-100 mt-1 btn-restore-student'
                    data-student-id='{$student_id}'
                    data-student-name='{$student_name}'>
                            Restore Student
                            </button>
                            </td>
                            </tr>
                            ";
                            $count++;
                            }

                            } else {
                            echo "<tr>
                                <td colspan='6' class='text-center'>No students found for this filter<b>(No Students in Recycling Bin)</b>.</td>
                            </tr>";
                            }

                            // close stmt after result use
                            $stmt->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- RIGHT 30% -->
        <div class="col-lg-3">
            <div class="card-box side-box">
                <h6 class="mb-2" style="font-weight:800;">About this page</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.5;">
                    This page is <b>Recycle Bin</b> where deleted students are stored.
                </div>

                <hr class="my-3">

                <div class="mb-2" style="font-weight:800;">Tips</div>
                <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                    Use <b>Restore</b> to restore the student profile.
                </div>
            </div>
        </div>

    </div>
</div>



<!-- ========================= EXPORT EXCEL MODAL (BR) ========================= -->
<div class="modal fade modal-mini" id="exportExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:800;">Export Students to Excel</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="export_students_excel.php">
                <div class="modal-body">

                    <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">

                    <!-- Select Students -->
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div style="font-weight:800;">Select Students</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllStudents">
                            <label class="form-check-label" for="selectAllStudents">Select all</label>
                        </div>
                    </div>

                    <div class="border rounded p-2" style="max-height:150px; overflow:auto;">
                        <?php if(empty($studentListForModal)): ?>
                        <div class="text-muted">No students found.</div>
                        <?php else: ?>
                        <?php foreach($studentListForModal as $st): ?>
                        <div class="form-check">
                            <input class="form-check-input studentChk" type="checkbox" name="student_ids[]"
                                value="<?php echo (int)$st['id']; ?>" id="st_<?php echo (int)$st['id']; ?>">
                            <label class="form-check-label" for="st_<?php echo (int)$st['id']; ?>">
                                <?php echo htmlspecialchars($st['name']); ?>
                                <?php if(!empty($st['jp'])): ?>
                                <span class="text-primary">(<?php echo htmlspecialchars($st['jp']); ?>)</span>
                                <?php endif; ?>
                                <?php if(!empty($st['school'])): ?>
                                <span class="text-muted">— <?php echo htmlspecialchars($st['school']); ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <hr class="my-2">

                    <!-- Select Fields -->
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div style="font-weight:800;">Select Fields</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllFields">
                            <label class="form-check-label" for="selectAllFields">All fields</label>
                        </div>
                    </div>

                    <div class="row g-1 small-checks">
                        <?php
              $fieldOptions = [
                'student_name' => 'Name',
                'student_name_jp' => 'Name (JP)',
                'gender' => 'Gender',
                'date_of_birth' => 'DOB',
                'age' => 'Age',
                'nationality' => 'Nationality',
                'phone' => 'Phone',
                'passport_number' => 'Passport No.',
                'permanent_address' => 'Permanent Address',
                'current_address' => 'Current Address',
                'highest_qualification' => 'Highest Qualification',
                'last_institution_name' => 'Last Institution',
                'graduation_year' => 'Graduation Year',
                'japanese_level' => 'Japanese Level',
                'japanese_test_type' => 'Japanese Test',
                'japanese_training_hours' => 'Training Hours',
                'sponsor_name' => 'Sponsor Name',
                'sponsor_relationship' => 'Sponsor Relationship',
                'intake' => 'Intake',
              ];
            ?>
                        <?php foreach($fieldOptions as $key => $label): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input fieldChk" type="checkbox" name="fields[]"
                                    value="<?php echo htmlspecialchars($key); ?>"
                                    id="f_<?php echo htmlspecialchars($key); ?>"
                                    <?php echo in_array($key, ['student_name','date_of_birth','gender','nationality']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="f_<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-2">

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_photo" value="1" id="includePhoto"
                            checked>
                        <label class="form-check-label" for="includePhoto">Include Student Photo (latest JPG
                            document)</label>
                    </div>

                    <div class="text-muted mt-2" style="font-size:12px;">
                        Photos will be embedded in Excel (if found). If not found, photo cell remains blank.
                    </div>

                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-dark">Export</button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Toast -->
<?php if(!empty($toast_success)): ?>
<div id="toastMsg" class="toast-pop" style="background:#198754; color:#fff;">
    <div style="font-weight:900;">Success</div>
    <div><?php echo htmlspecialchars($toast_success); ?></div>
</div>
<?php endif; ?>

<?php if(!empty($toast_error)): ?>
<div id="toastMsg" class="toast-pop" style="background:#dc3545; color:#fff;">
    <div style="font-weight:900;">Error</div>
    <div><?php echo htmlspecialchars($toast_error); ?></div>
</div>
<?php endif; ?>



<!-- restore student -->

<div id="restoreStudentPopup" class="toast-pop" style="background:#111827; color:#fff;">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div style="font-weight:900;">Restore student?</div>
            <div id="restoreStudentText" style="font-size:12px; opacity:.9;"></div>
        </div>
        <button type="button" id="restoreStudentClose" class="btn btn-sm btn-outline-light"
            style="border-radius:10px; padding:.15rem .45rem;">×</button>
    </div>

    <form method="POST" class="mt-2 d-flex gap-2 align-items-center">
        <input type="hidden" name="student_id" id="restoreStudentId" value="">
        <button type="submit" name="restore_student" class="btn btn-sm btn-danger" style="font-weight:800;">
            Yes, Restore
        </button>
        <button type="button" id="restoreStudentCancel" class="btn btn-sm btn-outline-light">
            Cancel
        </button>
    </form>

    <div class="text-muted mt-2" style="font-size:11.5px; opacity:.85;">
        This action cannot be undone.
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {

    // Toast show
    const t = document.getElementById("toastMsg");
    if (t) {
        t.style.display = "block";
        setTimeout(() => {
            t.style.display = "none";
        }, 3500);
    }

    // Students select all
    const selectAllStudents = document.getElementById('selectAllStudents');
    const studentChks = document.querySelectorAll('.studentChk');
    if (selectAllStudents) {
        selectAllStudents.addEventListener('change', () => {
            studentChks.forEach(chk => chk.checked = selectAllStudents.checked);
        });
    }

    // Fields select all
    const selectAllFields = document.getElementById('selectAllFields');
    const fieldChks = document.querySelectorAll('.fieldChk');
    if (selectAllFields) {
        selectAllFields.addEventListener('change', () => {
            fieldChks.forEach(chk => chk.checked = selectAllFields.checked);
        });
    }


    // Restore student popup (bottom-right)
    const popup = document.getElementById('restoreStudentPopup');
    const txt = document.getElementById('restoreStudentText');
    const hid = document.getElementById('restoreStudentId');
    const closeBtn = document.getElementById('restoreStudentClose');
    const cancelBtn = document.getElementById('restoreStudentCancel');

    function openPopup(name, id) {
        if (!popup) return;
        txt.textContent = `Student: ${name}`;
        hid.value = id;
        popup.style.display = 'block';
    }

    function closePopup() {
        if (!popup) return;
        popup.style.display = 'none';
        hid.value = '';
    }

    document.querySelectorAll('.btn-restore-student').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-student-id');
            const name = btn.getAttribute('data-student-name') || 'this student';
            openPopup(name, id);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closePopup);
    if (cancelBtn) cancelBtn.addEventListener('click', closePopup);

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>