<?php
$projectsDir = __DIR__ . "/projects";
$projects = is_dir($projectsDir) ? array_diff(scandir($projectsDir), ['.', '..']) : [];
?>

<!DOCTYPE html>
<html>
<head>
    <title>ClipperIN</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg" href="assets/scissors.svg">
    <style>
        .search-nav-btn { padding: 8px 12px; margin: 0; min-width: unset; font-size: 14px; display: flex; align-items: center; justify-content: center; }
        
        /* AI Tabs Styles */
        .ai-tabs-header { display: flex; gap: 8px; border-bottom: 1px solid #333; padding-bottom: 8px; margin-bottom: 12px; overflow-x: auto; }
        .ai-tab-btn { background: #1a1a1a; color: #888; border: 1px solid #333; padding: 6px 12px; cursor: pointer; border-radius: 4px; font-size: 13px; white-space: nowrap; }
        .ai-tab-btn.active { background: #333; color: #fff; border-color: #555; font-weight: bold; }
        .ai-tab-pane { display: none; font-size: 14px; line-height: 1.5; }
        .ai-tab-pane.active { display: block; }
        .ai-clip-info { background: #111; padding: 10px; border-radius: 4px; border: 1px solid #222; margin-bottom: 8px; }
        .ai-clip-title { font-weight: bold; color: #ff9800; margin-bottom: 6px; }
        .ai-clip-time { color: #00d2ff; font-family: monospace; font-size: 13px; margin-bottom: 8px; }
        .ai-clip-time span { cursor: pointer; text-decoration: underline; }
    </style>
</head>
<body>

<div class="dashboard">

    <div id="loadingModal" class="loading-modal hidden">
        <div class="loading-box">
            <div class="spinner"></div>
            <p id="loadingText">Processing...</p>
        </div>
    </div>

    <div class="header">
        <h1>ClipperIN Youtube</h1>
    </div>

    <div class="content">

        <!-- LEFT PANEL -->
        <div class="panel main-panel">

            <form id="clipForm" action="process.php" method="POST" autocomplete="off">

                <div class="panel-box glow">
                    <h3>Project Configuration</h3>

                    <label>YouTube URL</label>
                    <div class="url-info" autocomplete="off">
                        <input type="text" name="url" id="url" required>
                        <div style="display: flex; flex-direction: column; justify-content: center; gap: 6px; color: #ccc; flex-shrink: 0; width: max-content;">
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; margin: 0; line-height: 1; font-size: 14px;">
                                <input type="radio" name="sub_lang" value="id" checked style="margin: 0;"> ID
                            </label>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; margin: 0; line-height: 1; font-size: 14px;">
                                <input type="radio" name="sub_lang" value="en" style="margin: 0;"> EN
                            </label>
                        </div>
                        <button type="button" class="btn-secondary" onclick="downloadTranscribe()">Download Transcribe</button>
                    </div>

                    <label>Project Name</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="project_name" id="projectName" list="projectList" required autocomplete="off" style="flex: 1; margin: 0;">
                        <button type="button" class="btn-secondary" onclick="loadExistingProject()" style="margin: 0; white-space: nowrap;">Load</button>
                    </div>

                    <datalist id="projectList">
                        <?php foreach ($projects as$p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- AI SMART ANALYZER AREA -->
                <div class="panel-box glow ai-box">
                    <h3>AI Smart Analyzer</h3>
                    
                    <div id="btnAnalyzeContent">
                        <p class="muted" style="margin-bottom: 12px;">Biarkan AI mengkurasi dan mencari segmen paling viral dari transkrip video ini.</p>
                        <button type="button" id="btnAnalyze" class="btn-secondary" onclick="analyzeTranscript()">
                            Help Analyze
                        </button>
                    </div>

                    <!-- Loading Indicator for AI -->
                    <div id="aiLoading" class="hidden" style="text-align: center; padding: 20px;">
                        <div class="spinner" style="display:inline-block; margin-bottom: 10px;"></div>
                        <p class="muted">AI sedang menganalisa potensi viral (ini memakan waktu beberapa detik)...</p>
                    </div>

                    <!-- AI Result Tabs -->
                    <div id="aiResultArea" class="hidden">
                        <div class="ai-tabs-header" id="aiTabHeaders"></div>
                        <div class="ai-tab-contents" id="aiTabContents"></div>
                    </div>
                </div>

                <div class="panel-box glow">
                    <h3>Clip Segments (maximum 2 minutes)</h3>

                    <div id="clips">
                        <div class="clip active" onclick="setActiveClip(this)">
                            <input type="text" name="start[]" placeholder="Start (HH:MM:SS)" inputmode="numeric" oninput="formatTimeInput(this)" autocomplete="off">
                            <input type="text" name="end[]" placeholder="End (HH:MM:SS)" inputmode="numeric" oninput="formatTimeInput(this)" autocomplete="off">
                            <input type="text" name="caption[]" required placeholder="Maximum length 64 characters" maxlength="64" autocomplete="off">
                            <button type="button" class="btn-secondary" onclick="removeClip(this)">Delete</button>
                        </div>
                    </div>

                    <button type="button" class="btn-secondary" onclick="addClip()">+ Add Clip</button>
                </div>

                <button type="submit" class="btn-primary glow-strong">
                    Process Clips
                </button>

            </form>
        </div>

        <!-- RIGHT PANEL -->
        <div class="panel sidebar-panel" style="display: flex; flex-direction: column; height: 80vh;">
            <h3 style="flex-shrink: 0;">Transcript Search</h3>
            
            <div id="transcribeBox" style="flex: 1; overflow-y: auto; padding-right: 8px; margin-bottom: 12px; font-family: monospace;">
                <p class="muted">Subtitle teks akan muncul di sini setelah didownload.</p>
            </div>
            
            <div style="flex-shrink: 0; position: relative; display: flex; align-items: center; gap: 8px; padding-top: 8px; border-top: 1px solid #333;">
                <input type="text" id="searchInput" placeholder="Cari kata/kalimat..." style="flex: 1; padding: 10px; border-radius: 4px; background: #1a1a1a; color: #fff; border: 1px solid #333;" autocomplete="off">
                <span id="matchCount" style="color: #888; font-size: 12px; white-space: nowrap; font-family: monospace;">0/0</span>
                <button type="button" class="btn-secondary search-nav-btn" id="btnPrev" title="Previous (Shift+Enter)">&#8593;</button>
                <button type="button" class="btn-secondary search-nav-btn" id="btnNext" title="Next (Enter)">&#8595;</button>
            </div>
        </div>

    </div>
</div>

<script>
let currentTranscriptData = [];
let searchMatches = [];
let currentMatchIndex = -1;

function formatTimeInput(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 6) val = val.substring(0, 6);
    let formatted = '';
    if (val.length > 0) formatted = val.substring(0, 2);
    if (val.length > 2) formatted += ':' + val.substring(2, 4);
    if (val.length > 4) formatted += ':' + val.substring(4, 6);
    input.value = formatted;
}

function showLoading(text = "Processing...") {
    document.getElementById("loadingText").innerText = text;
    document.getElementById("loadingModal").classList.remove("hidden");
}

function hideLoading() {
    document.getElementById("loadingModal").classList.add("hidden");
}

function setActiveClip(element) {
    document.querySelectorAll('.clip').forEach(c => c.classList.remove('active'));
    element.classList.add('active');
}

function addClip() {
    const div = document.createElement('div');
    div.className = 'clip';
    div.setAttribute('onclick', 'setActiveClip(this)');
    div.innerHTML = `
        <input type="text" name="start[]" placeholder="Start (HH:MM:SS)" inputmode="numeric" oninput="formatTimeInput(this)" autocomplete="off">
        <input type="text" name="end[]" placeholder="End (HH:MM:SS)" inputmode="numeric" oninput="formatTimeInput(this)" autocomplete="off">
        <input type="text" name="caption[]" required placeholder="Maximum length 64 characters" maxlength="64" autocomplete="off">
        <button type="button" class="btn-secondary" onclick="removeClip(this, event)">Delete</button>
    `;
    document.getElementById('clips').appendChild(div);
    // Otomatis jadikan baris baru sebagai active
    setActiveClip(div);
}

function removeClip(button, event) {
    if(event) event.stopPropagation(); // Cegah trigger onclick dari container .clip
    
    const clip = button.parentElement;
    const container = document.getElementById("clips");
    
    if (container.children.length <= 1) {
        alert("At least one clip is required.");
        return;
    }
    
    const wasActive = clip.classList.contains('active');
    clip.remove();
    
    // Jika baris yang dihapus sedang aktif, pindahkan status aktif ke baris paling bawah
    if (wasActive && container.children.length > 0) {
        setActiveClip(container.lastElementChild);
    }
}

function fillActiveTime(type, time) {
    const activeClip = document.querySelector('.clip.active');
    if (activeClip) {
        const input = activeClip.querySelector(`input[name="${type}[]"]`);
        if (input) {
            input.value = time;
            // Visual feedback kedip hijau
            input.style.backgroundColor = '#1e3a1f'; 
            setTimeout(() => input.style.backgroundColor = '', 250);
        }
    }
}

// Fungsi baru untuk tombol Load
function loadExistingProject() {
    const projectName = document.getElementById("projectName").value.trim();
    if (!projectName) {
        alert("Pilih atau ketik Project Name terlebih dahulu.");
        return;
    }
    loadTranscript(projectName);
}

// VALIDATION + CONFIRM
document.getElementById("clipForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const projectName = document.getElementById("projectName").value.trim();
    const url = document.getElementById("url").value.trim();

    if (projectName.length === 0 || projectName.length > 64) { alert("Project name must be between 1 and 64 characters."); return; }
    const ytRegex = /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//;
    if (!ytRegex.test(url)) { alert("Invalid YouTube URL."); return; }

    const starts = document.getElementsByName("start[]");
    const ends = document.getElementsByName("end[]");
    const captions = document.getElementsByName("caption[]");

    function toSeconds(time) {
        const p = time.split(":").map(Number);
        return p[0]*3600 + p[1]*60 + p[2];
    }

    for (let i = 0; i < starts.length; i++) {
        if (!starts[i].value || !ends[i].value) continue;
        const s = toSeconds(starts[i].value);
        const eTime = toSeconds(ends[i].value);
        if (eTime <= s) { alert(`Clip ${i+1}: End must be greater than Start`); return; }
        if ((eTime - s) > 120) { alert(`Clip ${i+1}: Duration must not exceed 2 minute`); return; }
        if (captions[i].length > 64) { alert("Captions must not exceed 64 characters."); return; }
    }

    if (confirm("Are you sure you want to process this video and generate clips?")) { e.target.submit(); }
});

/* PENGAMBILAN & RENDER TRANSCRIPT */
function downloadTranscribe() {
    const url = document.getElementById("url").value;
    const project_name = document.getElementById("projectName").value;
    const lang = document.querySelector('input[name="sub_lang"]:checked').value;

    if (!url || !project_name) { alert("Please input YouTube URL and Project Name"); return; }
    showLoading("Downloading transcription...");

    fetch("get-transcribe.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "url=" + encodeURIComponent(url) + "&project_name=" + encodeURIComponent(project_name) + "&lang=" + encodeURIComponent(lang)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) { loadTranscript(data.project_name); } 
        else { alert("Error: " + data.message); hideLoading(); }
    })
    .catch(err => { console.error(err); alert("Failed to download transcribe"); hideLoading(); });
}

function loadTranscript(projectName) {
    showLoading("Loading transcript text...");
    fetch("load-subtitle.php?project=" + encodeURIComponent(projectName))
    .then(res => res.json())
    .then(res => {
        if(res.success) {
            currentTranscriptData = res.data;
            document.getElementById("searchInput").value = ""; 
            renderTranscript(currentTranscriptData, "");
            
            // Isi kembali kolom URL jika data URL tersedia
            if (res.url) {
                document.getElementById("url").value = res.url;
            }
        } else { 
            alert("Failed to load transcript: " + res.message); 
        }
    })
    .catch(err => console.error(err))
    .finally(() => { hideLoading(); });
}

function renderTranscript(data, keyword) {
    const container = document.getElementById("transcribeBox");
    container.innerHTML = "";
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="muted">No transcript available.</p>';
        updateMatchInfo(0, 0);
        return;
    }
    
    let html = '';
    const searchRegex = keyword ? new RegExp(`(${keyword.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')})`, 'gi') : null;
    
    data.forEach(item => {
        let textToDisplay = item.text;
        if (searchRegex) { textToDisplay = textToDisplay.replace(searchRegex, '<span class="search-match" style="background-color: yellow; color: #000; border-radius: 2px;">$1</span>'); }
        
        html += `
        <div style="margin-bottom: 12px; line-height: 1.5; font-size: 14px; border-bottom: 1px solid #333; padding-bottom: 8px;">
            <div style="color: #00d2ff; font-weight: bold; margin-bottom: 4px; font-size: 12px;">
                <span style="cursor: pointer;" onclick="fillActiveTime('start', '${item.start}')">[ ${item.start} ]</span> 
- 
<span style="cursor: pointer;" onclick="fillActiveTime('end', '${item.end}')">[ ${item.end} ]</span>
            </div>
            <div style="color: #ccc;">${textToDisplay}</div>
        </div>`;
    });
    
    container.innerHTML = html;
    if (keyword) {
        searchMatches = document.querySelectorAll('.search-match');
        if (searchMatches.length > 0) { currentMatchIndex = 0; highlightCurrentMatch(); } 
        else { currentMatchIndex = -1; updateMatchInfo(0, 0); }
    } else { searchMatches = []; currentMatchIndex = -1; updateMatchInfo(0, 0); }
}

function highlightCurrentMatch() {
    if (searchMatches.length === 0) return;
    searchMatches.forEach(el => { el.style.backgroundColor = 'yellow'; el.style.color = '#000'; });
    const currentEl = searchMatches[currentMatchIndex];
    currentEl.style.backgroundColor = '#ff9800'; 
    currentEl.style.color = '#fff';
    currentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    updateMatchInfo(currentMatchIndex + 1, searchMatches.length);
}

function nextSearchMatch() {
    if (searchMatches.length === 0) return;
    currentMatchIndex = (currentMatchIndex + 1) % searchMatches.length;
    highlightCurrentMatch();
}

function prevSearchMatch() {
    if (searchMatches.length === 0) return;
    currentMatchIndex = (currentMatchIndex - 1 + searchMatches.length) % searchMatches.length;
    highlightCurrentMatch();
}

function updateMatchInfo(current, total) {
    const countText = document.getElementById("matchCount");
    if (countText) { countText.innerText = total === 0 ? "0/0" : `${current}/${total}`; }
}

document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const btnNext = document.getElementById("btnNext");
    const btnPrev = document.getElementById("btnPrev");

    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const val = e.target.value.trim();
            renderTranscript(currentTranscriptData, val);
        });
        searchInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                if (e.shiftKey) { prevSearchMatch(); } else { nextSearchMatch(); }
            }
        });
    }
    if (btnNext) btnNext.addEventListener('click', nextSearchMatch);
    if (btnPrev) btnPrev.addEventListener('click', prevSearchMatch);
});

// === LOGIKA AI ANALYZER ===

function analyzeTranscript() {
    const projectName = document.getElementById("projectName").value.trim();
    if (!projectName) {
        alert("Harap masukkan Project Name dan jalankan Download Transcribe terlebih dahulu.");
        return;
    }

    document.getElementById("btnAnalyzeContent").classList.add("hidden");
    document.getElementById("aiLoading").classList.remove("hidden");

    fetch("ai-analyze.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ project: projectName })
    })
    .then(res => res.json())
    .then(res => {
        document.getElementById("aiLoading").classList.add("hidden");
        if (res.success && Array.isArray(res.data)) {
            renderAITabs(res.data);
        } else {
            alert("Analisa gagal: " + (res.message || "Pastikan transcript valid."));
            document.getElementById("btnAnalyzeContent").classList.remove("hidden");
        }
    })
    .catch(err => {
        console.error(err);
        alert("Terjadi kesalahan sistem saat menghubungi server AI.");
        document.getElementById("aiLoading").classList.add("hidden");
        document.getElementById("btnAnalyzeContent").classList.remove("hidden");
    });
}

function renderAITabs(clips) {
    const resultArea = document.getElementById("aiResultArea");
    const headers = document.getElementById("aiTabHeaders");
    const contents = document.getElementById("aiTabContents");

    headers.innerHTML = "";
    contents.innerHTML = "";
    resultArea.classList.remove("hidden");

    clips.forEach((clip, index) => {
        // Render Header Tab
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = `ai-tab-btn ${index === 0 ? "active" : ""}`;
        btn.innerText = `Klip ${index + 1}`;
        btn.onclick = () => switchAiTab(index);
        headers.appendChild(btn);

        // Escape kutip pada string caption agar tidak merusak inline onclick handler
        const safeCaption = clip.caption ? clip.caption.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';

        // Render Konten Tab
        const pane = document.createElement("div");
        pane.className = `ai-tab-pane ${index === 0 ? "active" : ""}`;
        pane.id = `aiTab-${index}`;
        
        pane.innerHTML = `
            <div class="ai-clip-info">
                <div class="ai-clip-title">${clip.title || "Viral Clip"}</div>
                <div class="ai-clip-time">
                    <span title="Klik untuk mengisi Start Time" onclick="fillActiveTime('start', '${clip.start}')">[ ${clip.start} ]</span> 
                    - 
                    <span title="Klik untuk mengisi End Time" onclick="fillActiveTime('end', '${clip.end}')">[ ${clip.end} ]</span>
                </div>
                <div style="margin-bottom: 6px;"><strong>Alasan:</strong> <span style="color: #ccc;">${clip.reason}</span></div>
                <div><strong>Caption:</strong> <span style="color: #ccc;">${clip.caption}</span></div>
                
                <button type="button" class="btn-secondary" style="margin-top: 10px; font-size: 12px; padding: 4px 10px;" onclick="applyAiClip('${clip.start}', '${clip.end}', '${safeCaption}')">
                    Gunakan Klip Ini
                </button>
            </div>
        `;
        contents.appendChild(pane);
    });
}

function switchAiTab(activeIndex) {
    document.querySelectorAll(".ai-tab-btn").forEach((btn, idx) => {
        btn.classList.toggle("active", idx === activeIndex);
    });
    document.querySelectorAll(".ai-tab-pane").forEach((pane, idx) => {
        pane.classList.toggle("active", idx === activeIndex);
    });
}

// Fungsi tambahan untuk menembakkan seluruh data sekaligus ke baris Clip pertama
function applyAiClip(start, end, caption) {
    const activeClip = document.querySelector('.clip.active');
    if (activeClip) {
        const startInput = activeClip.querySelector('input[name="start[]"]');
        const endInput = activeClip.querySelector('input[name="end[]"]');
        const captionInput = activeClip.querySelector('input[name="caption[]"]');
        
        if (startInput) startInput.value = start;
        if (endInput) endInput.value = end;
        if (captionInput) captionInput.value = caption.substring(0, 64);
        
        // Visual feedback kedip hijau di seluruh baris
        activeClip.style.backgroundColor = '#1e3a1f';
        setTimeout(() => activeClip.style.backgroundColor = '', 300);
    } else {
        alert("Terjadi kesalahan, tidak ada baris Clip yang aktif.");
    }
}
</script>

</body>
</html>