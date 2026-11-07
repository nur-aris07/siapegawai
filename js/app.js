"use strict";

/* Siapegawai v4: domain-specific, dependency-free prototype.
   Sample questions and explanations are NOT an official BKN question set. */
const Q = [
  {
    id: "twk-01",
    cat: "TWK",
    q: "Apa kedudukan Pancasila dalam sistem ketatanegaraan Indonesia?",
    options: [
      "Dasar negara dan pandangan hidup bangsa",
      "Pedoman teknis penyusunan APBD",
      "Aturan pelaksanaan satu kementerian",
      "Peraturan daerah tingkat provinsi",
    ],
    a: 0,
    why: "Pancasila merupakan dasar negara dan menjadi pedoman nilai dalam kehidupan berbangsa dan bernegara.",
  },
  {
    id: "twk-02",
    cat: "TWK",
    q: "Semboyan Bhinneka Tunggal Ika terutama mengajarkan tentang ...",
    options: [
      "Keseragaman budaya di seluruh daerah",
      "Persatuan di tengah keberagaman",
      "Pembatasan penggunaan bahasa daerah",
      "Pemisahan kelompok sosial",
    ],
    a: 1,
    why: "Bhinneka Tunggal Ika berarti berbeda-beda tetapi tetap satu, menekankan persatuan dalam keberagaman.",
  },
  {
    id: "twk-03",
    cat: "TWK",
    q: "Lembaga negara yang berwenang menguji undang-undang terhadap UUD 1945 adalah ...",
    options: [
      "Komisi Yudisial",
      "Badan Pemeriksa Keuangan",
      "Mahkamah Konstitusi",
      "Mahkamah Agung",
    ],
    a: 2,
    why: "Mahkamah Konstitusi berwenang menguji undang-undang terhadap Undang-Undang Dasar 1945.",
  },
  {
    id: "tiu-01",
    cat: "TIU",
    q: "Jika 3 buku seharga Rp45.000, berapa harga 7 buku dengan harga satuan yang sama?",
    options: ["Rp90.000", "Rp100.000", "Rp105.000", "Rp115.000"],
    a: 2,
    why: "Harga satu buku adalah Rp45.000 ÷ 3 = Rp15.000. Jadi 7 buku = 7 × Rp15.000 = Rp105.000.",
  },
  {
    id: "tiu-02",
    cat: "TIU",
    q: "Lanjutkan deret bilangan: 2, 5, 10, 17, 26, ...",
    options: ["35", "36", "37", "39"],
    a: 2,
    why: "Selisihnya berturut-turut 3, 5, 7, 9, sehingga selisih berikutnya 11. Hasilnya 26 + 11 = 37.",
  },
  {
    id: "tiu-03",
    cat: "TIU",
    q: "Semua analis adalah pegawai. Sebagian pegawai adalah peneliti. Kesimpulan yang pasti benar adalah ...",
    options: [
      "Semua peneliti adalah analis",
      "Sebagian analis adalah peneliti",
      "Semua analis adalah pegawai",
      "Tidak ada pegawai yang menjadi analis",
    ],
    a: 2,
    why: "Pernyataan yang pasti benar adalah premis pertama: semua analis merupakan pegawai. Hubungan analis dengan peneliti tidak dapat disimpulkan.",
  },
  {
    id: "tkp-01",
    cat: "TKP",
    q: "Seorang warga kesulitan menggunakan layanan digital. Respons paling tepat sebagai petugas adalah ...",
    options: [
      "Mengarahkan untuk datang lagi bersama keluarga",
      "Memberikan panduan dengan sabar dan memastikan layanan dapat diakses",
      "Meminta warga mempelajari aplikasi sendiri",
      "Mendahulukan pengguna yang sudah mahir",
    ],
    a: 1,
    why: "Dalam konteks latihan pelayanan publik, pendekatan inklusif dan bantuan yang jelas lebih mendukung akses layanan bagi seluruh warga.",
  },
  {
    id: "tkp-02",
    cat: "TKP",
    q: "Tugas tim berubah karena aturan baru, sedangkan tenggat tetap dekat. Langkah paling konstruktif adalah ...",
    options: [
      "Tetap menggunakan cara lama",
      "Menunggu rekan kerja menyelesaikan seluruh perubahan",
      "Menolak perubahan karena tenggat singkat",
      "Mengklarifikasi aturan, membagi prioritas, dan menyesuaikan rencana tim",
    ],
    a: 3,
    why: "Adaptasi yang terencana membantu tim memahami perubahan, menjaga koordinasi, dan memprioritaskan pekerjaan yang paling penting.",
  },
  {
    id: "tkp-03",
    cat: "TKP",
    q: "Kamu menemukan kesalahan angka pada laporan tim yang belum dikirim. Apa tindakan terbaik?",
    options: [
      "Memperbaiki bersama pemilik data dan memberi tahu penanggung jawab",
      "Membiarkan agar jadwal tidak terganggu",
      "Mengubah angka diam-diam tanpa pemeriksaan",
      "Meminta pihak lain mengambil alih tanpa menjelaskan",
    ],
    a: 0,
    why: "Mengonfirmasi sumber data, memperbaiki kesalahan, dan berkomunikasi secara transparan mendukung akurasi serta akuntabilitas.",
  },
  {
    id: "skb-01",
    cat: "SKB",
    q: "Dalam pengelolaan basis data, perintah SQL untuk mengambil data dari tabel adalah ...",
    options: ["CREATE", "SELECT", "DROP", "INSERT"],
    a: 1,
    why: "Perintah SELECT digunakan untuk membaca atau mengambil data dari satu atau lebih tabel.",
  },
  {
    id: "skb-02",
    cat: "SKB",
    q: "Untuk mengurangi risiko kehilangan data, praktik yang paling tepat adalah ...",
    options: [
      "Menaruh semua salinan di satu perangkat",
      "Hanya melakukan pencadangan saat ada masalah",
      "Melakukan backup berkala dan menguji proses pemulihannya",
      "Menonaktifkan pencatatan perubahan",
    ],
    a: 2,
    why: "Backup yang rutin perlu dilengkapi pengujian pemulihan agar data benar-benar dapat dipulihkan ketika terjadi insiden.",
  },
  {
    id: "skb-03",
    cat: "SKB",
    q: "Apa tujuan utama API dalam sebuah sistem informasi?",
    options: [
      "Memfasilitasi komunikasi antarkomponen/perangkat lunak",
      "Menghapus kebutuhan pengamanan data",
      "Menggantikan semua basis data",
      "Menentukan seluruh desain visual aplikasi",
    ],
    a: 0,
    why: "API menyediakan antarmuka agar aplikasi atau komponen sistem dapat saling berinteraksi secara terstruktur.",
  },
];
const examQuestions = Q.slice(0, 9);
const alphabet = ["A", "B", "C", "D", "E"];
const STORE = "siapegawai_v3_attempts"; // retain prior local attempts
const app = document.getElementById("app");
const nav = document.getElementById("main-nav");
let currentRoute = "";
let exam = null,
  clock = null,
  bankFilter = "Semua",
  bankSearch = "";
function ic(name, extra = "") {
  return `<svg class="icon ${extra}" aria-hidden="true"><use href="#ic-${name}"></use></svg>`;
}
function hydrateIcons(scope = document) {
  scope.querySelectorAll("[data-icon]").forEach((el) => {
    const name = el.dataset.icon;
    el.innerHTML = ic(name);
  });
}
function safe(s) {
  return String(s).replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
}
function storageRead() {
  try {
    const x = JSON.parse(localStorage.getItem(STORE) || "[]");
    return Array.isArray(x)
      ? x
          .filter((v) => Number.isFinite(v.correct) && Number.isFinite(v.total))
          .slice(0, 50)
      : [];
  } catch {
    return [];
  }
}
function storageWrite(xs) {
  try {
    localStorage.setItem(STORE, JSON.stringify(xs.slice(0, 50)));
    return true;
  } catch {
    return false;
  }
}
function storageUsable() {
  try {
    const k = "siapegawai_storage_check";
    localStorage.setItem(k, "1");
    localStorage.removeItem(k);
    return true;
  } catch {
    return false;
  }
}
function pageIntro(section, title, subtitle) {
  return `<section class="page-intro"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} <span>${safe(section)}</span></div><span class="eyebrow">${safe(section)}</span><h1>${title}</h1><p>${subtitle}</p></div></section>`;
}
function notice(text) {
  return `<div class="notice">${ic("info")}<span>${text}</span></div>`;
}

function renderHome() {
  return `
  <section class="hero"><div class="container hero-grid"><div class="hero-copy"><span class="eyebrow">PERSIAPAN SELEKSI CPNS</span><h1>Belajar CPNS,<br> dengan arah yang <em>jelas.</em></h1><p class="hero-sub">Latih kemampuan SKD, pahami alasan di balik setiap jawaban, lalu tentukan materi yang perlu kamu perkuat. Semua dimulai dari satu latihan kecil.</p><div class="hero-actions"><a href="#/latihan" class="button button-primary">Coba latihan SKD ${ic("arrow-right")}</a><a class="text-link" href="#/bank-soal">Jelajahi bank soal ${ic("arrow-right")}</a></div><div class="hero-points"><span>${ic("check")} Tanpa akun untuk mencoba</span><span>${ic("check")} Ada pembahasan</span><span>${ic("check")} Bisa dari HP</span></div></div><div class="hero-visual"><div class="visual-top">KENALAN DENGAN SIAPEGAWAI</div><div class="visual-index">01.</div><img src="assets/group-khaki.webp" width="1160" height="689" alt="Ilustrasi Nara, Bima, dan Laras dengan seragam khaki, membawa laptop, tablet, dan buku" fetchpriority="high"><div class="visual-bottom"><strong>Mulai belajar dari hal yang paling penting.</strong><span>SKD · SKB · EVALUASI</span></div></div></div></section>
  <div class="manifesto"><div class="container manifesto-inner"><div class="manifesto-title">Bisa dicoba sekarang</div><div class="manifesto-item"><b>9 soal SKD</b><p>Satu sesi latihan dengan waktu 12 menit.</p></div><div class="manifesto-item"><b>12 contoh soal</b><p>TWK, TIU, TKP, dan contoh SKB.</p></div><div class="manifesto-item"><b>Riwayat di browser</b><p>Hasil latihan pada perangkat ini, tanpa akun.</p></div></div></div>
  <section class="section" id="alur"><div class="container"><div class="section-top"><div><span class="eyebrow">CARA BELAJAR</span><h2>Tiga langkah, satu tujuan:<br> lebih siap menghadapi tes.</h2></div><p>Kami menempatkan pengalaman latihan lebih dulu, sebelum kamu memutuskan perlu ikut kelas atau tidak.</p></div><div class="route-steps"><article class="route-step"><span class="route-step-num">01 / COBA</span><h3>Ukur kemampuan awal.</h3><p>Mulai dari tryout singkat TWK, TIU, dan TKP. Tidak perlu membuat akun atau memilih paket.</p><a class="text-link" href="#/latihan">Mulai latihan ${ic("arrow-right")}</a></article><article class="route-step"><span class="route-step-num">02 / PAHAMI</span><h3>Bedah jawabanmu.</h3><p>Lihat hasil per materi, baca alasan setiap jawaban, lalu lanjutkan ke bank soal.</p><a class="text-link" href="#/bank-soal">Buka bank soal ${ic("arrow-right")}</a></article><article class="route-step"><span class="route-step-num">03 / TINGKATKAN</span><h3>Perkuat yang belum mantap.</h3><p>Pelajari rencana kelas SKD dan SKB, agar waktu belajarmu lebih terfokus.</p><a class="text-link" href="#/kelas">Lihat kelas ${ic("arrow-right")}</a></article></div></div></section>
  <section class="section section-cream"><div class="container preview-split"><div class="preview-copy"><span class="eyebrow">DI DALAM LATIHAN</span><h2>Tidak cuma menjawab. Kamu perlu tahu kenapa.</h2><p>Ruang latihan dirancang sederhana: satu soal dalam satu waktu, navigasi nomor yang jelas, serta pembahasan setelah selesai.</p><ul class="evidence-list"><li>${ic("check")} Timer dan navigasi soal</li><li>${ic("check")} Tandai soal yang ingin ditinjau</li><li>${ic("check")} Evaluasi TWK, TIU, dan TKP</li></ul><a href="#/latihan" class="button button-primary">Masuk ke latihan ${ic("arrow-right")}</a><p class="tiny muted" style="margin-top:15px">Latihan ini contoh produk, bukan replika CAT BKN.</p></div><div class="question-sample" aria-label="Cuplikan tampilan soal latihan"><div class="sample-head"><strong>LATIHAN SKD / TWK</strong><span>Pratinjau soal</span></div><div class="sample-body"><div class="sample-label">SOAL 01 DARI 09</div><h3>${safe(Q[0].q)}</h3>${Q[0].options.map((o, i) => `<div class="sample-option"><span>${alphabet[i]}.</span><div>${safe(o)}</div></div>`).join("")}</div><div class="sample-footer"><details><summary>Lihat kunci dan pembahasan contoh</summary><p><strong>${alphabet[Q[0].a]}. ${safe(Q[0].options[Q[0].a])}</strong> — ${safe(Q[0].why)}</p></details></div></div></div></section>
  <section class="section"><div class="container"><div class="section-top"><div><span class="eyebrow">JALUR BELAJAR</span><h2>Pilih materi sesuai<br> tahapan seleksimu.</h2></div><p>Belum tentu semua orang butuh materi yang sama. Mulai dari tahapan yang sedang kamu persiapkan.</p></div><div class="line-courses"><article class="line-course"><div class="course-code">SKD</div><div class="course-name"><h3>Seleksi Kompetensi Dasar</h3><p>TWK, TIU, dan TKP dengan materi terstruktur.</p></div><div class="course-details"><b>Rencana pendampingan SKD</b> Silabus dan jadwal masih disusun.</div><a class="text-link" href="#/kelas">Detail kelas ${ic("arrow-right")}</a></article><article class="line-course"><div class="course-code">SKB</div><div class="course-name"><h3>Seleksi Kompetensi Bidang</h3><p>Pendalaman sesuai kebutuhan jabatan/formasi.</p></div><div class="course-details"><b>Rencana pendampingan SKB</b> Materi akan dipetakan per formasi.</div><a class="text-link" href="#/kelas">Detail kelas ${ic("arrow-right")}</a></article></div></div></section>
  <section class="brand-story"><div class="container story-grid"><div class="story-content"><span class="eyebrow eyebrow-light">KENALAN DENGAN KARAKTER KAMI</span><h2>Nara, Bima, dan Laras. Teman di perjalanan belajarmu.</h2><p>Tiga karakter asli Siapegawai hadir di berbagai seragam ASN. Identitas visualnya konsisten, sama seperti pengalaman belajar yang ingin kami bangun.</p><a href="#/kelas" class="text-link">Jelajahi kelas ${ic("arrow-right")}</a></div><div class="story-image"><img src="assets/group-korpri.webp" width="1160" height="689" loading="lazy" alt="Nara, Bima, dan Laras dalam seragam batik Korpri biru"></div></div></section>
  <section class="start-strip"><div class="container start-inner"><div><h2>Mulai saja dari sembilan soal.</h2><p>Belum perlu paket. Belum perlu komitmen panjang.</p></div><a href="#/latihan" class="button button-primary">Coba sekarang ${ic("arrow-right")}</a></div></section>`;
}

function renderTraining() {
  if (exam?.submitted) return renderResult();
  if (exam?.active) return renderExam();
  return `
<section class="task-heading"><div class="container task-heading-inner"><div><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Latihan</div><h1>Latihan SKD singkat</h1><p>Coba 9 soal TWK, TIU, dan TKP. Baca pembahasan setelah mengumpulkan.</p></div><div class="task-heading-meta">Mode latihan mandiri · bukan CAT resmi BKN</div></div></section>
<section class="task-body"><div class="container"><div class="exam-start"><div class="start-panel"><div class="exam-facts"><div class="exam-fact"><strong>9 soal</strong><span>3 TWK · 3 TIU · 3 TKP</span></div><div class="exam-fact"><strong>12 menit</strong><span>Timer hitung mundur</span></div><div class="exam-fact"><strong>1 poin</strong><span>Setiap jawaban sesuai kunci contoh</span></div></div><p>Kamu boleh mengganti jawaban, menandai nomor, dan berpindah soal sebelum mengumpulkan. Saat waktu habis, latihan dikumpulkan otomatis.</p><button class="button button-primary" type="button" data-start-exam>Mulai latihan ${ic("arrow-right")}</button><p class="small muted start-note">Hasil ${storageUsable() ? "disimpan pada browser ini, tanpa sinkronisasi antarperangkat." : "tidak dapat disimpan pada browser ini; hasil hanya tampil selama sesi."} Skor bukan prediksi nilai atau ambang kelulusan BKN.</p></div><aside class="start-aside"><h2>Apa yang akan terjadi?</h2><ol><li>Pilih satu jawaban untuk setiap soal.</li><li>Tinjau soal yang ditandai sebelum mengumpulkan.</li><li>Lihat total benar, rincian materi, dan pembahasan.</li></ol></aside></div></div></section>`;
}
function beginExam() {
  exam = {
    active: true,
    submitted: false,
    index: 0,
    answers: {},
    flags: {},
    remaining: 12 * 60,
    started: Date.now(),
  };
  clearInterval(clock);
  renderView(false);
  clock = setInterval(() => {
    if (!exam || !exam.active || exam.submitted) {
      clearInterval(clock);
      return;
    }
    exam.remaining = Math.max(
      0,
      12 * 60 - Math.floor((Date.now() - exam.started) / 1000),
    );
    const el = document.querySelector("[data-timer]");
    if (el) {
      el.textContent = timeLabel(exam.remaining);
      el.classList.toggle("warning", exam.remaining <= 60);
    }
    if (exam.remaining === 0) submitExam(true);
  }, 1000);
}
function timeLabel(n) {
  return `${String(Math.floor(n / 60)).padStart(2, "0")}:${String(n % 60).padStart(2, "0")}`;
}
function renderExam() {
  const q = examQuestions[exam.index];
  const answered = Object.keys(exam.answers).length;
  return `
<section class="exam-heading"><div class="container exam-heading-inner"><div><a class="task-back" href="#/beranda">${ic("chevron-left")} Beranda</a><h1>Latihan SKD singkat</h1><p>Soal ${exam.index + 1} dari ${examQuestions.length} · ${answered} dijawab</p></div><div class="timer ${exam.remaining <= 60 ? "warning" : ""}">${ic("clock")} <span data-timer>${timeLabel(exam.remaining)}</span></div></div></section>
<section class="task-body"><div class="container"><div class="exam-shell"><div class="exam-pane"><div class="exam-toolbar"><span>${safe(q.cat)} · Soal ${exam.index + 1} dari ${examQuestions.length}</span><span>${answered} / ${examQuestions.length} terjawab</span></div><div class="exam-question-area"><h2 id="question-title" tabindex="-1">${safe(q.q)}</h2><div class="options" role="radiogroup" aria-labelledby="question-title">${q.options.map((o, i) => `<label class="option ${exam.answers[q.id] === i ? "selected" : ""}"><input class="option-input" type="radio" name="answer" data-answer="${i}" value="${i}" ${exam.answers[q.id] === i ? "checked" : ""}><span class="option-letter">${alphabet[i]}</span><span>${safe(o)}</span></label>`).join("")}</div></div><div class="exam-actions"><div class="action-group"><button class="button button-outline" type="button" data-prev ${exam.index === 0 ? "disabled" : ""}>${ic("chevron-left")} Sebelumnya</button><button class="button button-primary" type="button" data-next ${exam.index === examQuestions.length - 1 ? "disabled" : ""}>Berikutnya ${ic("chevron-right")}</button></div><button class="mark-button ${exam.flags[q.id] ? "marked" : ""}" type="button" data-flag aria-pressed="${exam.flags[q.id] ? "true" : "false"}">${ic("flag")} ${exam.flags[q.id] ? "Hapus tanda tinjau" : "Tandai untuk ditinjau"}</button></div></div><aside class="exam-sidebar"><div class="sidebar-top"><h2>Nomor soal</h2><p>${answered} dari 9 dijawab</p></div><div class="number-grid" aria-label="Navigasi nomor soal">${examQuestions.map((x, i) => `<button type="button" data-goto="${i}" ${exam.index === i ? 'aria-current="step"' : ""} aria-label="Soal ${i + 1}${exam.answers[x.id] !== undefined ? ", sudah dijawab" : ", belum dijawab"}${exam.flags[x.id] ? ", ditandai untuk ditinjau" : ""}" class="${exam.index === i ? "current" : ""} ${exam.answers[x.id] !== undefined ? "answered" : ""} ${exam.flags[x.id] ? "flagged" : ""}">${i + 1}${exam.flags[x.id] ? "*" : ""}</button>`).join("")}</div><p class="nav-tip">Angka berwarna navy sudah dijawab. Tanda * berarti ditandai untuk ditinjau.</p><button class="button button-primary sidebar-submit" type="button" data-submit-exam>Kumpulkan jawaban ${ic("arrow-right")}</button></aside></div><p class="exam-disclaimer">Penilaian demonstrasi, bukan simulasi resmi BKN. Nilai dihitung 1 poin per jawaban sesuai kunci.</p></div></section>`;
}
function submitExam(auto = false) {
  if (!exam || !exam.active || exam.submitted) return;
  let answered = Object.keys(exam.answers).length;
  if (!auto && answered < examQuestions.length) {
    if (
      !confirm(
        `Kamu baru menjawab ${answered} dari 9 soal. Yakin ingin mengumpulkan sekarang?`,
      )
    )
      return;
  }
  if (!auto && answered === examQuestions.length) {
    if (!confirm("Kumpulkan jawaban dan lihat pembahasannya?")) return;
  }
  exam.active = false;
  exam.submitted = true;
  clearInterval(clock);
  let correct = examQuestions.filter((q) => exam.answers[q.id] === q.a).length;
  const record = {
    date: new Date().toISOString(),
    correct,
    total: examQuestions.length,
    by: countByTopic(),
    answers: { ...exam.answers },
    timedOut: auto,
  };
  const stored = storageWrite([record, ...storageRead()]);
  exam.stored = stored;
  exam.timedOut = auto;
  renderView(false);
  window.scrollTo({ top: 0, behavior: "smooth" });
}
function countByTopic() {
  return ["TWK", "TIU", "TKP"].map((cat) => ({
    cat,
    correct: examQuestions.filter(
      (q) => q.cat === cat && exam.answers[q.id] === q.a,
    ).length,
    total: examQuestions.filter((q) => q.cat === cat).length,
  }));
}
function renderResult() {
  const correct = examQuestions.filter(
    (q) => exam.answers[q.id] === q.a,
  ).length;
  const answered = Object.keys(exam.answers).length;
  return `<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Hasil latihan</div><h1>${exam.fromHistory ? "Riwayat hasil latihan" : "Hasil latihan SKD"}</h1><p>${exam.timedOut ? "Waktu latihan habis dan jawaban dikumpulkan otomatis. " : ""}Lihat kunci dan pembahasan untuk setiap soal.</p></div></section>
<section class="page-content"><div class="container">${notice(`Skor ini menghitung jumlah jawaban yang cocok dengan kunci latihan (0–9), bukan nilai CAT atau passing grade resmi. ${exam.stored ? "Riwayat tersimpan di browser ini (tidak tersinkronisasi)." : "Penyimpanan browser tidak tersedia; hasil ini hanya terlihat selama sesi berlangsung."}`)}<div class="result-summary"><div class="score-block"><span class="eyebrow">JAWABAN SESUAI</span><strong>${correct}<span style="font-size:25px;color:#a6b7cc"> / 9</span></strong><span>${answered} soal dijawab</span></div><div class="result-description"><span class="eyebrow">EVALUASI SINGKAT</span><h2>${correct === 9 ? "Semua jawaban sesuai." : correct >= 6 ? "Dasarnya sudah mulai terbentuk." : "Masih banyak ruang untuk berlatih."}</h2><p>Gunakan hasil per topik sebagai petunjuk belajar berikutnya. Di prototipe ini, TKP juga dinilai sebagai satu pilihan contoh yang disarankan, bukan bobot resmi.</p><a class="text-link" href="#/progres">Lihat riwayat latihan ${ic("arrow-right")}</a></div></div><div class="result-by-topic">${countByTopic()
    .map(
      (t) =>
        `<div class="topic-score"><b>${t.cat}</b><span>${t.correct} dari ${t.total} jawaban sesuai</span></div>`,
    )
    .join(
      "",
    )}</div><div class="workspace-head"><div><span class="eyebrow">ULASAN JAWABAN</span><h2>Pembahasan per soal</h2></div></div>${examQuestions
    .map((q, i) => {
      let answer = exam.answers[q.id];
      let good = answer === q.a;
      return `<article class="review-item"><div class="review-head"><strong>${String(i + 1).padStart(2, "0")} / ${q.cat}</strong><span class="${good ? "correct" : "incorrect"}">${answer === undefined ? "Tidak dijawab" : good ? "Jawaban sesuai" : "Perlu ditinjau"}</span></div><h3>${safe(q.q)}</h3><p>Jawaban kamu: ${answer === undefined ? "<em>Belum dipilih</em>" : safe(alphabet[answer] + ". " + q.options[answer])}</p><p class="review-answer">Kunci contoh: ${alphabet[q.a]}. ${safe(q.options[q.a])}</p><p>${safe(q.why)}</p></article>`;
    })
    .join(
      "",
    )}<div class="review-actions"><button class="button button-primary" type="button" data-start-exam>Latihan lagi ${ic("refresh")}</button><a class="button button-outline" href="#/bank-soal">Pelajari bank soal ${ic("arrow-right")}</a></div></div></section>`;
}

function renderBank() {
  return `<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Bank soal</div><h1>Bank soal & pembahasan</h1><p>Cari contoh soal berdasarkan materi. Buka kunci dan alasan jawabannya saat kamu siap.</p></div></section>
<section class="task-body"><div class="container"><div class="bank-controls"><div class="filter-group" role="group" aria-label="Filter kategori soal">${["Semua", "TWK", "TIU", "TKP", "SKB"].map((c) => `<button class="filter-button" type="button" aria-pressed="${bankFilter === c ? "true" : "false"}" data-filter="${c}">${c}</button>`).join("")}</div><label class="search-wrap"><span class="visually-hidden">Cari soal</span>${ic("search")}<input type="search" id="bank-search" placeholder="Cari materi atau kata kunci" autocomplete="off" value="${safe(bankSearch)}" aria-label="Cari soal"></label></div><div id="bank-results" aria-live="polite" aria-atomic="false">${renderBankResults()}</div><p class="small muted bank-footnote">${Q.length} contoh soal awal · Kunci dan pembahasan masih perlu peninjauan substansi oleh tutor. Contoh SKB belum mencakup semua formasi.</p></div></section>`;
}
function renderBankResults() {
  const q = bankSearch.trim().toLowerCase();
  const rows = Q.filter(
    (item) =>
      (bankFilter === "Semua" || item.cat === bankFilter) &&
      (!q ||
        [item.q, ...item.options, item.why]
          .join(" ")
          .toLowerCase()
          .includes(q)),
  );
  const count = `Menampilkan ${rows.length} dari ${Q.length} contoh soal${bankFilter !== "Semua" ? " · " + bankFilter : ""}${q ? " · pencarian: " + safe(bankSearch.trim()) : ""}`;
  if (!rows.length)
    return `<p class="bank-count" role="status">${count}</p><div class="empty-search">${ic("search")}<h2>Tidak ada soal yang cocok</h2><p>Hapus kata kunci atau pilih kategori lain.</p><button class="button button-outline" type="button" data-clear-search>Hapus pencarian dan filter</button></div>`;
  return `<p class="bank-count" role="status">${count}</p>${rows.map((item) => `<article class="bank-item"><div class="bank-meta"><span class="category-label">${item.cat}</span><span>${item.id.toUpperCase()}</span></div><h2>${safe(item.q)}</h2><div class="bank-options">${item.options.map((op, k) => `<div><b>${alphabet[k]}.</b><span>${safe(op)}</span></div>`).join("")}</div><details><summary>Lihat kunci dan pembahasan</summary><p class="bank-answer">Kunci contoh: ${alphabet[item.a]}. ${safe(item.options[item.a])}</p><p>${safe(item.why)}</p></details></article>`).join("")}`;
}
function renderClasses() {
  return `<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Kelas</div><h1>Rencana kelas SKD & SKB</h1><p>Program kelas masih dalam perencanaan. Belum ada jadwal, pengajar, atau pendaftaran yang dibuka.</p></div></section><section class="task-body"><div class="container"><div class="class-rows compact"><article class="class-row"><div class="course-code">SKD</div><div><h2>Fondasi TWK, TIU, TKP</h2><p>Untuk calon peserta yang ingin menguasai materi dasar dan berlatih mengelola waktu pengerjaan.</p></div><div><h3>Rencana silabus</h3><ul><li>Pengetahuan kebangsaan dan latihan kontekstual</li><li>Penalaran verbal dan numerik</li><li>Studi kasus pelayanan publik</li><li>Evaluasi latihan dan tanya jawab</li></ul></div></article><article class="class-row"><div class="course-code">SKB</div><div><h2>Pendalaman sesuai formasi</h2><p>Jalur SKB memerlukan penyusunan materi menurut jabatan yang akan dilamar, bukan satu paket untuk seluruh peserta.</p></div><div><h3>Contoh arah materi</h3><ul><li>Pemetaan kompetensi jabatan</li><li>Konsep teknis sesuai bidang</li><li>Kasus praktik dan pembahasan</li><li>Evaluasi bersama pendamping</li></ul></div></article></div><div class="class-bottom"><div><h2>Status program</h2><p>Silabus di atas masih draf. Ketersediaan kelas, format pembelajaran, pengajar, dan jadwal akan diumumkan setelah mendapat konfirmasi tim. Untuk sekarang, latihan gratis dan bank soal contoh sudah bisa digunakan.</p><a class="text-link" href="#/latihan">Coba latihan yang tersedia ${ic("arrow-right")}</a></div><img src="assets/group-white.webp" width="1160" height="689" loading="lazy" alt="Nara, Bima, dan Laras mengenakan seragam putih ASN"></div></div></section>`;
}
function renderPackages() {
  return `<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Paket</div><h1>Mulai gratis, tanpa harus memilih paket</h1><p>Untuk saat ini hanya latihan contoh yang tersedia. Paket lanjutan masih direncanakan; belum ada harga atau pembelian.</p></div></section><section class="task-body"><div class="container"><div class="available-package"><div><span class="eyebrow">TERSEDIA SEKARANG</span><h2>Pejuang · gratis</h2><p>Mulai dari 9 soal latihan SKD dengan timer, pembahasan, dan riwayat lokal di browser yang mendukung penyimpanan.</p></div><a class="button button-primary" href="#/latihan">Coba latihan SKD ${ic("arrow-right")}</a></div><h2 class="planned-title">Dalam perencanaan</h2><p class="small muted planned-desc">Manfaat berikut masih berupa usulan fitur. Harga, tanggal peluncuran, dan metode pembayaran belum ditentukan.</p><div class="planned-grid"><article class="planned-package"><span class="eyebrow">KONSEP PAKET</span><h3>Abdi Negara</h3><p>Rencana akses latihan lebih luas, analisis materi, dan pembahasan terjadwal.</p><span class="not-for-sale">Belum tersedia · tanpa pembelian</span></article><article class="planned-package"><span class="eyebrow">KONSEP PAKET</span><h3>VIP Mentoring</h3><p>Rencana pendampingan tutor, sesi kelompok, dan peninjauan progres berkala.</p><span class="not-for-sale">Belum tersedia · tanpa pembelian</span></article></div></div></section>`;
}
function renderProgress() {
  const rows = storageRead();
  const total = rows.length;
  const max = rows.reduce((m, x) => Math.max(m, x.correct), 0);
  const last = rows[0];
  const canSave = storageUsable();
  return `<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="#/beranda">Beranda</a> ${ic("chevron-right", "icon-small")} Progres saya</div><h1>Progres saya</h1><p>Riwayat hasil dari latihan di browser perangkat ini. Data tidak tersinkronisasi dan dapat hilang jika data browser dihapus.</p></div></section><section class="task-body"><div class="container">${!canSave ? notice("Penyimpanan lokal tidak tersedia di browser ini. Riwayat baru tidak akan tersimpan setelah sesi berakhir.") : ""}${!total ? `<div class="empty-state">${ic("chart-bar")}<h2>Belum ada riwayat latihan</h2><p>Kerjakan 9 soal SKD pertama. Jika browser mendukung penyimpanan, hasilnya bisa kamu lihat kembali di sini.</p><a class="button button-primary" href="#/latihan">Mulai latihan ${ic("arrow-right")}</a></div>` : `<div class="progress-meta"><div class="progress-metric"><strong>${total}</strong><span>Sesi tersimpan</span></div><div class="progress-metric"><strong>${max} / 9</strong><span>Jawaban sesuai terbaik</span></div><div class="progress-metric"><strong>${last.correct} / 9</strong><span>Hasil terakhir</span></div></div><div class="workspace-head"><h2>Riwayat latihan</h2><span class="small muted">Terbaru di atas</span></div><div class="history-list">${rows.map((x, i) => `<div class="history-row"><strong>Latihan SKD · 9 soal</strong><span class="small">${safe(formatDate(x.date))}</span><span class="history-score">${x.correct} / ${x.total}</span>${x.answers ? `<button class="history-review" data-history-index="${i}" type="button">Lihat pembahasan ${ic("arrow-right")}</button>` : '<span class="small muted">Riwayat lama: rincian belum tersedia</span>'}</div>`).join("")}</div><div class="actions-row"><a href="#/latihan" class="button button-primary">Latihan lagi ${ic("arrow-right")}</a><button class="button button-outline" type="button" data-clear-history>${ic("trash")} Hapus riwayat</button></div>`}</div></section>`;
}
function formatDate(s) {
  const d = new Date(s);
  if (Number.isNaN(d.valueOf())) return "Tanggal tidak tersedia";
  return d.toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });
}
const pages = {
  beranda: renderHome,
  latihan: renderTraining,
  "bank-soal": renderBank,
  kelas: renderClasses,
  paket: renderPackages,
  progres: renderProgress,
};
function getRoute() {
  const path = location.hash.replace(/^#\/?/, "").split("/")[0];
  return pages[path] ? path : "beranda";
}
function renderView(scroll = true) {
  const route = getRoute();
  currentRoute = route;
  document.title =
    route === "beranda"
      ? "Siapegawai — Belajar CPNS lebih terarah"
      : `${{ latihan: "Latihan SKD", "bank-soal": "Bank Soal", kelas: "Kelas", paket: "Paket", progres: "Progres Saya" }[route]} | Siapegawai`;
  app.innerHTML = pages[route]();
  document
    .querySelector(".progress-link")
    ?.classList.toggle("active", route === "progres");
  document.querySelectorAll("[data-nav]").forEach((el) => {
    const active = el.dataset.nav === route;
    el.classList.toggle("active", active);
    if (active) el.setAttribute("aria-current", "page");
    else el.removeAttribute("aria-current");
  });
  nav.classList.remove("is-open");
  document.getElementById("menu-toggle").setAttribute("aria-expanded", "false");
  document
    .getElementById("menu-toggle")
    .setAttribute("aria-label", "Buka menu navigasi");
  hydrateIcons(app);
  if (scroll) {
    window.scrollTo({ top: 0, behavior: "instant" });
    document.getElementById("main").focus({ preventScroll: true });
  }
}
function navigate() {
  const target = getRoute();
  if (
    currentRoute === "latihan" &&
    target !== "latihan" &&
    exam?.active &&
    !exam.submitted
  ) {
    if (
      !confirm(
        "Latihan masih berlangsung. Keluar akan membatalkan sesi yang belum dikumpulkan. Lanjut keluar?",
      )
    ) {
      location.hash = "#/latihan";
      return;
    }
    clearInterval(clock);
    exam = null;
  }
  renderView();
}
window.addEventListener("hashchange", navigate);

app.addEventListener("click", (e) => {
  const el = e.target.closest("button,[data-scroll]");
  if (!el) return;
  if (el.hasAttribute("data-start-exam")) {
    beginExam();
    window.scrollTo({ top: 0, behavior: "smooth" });
    return;
  }
  if (!exam?.submitted && exam?.active && currentRoute === "latihan") {
    if (el.hasAttribute("data-flag")) {
      const id = examQuestions[exam.index].id;
      exam.flags[id] = !exam.flags[id];
      renderView(false);
      app.querySelector("[data-flag]")?.focus();
      return;
    }
    if (el.hasAttribute("data-goto")) {
      exam.index = Number(el.dataset.goto);
      renderView(false);
      app.querySelector("#question-title")?.focus();
      return;
    }
    if (el.hasAttribute("data-next")) {
      exam.index = Math.min(8, exam.index + 1);
      renderView(false);
      app.querySelector("#question-title")?.focus();
      return;
    }
    if (el.hasAttribute("data-prev")) {
      exam.index = Math.max(0, exam.index - 1);
      renderView(false);
      app.querySelector("#question-title")?.focus();
      return;
    }
    if (el.hasAttribute("data-submit-exam")) {
      submitExam();
      return;
    }
  }
  if (el.hasAttribute("data-filter")) {
    bankFilter = el.dataset.filter;
    renderView(false);
    return;
  }
  if (el.hasAttribute("data-clear-search")) {
    bankFilter = "Semua";
    bankSearch = "";
    renderView(false);
    document.getElementById("bank-search")?.focus();
    return;
  }
  if (el.hasAttribute("data-history-index")) {
    const record = storageRead()[Number(el.dataset.historyIndex)];
    if (record?.answers) {
      exam = {
        active: false,
        submitted: true,
        answers: record.answers,
        flags: {},
        index: 0,
        remaining: 0,
        stored: true,
        fromHistory: true,
        date: record.date,
        timedOut: record.timedOut,
      };
      location.hash = "#/latihan";
      if (currentRoute === "latihan") renderView(false);
    }
    return;
  }
  if (el.hasAttribute("data-clear-history")) {
    if (confirm("Hapus semua riwayat latihan yang tersimpan di browser ini?")) {
      storageWrite([]);
      renderView(false);
    }
    return;
  }
  if (el.hasAttribute("data-scroll")) {
    document
      .getElementById(el.dataset.scroll)
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
app.addEventListener("change", (e) => {
  if (e.target.matches(".option-input") && exam?.active) {
    const i = Number(e.target.dataset.answer);
    exam.answers[examQuestions[exam.index].id] = i;
    renderView(false);
    app.querySelector(`.option-input[data-answer="${i}"]`)?.focus();
  }
});
app.addEventListener("input", (e) => {
  if (e.target.id === "bank-search") {
    bankSearch = e.target.value;
    document.getElementById("bank-results").innerHTML = renderBankResults();
    hydrateIcons(document.getElementById("bank-results"));
  }
});
const menuToggle = document.getElementById("menu-toggle");
menuToggle.addEventListener("click", () => {
  const opened = nav.classList.toggle("is-open");
  menuToggle.setAttribute("aria-expanded", String(opened));
  menuToggle.setAttribute(
    "aria-label",
    opened ? "Tutup menu navigasi" : "Buka menu navigasi",
  );
});
document.addEventListener("click", (e) => {
  const sameLink = e.target.closest('a[href^="#/"]');
  if (sameLink && sameLink.getAttribute("href") === location.hash) {
    window.scrollTo({ top: 0, behavior: "smooth" });
    nav.classList.remove("is-open");
    menuToggle.setAttribute("aria-expanded", "false");
  }
  const info = e.target.closest("[data-info]");
  if (info) {
    document.getElementById("info-dialog").showModal();
    return;
  }
  const close = e.target.closest("[data-close-dialog]");
  if (close) close.closest("dialog")?.close();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && nav.classList.contains("is-open")) {
    nav.classList.remove("is-open");
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Buka menu navigasi");
    menuToggle.focus();
  }
});
hydrateIcons(document);
if (!location.hash || location.hash === "#") location.hash = "#/beranda";
else renderView(false);
