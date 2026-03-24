    </main> 
</div> 

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const btnMenu = document.getElementById('btn-menu');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (btnMenu) {
        btnMenu.addEventListener('click', function() {
            sidebar.classList.add('show');
            overlay.classList.remove('d-none');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.add('d-none');
        });
    }
    
    const toast = document.querySelector('.esi-toast');
    if(toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
    

    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if(!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    const searchInput = document.getElementById('global-search');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const val = e.target.value.toLowerCase().trim();
            
            document.querySelectorAll('.gn-table tbody tr').forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(val) ? '' : 'none';
            });

            document.querySelectorAll('.gn-carte').forEach(carte => {
                if (carte.querySelector('.gn-table') || carte.querySelector('form') || carte.classList.contains('gn-stat')) return;
                
                const text = carte.innerText.toLowerCase();

                const wrapper = carte.closest('.col-md-6, .col-lg-4, .col-md-4') || carte;
                wrapper.style.display = text.includes(val) ? '' : 'none';
            });
        });
    }
</script>
<?php if (!empty($scriptsSupp))
    echo $scriptsSupp; ?>
</body>
</html>
