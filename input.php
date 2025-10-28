<?php
require 'config.php';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Input Data — Diagnosa Gizi</title>
<meta name="description" content="Input data untuk diagnosa gizi: berat, tinggi, jenis kelamin, aktivitas.">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <h2>Input Data Diri</h2>
  <form action="diagnose.php" method="post">
    <div class="form-row">
      <label>Nama</label>
      <input type="text" name="name" required>
    </div>
    <div class="form-row">
      <label>Jenis Kelamin</label>
      <select name="gender" required>
        <option value="L">Laki-laki</option>
        <option value="P">Perempuan</option>
      </select>
    </div>
    <div class="form-row">
      <label>Berat Badan (kg)</label>
      <input type="number" name="weight" step="0.1" required>
    </div>
    <div class="form-row">
      <label>Tinggi Badan (cm)</label>
      <input type="number" name="height" step="0.1" required>
    </div>
    <div class="form-row">
      <label>Aktivitas Fisik</label>
      <select name="activity" required>
        <option value="ringan">Ringan (faktor 1.55)</option>
        <option value="sedang">Sedang (faktor 1.70)</option>
        <option value="berat">Berat (faktor 2.00)</option>
      </select>
    </div>
    <button type="submit">Lanjut Pilih Gejala</button>
  </form>
</div>
</body>
</html>
