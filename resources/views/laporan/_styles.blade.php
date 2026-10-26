<style>
    /* ==========================================================
       Halaman Laporan — tampilan modern
       Warna per kategori lewat variabel --c1 / --c2 (gradien)
       ========================================================== */
    .rpt-page { --ink: #1f2937; --muted: #6b7280; --line: #eceff5; --bg-soft: #f6f7fb; }

    /* ---------- Hero ---------- */
    .rpt-hero {
        position: relative; overflow: hidden; border-radius: 18px; padding: 28px 30px; margin-bottom: 22px; color: #fff;
        background: linear-gradient(120deg, #4b46a1 0%, #605ca8 45%, #7d6bd6 100%);
        box-shadow: 0 10px 30px rgba(96, 92, 168, .28);
    }
    .rpt-hero:before, .rpt-hero:after { content: ""; position: absolute; border-radius: 50%; background: rgba(255,255,255,.08); pointer-events: none; }
    .rpt-hero:before { width: 280px; height: 280px; right: -70px; top: -120px; }
    .rpt-hero:after  { width: 180px; height: 180px; right: 160px; bottom: -110px; }
    .rpt-hero-inner { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; }
    .rpt-hero-text { flex: 1 1 340px; margin-right: 24px; }
    .rpt-hero-eyebrow { display: inline-block; font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; background: rgba(255,255,255,.16); padding: 3px 11px; border-radius: 20px; margin-bottom: 10px; }
    .rpt-hero h2 { margin: 0 0 6px; font-size: 26px; font-weight: 700; line-height: 1.25; color: #fff; }
    .rpt-hero p { margin: 0 0 16px; opacity: .88; font-size: 14px; }

    .rpt-search { position: relative; max-width: 460px; }
    .rpt-search i { position: absolute; left: 16px; top: 50%; margin-top: -8px; color: #8b8fb5; font-size: 15px; }
    .rpt-search input {
        width: 100%; height: 46px; border: 0; border-radius: 26px; padding: 0 18px 0 42px; font-size: 14px; color: var(--ink);
        background: #fff; box-shadow: 0 6px 18px rgba(20, 16, 70, .22); outline: none;
    }
    .rpt-search input:focus { box-shadow: 0 0 0 4px rgba(255,255,255,.35), 0 6px 18px rgba(20,16,70,.22); }

    .rpt-hero-stats { display: flex; flex-wrap: wrap; margin: 10px -6px -6px 0; }
    .rpt-hstat { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); border-radius: 14px; padding: 12px 16px; margin: 0 6px 6px 0; min-width: 138px; backdrop-filter: blur(4px); }
    .rpt-hstat small { display: block; font-size: 10px; letter-spacing: .8px; text-transform: uppercase; opacity: .8; }
    .rpt-hstat strong { display: block; font-size: 17px; font-weight: 700; margin-top: 2px; white-space: nowrap; }
    .rpt-hstat i { margin-right: 6px; opacity: .85; }

    /* ---------- Catatan ---------- */
    .rpt-note { display: flex; align-items: flex-start; background: #fff; border: 1px solid var(--line); border-left: 4px solid #3c8dbc; border-radius: 10px; padding: 10px 14px; margin-bottom: 10px; color: #475569; font-size: 13px; }
    .rpt-note i { color: #3c8dbc; margin: 3px 10px 0 0; }

    /* ---------- Tab kategori ---------- */
    .rpt-tabs { display: flex; flex-wrap: nowrap; overflow-x: auto; padding: 4px 2px 10px; margin: 8px 0 14px; -webkit-overflow-scrolling: touch; }
    .rpt-tabs::-webkit-scrollbar { height: 6px; }
    .rpt-tabs::-webkit-scrollbar-thumb { background: #d6d9e6; border-radius: 6px; }
    .rpt-chip {
        flex: 0 0 auto; display: inline-flex; align-items: center; padding: 8px 16px; margin-right: 10px; border-radius: 24px;
        background: #fff; border: 1px solid var(--line); color: #4b5563; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;
        box-shadow: 0 1px 2px rgba(16,24,40,.05); transition: all .18s ease;
    }
    .rpt-chip i { margin-right: 7px; color: #9aa3b5; transition: color .18s; }
    .rpt-chip:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16,24,40,.09); }
    .rpt-chip .rpt-count { margin-left: 8px; min-width: 22px; text-align: center; padding: 1px 7px; border-radius: 12px; font-size: 11px; background: #eef0f6; color: #5b6478; }
    .rpt-chip.active { background: linear-gradient(120deg, #4b46a1, #7d6bd6); border-color: transparent; color: #fff; box-shadow: 0 6px 14px rgba(96,92,168,.35); }
    .rpt-chip.active i { color: #fff; }
    .rpt-chip.active .rpt-count { background: rgba(255,255,255,.25); color: #fff; }

    /* ---------- Grup & kartu ---------- */
    .rpt-group { margin-bottom: 26px; }
    .rpt-group.is-hidden, .rpt-item.is-hidden, #rpt-empty.is-hidden { display: none !important; }
    .rpt-group-head { display: flex; align-items: center; margin-bottom: 14px; }
    .rpt-group-ico { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; margin-right: 11px; background: linear-gradient(135deg, var(--c1), var(--c2)); box-shadow: 0 4px 10px rgba(0,0,0,.14); }
    .rpt-group-head h3 { margin: 0; font-size: 17px; font-weight: 700; color: var(--ink); }
    .rpt-group-head span.rpt-group-count { margin-left: 10px; font-size: 12px; color: var(--muted); background: #eef0f6; padding: 2px 10px; border-radius: 12px; }

    .rpt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 18px; }
    .rpt-item { display: flex; animation: rptIn .28s ease both; }
    @keyframes rptIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    .rpt-card {
        position: relative; width: 100%; display: flex; flex-direction: column; padding: 18px 18px 16px; background: #fff;
        border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 2px 6px rgba(16,24,40,.05); overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .rpt-card:before { content: ""; position: absolute; left: 0; top: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--c1), var(--c2)); }
    .rpt-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(16,24,40,.12); border-color: transparent; }
    .rpt-card-head { display: flex; align-items: center; margin-bottom: 10px; }
    .rpt-ico { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; margin-right: 13px; background: linear-gradient(135deg, var(--c1), var(--c2)); box-shadow: 0 6px 14px rgba(0,0,0,.16); }
    .rpt-card-head h4 { margin: 0; font-size: 15px; font-weight: 700; line-height: 1.3; color: var(--ink); }
    .rpt-card p { color: var(--muted); font-size: 12.8px; line-height: 1.55; flex: 1 1 auto; margin: 0 0 12px; }
    .rpt-badges { margin-bottom: 14px; }
    .rpt-badge { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 2px 9px; border-radius: 10px; background: #f1f3f9; color: #5b6478; margin: 0 5px 4px 0; }
    .rpt-badge i { margin-right: 3px; }
    .rpt-badge.xls { background: #e8f6ee; color: #15803d; }
    .rpt-badge.pdf { background: #fdecec; color: #b91c1c; }

    .rpt-go {
        display: flex; align-items: center; justify-content: center; width: 100%; padding: 9px 12px; border-radius: 11px; border: 0; cursor: pointer;
        font-size: 13px; font-weight: 700; color: var(--c1); background: var(--bg-soft); transition: all .2s ease;
    }
    .rpt-go i { margin-right: 8px; transition: transform .2s; }
    .rpt-card:hover .rpt-go, .rpt-go:focus { background: linear-gradient(120deg, var(--c1), var(--c2)); color: #fff; outline: none; }
    .rpt-card:hover .rpt-go i { transform: translateY(2px); }

    /* ---------- Warna kategori ---------- */
    .rpt-blue   { --c1: #2f80c9; --c2: #4fb0f0; }
    .rpt-green  { --c1: #0c9a63; --c2: #34d399; }
    .rpt-orange { --c1: #e2860c; --c2: #fbbf24; }
    .rpt-red    { --c1: #d33c3c; --c2: #fb7185; }
    .rpt-purple { --c1: #5b52c4; --c2: #9f8cf0; }

    /* ---------- Kosong ---------- */
    #rpt-empty { text-align: center; color: #8a94a6; padding: 54px 0; background: #fff; border: 1px dashed #d6d9e6; border-radius: 16px; }
    #rpt-empty i { font-size: 38px; display: block; margin-bottom: 10px; color: #c3c8d8; }
    #rpt-empty a { cursor: pointer; font-weight: 600; }

    /* ---------- Modal ---------- */
    .rpt-modal .modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 60px rgba(16,24,40,.28); }
    .rpt-modal .modal-header { border: 0; padding: 20px 22px; color: #fff; background: linear-gradient(120deg, var(--c1), var(--c2)); display: flex; align-items: center; }
    .rpt-modal .modal-header:before, .rpt-modal .modal-header:after { display: none; }
    .rpt-modal .modal-header .close { position: absolute; right: 18px; top: 16px; color: #fff; opacity: .85; text-shadow: none; font-size: 26px; }
    .rpt-modal .modal-header .close:hover { opacity: 1; }
    .rpt-modal .rpt-mico { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 12px; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: 19px; margin-right: 14px; }
    .rpt-modal .modal-title { font-size: 17px; font-weight: 700; line-height: 1.25; color: #fff; }
    .rpt-modal .modal-title small { display: block; margin-top: 2px; font-size: 11.5px; font-weight: 400; color: rgba(255,255,255,.85); }
    .rpt-modal .modal-body { padding: 20px 22px 8px; }
    .rpt-modal .modal-footer { border: 0; background: var(--bg-soft); padding: 14px 22px; }
    .rpt-modal label { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #4b5563; }
    .rpt-modal .form-control, .rpt-modal .input-group-addon { border-radius: 10px; height: 40px; border-color: #dfe3ee; box-shadow: none; }
    .rpt-modal .input-group .form-control { border-radius: 0 10px 10px 0; }
    .rpt-modal .input-group-addon { border-radius: 10px 0 0 10px; background: #f6f7fb; color: #6b7280; }
    .rpt-modal .btn { border-radius: 10px; padding: 8px 18px; font-weight: 600; }
    .rpt-modal .btn-primary { border: 0; background: linear-gradient(120deg, var(--c1), var(--c2)); }
    .rpt-modal .btn-primary[disabled] { opacity: .7; }
    .rpt-modal .rpt-desc { color: #6b7280; margin-bottom: 16px; font-size: 13.5px; }
    .rpt-modal .rpt-range { background: #fff; cursor: pointer; }
    .rpt-modal .rpt-error { display: none; border-radius: 10px; }
    .rpt-modal .rpt-loading-note { display: none; color: #6b7280; font-size: 12px; margin: 4px 0 12px; }
    .rpt-modal .rpt-nodate { color: #8a94a6; font-size: 12.5px; }

    @media (max-width: 767px) {
        .rpt-hero { padding: 22px 18px; border-radius: 14px; }
        .rpt-hero h2 { font-size: 21px; }
        .rpt-hero-stats { margin-top: 4px; }
        .rpt-hstat { min-width: 0; flex: 1 1 calc(50% - 6px); }
        .rpt-grid { grid-template-columns: 1fr; }
    }
    @media (prefers-reduced-motion: reduce) { .rpt-item { animation: none; } .rpt-card, .rpt-chip, .rpt-go { transition: none; } }
</style>