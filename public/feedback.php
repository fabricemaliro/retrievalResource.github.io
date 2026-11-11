<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\App;
use App\Evaluation;
use App\I18n;

$user = require_login();
$questions = [
    'ease' => __('The system was easy to use.'),
    'relevance' => __('The results matched what I was looking for.'),
    'speed' => __('I found the document quickly.'),
    'filters_useful' => __('The filters and sorting helped me narrow the results.'),
    'satisfaction' => __('Overall, I would use this system for my work.'),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $scores = [];
    foreach ($questions as $key => $_) {
        $value = (int) ($_POST[$key] ?? 0);
        if ($value < 1 || $value > 5) {
            $errors[$key] = true;
        }
        $scores[$key] = $value;
    }
    $task = in_array($_POST['task'] ?? '', array_merge(array_column(Evaluation::QUERIES, 'id'), ['other']), true) ? $_POST['task'] : null;
    $found = isset($_POST['found']) && in_array($_POST['found'], ['0', '1'], true) ? (int) $_POST['found'] : null;
    $clicks = ($_POST['clicks'] ?? '') !== '' ? max(0, min(99, (int) $_POST['clicks'])) : null;
    $comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 2000);

    if (!$errors) {
        App::db()->prepare(
            'INSERT INTO feedback (user_id, task, found, clicks, ease, relevance, speed, filters_useful, satisfaction, comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$user['id'], $task, $found, $clicks, ...array_values($scores), $comment ?: null, date('Y-m-d H:i:s')]);
        flash(__('Thank you — your feedback has been recorded.'), 'success');
        redirect('index.php');
    }
}

render_header(__('Feedback'), 'feedback');
?>
<div class="page-head">
  <p class="eyebrow"><?= e(__('Peer evaluation')) ?></p>
  <h1><?= e(__('How did the search go?')) ?></h1>
  <p class="muted"><?= e(__('Try one task, then rate the system. Your answers feed the evaluation report.')) ?></p>
</div>

<form class="card narrow" method="post" action="feedback.php">
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="flash flash-error" role="alert"><?= e(__('Please answer every rating question.')) ?></div><?php endif; ?>

  <label for="task"><?= e(__('Which task did you try?')) ?></label>
  <select id="task" name="task">
    <?php foreach (Evaluation::QUERIES as $q): $q = Evaluation::localized($q, I18n::lang()); ?><option value="<?= e($q['id']) ?>"><?= e($q['id'] . ' — ' . $q['need']) ?></option><?php endforeach; ?>
    <option value="other"><?= e(__('Another search of my own')) ?></option>
  </select>

  <div class="inline-fields">
    <fieldset class="plain"><legend><?= e(__('Did you find the document?')) ?></legend>
      <label class="check"><input type="radio" name="found" value="1"> <?= e(__('Yes')) ?></label>
      <label class="check"><input type="radio" name="found" value="0"> <?= e(__('No')) ?></label>
    </fieldset>
    <div><label for="clicks"><?= e(__('How many clicks did it take?')) ?></label><input id="clicks" name="clicks" type="number" min="0" max="99" inputmode="numeric"></div>
  </div>

  <?php foreach ($questions as $key => $text): ?>
    <fieldset class="likert<?= isset($errors[$key]) ? ' invalid' : '' ?>">
      <legend><?= e($text) ?></legend>
      <div class="likert-scale">
        <span class="small muted"><?= e(__('Disagree')) ?></span>
        <?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="<?= e($key) ?>" value="<?= $i ?>" <?= (int) ($_POST[$key] ?? 0) === $i ? 'checked' : '' ?>><span><?= $i ?></span></label><?php endfor; ?>
        <span class="small muted"><?= e(__('Agree')) ?></span>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <label for="comment"><?= e(__('What should be improved?')) ?></label>
  <textarea id="comment" name="comment" rows="4" placeholder="<?= e(__('e.g. I expected to filter by programme…')) ?>"><?= e($_POST['comment'] ?? '') ?></textarea>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('Send feedback')) ?></button></div>
</form>
<?php
render_footer();
