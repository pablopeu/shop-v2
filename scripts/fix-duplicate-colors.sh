#!/bin/bash
##
# Script para corregir paletas con colores duplicados
# Detecta paletas con 2 o más colores repetidos y los reemplaza
# con colores armoniosos generados usando teoría del color
##

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="$SCRIPT_DIR/fix-duplicate-colors.php"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Corrector de Paletas con Colores Duplicados              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Verificar que existe el script PHP
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "❌ Error: No se encuentra el script PHP"
    exit 1
fi

# Verificar que existe PHP
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP no está instalado"
    exit 1
fi

# Ejecutar el script PHP
php "$PHP_SCRIPT"

exit_code=$?

if [ $exit_code -eq 0 ]; then
    echo ""
    echo "✅ Proceso completado exitosamente"
else
    echo ""
    echo "❌ El proceso terminó con errores (código: $exit_code)"
fi

exit $exit_code
