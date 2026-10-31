{{-- Kotak unggah foto / file dengan pratinjau — @2026-Alifiandra --}}
<label class="dropzone" data-dropzone for="{{ $id }}">
    <input type="file" id="{{ $id }}" name="{{ $name }}" class="dz-input"
        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp3,.ogg,.m4a,.aac,.amr,.opus,.mp4,.3gp">
    <span class="dz-empty">
        <span class="dz-ico">📎</span>
        <b>Klik untuk pilih foto / file</b>
        <span class="muted">atau seret & lepas ke sini · maks 16 MB</span>
    </span>
    <span class="dz-filled" hidden>
        <img class="dz-thumb" alt="" hidden>
        <span class="dz-doc" hidden>📄</span>
        <span class="dz-meta"><b class="dz-name"></b><span class="muted dz-size"></span></span>
        <button type="button" class="btn btn-sm dz-clear">Hapus</button>
    </span>
</label>
