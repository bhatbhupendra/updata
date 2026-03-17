<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$toast_success = "";
$toast_error = "";

/* ---------------------------
   Find Pre School ID
---------------------------- */
$preSchoolId = 0;
$preQ = $conn->query("SELECT id FROM schools WHERE LOWER(name)='PRE-SCHOOL' LIMIT 1");
if($preQ && $preQ->num_rows > 0){
    $preSchoolId = (int)($preQ->fetch_assoc()['id'] ?? 0);
}
if($preSchoolId <= 0){
    die("Pre School not found. Please create a school named exactly: Pre School");
}

/* ---------------------------
   Filters
---------------------------- */
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;

/* ---------------------------
   Load agents (users with role=user)
---------------------------- */
$agents = [];
$aQ = $conn->query("SELECT id, name, email FROM users WHERE role='user' ORDER BY name ASC");
while($r = $aQ->fetch_assoc()){
    $agents[] = $r;
}

/* ---------------------------
   Load destination schools (exclude Pre School)
---------------------------- */
$schools = [];
$sQ = $conn->query("SELECT id, name FROM schools WHERE id <> $preSchoolId ORDER BY name ASC");
while($r = $sQ->fetch_assoc()){
    $schools[] = $r;
}

/* ---------------------------
   ACTION: MOVE STUDENT(S) TO SCHOOL
---------------------------- */
if(isset($_POST['move_students'])){
    $dest_school_id = (int)($_POST['dest_school_id'] ?? 0);
    $student_ids = $_POST['student_ids'] ?? [];

    if($dest_school_id <= 0){
        $toast_error = "Please select destination school.";
    } elseif(empty($student_ids)) {
        $toast_error = "Please select at least one student.";
    } else {
        $ids = array_map('intval', $student_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Only move students currently in Pre School
        $sql = "UPDATE students
                SET school_id=?
                WHERE school_id=? AND id IN ($placeholders)";

        $stmt = $conn->prepare($sql);
        if(!$stmt){
            $toast_error = "Prepare failed: ".$conn->error;
        } else {
            // bind params dynamically (all integers)
            $types = str_repeat('i', 2 + count($ids));
            $params = array_merge([$dest_school_id, $preSchoolId], $ids);

            // mysqli bind_param requires references
            $bind = [];
            $bind[] = $types;
            foreach($params as $k => $v){
                $bind[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);

            if($stmt->execute()){
                $moved = $stmt->affected_rows;
                $stmt->close();
                header("Location: ".$_SERVER['PHP_SELF']."?agent_id=".$agent_id."&msg=moved&count=".$moved);
                exit();
            } else {
                $toast_error = "Move failed: ".$stmt->error;
                $stmt->close();
            }
        }
    }
}

if(isset($_GET['msg']) && $_GET['msg'] === 'moved'){
    $c = isset($_GET['count']) ? (int)$_GET['count'] : 0;
    $toast_success = "Moved successfully. Updated students: ".$c;
}

/* ---------------------------
   Load Pre School students (with agent + school)
---------------------------- */
$where = "WHERE s.school_id = $preSchoolId";
if($agent_id > 0){
    $where .= " AND s.user_id = $agent_id";
}

$students = [];
$sql = "
    SELECT s.*,
           u.name AS agent_name, u.email AS agent_email,
           sc.name AS school_name
    FROM students s
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN schools sc ON sc.id = s.school_id
    $where
    ORDER BY s.id DESC
";
$stQ = $conn->query($sql);
while($r = $stQ->fetch_assoc()){
    $students[] = $r;
}

/* ---------------------------
   Build photo map (latest JPG/JPEG doc per student)
---------------------------- */
$photoMap = []; // student_id => file_path
if(!empty($students)){
    $ids = array_map(fn($x) => (int)$x['id'], $students);
    $in = implode(',', $ids);

    // NOTE: we assume dt.file_type holds jpg/jpeg
    $pQ = $conn->query("
        SELECT sd.student_id, sd.file_path
        FROM student_documents sd
        JOIN document_types dt ON dt.id = sd.doc_type_id
        WHERE sd.student_id IN ($in)
          AND LOWER(dt.file_type) IN ('jpg','jpeg')
        ORDER BY sd.student_id ASC, sd.uploaded_at DESC
    ");

    // take first occurrence per student (because ordered desc by uploaded_at)
    if($pQ){
        while($row = $pQ->fetch_assoc()){
            $sid = (int)$row['student_id'];
            if(!isset($photoMap[$sid])){
                $photoMap[$sid] = $row['file_path'] ?? '';
            }
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
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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

.thumb {
    width: 54px;
    height: 54px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
    background: #fff;
}

.student-name {
    font-weight: 900;
    font-size: 13px;
}

.meta {
    font-size: 12px;
    color: #6c757d;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 800;
}

.btn-tight {
    padding: .28rem .55rem;
}

.modal-mini .modal-dialog {
    position: fixed;
    right: 16px;
    bottom: 16px;
    margin: 0;
    width: 460px;
    max-width: calc(100vw - 32px);
}

.modal-mini .modal-content {
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, .2);
}

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
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT 70% -->
        <div class="col-lg-9">

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0">Pre School Students</h5>
                        <div class="text-muted" style="font-size:12px;">
                            Students waiting for interview & school assignment
                        </div>
                    </div>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back</a>
                </div>

                <hr class="my-2">

                <!-- Filters + Actions -->
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-1">Filter by Agent</label>
                        <select name="agent_id" class="form-select form-select-sm">
                            <option value="0">All Agents</option>
                            <?php foreach($agents as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>"
                                <?php echo ($agent_id==(int)$a['id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($a['name']); ?> —
                                <?php echo htmlspecialchars($a['email']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-primary w-100" type="submit">Apply Filter</button>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-sm btn-dark w-100" data-bs-toggle="modal"
                            data-bs-target="#exportExcelModal">
                            Export to Excel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold">Students in Pre School</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-dark btn-tight" id="btnSelectAll">
                            Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-primary btn-tight" data-bs-toggle="modal"
                            data-bs-target="#moveSchoolModal">
                            Move to School
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:44px;">
                                    <input type="checkbox" id="masterChk">
                                </th>
                                <th style="width:150px;">Photo</th>
                                <th>Student</th>
                                <th style="width:220px;">Agent</th>
                                <th style="width:140px;">Intake</th>
                                <th style="width:200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No students found in Pre School.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($students as $i => $st):
                $sid = (int)$st['id'];
                $photo = $photoMap[$sid] ?? '';
                $photoHtml = $photo ? "<img class='thumb' style='width:100%;height:auto;' src='".htmlspecialchars($photo)."' alt='photo'>" : "<span class='text-muted'>—</span>";
              ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="rowChk" value="<?php echo $sid; ?>">
                                </td>
                                <td class="text-center"><?php echo $photoHtml; ?></td>

                                <td>
                                    <div class="student-name">
                                        <?php echo htmlspecialchars($st['student_name'] ?? ''); ?>
                                        <?php if(!empty($st['student_name_jp'])): ?>
                                        <span
                                            class="text-primary">(<?php echo htmlspecialchars($st['student_name_jp']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta">
                                        <?php if(!empty($st['gender'])): ?>
                                        <span
                                            class="badge badge-soft me-1"><?php echo htmlspecialchars($st['gender']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($st['nationality'])): ?>
                                        <span
                                            class="badge badge-soft me-1"><?php echo htmlspecialchars($st['nationality']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($st['date_of_birth'])): ?>
                                        DOB: <?php echo htmlspecialchars($st['date_of_birth']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <div style="font-weight:800;">
                                        <?php echo htmlspecialchars($st['agent_name'] ?? '—'); ?></div>
                                    <div class="meta"><?php echo htmlspecialchars($st['agent_email'] ?? ''); ?></div>
                                </td>

                                <td>
                                    <span
                                        class="badge badge-soft"><?php echo htmlspecialchars($st['intake'] ?? '—'); ?></span>
                                </td>

                                <td>
                                    <div class="d-grid gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btnOpenMoveOne"
                                            data-studentid="<?php echo $sid; ?>"
                                            data-studentname="<?php echo htmlspecialchars($st['student_name'] ?? 'Student'); ?>">
                                            Move Student
                                        </button>
                                        <a class="btn btn-sm btn-outline-dark"
                                            href="student_file.php?student_id=<?php echo $sid; ?>&from=preschool"
                                            target="_self">
                                            Open Student File
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-muted mt-2" style="font-size:12px;">
                    Tip: Use checkbox selection to export/move multiple students at once.
                </div>
            </div>

        </div>

        <!-- RIGHT 30% -->
        <div class="col-lg-3">
            <div class="card-box side-box">
                <h6 class="fw-bold mb-2">How this page works</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.55;">
                    Students here are in <b>Pre School</b>. After interview, move them to their destination school.
                </div>

                <hr class="my-3">

                <div class="fw-bold mb-2">Quick actions</div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal"
                        data-bs-target="#exportExcelModal">
                        Export Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#moveSchoolModal">
                        Move Selected
                    </button>
                </div>

                <hr class="my-3">

                <div class="fw-bold mb-2">Export includes</div>
                <ul style="padding-left:16px; line-height:1.65; margin:0;">
                    <li>Selected students (or Select All)</li>
                    <li>Optional photo (latest JPG/JPEG)</li>
                    <li>Common fields (Name, DOB, Intake, etc.)</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- =========================
  MOVE TO SCHOOL MODAL (BOTTOM RIGHT)
========================== -->
<div class="modal fade modal-mini" id="moveSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:900;">Move to Destination School</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="moveForm">
                <div class="modal-body">
                    <input type="hidden" name="move_students" value="1">
                    <div class="p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e5e7eb;">
                        <div style="font-weight:900;">Are you sure?</div>
                        <div class="text-muted" style="font-size:12px;">
                            This will move selected student(s) from <b>Pre School</b> to the chosen school.
                        </div>
                    </div>

                    <label class="form-label fw-bold">Destination School</label>
                    <select name="dest_school_id" class="form-select form-select-sm" required>
                        <option value="">-- Select School --</option>
                        <?php foreach($schools as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="text-muted mt-2" style="font-size:12px;">
                        Selected students: <b id="selectedCount">0</b>
                    </div>

                    <div id="hiddenStudentInputs"></div>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================
  EXPORT EXCEL MODAL (BOTTOM RIGHT)
========================== -->
<div class="modal fade modal-mini" id="exportExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:900;">Export Pre School Students</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="export_preschool_students_excel.php" id="exportForm">
                <div class="modal-body">

                    <div class="p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e5e7eb;">
                        <div style="font-weight:900;">Export Selected Students</div>
                        <div class="text-muted" style="font-size:12px;">
                            Choose students from table checkboxes. Use “Select All” if needed.
                        </div>
                    </div>

                    <input type="hidden" name="pre_school_id" value="<?php echo (int)$preSchoolId; ?>">
                    <input type="hidden" name="agent_id" value="<?php echo (int)$agent_id; ?>">

                    <div class="text-muted" style="font-size:12px;">
                        Selected students: <b id="exportSelectedCount">0</b>
                    </div>

                    <hr class="my-2">

                    <div class="fw-bold mb-1">Select Fields</div>
                    <div class="row g-1">
                        <?php
              $fieldOptions = [
                'student_name' => 'Name',
                'student_name_jp' => 'Name (JP)',
                'gender' => 'Gender',
                'date_of_birth' => 'DOB',
                'nationality' => 'Nationality',
                'phone' => 'Phone',
                'passport_number' => 'Passport No.',
                'current_address' => 'Current Address',
                'permanent_address' => 'Permanent Address',
                'japanese_level' => 'Japanese Level',
                'intake' => 'Intake',
                'agent_name' => 'Agent Name',
                'agent_email' => 'Agent Email',
              ];
              $defaultChecked = ['student_name','date_of_birth','gender','nationality','intake','agent_name'];
            ?>
                        <?php foreach($fieldOptions as $k=>$lbl): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input fieldChk" type="checkbox" name="fields[]"
                                    value="<?php echo htmlspecialchars($k); ?>"
                                    id="f_<?php echo htmlspecialchars($k); ?>"
                                    <?php echo in_array($k, $defaultChecked, true) ? "checked" : ""; ?>>
                                <label class="form-check-label" for="f_<?php echo htmlspecialchars($k); ?>">
                                    <?php echo htmlspecialchars($lbl); ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-2">

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_photo" value="1" id="includePhoto"
                            checked>
                        <label class="form-check-label" for="includePhoto">
                            Include Photo (latest JPG/JPEG)
                        </label>
                    </div>

                    <div id="exportHiddenStudentInputs"></div>
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
<div id="toastMsg" class="toast-pop" style="background:#198754;color:#fff;">
    <div style="font-weight:900;">Success</div>
    <div><?php echo htmlspecialchars($toast_success); ?></div>
</div>
<?php endif; ?>

<?php if(!empty($toast_error)): ?>
<div id="toastMsg" class="toast-pop" style="background:#dc3545;color:#fff;">
    <div style="font-weight:900;">Error</div>
    <div><?php echo htmlspecialchars($toast_error); ?></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // toast
    const t = document.getElementById('toastMsg');
    if (t) {
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3500);
    }

    const master = document.getElementById('masterChk');
    const rows = () => Array.from(document.querySelectorAll('.rowChk'));

    function getSelectedIds() {
        return rows().filter(x => x.checked).map(x => x.value);
    }

    function fillHiddenInputs(containerId, name) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = '';
        getSelectedIds().forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = id;
            el.appendChild(input);
        });
    }

    function syncCounts() {
        const count = getSelectedIds().length;
        const sc = document.getElementById('selectedCount');
        const ec = document.getElementById('exportSelectedCount');
        if (sc) sc.textContent = count;
        if (ec) ec.textContent = count;

        // keep hidden inputs ready
        fillHiddenInputs('hiddenStudentInputs', 'student_ids[]');
        fillHiddenInputs('exportHiddenStudentInputs', 'student_ids[]');
    }

    if (master) {
        master.addEventListener('change', () => {
            rows().forEach(c => c.checked = master.checked);
            syncCounts();
        });
    }

    rows().forEach(c => c.addEventListener('change', syncCounts));

    document.getElementById('btnSelectAll')?.addEventListener('click', () => {
        rows().forEach(c => c.checked = true);
        if (master) master.checked = true;
        syncCounts();
    });

    // Move ONE student quick button
    document.querySelectorAll('.btnOpenMoveOne').forEach(btn => {
        btn.addEventListener('click', () => {
            const sid = btn.dataset.studentid;
            // uncheck all, check only that one
            rows().forEach(c => c.checked = false);
            if (master) master.checked = false;
            const chk = rows().find(x => x.value === sid);
            if (chk) chk.checked = true;
            syncCounts();

            // open modal
            const modal = new bootstrap.Modal(document.getElementById('moveSchoolModal'));
            modal.show();
        });
    });

    // Before submit, ensure at least one student
    document.getElementById('moveForm')?.addEventListener('submit', (e) => {
        if (getSelectedIds().length === 0) {
            e.preventDefault();
            alert("Please select at least one student.");
        }
    });

    document.getElementById('exportForm')?.addEventListener('submit', (e) => {
        if (getSelectedIds().length === 0) {
            e.preventDefault();
            alert("Please select at least one student.");
            return;
        }
        // ensure at least 1 field selected
        const anyField = Array.from(document.querySelectorAll('.fieldChk')).some(x => x.checked);
        if (!anyField) {
            e.preventDefault();
            alert("Please select at least one field.");
        }
    });

    syncCounts();
});
</script>