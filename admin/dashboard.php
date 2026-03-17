<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}


$success = "";
$error = "";


/**
 * Flash via GET
 */
if(isset($_GET['msg'])){
    if($_GET['msg'] === 'school_added') $success = "School added successfully!";
    if($_GET['msg'] === 'school_deleted') $success = "School deleted successfully!";
    if($_GET['msg'] === 'user_deleted') $success = "User deleted successfully!";
}

/**
 * Optional: Add School
 */
if(isset($_POST['add_school'])){
    $school_name = trim($_POST['school_name'] ?? '');

    if($school_name === ""){
        $error = "School name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO schools (name) VALUES (?)");
        if(!$stmt){
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("s", $school_name);
            if($stmt->execute()){
                $success = "School added successfully!";
                //reloading to avoid resubmiting from on refresh
                header("Location: dashboard.php?msg=school_added");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
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
    background: #ffffff;
    margin-bottom: 16px;
}

.side-box {
    position: sticky;
    top: 16px;
}

.table thead th {
    white-space: nowrap;
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
        <div class="col-lg-9">

            <!-- Header -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0">Admin Dashboard</h5>
                        <div class="text-muted" style="font-size:12px;">Manage agents and schools</div>
                    </div>
                    <!-- <a href="school_requirements.php" class="btn btn-outline-primary btn-sm">
                        Set Requirements
                    </a> -->
                </div>
            </div>

            <!-- AGENTS & CONSULTANCY -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;">AGENTS & CONSULTANCY</h6>
                    <span class="badge badge-soft">Users (Role: Admin)</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th style="width:180px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
              $users_result = $conn->query("SELECT id, name, email FROM users WHERE role='user' ORDER BY id DESC");
              $count=1;

              if($users_result && $users_result->num_rows > 0){
                  while($row = $users_result->fetch_assoc()){
                      echo "<tr>
                              <td>".$count++."</td>
                              <td>".htmlspecialchars($row['name'])."</td>
                              <td>".htmlspecialchars($row['email'])."</td>
                              <td>
                                  <a class='btn btn-sm btn-success w-100'
                                     href='../admin/view_user.php?user_id=".$row['id']."'>
                                     View User Data
                                  </a>
                              </td>
                            </tr>";
                  }
              } else {
                  echo "<tr><td colspan='4' class='text-center'>No users found.</td></tr>";
              }
              ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ADD SCHOOLS -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;">ADD NEW SCHOOLS</h6>
                </div>
                <!-- Add School -->
                <form method="POST" class="row g-2 mb-2">
                    <div class="col-md-9">
                        <input type="text" name="school_name" class="form-control form-control-sm"
                            placeholder="Add new school name..." required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="add_school" class="btn btn-dark btn-sm w-100">Add
                            School</button>
                    </div>
                </form>
            </div>

            <!-- SCHOOLS -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0" style="font-weight:800;">CURRENT SCHOOLS</h6>
                    <a href="school_requirements.php" class="btn btn-outline-primary btn-sm">
                        Manage Requirements
                    </a>
                </div>


                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>School Name</th>
                                <th style="width:320px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
              $schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name ASC");
              $count=1;

              if($schools_result && $schools_result->num_rows > 0){
                  while($s = $schools_result->fetch_assoc()){
                      echo "<tr>
                              <td>".$count++."</td>
                              <td>".htmlspecialchars($s['name'])."</td>
                              <td>
                                  <a class='btn btn-sm btn-primary'
                                     href='school_requirements.php?school_id=".$s['id']."'>
                                     Manage Requirements
                                  </a>
                                  <a class='btn btn-sm btn-primary'
                                     href='school_detail.php?school_id=".$s['id']."'>
                                     View School
                                  </a>                                  
                              </td>
                            </tr>";
                  }
              } else {
                  echo "<tr><td colspan='3' class='text-center'>No schools found.</td></tr>";
              }
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
                    This admin dashboard lets you manage <b>agents</b> (users) and <b>schools</b>.
                    Schools are connected to document requirements used in student checklists.
                </div>

                <hr class="my-3">

                <div class="mb-2" style="font-weight:800;">Quick actions</div>
                <div class="d-grid gap-2">
                    <a href="preschool_students.php" class="btn btn-outline-primary btn-sm">
                        PRE-SCHOOL(id=35)
                    </a>
                    <a href="recycle_bin.php?user_id=0" class="btn btn-outline-primary btn-sm">
                        RECYCLE BIN (id=0)
                    </a><a href="notification.php" class="btn btn-outline-primary btn-sm">
                        NOTIFICATIONS/ACTIVITIES
                    </a>
                    <a href="announcement.php" class="btn btn-outline-primary btn-sm">Announcements</a>
                    <a href="school_requirements.php" class="btn btn-outline-primary btn-sm">
                        Configure School Requirements
                    </a>
                    <a href="register.php" class="btn btn-primary btn-sm">
                        Register New User/Agent
                    </a>
                </div>

                <hr class="my-3">

                <div class="mb-2" style="font-weight:800;">How it works</div>
                <ul style="padding-left:16px; line-height:1.55;" class="mb-0">
                    <li><b>Add School</b> here.</li>
                    <li>Go to <b>Manage Requirements</b> for each school.</li>
                    <li>Agents create students and choose a school.</li>
                    <li>Student checklist is generated from school requirements.</li>
                </ul>

                <hr class="my-3">

                <div class="mb-2" style="font-weight:800;">Tips</div>
                <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                    Keep document types organized by category (Identity, Education, Finance, etc.)
                </div>

            </div>
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