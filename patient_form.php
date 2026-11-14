<?php
require_once __DIR__ . '/config.php';

const GENDERS     = ['M' => 'Laki-laki', 'F' => 'Perempuan'];
const BLOOD_TYPES = ['A', 'B', 'AB', 'O'];
const ALLERGIES   = ['Penicillin', 'Dust', 'Peanuts', 'Seafood', 'Pollen', 'Latex'];

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$errors = [];

$data = [
    'medical_record_no' => '',
    'full_name'         => '',
    'email'             => '',
    'phone'             => '',
    'gender'            => '',
    'birth_date'        => '',
    'blood_type'        => '',
    'allergies'         => [],
    'pain_level'        => '5',
    'appointment_at'    => '',
    'complaint'         => '',
];

// Mode ubah: ambil data lama
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $db->prepare('SELECT * FROM patients WHERE patient_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        flash_set('error', 'Data pasien tidak ditemukan.');
        redirect('patients.php');
    }
    foreach ($data as $k => $_) {
        $data[$k] = $row[$k];
    }
    $data['allergies']      = json_to_list($row['allergies']);
    $data['pain_level']     = (string)(int)$row['pain_level'];
    $data['appointment_at'] = date('Y-m-d\TH:i', strtotime($row['appointment_at']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit) {
        $stmt = $db->prepare('SELECT 1 FROM patients WHERE patient_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            flash_set('error', 'Data pasien tidak ditemukan.');
            redirect('patients.php');
        }
    }

    $str = fn(string $k): string => isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';

    $data['medical_record_no'] = $str('medical_record_no');
    $data['full_name']         = $str('full_name');
    $data['email']             = $str('email');
    $data['phone']             = $str('phone');
    $data['gender']            = $str('gender');
    $data['birth_date']        = $str('birth_date');
    $data['blood_type']        = $str('blood_type');
    $data['pain_level']        = $str('pain_level');
    $data['appointment_at']    = $str('appointment_at');
    $data['complaint']         = $str('complaint');

    // --- Validasi ---
    if ($data['medical_record_no'] === '') {
        $errors['medical_record_no'] = 'Nomor rekam medis wajib diisi.';
    } elseif (mb_strlen($data['medical_record_no']) > 10) {
        $errors['medical_record_no'] = 'Nomor rekam medis maksimal 10 karakter.';
    } elseif (value_exists($db, 'patients', 'medical_record_no', $data['medical_record_no'], 'patient_id', $id)) {
        $errors['medical_record_no'] = 'Nomor rekam medis sudah digunakan, gunakan nomor lain.';
    }

    if ($data['full_name'] === '') {
        $errors['full_name'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($data['full_name']) > 100) {
        $errors['full_name'] = 'Nama lengkap maksimal 100 karakter.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (mb_strlen($data['email']) > 100) {
        $errors['email'] = 'Email maksimal 100 karakter.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid (contoh: nama@domain.com).';
    }

    if ($data['phone'] === '') {
        $errors['phone'] = 'Nomor telepon wajib diisi.';
    } elseif (!preg_match('/^\+?[0-9]{8,14}$/', $data['phone'])) {
        $errors['phone'] = 'Nomor telepon harus 8-14 digit angka (boleh diawali +), maksimal 15 karakter.';
    }

    if ($data['gender'] === '') {
        $errors['gender'] = 'Jenis kelamin wajib dipilih.';
    } elseif (!array_key_exists($data['gender'], GENDERS)) {
        $errors['gender'] = 'Jenis kelamin tidak valid.';
    }

    if ($data['birth_date'] === '') {
        $errors['birth_date'] = 'Tanggal lahir wajib diisi.';
    } elseif (!is_valid_date($data['birth_date'], 'Y-m-d')) {
        $errors['birth_date'] = 'Format tanggal lahir tidak valid.';
    } elseif ($data['birth_date'] > date('Y-m-d')) {
        $errors['birth_date'] = 'Tanggal lahir tidak boleh melebihi hari ini.';
    }

    if ($data['blood_type'] === '') {
        $errors['blood_type'] = 'Golongan darah wajib dipilih.';
    } elseif (!in_array($data['blood_type'], BLOOD_TYPES, true)) {
        $errors['blood_type'] = 'Golongan darah tidak valid.';
    }

    [$allergySelected, $allergyErr] = validate_multi($_POST['allergies'] ?? null, ALLERGIES);
    $data['allergies'] = $allergySelected;
    if ($allergyErr) {
        $errors['allergies'] = $allergyErr;
    }
    $allergyJson = json_encode($allergySelected, JSON_UNESCAPED_UNICODE);
    if (!isset($errors['allergies']) && strlen($allergyJson) > 100) {
        $errors['allergies'] = 'Data alergi terlalu panjang (maksimal 100 karakter).';
    }

    if ($data['pain_level'] === '') {
        $errors['pain_level'] = 'Tingkat nyeri wajib diisi.';
    } elseif (filter_var($data['pain_level'], FILTER_VALIDATE_INT) === false) {
        $errors['pain_level'] = 'Tingkat nyeri harus berupa bilangan bulat.';
    } elseif ((int)$data['pain_level'] < 1 || (int)$data['pain_level'] > 10) {
        $errors['pain_level'] = 'Tingkat nyeri harus antara 1 sampai 10.';
    }

    $appointmentSql = null;
    if ($data['appointment_at'] === '') {
        $errors['appointment_at'] = 'Jadwal pemeriksaan wajib diisi.';
    } elseif (!is_valid_date($data['appointment_at'], 'Y-m-d\TH:i')) {
        $errors['appointment_at'] = 'Format jadwal pemeriksaan tidak valid.';
    } else {
        $apt = DateTime::createFromFormat('!Y-m-d\TH:i', $data['appointment_at']);
        if ($apt < new DateTime(date('Y-m-d H:i'))) {
            $errors['appointment_at'] = 'Jadwal pemeriksaan tidak boleh waktu yang sudah lewat.';
        } else {
            $appointmentSql = $apt->format('Y-m-d H:i:00');
        }
    }

    if ($data['complaint'] === '') {
        $errors['complaint'] = 'Keluhan wajib diisi.';
    }

    // --- Simpan dengan transaksi ---
    if (!$errors) {
        $pain = (int)$data['pain_level'];

        try {
            $db->begin_transaction();

            if ($isEdit) {
                $stmt = $db->prepare(
                    'UPDATE patients SET medical_record_no=?, full_name=?, email=?, phone=?, gender=?, birth_date=?,
                     blood_type=?, allergies=?, pain_level=?, appointment_at=?, complaint=? WHERE patient_id=?'
                );
                $stmt->bind_param(
                    'ssssssssissi',
                    $data['medical_record_no'], $data['full_name'], $data['email'], $data['phone'], $data['gender'],
                    $data['birth_date'], $data['blood_type'], $allergyJson, $pain, $appointmentSql, $data['complaint'], $id
                );
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO patients (medical_record_no, full_name, email, phone, gender, birth_date,
                     blood_type, allergies, pain_level, appointment_at, complaint) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->bind_param(
                    'ssssssssiss',
                    $data['medical_record_no'], $data['full_name'], $data['email'], $data['phone'], $data['gender'],
                    $data['birth_date'], $data['blood_type'], $allergyJson, $pain, $appointmentSql, $data['complaint']
                );
            }
            $stmt->execute();
            $stmt->close();

            $db->commit();
            flash_set('success', $isEdit ? 'Data pasien berhasil diubah.' : 'Data pasien berhasil ditambahkan.');
            redirect('patients.php');
        } catch (mysqli_sql_exception $ex) {
            $db->rollback();
            if ($ex->getCode() === 1062) {
                $errors['medical_record_no'] = 'Nomor rekam medis sudah digunakan, gunakan nomor lain.';
            } else {
                $errors['general'] = 'Terjadi kesalahan saat menyimpan data. Perubahan dibatalkan (rollback).';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Ubah Pasien' : 'Tambah Pasien';
require __DIR__ . '/header.php';

$err = function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<div class="error-text">' . e($errors[$k]) . '</div>' : '';
};
$cls = fn(string $k): string => isset($errors[$k]) ? 'field has-error' : 'field';
?>
<div class="toolbar">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn secondary" href="patients.php">&laquo; Kembali</a>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php elseif ($errors): ?>
    <div class="alert error">Data belum tersimpan. Periksa kembali isian yang ditandai merah.</div>
<?php endif; ?>

<form class="form" method="post" action="patient_form.php<?= $isEdit ? '?id=' . $id : '' ?>" novalidate>
    <div class="<?= $cls('medical_record_no') ?>">
        <label for="medical_record_no">No. Rekam Medis <span class="req">*</span></label>
        <input type="text" id="medical_record_no" name="medical_record_no" maxlength="10" value="<?= e($data['medical_record_no']) ?>">
        <?= $err('medical_record_no') ?>
    </div>

    <div class="<?= $cls('full_name') ?>">
        <label for="full_name">Nama Lengkap <span class="req">*</span></label>
        <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($data['full_name']) ?>">
        <?= $err('full_name') ?>
    </div>

    <div class="<?= $cls('email') ?>">
        <label for="email">Email <span class="req">*</span></label>
        <input type="email" id="email" name="email" maxlength="100" value="<?= e($data['email']) ?>">
        <?= $err('email') ?>
    </div>

    <div class="<?= $cls('phone') ?>">
        <label for="phone">Telepon <span class="req">*</span></label>
        <input type="tel" id="phone" name="phone" maxlength="15" value="<?= e($data['phone']) ?>">
        <?= $err('phone') ?>
    </div>

    <div class="<?= $cls('gender') ?>">
        <span class="label">Jenis Kelamin <span class="req">*</span></span>
        <div class="options">
            <?php foreach (GENDERS as $val => $label): ?>
                <label>
                    <input type="radio" name="gender" value="<?= e($val) ?>" <?= $data['gender'] === $val ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('gender') ?>
    </div>

    <div class="<?= $cls('birth_date') ?>">
        <label for="birth_date">Tanggal Lahir <span class="req">*</span></label>
        <input type="date" id="birth_date" name="birth_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($data['birth_date']) ?>">
        <?= $err('birth_date') ?>
    </div>

    <div class="<?= $cls('blood_type') ?>">
        <label for="blood_type">Golongan Darah <span class="req">*</span></label>
        <select id="blood_type" name="blood_type">
            <option value="">-- Pilih golongan darah --</option>
            <?php foreach (BLOOD_TYPES as $b): ?>
                <option value="<?= e($b) ?>" <?= $data['blood_type'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $err('blood_type') ?>
    </div>

    <div class="<?= $cls('allergies') ?>">
        <span class="label">Alergi</span>
        <div class="options">
            <?php foreach (ALLERGIES as $a): ?>
                <label>
                    <input type="checkbox" name="allergies[]" value="<?= e($a) ?>"
                        <?= in_array($a, $data['allergies'], true) ? 'checked' : '' ?>>
                    <?= e($a) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('allergies') ?>
    </div>

    <div class="<?= $cls('pain_level') ?>">
        <label for="pain_level">Tingkat Nyeri (1-10): <strong id="pain_value"><?= e($data['pain_level']) ?></strong> <span class="req">*</span></label>
        <input type="range" id="pain_level" name="pain_level" min="1" max="10" step="1"
               value="<?= e($data['pain_level'] === '' ? '5' : $data['pain_level']) ?>"
               oninput="document.getElementById('pain_value').textContent = this.value">
        <?= $err('pain_level') ?>
    </div>

    <div class="<?= $cls('appointment_at') ?>">
        <label for="appointment_at">Jadwal Pemeriksaan <span class="req">*</span></label>
        <input type="datetime-local" id="appointment_at" name="appointment_at" value="<?= e($data['appointment_at']) ?>">
        <?= $err('appointment_at') ?>
    </div>

    <div class="<?= $cls('complaint') ?>">
        <label for="complaint">Keluhan <span class="req">*</span></label>
        <textarea id="complaint" name="complaint" rows="4"><?= e($data['complaint']) ?></textarea>
        <?= $err('complaint') ?>
    </div>

    <button type="submit" class="btn"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?></button>
    <a class="btn secondary" href="patients.php">Batal</a>
</form>
<?php require __DIR__ . '/footer.php'; ?>