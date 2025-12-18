<style>
    /* Sembunyikan sidebar dan sesuaikan padding konten */
    .fi-sidebar {
        display: none !important;
    }

    .fi-main {
        padding-left: 0 !important;
    }

    /* Jaga agar konten melebar penuh */
    .fi-topbar {
        position: sticky;
        top: 0;
        z-index: 40;
    }

    /* Rapikan tinggi topbar bila menu banyak */
    .fi-topbar nav {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>
