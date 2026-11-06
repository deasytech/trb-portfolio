<style>
    :root {
        --trb-side-1: #0b1b3a;
        --trb-side-2: #0f2a5c;
        --trb-side-3: #123572;
        --trb-side-text: #b6c4e0;
        --trb-side-muted: #7f93b8;
    }

    /* Sidebar: layered navy gradient with soft shade bands */
    .fi-sidebar {
        background:
            radial-gradient(120% 60% at 0% 0%, rgba(59, 130, 246, .22), transparent 60%),
            linear-gradient(180deg, var(--trb-side-1) 0%, var(--trb-side-2) 55%, var(--trb-side-3) 100%) !important;
        border-inline-end: 1px solid rgba(255, 255, 255, .06) !important;
        box-shadow: 4px 0 24px -12px rgba(8, 20, 50, .45);
    }
    .fi-sidebar-header-ctn,
    .fi-sidebar-header {
        background: rgba(255, 255, 255, .04) !important;
        border-bottom: 1px solid rgba(255, 255, 255, .08) !important;
        box-shadow: none !important;
    }
    .fi-sidebar-header .fi-logo,
    .fi-sidebar-header a,
    .fi-sidebar-header-logo-ctn * { color: #fff !important; font-weight: 600; letter-spacing: .01em; }
    .fi-sidebar-nav { padding: .75rem .625rem 1rem !important; row-gap: .25rem !important; }

    /* Group headings in their own darker band */
    .fi-sidebar-group-btn {
        padding: .45rem .6rem !important;
        border-radius: .5rem;
        background: rgba(5, 12, 30, .32);
        margin-top: .5rem;
    }
    .fi-sidebar-group-label {
        color: var(--trb-side-muted) !important;
        font-size: .68rem !important;
        font-weight: 600 !important;
        letter-spacing: .09em;
        text-transform: uppercase;
    }
    .fi-sidebar-group-btn svg,
    .fi-sidebar-group-collapse-btn svg { color: var(--trb-side-muted) !important; }
    .fi-sidebar-group.fi-active > .fi-sidebar-group-btn { background: rgba(59, 130, 246, .18); }
    .fi-sidebar-group.fi-active > .fi-sidebar-group-btn .fi-sidebar-group-label { color: #dbe7ff !important; }

    /* Tree: indented children joined by a vertical guide with elbow connectors */
    .fi-sidebar-group-items {
        position: relative;
        margin: .25rem 0 .25rem 1.1rem !important;
        padding-inline-start: .85rem;
        border-inline-start: 1px solid rgba(148, 170, 214, .28);
        row-gap: .125rem !important;
    }
    .fi-sidebar-group-items > .fi-sidebar-item { position: relative; }
    .fi-sidebar-group-items > .fi-sidebar-item::before {
        content: "";
        position: absolute;
        inset-inline-start: -.85rem;
        top: 1.1rem;
        width: .6rem;
        border-top: 1px solid rgba(148, 170, 214, .28);
    }
    .fi-sidebar-group-items > .fi-sidebar-item.fi-active::before { border-top-color: #60a5fa; }
    .fi-sidebar-item-grouped-border { display: none !important; }

    /* Items */
    .fi-sidebar-item-btn {
        border-radius: .5rem !important;
        padding: .45rem .65rem !important;
        transition: background-color .15s, transform .15s;
    }
    .fi-sidebar-item-label, .fi-sidebar-item-icon { color: var(--trb-side-text) !important; }
    .fi-sidebar-item-btn:hover { background: rgba(255, 255, 255, .08) !important; }
    .fi-sidebar-item-btn:hover .fi-sidebar-item-label,
    .fi-sidebar-item-btn:hover .fi-sidebar-item-icon { color: #fff !important; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: linear-gradient(90deg, rgba(59, 130, 246, .38), rgba(59, 130, 246, .12)) !important;
        box-shadow: inset 2px 0 0 #60a5fa;
    }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color: #fff !important; font-weight: 600; }

    .fi-sidebar-footer, .fi-sidebar-database-notifications-btn { color: var(--trb-side-text); }

    /* Content: full width, tidier surface */
    .fi-main { max-width: none !important; width: 100%; padding-inline: 1.5rem !important; }
    .fi-main-ctn { background: #f4f6fb; }
    .dark .fi-main-ctn { background: transparent; }
    .fi-topbar { box-shadow: 0 1px 0 rgba(15, 23, 42, .06); }
    .fi-header-heading { font-weight: 700; letter-spacing: -.01em; }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat {
        border-radius: .85rem !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 6px 18px -10px rgba(15, 23, 42, .12) !important;
    }
    .fi-ta-header-cell { background: rgba(15, 42, 92, .04); text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; }
    .dark .fi-ta-header-cell { background: rgba(255, 255, 255, .04); }
    .fi-btn { border-radius: .55rem; }
</style>
