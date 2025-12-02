
// Detectar si la tabla tiene overflow horizontal
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.querySelector('.table-container');
    const tableWrapper = document.querySelector('.table-wrapper');
    const swipeHint = document.querySelector('.swipe-hint');
    
    function checkOverflow() {
        if (tableContainer && tableContainer.scrollWidth > tableContainer.clientWidth) {
            // Hay overflow horizontal
            swipeHint.style.display = 'flex';
        } else {
            // No hay overflow horizontal
            swipeHint.style.display = 'none';
        }
    }
    
    // Comprobar al cargar y al cambiar el tamaño de la ventana
    if (tableContainer && tableWrapper && swipeHint) {
        checkOverflow();
        window.addEventListener('resize', checkOverflow);
        
        // También mostrar cuando el usuario interactúa con la tabla
        tableContainer.addEventListener('touchstart', function() {
            swipeHint.style.display = 'flex';
            // Ocultar después de 3 segundos
            setTimeout(function() {
                swipeHint.style.display = 'none';
            }, 3000);
        }, { passive: true });
    }
});
