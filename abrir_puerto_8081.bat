@echo off
chcp 65001 >nul
title Abrir Puerto 8081 - FirmEasy Web

echo ==========================================
echo  Abriendo puerto 8081 en Firewall Windows
echo  IP: localhost:8081
echo  Para acceso desde cualquier dispositivo en la red
echo ==========================================
echo.

netsh advfirewall firewall delete rule name="FirmEasy Web (Puerto 8081)"
netsh advfirewall firewall add rule name="FirmEasy Web (Puerto 8081)" dir=in action=allow protocol=TCP localport=8081 profile=any

if %errorlevel% equ 0 (
    echo [OK] Regla creada correctamente para localhost:8081 (toda la red)
) else (
    echo [ERROR] No se pudo crear la regla (ejecutar como Administrador)
)

echo.
echo Verificando regla...
netsh advfirewall firewall show rule name="FirmEasy Web (Puerto 8081)"
echo.
pause