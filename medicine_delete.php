<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('medicines.php');
}

$id = filter_var($_POST['medicine_id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    flash_set('error', 'ID obat tidak valid.');
    redirect('medicines.php');
}

try {
    $db->begin_transaction();
    $stmt = $db->prepare('DELETE FROM medicines WHERE medicine_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        $db->rollback();
        flash_set('error', 'Data obat tidak ditemukan.');
    } else {
        $db->commit();
        flash_set('success', 'Data obat berhasil dihapus.');
    }
} catch (mysqli_sql_exception $ex) {
    $db->rollback();
    flash_set('error', 'Gagal menghapus data. Perubahan dibatalkan (rollback).');
}
redirect('medicines.php');