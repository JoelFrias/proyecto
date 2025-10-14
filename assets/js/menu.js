document.addEventListener('DOMContentLoaded', function() {
    // Elementos del menú
    const sidebar = document.getElementById('sidebar');
    const toggleMenu = document.getElementById('toggleMenu');
    const mobileToggle = document.getElementById('mobileToggle');
    const overlay = document.querySelector('.overlay');
    
    // Crear overlay si no existe
    let overlayElement = overlay;
    if (!overlayElement) {
        overlayElement = document.createElement('div');
        overlayElement.className = 'overlay';
        document.body.appendChild(overlayElement);
    }
    
    // Función para verificar si es vista móvil
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Toggle para el sidebar (botón dentro del sidebar)
    if (toggleMenu) {
        toggleMenu.addEventListener('click', function() {
            if (isMobile()) {
                sidebar.classList.toggle('mobile-active');
                overlayElement.classList.toggle('active');
                if (mobileToggle) mobileToggle.classList.toggle('hidden');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }
    
    // Toggle para móvil (botón fuera del sidebar)
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.add('mobile-active');
            overlayElement.classList.add('active');
            this.classList.add('hidden');
        });
    }
    
    // Cerrar al hacer clic en el overlay
    overlayElement.addEventListener('click', function() {
        sidebar.classList.remove('mobile-active');
        this.classList.remove('active');
        if (mobileToggle) mobileToggle.classList.remove('hidden');
    });
    
    // Ajustar al cambiar el tamaño de la ventana
    window.addEventListener('resize', function() {
        if (!isMobile() && sidebar.classList.contains('mobile-active')) {
            sidebar.classList.remove('mobile-active');
            overlayElement.classList.remove('active');
            if (mobileToggle) mobileToggle.classList.remove('hidden');
        }
    });
});

// Mantener las funciones de navegación existentes
function navigateTo(page) {
    window.location.href = page;
}