<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Catalog;
use App\Database;
use App\Logger;
use App\Search\Indexer;
use App\TextExtractor;

$user = require_manager();
$db = App::db();
$departments = Catalog::departments($db);
$levels = AccessControl::assignableLevels($user);
$maxMb = (int) App::config('upload_max_mb', 10);

$v = [
    'document_type' => '', 'department_id' => '', 'date_created' => date('Y-m-d'), 'access_level' => 'internal',
    'reference_no' => '', 'academic_year' => '', 'programme' => '', 'student_no' => '', 'student_name' => '', 'file_language' => \App\I18n::lang(),
    'title' => '', 'title_fr' => '', 'author' => '', 'author_fr' => '', 'keywords' => '', 'keywords_fr' => '',
    'description' => '', 'description_fr' => '', 'content' => '', 'content_fr' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $key => $_) {
        $v[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $v['file_language'] = $v['file_language'] === 'fr' ? 'fr' : 'en';

    if ($v['title'] === '' && $v['title_fr'] === '') $errors['title'] = __('Enter a title in at least one language.');
    if (mb_strlen($v['title']) > 255 || mb_strlen($v['title_fr']) > 255) $errors['title'] = __('A title can have at most 255 characters.');
    if (!isset(Catalog::TYPES[$v['document_type']])) $errors['document_type'] = __('Choose a document type.');
    if (!isset($departments[(int) $v['department_id']])) $errors['department_id'] = __('Choose the owning department.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['date_created']) || $v['date_created'] > date('Y-m-d')) $errors['date_created'] = __('Enter a valid date that is not in the future.');
    if (!in_array($v['access_level'], $levels, true)) $errors['access_level'] = __('Choose an access level.');
    if ($v['academic_year'] !== '' && !preg_match('/^\d{4}\/\d{4}$/', $v['academic_year'])) $errors['academic_year'] = __('Use the format 2025/2026.');
    if ($v['reference_no'] !== '' && !preg_match('~^[A-Z]{2}/[A-Z]{2,4}/\d{4}/\d{4}$~', strtoupper($v['reference_no']))) $errors['reference_no'] = __('Use the format LU/REG/2026/0001, or leave empty to generate it.');
    if (($v['student_no'] === '') !== ($v['student_name'] === '')) $errors['student_no'] = __('Enter both the registration number and the student name, or neither.');

    $file = $_FILES['file'] ?? null;
    $stored = null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['file'] = __('The upload failed (error {code}). Check the file size.', ['code' => (int) $file['error']]);
        } elseif (!isset(TextExtractor::ALLOWED[$ext])) {
            $errors['file'] = __('Allowed formats: {formats}.', ['formats' => strtoupper(implode(', ', array_keys(TextExtractor::ALLOWED)))]);
        } elseif ($file['size'] > $maxMb * 1024 * 1024) {
            $errors['file'] = __('The file is larger than {size} MB.', ['size' => $maxMb]);
        } else {
            $stored = ['tmp' => $file['tmp_name'], 'ext' => $ext, 'name' => basename((string) $file['name']), 'size' => (int) $file['size']];
        }
    }
    if (!$stored && $v['content'] === '' && $v['content_fr'] === '' && !isset($errors['file'])) {
        $errors['file'] = __('Attach a file or type/paste the document text.');
    }

    if (!$errors) {
        $dept = $departments[(int) $v['department_id']];
        $year = (int) substr($v['date_created'], 0, 4);
        if ($v['reference_no'] === '') {
            $prefix = "LU/{$dept['code']}/{$year}/";
            $max = $db->prepare("SELECT MAX(reference_no) FROM documents WHERE reference_no LIKE ? ESCAPE '!'");
            $max->execute([ltrim(Database::like($prefix), '%')]);
            $last = (string) $max->fetchColumn();
            $v['reference_no'] = $prefix . sprintf('%04d', $last ? (int) substr($last, -4) + 1 : 1);
        }
        $v['reference_no'] = strtoupper($v['reference_no']);
        $exists = $db->prepare('SELECT 1 FROM documents WHERE reference_no = ?');
        $exists->execute([$v['reference_no']]);
        if ($exists->fetchColumn()) {
            $errors['reference_no'] = __('This reference number is already used.');
        }
    }

    if (!$errors) {
        $fileName = $filePath = $mime = null;
        $size = null;
        if ($stored) {
            $dir = App::config('upload_dir');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filePath = bin2hex(random_bytes(16)) . '.' . $stored['ext'];
            if (!move_uploaded_file($stored['tmp'], $dir . '/' . $filePath)) {
                $errors['file'] = __('The file could not be saved on the server.');
            } else {
                $fileName = $stored['name'];
                $size = $stored['size'];
                $mime = TextExtractor::ALLOWED[$stored['ext']];
                $target = $v['file_language'] === 'fr' ? 'content_fr' : 'content';
                if ($v[$target] === '') {
                    $v[$target] = TextExtractor::extract($dir . '/' . $filePath, $stored['ext']);
                }
            }
        }

        if (!$errors) {
            $language = ($v['title'] !== '' && $v['title_fr'] !== '') ? 'both' : ($v['title_fr'] !== '' ? 'fr' : 'en');
            $now = date('Y-m-d H:i:s');
            $row = [
                'reference_no' => $v['reference_no'], 'title' => $v['title'], 'title_fr' => $v['title_fr'] ?: null,
                'document_type' => $v['document_type'], 'department_id' => (int) $v['department_id'], 'programme' => $v['programme'] ?: null,
                'student_no' => $v['student_no'] ?: null, 'student_name' => $v['student_name'] ?: null,
                'author' => $v['author'] ?: null, 'author_fr' => $v['author_fr'] ?: null, 'date_created' => $v['date_created'],
                'doc_year' => $year, 'academic_year' => $v['academic_year'] ?: null,
                'keywords' => $v['keywords'], 'keywords_fr' => $v['keywords_fr'] ?: null,
                'description' => $v['description'], 'description_fr' => $v['description_fr'] ?: null,
                'content' => $v['content'], 'content_fr' => $v['content_fr'] ?: null, 'language' => $language,
                'access_level' => $v['access_level'], 'status' => 'active', 'retention_period' => Catalog::RETENTION[$v['document_type']],
                'file_name' => $fileName, 'file_path' => $filePath, 'file_mime' => $mime, 'file_size' => $size,
                'created_by' => $user['id'], 'created_at' => $now, 'updated_at' => $now,
            ];
            $db->prepare('INSERT INTO documents (' . implode(', ', array_keys($row)) . ') VALUES (' . Database::placeholders($row) . ')')
                ->execute(array_values($row));
            $id = (int) $db->lastInsertId();
            (new Indexer($db))->index($id);
            Logger::audit($user, 'upload', $id, ['reference' => $v['reference_no']]);
            flash(__('Document {ref} saved and indexed. It is now searchable.', ['ref' => $v['reference_no']]), 'success');
            redirect(url('document.php', ['id' => $id]));
        }
    }
}

$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = static fn (string $k): string => isset($errors[$k]) ? ' class="invalid"' : '';
$langPair = static function (string $field, string $label, string $type = 'input', string $placeholderEn = '', string $placeholderFr = '') use ($v, $cls, $err): string {
    $html = '';
    foreach (['' => [__('English'), $placeholderEn], '_fr' => [__('French'), $placeholderFr]] as $suffix => [$language, $placeholder]) {
        $name = $field . $suffix;
        $id = 'f_' . $name;
        $html .= '<div><label for="' . $id . '">' . e($label) . ' <span class="muted small">(' . e($language) . ')</span></label>';
        $html .= $type === 'textarea'
            ? '<textarea id="' . $id . '" name="' . $name . '" rows="' . ($field === 'content' ? 8 : 2) . '" lang="' . ($suffix ? 'fr' : 'en') . '">' . e($v[$name]) . '</textarea>'
            : '<input id="' . $id . '" name="' . $name . '" lang="' . ($suffix ? 'fr' : 'en') . '" value="' . e($v[$name]) . '"' . ($suffix === '' ? $cls($field) : '') . ' placeholder="' . e($placeholder) . '">';
        $html .= '</div>';
    }
    return $html . ($err($field) ? '<div class="span-2">' . $err($field) . '</div>' : '');
};

render_header(__('Add a document'), 'upload');
?>
<div class="page-head">
  <p class="eyebrow"><?= e(__('Records capture')) ?></p>
  <h1><?= e(__('Add a document')) ?></h1>
  <p class="muted"><?= e(__('Good metadata is what makes a record findable. Fields marked * are required; the reference number is generated if left empty.')) ?>
    <?= e(__('Fill in the French and/or English text: users see the document in their chosen language, or in the only language available.')) ?></p>
</div>

<?php if ($errors): ?><div class="flash flash-error" role="alert"><?= e(__('Please correct the highlighted fields.')) ?></div><?php endif; ?>

<form class="card form-grid" method="post" enctype="multipart/form-data" action="upload.php" novalidate>
  <?= csrf_field() ?>
  <fieldset>
    <legend><?= e(__('Identification')) ?></legend>
    <?= $langPair('title', __('Title') . ' *', 'input', 'Minutes of the 46th Senate Meeting', 'Procès-verbal de la 46e réunion du Sénat') ?>
    <div>
      <label for="document_type"><?= e(__('Document type')) ?> *</label>
      <select id="document_type" name="document_type"<?= $cls('document_type') ?>>
        <option value=""><?= e(__('Choose…')) ?></option>
        <?php foreach (array_keys(Catalog::TYPES) as $key): ?><option value="<?= e($key) ?>" <?= $v['document_type'] === $key ? 'selected' : '' ?>><?= e(Catalog::typeLabel($key)) ?></option><?php endforeach; ?>
      </select><?= $err('document_type') ?>
    </div>
    <div>
      <label for="department_id"><?= e(__('Owning department')) ?> *</label>
      <select id="department_id" name="department_id"<?= $cls('department_id') ?>>
        <option value=""><?= e(__('Choose…')) ?></option>
        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= (int) $v['department_id'] === $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
      </select><?= $err('department_id') ?>
    </div>
    <div><label for="reference_no"><?= e(__('Reference number')) ?></label><input id="reference_no" name="reference_no" value="<?= e($v['reference_no']) ?>"<?= $cls('reference_no') ?> placeholder="<?= e(__('Generated automatically')) ?>"><?= $err('reference_no') ?></div>
    <div><label for="date_created"><?= e(__('Date of the document')) ?> *</label><input id="date_created" name="date_created" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($v['date_created']) ?>"<?= $cls('date_created') ?>><?= $err('date_created') ?></div>
    <div><label for="academic_year"><?= e(__('Academic year')) ?></label><input id="academic_year" name="academic_year" value="<?= e($v['academic_year']) ?>"<?= $cls('academic_year') ?> placeholder="2025/2026"><?= $err('academic_year') ?></div>
    <div></div>
    <?= $langPair('author', __('Author / signatory'), 'input', 'Academic Registrar', 'Registraire académique') ?>
  </fieldset>

  <fieldset>
    <legend><?= e(__('Student (only for student records)')) ?></legend>
    <div><label for="student_no"><?= e(__('Registration number')) ?></label><input id="student_no" name="student_no" value="<?= e($v['student_no']) ?>"<?= $cls('student_no') ?> placeholder="21/U/04567"><?= $err('student_no') ?></div>
    <div><label for="student_name"><?= e(__('Student name')) ?></label><input id="student_name" name="student_name" value="<?= e($v['student_name']) ?>"></div>
    <div class="span-2"><label for="programme"><?= e(__('Programme')) ?></label><input id="programme" name="programme" value="<?= e($v['programme']) ?>" placeholder="Bachelor of Science in Computer Science"></div>
  </fieldset>

  <fieldset>
    <legend><?= e(__('Access & description')) ?></legend>
    <div class="span-2">
      <span class="label"><?= e(__('Access level')) ?> *</span>
      <div class="radio-row">
        <?php foreach ($levels as $level): ?>
          <label class="radio-card"><input type="radio" name="access_level" value="<?= e($level) ?>" <?= $v['access_level'] === $level ? 'checked' : '' ?>>
            <?= access_badge($level) ?><span class="small muted"><?= e(Catalog::accessDescription($level)) ?></span></label>
        <?php endforeach; ?>
      </div><?= $err('access_level') ?>
    </div>
    <?= $langPair('keywords', __('Keywords (comma separated)'), 'input', 'senate, results approval, graduation', 'sénat, approbation des résultats, remise des diplômes') ?>
    <?= $langPair('description', __('Summary'), 'textarea') ?>
  </fieldset>

  <fieldset>
    <legend><?= e(__('Content')) ?></legend>
    <div><label for="file"><?= e(__('File (PDF, DOCX, TXT, MD — max {size} MB)', ['size' => $maxMb])) ?></label><input id="file" name="file" type="file" accept=".pdf,.docx,.txt,.md"<?= $cls('file') ?>><?= $err('file') ?></div>
    <div>
      <label for="file_language"><?= e(__('Language of the file')) ?></label>
      <select id="file_language" name="file_language">
        <option value="en" <?= $v['file_language'] === 'en' ? 'selected' : '' ?>><?= e(__('English')) ?></option>
        <option value="fr" <?= $v['file_language'] === 'fr' ? 'selected' : '' ?>><?= e(__('French')) ?></option>
      </select>
    </div>
    <p class="small muted span-2"><?= e(__('Text is extracted automatically from DOCX, TXT and text-based PDFs. For scanned PDFs, paste the text below so the document can be found. Paste a translation in the other language to make the document bilingual.')) ?></p>
    <?= $langPair('content', __('Document text'), 'textarea') ?>
  </fieldset>

  <div class="form-actions"><a class="btn" href="index.php"><?= e(__('Cancel')) ?></a><button class="btn btn-primary" type="submit"><?= e(__('Save and index')) ?></button></div>
</form>
<?php
render_footer();
