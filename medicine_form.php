<?php
require_once __DIR__ . '/config.php';

const MED_TYPES        = ['Tablet', 'Syrup', 'Capsule', 'Ointment'];
const MED_USAGE_RULES  = ['Before Meal', 'After Meal'];
const MED_SIDE_EFFECTS = ['Nausea', 'Dizziness', 'Drowsiness', 'Headache', 'Rash'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$errors = [];

$data = [
    'medicine_code'     => '',
    'medicine_name'     => '',
    'type'              => '',
    'usage_rule'        => '',
    'side_effects'      => [],
    'price'             => '',
    'stock'             => '',
    'expired_date'      => '',
    'need_prescription' => 0,
    'description'       => '',
];

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $db->prepare('SELECT * FROM medicines WHERE medicine_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        flash_set('error', 'Data obat tidak ditemukan.');
        redirect('medicines.php');
    }
    foreach ($data as $k => $_) {
        $data[$k] = $row[$k];
    }
    $data['side_effects']      = json_to_list($row['side_effects']);
    $data['need_prescription'] = (int)$row['need_prescription'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit) {
        $stmt = $db->prepare('SELECT 1 FROM medicines WHERE medicine_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            flash_set('error', 'Data obat tidak ditemukan.');
            redirect('medicines.php');
        }
    }

    $str = fn(string $k): string => isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';

    $data['medicine_code']     = $str('medicine_code');
    $data['medicine_name']     = $str('medicine_name');
    $data['type']              = $str('type');
    $data['usage_rule']        = $str('usage_rule');
    $data['price']             = $str('price');
    $data['stock']             = $str('stock');
    $data['expired_date']      = $str('expired_date');
    $data['description']       = $str('description');
    $data['need_prescription'] = isset($_POST['need_prescription']) && $_POST['need_prescription'] === '1' ? 1 : 0;

    if ($data['medicine_code'] === '') {
        $errors['medicine_code'] = 'Kode obat wajib diisi.';
    } elseif (mb_strlen($data['medicine_code']) > 10) {
        $errors['medicine_code'] = 'Kode obat maksimal 10 karakter.';
    } elseif (value_exists($db, 'medicines', 'medicine_code', $data['medicine_code'], 'medicine_id', $id)) {
        $errors['medicine_code'] = 'Kode obat sudah digunakan, gunakan kode lain.';
    }

    if ($data['medicine_name'] === '') {
        $errors['medicine_name'] = 'Nama obat wajib diisi.';
    } elseif (mb_strlen($data['medicine_name']) > 100) {
        $errors['medicine_name'] = 'Nama obat maksimal 100 karakter.';
    }

    if ($data['type'] === '') {
        $errors['type'] = 'Tipe obat wajib dipilih.';
    } elseif (!in_array($data['type'], MED_TYPES, true)) {
        $errors['type'] = 'Tipe obat tidak valid.';
    }

    if ($data['usage_rule'] === '') {
        $errors['usage_rule'] = 'Aturan pakai wajib dipilih.';
    } elseif (!in_array($data['usage_rule'], MED_USAGE_RULES, true)) {
        $errors['usage_rule'] = 'Aturan pakai tidak valid.';
    }

    [$sideSelected, $sideErr] = validate_multi($_POST['side_effects'] ?? null, MED_SIDE_EFFECTS);
    $data['side_effects'] = $sideSelected;
    if ($sideErr) {
        $errors['side_effects'] = $sideErr;
    }
    $sideJson = json_encode($sideSelected, JSON_UNESCAPED_UNICODE);
    if (!isset($errors['side_effects']) && strlen($sideJson) > 100) {
        $errors['side_effects'] = 'Data efek samping terlalu panjang (maksimal 100 karakter).';
    }

    if ($data['price'] === '') {
        $errors['price'] = 'Harga wajib diisi.';
    } elseif (!is_numeric($data['price'])) {
        $errors['price'] = 'Harga harus berupa angka.';
    } elseif ((float)$data['price'] <= 0) {
        $errors['price'] = 'Harga harus lebih dari 0.';
    } elseif ((float)$data['price'] > 99999999.99) {
        $errors['price'] = 'Harga terlalu besar (maksimal 99.999.999,99).';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $data['price'])) {
        $errors['price'] = 'Harga maksimal 2 angka di belakang desimal.';
    }

    if ($data['stock'] === '') {
        $errors['stock'] = 'Stok wajib diisi.';
    } elseif (filter_var($data['stock'], FILTER_VALIDATE_INT) === false) {
        $errors['stock'] = 'Stok harus berupa bilangan bulat.';
    } elseif ((int)$data['stock'] < 0) {
        $errors['stock'] = 'Stok tidak boleh negatif.';
    } elseif ((int)$data['stock'] > 2147483647) {
        $errors['stock'] = 'Stok terlalu besar.';
    }

    if ($data['expired_date'] === '') {
        $errors['expired_date'] = 'Tanggal kadaluarsa wajib diisi.';
    } elseif (!is_valid_date($data['expired_date'], 'Y-m-d')) {
        $errors['expired_date'] = 'Format tanggal kadaluarsa tidak valid.';
    } elseif ($data['expired_date'] <= date('Y-m-d')) {
        $errors['expired_date'] = 'Tanggal kadaluarsa harus setelah hari ini.';
    }

    if ($data['description'] === '') {
        $errors['description'] = 'Deskripsi wajib diisi.';
    }

    if (!$errors) {
        $price = number_format((float)$data['price'], 2, '.', '');
        $stock = (int)$data['stock'];
        $need  = $data['need_prescription'];

        try {
            $db->begin_transaction();

            if ($isEdit) {
                $stmt = $db->prepare(
                    'UPDATE medicines SET medicine_code=?, medicine_name=?, type=?, usage_rule=?, side_effects=?,
                     price=?, stock=?, expired_date=?, need_prescription=?, description=? WHERE medicine_id=?'
                );
                $stmt->bind_param(
                    'ssssssisisi',
                    $data['medicine_code'], $data['medicine_name'], $data['type'], $data['usage_rule'], $sideJson,
                    $price, $stock, $data['expired_date'], $need, $data['description'], $id
                );
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO medicines (medicine_code, medicine_name, type, usage_rule, side_effects,
                     price, stock, expired_date, need_prescription, description) VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->bind_param(
                    'ssssssisis',
                    $data['medicine_code'], $data['medicine_name'], $data['type'], $data['usage_rule'], $sideJson,
                    $price, $stock, $data['expired_date'], $need, $data['description']
                );
            }
            $stmt->execute();
            $stmt->close();

            $db->commit();
            flash_set('success', $isEdit ? 'Data obat berhasil diubah.' : 'Data obat berhasil ditambahkan.');
            redirect('medicines.php');
        } catch (mysqli_sql_exception $ex) {
            $db->rollback();
            if ($ex->getCode() === 1062) {
                $errors['medicine_code'] = 'Kode obat sudah digunakan, gunakan kode lain.';
            } else {
                $errors['general'] = 'Terjadi kesalahan saat menyimpan data. Perubahan dibatalkan (rollback).';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Ubah Obat' : 'Tambah Obat';
require __DIR__ . '/header.php';

$err = function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<div class="error-text">' . e($errors[$k]) . '</div>' : '';
};
$cls = fn(string $k): string => isset($errors[$k]) ? 'field has-error' : 'field';
?>
<div class="toolbar">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn secondary" href="medicines.php">&laquo; Kembali</a>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php elseif ($errors): ?>
    <div class="alert error">Data belum tersimpan. Periksa kembali isian yang ditandai merah.</div>
<?php endif; ?>

<form class="form" method="post" action="medicine_form.php<?= $isEdit ? '?id=' . $id : '' ?>" novalidate>
    <div class="<?= $cls('medicine_code') ?>">
        <label for="medicine_code">Kode Obat <span class="req">*</span></label>
        <input type="text" id="medicine_code" name="medicine_code" maxlength="10" value="<?= e($data['medicine_code']) ?>">
        <?= $err('medicine_code') ?>
    </div>

    <div class="<?= $cls('medicine_name') ?>">
        <label for="medicine_name">Nama Obat <span class="req">*</span></label>
        <input type="text" id="medicine_name" name="medicine_name" maxlength="100" value="<?= e($data['medicine_name']) ?>">
        <?= $err('medicine_name') ?>
    </div>

    <div class="<?= $cls('type') ?>">
        <label for="type">Tipe <span class="req">*</span></label>
        <select id="type" name="type">
            <option value="">-- Pilih tipe --</option>
            <?php foreach (MED_TYPES as $t): ?>
                <option value="<?= e($t) ?>" <?= $data['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $err('type') ?>
    </div>

    <div class="<?= $cls('usage_rule') ?>">
        <span class="label">Aturan Pakai <span class="req">*</span></span>
        <div class="options">
            <?php foreach (MED_USAGE_RULES as $i => $r): ?>
                <label>
                    <input type="radio" name="usage_rule" value="<?= e($r) ?>" <?= $data['usage_rule'] === $r ? 'checked' : '' ?>>
                    <?= e($r) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('usage_rule') ?>
    </div>

    <div class="<?= $cls('side_effects') ?>">
        <span class="label">Efek Samping</span>
        <div class="options">
            <?php foreach (MED_SIDE_EFFECTS as $s): ?>
                <label>
                    <input type="checkbox" name="side_effects[]" value="<?= e($s) ?>"
                        <?= in_array($s, $data['side_effects'], true) ? 'checked' : '' ?>>
                    <?= e($s) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('side_effects') ?>
    </div>

    <div class="<?= $cls('price') ?>">
        <label for="price">Harga (Rp) <span class="req">*</span></label>
        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e($data['price']) ?>">
        <?= $err('price') ?>
    </div>

    <div class="<?= $cls('stock') ?>">
        <label for="stock">Stok <span class="req">*</span></label>
        <input type="number" id="stock" name="stock" step="1" min="0" value="<?= e($data['stock']) ?>">
        <?= $err('stock') ?>
    </div>

    <div class="<?= $cls('expired_date') ?>">
        <label for="expired_date">Tanggal Kadaluarsa <span class="req">*</span></label>
        <input type="date" id="expired_date" name="expired_date" value="<?= e($data['expired_date']) ?>">
        <?= $err('expired_date') ?>
    </div>

    <div class="field">
        <label>
            <input type="checkbox" name="need_prescription" value="1" <?= $data['need_prescription'] ? 'checked' : '' ?>>
            Memerlukan resep dokter
        </label>
    </div>

    <div class="<?= $cls('description') ?>">
        <label for="description">Deskripsi <span class="req">*</span></label>
        <textarea id="description" name="description" rows="4"><?= e($data['description']) ?></textarea>
        <?= $err('description') ?>
    </div>

    <button type="submit" class="btn"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?></button>
    <a class="btn secondary" href="medicines.php">Batal</a>
</form>
<?php require __DIR__ . '/footer.php'; ?>