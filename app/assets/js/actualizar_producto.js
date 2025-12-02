let otraVentana;

document.getElementById("openModalBtn").addEventListener("click", function () {
    if (!otraVentana || otraVentana.closed) {
        // Abrimos la otra pestaña si no está abierta
        otraVentana = window.open("producto_actualizar.php", "_blank");
    } else {
        // Si ya está abierta, enviamos el mensaje directamente
        otraVentana.postMessage("abrirModal", "*");
    }
});