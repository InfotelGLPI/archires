#!/usr/bin/env bash

#
# -------------------------------------------------------------------------
# archires plugin for GLPI
# -------------------------------------------------------------------------
#
# LICENSE
#
# This file is part of archires.
#
# archires is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# archires is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with archires. If not, see <http://www.gnu.org/licenses/>.
# -------------------------------------------------------------------------
# @copyright Copyright (C) 2009-2026 by archires plugin team.
# @license   AGPLv3 https://www.gnu.org/licenses/agpl-3.0.html
# @link      https://github.com/InfotelGLPI/archires
# --------------------------------------------------------------------------
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
HEADER_FILE="$SCRIPT_DIR/HEADER"

if [[ ! -f "$HEADER_FILE" ]]; then
    echo "Error: header file not found: $HEADER_FILE"
    exit 1
fi

# Single raw header file for every language (PHP + Twig), mirroring glpi/tools.
php "$SCRIPT_DIR/regenerate_headers.php" "$PLUGIN_DIR" "$HEADER_FILE" "$@"
