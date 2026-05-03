<?php
/* One-time poster upload helper — delete this file after use */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['poster'])) {
    $dest = __DIR__ . '/tumaini-lenye-baraka-2026.jpg';
    if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest)) {
        echo '<p style="color:green;font-family:sans-serif;">✅ Uploaded successfully! You can delete this file now.</p>';
    } else {
        echo '<p style="color:red;font-family:sans-serif;">❌ Upload failed.</p>';
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Upload Poster</title></head>
<body style="font-family:sans-serif;max-width:400px;margin:3rem auto;">
<h3>Upload Event Poster</h3>
<p>Upload the <strong>Tumaini Lenye Baraka</strong> poster image.</p>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="poster" accept="image/*" required style="margin-bottom:1rem;display:block;">
  <button type="submit" style="background:#f9bf3f;border:none;padding:.6rem 1.5rem;border-radius:8px;font-weight:800;cursor:pointer;">Upload</button>
</form>
</body></html>
