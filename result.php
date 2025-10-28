<?php
require 'config.php';

// ambil semua input
$name = $_POST['name'] ?? '';
$gender = $_POST['gender'] ?? 'L';
$weight = floatval($_POST['weight'] ?? 0);
$height = floatval($_POST['height'] ?? 0);
$activity = $_POST['activity'] ?? 'ringan';
$imt = floatval($_POST['imt'] ?? 0);
$imt_category = $_POST['imt_category'] ?? 'normal';

if(!$name || !$weight || !$height){
    die("Data tidak lengkap. Kembali ke <a href='input.php'>form</a>.");
}

// ambil semua rules untuk kategori IMT
$stmt = $mysqli->prepare("
    SELECT r.id AS rule_id, r.disease_id, r.symptom_id, r.mb, r.md, d.name AS disease_name, s.text AS symptom_text
    FROM rules r
    JOIN diseases d ON r.disease_id = d.id
    JOIN symptoms s ON r.symptom_id = s.id
    WHERE r.imt_category = ?
    ORDER BY r.disease_id
");
$stmt->bind_param("s",$imt_category);
$stmt->execute();
$res = $stmt->get_result();
$rules = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if(!$rules){
    die("Aturan untuk kategori IMT ini belum tersedia. Hubungi admin.");
}

// kumpulkan jawaban user menjadi array keyed by symptom_id
$user_answers = [];
foreach($_POST as $k=>$v){
    if(strpos($k,'sym_')===0){
        $sym_id = intval(substr($k,4));
        $user_answers[$sym_id] = ($v === 'yes');
    }
}

// menghitung CF per disease
// struktur: disease_id => array of CF for each selected symptom
$disease_cfs = [];

// loop rules
foreach($rules as $r){
    $did = $r['disease_id'];
    $sid = $r['symptom_id'];
    // hanya jika pengguna memilih gejala ini (YA)
    if(isset($user_answers[$sid]) && $user_answers[$sid]){
        $mb = floatval($r['mb']);
        $md = floatval($r['md']);
        $cf = $mb - $md; // CF single rule
        if(!isset($disease_cfs[$did])) $disease_cfs[$did] = ['name'=>$r['disease_name'],'cfs'=>[]];
        $disease_cfs[$did]['cfs'][] = $cf;
    }
}

// fungsi menggabungkan CF bertingkat: CF_total = CF1 + CF2*(1-CF1) dan seterusnya
function combine_cfs($cfs){
    if(!$cfs) return 0.0;
    $c = $cfs[0];
    for($i=1;$i<count($cfs);$i++){
        $c = $c + $cfs[$i] * (1 - $c);
    }
    // pastikan antara 0 dan 1
    if($c < 0) $c = 0;
    if($c > 1) $c = 1;
    return round($c, 2);
}

// hitung CF total masing-masing disease
$results = [];
foreach($disease_cfs as $did => $data){
    $combined = combine_cfs($data['cfs']);
    $results[] = ['disease_id'=>$did,'name'=>$data['name'],'cf'=>$combined];
}

// jika tidak ada gejala dipilih user -> default no diagnosis
if(empty($results)){
    $best = ['name'=>'Tidak ada gejala dipilih','cf'=>0.0];
} else {
    // pilih disease dengan cf tertinggi
    usort($results, function($a,$b){ return $b['cf'] <=> $a['cf']; });
    $best = $results[0];
}

// =======================
// BAGIAN REKOMENDASI KALORI
// =======================
if($gender == 'L'){
    $amb = 1.0 * $weight * 24;
} else {
    $amb = 0.95 * $weight * 24;
}
$factor = 1.55;
if($activity == 'ringan') $factor = 1.55;
elseif($activity == 'sedang') $factor = 1.70;
elseif($activity == 'berat') $factor = 2.00;

$total_calorie = round($amb * $factor);

// penyesuaian berdasar IMT: jika gemuk => -500, kurus => +500, normal => 0
if($imt_category == 'gemuk') $total_calorie_adj = max(1200, $total_calorie - 500);
elseif($imt_category == 'kurus') $total_calorie_adj = $total_calorie + 500;
else $total_calorie_adj = $total_calorie;

// pembagian makan
$breakfast = round($total_calorie_adj * 0.20);
$snack1 = round($total_calorie_adj * 0.15);
$lunch = round($total_calorie_adj * 0.30);
$snack2 = round($total_calorie_adj * 0.15);
$dinner = round($total_calorie_adj * 0.20);

// simpan diagnosa ke DB
$insert = $mysqli->prepare("INSERT INTO diagnoses (name, gender, weight, height, activity_level, imt, imt_category, result_disease, cf_score, total_calorie) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insert->bind_param("ssdddssdii", $name, $gender, $weight, $height, $activity, $imt, $imt_category, $best['name'], $best['cf'], $total_calorie_adj);
$insert->execute();
$insert->close();

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Hasil Diagnosa — Sistem Pakar Gizi</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <h2>Hasil Diagnosa</h2>

  <div class="result">
    <p><strong>Nama:</strong> <?php echo htmlspecialchars($name); ?></p>
    <p><strong>IMT:</strong> <?php echo $imt; ?> — <strong>Kategori:</strong> <?php echo htmlspecialchars($imt_category); ?></p>
    <hr>
    <h3>Diagnosis Terkuat</h3>
    <p><strong><?php echo htmlspecialchars($best['name']); ?></strong> — Tingkat keyakinan: <strong><?php echo ($best['cf']*100); ?>%</strong></p>

    <h4>Rincian Perhitungan CF (semua penyakit dengan gejala terpilih)</h4>
    <ul>
    <?php
    if(!empty($results)){
        foreach($results as $r){
            echo "<li>".htmlspecialchars($r['name'])." — CF = ".($r['cf']*100)."%</li>";
        }
    } else {
        echo "<li>Tidak ada gejala dipilih.</li>";
    }
    ?>
    </ul>

    <h3>Rekomendasi Kebutuhan Energi Harian</h3>
    <p>Total kebutuhan kalori (dengan penyesuaian IMT): <strong><?php echo $total_calorie_adj; ?> kkal/hari</strong></p>
    <ul>
      <li>Sarapan (20%): <?php echo $breakfast; ?> kkal</li>
      <li>Cemilan Pagi (15%): <?php echo $snack1; ?> kkal</li>
      <li>Makan Siang (30%): <?php echo $lunch; ?> kkal</li>
      <li>Cemilan Sore (15%): <?php echo $snack2; ?> kkal</li>
      <li>Makan Malam (20%): <?php echo $dinner; ?> kkal</li>
    </ul>

    <p><small class="note">Catatan: Hasil diagnosa adalah estimasi berdasarkan model pakar; gunakan sebagai referensi awal dan konsultasikan dengan ahli gizi/tenaga medis bila perlu.</small></p>

    <a href="index.php"><button>Kembali ke Beranda</button></a>
  </div>
</div>
</body>
</html>
