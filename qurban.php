<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$animalOptions = qurban_animal_options();
$statusOptions = qurban_status_options();
$form = [
    'hijri_year' => '',
    'participant_name' => '',
    'animal_type' => 'kambing',
    'group_name' => '',
    'share_count' => '1',
    'share_label' => qurban_share_label('kambing', 1),
    'notes' => '',
    'sort_order' => '0',
    'status' => 'published',
];
$records = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('qurban.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'toggle_payment') {
            $id = (int) ($_POST['id'] ?? 0);
            $isPaid = (int) ($_POST['is_paid'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE qurban_participants SET is_paid = :is_paid WHERE id = :id')->execute([
                'id' => $id,
                'is_paid' => $isPaid,
            ]);
            set_flash('success', $isPaid === 1 ? 'Status pembayaran diubah menjadi sudah bayar.' : 'Status pembayaran diubah menjadi belum bayar.');
            redirect_to('/qurban.php#daftar-peserta');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM qurban_participants WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Peserta qurban berhasil dihapus.');
            redirect_to('/qurban.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $form = [
            'hijri_year' => trim((string) ($_POST['hijri_year'] ?? '')),
            'participant_name' => trim((string) ($_POST['participant_name'] ?? '')),
            'animal_type' => trim((string) ($_POST['animal_type'] ?? 'kambing')),
            'group_name' => trim((string) ($_POST['group_name'] ?? '')),
            'share_count' => trim((string) ($_POST['share_count'] ?? '1')),
            'share_label' => '',
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'sort_order' => trim((string) ($_POST['sort_order'] ?? '0')),
            'status' => trim((string) ($_POST['status'] ?? 'published')),
        ];
        $form['share_label'] = qurban_share_label($form['animal_type'], (int) $form['share_count']);

        if ($form['hijri_year'] === '' || preg_match('/^\d{4}$/', $form['hijri_year']) !== 1) {
            $errors['hijri_year'] = 'Tahun Hijriah wajib diisi dengan 4 digit, misalnya 1447.';
        }
        if ($form['participant_name'] === '') {
            $errors['participant_name'] = 'Nama peserta wajib diisi.';
        }
        if (!array_key_exists($form['animal_type'], $animalOptions)) {
            $errors['animal_type'] = 'Jenis hewan qurban tidak valid.';
        }
        if ($form['animal_type'] === 'sapi' && $form['group_name'] === '') {
            $errors['group_name'] = 'Kelompok wajib diisi untuk peserta qurban sapi.';
        }
        if ($form['animal_type'] !== 'sapi' && $form['group_name'] !== '') {
            $errors['group_name'] = 'Kelompok hanya dipakai untuk peserta qurban sapi.';
        }
        if ($form['share_count'] === '' || filter_var($form['share_count'], FILTER_VALIDATE_INT) === false) {
            $errors['share_count'] = 'Porsi / keterangan paket wajib dipilih.';
        } else {
            $shareCount = (int) $form['share_count'];
            $shareOptions = qurban_share_count_options($form['animal_type']);
            if (!array_key_exists($shareCount, $shareOptions)) {
                $errors['share_count'] = 'Pilihan porsi / keterangan paket tidak valid.';
            }
        }
        if ($form['sort_order'] === '' || filter_var($form['sort_order'], FILTER_VALIDATE_INT) === false) {
            $errors['sort_order'] = 'Urutan tampil harus berupa angka bulat.';
        }
        if (!array_key_exists($form['status'], $statusOptions)) {
            $errors['status'] = 'Status data qurban tidak valid.';
        }

        if ($errors === []) {
            $payload = [
                'hijri_year' => (int) $form['hijri_year'],
                'participant_name' => $form['participant_name'],
                'animal_type' => $form['animal_type'],
                'group_name' => $form['animal_type'] === 'sapi' ? $form['group_name'] : null,
                'share_count' => (int) $form['share_count'],
                'share_label' => qurban_share_label($form['animal_type'], (int) $form['share_count']),
                'notes' => $form['notes'] !== '' ? $form['notes'] : null,
                'sort_order' => (int) $form['sort_order'],
                'status' => $form['status'],
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE qurban_participants
                     SET hijri_year = :hijri_year, participant_name = :participant_name, animal_type = :animal_type,
                         group_name = :group_name, share_count = :share_count, share_label = :share_label, notes = :notes, sort_order = :sort_order, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Peserta qurban berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO qurban_participants (hijri_year, participant_name, animal_type, group_name, share_count, share_label, notes, sort_order, status)
                     VALUES (:hijri_year, :participant_name, :animal_type, :group_name, :share_count, :share_label, :notes, :sort_order, :status)'
                )->execute($payload);
                set_flash('success', 'Peserta qurban baru berhasil ditambahkan.');
            }

            redirect_to('/qurban.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM qurban_participants WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'hijri_year' => (string) $record['hijri_year'],
                'participant_name' => (string) $record['participant_name'],
                'animal_type' => (string) ($record['animal_type'] ?? 'kambing'),
                'group_name' => (string) ($record['group_name'] ?? ''),
                'share_count' => (string) max(1, (int) ($record['share_count'] ?? ((string) ($record['animal_type'] ?? '') === 'sapi' ? 7 : 1))),
                'share_label' => '',
                'notes' => (string) ($record['notes'] ?? ''),
                'sort_order' => (string) ($record['sort_order'] ?? '0'),
                'status' => (string) ($record['status'] ?? 'published'),
            ];
            $form['share_label'] = qurban_share_label($form['animal_type'], (int) $form['share_count']);
        }
    }

    $records = db()->query(
        "SELECT * FROM qurban_participants
         ORDER BY hijri_year DESC, sort_order ASC, COALESCE(group_name, '') ASC, participant_name ASC, id ASC"
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul qurban belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `qurban_participants` tersedia.';
}

render_admin_page_start('Qurban', 'qurban');
render_admin_page_header(
    'Qurban',
    'Kelola Peserta Qurban',
    'Masukkan peserta qurban per tahun Hijriah, atur kelompok untuk sapi, lalu tentukan data mana yang dipublikasikan ke halaman publik.',
    [
        ['href' => 'dashboard.php', 'label' => 'Kembali ke Dashboard', 'secondary' => true],
        ['href' => 'qurban-page.php', 'label' => 'Lihat Halaman Publik', 'secondary' => true],
    ]
);
?>

            <?php if ($pageError !== null): ?>
                <div class="flash-message flash-message--error"><?= h($pageError); ?></div>
            <?php else: ?>
                <section class="content-grid content-grid--single">
                    <article class="card card--form">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Peserta' : 'Tambah Peserta'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui data peserta qurban' : 'Input peserta qurban baru'; ?></h2>
                            </div>
                            <div class="metric-chip">Form cepat</div>
                        </div>

                        <form method="post" class="admin-form">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update' : 'create'; ?>">
                            <input type="hidden" name="id" value="<?= h((string) $editingId); ?>">

                            <div class="field-grid field-grid--compact">
                                <div class="field-group">
                                    <label for="hijri_year">Tahun Hijriah</label>
                                    <input class="admin-input" id="hijri_year" name="hijri_year" type="number" min="1300" max="2000" value="<?= h($form['hijri_year']); ?>" placeholder="1447">
                                    <?php if (isset($errors['hijri_year'])): ?><p class="field-error"><?= h($errors['hijri_year']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="animal_type">Jenis Hewan</label>
                                    <select class="admin-input" id="animal_type" name="animal_type">
                                        <?php foreach ($animalOptions as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['animal_type'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['animal_type'])): ?><p class="field-error"><?= h($errors['animal_type']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="group_name">Kelompok</label>
                                    <input class="admin-input" id="group_name" name="group_name" type="text" value="<?= h($form['group_name']); ?>" placeholder="Contoh: Kelompok Sapi 1">
                                    <p class="field-help">Isi hanya untuk peserta qurban sapi. Untuk kambing, biarkan kosong.</p>
                                    <?php if (isset($errors['group_name'])): ?><p class="field-error"><?= h($errors['group_name']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="sort_order">Urutan Tampil</label>
                                    <input class="admin-input" id="sort_order" name="sort_order" type="number" value="<?= h($form['sort_order']); ?>">
                                    <?php if (isset($errors['sort_order'])): ?><p class="field-error"><?= h($errors['sort_order']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group field-group--span-2">
                                    <label for="participant_name">Nama Peserta</label>
                                    <input class="admin-input" id="participant_name" name="participant_name" type="text" value="<?= h($form['participant_name']); ?>" placeholder="Nama lengkap peserta">
                                    <?php if (isset($errors['participant_name'])): ?><p class="field-error"><?= h($errors['participant_name']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="status">Status</label>
                                    <select class="admin-input" id="status" name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-help">Hanya status `published` yang tampil ke publik.</p>
                                </div>
                                <div class="field-group">
                                    <label for="share_label">Porsi / Keterangan Paket</label>
                                    <select class="admin-input" id="share_count" name="share_count" data-current="<?= h($form['share_count']); ?>">
                                        <?php foreach (qurban_share_count_options($form['animal_type']) as $value => $label): ?>
                                            <option value="<?= h((string) $value); ?>" <?= (int) $form['share_count'] === (int) $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" id="share_label" name="share_label" value="<?= h($form['share_label']); ?>">
                                    <p class="field-help">Untuk sapi, pilih jumlah porsi 1 sampai 7. Di halaman publik hanya nama yang tampil, sesuai jumlah porsinya.</p>
                                    <?php if (isset($errors['share_count'])): ?><p class="field-error"><?= h($errors['share_count']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="notes">Catatan</label>
                                    <textarea class="admin-input admin-input--compact" id="notes" name="notes" rows="3" placeholder="Opsional: keterangan kelompok, keluarga, atau catatan lain."><?= h($form['notes']); ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Perubahan' : 'Tambah Peserta'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('qurban.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card card--list" id="daftar-peserta">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Daftar Peserta</p>
                                <h2>Peserta qurban tersimpan</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada peserta qurban</h3>
                                <p>Mulai dengan menambahkan peserta qurban untuk tahun Hijriah yang sedang berjalan.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Tahun</th>
                                            <th>Peserta</th>
                                            <th>Hewan</th>
                                            <th>Kelompok</th>
                                            <th>Pembayaran</th>
                                            <th>Porsi</th>
                                            <th>Urutan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?= h(qurban_hijri_year_label((int) $record['hijri_year'])); ?></td>
                                                <td>
                                                    <strong><?= h((string) $record['participant_name']); ?></strong>
                                                    <?php if (trim((string) ($record['notes'] ?? '')) !== ''): ?>
                                                        <span><?= h((string) $record['notes']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= h(qurban_animal_label((string) ($record['animal_type'] ?? 'kambing'))); ?></td>
                                                <td><?= h(trim((string) ($record['group_name'] ?? '')) !== '' ? (string) $record['group_name'] : '-'); ?></td>
                                                <td>
                                                    <form method="post">
                                                        <?= csrf_input(); ?>
                                                        <input type="hidden" name="action" value="toggle_payment">
                                                        <input type="hidden" name="id" value="<?= h((string) $record['id']); ?>">
                                                        <input type="hidden" name="is_paid" value="<?= (int) ($record['is_paid'] ?? 0) === 1 ? '0' : '1'; ?>">
                                                        <button class="button-small <?= (int) ($record['is_paid'] ?? 0) === 1 ? 'button-small--success' : 'button-small--neutral'; ?>" type="submit">
                                                            <?= h(qurban_payment_label((int) ($record['is_paid'] ?? 0))); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td><?= h(qurban_share_label((string) ($record['animal_type'] ?? 'kambing'), (int) ($record['share_count'] ?? 1))); ?></td>
                                                <td><?= h((string) ($record['sort_order'] ?? 0)); ?></td>
                                                <td><span class="<?= h(status_badge_class((string) ($record['status'] ?? 'draft'))); ?>"><?= h((string) ($record['status'] ?? 'draft')); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('qurban.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus peserta qurban ini?');">
                                                            <?= csrf_input(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= h((string) $record['id']); ?>">
                                                            <button class="button-small button-small--danger" type="submit">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
                <script>
                    (function () {
                        var animalInput = document.getElementById('animal_type');
                        var shareCountInput = document.getElementById('share_count');
                        var shareInput = document.getElementById('share_label');

                        if (!animalInput || !shareCountInput || !shareInput) {
                            return;
                        }

                        var shareOptions = {
                            sapi: [
                                { value: '1', label: '1 porsi sapi' },
                                { value: '2', label: '2 porsi sapi' },
                                { value: '3', label: '3 porsi sapi' },
                                { value: '4', label: '4 porsi sapi' },
                                { value: '5', label: '5 porsi sapi' },
                                { value: '6', label: '6 porsi sapi' },
                                { value: '7', label: '7 porsi sapi (1 ekor sapi)' }
                            ],
                            kambing: [
                                { value: '1', label: '1 ekor kambing' }
                            ]
                        };

                        function syncOptions() {
                            var type = animalInput.value === 'sapi' ? 'sapi' : 'kambing';
                            var currentValue = shareCountInput.value;
                            var options = shareOptions[type];
                            var matched = false;

                            shareCountInput.innerHTML = '';

                            options.forEach(function (option) {
                                var element = document.createElement('option');
                                element.value = option.value;
                                element.textContent = option.label;

                                if (option.value === currentValue) {
                                    element.selected = true;
                                    matched = true;
                                }

                                shareCountInput.appendChild(element);
                            });

                            if (!matched && options.length > 0) {
                                shareCountInput.value = options[0].value;
                            }
                        }

                        function updateShareLabel() {
                            var type = animalInput.value === 'sapi' ? 'sapi' : 'kambing';
                            var count = shareCountInput.value;

                            if (type === 'sapi') {
                                shareInput.value = count + ' porsi sapi';
                                return;
                            }

                            shareInput.value = '1 ekor kambing';
                        }

                        animalInput.addEventListener('change', function () {
                            syncOptions();
                            updateShareLabel();
                        });
                        shareCountInput.addEventListener('change', updateShareLabel);
                        syncOptions();
                        updateShareLabel();
                    }());
                </script>
            <?php endif; ?>
<?php
render_admin_page_end();
