function formatRupiah(num) {
    const n = parseFloat(num) || 0;
    return 'Rp ' + n.toLocaleString('id-ID');
}

function createPreviewVariantTags(variants) {
    if (!variants?.length) return '';
    return variants.map(v => 
        `<span>${v.trim()}</span>`
    ).join('');
}

function handleImageLoad(img) {
    img.classList.add('loaded');
    const placeholder = document.getElementById('previewPlaceholder');
    if (placeholder) {
        placeholder.style.display = 'none';
    }
}

function handleImageError(img) {
    img.style.display = 'none';
    const placeholder = document.getElementById('previewPlaceholder');
    if (placeholder) {
        placeholder.style.display = 'flex';
        placeholder.className = 'preview-img-placeholder error';
        const fotoCurrent = window.EDIT_PRODUK_DATA?.fotoCurrent || '';
        placeholder.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <div>Foto tidak dapat dimuat</div>
            ${fotoCurrent ? `<small style="font-size: 0.7rem; margin-top: 5px; color: #999;">${fotoCurrent}</small>` : ''}
        `;
    }
}

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
        if (!liveKategoriDisplay.classList.contains('preview-category-badge')) {
            liveKategoriDisplay.classList.add('preview-category-badge');
        }
        liveKategoriDisplay.textContent = kategoriSelect.value;
        liveKategoriDisplay.style.display = 'inline-block';
    } else {
        liveKategoriDisplay.style.display = 'none';
    }
}

function resetPhotoDisplay() {
    const fotoCurrent = window.EDIT_PRODUK_DATA?.fotoCurrent || '';
    const fotoCurrentUrl = window.EDIT_PRODUK_DATA?.fotoCurrentUrl || '';
    
    uploadFileName.textContent = fotoCurrent ? 'Ganti foto produk' : 'Upload foto produk';
    uploadFileName.style.color = 'var(--primary)';
    
    if (fotoCurrent) {
        uploadHint.innerHTML = `Foto saat ini: <span class="current-foto">${fotoCurrent}</span>`;
    } else {
        uploadHint.innerHTML = 'JPG, PNG, WebP • ≤3MB';
    }
    
    if (fotoCurrentUrl) {
        livePreviewImg.src = fotoCurrentUrl;
        livePreviewImg.classList.add('loaded');
        previewPlaceholder.style.display = 'none';
    } else {
        livePreviewImg.style.display = 'none';
        livePreviewImg.classList.remove('loaded');
        previewPlaceholder.style.display = 'flex';
        previewPlaceholder.className = 'preview-img-placeholder';
        previewPlaceholder.innerHTML = `
            <i class="fas fa-image"></i>
            <div>Belum ada foto</div>
        `;
    }
}

namaInput.addEventListener('input', e => {
    liveNama.textContent = e.target.value.trim() || 'Nama Produk';
});

hargaInput.addEventListener('input', e => {
    liveHarga.textContent = formatRupiah(e.target.value);
});

deskripsiInput.addEventListener('input', e => {
    liveDeskripsi.textContent = e.target.value.trim() || 'Deskripsi produk akan muncul...';
});

varianInput.addEventListener('input', e => {
    const vars = e.target.value.split(',').map(v => v.trim()).filter(v => v);
    liveVarianDisplay.innerHTML = createPreviewVariantTags(vars);
});

kategoriSelect.addEventListener('change', function(e) {
    if (e.target.value) {
        if (!liveKategoriDisplay.classList.contains('preview-category-badge')) {
            liveKategoriDisplay.classList.add('preview-category-badge');
        }
        liveKategoriDisplay.textContent = e.target.value;
        liveKategoriDisplay.style.display = 'inline-block';
    } else {
        liveKategoriDisplay.style.display = 'none';
    }
});

fotoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fotoCurrent = window.EDIT_PRODUK_DATA?.fotoCurrent || '';
    
    if (file) {
        const name = file.name.length > 22 ? file.name.substring(0, 19) + '...' : file.name;
        uploadFileName.textContent = 'File baru: ' + name;
        uploadFileName.style.color = 'var(--secondary)';
        
        // Update hint text
        if (fotoCurrent) {
            uploadHint.innerHTML = `
                <span class="warning">
                    <i class="fas fa-sync-alt"></i> Akan mengganti: 
                    <span style="text-decoration: line-through; color: #999;">${fotoCurrent}</span>
                </span>
            `;
        } else {
            uploadHint.innerHTML = `
                <span class="warning">
                    <i class="fas fa-sync-alt"></i> File baru akan diupload
                </span>
            `;
        }
        
        const reader = new FileReader();
        reader.onload = ev => {
            livePreviewImg.src = ev.target.result;
            livePreviewImg.classList.add('loaded');
            previewPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        resetPhotoDisplay();
    }
});

document.getElementById('editForm').addEventListener('submit', function(e) {
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
});

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

// Initialize on page load
document.addEventListener('DOMContentLoaded', initializePreview);