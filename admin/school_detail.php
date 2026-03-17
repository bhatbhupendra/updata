<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
if($school_id <= 0){
    die("No school selected.");
}

$success = "";
$error = "";

/**
 * Filters
 */
$selected_intake = trim($_GET['intake'] ?? 'all');
$selected_agent  = trim($_GET['agent_id'] ?? 'all');

/**
 * Load intake options for this school
 */
$intakes = [];
$stmt = $conn->prepare("
    SELECT DISTINCT intake
    FROM students
    WHERE school_id=?
      AND intake IS NOT NULL
      AND intake <> ''
    ORDER BY intake DESC
");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $intakes[] = $r['intake'];
}
$stmt->close();

/**
 * Load agent options for this school
 */
$agents = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name
    FROM students s
    JOIN users u ON u.id = s.user_id
    WHERE s.school_id=?
    ORDER BY u.name ASC
");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $agents[] = $r;
}
$stmt->close();

/**
 * Fetch school
 */
$stmt = $conn->prepare("SELECT id, name FROM schools WHERE id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$school){
    die("School not found.");
}

/**
 * Flash via GET
 */
if(isset($_GET['msg'])){
    if($_GET['msg'] === 'school_updated') $success = "School name updated successfully!";
    if($_GET['msg'] === 'school_deleted') $success = "School deleted successfully!";
}

/**
 * Count students enrolled in this school
 */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM students WHERE school_id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$studentCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

/**
 * Handle UPDATE school name
 */
if(isset($_POST['update_school'])){
    $new_name = trim($_POST['school_name'] ?? '');

    if($new_name === ''){
        $error = "School name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE schools SET name=? WHERE id=?");
        if(!$stmt){
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("si", $new_name, $school_id);
            if($stmt->execute()){
                $stmt->close();
                header("Location: ".$_SERVER['PHP_SELF']."?school_id=".$school_id."&msg=school_updated");
                exit();
            } else {
                $error = "Update failed: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

/**
 * Handle DELETE school
 * (Safe rule: do not delete if students exist)
 */
if(isset($_POST['delete_school'])){
    if($studentCount > 0){
        $error = "Cannot delete this school because students are enrolled. Remove/move students first.";
    } else {

        // Also delete requirements mapping (safe cleanup)
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM school_required_docs WHERE school_id=?");
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM schools WHERE id=?");
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: dashboard.php?msg=school_deleted");
            exit();

        } catch(Exception $e){
            $conn->rollback();
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}

/**
 * List students enrolled (with intake + agent filters)
 */
$students = [];

$sql = "
    SELECT s.id, s.student_name, s.student_name_jp, s.gender, s.nationality, s.age, s.intake, u.name AS agent_name
    FROM students s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.school_id=?
";
$types = "i";
$params = [$school_id];

if($selected_intake !== 'all' && $selected_intake !== ''){
    $sql .= " AND s.intake=? ";
    $types .= "s";
    $params[] = $selected_intake;
}

if($selected_agent !== 'all' && ctype_digit((string)$selected_agent)){
    $sql .= " AND s.user_id=? ";
    $types .= "i";
    $params[] = (int)$selected_agent;
}

$sql .= " ORDER BY s.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $students[] = $r;
}
$stmt->close();
include_once '../template/login_status.php';

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-container {
    max-width: 1400px;
    margin: 24px auto;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.card-box {
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    background: #fff;
    margin-bottom: 18px;
}

.side-box {
    position: sticky;
    top: 16px;
}

.form-label {
    margin-bottom: 4px;
    font-weight: 700;
}

.form-control,
.form-select {
    padding: .38rem .55rem;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 700;
}

.thumb-mini {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #f3f4f6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #374151;
}

.modal-mini .modal-dialog {
    position: fixed;
    right: 16px;
    bottom: 16px;
    margin: 0;
    width: 380px;
    max-width: calc(100vw - 32px);
}

.modal-mini .modal-content {
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.2);
}

.table thead th {
    white-space: nowrap;
}
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT 70% -->
        <div class="col-lg-8">

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="m-0">School Detail</h5>
                        <div class="text-muted" style="font-size:12px;">
                            School ID: <b><?php echo (int)$school['id']; ?></b>
                            <span class="badge badge-soft ms-2"><?php echo (int)$studentCount; ?> enrolled</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="school_requirements.php?school_id=<?php echo (int)$school_id; ?>"
                            class="btn btn-sm btn-outline-primary">
                            Requirements
                        </a>
                        <a href="dashboard.php" class="btn btn-sm btn-secondary">Back</a>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div style="font-weight:800; font-size:13px;"><?php echo htmlspecialchars($school['name']); ?>
                        </div>
                        <div class="text-muted" style="font-size:12px;">
                            Enrolled students: <?php echo (int)$studentCount; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#editSchoolModal">
                            Edit Name
                        </button>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                            data-bs-target="#deleteSchoolModal">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;">Students Enrolled</h6>
                    <div class="text-muted" style="font-size:12px;">Sorted by newest first</div>
                </div>

                <form method="GET" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="school_id" value="<?php echo (int)$school_id; ?>">

                    <div class="col-md-4">
                        <label class="form-label">Filter by Intake</label>
                        <select name="intake" class="form-select">
                            <option value="all" <?php echo ($selected_intake === 'all' ? 'selected' : ''); ?>>All Intake
                            </option>
                            <?php foreach($intakes as $intake): ?>
                            <option value="<?php echo htmlspecialchars($intake); ?>"
                                <?php echo ($selected_intake === $intake ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($intake); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Filter by Agent</label>
                        <select name="agent_id" class="form-select">
                            <option value="all" <?php echo ($selected_agent === 'all' ? 'selected' : ''); ?>>All Agents
                            </option>
                            <?php foreach($agents as $ag): ?>
                            <option value="<?php echo (int)$ag['id']; ?>"
                                <?php echo ((string)$selected_agent === (string)$ag['id'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($ag['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                    </div>

                    <div class="col-md-2">
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?school_id=<?php echo (int)$school_id; ?>"
                            class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Student</th>
                                <th style="width:140px;">Agent</th>
                                <th style="width:140px;">Info</th>
                                <th style="width:170px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($students) === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center">No students enrolled yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php $i=1; foreach($students as $st): ?>
                            <?php
                    $name = htmlspecialchars($st['student_name'] ?? '');
                    $jp = htmlspecialchars($st['student_name_jp'] ?? '');
                    $agent = htmlspecialchars($st['agent_name'] ?? '-');
                    $gender = htmlspecialchars($st['gender'] ?? '');
                    $nat = htmlspecialchars($st['nationality'] ?? '');
                    $age = htmlspecialchars($st['age'] ?? '');
                    $initial = strtoupper(mb_substr($name, 0, 1));
                  ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="thumb-mini"><?php echo $initial ?: 'S'; ?></div>
                                        <div>
                                            <div style="font-weight:800;"><?php echo $name; ?></div>
                                            <?php if($jp): ?>
                                            <div class="text-muted" style="font-size:12px;"><?php echo $jp; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $agent; ?></td>
                                <td class="text-muted" style="font-size:12px;">
                                    <?php echo $gender ? $gender . " • " : ""; ?>
                                    <?php echo $nat ? $nat . " • " : ""; ?>
                                    <?php echo $age ? "Age: ".$age . " • " : ""; ?>
                                    <?php echo !empty($st['intake']) ? "Intake: ".htmlspecialchars($st['intake']) : ""; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-success"
                                        href="student_file.php?student_id=<?php echo (int)$st['id']; ?>&from=school_detail&school_id=<?php echo (int)$school_id; ?>">
                                        View
                                    </a>
                                    <a class="btn btn-sm btn-primary ms-1"
                                        href="download_zip.php?student_id=<?php echo (int)$st['id']; ?>">
                                        ZIP Files
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- RIGHT 30% -->
        <div class="col-lg-4">
            <div class="card-box side-box">
                <h6 class="mb-2" style="font-weight:800;">About this page</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.5;">
                    Manage a school record and see all students enrolled in it. You can rename the school or delete it.
                </div>

                <hr>

                <h6 class="mb-2" style="font-weight:800;">Rules</h6>
                <ul style="padding-left:16px; line-height:1.55;">
                    <li><b>Delete</b> is blocked if students exist (safe mode).</li>
                    <li>School requirements are linked in <b>Requirements</b> button.</li>
                    <li>Use student actions to manage documents.</li>
                </ul>

                <hr>

                <h6 class="mb-2" style="font-weight:800;">Tips</h6>
                <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                    Before deleting a school, move students to a new school or delete students first.
                </div>
            </div>
        </div>

    </div>
</div>

<!-- EDIT MODAL (RIGHT-BOTTOM) -->
<div class="modal fade modal-mini" id="editSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:800;">Edit School Name</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <label class="form-label">School Name</label>
                    <input type="text" class="form-control" name="school_name"
                        value="<?php echo htmlspecialchars($school['name']); ?>" required>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        This updates the school name for future students and requirement pages.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_school" class="btn btn-sm btn-dark">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL (RIGHT-BOTTOM) -->
<div class="modal fade modal-mini" id="deleteSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:800;">Delete School</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 mb-2">
                        This action cannot be undone.
                    </div>
                    <div style="font-weight:700;">
                        Delete: "<?php echo htmlspecialchars($school['name']); ?>" ?
                    </div>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        <?php if($studentCount > 0): ?>
                        Delete is disabled because <b><?php echo (int)$studentCount; ?></b> students are enrolled.
                        <?php else: ?>
                        This will also remove school requirement mappings.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_school" class="btn btn-sm btn-danger"
                        <?php echo ($studentCount > 0) ? "disabled" : ""; ?>>
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">

    <?php if($success): ?>
    <div id="liveToastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div id="liveToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const successToast = document.getElementById('liveToastSuccess');
    const errorToast = document.getElementById('liveToastError');

    if (successToast) {
        const toast = new bootstrap.Toast(successToast, {
            delay: 3500
        });
        toast.show();
    }

    if (errorToast) {
        const toast = new bootstrap.Toast(errorToast, {
            delay: 4500
        });
        toast.show();
    }

});
</script>