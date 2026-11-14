<?php
$pageTitle = 'Beranda';
require __DIR__ . '/header.php';

$totalMed = (int)$db->query('SELECT COUNT(*) FROM medicines')->fetch_row()[0];
$totalPat = (int)$db->query('SELECT COUNT(*) FROM patients')->fetch_row()[0];
?>
<h1>Sistem Informasi Klinik Kesehatan Sekolah</h1>
<p class="muted">Pilih menu untuk mengelola data.</p>
<div class="cards">
    <a class="card" href="medicines.php">
        <h2>Medicines</h2>
        <p>Kelola data obat</p>
        <strong><?= $totalMed ?> data</strong>
    </a>
    <a class="card" href="patients.php">
        <h2>Patients</h2>
        <p>Kelola data pasien</p>
        <strong><?= $totalPat ?> data</strong>
    </a>
</div>
<?php require __DIR__ . '/footer.php'; ?>