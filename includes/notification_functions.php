<?php function notifyNewStudent($conn, $student_id, $student_name, $user_id)
{
    if(!$student_id || !$student_name) return;

    $created_by = (int)$user_id;

    $notification_type = "New Student Added";
    $title = "New Student Added";

    $message = $_SESSION['name'] . " added student: " . $student_name;

    $redirect_url = "student_file.php?student_id=" . $student_id . "&agent_id=" . $user_id;

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (notification_type, title, message, user_id, student_id, created_by, redirect_url)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if($stmt){
        $stmt->bind_param(
            "sssiiis",
            $notification_type,
            $title,
            $message,
            $user_id,
            $student_id,
            $created_by,
            $redirect_url
        );
        $stmt->execute();
        $stmt->close();
    }
}




function notifyFileUpload($conn, $student_id, $student_name, $doc_name, $agent_id, $document_id = null)
{
    if(!$student_id || !$doc_name) return;

    $created_by = (int)$_SESSION['user_id'];

    $notification_type = "File Uploaded";
    $title = "Student File Uploaded";

    $message = $_SESSION['name'] . " uploaded " . $doc_name . " for student: " . $student_name;

    $redirect_url = "student_file.php?student_id=" . $student_id . "&agent_id=" . $agent_id;

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (notification_type, title, message, user_id, student_id, document_id, created_by, redirect_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if($stmt){
        $stmt->bind_param(
            "sssiiiis",
            $notification_type,
            $title,
            $message,
            $agent_id,
            $student_id,
            $document_id,
            $created_by,
            $redirect_url
        );
        $stmt->execute();
        $stmt->close();
    }
}
?>