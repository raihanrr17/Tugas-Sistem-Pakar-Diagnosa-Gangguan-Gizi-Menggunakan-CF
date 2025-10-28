<?php
require 'config.php';

// ambil input
$name = $_POST['name'] ?? '';
$gender = $_POST['gender'] ?? 'L';
$weight = floatval($_POST['weight'] ?? 0);
$height = floatval($_POST['height'] ?? 0);
$activity = $_POST['activity'] ?? 'ringan';

if(!$name || !$weight || !$height){
    die("Data tidak lengkap. Kembali ke <a href='input.php'>form</a>.");
}

// hitung IMT
$imt = 0;
if($height > 0){
    $imt = $weight / (($height/100)*($height/100));
    $imt = round($imt,2);
}

// tentukan kategori
$category = '';
if($imt < 18.5) $category = 'kurus';
elseif($imt >= 18.5 && $imt <= 25.0) $category = 'normal';
else $category = 'gemuk';

// ambil daftar gejala terkait kategori (distinct symptoms from rules)
$stmt = $mysqli->prepare("
    SELECT DISTINCT s.id, s.code, s.text
    FROM rules r
    JOIN symptoms s ON r.symptom_id = s.id
    WHERE r.imt_category = ?
    ORDER BY s.id
");
$stmt->bind_param("s",$category);
$stmt->execute();
$res = $stmt->get_result();
$symptoms = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// simpan data sementara (kirim ke result.php via POST, atau bisa simpan session)
// untuk sederhana, kirimkan via form POST hidden fields
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Pilih Gejala — Diagnosa Gizi</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <h2>Pilih Gejala (Kategori IMT: <?php echo htmlspecialchars($category); ?> — IMT: <?php echo $imt; ?>)</h2>
  <p>Centang gejala yang kamu alami (jawab YA untuk yang terjadi).</p>

  <form action="result.php" method="post">
    <!-- kirim data user -->
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
    <input type="hidden" name="weight" value="<?php echo htmlspecialchars($weight); ?>">
    <input type="hidden" name="height" value="<?php echo htmlspecialchars($height); ?>">
    <input type="hidden" name="activity" value="<?php echo htmlspecialchars($activity); ?>">
    <input type="hidden" name="imt" value="<?php echo $imt; ?>">
    <input type="hidden" name="imt_category" value="<?php echo $category; ?>">

    <div class="symptom-list">
      <?php if(count($symptoms)): foreach($symptoms as $s): ?>
        <div class="card">
          <label><?php echo htmlspecialchars($s['text']); ?></label>
          <select name="sym_<?php echo $s['id']; ?>">
            <option value="no">TIDAK</option>
            <option value="yes">YA</option>
          </select>
        </div>
      <?php endforeach; else: ?>
        <p>Tidak ada gejala ditemukan untuk kategori ini.</p>
      <?php endif; ?>
    </div>

    <div style="margin-top:14px;">
      <button type="submit">Proses Diagnosa</button>
    </div>
  </form>
</div>
</body>
</html>
