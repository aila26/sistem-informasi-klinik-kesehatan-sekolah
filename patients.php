<?php
$pageTitle = 'Patients';
require __DIR__ . '/header.php';

$result = $db->query('SELECT * FROM patients ORDER BY patient_id DESC');
?>
<div class="toolbar">
    <h1>Data Patients</h1>
    <a class="btn" href="patient_form.php">+ Tambah Pasien</a>
</div>

<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>No</th><th>No. RM</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Gender</th>
        <th>Tgl Lahir</th><th>Gol. Darah</th><th>Alergi</th><th>Nyeri</th>
        <th>Jadwal Periksa</th><th>Keluhan</th><th>Aksi</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="13" class="empty">Belum ada data pasien.</td></tr>
    <?php endif; ?>
    <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= e($row['medical_record_no']) ?></td>
            <td><?= e($row['full_name']) ?></td>
            <td><?= e($row['email']) ?></td>
            <td><?= e($row['phone']) ?></td>
            <td><?= $row['gender'] === 'M' ? 'Laki-laki' : 'Perempuan' ?></td>
            <td><?= e($row['birth_date']) ?></td>
            <td><?= e($row['blood_type']) ?></td>
            <td>
                <?php $al = json_to_list($row['allergies']); ?>
                <?php if ($al): foreach ($al as $a): ?>
                    <span class="badge"><?= e($a) ?></span>
                <?php endforeach; else: ?>-<?php endif; ?>
            </td>
            <td><?= (int)$row['pain_level'] ?>/10</td>
            <td><?= e(date('d-m-Y H:i', strtotime($row['appointment_at']))) ?></td>
            <td><?= e($row['complaint']) ?></td>
            <td class="actions">
                <a class="btn warn small" href="patient_form.php?id=<?= (int)$row['patient_id'] ?>">Ubah</a>
                <form method="post" action="patient_delete.php"
                      onsubmit="return confirm('Yakin ingin menghapus pasien &quot;<?= e(addslashes($row['full_name'])) ?>&quot;? Data tidak dapat dikembalikan.');">
                    <input type="hidden" name="patient_id" value="<?= (int)$row['patient_id'] ?>">
                    <button type="submit" class="btn danger small">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/footer.php'; ?>