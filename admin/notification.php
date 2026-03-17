<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

$success = "";

if(isset($_GET['deleted']) && $_GET['deleted']=="1"){
    $success = "Notification deleted successfully.";
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$notifications = [];

/*
Admin → all notifications
User/Agent → only own notifications
*/
if($role === 'admin'){
    $sql = "
        SELECT n.*, u.name AS creator_name
        FROM notifications n
        LEFT JOIN users u ON u.id = n.created_by
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 300
    ";
    $res = $conn->query($sql);
} else {
    $stmt = $conn->prepare("
        SELECT n.*, u.name AS creator_name
        FROM notifications n
        LEFT JOIN users u ON u.id = n.created_by
        WHERE n.user_id=?
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 300
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
}

while($r = $res->fetch_assoc()){
    $notifications[] = $r;
}

if(isset($stmt) && $stmt instanceof mysqli_stmt){
    $stmt->close();
}

/* -------------------------
   Group notifications by day
-------------------------- */
$groupedNotifications = [];

foreach($notifications as $n){
    $rawDate = $n['created_at'] ?? '';
    $dateKey = date('Y-m-d', strtotime($rawDate));

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if($dateKey === $today){
        $label = "Today";
    } elseif($dateKey === $yesterday){
        $label = "Yesterday";
    } else {
        $label = date('Y-m-d', strtotime($rawDate));
    }

    if(!isset($groupedNotifications[$label])){
        $groupedNotifications[$label] = [];
    }

    $groupedNotifications[$label][] = $n;
}

/* =========================
   Delete notification
========================= */
if(isset($_POST['delete_notification'])){

    $nid = (int)($_POST['notification_id'] ?? 0);

    if($nid > 0){

        if($role === 'admin'){
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $nid);
        }else{
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=? LIMIT 1");
            $stmt->bind_param("ii", $nid, $user_id);
        }

        $stmt->execute();
        $stmt->close();

        header("Location: ".$_SERVER['PHP_SELF']."?deleted=1");
        exit();
    }
}

include_once '../template/login_status.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-container {
    max-width: 1200px;
    margin: 24px auto;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.card-box {
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
    background: #fff;
    margin-bottom: 18px;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 700;
}

.notification-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 10px;
    background: #fff;
}

.notification-item.unread {
    background: #eef6ff;
    border-color: #bfdbfe;
}

.notification-title {
    font-weight: 800;
    font-size: 13px;
}

.notification-meta {
    color: #6c757d;
    font-size: 12px;
}

.day-title {
    font-weight: 800;
    font-size: 14px;
    margin-bottom: 10px;
    color: #111827;
}

.day-block {
    margin-bottom: 18px;
}
</style>

<div class="container page-container small-ui">

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h5 class="m-0">Notifications</h5>
                <div class="text-muted" style="font-size:12px;">
                    Activity grouped by date
                </div>
            </div>

            <?php if($role === 'admin'): ?>
            <a href="../admin/dashboard.php" class="btn btn-sm btn-secondary">Back</a>
            <?php else: ?>
            <a href="../user/dashboard.php" class="btn btn-sm btn-secondary">Back</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if(empty($groupedNotifications)): ?>
    <div class="card-box">
        <div class="text-center text-muted">No notifications yet.</div>
    </div>
    <?php else: ?>

    <?php foreach($groupedNotifications as $dayLabel => $items): ?>
    <div class="card-box day-block">
        <div class="day-title"><?php echo htmlspecialchars($dayLabel); ?></div>

        <?php foreach($items as $n): ?>
        <?php
                        $type = htmlspecialchars($n['notification_type'] ?? '');
                        $title = htmlspecialchars($n['title'] ?? '');
                        $message = htmlspecialchars($n['message'] ?? '');
                        $creator = htmlspecialchars($n['creator_name'] ?? '-');
                        $timeOnly = date('h:i A', strtotime($n['created_at']));
                        $redirect = trim($n['redirect_url'] ?? '');
                        $unread = ((int)($n['is_read'] ?? 0) === 0);
                    ?>

        <div class="notification-item <?php echo $unread ? 'unread' : ''; ?>">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft"><?php echo $type; ?></span>
                        <?php if($unread): ?>
                        <span class="badge bg-primary">New</span>
                        <?php endif; ?>
                    </div>

                    <div class="notification-title"><?php echo $title; ?></div>
                    <div class="text-muted mb-2"><?php echo $message; ?></div>

                    <div class="notification-meta">
                        By: <b><?php echo $creator; ?></b> · <?php echo htmlspecialchars($timeOnly); ?>
                    </div>
                </div>

                <div>
                    <?php if($redirect !== ''): ?>
                    <a class="btn btn-sm btn-primary" href="notification_redirect.php?id=<?php echo (int)$n['id']; ?>">
                        Open
                    </a>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="notification_id" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div>