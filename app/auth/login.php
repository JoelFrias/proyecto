<?php
// Redirigir si ya existe una sesion
session_start();

if (isset($_SESSION['username'])) {
    header('Location: ../');
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>EasyPOS - Iniciar Sesión</title>
    <link rel="shortcut icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-img {
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.5px;
        }

        .error-message,
        .success-message,
        .warning-message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.3s ease;
            font-size: 14px;
        }

        .error-message {
            background-color: #fee;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .warning-message {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-btn {
            background: none;
            border: none;
            color: inherit;
            font-size: 20px;
            cursor: pointer;
            padding: 0 5px;
            line-height: 1;
            transition: opacity 0.2s;
        }

        .close-btn:hover {
            opacity: 0.7;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
            color: #1a1a1a;
        }

        .input-group.password-field input {
            padding-right: 45px;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group input:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        .input-group input::placeholder {
            color: #9ca3af;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #3b82f6;
        }

        .toggle-password:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .eye-icon, .eye-slash-icon {
            width: 20px;
            height: 20px;
        }

        .eye-slash-icon {
            display: none;
        }

        .checkbox-group {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            accent-color: #2c3e50;
        }

        .checkbox-group label {
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            user-select: none;
            margin: 0;
        }

        .checkbox-group input[type="checkbox"]:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .checkbox-group label.disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            position: relative;
        }

        .btn:hover:not(:disabled) {
            background: #384f66ff;
        }

        .btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn.loading {
            color: transparent;
        }

        .spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff40;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .btn.loading .spinner {
            display: block;
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                background: white;
                padding: 0;
                min-height: 100dvh;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
                padding: 40px 24px;
                max-width: 100%;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .logo-img {
                width: 80px;
                height: 80px;
                font-size: 40px;
            }

            .logo-text {
                font-size: 24px;
            }

            .container {
                padding: 32px 20px;
            }

            .toggle-password {
                top: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="container">

         <div class="logo">
            <img src="../../assets/img/logo.png" alt="EasyPOS Logo" class="logo-img">
            <div class="logo-text">EasyPOS</div>
        </div>

        <div id="messageContainer"></div>

        <form id="loginForm" autocomplete="off">
            <div class="input-group">
                <label for="username">Usuario</label>
                <input 
                    type="text" 
                    name="username" 
                    id="username" 
                    placeholder="Ingresa tu usuario" 
                    autocomplete="off"
                    required>
            </div>

            <div class="input-group password-field">
                <label for="password">Contraseña</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    placeholder="Ingresa tu contraseña" 
                    autocomplete="off"
                    required>
                <button type="button" class="toggle-password" id="togglePassword">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="rememberMe" name="rememberMe">
                <label for="rememberMe">Mantener la sesión abierta</label>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <span id="btnText">Ingresar</span>
                <span class="spinner"></span>
            </button>
        </form>
    </div>
    
    <script>
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const rememberMeCheckbox = document.getElementById('rememberMe');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const messageContainer = document.getElementById('messageContainer');

        // Manejar el envío del formulario
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();
            const rememberMe = rememberMeCheckbox.checked;

            // Validación básica
            if (!username || !password) {
                showMessage('Usuario y contraseña son requeridos.', 'error');
                return;
            }

            // Deshabilitar el formulario
            setFormLoading(true);

            try {
                const response = await fetch('../../api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password,
                        remember_me: rememberMe
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Inicio de sesión exitoso. Redirigiendo...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '../../';
                    }, 500);
                } else {
                    const messageType = data.disabled ? 'warning' : 'error';
                    showMessage(data.error || 'Error al iniciar sesión.', messageType);
                    setFormLoading(false);
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Error de conexión. Por favor, intenta nuevamente.', 'error');
                setFormLoading(false);
            }
        });

        // Toggle password visibility
        togglePasswordBtn.addEventListener('click', () => {
            const eyeIcon = document.querySelector('.eye-icon');
            const eyeSlashIcon = document.querySelector('.eye-slash-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeSlashIcon.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeSlashIcon.style.display = 'none';
            }
        });

        // Función para mostrar mensajes
        function showMessage(message, type = 'error') {
            let messageClass = 'error-message';
            
            if (type === 'success') {
                messageClass = 'success-message';
            } else if (type === 'warning') {
                messageClass = 'warning-message';
            }
            
            const messageHTML = `
                <div class="${messageClass}" id="message-alert">
                    <span>${message}</span>
                    <button class="close-btn" onclick="closeMessage()" type="button">×</button>
                </div>
            `;
            messageContainer.innerHTML = messageHTML;
        }

        // Función para cerrar el mensaje
        function closeMessage() {
            const messageAlert = document.getElementById('message-alert');
            if (messageAlert) {
                messageAlert.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    messageAlert.remove();
                }, 300);
            }
        }

        // Función para manejar el estado de carga
        function setFormLoading(loading) {
            submitBtn.disabled = loading;
            usernameInput.disabled = loading;
            passwordInput.disabled = loading;
            rememberMeCheckbox.disabled = loading;
            togglePasswordBtn.disabled = loading;
            
            const checkboxLabel = document.querySelector('.checkbox-group label');
            if (loading) {
                submitBtn.classList.add('loading');
                btnText.textContent = 'Iniciando sesión...';
                checkboxLabel.classList.add('disabled');
            } else {
                submitBtn.classList.remove('loading');
                btnText.textContent = 'Ingresar';
                checkboxLabel.classList.remove('disabled');
            }
        }
    </script>
</body>
</html>