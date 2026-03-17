<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}


$success = "";
$error = "";

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if($student_id <= 0){
    die("No student selected.");
}

/**
 * Load schools for dropdown
 */
$schools = [];
$school_result = $conn->query("SELECT id, name FROM schools ORDER BY name ASC");
if($school_result){
    while($s = $school_result->fetch_assoc()){
        $schools[] = $s;
    }
}

/**
 * Fetch student (admin can fetch any, user can fetch only their student)
 */
if($role === 'admin'){
    $stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
    $stmt->bind_param("i", $student_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $student_id, $user_id);
}
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$student){
    die("Student not found or access denied.");
}

/**
 * PRG success message
 */
if(isset($_GET['updated']) && $_GET['updated'] == '1'){
    $success = "Student updated successfully!";
}

/**
 * Helper: compute age from DOB
 */
function calc_age($dob){
    if(!$dob) return null;
    $ts = strtotime($dob);
    if(!$ts) return null;
    $d1 = new DateTime(date('Y-m-d', $ts));
    $d2 = new DateTime(date('Y-m-d'));
    return (int)$d1->diff($d2)->y;
}

/**
 * Handle Update
 */
if(isset($_POST['update_student'])){

    // ===== BASIC =====
    $school_id     = (int)($_POST['school_id'] ?? 0);
    $student_name  = trim($_POST['student_name'] ?? '');
    $student_name_jp  = trim($_POST['student_name_jp'] ?? '');
    $student_email = trim($_POST['student_email'] ?? '');
    $gender        = $_POST['gender'] ?? null;
    $date_of_birth = $_POST['date_of_birth'] ?: null;
    $age           = calc_age($date_of_birth);

    $nationality   = $_POST['nationality'] ?? null;
    $phone         = $_POST['phone'] ?? null;

    $passport_number = $_POST['passport_number'] ?? null;
    $passport_issue_date = $_POST['passport_issue_date'] ?: null;
    $passport_expiry_date = $_POST['passport_expiry_date'] ?: null;

    $current_address   = $_POST['current_address'] ?? null;
    $permanent_address = $_POST['permanent_address'] ?? null;
    $information   = $_POST['information'] ?? null;

    // ===== FAMILY =====
    $father_name = $_POST['father_name'] ?? null;
    $father_occupation = $_POST['father_occupation'] ?? null;
    $mother_name = $_POST['mother_name'] ?? null;
    $mother_occupation = $_POST['mother_occupation'] ?? null;

    // ===== EDUCATION =====
    $highest_qualification = $_POST['highest_qualification'] ?? null;
    $last_institution_name = $_POST['last_institution_name'] ?? null;
    $graduation_year = $_POST['graduation_year'] ?: null;
    $academic_gap_years = $_POST['academic_gap_years'] ?: 0;

    // ===== JAPANESE =====
    $japanese_level = $_POST['japanese_level'] ?? null;
    $japanese_test_type = $_POST['japanese_test_type'] ?? null;
    $japanese_training_hours = $_POST['japanese_training_hours'] ?: null;

    // ===== FINANCE =====
    $sponsor_name_1 = $_POST['sponsor_name_1'] ?? null;
    $sponsor_relationship_1 = $_POST['sponsor_relationship_1'] ?? null;
    $sponsor_occupation_1 = $_POST['sponsor_occupation_1'] ?? null;
    $sponsor_annual_income_1 = $_POST['sponsor_annual_income_1'] ?: null;
    $sponsor_savings_amount_1 = $_POST['sponsor_savings_amount_1'] ?: null;

    $sponsor_name_2 = $_POST['sponsor_name_2'] ?? null;
    $sponsor_relationship_2 = $_POST['sponsor_relationship_2'] ?? null;
    $sponsor_occupation_2 = $_POST['sponsor_occupation_2'] ?? null;
    $sponsor_annual_income_2 = $_POST['sponsor_annual_income_2'] ?: null;
    $sponsor_savings_amount_2 = $_POST['sponsor_savings_amount_2'] ?: null;

    // ===== intake =====
    $intake = $_POST['intake'] ?? null;

    if($student_name === "" || $school_id <= 0){
        $error = "Student Name and School are required.";
    } else {

        // Update permissions: user cannot change user_id; admin can edit but we still keep original user_id
        $original_user_id = (int)$student['user_id'];

        $sql = "
            UPDATE students SET
                school_id=?,
                student_name=?,
                student_name_jp=?,
                student_email=?,
                gender=?,
                date_of_birth=?,
                age=?,
                nationality=?,
                phone=?,
                passport_number=?,
                passport_issue_date=?,
                passport_expiry_date=?,
                current_address=?,
                permanent_address=?,
                information=?,
                father_name=?,
                father_occupation=?,
                mother_name=?,
                mother_occupation=?,
                highest_qualification=?,
                last_institution_name=?,
                graduation_year=?,
                academic_gap_years=?,
                japanese_level=?,
                japanese_test_type=?,
                japanese_training_hours=?,
                sponsor_name_1=?,
                sponsor_relationship_1=?,
                sponsor_occupation_1=?,
                sponsor_annual_income_1=?,
                sponsor_savings_amount_1=?,
                sponsor_name_2=?,
                sponsor_relationship_2=?,
                sponsor_occupation_2=?,
                sponsor_annual_income_2=?,
                sponsor_savings_amount_2=?,
                intake=?
            WHERE id=? AND user_id=?
        ";

        $stmt = $conn->prepare($sql);
        if(!$stmt){
            $error = "Prepare failed: " . $conn->error;
        } else {

            $stmt->bind_param(
                "isssssissssssssssssssiississsddsssddsii",
                $school_id,
                $student_name,
                $student_name_jp,
                $student_email,
                $gender,
                $date_of_birth,
                $age,
                $nationality,
                $phone,
                $passport_number,
                $passport_issue_date,
                $passport_expiry_date,
                $current_address,
                $permanent_address,
                $information,
                $father_name,
                $father_occupation,
                $mother_name,
                $mother_occupation,
                $highest_qualification,
                $last_institution_name,
                $graduation_year,
                $academic_gap_years,
                $japanese_level,
                $japanese_test_type,
                $japanese_training_hours,
                $sponsor_name_1,
                $sponsor_relationship_1,
                $sponsor_occupation_1,
                $sponsor_annual_income_1,
                $sponsor_savings_amount_1,
                $sponsor_name_2,
                $sponsor_relationship_2,
                $sponsor_occupation_2,
                $sponsor_annual_income_2,
                $sponsor_savings_amount_2,
                $intake,
                $student_id,
                $original_user_id
            );

            if($stmt->execute()){
                $stmt->close();

                // ✅ PRG redirect to prevent resubmit
                header("Location: ".$_SERVER['PHP_SELF']."?student_id=".$student_id."&updated=1");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

/**
 * Refresh student data after update attempt (or initial view)
 */
if($role === 'admin'){
    $stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
    $stmt->bind_param("i", $student_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $student_id, $user_id);
}
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
require_once '../template/login_status.php';

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
    font-weight: 600;
}

.form-control,
.form-select {
    padding: .38rem .55rem;
}

.mb-tight {
    margin-bottom: 10px !important;
}

.section-title {
    font-weight: 800;
    font-size: 13px;
    margin: 14px 0 8px;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 700;
}
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT 70% -->
        <div class="col-lg-8">

            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0">Edit Student (Japan)</h5>
                        <div class="text-muted" style="font-size:12px;">
                            Student ID: <b><?php echo (int)$student_id; ?></b>
                            <span class="badge badge-soft ms-2">Update Mode</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="student_file.php?student_id=<?php echo (int)$student_id; ?>"
                            class="btn btn-sm btn-outline-primary">
                            Back to Student Files
                        </a>
                        <a href="dashboard.php" class="btn btn-sm btn-secondary">Dashboard</a>
                    </div>
                </div>

                <?php if($success): ?>
                <div class="alert alert-success py-2 mt-3 mb-0"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="alert alert-danger py-2 mt-3 mb-0"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="card-box">
                <form method="POST">

                    <div class="section-title">Basic</div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Student Name <span class="req">*</span></label>
                            <input type="text" name="student_name" class="form-control" required
                                value="<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Student Name (JP)<span class="req">*</span></label>
                            <input type="text" name="student_name_jp" class="form-control"
                                value="<?php echo htmlspecialchars($student['student_name_jp'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Email</label>
                            <input type="email" name="student_email" class="form-control"
                                value="<?php echo htmlspecialchars($student['student_email'] ?? ''); ?>">
                        </div>

                        <?php if($_SESSION['role'] === 'admin') {?>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">School </label>
                            <select name="school_id" class="form-select" required>
                                <option value="">-- Select School --</option>
                                <?php foreach($schools as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"
                                    <?php echo ((int)$student['school_id'] === (int)$s['id']) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php } ?>

                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">--</option>
                                <?php $g = $student['gender'] ?? ''; ?>
                                <option value="Male" <?php echo ($g==='Male')?'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo ($g==='Female')?'selected':''; ?>>Female</option>
                                <option value="Other" <?php echo ($g==='Other')?'selected':''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                        </div>

                        <div class="col-md-2 mb-tight">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-control"
                                value="<?php echo htmlspecialchars($student['age'] ?? ''); ?>" disabled>
                        </div>

                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control"
                                value="<?php echo htmlspecialchars($student['nationality'] ?? ''); ?>">
                        </div>

                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Passport Number</label>
                            <input type="text" name="passport_number" class="form-control"
                                value="<?php echo htmlspecialchars($student['passport_number'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Passport Issue Date</label>
                            <input type="date" name="passport_issue_date" class="form-control"
                                value="<?php echo htmlspecialchars($student['passport_issue_date'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Passport Expiry Date</label>
                            <input type="date" name="passport_expiry_date" class="form-control"
                                value="<?php echo htmlspecialchars($student['passport_expiry_date'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Current Address</label>
                            <input type="text" name="current_address" class="form-control"
                                value="<?php echo htmlspecialchars($student['current_address'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Permanent Address</label>
                            <input type="text" name="permanent_address" class="form-control"
                                value="<?php echo htmlspecialchars($student['permanent_address'] ?? ''); ?>">
                        </div>

                        <div class="col-12 mb-tight">
                            <label class="form-label">Information / Notes</label>
                            <input type="text" name="information" class="form-control"
                                value="<?php echo htmlspecialchars($student['information'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Family</div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Father Name</label>
                            <input type="text" name="father_name" class="form-control"
                                value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Father Occupation</label>
                            <input type="text" name="father_occupation" class="form-control"
                                value="<?php echo htmlspecialchars($student['father_occupation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Mother Name</label>
                            <input type="text" name="mother_name" class="form-control"
                                value="<?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Mother Occupation</label>
                            <input type="text" name="mother_occupation" class="form-control"
                                value="<?php echo htmlspecialchars($student['mother_occupation'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Education</div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Highest Qualification</label>
                            <input type="text" name="highest_qualification" class="form-control"
                                value="<?php echo htmlspecialchars($student['highest_qualification'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Last Institution Name</label>
                            <input type="text" name="last_institution_name" class="form-control"
                                value="<?php echo htmlspecialchars($student['last_institution_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control"
                                value="<?php echo htmlspecialchars($student['graduation_year'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-tight">
                            <label class="form-label">Academic Gap Years</label>
                            <input type="number" name="academic_gap_years" class="form-control"
                                value="<?php echo htmlspecialchars($student['academic_gap_years'] ?? 0); ?>">
                        </div>
                    </div>

                    <div class="section-title">Japanese</div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Japanese Level</label>
                            <input type="text" name="japanese_level" class="form-control"
                                value="<?php echo htmlspecialchars($student['japanese_level'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Test Type</label>
                            <input type="text" name="japanese_test_type" class="form-control"
                                value="<?php echo htmlspecialchars($student['japanese_test_type'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Japanese language Learning hours</label>
                            <input type="number" name="japanese_training_hours" class="form-control"
                                value="<?php echo htmlspecialchars($student['japanese_training_hours'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Finance / Sponsor</div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 1 Name</label>
                            <input type="text" name="sponsor_name_1" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_name_1'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 1 Relationship</label>
                            <input type="text" name="sponsor_relationship_1" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_relationship_1'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 1 Occupation</label>
                            <input type="text" name="sponsor_occupation_1" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_occupation_1'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Sponsor 1 Annual Income(JPY)</label>
                            <input type="number" step="0.01" name="sponsor_annual_income_1" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_annual_income_1'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Sponsor 1 Savings Amount(JPY)</label>
                            <input type="number" step="0.01" name="sponsor_savings_amount_1" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_savings_amount_1'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 2 Name</label>
                            <input type="text" name="sponsor_name_2" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_name_2'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 2 Relationship</label>
                            <input type="text" name="sponsor_relationship_2" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_relationship_2'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Sponsor 2 Occupation</label>
                            <input type="text" name="sponsor_occupation_2" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_occupation_2'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Sponsor 2 Annual Income(JPY)</label>
                            <input type="number" step="0.01" name="sponsor_annual_income_2" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_annual_income_2'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-tight">
                            <label class="form-label">Sponsor 2 Savings Amount(JPY)</label>
                            <input type="number" step="0.01" name="sponsor_savings_amount_2" class="form-control"
                                value="<?php echo htmlspecialchars($student['sponsor_savings_amount_2'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Intake</div>
                    <div class="row g-2">
                        <div class="col-md-4 mb-tight">
                            <label class="form-label">Intake</label>
                            <?php $in = $student['intake'] ?? ''; ?>
                            <select name="intake" class="form-select">
                                <option value="">--</option>
                                <option value="2026-01(January)"
                                    <?php echo ($in==='2026-01(January)')?'selected':''; ?>>2026-01(January)
                                </option>
                                <option value="2026-04(April)" <?php echo ($in==='2026-04(April)')?'selected':''; ?>>
                                    2026-04(April)
                                </option>
                                <option value="2026-07(July)" <?php echo ($in==='2026-07(July)')?'selected':''; ?>>
                                    2026-07(July)</option>
                                <option value="2026-10(October)"
                                    <?php echo ($in==='2026-10(October)')?'selected':''; ?>>2026-10(October)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="submit" name="update_student" class="btn btn-primary btn-sm px-4">
                            Update Student
                        </button>
                    </div>

                </form>
            </div>

        </div>

        <!-- RIGHT 30% -->
        <div class="col-lg-4">
            <div class="card-box side-box">
                <h6 class="mb-2" style="font-weight:800;">About this page</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.5;">
                    Update student profile details safely. DOB automatically recalculates age.
                    <b>While editing, fields must be filled correctly and properly(Owner or (admin) can change current
                        data
                        by mistake).</b>
                </div>

                <hr class="my-3">

                <h6 class="mb-2" style="font-weight:800;">How saving works</h6>
                <ul class="mb-0" style="padding-left:16px; line-height:1.55;">
                    <li>Click <b>Update Student</b> to save changes.</li>
                    <li>Page redirects after save (prevents duplicate submit).</li>
                    <li>Only the owner (or admin) can edit.</li>
                </ul>

                <hr class="my-3">

                <h6 class="mb-2" style="font-weight:800;">Tips</h6>
                <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                    Keep names consistent with passport + Japanese name format to avoid document mismatch.
                </div>

                <hr class="my-3">

                <h6 class="mb-2" style="font-weight:800;">Note</h6>
                <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                    When <b>Agent/User</b> create new student,By Default student is assigned to <b>PRE-SCHOOL</b>.
                    Only <b>Admin</b> can assign/change student's School from <b>PRE-SCHOOL</b> to <b>Listed School</b>.
                </div>

            </div>
        </div>

    </div>
</div>