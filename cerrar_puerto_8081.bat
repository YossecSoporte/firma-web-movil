@echo off
chcp 65001 >nul
title Cerrar Puerto 8081 - FirmEasy Web

echo ==========================================
echo  Eliminando regla Firewall Puerto 8081
echo ==========================================
echo.

netsh advfirewall firewall delete rule name="FirmEasy Web (Puerto 8081)"

if %errorlevel% equ 0 (
    echo [OK] Regla eliminada
) else (
    echo [ERROR] No se encontro la regla o no hay permisos
)

pause