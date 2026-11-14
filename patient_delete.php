<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('patients.php');
}

$id = filter_var($_POST['patient_id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    flash_set('error', 'ID pasien tidak valid.');
    redirect('patients.php');
}

try {
    $db->begin_transaction();
    $stmt = $db->prepare('DELETE FROM patients WHERE patient_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        $db->rollback();
        flash_set('error', 'Data pasien tidak ditemukan.');
    } else {
        $db->commit();
        flash_set('success', 'Data pasien berhasil dihapus.');
    }
} catch (mysqli_sql_exception $ex) {
    $db->rollback();
    flash_set('error', 'Gagal menghapus data. Perubahan dibatalkan (rollback).');
}
redirect('patients.php');