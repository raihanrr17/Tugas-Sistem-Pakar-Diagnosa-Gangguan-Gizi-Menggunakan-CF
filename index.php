<?php
require 'config.php';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Sistem Pakar Diagnosa Gizi — Tes IMT & CF</title>
  <meta name="description" content="Sistem pakar diagnosa gangguan gizi berbasis Certainty Factor. Hitung IMT, pilih gejala, dapatkan diagnosa dan rekomendasi gizi harian.">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header>
      <h1>Sistem Pakar Diagnosa Gizi</h1>
      <p class="note">Masukkan data (BB/TB), pilih gejala yang dialami, sistem akan mendiagnosa menggunakan metode Certainty Factor dan memberikan rekomendasi kalori.</p>
    </header>

    <main>
      <a href="input.php"><button>Mulai Diagnosa</button></a>
      <hr>
      <h3>Riwayat Diagnosa Terakhir</h3>
      <?php
      $res = $mysqli->query("SELECT * FROM diagnoses ORDER BY created_at DESC LIMIT 5");
      if($res->num_rows){
          echo "<ul>";
          while($row = $res->fetch_assoc()){
              echo "<li><strong>".htmlspecialchars($row['name'])."</strong> — Hasil: {$row['result_disease']} ({$row['cf_score']}), Kalori: {$row['total_calorie']} kkal — <small>{$row['created_at']}</small></li>";
          }
          echo "</ul>";
      } else {
          echo "<p>Tidak ada riwayat.</p>";
      }
      ?>
    </main>
  </div>
</body>
</html>
