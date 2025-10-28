async function loadRules() {
  const response = await fetch("/rules.json");
  return await response.json();
}

const gejalaList = [
  "bengkak_perut", "diare", "rambut_mudah_rontok", "kulit_kering",
  "lesu", "nafsu_makan_hilang", "berat_badan_turun", "berat_badan_tidak_naik",
  "sering_kelelahan", "haus", "sakit_kepala", "pusing",
  "detak_jantung_cepat", "sering_berkeringat", "nyeri_dada",
  "sesak_napas", "muntah", "sering_mual", "berkeringat_dingin",
  "wajah_kemerahan", "berat_badan_berlebih"
];

window.onload = () => {
  const list = document.getElementById("gejala-list");
  gejalaList.forEach(g => {
    const label = document.createElement("label");
    label.innerHTML = `<input type="checkbox" value="${g}"> ${g.replaceAll("_", " ")}`;
    list.appendChild(label);
    list.appendChild(document.createElement("br"));
  });
};

document.getElementById("btnProses").addEventListener("click", async () => {
  const berat = parseFloat(document.getElementById("berat").value);
  const tinggi = parseFloat(document.getElementById("tinggi").value);
  const aktivitas = parseFloat(document.getElementById("aktivitas").value);
  const rules = await loadRules();

  if (!berat || !tinggi || !aktivitas) {
    alert("Lengkapi berat badan, tinggi badan, dan jenis aktivitas terlebih dahulu.");
    return;
  }

  const imt = berat / Math.pow(tinggi / 100, 2);
  let kategori = "";
  if (imt < 18.5) kategori = "kurus";
  else if (imt <= 25) kategori = "normal";
  else kategori = "gemuk";

  const gejalaTerpilih = Array.from(document.querySelectorAll("#gejala-list input:checked"))
                        .map(c => c.value);

  const { hasilDiagnosa, penjelasanProses } = inferensi(rules, kategori, gejalaTerpilih);
  const rekomendasiKalori = hitungRekomendasiKalori(berat, tinggi, kategori, aktivitas);

  tampilkanHasil(hasilDiagnosa, imt, kategori, rekomendasiKalori, gejalaTerpilih, penjelasanProses);
});

function inferensi(rules, kategori, fakta) {
  let kesimpulan = [];
  let penjelasan = [];

  rules.forEach(rule => {
    if (rule.kategori_imt.includes(kategori)) {
      const kondisi = rule.if;
      const gejalaTerpenuhi = kondisi.filter(g => fakta.includes(g));
      if (gejalaTerpenuhi.length > 0) {
        // Hitung CF antar gejala dalam satu rule (bisa lebih dari 2)
        let cfGabung = 0;
        let prosesCF = [];

        gejalaTerpenuhi.forEach((g, i) => {
          const cfGejala = rule.cf_per_gejala ? rule.cf_per_gejala[g] || rule.cf : rule.cf;
          if (i === 0) cfGabung = cfGejala;
          else cfGabung = combineCF(cfGabung, cfGejala);
          prosesCF.push(`Step ${i + 1}: CF = ${cfGabung.toFixed(3)}`);
        });

        penjelasan.push(
          `${rule.id} aktif karena gejala ${gejalaTerpenuhi.join(", ")} ditemukan.<br>
          CF antar gejala dihitung berurutan:<br>${prosesCF.join("<br>")}<br>
          Hasil CF gabungan rule = <b>${cfGabung.toFixed(3)}</b><br>`
        );

        kesimpulan.push({ penyakit: rule.then, cf: cfGabung });
      }
    }
  });

  // Gabungkan antar rule dengan penyakit sama
  let hasilGabung = {};
  kesimpulan.forEach(h => {
    if (!hasilGabung[h.penyakit]) hasilGabung[h.penyakit] = h.cf;
    else hasilGabung[h.penyakit] = combineCF(hasilGabung[h.penyakit], h.cf);
  });

  const sorted = Object.entries(hasilGabung)
    .map(([penyakit, cf]) => ({ penyakit, cf }))
    .sort((a, b) => b.cf - a.cf);

  return { hasilDiagnosa: sorted, penjelasanProses: penjelasan };
}

function combineCF(cfOld, cfGejala) {
  return cfOld + cfGejala * (1 - cfOld);
}

function hitungRekomendasiKalori(berat, tinggi, kategori, aktivitas) {
  const jenisKelamin = "pria";
  let amb = jenisKelamin === "pria" ? 1 * berat * 24 : 0.95 * berat * 24;
  let totalKalori = amb * aktivitas;
  if (kategori === "kurus") totalKalori += 500;
  else if (kategori === "gemuk") totalKalori -= 500;

  return {
    total: totalKalori,
    sarapan: totalKalori * 0.20,
    cemilan1: totalKalori * 0.15,
    makanSiang: totalKalori * 0.30,
    cemilan2: totalKalori * 0.15,
    makanMalam: totalKalori * 0.20
  };
}

function tampilkanHasil(hasil, imt, kategori, rekom, gejalaTerpilih, penjelasanProses) {
  const div = document.getElementById("hasil");
  div.innerHTML = `<b>IMT:</b> ${imt.toFixed(2)} (${kategori})<br><br>`;
  div.innerHTML += `<b>Gejala dipilih:</b><br>${gejalaTerpilih.join(", ").replaceAll("_", " ")}<br><br>`;

  if (hasil.length === 0) {
    div.innerHTML += "Tidak ada penyakit yang terdeteksi.<br><br>";
  } else {
    div.innerHTML += "<b>Hasil Diagnosis:</b><br>";
    hasil.forEach((h, i) => {
      div.innerHTML += `${i + 1}. ${h.penyakit.replaceAll("_", " ")} - CF: ${(h.cf * 100).toFixed(1)}%<br>`;
    });
  }

  div.innerHTML += `<br><b>Proses Penentuan Diagnosa:</b><br>`;
  if (penjelasanProses.length === 0) div.innerHTML += "Tidak ada rule yang aktif.<br>";
  else penjelasanProses.forEach(p => div.innerHTML += `• ${p}<br>`);

  div.innerHTML += `<br><b>Rekomendasi Kalori Harian:</b><br>
    Total energi: ${rekom.total.toFixed(0)} kkal/hari<br>
    Sarapan: ${rekom.sarapan.toFixed(0)} kkal<br>
    Cemilan pagi: ${rekom.cemilan1.toFixed(0)} kkal<br>
    Makan siang: ${rekom.makanSiang.toFixed(0)} kkal<br>
    Cemilan sore: ${rekom.cemilan2.toFixed(0)} kkal<br>
    Makan malam: ${rekom.makanMalam.toFixed(0)} kkal<br>`;
}

