<?php
$pageTitle = 'Medicines';
require __DIR__ . '/header.php';

$result = $db->query('SELECT * FROM medicines ORDER BY medicine_id DESC');
?>
<div class="toolbar">
    <h1>Data Medicines</h1>
    <a class="btn" href="medicine_form.php">+ Tambah Obat</a>
</div>

<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>No</th><th>Kode</th><th>Nama</th><th>Tipe</th><th>Aturan Pakai</th>
        <th>Efek Samping</th><th>Harga</th><th>Stok</th><th>Kadaluarsa</th>
        <th>Resep</th><th>Deskripsi</th><th>Aksi</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="12" class="empty">Belum ada data obat.</td></tr>
    <?php endif; ?>
    <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= e($row['medicine_code']) ?></td>
            <td><?= e($row['medicine_name']) ?></td>
            <td><?= e($row['type']) ?></td>
            <td><?= e($row['usage_rule']) ?></td>
            <td>
                <?php $se = json_to_list($row['side_effects']); ?>
                <?php if ($se): foreach ($se as $s): ?>
                    <span class="badge"><?= e($s) ?></span>
                <?php endforeach; else: ?>-<?php endif; ?>
            </td>
            <td>Rp <?= number_format((float)$row['price'], 2, ',', '.') ?></td>
            <td><?= (int)$row['stock'] ?></td>
            <td><?= e($row['expired_date']) ?></td>
            <td><?= $row['need_prescription'] ? 'Ya' : 'Tidak' ?></td>
            <td><?= e($row['description']) ?></td>
            <td class="actions">
                <a class="btn warn small" href="medicine_form.php?id=<?= (int)$row['medicine_id'] ?>">Ubah</a>
                <form method="post" action="medicine_delete.php"
                      onsubmit="return confirm('Yakin ingin menghapus obat &quot;<?= e(addslashes($row['medicine_name'])) ?>&quot;? Data tidak dapat dikembalikan.');">
                    <input type="hidden" name="medicine_id" value="<?= (int)$row['medicine_id'] ?>">
                    <button type="submit" class="btn danger small">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/footer.php'; ?>