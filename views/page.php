<?php /** Template only: every dynamic value goes through h(). Vars: $message $uid $role $notes $editing $csrf */ ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<meta name="csrf-token" content="<?= h($csrf) ?>">
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}form.inline{display:inline}</style>
</head>
<body>
<h1>Campus Notes</h1>
<p><?= h($message) ?></p>
<?php if ($uid === null): ?>
<h2>Register or sign in</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <label>Email <input name="email" type="email" maxlength="254" required></label>
  <label>Password <input name="password" type="password" minlength="8" maxlength="72" required></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  User ID: <?= h((string) $uid) ?> | Role: <?= h($role) ?> | <button name="action" value="logout">Log out</button>
</form>
<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" value="<?= h((string) $editing['id']) ?>">
  <label>Title <input name="title" value="<?= h($editing['title']) ?>" maxlength="120" required></label>
  <label>Body <textarea name="body" maxlength="5000" required><?= h($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>
<h2>Create a note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <label>Title <input name="title" maxlength="120" required></label>
  <label>Body <textarea name="body" maxlength="5000" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>
<h2>Notes</h2>
<?php foreach ($notes as $note): ?>
<article><h3><?= h($note['title']) ?></h3><p><?= h($note['body']) ?></p>
<a href="?edit=<?= h((string) $note['id']) ?>">Edit</a>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" value="<?= h((string) $note['id']) ?>">
  <button name="action" value="delete">Delete</button>
</form></article>
<?php endforeach; ?>
<?php endif; ?>
</body></html>
