document.addEventListener('DOMContentLoaded', function() {
  // Elementos del DOM
  const modal = document.getElementById('tipoProductoModal');
  const btnAbrirModal = document.getElementById('openModalBtn');
  const btnCerrar = document.querySelector('.close');
  const btnAgregarTipo = document.getElementById('btnAgregarTipo');
  const nuevoTipoInput = document.getElementById('nuevoTipoNombre');
  const tiposTable = document.getElementById('tiposProductoBody');
  const searchInput = document.getElementById('searchTipo');
  const mensajeError = document.getElementById('mensajeError');
  
  // Función para actualizar el select de tipo de producto en la página principal
  function actualizarSelectTipoProducto(tipos) {
    // Obtener el select de tipo de producto en la página principal
    const selectTipoProducto = document.getElementById('tipo');
    
    if (selectTipoProducto) {
      // Guardar el valor seleccionado actual
      const valorSeleccionado = selectTipoProducto.value;
      
      // Limpiar las opciones actuales (excepto la opción por defecto)
      const opcionPorDefecto = selectTipoProducto.querySelector('option[disabled]');
      selectTipoProducto.innerHTML = '';
      
      // Agregar de nuevo la opción por defecto
      if (opcionPorDefecto) {
        selectTipoProducto.appendChild(opcionPorDefecto);
      }
      
      // Agregar las opciones de tipos
      tipos.forEach(tipo => {
        const option = document.createElement('option');
        option.value = tipo.id;
        option.textContent = tipo.descripcion;
        selectTipoProducto.appendChild(option);
      });
      
      // Restaurar el valor seleccionado si existe
      if (valorSeleccionado && selectTipoProducto.querySelector(`option[value="${valorSeleccionado}"]`)) {
        selectTipoProducto.value = valorSeleccionado;
      }
    }
  }

  // Evitar que el botón de abrir modal envíe el formulario
  btnAbrirModal.type = 'button';
  
  // Abrir el modal
  btnAbrirModal.addEventListener('click', function(e) {
    e.preventDefault();
    modal.style.display = 'block';
    cargarTiposProducto();
  });
  
  // Cerrar el modal
  btnCerrar.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  // Cerrar el modal haciendo clic fuera
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
  
  // Búsqueda en tiempo real
  searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = tiposTable.querySelectorAll('tr');
    
    rows.forEach(row => {
      const descripcion = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
      row.style.display = descripcion.includes(searchTerm) ? '' : 'none';
    });
  });
  
  // Agregar nuevo tipo de producto
  btnAgregarTipo.addEventListener('click', function() {
    const nombre = nuevoTipoInput.value.trim();
    
    if (!nombre) {
      mostrarError('El nombre del tipo no puede estar vacío');
      return;
    }
    
    agregarTipoProducto(nombre);
  });
  
  // Cargar tipos de producto desde el servidor
  function cargarTiposProducto() {
    tiposTable.innerHTML = '<tr><td colspan="3" class="loading">Cargando...</td></tr>';
    
    fetch('../../API/productos/producto_tipo.php?action=getAll')
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
      })
      .then(data => {
        if (data.status === 'success') {
          renderizarTiposProducto(data.tipos);
          actualizarSelectTipoProducto(data.tipos);
        } else {
          tiposTable.innerHTML = `<tr><td colspan="3" class="error-message">${data.message}</td></tr>`;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        tiposTable.innerHTML = '<tr><td colspan="3" class="error-message">Error al cargar los datos</td></tr>';
      });
  }
  
  // Renderizar los tipos de producto en la tabla
  function renderizarTiposProducto(tipos) {
    if (tipos.length === 0) {
      tiposTable.innerHTML = '<tr><td colspan="3" class="empty-state">No hay tipos de producto registrados</td></tr>';
      return;
    }
    
    tiposTable.innerHTML = '';
    tipos.forEach(tipo => {
      const row = document.createElement('tr');
      row.dataset.id = tipo.id;
      row.innerHTML = `
        <td>${tipo.id}</td>
        <td>${tipo.descripcion}</td>
        <td>
          <div class="action-buttons">
            <button class="btn-edit"><i class="fas fa-edit"></i> Editar</button>
            <!-- <button class="btn-delete"><i class="fas fa-trash"></i> Eliminar</button> -->
          </div>
        </td>
      `;
      
      // Asignar eventos a los botones
      const btnEdit = row.querySelector('.btn-edit');
      const btnDelete = row.querySelector('.btn-delete');
      
      btnEdit.addEventListener('click', () => activarModoEdicion(row, tipo.descripcion));
      // btnDelete.addEventListener('click', () => eliminarTipoProducto(tipo.id));
      
      tiposTable.appendChild(row);
    });
  }
  
  // Activar modo edición en una fila
  function activarModoEdicion(row, descripcionActual) {
    const celdaDescripcion = row.querySelector('td:nth-child(2)');
    const celdaAcciones = row.querySelector('td:nth-child(3)');
    const tipoId = row.dataset.id;
    
    // Guardar el contenido original para restaurarlo si se cancela
    row.dataset.originalContent = celdaDescripcion.innerHTML;
    
    // Cambiar a modo edición
    celdaDescripcion.innerHTML = `<input type="text" value="${descripcionActual}" class="edit-input">`;
    celdaAcciones.innerHTML = `
      <div class="action-buttons">
        <button class="btn-save"><i class="fas fa-check"></i> Guardar</button>
        <button class="btn-cancel"><i class="fas fa-times"></i> Cancelar</button>
      </div>
    `;
    
    // Asignar eventos a los nuevos botones
    const btnSave = row.querySelector('.btn-save');
    const btnCancel = row.querySelector('.btn-cancel');
    
    btnSave.addEventListener('click', () => guardarEdicion(tipoId, row));
    btnCancel.addEventListener('click', () => cancelarEdicion(row));
    
    // Enfocar el input
    const input = celdaDescripcion.querySelector('input');
    input.focus();
    input.select();
  }
  
  // Guardar los cambios de la edición
  function guardarEdicion(tipoId, row) {
    const nuevoNombre = row.querySelector('.edit-input').value.trim();
    
    if (!nuevoNombre) {
      mostrarError('El nombre no puede estar vacío');
      return;
    }
    
    // Datos para enviar al servidor
    const formData = new FormData();
    formData.append('id', tipoId);
    formData.append('descripcion', nuevoNombre);
    formData.append('action', 'update');
    
    fetch('../../API/productos/producto_tipo.php', {
      method: 'POST',
      body: formData
    })
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
      })
      .then(data => {
        if (data.status === 'success') {
          // Actualizar la interfaz
          row.querySelector('td:nth-child(2)').textContent = nuevoNombre;
          row.querySelector('td:nth-child(3)').innerHTML = `
            <div class="action-buttons">
              <button class="btn-edit"><i class="fas fa-edit"></i> Editar</button>
              <!-- <button class="btn-delete"><i class="fas fa-trash"></i> Eliminar</button> -->
            </div>
          `;
          
          // Volver a asignar eventos
          const btnEdit = row.querySelector('.btn-edit');
          const btnDelete = row.querySelector('.btn-delete');
          
          btnEdit.addEventListener('click', () => activarModoEdicion(row, nuevoNombre));
          // btnDelete.addEventListener('click', () => eliminarTipoProducto(tipoId));
          
          // Actualizar el select de la página principal
          fetch('../../API/productos/producto_tipo.php?action=getAll')
            .then(response => response.json())
            .then(data => {
              if (data.status === 'success') {
                actualizarSelectTipoProducto(data.tipos);
              }
            });
          
          // Mostrar mensaje de éxito
          Swal.fire({
            icon: 'success',
            title: '¡Actualizado!',
            text: 'Tipo de producto actualizado correctamente',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
          });
        } else {
          mostrarError(data.message || 'Error al actualizar');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarError('Error de conexión');
      });
  }
  
  // Cancelar la edición
  function cancelarEdicion(row) {
    // Restaurar contenido original
    const celdaDescripcion = row.querySelector('td:nth-child(2)');
    const celdaAcciones = row.querySelector('td:nth-child(3)');
    const tipoId = row.dataset.id;
    const descripcion = celdaDescripcion.querySelector('input').value;
    
    celdaDescripcion.textContent = row.dataset.originalContent;
    celdaAcciones.innerHTML = `
      <div class="action-buttons">
        <button class="btn-edit"><i class="fas fa-edit"></i> Editar</button>
        <!-- <button class="btn-delete"><i class="fas fa-trash"></i> Eliminar</button> -->
      </div>
    `;
    
    // Volver a asignar eventos
    const btnEdit = row.querySelector('.btn-edit');
    const btnDelete = row.querySelector('.btn-delete');
    
    btnEdit.addEventListener('click', () => activarModoEdicion(row, descripcion));
    // btnDelete.addEventListener('click', () => eliminarTipoProducto(tipoId));
  }
  
  // Agregar nuevo tipo de producto
  function agregarTipoProducto(nombre) {
    // Deshabilitar el botón mientras se procesa
    btnAgregarTipo.disabled = true;
    
    // Datos para enviar al servidor
    const formData = new FormData();
    formData.append('descripcion', nombre);
    formData.append('action', 'create');
    
    fetch('../../API/productos/producto_tipo.php', {
      method: 'POST',
      body: formData
    })
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
      })
      .then(data => {
        if (data.status === 'success') {
          // Limpiar el campo de entrada
          nuevoTipoInput.value = '';
          
          // Recargar la tabla y actualizar el select
          cargarTiposProducto();
          
          // Mostrar mensaje de éxito
          Swal.fire({
            icon: 'success',
            title: '¡Agregado!',
            text: 'Tipo de producto agregado correctamente',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
          });
          
          // Limpiar cualquier mensaje de error
          mensajeError.textContent = '';
        } else {
          mostrarError(data.message || 'Error al agregar');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarError('Error de conexión');
      })
      .finally(() => {
        // Habilitar el botón nuevamente
        btnAgregarTipo.disabled = false;
      });
  }
  
  // Eliminar tipo de producto
  function eliminarTipoProducto(tipoId) {
    // Confirmar antes de eliminar
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción no se puede deshacer',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        // Datos para enviar al servidor
        const formData = new FormData();
        formData.append('id', tipoId);
        formData.append('action', 'delete');
        
        fetch('../../API/productos/producto_tipo.php', {
          method: 'POST',
          body: formData
        })
          .then(response => {
            if (!response.ok) {
              throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
          })
          .then(data => {
            if (data.status === 'success') {
              // Eliminar la fila de la tabla
              const row = document.querySelector(`tr[data-id="${tipoId}"]`);
              if (row) {
                row.remove();
              }
              
              // Si no quedan filas, mostrar mensaje
              if (tiposTable.children.length === 0) {
                tiposTable.innerHTML = '<tr><td colspan="3" class="empty-state">No hay tipos de producto registrados</td></tr>';
              }
              
              // Actualizar el select de la página principal
              fetch('../../API/productos/producto_tipo.php?action=getAll')
                .then(response => response.json())
                .then(data => {
                  if (data.status === 'success') {
                    actualizarSelectTipoProducto(data.tipos);
                  }
                });
              
              // Mostrar mensaje de éxito
              Swal.fire({
                icon: 'success',
                title: '¡Eliminado!',
                text: 'Tipo de producto eliminado correctamente',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudo eliminar el tipo de producto'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Error de conexión'
            });
          });
      }
    });
  }
  
  // Mostrar mensaje de error
  function mostrarError(mensaje) {
    mensajeError.textContent = mensaje;
    setTimeout(() => {
      mensajeError.textContent = '';
    }, 5000);
  }
});