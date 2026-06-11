</main>
</div><!-- .main-area -->
</div><!-- .app-wrap -->

<script>
function toggleSidebar() {
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('sidebarOverlay');
    const open = sb.classList.toggle('open');
    ov.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
}
// Close sidebar on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
});
</script>
</body>
</html>
