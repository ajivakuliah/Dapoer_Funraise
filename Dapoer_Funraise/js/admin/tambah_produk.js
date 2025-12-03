function formatRupiah(num) {
    const n = parseFloat(num) || 0;
    return 'Rp ' + n.toLocaleString('id-ID');
}

const createPreviewVariantTags = (variants) => {
    if (!variants?.length) return '';
    return variants.map(v => 
        `<span>${v.trim()}</span>`
    ).join('');
};

const namaInput = document.getElementById('nama');
const hargaInput = document.getElementById('harga');
const varianInput = document.getElementById('varian');
const kategoriSelect = document.getElementById('kategori');
const deskripsiInput = document.getElementById('deskripsi');
const fotoInput = document.getElementById('foto');

const liveNama = document.getElementById('liveNama');
const liveHarga = document.getElementById('liveHarga');
const liveVarianDisplay = document.getElementById('liveVarianDisplay');
const liveKategoriDisplay = document.getElementById('liveKategoriDisplay');
const liveDeskripsi = document.getElementById('liveDeskripsi');
const livePreviewImg = document.getElementById('livePreviewImg');
const previewPlaceholder = document.getElementById('previewPlaceholder');
const uploadFileName = document.getElementById('uploadFileName');
const uploadHint = document.getElementById('uploadHint');

function initializePreview() {
    liveNama.textContent = namaInput.value || 'Nama Produk';
    liveHarga.textContent = formatRupiah(hargaInput.value);
    liveDeskripsi.textContent = deskripsiInput.value || 'Deskripsi produk akan muncul...';
    
    const initVars = (varianInput.value || '').split(',').map(v => v.trim()).filter(v => v);
    liveVarianDisplay.innerHTML = createPreviewVariantTags(initVars);
    
    if (kategoriSelect.value) {
        liveKategoriDisplay.textContent = kategoriSelect.value;
        liveKategoriDisplay.style.display = 'inline-block';
    } else {
        liveKategoriDisplay.style.display = 'none';
    }
}

namaInput.addEventListener('input', e => {
    const value = e.target.value.trim() || 'Nama Produk';
    liveNama.textContent = value;
});

hargaInput.addEventListener('input', e => {
    liveHarga.textContent = formatRupiah(e.target.value);
});

deskripsiInput.addEventListener('input', e => {
    const value = e.target.value.trim();
    liveDeskripsi.textContent = value || 'Deskripsi produk akan muncul...';
});

varianInput.addEventListener('input', e => {
    const vars = e.target.value.split(',').map(v => v.trim()).filter(v => v);
    if (vars.length > 0) {
        liveVarianDisplay.innerHTML = createPreviewVariantTags(vars);
    } else {
        liveVarianDisplay.innerHTML = '';
    }
});

kategoriSelect.addEventListener('change', function(e) {
    if (e.target.value) {
        liveKategoriDisplay.textContent = e.target.value;
        liveKategoriDisplay.style.display = 'inline-block';
    } else {
        liveKategoriDisplay.style.display = 'none';
    }
});

fotoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const name = file.name.length > 22 ? file.name.substring(0, 19) + '...' : file.name;
        uploadFileName.textContent = name;
        uploadFileName.style.color = 'var(--secondary)';
        
        uploadHint.innerHTML = `File: <span class="current-foto">${name}</span>`;
        
        const reader = new FileReader();
        reader.onload = ev => {
            livePreviewImg.src = ev.target.result;
            livePreviewImg.classList.add('loaded');
            livePreviewImg.style.display = 'block';
            previewPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        resetPhotoDisplay();
    }
});

function resetPhotoDisplay() {
    uploadFileName.textContent = 'Upload foto produk';
    uploadFileName.style.color = 'var(--primary)';
    
    uploadHint.innerHTML = 'JPG, PNG, WebP • ≤3MB';
    
    livePreviewImg.style.display = 'none';
    livePreviewImg.classList.remove('loaded');
    previewPlaceholder.style.display = 'flex';
    previewPlaceholder.innerHTML = `
        <i class="fas fa-image"></i>
        <div>Belum ada foto</div>
    `;
}

document.getElementById('addForm').addEventListener('submit', function(e) {
    if (!namaInput.value.trim()) {
        alert('Nama produk wajib diisi!');
        e.preventDefault();
        namaInput.focus();
        return;
    }
    
    if (!hargaInput.value || parseFloat(hargaInput.value) <= 0) {
        alert('Harga wajib diisi dengan angka positif!');
        e.preventDefault();
        hargaInput.focus();
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        submitBtn.disabled = true;
        
        setTimeout(() => {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }, 5000);
    }
});

document.addEventListener('DOMContentLoaded', initializePreview);

const uploadArea = document.getElementById('uploadArea');
if (uploadArea) {
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary)';
        uploadArea.style.background = '#f5f3ff';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--soft)';
        uploadArea.style.background = 'var(--bg-light)';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--soft)';
        uploadArea.style.background = 'var(--bg-light)';
        
        if (e.dataTransfer.files.length) {
            fotoInput.files = e.dataTransfer.files;
            const event = new Event('change', { bubbles: true });
            fotoInput.dispatchEvent(event);
        }
    });
}