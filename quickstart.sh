#!/bin/bash
###############################################################################
# Legal Analyzer - Quick Start Script
# One-command setup for development
###############################################################################

set -e

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Legal Document Analyzer - Quick Start                    ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed"
    echo ""
    echo "Install PHP 7.4+ first:"
    echo "  Ubuntu/Debian: sudo apt-get install php7.4"
    echo "  macOS:         brew install php@7.4"
    exit 1
fi

echo "✓ PHP found: $(php -r 'echo PHP_VERSION;')"
echo ""

# Run deployment in development mode
echo "🚀 Deploying in development mode..."
./deploy.sh dev --skip-tests

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Setup Complete!                                           ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Next steps:"
echo ""
echo "  1. Start PHP built-in server:"
echo "     php -S localhost:8000"
echo ""
echo "  2. Open in browser:"
echo "     http://localhost:8000/qsgx_v2.php"
echo ""
echo "  3. Try analyzing a legal clause!"
echo ""
echo "For production deployment, see: DEPLOYMENT.md"
echo ""
